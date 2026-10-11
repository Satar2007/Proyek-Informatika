<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftGenerateAllDaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_shift_for_every_date_without_hari_kerja_input(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $kasir = User::factory()->create([
            'role' => 'kasir',
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.shift.index'))
            ->post(route('admin.shift.store'), [
                'user_id' => $kasir->id,
                'tanggal_mulai' => '2026-10-12',
                'tanggal_akhir' => '2026-10-14',
                'jam_masuk' => '08:00',
                'jam_keluar' => '16:00',
                'catatan' => 'Regression test all days',
            ]);

        $response->assertRedirect(route('admin.shift.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('shifts', 3);

        foreach ([
            '2026-10-12',
            '2026-10-13',
            '2026-10-14',
        ] as $tanggal) {
            $this->assertDatabaseHas('shifts', [
                'user_id' => $kasir->id,
                'tanggal' => $tanggal,
            ]);
        }
    }
}