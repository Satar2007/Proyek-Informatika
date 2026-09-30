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
        $start = $today->copy()->subDays(6);
        // Same transaction-date basis as the existing sales reports.
        $paid = Transaction::query()->where('status', 'success')->where('payment_status', 'paid');
        $totalTransaksi = (clone $paid)->where('created_at', '>=', $today)->where('created_at', '<', $end)->count();
        $omzetHarian = (clone $paid)->where('created_at', '>=', $today)->where('created_at', '<', $end)->sum('grand_total');
        $omzetBulanan = (clone $paid)->where('created_at', '>=', $today->copy()->startOfMonth())->where('created_at', '<', $end)->sum('grand_total');
        $daily = (clone $paid)->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('DATE(created_at) as tanggal, SUM(grand_total) as omzet')
            ->groupByRaw('DATE(created_at)')->pluck('omzet', 'tanggal');
        $omzetMingguan = collect(range(0, 6))->map(function ($offset) use ($start, $daily) {
            $date = $start->copy()->addDays($offset);
            return ['label' => $date->format('d M'), 'tanggal' => $date->toDateString(), 'omzet' => (int) ($daily[$date->toDateString()] ?? 0)];
        });
        $totalKasir = User::where('role', 'kasir')->count();
        $kasirHadir = User::where('role', 'kasir')->whereHas('attendances', function ($query) use ($today) {
            $query->whereDate('tanggal', $today)->whereNotNull('clock_in');
        })->count();
        $menuTerlaris = Menu::select('menus.id', 'menus.nama_menu', DB::raw('SUM(transaction_details.qty) as total_terjual'))
            ->join('transaction_details', 'menus.id', '=', 'transaction_details.menu_id')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'success')->where('transactions.payment_status', 'paid')
            ->where('transactions.created_at', '>=', $start)->where('transactions.created_at', '<', $end)
            ->groupBy('menus.id', 'menus.nama_menu')->orderByDesc('total_terjual')->orderBy('menus.id')->take(5)->get();
        $stokHampirHabis = Menu::where('is_active', true)
            ->whereColumn('stok', '<=', 'minimum_stok')->orderBy('stok')->orderBy('nama_menu')->get();
        return view('owner.dashboard', compact('totalTransaksi', 'omzetHarian', 'omzetBulanan',
            'totalKasir', 'kasirHadir', 'omzetMingguan', 'menuTerlaris', 'stokHampirHabis'));
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