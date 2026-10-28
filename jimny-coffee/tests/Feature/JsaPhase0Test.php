<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JsaPhase0Test extends TestCase
{
    use RefreshDatabase;

    private function menu(string $name = 'Latte', string $slug = 'latte'): Menu
    {
        $category = Category::firstOrCreate(['nama_kategori' => 'Coffee']);

        return Menu::create([
            'category_id' => $category->id,
            'nama_menu' => $name,
            'slug' => $slug,
            'harga' => 10000,
            'stok' => 100,
        ]);
    }

    private function sale(
        User $user,
        Menu $menu,
        Carbon $date,
        int $qty,
        string $status = 'success',
        string $payment = 'paid'
    ): void {
        $transaction = Transaction::create([
            'kode_transaksi' => 'TEST-' . uniqid(),
            'user_id' => $user->id,
            'nama_pelanggan' => 'Pelanggan Uji',
            'total' => $qty * 10000,
            'grand_total' => $qty * 10000,
            'status' => $status,
            'payment_status' => $payment,
            'payment_method' => 'cash',
            'created_at' => $date->copy()->setTime(10, 0),
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'menu_id' => $menu->id,
            'qty' => $qty,
            'harga' => 10000,
            'subtotal' => $qty * 10000,
        ]);
    }

    /** Penjualan konstan selama $days hari penuh sebelum hari ini. */
    private function constantHistory(User $user, Menu $menu, int $days, int $qty): void
    {
        for ($i = 1; $i <= $days; $i++) {
            $this->sale($user, $menu, Carbon::today()->subDays($i), $qty);
        }
    }

    public function test_analytics_excludes_today_from_window(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $menu = $this->menu();

        $this->sale($admin, $menu, Carbon::yesterday(), 3);
        $this->sale($admin, $menu, Carbon::today(), 50);

        $response = $this->actingAs($admin)
            ->getJson('/smart-assistant/analytics?days=7')
            ->assertOk();

        $this->assertSame(3, $response->json('summary.sold_qty'));

        $trend = $response->json('sales_trend');
        $this->assertCount(7, $trend);
        $this->assertSame(Carbon::yesterday()->toDateString(), end($trend)['date']);
        $this->assertNotContains(
            Carbon::today()->toDateString(),
            array_column($trend, 'date')
        );
    }

    public function test_short_filters_still_produce_forecast_from_fixed_history(): void
    {
        $admin = User::factory()->create(['role' => 'owner']);
        $menu = $this->menu();
        $this->constantHistory($admin, $menu, 20, 5);

        foreach ([7, 14, 30] as $days) {
            $response = $this->actingAs($admin)
                ->getJson("/smart-assistant/analytics?days={$days}")
                ->assertOk();

            $this->assertSame('experimental', $response->json('forecast.status'), "days={$days}");
            $this->assertSame(5, $response->json('forecast.values.0.qty'), "days={$days}");
            $this->assertSame(
                Carbon::today()->toDateString(),
                $response->json('forecast.values.0.date'),
                "days={$days}"
            );
            $this->assertCount($days, $response->json('sales_trend'), "days={$days}");
        }
    }

    public function test_today_sales_do_not_bias_the_forecast(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $menu = $this->menu();
        $this->constantHistory($admin, $menu, 20, 20);

        // Hari ini baru 8 porsi (belum selesai): tidak boleh menurunkan prediksi.
        $this->sale($admin, $menu, Carbon::today(), 8);

        $this->actingAs($admin)
            ->getJson('/smart-assistant/analytics?days=30')
            ->assertOk()
            ->assertJsonPath('forecast.values.0.qty', 20);
    }

    public function test_model_evaluation_includes_seasonal_naive(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $menu = $this->menu();
        $this->constantHistory($admin, $menu, 25, 4);

        $response = $this->actingAs($admin)
            ->getJson('/smart-assistant/analytics?days=30')
            ->assertOk();

        $evaluation = $response->json('model_evaluation');

        $this->assertEquals(0, $evaluation['wma_mae']);
        $this->assertEquals(0, $evaluation['naive_mae']);
        $this->assertEquals(0, $evaluation['seasonal_naive_mae']);
        $this->assertContains($evaluation['best_by_mae'], ['wma', 'seasonal_naive', 'naive']);
        $this->assertArrayHasKey('seasonal_naive_qty', $response->json('forecast.backtest.0'));
    }

    public function test_insufficient_history_gives_no_forecast(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $menu = $this->menu();
        $this->constantHistory($admin, $menu, 5, 4);

        $this->actingAs($admin)
            ->getJson('/smart-assistant/analytics?days=30')
            ->assertOk()
            ->assertJsonPath('forecast.status', 'insufficient_data')
            ->assertJsonPath('model_evaluation', null);
    }

    public function test_only_successful_paid_orders_are_counted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $menu = $this->menu();

        $this->sale($admin, $menu, Carbon::yesterday(), 2);
        $this->sale($admin, $menu, Carbon::yesterday(), 9, 'pending', 'paid');
        $this->sale($admin, $menu, Carbon::yesterday(), 9, 'success', 'unpaid');

        $this->actingAs($admin)
            ->getJson('/smart-assistant/analytics?days=7')
            ->assertOk()
            ->assertJsonPath('summary.sold_qty', 2);
    }

    public function test_analytics_access_and_validation(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $admin = User::factory()->create(['role' => 'admin']);
        $menu = $this->menu();

        $this->actingAs($kasir)
            ->getJson('/smart-assistant/analytics')
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson('/smart-assistant/analytics?days=10')
            ->assertStatus(422);

        $this->actingAs($admin)
            ->getJson('/smart-assistant/analytics?menu_id=999999')
            ->assertStatus(422);

        $this->actingAs($admin)
            ->getJson('/smart-assistant/analytics?menu_id=' . $menu->id)
            ->assertOk()
            ->assertJsonPath('selected_menu_id', $menu->id);
    }

    public function test_export_writes_zero_filled_daily_csv_without_today(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $latte = $this->menu('Latte', 'latte');
        $mocha = $this->menu('Mocha', 'mocha');

        $this->sale($admin, $latte, Carbon::today()->subDays(3), 2);
        $this->sale($admin, $mocha, Carbon::today()->subDays(3), 1);
        $this->sale($admin, $latte, Carbon::today()->subDays(1), 4);
        $this->sale($admin, $latte, Carbon::today(), 50);
        $this->sale($admin, $latte, Carbon::today()->subDays(2), 9, 'pending', 'paid');

        $dir = storage_path('framework/testing/jsa-export-' . uniqid());

        try {
            $this->artisan('jsa:export', ['--out' => $dir])->assertExitCode(0);

            $rows = array_map('str_getcsv', file($dir . '/daily_total.csv', FILE_IGNORE_NEW_LINES));

            $this->assertSame(['tanggal', 'total_porsi', 'jumlah_transaksi'], $rows[0]);
            $this->assertSame(
                [
                    [Carbon::today()->subDays(3)->toDateString(), '3', '2'],
                    [Carbon::today()->subDays(2)->toDateString(), '0', '0'],
                    [Carbon::today()->subDays(1)->toDateString(), '4', '1'],
                ],
                array_slice($rows, 1)
            );

            $menuRows = array_map('str_getcsv', file($dir . '/daily_menu.csv', FILE_IGNORE_NEW_LINES));
            $this->assertSame(['tanggal', 'menu_id', 'nama_menu', 'porsi'], $menuRows[0]);
            $this->assertCount(4, $menuRows); // header + 3 baris menu-hari
        } finally {
            foreach (['daily_total.csv', 'daily_menu.csv'] as $file) {
                @unlink($dir . '/' . $file);
            }
            @rmdir($dir);
        }
    }

    public function test_export_rejects_invalid_days_and_handles_empty_data(): void
    {
        $dir = storage_path('framework/testing/jsa-export-' . uniqid());

        $this->artisan('jsa:export', ['--days' => 'abc', '--out' => $dir])->assertExitCode(1);
        $this->artisan('jsa:export', ['--days' => '0', '--out' => $dir])->assertExitCode(1);
        $this->artisan('jsa:export', ['--out' => $dir])->assertExitCode(0);

        $this->assertFileDoesNotExist($dir . '/daily_total.csv');
    }
}