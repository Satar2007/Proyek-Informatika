<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class JsaAnalyticsService
{
    public function analyze(int $days = 30, ?int $menuId = null): array
    {
        $days = in_array($days, [7, 14, 30], true)
            ? $days
            : 30;

        $end = Carbon::now()->endOfDay();
        $start = Carbon::today()->subDays($days - 1)->startOfDay();

        /*
         * Basis data penjualan:
         * - transaksi sukses
         * - pembayaran sudah paid
         * - target analitik berupa jumlah porsi / qty
         */
        $salesBase = DB::table('transactions as t')
            ->join(
                'transaction_details as d',
                't.id',
                '=',
                'd.transaction_id'
            )
            ->where('t.status', 'success')
            ->where('t.payment_status', 'paid')
            ->whereBetween('t.created_at', [$start, $end]);

        if ($menuId !== null) {
            $salesBase->where('d.menu_id', $menuId);
        }

        /*
         * SUMMARY
         */
        $transactions = (clone $salesBase)
            ->distinct()
            ->count('t.id');

        $soldQty = (int) (
            (clone $salesBase)->sum('d.qty') ?? 0
        );

        $activeDayRow = (clone $salesBase)
            ->selectRaw(
                'COUNT(DISTINCT DATE(t.created_at)) AS active_days'
            )
            ->first();

        $activeDays = (int) (
            $activeDayRow->active_days ?? 0
        );

        /*
         * SALES TREND
         *
         * Hari tanpa transaksi ditampilkan sebagai 0 agar grafik
         * memiliki rangka waktu yang konsisten.
         *
         * Catatan:
         * angka 0 belum membedakan kedai tutup dengan kedai buka
         * tetapi tidak memiliki transaksi.
         */
        $dailyRows = (clone $salesBase)
            ->selectRaw(
                'DATE(t.created_at) AS sale_date,
                 SUM(d.qty) AS qty'
            )
            ->groupByRaw('DATE(t.created_at)')
            ->orderByRaw('DATE(t.created_at)')
            ->get();

        $dailyMap = [];

        foreach ($dailyRows as $row) {
            $dailyMap[$row->sale_date] = (int) $row->qty;
        }

        $salesTrend = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start
                ->copy()
                ->addDays($i)
                ->toDateString();

            $salesTrend[] = [
                'date' => $date,
                'qty' => $dailyMap[$date] ?? 0,
            ];
        }

        /*
         * PEAK HOURS
         *
         * Menghitung jumlah transaksi unik, bukan jumlah baris detail.
         */
        $hourQuery = DB::table('transactions as t')
            ->where('t.status', 'success')
            ->where('t.payment_status', 'paid')
            ->whereBetween('t.created_at', [$start, $end]);

        if ($menuId !== null) {
            $hourQuery
                ->join(
                    'transaction_details as d',
                    't.id',
                    '=',
                    'd.transaction_id'
                )
                ->where('d.menu_id', $menuId);
        }

        $hourRows = $hourQuery
            ->selectRaw(
                'HOUR(t.created_at) AS sale_hour,
                 COUNT(DISTINCT t.id) AS total_transactions'
            )
            ->groupByRaw('HOUR(t.created_at)')
            ->orderByRaw('HOUR(t.created_at)')
            ->get();

        $hourMap = [];

        foreach ($hourRows as $row) {
            $hourMap[(int) $row->sale_hour] =
                (int) $row->total_transactions;
        }

        $peakHours = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $peakHours[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'transactions' => $hourMap[$hour] ?? 0,
            ];
        }

        /*
         * STOCK MONITORING
         *
         * Tahap pertama belum mengklaim hasil ini sebagai prediksi.
         * Ini adalah alert berbasis stok minimum.
         */
        $stockQuery = Menu::query()
            ->where('is_active', true)
            ->whereColumn('stok', '<=', 'minimum_stok');

        if ($menuId !== null) {
            $stockQuery->where('id', $menuId);
        }

        $stockRecommendations = $stockQuery
            ->orderBy('stok')
            ->limit(10)
            ->get([
                'id',
                'nama_menu',
                'stok',
                'minimum_stok',
            ])
            ->map(function ($menu) {
                $stock = (int) $menu->stok;
                $minimum = (int) $menu->minimum_stok;

                return [
                    'menu_id' => (int) $menu->id,
                    'name' => $menu->nama_menu,
                    'stock' => $stock,
                    'minimum_stock' => $minimum,

                    /*
                     * Selisih menuju minimum.
                     * Jika stok tepat sama dengan minimum, nilainya 0
                     * namun status tetap low_stock.
                     */
                    'shortage_to_minimum' =>
                        max(0, $minimum - $stock),

                    'status' =>
                        $stock <= 0
                            ? 'critical'
                            : 'low_stock',

                    'basis' =>
                        'minimum_stock_threshold',
                ];
            })
            ->values()
            ->all();

        /*
         * DAFTAR MENU UNTUK FILTER FRONTEND RAFAEL
         */
        $menuOptions = Menu::query()
            ->where('is_active', true)
            ->orderBy('nama_menu')
            ->get([
                'id',
                'nama_menu',
            ])
            ->map(fn ($menu) => [
                'id' => (int) $menu->id,
                'name' => $menu->nama_menu,
            ])
            ->values()
            ->all();

        /*
         * FORECAST READINESS
         *
         * Belum menghasilkan prediksi palsu.
         *
         * Minimum 14 hari penjualan aktif digunakan sebagai gerbang
         * awal sebelum model forecasting dievaluasi.
         */
        $minimumActiveDays = 14;

        $forecastStatus =
            $activeDays < $minimumActiveDays
                ? 'insufficient_data'
                : 'model_not_trained';

        return [
            'summary' => [
                'transactions' => (int) $transactions,
                'sold_qty' => $soldQty,
                'active_days' => $activeDays,
            ],

            'period_days' => $days,

            'selected_menu_id' => $menuId,

            'menu_options' => $menuOptions,

            'sales_trend' => $salesTrend,

            'forecast' => [
                'status' => $forecastStatus,
                'method' => null,
                'values' => [],
                'observed_active_days' => $activeDays,
                'minimum_active_days' => $minimumActiveDays,
                'message' =>
                    $forecastStatus === 'insufficient_data'
                        ? 'Data penjualan aktif belum cukup untuk mengevaluasi model prediksi.'
                        : 'Data dasar tersedia, tetapi model prediksi belum dilatih dan dievaluasi.',
            ],

            'stock_recommendations' =>
                $stockRecommendations,

            'peak_hours' => $peakHours,

            'model_evaluation' => null,

            'data_notes' => [
                'transaction_filter' =>
                    'status=success, payment_status=paid',

                'prediction_target' =>
                    'transaction_details.qty',

                'zero_sales_note' =>
                    'Hari tanpa transaksi diisi 0 untuk visualisasi; status kedai tutup belum tersedia.',
            ],
        ];
    }
}