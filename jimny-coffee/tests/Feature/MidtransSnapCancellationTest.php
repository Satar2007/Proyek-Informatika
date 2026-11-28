<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransSnapCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MidtransService
    {
        config([
            'midtrans.server_key' => 'sandbox-server-key',
            'midtrans.is_production' => false,
        ]);

        return new MidtransService();
    }

    private function payment(): Payment
    {
        $user = \App\Models\User::factory()->create(['role' => 'kasir']);

        $transaction = \App\Models\Transaction::create([
            'kode_transaksi' => 'SNAP-CANCEL-' . strtoupper(bin2hex(random_bytes(4))),
            'user_id' => $user->id,
            'nama_pelanggan' => 'Snap Cancel Test',
            'total' => 10000,
            'pajak' => 0,
            'diskon' => 0,
            'grand_total' => 10000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'qris',
            'stock_reservation_status' => 'reserved',
        ]);

        return Payment::create([
            'transaction_id' => $transaction->id,
            'metode' => 'qris',
            'external_id' => 'SNAP-CANCEL-ORDER-' . strtoupper(bin2hex(random_bytes(4))),
            'midtrans_snap_token' => 'TEST-SNAP-TOKEN',
            'payment_status' => 'waiting',
        ]);
    }

    public function test_production_rejects_local_only_lock_backends(): void
    {
        config([
            'midtrans.server_key' => 'production-server-key',
            'midtrans.is_production' => true,
            'midtrans.lock_store' => 'file',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'MIDTRANS_LOCK_STORE production wajib menggunakan database atau redis'
        );

        new MidtransService();
    }

    public function test_confirmed_snap_session_cancellation_is_accepted(): void
    {
        Http::fake([
            '*app.sandbox.midtrans.com/snap/v1/transactions/*/cancel' => Http::response([
                'canceled_at' => '2026-11-28 09:00:00',
            ], 200),
        ]);

        $result = $this->service()->batalkanSesiSnap($this->payment());

        $this->assertTrue($result);
        Http::assertSent(fn ($request) => str_contains(
            $request->url(),
            '/snap/v1/transactions/TEST-SNAP-TOKEN/cancel'
        ));
    }

    public function test_already_cancelled_snap_token_is_accepted_as_terminal(): void
    {
        Http::fake([
            '*app.sandbox.midtrans.com/snap/v1/transactions/*/cancel' => Http::response([
                'error_messages' => ['Token already canceled'],
            ], 422),
        ]);

        $this->assertTrue(
            $this->service()->batalkanSesiSnap($this->payment())
        );
    }

    public function test_token_not_found_requires_status_api_404_precheck(): void
    {
        $service = $this->service();
        $payment = $this->payment();

        Http::fake([
            '*app.sandbox.midtrans.com/snap/v1/transactions/*/cancel' => Http::response([
                'error_messages' => ['Token not found'],
            ], 404),
        ]);

        $this->assertFalse(
            $service->batalkanSesiSnap($payment)
        );

        $this->assertTrue(
            $service->batalkanSesiSnap($payment, true)
        );
    }

    public function test_in_progress_snap_session_is_not_treated_as_cancelled(): void
    {
        Http::fake([
            '*app.sandbox.midtrans.com/snap/v1/transactions/*/cancel' => Http::response([
                'error_messages' => ['Transaction is in progress'],
            ], 409),
        ]);

        $this->assertFalse(
            $this->service()->batalkanSesiSnap($this->payment())
        );
    }
}
