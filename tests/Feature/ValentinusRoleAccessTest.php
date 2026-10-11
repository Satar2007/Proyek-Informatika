<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ValentinusRoleAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_cannot_access_admin_accounts(): void
    {
        $this->get('/admin/akun')->assertRedirect('/login');
    }

    public function test_guest_cannot_access_owner_dashboard(): void
    {
        $this->get('/owner/dashboard')->assertRedirect('/login');
    }

    public function test_cashier_cannot_access_admin_accounts(): void
    {
        $user = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($user)
            ->get('/admin/akun')
            ->assertForbidden();
    }

    public function test_cashier_cannot_create_accounts(): void
    {
        $user = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($user)
            ->post('/admin/akun', [])
            ->assertForbidden();
    }

    public function test_cashier_cannot_access_owner_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($user)
            ->get('/owner/dashboard')
            ->assertForbidden();
    }

    public function test_owner_cannot_access_admin_accounts(): void
    {
        $user = User::factory()->create(['role' => 'owner']);

        $this->actingAs($user)
            ->get('/admin/akun')
            ->assertForbidden();
    }

    public function test_owner_cannot_create_accounts(): void
    {
        $user = User::factory()->create(['role' => 'owner']);

        $this->actingAs($user)
            ->post('/admin/akun', [])
            ->assertForbidden();
    }

    public function test_owner_cannot_access_pos(): void
    {
        $user = User::factory()->create(['role' => 'owner']);

        $this->actingAs($user)
            ->get('/kasir')
            ->assertForbidden();
    }

    public function test_admin_cannot_access_owner_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/owner/dashboard')
            ->assertForbidden();
    }
}
