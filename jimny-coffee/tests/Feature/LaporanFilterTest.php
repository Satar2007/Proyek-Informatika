<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanFilterTest extends TestCase
{
    use RefreshDatabase;

    private function order(
        User $user,
        string $date,
        int $amount,
        string $status = 'success',
        string $method = 'cash'
    ): Transaction {
        return Transaction::create([
            'kode_transaksi' => 'FILTER-' . uniqid(),
            'user_id' => $user->id,
            'nama_pelanggan' => 'Pelanggan Uji',
            'total' => $amount,
            'grand_total' => $amount,
            'status' => $status,
            'payment_method' => $method,
            'created_at' => $date,
        ]);
    }

    public function test_cashier_report_only_contains_own_transactions(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $other = User::factory()->create(['role' => 'kasir']);

        $this->order($cashier, '2026-09-08 10:00:00', 12000);
        $this->order($other, '2026-09-08 11:00:00', 99000);

        $this->actingAs($cashier)
            ->get('/laporan/harian?tanggal=2026-09-08')
            ->assertOk()
            ->assertViewHas('total', 12000.0)
            ->assertViewHas('count', 1);
    }

    public function test_cashier_cannot_override_scope_with_kasir_id_query(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $other = User::factory()->create(['role' => 'kasir']);

        $this->order($cashier, '2026-09-08 10:00:00', 15000);
        $this->order($other, '2026-09-08 11:00:00', 88000);

        $this->actingAs($cashier)
            ->get('/laporan/harian?tanggal=2026-09-08&kasir_id=' . $other->id)
            ->assertOk()
            ->assertViewHas('total', 15000.0)
            ->assertViewHas('count', 1);
    }

    public function test_admin_can_filter_cashier_status_and_payment_method(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $other = User::factory()->create(['role' => 'kasir']);

        $this->order($cashier, '2026-09-08 09:00:00', 18000, 'success', 'cash');
        $this->order($cashier, '2026-09-08 10:00:00', 27000, 'pending', 'qris');
        $this->order($other, '2026-09-08 11:00:00', 45000, 'pending', 'qris');

        $this->actingAs($admin)
            ->get('/laporan/harian?tanggal=2026-09-08&kasir_id=' . $cashier->id . '&status=pending&metode=qris')
            ->assertOk()
            ->assertViewHas('total', 27000.0)
            ->assertViewHas('count', 1)
            ->assertViewHas('filters', fn (array $filters) =>
                $filters['kasir_id'] === $cashier->id
                && $filters['status'] === 'pending'
                && $filters['metode'] === 'qris'
            );
    }

    public function test_invalid_report_filter_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/laporan/harian?tanggal=2026-09-08&status=unknown')
            ->assertSessionHasErrors('status');
    }
}
