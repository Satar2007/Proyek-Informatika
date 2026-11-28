<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ObjectAuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $db = DB::connection();

        if (
            $db->getDriverName() !== 'mysql'
            || $db->getDatabaseName() !== 'satar_integrated_test'
            || (bool) config('midtrans.is_production')
        ) {
            throw new RuntimeException(
                'WRONG TEST DATABASE OR PRODUCTION MODE. STOP.'
            );
        }
    }

    private function transactionFor(
        User $cashier,
        array $overrides = []
    ): Transaction {
        return Transaction::create(array_merge([
            'kode_transaksi' => 'OBJ-' . strtoupper(bin2hex(random_bytes(5))),
            'user_id' => $cashier->id,
            'nama_pelanggan' => 'Object Authorization Test',
            'total' => 10000,
            'pajak' => 0,
            'diskon' => 0,
            'grand_total' => 10000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'qris',
            'stock_reservation_status' => 'reserved',
        ], $overrides));
    }

    private function paymentFor(
        Transaction $transaction,
        array $overrides = []
    ): Payment {
        return Payment::create(array_merge([
            'transaction_id' => $transaction->id,
            'metode' => 'qris',
            'external_id' => 'PAY-OBJ-' . strtoupper(bin2hex(random_bytes(5))),
            'payment_status' => 'waiting',
        ], $overrides));
    }

    public function test_cashier_cannot_view_another_cashiers_transaction(): void
    {
        $a = User::factory()->create(['role' => 'kasir']);
        $b = User::factory()->create(['role' => 'kasir']);

        $transaction = $this->transactionFor($b);

        $this->actingAs($a)
            ->get(route('transaksi.show', $transaction->id))
            ->assertNotFound();
    }

    public function test_cashier_cannot_view_or_check_another_cashiers_payment(): void
    {
        $a = User::factory()->create(['role' => 'kasir']);
        $b = User::factory()->create(['role' => 'kasir']);

        $transaction = $this->transactionFor($b);
        $payment = $this->paymentFor($transaction);

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

        $this->actingAs($a)
            ->get(route('payment.show', $payment->id))
            ->assertNotFound();

        $this->actingAs($a)
            ->post(route('payment.check', $payment->id))
            ->assertNotFound();

        $this->assertSame(
            0,
            $fake->calls,
            'Unauthorized payment must never contact Midtrans.'
        );
    }

    public function test_cashier_cannot_print_another_cashiers_paid_receipt(): void
    {
        $a = User::factory()->create(['role' => 'kasir']);
        $b = User::factory()->create(['role' => 'kasir']);

        $transaction = $this->transactionFor($b, [
            'status' => 'success',
            'payment_status' => 'paid',
        ]);

        $payment = $this->paymentFor($transaction, [
            'payment_status' => 'paid',
        ]);

        $this->actingAs($a)
            ->get(route('payment.struk', $payment->id))
            ->assertNotFound();
    }

    public function test_cashier_cannot_mutate_another_cashiers_transaction(): void
    {
        $a = User::factory()->create(['role' => 'kasir']);
        $b = User::factory()->create(['role' => 'kasir']);

        $transaction = $this->transactionFor($b, [
            'payment_method' => 'cash',
        ]);

        $id = $transaction->id;

        $this->actingAs($a)
            ->post(route('transaksi.cancel', $id))
            ->assertNotFound();

        $this->actingAs($a)
            ->post(route('transaksi.expire', $id))
            ->assertNotFound();

        $this->actingAs($a)
            ->delete(route('transaksi.destroy', $id))
            ->assertNotFound();

        $this->assertDatabaseHas('transactions', [
            'id' => $id,
            'user_id' => $b->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_admin_can_access_another_cashiers_transaction_and_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);

        $transaction = $this->transactionFor($cashier);
        $payment = $this->paymentFor($transaction);

        $this->actingAs($admin)
            ->get(route('transaksi.show', $transaction->id))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('payment.show', $payment->id))
            ->assertOk();
    }

    public function test_owner_can_read_transaction_but_cannot_access_pos_payment(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $cashier = User::factory()->create(['role' => 'kasir']);

        $transaction = $this->transactionFor($cashier);
        $payment = $this->paymentFor($transaction);

        $this->actingAs($owner)
            ->get(route('transaksi.show', $transaction->id))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('payment.show', $payment->id))
            ->assertForbidden();
    }
}