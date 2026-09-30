<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerController extends Controller
{
    public function dashboard()
    {
        $today = today();
        $end = $today->copy()->addDay();
        $yesterday = $today->copy()->subDay();
        $start = $today->copy()->subDays(6);
        $slowStart = $today->copy()->subDays(29);

        // Same transaction-date basis as the existing sales reports.
        $paid = Transaction::query()->where('status', 'success')->where('payment_status', 'paid');

        $totalTransaksi = (clone $paid)
            ->where('created_at', '>=', $today)
            ->where('created_at', '<', $end)
            ->count();

        $omzetHarian = (int) (clone $paid)
            ->where('created_at', '>=', $today)
            ->where('created_at', '<', $end)
            ->sum('grand_total');

        $omzetKemarin = (int) (clone $paid)
            ->where('created_at', '>=', $yesterday)
            ->where('created_at', '<', $today)
            ->sum('grand_total');

        $rataRataTransaksi = $totalTransaksi > 0
            ? (int) round($omzetHarian / $totalTransaksi)
            : 0;

        $perubahanOmzetNominal = $omzetHarian - $omzetKemarin;
        $perubahanOmzetPersen = $omzetKemarin > 0
            ? round(($perubahanOmzetNominal / $omzetKemarin) * 100, 1)
            : null;

        $omzetBulanan = (int) (clone $paid)
            ->where('created_at', '>=', $today->copy()->startOfMonth())
            ->where('created_at', '<', $end)
            ->sum('grand_total');

        $daily = (clone $paid)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('DATE(created_at) as tanggal, SUM(grand_total) as omzet')
            ->groupByRaw('DATE(created_at)')
            ->pluck('omzet', 'tanggal');

        $omzetMingguan = collect(range(0, 6))->map(function ($offset) use ($start, $daily) {
            $date = $start->copy()->addDays($offset);

            return [
                'label' => $date->format('d M'),
                'tanggal' => $date->toDateString(),
                'omzet' => (int) ($daily[$date->toDateString()] ?? 0),
            ];
        });

        $totalKasir = User::where('role', 'kasir')->count();
        $kasirHadir = User::where('role', 'kasir')
            ->whereHas('attendances', function ($query) use ($today) {
                $query->whereDate('tanggal', $today)->whereNotNull('clock_in');
            })
            ->count();

        $menuTerlaris = Menu::select('menus.id', 'menus.nama_menu', DB::raw('SUM(transaction_details.qty) as total_terjual'))
            ->join('transaction_details', 'menus.id', '=', 'transaction_details.menu_id')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'success')
            ->where('transactions.payment_status', 'paid')
            ->where('transactions.created_at', '>=', $start)
            ->where('transactions.created_at', '<', $end)
            ->groupBy('menus.id', 'menus.nama_menu')
            ->orderByDesc('total_terjual')
            ->orderBy('menus.id')
            ->take(5)
            ->get();

        $menuKurangLaku = Menu::query()
            ->select('menus.id', 'menus.nama_menu')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN transactions.status = ? AND transactions.payment_status = ? AND transactions.created_at >= ? AND transactions.created_at < ? THEN transaction_details.qty ELSE 0 END), 0) as total_terjual',
                ['success', 'paid', $slowStart, $end]
            )
            ->leftJoin('transaction_details', 'menus.id', '=', 'transaction_details.menu_id')
            ->leftJoin('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('menus.is_active', true)
            ->groupBy('menus.id', 'menus.nama_menu')
            ->orderBy('total_terjual')
            ->orderBy('menus.nama_menu')
            ->take(5)
            ->get();

        $stokHampirHabis = Menu::where('is_active', true)
            ->whereColumn('stok', '<=', 'minimum_stok')
            ->orderBy('stok')
            ->orderBy('nama_menu')
            ->get();

        $stokKritis = $stokHampirHabis->count();

        return view('owner.dashboard', compact(
            'totalTransaksi',
            'omzetHarian',
            'omzetKemarin',
            'rataRataTransaksi',
            'perubahanOmzetNominal',
            'perubahanOmzetPersen',
            'omzetBulanan',
            'totalKasir',
            'kasirHadir',
            'omzetMingguan',
            'menuTerlaris',
            'menuKurangLaku',
            'stokHampirHabis',
            'stokKritis'
        ));
    }

    public function rekapKehadiran(Request $request)
    {
        $bulan = $request->bulan ?? now()->month;
        $tahun = $request->tahun ?? now()->year;

        $kasirs = User::where('role', 'kasir')
            ->with([
                'shifts' => function ($q) use ($bulan, $tahun) {
                    $q->whereMonth('tanggal', $bulan)
                        ->whereYear('tanggal', $tahun);
                },
                'attendances' => function ($q) use ($bulan, $tahun) {
                    $q->whereMonth('tanggal', $bulan)
                        ->whereYear('tanggal', $tahun);
                },
            ])
            ->orderBy('name')
            ->get();

        return view('owner.rekap-kehadiran', compact('kasirs', 'bulan', 'tahun'));
    }
}
