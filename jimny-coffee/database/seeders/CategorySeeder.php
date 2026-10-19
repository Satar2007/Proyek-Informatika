<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Coffee',
            'Coffee Flavoured',
            'Milk Base',
            'Non Coffee',
            'Tea',
            'Food',
            'Snack',
            'Ice Cream',
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['nama_kategori' => $category],
                ['nama_kategori' => $category]
            );
        }
    }
}