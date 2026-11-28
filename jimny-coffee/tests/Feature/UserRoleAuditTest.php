<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRoleAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleAuditTest extends TestCase
{
    use RefreshDatabase;
    public function test_admin_owner_creation_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.akun.store'), [
                'name' => 'Owner Audit',
                'email' => 'owner-audit@example.test',
                'password' => 'StrongPassword123!',
                'role' => 'owner',
            ])
            ->assertSessionHas('success');

        $target = User::where('email', 'owner-audit@example.test')->firstOrFail();

        $this->assertDatabaseHas('user_role_audits', [
            'actor_user_id' => $admin->id,
    'actor_role' => 'admin',
            'target_user_id' => $target->id,
            'event' => 'account_created',
            'old_role' => null,
            'new_role' => 'owner',
            'target_email' => 'owner-audit@example.test',
        ]);
    }

    public function test_admin_role_promotion_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($admin)
            ->put(route('admin.akun.update', $cashier), [
                'name' => $cashier->name,
                'email' => $cashier->email,
                'password' => '',
                'role' => 'owner',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_role_audits', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $cashier->id,
            'event' => 'role_changed',
            'old_role' => 'kasir',
            'new_role' => 'owner',
        ]);
    }

    public function test_no_role_audit_is_written_when_role_does_not_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($admin)
            ->put(route('admin.akun.update', $cashier), [
                'name' => 'Updated Cashier Name',
                'email' => $cashier->email,
                'password' => '',
                'role' => 'kasir',
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, UserRoleAudit::count());
    }

    public function test_role_audit_keeps_snapshots_when_target_is_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($admin)
            ->put(route('admin.akun.update', $cashier), [
                'name' => $cashier->name,
                'email' => $cashier->email,
                'password' => '',
                'role' => 'owner',
            ])
            ->assertSessionHas('success');

        $audit = UserRoleAudit::firstOrFail();
        User::factory()->create(['role' => 'owner']);
        $cashier->delete();

        $audit->refresh();

        $this->assertNull($audit->target_user_id);
        $this->assertSame($cashier->name, $audit->target_name);
        $this->assertSame($cashier->email, $audit->target_email);
    }

    public function test_role_audit_page_is_admin_only(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($owner)
            ->get(route('admin.audit-role.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.audit-role.index'))
            ->assertOk();
    }

    public function test_admin_account_deletion_is_audited_with_target_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $this->actingAs($admin)
            ->delete(route('admin.akun.destroy', $cashier))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_role_audits', [
            'actor_user_id' => $admin->id,
            'target_user_id' => null,
            'target_name' => $cashier->name,
            'target_email' => $cashier->email,
            'event' => 'account_deleted',
            'old_role' => 'kasir',
            'new_role' => null,
        ]);
    }
}
