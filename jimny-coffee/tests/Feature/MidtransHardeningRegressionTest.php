<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\PaymentService;
use App\Services\PaymentStatusService;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MidtransHardeningRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDatabaseName() !== 'satar_integrated_test') {
            throw new \RuntimeException('STOP: tes hanya boleh memakai satar_integrated_test');
        }
    }

    private function makePendingQrisPayment(
        User $cashier,
        array $transactionOverrides = [],
        array $paymentOverrides = []
    ): Payment {
        $transaction = Transaction::create(array_merge([
            'kode_transaksi' => 'MIDTRANS-TEST-' . strtoupper(bin2hex(random_bytes(4))),
            'user_id' => $cashier->id,
            'nama_pelanggan' => 'Midtrans Regression Test',
            'total' => 10000,
            'pajak' => 300,
            'diskon' => 0,
            'grand_total' => 10300,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'qris',
            'stock_reservation_status' => 'reserved',
        ], $transactionOverrides));

        return Payment::create(array_merge([
            'transaction_id' => $transaction->id,
            'metode' => 'qris',
            'external_id' => 'PAY-' . strtoupper(bin2hex(random_bytes(6))),
            'payment_status' => 'waiting',
            'expired_at' => now()->addMinutes(5),
        ], $paymentOverrides));
    }

    public function test_webhook_uses_dedicated_signature_secret_key(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $payment = $this->makePendingQrisPayment($cashier);
        config([
            'midtrans.server_key' => 'legacy-server-key',
            'midtrans.signature_secret_key' => 'dedicated-signature-secret',
        ]);

        $payload = [
            'order_id' => $payment->external_id,
            'status_code' => '201',
            'gross_amount' => '10300.00',
            'transaction_status' => 'pending',
            'fraud_status' => 'accept',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . 'dedicated-signature-secret'
        );

        $this->postJson(route('midtrans.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('status', 'waiting');
    }

    public function test_settlement_with_non_200_status_code_cannot_mark_payment_paid(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $payment = $this->makePendingQrisPayment($cashier);

        $reservation = \Mockery::mock(StockReservationService::class);
        $reservation->shouldNotReceive('complete');
        $this->app->instance(StockReservationService::class, $reservation);

        $status = app(PaymentStatusService::class)->applyRemoteStatus($payment->id, [
            'order_id' => $payment->external_id,
            'gross_amount' => '10300.00',
            'status_code' => '201',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ]);

        $this->assertSame('waiting', $status);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'payment_status' => 'waiting']);
        $this->assertDatabaseHas('transactions', [
            'id' => $payment->transaction_id,
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ]);
    }

    public function test_settlement_with_status_code_200_marks_payment_paid_once(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $payment = $this->makePendingQrisPayment($cashier);

        $reservation = \Mockery::mock(StockReservationService::class);
        $reservation->shouldReceive('complete')->once()->with($payment->transaction_id);
        $this->app->instance(StockReservationService::class, $reservation);

        $status = app(PaymentStatusService::class)->applyRemoteStatus($payment->id, [
            'order_id' => $payment->external_id,
            'gross_amount' => '10300.00',
            'status_code' => '200',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ]);

        $this->assertSame('paid', $status);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('transactions', [
            'id' => $payment->transaction_id,
            'payment_status' => 'paid',
            'status' => 'success',
        ]);
    }

    public function test_expired_qris_without_snap_token_releases_stock_reservation(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $payment = $this->makePendingQrisPayment($cashier, [], [
            'midtrans_snap_token' => null,
            'expired_at' => now()->subMinute(),
        ]);

        $reservation = \Mockery::mock(StockReservationService::class);
        $reservation->shouldReceive('release')->once()->with($payment->transaction_id);
        $this->app->instance(StockReservationService::class, $reservation);

        $status = app(PaymentStatusService::class)->expireExpiredQris($payment->id);

        $this->assertSame('expired', $status);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'payment_status' => 'expired']);
        $this->assertDatabaseHas('transactions', [
            'id' => $payment->transaction_id,
            'status' => 'expired',
            'payment_status' => 'expired',
        ]);
    }

    public function test_expired_snap_session_is_cancelled_before_stock_is_released(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $payment = $this->makePendingQrisPayment($cashier, [], [
            'midtrans_snap_token' => 'EXISTING-SNAP-TOKEN',
            'expired_at' => now()->subMinute(),
        ]);

        $midtrans = \Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('ambilStatus')->twice()->with(\Mockery::on(
            fn ($candidate) => $candidate->id === $payment->id
        ))->andReturn(null);
        $midtrans->shouldReceive('batalkanSesiSnap')->once()->with(\Mockery::on(
            fn ($candidate) => $candidate->id === $payment->id
        ), true)->andReturn(true);
        $this->app->instance(MidtransService::class, $midtrans);

        $reservation = \Mockery::mock(StockReservationService::class);
        $reservation->shouldReceive('release')->once()->with($payment->transaction_id);
        $this->app->instance(StockReservationService::class, $reservation);

        $status = app(PaymentStatusService::class)->expireExpiredQris($payment->id);

        $this->assertSame('expired', $status);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'payment_status' => 'expired']);
    }

    public function test_expired_snap_session_is_not_released_when_remote_cancel_is_unconfirmed(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $payment = $this->makePendingQrisPayment($cashier, [], [
            'midtrans_snap_token' => 'EXISTING-SNAP-TOKEN',
            'expired_at' => now()->subMinute(),
        ]);

        $midtrans = \Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('ambilStatus')->once()->andReturn(null);
        $midtrans->shouldReceive('batalkanSesiSnap')->once()->andReturn(false);
        $this->app->instance(MidtransService::class, $midtrans);

        $reservation = \Mockery::mock(StockReservationService::class);
        $reservation->shouldNotReceive('release');
        $this->app->instance(StockReservationService::class, $reservation);

        $status = app(PaymentStatusService::class)->expireExpiredQris($payment->id);

        $this->assertSame('waiting', $status);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'payment_status' => 'waiting']);
    }

    public function test_expired_pending_remote_transaction_is_cancelled_and_rechecked(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $payment = $this->makePendingQrisPayment($cashier, [], [
            'midtrans_snap_token' => 'EXISTING-SNAP-TOKEN',
            'expired_at' => now()->subMinute(),
        ]);
        $pending = (object) [
            'order_id' => $payment->external_id,
            'gross_amount' => '10300.00',
            'status_code' => '201',
            'transaction_status' => 'pending',
            'fraud_status' => 'accept',
        ];

        $midtrans = \Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('ambilStatus')->twice()->andReturn($pending, $pending);
        $midtrans->shouldReceive('batalkanTransaksiMidtrans')->once()->andReturn((object) [
            'transaction_status' => 'pending',
        ]);
        $this->app->instance(MidtransService::class, $midtrans);

        $reservation = \Mockery::mock(StockReservationService::class);
        $reservation->shouldNotReceive('release');
        $this->app->instance(StockReservationService::class, $reservation);

        $status = app(PaymentStatusService::class)->expireExpiredQris($payment->id);

        $this->assertSame('waiting', $status);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'payment_status' => 'waiting']);
    }

    public function test_production_can_cancel_qris_without_a_snap_token_safely(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $payment = $this->makePendingQrisPayment($cashier);
        config(['midtrans.is_production' => true]);

        $reservation = \Mockery::mock(StockReservationService::class);
        $reservation->shouldReceive('release')->once()->with($payment->transaction_id);
        $this->app->instance(StockReservationService::class, $reservation);

        $this->actingAs($cashier)
            ->post(route('transaksi.cancel', $payment->transaction_id))
            ->assertRedirect(route('transaksi.show', $payment->transaction_id));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'payment_status' => 'cancelled']);
        $this->assertDatabaseHas('transactions', [
            'id' => $payment->transaction_id,
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
        ]);
    }

    public function test_payment_service_uses_configured_minimum_snap_expiry(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);

        // Buat transaksi tanpa payment agar test menguji payment pertama.
        $transaction = Transaction::create([
            'kode_transaksi' => 'MIDTRANS-EXPIRY-TEST-' . strtoupper(bin2hex(random_bytes(4))),
            'user_id' => $cashier->id,
            'nama_pelanggan' => 'Midtrans Expiry Regression Test',
            'total' => 10000,
            'pajak' => 300,
            'diskon' => 0,
            'grand_total' => 10300,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'qris',
            'stock_reservation_status' => 'reserved',
        ]);

        config(['midtrans.snap_expiry_minutes' => 3]);

        $created = app(PaymentService::class)->buatPayment($transaction, 'qris');

        $this->assertTrue(
            $created->fresh()->expired_at->greaterThanOrEqualTo(now()->addMinutes(4)->subSeconds(2))
        );
        $this->assertTrue(
            $created->fresh()->expired_at->lessThanOrEqualTo(now()->addMinutes(5)->addSeconds(2))
        );
    }
}
