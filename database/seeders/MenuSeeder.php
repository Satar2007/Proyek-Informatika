<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Category;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [

            // ======================
            // COFFEE
            // ======================
            [
                'nama' => 'Tubruk',
                'kategori' => 'Coffee',
                'harga' => 6000,
                'stok' => 50,
                'deskripsi' => 'Kopi tubruk tradisional.',
            ],
            [
                'nama' => 'Espresso',
                'kategori' => 'Coffee',
                'harga' => 10000,
                'stok' => 50,
                'deskripsi' => 'Kopi espresso pekat dengan rasa kuat.',
            ],
            [
                'nama' => 'Lungo',
                'kategori' => 'Coffee',
                'harga' => 10000,
                'stok' => 50,
                'deskripsi' => 'Espresso dengan ekstraksi lebih panjang.',
            ],
            [
                'nama' => 'Ristretto',
                'kategori' => 'Coffee',
                'harga' => 10000,
                'stok' => 50,
                'deskripsi' => 'Espresso pendek dengan rasa lebih intens.',
            ],
            [
                'nama' => 'Americano',
                'kategori' => 'Coffee',
                'harga' => 14000,
                'stok' => 50,
                'deskripsi' => 'Espresso dengan tambahan air panas.',
            ],
            [
                'nama' => 'Limericano',
                'kategori' => 'Coffee',
                'harga' => 15000,
                'stok' => 50,
                'deskripsi' => 'Americano dengan sentuhan lime yang segar.',
            ],
            [
                'nama' => 'Long Black',
                'kategori' => 'Coffee',
                'harga' => 15000,
                'stok' => 50,
                'deskripsi' => 'Kopi hitam dengan rasa bold dan smooth.',
            ],
            [
                'nama' => 'Americano Premium Beans',
                'kategori' => 'Coffee',
                'harga' => 18000,
                'stok' => 50,
                'deskripsi' => 'Americano dengan premium beans. Arabika +4k.',
            ],

            // ======================
            // MILK BASE
            // ======================
            [
                'nama' => 'Magic Latte',
                'kategori' => 'Milk Base',
                'harga' => 13000,
                'stok' => 50,
                'deskripsi' => 'Minuman susu dengan cita rasa lembut.',
            ],
            [
                'nama' => 'Dirty Latte',
                'kategori' => 'Milk Base',
                'harga' => 15000,
                'stok' => 50,
                'deskripsi' => 'Latte dengan espresso shot yang kuat.',
            ],
            [
                'nama' => 'Cafe Latte',
                'kategori' => 'Milk Base',
                'harga' => 13000,
                'stok' => 50,
                'deskripsi' => 'Latte klasik dengan susu creamy.',
            ],
            [
                'nama' => 'Cappuccino',
                'kategori' => 'Milk Base',
                'harga' => 15000,
                'stok' => 50,
                'deskripsi' => 'Perpaduan espresso, susu, dan foam lembut.',
            ],

            // ======================
            // COFFEE FLAVOURED
            // ======================
            [
                'nama' => 'Aren Latte',
                'kategori' => 'Coffee Flavoured',
                'harga' => 15000,
                'stok' => 50,
                'deskripsi' => 'Latte dengan manis gula aren.',
            ],
            [
                'nama' => 'Spanish Latte',
                'kategori' => 'Coffee Flavoured',
                'harga' => 16000,
                'stok' => 50,
                'deskripsi' => 'Latte manis creamy ala Spanish style.',
            ],
            [
                'nama' => 'Hazelnut Latte',
                'kategori' => 'Coffee Flavoured',
                'harga' => 18000,
                'stok' => 50,
                'deskripsi' => 'Latte dengan aroma hazelnut.',
            ],
            [
                'nama' => 'Caramel Latte',
                'kategori' => 'Coffee Flavoured',
                'harga' => 18000,
                'stok' => 50,
                'deskripsi' => 'Latte dengan sirup karamel.',
            ],
            [
                'nama' => 'Vanilla Latte',
                'kategori' => 'Coffee Flavoured',
                'harga' => 18000,
                'stok' => 50,
                'deskripsi' => 'Latte dengan rasa vanilla lembut.',
            ],
            [
                'nama' => 'Sea Salt Latte',
                'kategori' => 'Coffee Flavoured',
                'harga' => 18000,
                'stok' => 50,
                'deskripsi' => 'Latte dengan sentuhan sea salt.',
            ],
            [
                'nama' => 'Butterscotch Latte',
                'kategori' => 'Coffee Flavoured',
                'harga' => 18000,
                'stok' => 50,
                'deskripsi' => 'Latte manis dengan rasa butterscotch.',
            ],
            [
                'nama' => 'Caramel Macchiato',
                'kategori' => 'Coffee Flavoured',
                'harga' => 20000,
                'stok' => 50,
                'deskripsi' => 'Macchiato dengan rasa karamel.',
            ],
            [
                'nama' => 'Butterscotch Caramel',
                'kategori' => 'Coffee Flavoured',
                'harga' => 20000,
                'stok' => 50,
                'deskripsi' => 'Perpaduan rasa butterscotch dan karamel.',
            ],
            [
                'nama' => 'Offroad Creamy Latte',
                'kategori' => 'Coffee Flavoured',
                'harga' => 22000,
                'stok' => 50,
                'deskripsi' => 'Latte creamy spesial khas Jimny Coffee.',
            ],

            // ======================
            // NON COFFEE
            // ======================
            [
                'nama' => 'Matcha',
                'kategori' => 'Non Coffee',
                'harga' => 20000,
                'stok' => 50,
                'deskripsi' => 'Minuman matcha lembut.',
            ],
            [
                'nama' => 'Signature Chocolate',
                'kategori' => 'Non Coffee',
                'harga' => 18000,
                'stok' => 50,
                'deskripsi' => 'Minuman cokelat signature.',
            ],
            [
                'nama' => 'Taro Latte',
                'kategori' => 'Non Coffee',
                'harga' => 18000,
                'stok' => 50,
                'deskripsi' => 'Minuman taro manis dan creamy.',
            ],
            [
                'nama' => 'Red Velvet Latte',
                'kategori' => 'Non Coffee',
                'harga' => 18000,
                'stok' => 50,
                'deskripsi' => 'Latte red velvet yang lembut.',
            ],

            // ======================
            // TEA
            // ======================
            [
                'nama' => 'Signature Tea',
                'kategori' => 'Tea',
                'harga' => 6000,
                'stok' => 50,
                'deskripsi' => 'Teh signature khas Jimny Coffee.',
            ],
            [
                'nama' => 'Tea Telang',
                'kategori' => 'Tea',
                'harga' => 10000,
                'stok' => 50,
                'deskripsi' => 'Teh bunga telang yang segar.',
            ],
            [
                'nama' => 'Vanilla Tea',
                'kategori' => 'Tea',
                'harga' => 10000,
                'stok' => 50,
                'deskripsi' => 'Teh dengan rasa vanilla.',
            ],
            [
                'nama' => 'Lychee Tea',
                'kategori' => 'Tea',
                'harga' => 12000,
                'stok' => 50,
                'deskripsi' => 'Teh segar rasa leci.',
            ],
            [
                'nama' => 'Lemon Tea',
                'kategori' => 'Tea',
                'harga' => 12000,
                'stok' => 50,
                'deskripsi' => 'Teh segar dengan lemon.',
            ],
            [
                'nama' => 'Milk Tea',
                'kategori' => 'Tea',
                'harga' => 15000,
                'stok' => 50,
                'deskripsi' => 'Teh susu creamy.',
            ],

            // ======================
            // FOOD
            // ======================
            [
                'nama' => 'Nasi Ayam Betutu',
                'kategori' => 'Food',
                'harga' => 16000,
                'stok' => 50,
                'deskripsi' => 'Nasi ayam betutu khas Bali.',
            ],
            [
                'nama' => 'Indomie',
                'kategori' => 'Food',
                'harga' => 6000,
                'stok' => 50,
                'deskripsi' => 'Indomie. Harga variasi mulai 6k - 8k.',
            ],
            [
                'nama' => 'Indomie Telur',
                'kategori' => 'Food',
                'harga' => 12000,
                'stok' => 50,
                'deskripsi' => 'Indomie dengan tambahan telur.',
            ],

            // ======================
            // SNACK
            // ======================
            [
                'nama' => 'Roti',
                'kategori' => 'Snack',
                'harga' => 6000,
                'stok' => 50,
                'deskripsi' => 'Roti. Harga variasi mulai 6k - 12k.',
            ],
            [
                'nama' => 'Dimsum',
                'kategori' => 'Snack',
                'harga' => 12000,
                'stok' => 50,
                'deskripsi' => 'Dimsum hangat.',
            ],
            [
                'nama' => 'Roti Bakar',
                'kategori' => 'Snack',
                'harga' => 12000,
                'stok' => 50,
                'deskripsi' => 'Roti bakar dengan topping pilihan.',
            ],
            [
                'nama' => 'Kentang Goreng',
                'kategori' => 'Snack',
                'harga' => 12000,
                'stok' => 50,
                'deskripsi' => 'Kentang goreng renyah.',
            ],
            [
                'nama' => 'Nugget',
                'kategori' => 'Snack',
                'harga' => 12000,
                'stok' => 50,
                'deskripsi' => 'Nugget gurih dan renyah.',
            ],

            // ======================
            // ICE CREAM
            // ======================
            [
                'nama' => 'Affogato',
                'kategori' => 'Ice Cream',
                'harga' => 16000,
                'stok' => 50,
                'deskripsi' => 'Espresso dengan es krim.',
            ],
            [
                'nama' => 'Extra Ice Cream',
                'kategori' => 'Ice Cream',
                'harga' => 5000,
                'stok' => 50,
                'deskripsi' => 'Tambahan ice cream.',
            ],
        ];

        foreach ($menus as $menu) {
            $category = Category::where('nama_kategori', $menu['kategori'])->first();

            if (!$category) {
                continue;
            }

            Menu::updateOrCreate(
                ['slug' => Str::slug($menu['nama'])],
                [
                    'category_id'  => $category->id,
                    'nama_menu'    => $menu['nama'],
                    'slug'         => Str::slug($menu['nama']),
                    'deskripsi'    => $menu['deskripsi'],
                    'harga'        => $menu['harga'],
                    'stok'         => $menu['stok'],
                    'minimum_stok' => 5,
                    'gambar'       => null,
                    'is_active'    => true,
                ]
            );
        }
    }
}