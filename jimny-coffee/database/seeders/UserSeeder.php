<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (
            !app()->environment('local')
            || !(bool) config('jimny.seed_default_users', false)
        ) {
            return;
        }

        $password = (string) config('jimny.seed_default_password', '');

        if (strlen($password) < 16) {
            throw new RuntimeException(
                'JIMNY_SEED_DEFAULT_PASSWORD wajib diisi minimal 16 karakter '
                . 'ketika JIMNY_SEED_DEFAULT_USERS=true.'
            );
        }

        $accounts = [
            [
                'email' => 'admin@coffee.com',
                'name' => 'Admin',
                'role' => 'admin',
            ],
            [
                'email' => 'kasir@coffee.com',
                'name' => 'Kasir',
                'role' => 'kasir',
            ],
            [
                'email' => 'owner@coffee.com',
                'name' => 'Owner',
                'role' => 'owner',
            ],
        ];

        foreach ($accounts as $account) {
            // firstOrCreate never resets an existing user's password or role.
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($password),
                    'role' => $account['role'],
                ]
            );
        }
    }
}
