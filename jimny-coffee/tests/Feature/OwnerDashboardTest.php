<?php

namespace Tests\Feature;

use App\Http\Controllers\OwnerController;
use App\Models\Attendance;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'satar_integrated_test') {
            throw new \RuntimeException('STOP: tes hanya boleh memakai satar_integrated_test');
        }
        Carbon::setTestNow(Carbon::parse('2031-03-03 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_revenue_dates_paid_filter_zero_days_and_ranking(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $category = DB::table('categories')->insertGetId(['nama_kategori' => 'Dashboard Test']);
        $menu = Menu::create(['category_id' => $category, 'nama_menu' => 'Dashboard Test', 'slug' => 'dashboard-test-'.uniqid(), 'harga' => 10000, 'stok' => 30, 'minimum_stok' => 5, 'is_active' => true]);
        foreach ([
            ['2031-02-25 00:00:00', 10000, 'success', 'paid', 1],
            ['2031-03-03 11:00:00', 20000, 'success', 'paid', 2],
            ['2031-02-24 23:59:59', 90000, 'success', 'paid', 9],
            ['2031-03-04 00:00:00', 80000, 'success', 'paid', 8],
            ['2031-03-03 10:00:00', 70000, 'pending', 'unpaid', 7],
            ['2031-03-03 10:00:00', 60000, 'success', 'unpaid', 6],
        ] as [$date, $amount, $status, $payment, $qty]) {
            $t = Transaction::create(['kode_transaksi' => 'OWNER-'.uniqid(), 'nama_pelanggan' => 'Tes Dashboard Owner', 'user_id' => $cashier->id, 'total' => $amount, 'grand_total' => $amount, 'status' => $status, 'payment_status' => $payment, 'created_at' => $date, 'updated_at' => $date]);
            DB::table('transaction_details')->insert(['transaction_id' => $t->id, 'menu_id' => $menu->id, 'qty' => $qty, 'harga' => 10000, 'subtotal' => $amount]);
        }
        $response = $this->actingAs($owner)->get(route('owner.dashboard'));
        $response->assertOk()->assertSee('Omzet 7 Hari Terakhir');
        $data = $response->original->getData();
        $this->assertEquals(1, $data['totalTransaksi']);
        $this->assertEquals(20000, $data['omzetHarian']);
        $this->assertEquals(20000, $data['omzetBulanan']);
        $this->assertSame([10000, 0, 0, 0, 0, 0, 20000], $data['omzetMingguan']->pluck('omzet')->all());
        $this->assertEquals(3, $data['menuTerlaris']->firstWhere('id', $menu->id)->total_terjual);
    }

    public function test_attendance_counts_people_and_stock_uses_each_menu_minimum(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        User::factory()->create(['role' => 'kasir']);
        foreach ([['08:00:00', '10:00:00'], ['12:00:00', '14:00:00']] as [$start, $end]) {
            $shift = DB::table('shifts')->insertGetId(['user_id' => $cashier->id, 'tanggal' => '2031-03-03', 'jam_masuk' => $start, 'jam_keluar' => $end, 'status' => 'selesai']);
            Attendance::create(['user_id' => $cashier->id, 'shift_id' => $shift, 'tanggal' => '2031-03-03', 'clock_in' => '2031-03-03 '.$start, 'clock_out' => '2031-03-03 '.$end, 'status' => 'hadir']);
        }
        $category = DB::table('categories')->insertGetId(['nama_kategori' => 'Stock Test']);
        $menus = [];
        foreach ([[7, 8, true], [3, 2, true], [0, 0, true], [1, 5, false]] as [$stock, $minimum, $active]) {
            $menus[] = Menu::create(['category_id' => $category, 'nama_menu' => 'Stock Test', 'slug' => 'stock-test-'.uniqid(), 'harga' => 10000, 'stok' => $stock, 'minimum_stok' => $minimum, 'is_active' => $active]);
        }
        $data = app(OwnerController::class)->dashboard()->getData();
        $this->assertEquals(1, $data['kasirHadir']);
        $ids = $data['stokHampirHabis']->pluck('id')->all();
        $this->assertContains($menus[0]->id, $ids);
        $this->assertNotContains($menus[1]->id, $ids);
        $this->assertContains($menus[2]->id, $ids);
        $this->assertNotContains($menus[3]->id, $ids);
    }
}
