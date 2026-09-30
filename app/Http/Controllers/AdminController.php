<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Transaction;
use App\Models\User;
use App\Models\StockLog;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalTransaksi = Transaction::where('status', 'success')->count();

        $omzetHarian = Transaction::where('status', 'success')
            ->whereDate('created_at', today())
            ->sum('grand_total');

        $omzetBulanan = Transaction::where('status', 'success')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('grand_total');

        $totalKasir = User::where('role', 'kasir')->count();

        $menuTerlaris = Menu::select(
                'menus.id',
                'menus.nama_menu',
                'menus.harga',
                'menus.stok',
                DB::raw('COALESCE(SUM(transaction_details.qty), 0) as total_terjual')
            )
            ->join('transaction_details', 'menus.id', '=', 'transaction_details.menu_id')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'success')
            ->groupBy('menus.id', 'menus.nama_menu', 'menus.harga', 'menus.stok')
            ->orderByDesc('total_terjual')
            ->take(5)
            ->get();

        $stokHampirHabis = Menu::where('stok', '<=', 5)
            ->where('is_active', true)
            ->get();

        $transaksiTerbaru = Transaction::with('user')
            ->where('status', 'success')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalTransaksi',
            'omzetHarian',
            'omzetBulanan',
            'totalKasir',
            'menuTerlaris',
            'stokHampirHabis',
            'transaksiTerbaru'
        ));
    }

    public function transaksi()
    {
        $transaksis = Transaction::with('user', 'details.menu')
            ->latest()
            ->paginate(20);

        return view('admin.transaksi', compact('transaksis'));
    }

    public function stokLog()
    {
        $logs = StockLog::with('menu', 'createdBy')
            ->latest()
            ->paginate(20);

        return view('admin.stok-log', compact('logs'));
    }
}