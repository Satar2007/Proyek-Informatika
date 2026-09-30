<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OwnerDashboardV2Test extends TestCase
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

    public function test_dashboard_calculates_average_yesterday_comparison_and_critical_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $category = DB::table('categories')->insertGetId(['nama_kategori' => 'Owner V2 Metrics']);

        Menu::create([
            'category_id' => $category,
            'nama_menu' => 'Owner V2 Critical',
            'slug' => 'owner-v2-critical-'.uniqid(),
            'harga' => 10000,
            'stok' => 2,
            'minimum_stok' => 3,
            'is_active' => true,
        ]);

        Menu::create([
            'category_id' => $category,
            'nama_menu' => 'Owner V2 Safe',
            'slug' => 'owner-v2-safe-'.uniqid(),
            'harga' => 10000,
            'stok' => 10,
            'minimum_stok' => 3,
            'is_active' => true,
        ]);

        foreach ([
            ['2031-03-03 08:00:00', 20000, 'success', 'paid'],
            ['2031-03-03 10:00:00', 30000, 'success', 'paid'],
            ['2031-03-02 10:00:00', 25000, 'success', 'paid'],
            ['2031-03-03 11:00:00', 99000, 'pending', 'unpaid'],
        ] as [$date, $amount, $status, $paymentStatus]) {
            Transaction::create([
                'kode_transaksi' => 'OWNER-V2-'.uniqid(),
                'nama_pelanggan' => 'Owner V2 Test',
                'user_id' => $cashier->id,
                'total' => $amount,
                'grand_total' => $amount,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        $response = $this->actingAs($owner)->get(route('owner.dashboard'));

        $response->assertOk()
            ->assertSee('Rata-rata Transaksi')
            ->assertSee('Stok Kritis')
            ->assertSee('Menu Kurang Laku')
            ->assertSee('Perlu Restock');

        $data = $response->original->getData();

        $this->assertSame(2, $data['totalTransaksi']);
        $this->assertSame(50000, $data['omzetHarian']);
        $this->assertSame(25000, $data['omzetKemarin']);
        $this->assertSame(25000, $data['rataRataTransaksi']);
        $this->assertSame(25000, $data['perubahanOmzetNominal']);
        $this->assertSame(100.0, $data['perubahanOmzetPersen']);
        $this->assertSame(1, $data['stokKritis']);
    }

    public function test_least_selling_includes_zero_sales_and_excludes_inactive_menu(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $category = DB::table('categories')->insertGetId(['nama_kategori' => 'Owner V2 Slow']);

        // Isolate fixture ranking dari menu yang mungkin sudah ada di database test.
        // DatabaseTransactions akan rollback perubahan ini setelah test selesai.
        Menu::where('is_active', true)->update(['is_active' => false]);

        $zero = Menu::create([
            'category_id' => $category,
            'nama_menu' => 'A Zero Sale',
            'slug' => 'owner-v2-zero-'.uniqid(),
            'harga' => 10000,
            'stok' => 10,
            'minimum_stok' => 2,
            'is_active' => true,
        ]);

        $sold = Menu::create([
            'category_id' => $category,
            'nama_menu' => 'B Sold Menu',
            'slug' => 'owner-v2-sold-'.uniqid(),
            'harga' => 10000,
            'stok' => 10,
            'minimum_stok' => 2,
            'is_active' => true,
        ]);

        $inactive = Menu::create([
            'category_id' => $category,
            'nama_menu' => 'Inactive Zero Sale',
            'slug' => 'owner-v2-inactive-'.uniqid(),
            'harga' => 10000,
            'stok' => 10,
            'minimum_stok' => 2,
            'is_active' => false,
        ]);

        $transaction = Transaction::create([
            'kode_transaksi' => 'OWNER-V2-SALE-'.uniqid(),
            'nama_pelanggan' => 'Owner V2 Slow Test',
            'user_id' => $cashier->id,
            'total' => 20000,
            'grand_total' => 20000,
            'status' => 'success',
            'payment_status' => 'paid',
            'created_at' => '2031-03-03 09:00:00',
            'updated_at' => '2031-03-03 09:00:00',
        ]);

        DB::table('transaction_details')->insert([
            'transaction_id' => $transaction->id,
            'menu_id' => $sold->id,
            'qty' => 2,
            'harga' => 10000,
            'subtotal' => 20000,
        ]);

        $data = $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->original
            ->getData();

        $slowIds = $data['menuKurangLaku']->pluck('id')->all();

        $this->assertContains($zero->id, $slowIds);
        $this->assertContains($sold->id, $slowIds);
        $this->assertNotContains($inactive->id, $slowIds);
        $this->assertEquals(0, $data['menuKurangLaku']->firstWhere('id', $zero->id)->total_terjual);
        $this->assertEquals(2, $data['menuKurangLaku']->firstWhere('id', $sold->id)->total_terjual);
    }
}
