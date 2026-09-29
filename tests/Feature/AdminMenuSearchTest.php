<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMenuSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            DB::connection()->getDatabaseName()
            !== 'satar_integrated_test'
        ) {
            throw new \RuntimeException(
                'STOP: tes hanya boleh memakai satar_integrated_test'
            );
        }
    }

    public function test_admin_can_search_and_filter_menu(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $coffee = Category::create([
            'nama_kategori' => 'Coffee Search Test',
        ]);

        $tea = Category::create([
            'nama_kategori' => 'Tea Search Test',
        ]);

        Menu::create([
            'category_id' => $coffee->id,
            'nama_menu' => 'Espresso Search Unique',
            'slug' => 'espresso-search-unique',
            'deskripsi' => 'Kopi khusus pengujian search',
            'harga' => 11000,
            'stok' => 17,
            'minimum_stok' => 5,
            'is_active' => true,
        ]);

        Menu::create([
            'category_id' => $tea->id,
            'nama_menu' => 'Matcha Search Unique',
            'slug' => 'matcha-search-unique',
            'deskripsi' => 'Tea khusus pengujian search',
            'harga' => 22000,
            'stok' => 8,
            'minimum_stok' => 5,
            'is_active' => true,
        ]);


        // Search berdasarkan nama menu.
        $this->actingAs($admin)
            ->get(
                route(
                    'admin.menu.index',
                    ['q' => 'Espresso']
                )
            )
            ->assertOk()
            ->assertSee('Espresso Search Unique')
            ->assertDontSee('Matcha Search Unique');


        // Search berdasarkan nama kategori.
        $this->actingAs($admin)
            ->get(
                route(
                    'admin.menu.index',
                    ['q' => 'Tea Search Test']
                )
            )
            ->assertOk()
            ->assertSee('Matcha Search Unique')
            ->assertDontSee('Espresso Search Unique');


        // Search berdasarkan stok.
        $this->actingAs($admin)
            ->get(
                route(
                    'admin.menu.index',
                    ['q' => '17']
                )
            )
            ->assertOk()
            ->assertSee('Espresso Search Unique')
            ->assertDontSee('Matcha Search Unique');


        // Filter kategori Coffee.
        $this->actingAs($admin)
            ->get(
                route(
                    'admin.menu.index',
                    [
                        'category' => $coffee->id,
                    ]
                )
            )
            ->assertOk()
            ->assertSee('Espresso Search Unique')
            ->assertDontSee('Matcha Search Unique');


        // Filter kategori Tea.
        $this->actingAs($admin)
            ->get(
                route(
                    'admin.menu.index',
                    [
                        'category' => $tea->id,
                    ]
                )
            )
            ->assertOk()
            ->assertSee('Matcha Search Unique')
            ->assertDontSee('Espresso Search Unique');


        // Search + kategori dapat digabung.
        $this->actingAs($admin)
            ->get(
                route(
                    'admin.menu.index',
                    [
                        'q' => 'Espresso',
                        'category' => $tea->id,
                    ]
                )
            )
            ->assertOk()
            ->assertDontSee('Espresso Search Unique');
    }
}