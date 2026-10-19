<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $user, string $date, int $amount, string $status = 'success', string $method = 'cash'): Transaction
    {
        return Transaction::create([
            'kode_transaksi' => 'TEST-' . uniqid(), 'user_id' => $user->id,
            'nama_pelanggan' => 'Pelanggan Uji', 'total' => $amount, 'grand_total' => $amount,
            'status' => $status, 'payment_method' => $method, 'created_at' => $date,
        ]);
    }

    public function test_daily_report_counts_only_successful_orders_and_fills_empty_hours(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->order($user, '2026-09-08 09:15:00', 30000);
        $sale = $this->order($user, '2026-09-08 10:15:00', 20000, 'success', 'qris');
        $this->order($user, '2026-09-08 11:00:00', 90000, 'pending');
        $this->order($user, '2026-09-08 12:00:00', 90000, 'cancelled');
        $this->order($user, '2026-09-07 09:00:00', 25000);
        $this->order($user, '2026-09-09 00:00:00', 99000);
        $category = Category::create(['nama_kategori' => 'Coffee']);
        $menu = Menu::create(['category_id' => $category->id, 'nama_menu' => "Chef's Latte", 'slug' => 'chefs-latte', 'harga' => 10000]);
        TransactionDetail::create(['transaction_id' => $sale->id, 'menu_id' => $menu->id, 'qty' => 2, 'harga' => 10000, 'subtotal' => 20000]);
        $response = $this->actingAs($user)->get('/laporan/harian?tanggal=2026-09-08');
        $response->assertOk()->assertViewHas('total', 50000)->assertViewHas('count', 2)->assertViewHas('growth', 100);
        $response->assertViewHas('series', fn ($series) => $series->count() === 24 && $series[0]['count'] === 0 && $series[9]['total'] === 30000.0);
        $response->assertViewHas('payments', fn ($payments) => $payments->sum('total') === 50000.0);
        $response->assertViewHas('topMenus', fn ($menus) => $menus->first()->units == 2);
    }

    public function test_monthly_report_handles_leap_year_and_previous_year_boundary(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $this->order($user, '2023-12-31 23:59:59', 10000);
        $this->order($user, '2024-01-01 00:00:00', 15000);
        $this->actingAs($user)->get('/laporan/bulanan?bulan=1&tahun=2024')
            ->assertOk()->assertViewHas('growth', 50)->assertViewHas('total', 15000);
        $this->get('/laporan/bulanan?bulan=2&tahun=2024')->assertOk()
            ->assertViewHas('series', fn ($series) => $series->count() === 29)
            ->assertViewHas('total', 0);
    }

    public function test_empty_reports_and_invalid_filters(): void
    {
        $user = User::factory()->create(['role' => 'kasir']);
        $this->actingAs($user)->get('/laporan/harian?tanggal=2026-09-08')->assertOk()
            ->assertViewHas('growth', null)->assertSee('Belum ada transaksi sukses');
        $this->get('/laporan/bulanan?bulan=13&tahun=2026')->assertSessionHasErrors('bulan');
        $this->get('/laporan/harian?tanggal=bukan-tanggal')->assertSessionHasErrors('tanggal');
    }

    public function test_reports_require_authentication(): void
    {
        $this->get('/laporan/harian')->assertRedirect('/login');
    }
}
