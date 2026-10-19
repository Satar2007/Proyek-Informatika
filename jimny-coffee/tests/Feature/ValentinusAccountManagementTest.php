<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ValentinusAccountManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_view_account_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.akun.index'))
            ->assertOk();
    }

    public function test_admin_can_create_cashier_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->post(route('admin.akun.store'), [
                'name' => 'Kasir Baru Test',
                'email' => 'kasir-baru-valent@example.test',
                'password' => 'password123',
                'role' => 'kasir',
            ])
            ->assertRedirect(route('admin.akun.index'))
            ->assertSessionHas('success');

        $user = User::where(
            'email',
            'kasir-baru-valent@example.test'
        )->firstOrFail();

        $this->assertSame('kasir', $user->role);
        $this->assertTrue(
            Hash::check('password123', $user->password)
        );
    }

    public function test_admin_cannot_create_duplicate_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existing = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->post(route('admin.akun.store'), [
                'name' => 'Email Duplikat',
                'email' => $existing->email,
                'password' => 'password123',
                'role' => 'kasir',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(
            1,
            User::where('email', $existing->email)->count()
        );
    }

    public function test_admin_cannot_assign_invalid_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->post(route('admin.akun.store'), [
                'name' => 'Role Tidak Valid',
                'email' => 'role-invalid-valent@example.test',
                'password' => 'password123',
                'role' => 'superadmin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', [
            'email' => 'role-invalid-valent@example.test',
        ]);
    }

    public function test_admin_cannot_create_short_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->post(route('admin.akun.store'), [
                'name' => 'Password Pendek',
                'email' => 'password-pendek-valent@example.test',
                'password' => '12345',
                'role' => 'kasir',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_admin_can_update_account_without_changing_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'kasir']);

        $oldPassword = $user->password;

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->put(route('admin.akun.update', $user), [
                'name' => 'Nama Diperbarui',
                'email' => $user->email,
                'password' => '',
                'role' => 'owner',
            ])
            ->assertRedirect(route('admin.akun.index'))
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertSame('Nama Diperbarui', $user->name);
        $this->assertSame('owner', $user->role);
        $this->assertSame($oldPassword, $user->password);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->delete(route('admin.akun.destroy', $admin))
            ->assertRedirect(route('admin.akun.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }

    public function test_admin_can_delete_account_without_active_transactions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->delete(route('admin.akun.destroy', $cashier))
            ->assertRedirect(route('admin.akun.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', [
            'id' => $cashier->id,
        ]);
    }
}