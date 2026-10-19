<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockOperationalTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDatabaseName() !== 'satar_integrated_test') {
            throw new \RuntimeException(
                'STOP: tes hanya boleh memakai satar_integrated_test'
            );
        }
    }

    public function test_admin_can_filter_menu_by_stock_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $category = Category::create([
            'nama_kategori' => 'Stock Operational Filter Test',
        ]);

        $aman = $this->createMenu($category, 'Stock Aman Unique', 12, 5);
        $menipis = $this->createMenu($category, 'Stock Menipis Unique', 3, 5);
        $habis = $this->createMenu($category, 'Stock Habis Unique', 0, 5);

        $this->actingAs($admin)
            ->get(route('admin.menu.index', ['stock_status' => 'aman']))
            ->assertOk()
            ->assertSee($aman->nama_menu)
            ->assertDontSee($menipis->nama_menu)
            ->assertDontSee($habis->nama_menu);

        $this->actingAs($admin)
            ->get(route('admin.menu.index', ['stock_status' => 'menipis']))
            ->assertOk()
            ->assertSee($menipis->nama_menu)
            ->assertDontSee($aman->nama_menu)
            ->assertDontSee($habis->nama_menu);

        $this->actingAs($admin)
            ->get(route('admin.menu.index', ['stock_status' => 'habis']))
            ->assertOk()
            ->assertSee($habis->nama_menu)
            ->assertDontSee($aman->nama_menu)
            ->assertDontSee($menipis->nama_menu);
    }

    public function test_stock_summary_uses_global_counts_not_current_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $category = Category::create([
            'nama_kategori' => 'Stock Operational Summary Test',
        ]);

        $this->createMenu($category, 'Summary Aman Unique', 10, 5);
        $this->createMenu($category, 'Summary Menipis Unique', 2, 5);
        $this->createMenu($category, 'Summary Habis Unique', 0, 5);

        $expectedTotal = Menu::count();
        $expectedActive = Menu::where('is_active', true)->count();
        $expectedLow = Menu::where('stok', '>', 0)
            ->whereColumn('stok', '<=', 'minimum_stok')
            ->count();
        $expectedOut = Menu::where('stok', '<=', 0)->count();

        $this->actingAs($admin)
            ->get(route('admin.menu.index', ['stock_status' => 'habis']))
            ->assertOk()
            ->assertViewHas('totalMenu', $expectedTotal)
            ->assertViewHas('menuAktif', $expectedActive)
            ->assertViewHas('stokRendah', $expectedLow)
            ->assertViewHas('stokHabis', $expectedOut);
    }

    public function test_admin_can_search_and_filter_stock_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $category = Category::create([
            'nama_kategori' => 'Stock Operational Log Test',
        ]);

        $coffee = $this->createMenu($category, 'Log Coffee Unique', 10, 5);
        $tea = $this->createMenu($category, 'Log Tea Unique', 8, 5);

        StockLog::create([
            'menu_id' => $coffee->id,
            'tipe' => 'in',
            'qty_before' => 5,
            'qty_change' => 5,
            'qty_after' => 10,
            'catatan' => 'Restock biji kopi unik',
            'created_by' => $admin->id,
        ]);

        StockLog::create([
            'menu_id' => $tea->id,
            'tipe' => 'out',
            'qty_before' => 10,
            'qty_change' => 2,
            'qty_after' => 8,
            'catatan' => 'Koreksi teh unik',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.stok-log', [
                'q' => 'Restock biji kopi unik',
                'type' => 'in',
            ]))
            ->assertOk()
            ->assertSee('Log Coffee Unique')
            ->assertSee('Restock biji kopi unik')
            ->assertDontSee('Log Tea Unique')
            ->assertDontSee('Koreksi teh unik');

        $this->actingAs($admin)
            ->get(route('admin.stok-log', [
                'q' => (string) $tea->id,
                'type' => 'out',
            ]))
            ->assertOk()
            ->assertSee('Log Tea Unique')
            ->assertSee('Koreksi teh unik')
            ->assertDontSee('Log Coffee Unique');
    }

    private function createMenu(
        Category $category,
        string $name,
        int $stock,
        int $minimumStock
    ): Menu {
        return Menu::create([
            'category_id' => $category->id,
            'nama_menu' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
            'deskripsi' => 'Menu pengujian stok operasional',
            'harga' => 15000,
            'stok' => $stock,
            'minimum_stok' => $minimumStock,
            'is_active' => true,
        ]);
    }
}
