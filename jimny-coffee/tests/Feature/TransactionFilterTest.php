<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFilterTest extends TestCase
{
    use RefreshDatabase;

    private function transaction(
        User $cashier,
        string $code,
        string $customer,
        string $status = 'success',
        string $method = 'cash',
        string $date = '2026-09-30 10:00:00'
    ): Transaction {
        return Transaction::create([
            'kode_transaksi' => $code,
            'user_id' => $cashier->id,
            'nama_pelanggan' => $customer,
            'total' => 20000,
            'pajak' => 0,
            'diskon' => 0,
            'grand_total' => 20000,
            'status' => $status,
            'payment_status' => $status === 'success' ? 'paid' : 'unpaid',
            'payment_method' => $method,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }

    public function test_cashier_only_sees_own_transactions_even_when_searching(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir', 'name' => 'Kasir A']);
        $other = User::factory()->create(['role' => 'kasir', 'name' => 'Kasir B']);

        $this->transaction($cashier, 'OWN-1001', 'Budi Santoso');
        $this->transaction($other, 'OTHER-1002', 'Budi Rahasia');

        $response = $this->actingAs($cashier)->get('/transaksi?q=Budi');

        $response
            ->assertOk()
            ->assertSee('OWN-1001')
            ->assertDontSee('OTHER-1002');
    }

    public function test_admin_can_search_by_code_customer_and_numeric_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);

        $alpha = $this->transaction($cashier, 'TRX-ALPHA-001', 'Nadia Coffee');
        $this->transaction($cashier, 'TRX-BETA-002', 'Raka Tea');

        $this->actingAs($admin)
            ->get('/transaksi?q=ALPHA')
            ->assertOk()
            ->assertSee('TRX-ALPHA-001')
            ->assertDontSee('TRX-BETA-002');

        $this->actingAs($admin)
            ->get('/transaksi?q=Nadia')
            ->assertOk()
            ->assertSee('TRX-ALPHA-001')
            ->assertDontSee('TRX-BETA-002');

        $this->actingAs($admin)
            ->get('/transaksi?q=' . $alpha->id)
            ->assertOk()
            ->assertSee('TRX-ALPHA-001');
    }

    public function test_filters_status_payment_method_and_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);

        $this->transaction($cashier, 'MATCH-QRIS', 'Match', 'success', 'qris', '2026-09-20 09:00:00');
        $this->transaction($cashier, 'WRONG-CASH', 'Cash', 'success', 'cash', '2026-09-20 09:00:00');
        $this->transaction($cashier, 'WRONG-PENDING', 'Pending', 'pending', 'qris', '2026-09-20 09:00:00');
        $this->transaction($cashier, 'WRONG-DATE', 'Old', 'success', 'qris', '2026-09-10 09:00:00');

        $response = $this->actingAs($admin)->get(
            '/transaksi?status=success&metode=qris&tanggal_mulai=2026-09-15&tanggal_akhir=2026-09-25'
        );

        $response
            ->assertOk()
            ->assertSee('MATCH-QRIS')
            ->assertDontSee('WRONG-CASH')
            ->assertDontSee('WRONG-PENDING')
            ->assertDontSee('WRONG-DATE');
    }

    public function test_paid_transaction_shows_receipt_shortcut_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->transaction($cashier, 'PRINT-001', 'Cetak Struk');

        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'metode' => 'cash',
            'external_id' => 'PAY-PRINT-001',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->get('/transaksi')
            ->assertOk()
            ->assertSee(route('payment.struk', $payment->id), false)
            ->assertSee('Struk');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/transaksi?status=unknown&metode=card')
            ->assertSessionHasErrors(['status', 'metode']);

        $this->actingAs($admin)
            ->get('/transaksi?tanggal_mulai=2026-09-30&tanggal_akhir=2026-09-01')
            ->assertSessionHasErrors('tanggal_akhir');
    }
}
