<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function harian(Request $request)
    {
        $validated = $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);
        $start = CarbonImmutable::parse($validated['tanggal'] ?? today()->toDateString())->startOfDay();
        return $this->report($start, $start->addDay(), false);
    }

    public function bulanan(Request $request)
    {
        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:1900,2100'],
        ]);
        $start = CarbonImmutable::create((int) ($validated['tahun'] ?? now()->year), (int) ($validated['bulan'] ?? now()->month), 1)->startOfDay();
        return $this->report($start, $start->addMonth(), true);
    }

    private function report(CarbonImmutable $start, CarbonImmutable $end, bool $monthly)
    {
        // Follow the existing report definition: successful orders, grouped by created_at.
        $query = Transaction::where('status', 'success')->where('created_at', '>=', $start)->where('created_at', '<', $end);
        $orders = (clone $query)->get(['id', 'created_at', 'grand_total', 'payment_method']);
        $total = (float) $orders->sum('grand_total');
        $count = $orders->count();
        $previousStart = $monthly ? $start->subMonth() : $start->subDay();
        $previousTotal = (float) Transaction::where('status', 'success')->where('created_at', '>=', $previousStart)->where('created_at', '<', $start)->sum('grand_total');
        $growth = $previousTotal > 0 ? (($total - $previousTotal) / $previousTotal) * 100 : null;
        $groups = $orders->groupBy(fn ($order) => $order->created_at->format($monthly ? 'Y-m-d' : 'H'));
        $series = collect();
        $slots = $monthly ? $start->daysInMonth : 24;
        for ($i = 0; $i < $slots; $i++) {
            $date = $monthly ? $start->addDays($i) : $start->addHours($i);
            $rows = $groups->get($date->format($monthly ? 'Y-m-d' : 'H'), collect());
            $series->push(['label' => $date->format($monthly ? 'd' : 'H:i'), 'date' => $date->toDateString(), 'total' => (float) $rows->sum('grand_total'), 'count' => $rows->count()]);
        }
        $payments = $orders->groupBy('payment_method')->map(fn ($rows, $method) => [
            'label' => match ($method) { 'cash' => 'Tunai', 'qris' => 'QRIS', default => ucfirst($method ?: 'Lainnya') },
            'total' => (float) $rows->sum('grand_total'), 'count' => $rows->count(),
        ])->values();
        $topMenus = TransactionDetail::query()->whereIn('transaction_id', (clone $query)->select('id'))
            ->selectRaw('menu_id, SUM(qty) as units, SUM(subtotal) as revenue')
            ->groupBy('menu_id')->orderByDesc('units')->orderBy('menu_id')->limit(5)->with('menu')->get();
        $transactions = $monthly ? null : (clone $query)->with('user')->latest()->paginate(15)->withQueryString();
        $peak = $series->sortByDesc('total')->first();
        return view('laporan.report', compact('start', 'monthly', 'total', 'count', 'previousTotal', 'growth', 'series', 'payments', 'topMenus', 'transactions', 'peak'));
    }
}
