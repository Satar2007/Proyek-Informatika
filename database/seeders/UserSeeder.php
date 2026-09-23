<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@coffee.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'kasir@coffee.com'],
            [
                'name'     => 'Kasir',
                'password' => Hash::make('password'),
                'role'     => 'kasir',
            ]
        );

        User::updateOrCreate(
            ['email' => 'owner@coffee.com'],
            [
                'name'     => 'Owner',
                'password' => Hash::make('password'),
                'role'     => 'owner',
            ]
        );
    }
}