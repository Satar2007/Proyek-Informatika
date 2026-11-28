<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PaymentTokenHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function paymentFor(User $cashier, string $reservation = 'reserved'): Payment
    {
        $transaction = Transaction::create([
            'kode_transaksi' => 'TEST-PAYMENT-'.uniqid(),
            'user_id' => $cashier->id,
            'nama_pelanggan' => 'Payment Test',
            'total' => 10000,
            'pajak' => 0,
            'diskon' => 0,
            'grand_total' => 10000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'qris',
            'stock_reservation_status' => $reservation,
        ]);

        return Payment::create([
            'transaction_id' => $transaction->id,
            'metode' => 'qris',
            'external_id' => 'PAY-TEST-'.uniqid(),
            'payment_status' => 'waiting',
        ]);
    }

    public function test_locked_state_conflict_returns_409_without_calling_midtrans(): void
    {
        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $payment = $this->paymentFor($cashier, 'none');

        $fake = new class extends MidtransService {
            public int $calls = 0;

            public function __construct() {}

            public function buatSnapToken(Payment $payment): string
            {
                $this->calls++;
                return 'SHOULD-NOT-BE-CALLED';
            }
        };

        $this->app->instance(MidtransService::class, $fake);

        $response = $this
            ->actingAs($cashier)
            ->post(route('payment.token', $payment->id));

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Transaksi tidak lagi menunggu pembayaran.');

        $this->assertSame(0, $fake->calls);
    }

    public function test_snap_token_network_call_runs_outside_database_transaction(): void
    {
        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $payment = $this->paymentFor($cashier);
        $transactionLevelBeforeRequest = DB::transactionLevel();

        $fake = new class extends MidtransService {
            public int $transactionLevelDuringRequest = -1;

            public function __construct() {}

            public function buatSnapToken(Payment $payment): string
            {
                $this->transactionLevelDuringRequest = DB::transactionLevel();
                return 'TEST-SNAP-TOKEN-OUTSIDE-TRANSACTION';
            }
        };

        $this->app->instance(MidtransService::class, $fake);

        $this->actingAs($cashier)
            ->post(route('payment.token', $payment->id))
            ->assertOk()
            ->assertJsonPath('snap_token', 'TEST-SNAP-TOKEN-OUTSIDE-TRANSACTION');

        $this->assertSame(
            $transactionLevelBeforeRequest,
            $fake->transactionLevelDuringRequest
        );
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'midtrans_snap_token' => 'TEST-SNAP-TOKEN-OUTSIDE-TRANSACTION',
        ]);
    }

    public function test_midtrans_failure_still_returns_503(): void
    {
        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $payment = $this->paymentFor($cashier);

        $fake = new class extends MidtransService {
            public function __construct() {}

            public function buatSnapToken(Payment $payment): string
            {
                throw new RuntimeException('Simulasi kegagalan Midtrans.');
            }
        };

        $this->app->instance(MidtransService::class, $fake);

        $response = $this
            ->actingAs($cashier)
            ->post(route('payment.token', $payment->id));

        $response
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Pembayaran Midtrans belum dapat dibuka.');
    }

    public function test_status_synchronization_requires_post_and_keeps_get_read_only(): void
    {
        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $payment = $this->paymentFor($cashier);
        $payment->update([
            'midtrans_snap_token' => 'TEST-SNAP-TOKEN',
        ]);

        $fake = new class extends MidtransService {
            public int $calls = 0;

            public function __construct() {}

            public function ambilStatus(Payment $payment): ?object
            {
                $this->calls++;
                return null;
            }
        };

        $this->app->instance(MidtransService::class, $fake);

        $this->actingAs($cashier)
            ->get(route('payment.check', $payment->id))
            ->assertStatus(405);

        $this->assertSame(0, $fake->calls);

        $this->actingAs($cashier)
            ->post(route('payment.check', $payment->id))
            ->assertOk()
            ->assertJsonPath('status', 'waiting');

        $this->assertSame(1, $fake->calls);
    }

    public function test_legacy_manual_confirmation_cannot_mark_payment_paid(): void
    {
        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $payment = $this->paymentFor($cashier);

        try {
            app(PaymentService::class)->konfirmasiPayment($payment);
            $this->fail('Manual confirmation must be disabled.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Konfirmasi pembayaran manual dinonaktifkan',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'waiting',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $payment->transaction_id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_cashier_cannot_request_token_for_another_cashier_payment(): void
    {
        $ownerCashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $otherCashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $payment = $this->paymentFor($ownerCashier);

        $fake = new class extends MidtransService {
            public int $calls = 0;

            public function __construct() {}

            public function buatSnapToken(Payment $payment): string
            {
                $this->calls++;
                return 'SHOULD-NOT-BE-CALLED';
            }
        };

        $this->app->instance(MidtransService::class, $fake);

        $response = $this
            ->actingAs($otherCashier)
            ->post(route('payment.token', $payment->id));

        $response->assertNotFound();
        $this->assertSame(0, $fake->calls);
    }
}
