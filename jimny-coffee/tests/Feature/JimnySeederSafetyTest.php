<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class JimnySeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_seeder_does_not_overwrite_existing_operational_state(): void
    {
        $category = Category::create([
            'nama_kategori' => 'Coffee',
        ]);

        $menu = Menu::create([
            'category_id' => $category->id,
            'nama_menu' => 'Tubruk Custom',
            'slug' => 'tubruk',
            'deskripsi' => 'Deskripsi operasional',
            'harga' => 9900,
            'stok' => 7,
            'minimum_stok' => 2,
            'is_active' => false,
        ]);

        (new MenuSeeder())->run();

        $menu->refresh();

        $this->assertSame(7, (int) $menu->stok);
        $this->assertSame(9900, (int) $menu->harga);
        $this->assertSame(2, (int) $menu->minimum_stok);
        $this->assertFalse((bool) $menu->is_active);
        $this->assertSame('Deskripsi operasional', $menu->deskripsi);
    }

    public function test_user_seeder_is_noop_outside_local_environment(): void
    {
        $originalPassword = Hash::make('ExistingStrongPassword!123');
        $existing = User::factory()->create([
            'name' => 'Existing Admin',
            'email' => 'admin@coffee.com',
            'password' => $originalPassword,
            'role' => 'kasir',
        ]);

        $this->app['env'] = 'production';

        config([
            'jimny.seed_default_users' => true,
            'jimny.seed_default_password' => 'ThisIsStrongPassword!123',
        ]);

        (new UserSeeder())->run();

        $existing->refresh();

        $this->assertSame('Existing Admin', $existing->name);
        $this->assertSame('kasir', $existing->role);
        $this->assertSame($originalPassword, $existing->password);
        $this->assertDatabaseMissing('users', [
            'email' => 'owner@coffee.com',
        ]);
    }

    public function test_database_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DatabaseSeeder dinonaktifkan di production');

        (new DatabaseSeeder())->run();
    }

    public function test_local_user_seeder_does_not_reset_existing_account(): void
    {
        $originalPassword = Hash::make('ExistingStrongPassword!123');
        $existing = User::factory()->create([
            'name' => 'Custom Local Admin',
            'email' => 'admin@coffee.com',
            'password' => $originalPassword,
            'role' => 'owner',
        ]);
        $this->app['env'] = 'local';

        config([
            'jimny.seed_default_users' => true,
            'jimny.seed_default_password' => 'LocalOnlyStrongPassword!123',
        ]);

        (new UserSeeder())->run();

        $existing->refresh();

        $this->assertSame('Custom Local Admin', $existing->name);
        $this->assertSame('owner', $existing->role);
        $this->assertSame($originalPassword, $existing->password);
    }

    public function test_local_user_seeder_requires_explicit_strong_password(): void
    {
        $this->app['env'] = 'local';

        config([
            'jimny.seed_default_users' => true,
            'jimny.seed_default_password' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('minimal 16 karakter');

        (new UserSeeder())->run();
    }
}
