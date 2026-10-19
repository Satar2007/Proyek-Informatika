<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class LeaveRequestController extends Controller
{
    public function index()
    {
        $requests = LeaveRequest::with(['user', 'approvedBy'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('tanggal', 'desc')
            ->paginate(20);

        return view('admin.izin.index', compact('requests'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tanggal' => ['required', 'date'],
            'alasan'  => ['required', 'string', 'max:500'],
        ]);

        $user = auth()->user();

        $shift = Shift::where('user_id', $user->id)
            ->whereDate('tanggal', $request->tanggal)
            ->first();

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa mengajukan izin karena tidak ada jadwal shift pada tanggal tersebut.',
            ], 422);
        }

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('tanggal', $request->tanggal)
            ->first();

        if ($attendance && $attendance->clock_in) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa mengajukan izin karena Anda sudah clock in pada tanggal tersebut.',
            ], 422);
        }

        $existingRequest = LeaveRequest::where('user_id', $user->id)
            ->whereDate('tanggal', $request->tanggal)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan izin untuk tanggal tersebut sudah ada.',
            ], 422);
        }

        LeaveRequest::create([
            'user_id' => $user->id,
            'tanggal' => $request->tanggal,
            'alasan' => $request->alasan,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin berhasil dikirim. Menunggu persetujuan admin.',
        ]);
    }

    public function approve($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $leaveRequest = LeaveRequest::with('user')
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($leaveRequest->status !== 'pending') {
                    throw new Exception('Pengajuan izin ini sudah diproses sebelumnya.');
                }

                $shift = Shift::where('user_id', $leaveRequest->user_id)
                    ->whereDate('tanggal', $leaveRequest->tanggal)
                    ->lockForUpdate()
                    ->first();

                if (!$shift) {
                    throw new Exception('Tidak bisa menyetujui izin karena kasir tidak memiliki jadwal shift pada tanggal tersebut.');
                }

                $attendance = Attendance::where('user_id', $leaveRequest->user_id)
                    ->whereDate('tanggal', $leaveRequest->tanggal)
                    ->lockForUpdate()
                    ->first();

                if ($attendance && $attendance->clock_in) {
                    throw new Exception('Tidak bisa menyetujui izin karena kasir sudah clock in pada tanggal tersebut.');
                }

                Attendance::updateOrCreate(
                    [
                        'user_id' => $leaveRequest->user_id,
                        'tanggal' => $leaveRequest->tanggal,
                    ],
                    [
                        'shift_id' => $shift->id,
                        'clock_in' => null,
                        'clock_out' => null,
                        'status' => 'izin',
                        'catatan' => 'Izin disetujui admin. Alasan: ' . $leaveRequest->alasan,
                    ]
                );

                $leaveRequest->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
            });

            return redirect()
                ->back()
                ->with('success', 'Pengajuan izin berhasil disetujui dan masuk ke rekap kehadiran sebagai izin.');
        } catch (Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function reject($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $leaveRequest = LeaveRequest::lockForUpdate()->findOrFail($id);

                if ($leaveRequest->status !== 'pending') {
                    throw new Exception('Pengajuan izin ini sudah diproses sebelumnya.');
                }

                $leaveRequest->update([
                    'status' => 'rejected',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
            });

            return redirect()
                ->back()
                ->with('success', 'Pengajuan izin berhasil ditolak.');
        } catch (Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
}