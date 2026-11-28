<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaymentStatusService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class JimnySecurityInvariantTest extends TestCase
{
    use RefreshDatabase;

    private function transactionFor(User $user, array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'kode_transaksi' => 'SEC-TEST-' . strtoupper(bin2hex(random_bytes(5))),
            'user_id' => $user->id,
            'nama_pelanggan' => 'Security Test',
            'total' => 10000,
            'pajak' => 300,
            'diskon' => 0,
            'grand_total' => 10300,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'qris',
            'stock_reservation_status' => 'reserved',
        ], $overrides));
    }

    private function paymentFor(Transaction $transaction, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'transaction_id' => $transaction->id,
            'metode' => 'qris',
            'external_id' => 'PAY-SEC-' . strtoupper(bin2hex(random_bytes(5))),
            'payment_status' => 'waiting',
        ], $overrides));
    }

    public function test_admin_can_create_owner_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.akun.store'), [
                'name' => 'Owner Baru',
                'email' => 'owner-security-test@example.test',
                'password' => 'StrongPassword123!',
                'role' => 'owner',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'owner-security-test@example.test',
            'role' => 'owner',
        ]);
    }

    public function test_admin_cannot_demote_the_last_owner(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->put(route('admin.akun.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'password' => '',
                'role' => 'kasir',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'owner']);
    }

    public function test_admin_can_demote_an_owner_when_another_owner_remains(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ownerA = User::factory()->create(['role' => 'owner']);
        User::factory()->create(['role' => 'owner']);

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->put(route('admin.akun.update', $ownerA), [
                'name' => $ownerA->name,
                'email' => $ownerA->email,
                'password' => '',
                'role' => 'kasir',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $ownerA->id, 'role' => 'kasir']);
    }

    public function test_admin_cannot_delete_the_last_owner(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->delete(route('admin.akun.destroy', $owner))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'owner']);
    }

    public function test_last_owner_cannot_delete_own_account_from_profile(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'account');

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'owner']);
    }

    public function test_last_admin_cannot_delete_own_account_from_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'account');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_admin_cannot_demote_the_last_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->put(route('admin.akun.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'password' => '',
                'role' => 'owner',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_database_rejects_a_second_payment_for_the_same_transaction(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->transactionFor($cashier);
        $this->paymentFor($transaction);

        try {
            $this->paymentFor($transaction);
            $this->fail('A transaction must not have more than one payment record.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('payments_transaction_id_unique', $exception->getMessage());
        }
    }

    public function test_remote_qris_settlement_rejects_a_cash_transaction(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->transactionFor($cashier, ['payment_method' => 'cash']);
        $payment = $this->paymentFor($transaction);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Metode transaksi lokal tidak cocok');

        app(PaymentStatusService::class)->applyRemoteStatus($payment->id, [
            'order_id' => $payment->external_id,
            'gross_amount' => '10300.00',
            'status_code' => '200',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'currency' => 'IDR',
        ]);
    }

    public function test_remote_amount_with_fractional_or_excess_precision_is_rejected(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->transactionFor($cashier);
        $payment = $this->paymentFor($transaction);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nominal pembayaran Midtrans tidak sesuai');

        app(PaymentStatusService::class)->applyRemoteStatus($payment->id, [
            'order_id' => $payment->external_id,
            'gross_amount' => '10300.000001',
            'status_code' => '200',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'currency' => 'IDR',
        ]);
    }

    public function test_remote_non_idr_currency_is_rejected(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->transactionFor($cashier);
        $payment = $this->paymentFor($transaction);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mata uang pembayaran Midtrans bukan IDR');

        app(PaymentStatusService::class)->applyRemoteStatus($payment->id, [
            'order_id' => $payment->external_id,
            'gross_amount' => '10300.00',
            'status_code' => '200',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'currency' => 'USD',
        ]);
    }

    public function test_late_midtrans_settlement_after_local_cancel_is_flagged_for_reconciliation(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->transactionFor($cashier, [
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
            'stock_reservation_status' => 'released',
        ]);
        $payment = $this->paymentFor($transaction, ['payment_status' => 'cancelled']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rekonsiliasi manual diperlukan');

        app(PaymentStatusService::class)->applyRemoteStatus($payment->id, [
            'order_id' => $payment->external_id,
            'gross_amount' => '10300.00',
            'status_code' => '200',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'currency' => 'IDR',
        ]);
    }


    public function test_webhook_rejects_invalid_signature_without_changing_payment(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->transactionFor($cashier);
        $payment = $this->paymentFor($transaction);
        config(['midtrans.signature_secret_key' => 'test-signature-secret']);

        $this->postJson(route('midtrans.notification'), [
            'order_id' => $payment->external_id,
            'status_code' => '200',
            'gross_amount' => '10300.00',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'signature_key' => str_repeat('0', 128),
        ])->assertForbidden()
          ->assertJsonPath('received', false);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'waiting',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
    }
}
