<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_submit_leave_request_with_valid_shift(): void
    {
        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        Shift::create([
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-29',
            'jam_masuk' => '08:00:00',
            'jam_keluar' => '16:00:00',
            'status' => 'aktif',
        ]);

        $response = $this
            ->actingAs($cashier)
            ->post(route('izin.store'), [
                'tanggal' => '2026-09-29',
                'alasan' => 'Keperluan keluarga.',
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-29',
            'status' => 'pending',
        ]);
    }


    public function test_cashier_cannot_submit_leave_without_shift(): void
    {
        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $response = $this
            ->actingAs($cashier)
            ->post(route('izin.store'), [
                'tanggal' => '2026-09-29',
                'alasan' => 'Keperluan keluarga.',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }


    public function test_cashier_cannot_submit_leave_after_clock_in(): void
    {
        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $shift = Shift::create([
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-29',
            'jam_masuk' => '08:00:00',
            'jam_keluar' => '16:00:00',
            'status' => 'aktif',
        ]);

        Attendance::create([
            'user_id' => $cashier->id,
            'shift_id' => $shift->id,
            'tanggal' => '2026-09-29',
            'clock_in' => '2026-09-29 08:00:00',
            'clock_out' => null,
            'status' => 'hadir',
        ]);

        $response = $this
            ->actingAs($cashier)
            ->post(route('izin.store'), [
                'tanggal' => '2026-09-29',
                'alasan' => 'Keperluan keluarga.',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }


    public function test_admin_can_approve_pending_leave(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        Shift::create([
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-29',
            'jam_masuk' => '08:00:00',
            'jam_keluar' => '16:00:00',
            'status' => 'aktif',
        ]);

        $leave = LeaveRequest::create([
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-29',
            'alasan' => 'Keperluan keluarga.',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.izin.approve', $leave->id));

        $response
            ->assertStatus(302);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-29',
            'status' => 'izin',
        ]);
    }


    public function test_admin_can_reject_pending_leave(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $leave = LeaveRequest::create([
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-29',
            'alasan' => 'Keperluan keluarga.',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.izin.reject', $leave->id));

        $response
            ->assertStatus(302);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'rejected',
        ]);
    }
}