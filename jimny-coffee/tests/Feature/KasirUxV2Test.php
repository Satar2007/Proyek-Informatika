<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KasirUxV2Test extends TestCase
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

    public function test_pos_exposes_fast_new_order_flow_and_quantity_guard(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['nama_kategori' => 'Kasir UX Test']);

        Menu::create([
            'category_id' => $category->id,
            'nama_menu' => 'UX Latte Test',
            'slug' => 'ux-latte-test',
            'harga' => 18000,
            'stok' => 2,
            'minimum_stok' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($kasir)
            ->get(route('kasir.index'))
            ->assertOk()
            ->assertSee('x-ref="customerNameInput"', false)
            ->assertSee('x-ref="menuSearch"', false)
            ->assertSee(':disabled="item.qty >= item.stok"', false)
            ->assertSee('Cetak Struk')
            ->assertSee('Pesanan Baru')
            ->assertSee('startNewOrder()', false);
    }

    public function test_sold_out_menu_is_disabled_and_clearly_labelled(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['nama_kategori' => 'Kasir Sold Out Test']);

        Menu::create([
            'category_id' => $category->id,
            'nama_menu' => 'Sold Out UX Test',
            'slug' => 'sold-out-ux-test',
            'harga' => 15000,
            'stok' => 0,
            'minimum_stok' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($kasir)->get(route('kasir.index'));

        $response
            ->assertOk()
            ->assertSee('Sold Out UX Test')
            ->assertSee('Stok: 0 · Habis')
            ->assertSee('product-sold-out', false)
            ->assertSee('disabled', false);
    }
}
