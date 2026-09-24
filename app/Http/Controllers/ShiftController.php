<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Throwable;

class ShiftController extends Controller
{
    public function index()
    {
        $shifts = Shift::with('user')
            ->orderBy('tanggal', 'desc')
            ->orderBy('jam_masuk')
            ->paginate(20);

        $kasirs = User::where('role', 'kasir')
            ->orderBy('name')
            ->get();

        return view('admin.shift.index', compact('shifts', 'kasirs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'       => ['required', 'exists:users,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_akhir' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'hari_kerja'    => ['required', 'array', 'min:1'],
            'hari_kerja.*'  => ['required', 'integer', 'between:0,6'],
            'jam_masuk'     => ['required'],
            'jam_keluar'    => ['required'],
            'catatan'       => ['nullable', 'string', 'max:255'],
        ], [
            'user_id.required' => 'Kasir wajib dipilih.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_akhir.required' => 'Tanggal akhir wajib diisi.',
            'tanggal_akhir.after_or_equal' => 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.',
            'hari_kerja.required' => 'Minimal pilih satu hari kerja.',
            'jam_masuk.required' => 'Jam masuk wajib diisi.',
            'jam_keluar.required' => 'Jam keluar wajib diisi.',
        ]);

        try {
            $kasir = User::where('role', 'kasir')
                ->where('id', $validated['user_id'])
                ->first();

            if (!$kasir) {
                return back()
                    ->withInput()
                    ->with('error', 'User yang dipilih bukan kasir atau tidak ditemukan.');
            }

            $jamMasuk = str_replace('.', ':', $validated['jam_masuk']);
            $jamKeluar = str_replace('.', ':', $validated['jam_keluar']);

            if (strlen($jamMasuk) === 5) {
                $jamMasuk .= ':00';
            }

            if (strlen($jamKeluar) === 5) {
                $jamKeluar .= ':00';
            }

            $jamMasukCarbon = Carbon::createFromFormat('H:i:s', $jamMasuk);
            $jamKeluarCarbon = Carbon::createFromFormat('H:i:s', $jamKeluar);

            if ($jamKeluarCarbon->lessThanOrEqualTo($jamMasukCarbon)) {
                return back()
                    ->withInput()
                    ->with('error', 'Jam keluar harus lebih besar dari jam masuk.');
            }

            $tanggalMulai = Carbon::parse($validated['tanggal_mulai'])->startOfDay();
            $tanggalAkhir = Carbon::parse($validated['tanggal_akhir'])->startOfDay();

            $periode = CarbonPeriod::create($tanggalMulai, $tanggalAkhir);

            // Format hari sesuai checkbox Blade:
            // 0 = Minggu, 1 = Senin, 2 = Selasa, ..., 6 = Sabtu
            $hariDipilih = array_map('intval', $validated['hari_kerja']);

            $jumlahDibuat = 0;
            $jumlahDilewati = 0;

            DB::transaction(function () use (
                $periode,
                $hariDipilih,
                $kasir,
                $jamMasuk,
                $jamKeluar,
                $validated,
                &$jumlahDibuat,
                &$jumlahDilewati
            ) {
                foreach ($periode as $tanggal) {
                    if (!in_array((int) $tanggal->dayOfWeek, $hariDipilih, true)) {
                        continue;
                    }

                    $sudahAda = Shift::where('user_id', $kasir->id)
                        ->whereDate('tanggal', $tanggal->toDateString())
                        ->exists();

                    if ($sudahAda) {
                        $jumlahDilewati++;
                        continue;
                    }

                    Shift::create([
                        'user_id'    => $kasir->id,
                        'tanggal'    => $tanggal->toDateString(),
                        'jam_masuk'  => $jamMasuk,
                        'jam_keluar' => $jamKeluar,
                        'status'     => 'aktif',
                        'catatan'    => $validated['catatan'] ?? null,
                    ]);

                    $jumlahDibuat++;
                }
            });

            if ($jumlahDibuat === 0 && $jumlahDilewati === 0) {
                return back()
                    ->withInput()
                    ->with('error', 'Tidak ada shift yang dibuat. Pastikan hari kerja sesuai dengan rentang tanggal.');
            }

            return back()->with(
                'success',
                "Generate shift selesai. {$jumlahDibuat} shift dibuat, {$jumlahDilewati} tanggal dilewati karena sudah ada shift."
            );
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Gagal generate shift: ' . $e->getMessage());
        }
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();

        return back()->with('success', 'Shift berhasil dihapus.');
    }

    public function rekap(Request $request)
    {
        $bulan = $request->bulan ?? now()->month;
        $tahun = $request->tahun ?? now()->year;

        $kasirs = User::where('role', 'kasir')
            ->with([
                'shifts' => function ($q) use ($bulan, $tahun) {
                    $q->whereMonth('tanggal', $bulan)
                        ->whereYear('tanggal', $tahun)
                        ->orderBy('tanggal');
                },
                'attendances' => function ($q) use ($bulan, $tahun) {
                    $q->whereMonth('tanggal', $bulan)
                        ->whereYear('tanggal', $tahun);
                },
            ])
            ->orderBy('name')
            ->get();

        return view('admin.shift.rekap', compact('kasirs', 'bulan', 'tahun'));
    }
}