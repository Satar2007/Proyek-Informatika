<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class AttendanceController extends Controller
{
    public function clockIn()
    {
        $user = auth()->user();
        $today = today()->toDateString();

        try {
            $result = DB::transaction(function () use ($user, $today) {
                $shift = Shift::where('user_id', $user->id)
                    ->whereDate('tanggal', $today)
                    ->lockForUpdate()
                    ->first();

                if (!$shift) {
                    throw new Exception('Tidak ada jadwal shift hari ini.');
                }

                $attendance = Attendance::where('user_id', $user->id)
                    ->whereDate('tanggal', $today)
                    ->lockForUpdate()
                    ->first();

                if ($attendance && $attendance->status === 'izin') {
                    throw new Exception('Anda sudah tercatat izin hari ini. Clock in tidak bisa dilakukan.');
                }

                if ($attendance && $attendance->clock_in) {
                    throw new Exception('Anda sudah clock in hari ini.');
                }

                if ($attendance && $attendance->status === 'tidak_hadir') {
                    throw new Exception('Anda sudah tercatat alfa hari ini karena melewati batas toleransi keterlambatan.');
                }

                $now = now();

                $jamMasuk = Carbon::parse($today . ' ' . $shift->jam_masuk);
                $batasToleransi = $jamMasuk->copy()->addMinutes(30);

                if ($now->lessThanOrEqualTo($jamMasuk)) {
                    $status = 'hadir';
                    $message = 'Clock in berhasil. Status Anda: Hadir.';
                } elseif ($now->lessThanOrEqualTo($batasToleransi)) {
                    $status = 'terlambat';
                    $menitTerlambat = $jamMasuk->diffInMinutes($now);
                    $message = "Clock in berhasil. Status Anda: Terlambat {$menitTerlambat} menit.";
                } else {
                    Attendance::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'tanggal' => $today,
                        ],
                        [
                            'shift_id' => $shift->id,
                            'clock_in' => null,
                            'clock_out' => null,
                            'status' => 'tidak_hadir',
                            'catatan' => 'Alfa karena clock in melewati toleransi 30 menit.',
                        ]
                    );

                    throw new Exception('Anda melewati toleransi keterlambatan 30 menit. Status Anda tercatat alfa.');
                }

                Attendance::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'tanggal' => $today,
                    ],
                    [
                        'shift_id' => $shift->id,
                        'clock_in' => $now,
                        'status' => $status,
                        'catatan' => $status === 'terlambat'
                            ? 'Terlambat clock in. Toleransi maksimal 30 menit.'
                            : null,
                    ]
                );

                return [
                    'message' => $message,
                    'status' => $status,
                    'time' => $now->format('H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'status' => $result['status'],
                'time' => $result['time'],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function clockOut()
    {
        $user = auth()->user();
        $today = today()->toDateString();

        try {
            DB::transaction(function () use ($user, $today) {
                $attendance = Attendance::where('user_id', $user->id)
                    ->whereDate('tanggal', $today)
                    ->lockForUpdate()
                    ->first();

                if (!$attendance || !$attendance->clock_in) {
                    throw new Exception('Anda belum clock in hari ini.');
                }

                if ($attendance->status === 'izin') {
                    throw new Exception('Anda tercatat izin hari ini. Clock out tidak bisa dilakukan.');
                }

                if ($attendance->status === 'tidak_hadir') {
                    throw new Exception('Anda tercatat alfa hari ini. Clock out tidak bisa dilakukan.');
                }

                if ($attendance->clock_out) {
                    throw new Exception('Anda sudah clock out hari ini.');
                }

                $attendance->update([
                    'clock_out' => now(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Clock out berhasil. Terima kasih sudah bekerja.',
                'time' => now()->format('H:i:s'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function status()
    {
        $user = auth()->user();
        $today = today()->toDateString();

        $shift = Shift::where('user_id', $user->id)
            ->whereDate('tanggal', $today)
            ->first();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('tanggal', $today)
            ->first();

        return response()->json([
            'shift' => $shift,
            'attendance' => $attendance,
            'clock_in' => $attendance?->clock_in?->format('H:i:s'),
            'clock_out' => $attendance?->clock_out?->format('H:i:s'),
        ]);
    }
}