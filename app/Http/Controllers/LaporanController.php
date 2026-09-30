<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    private const STATUS_OPTIONS = [
        'success' => 'Lunas',
        'pending' => 'Pending',
        'cancelled' => 'Dibatalkan',
        'expired' => 'Expired',
        'all' => 'Semua status',
    ];

    private const PAYMENT_OPTIONS = [
        'cash' => 'Tunai',
        'qris' => 'QRIS',
    ];

    public function harian(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            ...$this->commonValidationRules(),
        ]);

        $start = CarbonImmutable::parse(
            $validated['tanggal'] ?? today()->toDateString()
        )->startOfDay();

        return $this->report(
            $request,
            $start,
            $start->addDay(),
            false,
            $validated
        );
    }

    public function bulanan(Request $request)
    {
        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:1900,2100'],
            ...$this->commonValidationRules(),
        ]);

        $start = CarbonImmutable::create(
            (int) ($validated['tahun'] ?? now()->year),
            (int) ($validated['bulan'] ?? now()->month),
            1
        )->startOfDay();

        return $this->report(
            $request,
            $start,
            $start->addMonth(),
            true,
            $validated
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'periode' => ['required', 'in:harian,bulanan'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:1900,2100'],
            ...$this->commonValidationRules(),
        ]);

        $monthly = $validated['periode'] === 'bulanan';

        if ($monthly) {
            $start = CarbonImmutable::create(
                (int) ($validated['tahun'] ?? now()->year),
                (int) ($validated['bulan'] ?? now()->month),
                1
            )->startOfDay();
            $end = $start->addMonth();
        } else {
            $start = CarbonImmutable::parse(
                $validated['tanggal'] ?? today()->toDateString()
            )->startOfDay();
            $end = $start->addDay();
        }

        $filters = $this->normalizeFilters($request, $validated);

        $transactions = $this->scopedQuery($request, $filters)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->with('user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $filename = sprintf(
            'laporan-%s-%s.csv',
            $monthly ? 'bulanan' : 'harian',
            $monthly ? $start->format('Y-m') : $start->format('Y-m-d')
        );

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'wb');

            // BOM membantu Excel membaca UTF-8 dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Kode Transaksi',
                'Tanggal',
                'Waktu',
                'Pelanggan',
                'Petugas',
                'Metode Pembayaran',
                'Status',
                'Grand Total',
            ], ';', '"', '', "\r\n");

            foreach ($transactions as $transaction) {
                fputcsv($handle, [
                    $transaction->kode_transaksi,
                    $transaction->created_at->format('d/m/Y'),
                    $transaction->created_at->format('H:i:s'),
                    $transaction->nama_pelanggan,
                    $transaction->cashier_display_name,
                    self::PAYMENT_OPTIONS[$transaction->payment_method]
                        ?? strtoupper((string) $transaction->payment_method),
                    self::STATUS_OPTIONS[$transaction->status]
                        ?? ucfirst((string) $transaction->status),
                    (int) $transaction->grand_total,
                ], ';', '"', '', "\r\n");
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function report(
        Request $request,
        CarbonImmutable $start,
        CarbonImmutable $end,
        bool $monthly,
        array $validated
    ) {
        $filters = $this->normalizeFilters($request, $validated);

        $baseQuery = $this->scopedQuery($request, $filters);

        $query = (clone $baseQuery)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end);

        $orders = (clone $query)
            ->get([
                'id',
                'created_at',
                'grand_total',
                'payment_method',
                'status',
            ]);

        $total = (float) $orders->sum('grand_total');
        $count = $orders->count();

        $previousStart = $monthly
            ? $start->subMonth()
            : $start->subDay();

        $previousTotal = (float) (clone $baseQuery)
            ->where('created_at', '>=', $previousStart)
            ->where('created_at', '<', $start)
            ->sum('grand_total');

        $growth = $previousTotal > 0
            ? (($total - $previousTotal) / $previousTotal) * 100
            : null;

        $groups = $orders->groupBy(
            fn ($order) => $order->created_at->format(
                $monthly ? 'Y-m-d' : 'H'
            )
        );

        $series = collect();
        $slots = $monthly ? $start->daysInMonth : 24;

        for ($i = 0; $i < $slots; $i++) {
            $date = $monthly
                ? $start->addDays($i)
                : $start->addHours($i);

            $rows = $groups->get(
                $date->format($monthly ? 'Y-m-d' : 'H'),
                collect()
            );

            $series->push([
                'label' => $date->format($monthly ? 'd' : 'H:i'),
                'date' => $date->toDateString(),
                'total' => (float) $rows->sum('grand_total'),
                'count' => $rows->count(),
            ]);
        }

        $payments = $orders
            ->groupBy('payment_method')
            ->map(fn ($rows, $method) => [
                'label' => self::PAYMENT_OPTIONS[$method]
                    ?? ucfirst($method ?: 'Lainnya'),
                'total' => (float) $rows->sum('grand_total'),
                'count' => $rows->count(),
            ])
            ->values();

        $topMenus = TransactionDetail::query()
            ->whereIn('transaction_id', (clone $query)->select('id'))
            ->selectRaw(
                'menu_id, SUM(qty) as units, SUM(subtotal) as revenue'
            )
            ->groupBy('menu_id')
            ->orderByDesc('units')
            ->orderBy('menu_id')
            ->limit(5)
            ->with('menu')
            ->get();

        $transactions = $monthly
            ? null
            : (clone $query)
                ->with('user')
                ->latest()
                ->paginate(15)
                ->withQueryString();

        $peak = $series->sortByDesc('total')->first();

        $cashiers = auth()->user()->role === 'kasir'
            ? collect()
            : User::query()
                ->whereIn('role', ['kasir', 'admin'])
                ->orderBy('name')
                ->get(['id', 'name', 'role']);

        $statusLabels = self::STATUS_OPTIONS;
        $paymentLabels = self::PAYMENT_OPTIONS;
        $isRevenueReport = $filters['status'] === 'success';

        return view('laporan.report', compact(
            'start',
            'monthly',
            'total',
            'count',
            'previousTotal',
            'growth',
            'series',
            'payments',
            'topMenus',
            'transactions',
            'peak',
            'filters',
            'cashiers',
            'statusLabels',
            'paymentLabels',
            'isRevenueReport'
        ));
    }

    private function commonValidationRules(): array
    {
        return [
            'kasir_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => [
                'nullable',
                'in:success,pending,cancelled,expired,all',
            ],
            'metode' => ['nullable', 'in:cash,qris'],
        ];
    }

    private function normalizeFilters(
        Request $request,
        array $validated
    ): array {
        $user = $request->user();

        return [
            'kasir_id' => $user->role === 'kasir'
                ? (int) $user->id
                : (isset($validated['kasir_id'])
                    ? (int) $validated['kasir_id']
                    : null),
            'status' => $validated['status'] ?? 'success',
            'metode' => $validated['metode'] ?? null,
        ];
    }

    private function scopedQuery(
        Request $request,
        array $filters
    ): Builder {
        $user = $request->user();
        $query = Transaction::query();

        if ($user->role === 'kasir') {
            $query->where('user_id', $user->id);
        } elseif ($filters['kasir_id']) {
            $query->where('user_id', $filters['kasir_id']);
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['metode']) {
            $query->where('payment_method', $filters['metode']);
        }

        return $query;
    }
}
