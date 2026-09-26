<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_late_clock_in_returns_422_and_persists_alpha(): void
    {
        Carbon::setTestNow('2026-09-26 10:31:00');

        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $shift = Shift::create([
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-26',
            'jam_masuk' => '10:00:00',
            'jam_keluar' => '18:00:00',
            'status' => 'aktif',
        ]);

        $response = $this
            ->actingAs($cashier)
            ->post(route('attendance.clock-in'));

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Anda melewati toleransi keterlambatan 30 menit. Status Anda tercatat alfa.'
            );

        $this->assertDatabaseHas('attendances', [
            'user_id' => $cashier->id,
            'shift_id' => $shift->id,
            'tanggal' => '2026-09-26',
            'status' => 'tidak_hadir',
            'clock_in' => null,
            'clock_out' => null,
        ]);
    }

    public function test_shift_with_attendance_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $shift = Shift::create([
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-26',
            'jam_masuk' => '08:00:00',
            'jam_keluar' => '16:00:00',
            'status' => 'aktif',
        ]);

        $attendance = Attendance::create([
            'user_id' => $cashier->id,
            'shift_id' => $shift->id,
            'tanggal' => '2026-09-26',
            'clock_in' => '2026-09-26 08:00:00',
            'clock_out' => '2026-09-26 16:00:00',
            'status' => 'hadir',
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.shift.destroy', $shift));

        $response
            ->assertStatus(302)
            ->assertSessionHas(
                'error',
                'Shift tidak dapat dihapus karena sudah memiliki riwayat kehadiran.'
            );

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'user_id' => $cashier->id,
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'shift_id' => $shift->id,
            'user_id' => $cashier->id,
            'status' => 'hadir',
        ]);
    }

    public function test_shift_without_attendance_history_can_be_deleted(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $shift = Shift::create([
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-27',
            'jam_masuk' => '08:00:00',
            'jam_keluar' => '16:00:00',
            'status' => 'aktif',
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.shift.destroy', $shift));

        $response
            ->assertStatus(302)
            ->assertSessionHas(
                'success',
                'Shift berhasil dihapus.'
            );

        $this->assertDatabaseMissing('shifts', [
            'id' => $shift->id,
        ]);
    }

    public function test_shift_with_pending_leave_cannot_be_deleted(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $cashier = User::factory()->create([
            'role' => 'kasir',
        ]);

        $shift = Shift::create([
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-28',
            'jam_masuk' => '08:00:00',
            'jam_keluar' => '16:00:00',
            'status' => 'aktif',
        ]);

        $leave = LeaveRequest::create([
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-28',
            'alasan' => 'Keperluan keluarga.',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.shift.destroy', $shift));

        $response
            ->assertStatus(302)
            ->assertSessionHas(
                'error',
                'Shift tidak dapat dihapus karena masih memiliki pengajuan izin yang menunggu persetujuan.'
            );

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'user_id' => $cashier->id,
        ]);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'user_id' => $cashier->id,
            'tanggal' => '2026-09-28',
            'status' => 'pending',
        ]);
    }
}
