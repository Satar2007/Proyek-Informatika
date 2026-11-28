<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransSignatureSecretTest extends TestCase
{
    use RefreshDatabase;

    private function waitingQrisPayment(): Payment
    {
        $cashier = User::factory()->create(['role' => 'kasir']);

        $transaction = Transaction::create([
            'kode_transaksi' => 'SIGNATURE-' . strtoupper(bin2hex(random_bytes(4))),
            'user_id' => $cashier->id,
            'nama_pelanggan' => 'Signature Test',
            'total' => 10300,
            'pajak' => 0,
            'diskon' => 0,
            'grand_total' => 10300,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'qris',
            'stock_reservation_status' => 'reserved',
        ]);

        return Payment::create([
            'transaction_id' => $transaction->id,
            'metode' => 'qris',
            'external_id' => 'SIGNATURE-ORDER-' . strtoupper(bin2hex(random_bytes(4))),
            'payment_status' => 'waiting',
        ]);
    }

    public function test_webhook_fails_closed_when_dedicated_secret_is_missing_even_if_server_key_exists(): void
    {
        $payment = $this->waitingQrisPayment();

        config([
            'midtrans.server_key' => 'legacy-server-key',
            'midtrans.signature_secret_key' => '',
        ]);

        $signatureUsingLegacyServerKey = hash(
            'sha512',
            $payment->external_id . '200' . '10300.00' . 'legacy-server-key'
        );

        $this->postJson(route('midtrans.notification'), [
            'order_id' => $payment->external_id,
            'status_code' => '200',
            'gross_amount' => '10300.00',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'currency' => 'IDR',
            'signature_key' => $signatureUsingLegacyServerKey,
        ])->assertStatus(500)
          ->assertJsonPath('received', false);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'waiting',
        ]);
    }

    public function test_webhook_accepts_signature_from_dedicated_secret(): void
    {
        $payment = $this->waitingQrisPayment();

        config([
            'midtrans.server_key' => 'server-key-not-used-for-signature',
            'midtrans.signature_secret_key' => 'dedicated-notification-secret',
        ]);

        $signature = hash(
            'sha512',
            $payment->external_id
                . '200'
                . '10300.00'
                . 'dedicated-notification-secret'
        );

        $this->postJson(route('midtrans.notification'), [
            'order_id' => $payment->external_id,
            'status_code' => '200',
            'gross_amount' => '10300.00',
            'transaction_status' => 'pending',
            'fraud_status' => 'accept',
            'currency' => 'IDR',
            'signature_key' => $signature,
        ])->assertOk()
          ->assertJsonPath('received', true)
          ->assertJsonPath('status', 'waiting');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'waiting',
        ]);
    }
}
