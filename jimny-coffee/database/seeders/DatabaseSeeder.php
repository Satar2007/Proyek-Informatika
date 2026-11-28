<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'DatabaseSeeder dinonaktifkan di production. '
                . 'Jalankan seeder terpilih setelah review dan backup.'
            );
        }

        $this->call([
            CategorySeeder::class,
            MenuSeeder::class,
        ]);

        // Default accounts are opt-in for local development only.
        if (
            app()->environment('local')
            && (bool) config('jimny.seed_default_users', false)
        ) {
            $this->call(UserSeeder::class);
        }
    }
}
