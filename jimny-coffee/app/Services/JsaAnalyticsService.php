<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class JsaAnalyticsService
{
    /*
     * Riwayat tetap yang dipakai untuk evaluasi dan prediksi.
     *
     * Filter 7/14/30 hari hanya mengatur tampilan (ringkasan, grafik,
     * jam ramai). Prediksi selalu memakai riwayat 30 hari penuh agar
     * filter pendek tidak membuat prediksi mustahil dihasilkan.
     */
    private const FORECAST_HISTORY_DAYS = 30;

    public function analyze(int $days = 30, ?int $menuId = null): array
    {
        $days = in_array($days, [7, 14, 30], true)
            ? $days
            : 30;

        /*
         * Hari ini belum selesai, sehingga tidak dihitung sebagai hari
         * penuh. Jendela data berakhir pada akhir hari kemarin.
         */
        $end = Carbon::yesterday()->endOfDay();
        $start = Carbon::yesterday()
            ->subDays($days - 1)
            ->startOfDay();

        /*
         * SUMMARY
         *
         * Basis data penjualan:
         * - transaksi sukses
         * - pembayaran sudah paid
         * - target analitik berupa jumlah porsi / qty
         */
        $salesBase = $this->salesBase($start, $end, $menuId);

        $transactions = (clone $salesBase)
            ->distinct()
            ->count('t.id');

        $soldQty = (int) (
            (clone $salesBase)->sum('d.qty') ?? 0
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
        [$salesTrend, $activeDays] = $this->dailyTrend(
            $start,
            $days,
            $menuId
        );

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
         * FORECAST
         *
         * Memakai riwayat tetap 30 hari penuh (tanpa hari ini),
         * terlepas dari filter tampilan 7/14/30 hari.
         */
        if ($days === self::FORECAST_HISTORY_DAYS) {
            $forecastTrend = $salesTrend;
            $forecastActiveDays = $activeDays;
        } else {
            [$forecastTrend, $forecastActiveDays] = $this->dailyTrend(
                Carbon::yesterday()
                    ->subDays(self::FORECAST_HISTORY_DAYS - 1)
                    ->startOfDay(),
                self::FORECAST_HISTORY_DAYS,
                $menuId
            );
        }

        $forecastAnalysis = $this->evaluateWma(
            $forecastTrend,
            $forecastActiveDays
        );

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

            'forecast' => $forecastAnalysis['forecast'],

            'stock_recommendations' =>
                $stockRecommendations,

            'peak_hours' => $peakHours,

            'model_evaluation' => $forecastAnalysis['model_evaluation'],

            'data_notes' => [
                'transaction_filter' =>
                    'status=success, payment_status=paid',

                'prediction_target' =>
                    'transaction_details.qty',

                'today_excluded' =>
                    'Hari ini belum selesai, jadi tidak dihitung. Data berakhir kemarin.',

                'forecast_history_days' =>
                    self::FORECAST_HISTORY_DAYS,

                'forecast_window_note' =>
                    'Prediksi memakai 30 hari penuh terakhir, terlepas dari filter 7/14/30 hari.',

                'zero_sales_note' =>
                    'Hari tanpa transaksi diisi 0 untuk visualisasi; status kedai tutup belum tersedia.',
            ],
        ];
    }

    /**
     * Query dasar penjualan: transaksi sukses + paid, digabung dengan detail.
     */
    private function salesBase(
        Carbon $start,
        Carbon $end,
        ?int $menuId
    ): Builder {
        $query = DB::table('transactions as t')
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
            $query->where('d.menu_id', $menuId);
        }

        return $query;
    }

    /**
     * Deret porsi harian sepanjang $days hari mulai dari $start.
     * Hari tanpa transaksi diisi 0.
     *
     * @return array{0: array<int, array{date: string, qty: int}>, 1: int}
     *         [deret harian, jumlah hari yang punya penjualan]
     */
    private function dailyTrend(
        Carbon $start,
        int $days,
        ?int $menuId
    ): array {
        $end = $start
            ->copy()
            ->addDays($days - 1)
            ->endOfDay();

        $rows = $this->salesBase($start, $end, $menuId)
            ->selectRaw(
                'DATE(t.created_at) AS sale_date,
                 SUM(d.qty) AS qty'
            )
            ->groupByRaw('DATE(t.created_at)')
            ->orderByRaw('DATE(t.created_at)')
            ->get();

        $dailyMap = [];

        foreach ($rows as $row) {
            $dailyMap[$row->sale_date] = (int) $row->qty;
        }

        $trend = [];
        $activeDays = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = $start
                ->copy()
                ->addDays($i)
                ->toDateString();

            $qty = $dailyMap[$date] ?? 0;

            if ($qty > 0) {
                $activeDays++;
            }

            $trend[] = [
                'date' => $date,
                'qty' => $qty,
            ];
        }

        return [$trend, $activeDays];
    }

    private function evaluateWma(array $trend, int $activeDays): array
    {
        $minimumActiveDays = 14;
        $minimumCalendarDays = 21;
        $observations = count($trend);

        $notReady = [
            'forecast' => [
                'status' => 'insufficient_data',
                'method' => null,
                'values' => [],
                'backtest' => [],
                'observed_active_days' => $activeDays,
                'minimum_active_days' => $minimumActiveDays,
                'minimum_calendar_days' => $minimumCalendarDays,
                'message' => 'Histori belum memadai untuk mengevaluasi WMA.',
            ],
            'model_evaluation' => null,
        ];

        if (
            $activeDays < $minimumActiveDays ||
            $observations < $minimumCalendarDays
        ) {
            return $notReady;
        }

        $quantities = array_map(
            static fn (array $day): int =>
                max(0, (int) ($day['qty'] ?? 0)),
            $trend
        );

        $weights = [1, 2, 3, 4, 5, 6, 7];

        $estimate = static function (array $history) use ($weights): float {
            $lastSeven = array_slice($history, -7);
            $weightedSum = 0;

            foreach ($lastSeven as $index => $quantity) {
                $weightedSum += $quantity * $weights[$index];
            }

            return $weightedSum / array_sum($weights);
        };

        $backtest = [];
        $sumWmaAbsoluteError = 0.0;
        $sumNaiveAbsoluteError = 0.0;
        $sumSeasonalAbsoluteError = 0.0;
        $holdoutDays = 14;

        // Evaluasi bergulir: prediksi setiap hari hanya
        // menggunakan data dari hari-hari sebelumnya.
        for (
            $index = $observations - $holdoutDays;
            $index < $observations;
            $index++
        ) {
            $history = array_slice($quantities, 0, $index);

            $prediction = $estimate($history);
            $naivePrediction = (float) $quantities[$index - 1];

            // Seasonal-naive: hari yang sama minggu lalu (lag-7).
            $seasonalPrediction = (float) $quantities[$index - 7];
            $actual = $quantities[$index];

            $backtest[] = [
                'date' => $trend[$index]['date'],
                'actual_qty' => $actual,
                'predicted_qty' => round($prediction, 2),
                'naive_qty' => (int) $naivePrediction,
                'seasonal_naive_qty' => (int) $seasonalPrediction,
            ];

            $sumWmaAbsoluteError += abs($actual - $prediction);
            $sumNaiveAbsoluteError += abs($actual - $naivePrediction);
            $sumSeasonalAbsoluteError += abs($actual - $seasonalPrediction);
        }

        $nextDayPrediction = $estimate($quantities);

        $wmaMae = round(
            $sumWmaAbsoluteError / $holdoutDays,
            2
        );

        $naiveMae = round(
            $sumNaiveAbsoluteError / $holdoutDays,
            2
        );

        $seasonalMae = round(
            $sumSeasonalAbsoluteError / $holdoutDays,
            2
        );

        // Metode dengan MAE terendah. Jika seri, urutan WMA lalu
        // seasonal-naive lalu naive (metode yang dipakai tampil lebih dulu).
        $maes = [
            'wma' => $wmaMae,
            'seasonal_naive' => $seasonalMae,
            'naive' => $naiveMae,
        ];

        $lowestMae = min($maes);
        $bestByMae = 'wma';

        foreach ($maes as $method => $mae) {
            if ($mae === $lowestMae) {
                $bestByMae = $method;
                break;
            }
        }

        $message = 'WMA-7 eksperimental. Belum untuk restok otomatis.';

        if ($bestByMae !== 'wma') {
            $message .= ' Pada backtest, metode lain memiliki MAE lebih rendah.';
        }

        return [
            'forecast' => [
                'status' => 'experimental',
                'method' => 'WMA-7',
                'values' => [
                    [
                        'date' => Carbon::parse(
                            $trend[array_key_last($trend)]['date']
                        )->addDay()->toDateString(),
                        'qty' => max(0, (int) round($nextDayPrediction)),
                        'raw_qty' => round($nextDayPrediction, 2),
                    ],
                ],
                'backtest' => $backtest,
                'observed_active_days' => $activeDays,
                'minimum_active_days' => $minimumActiveDays,
                'minimum_calendar_days' => $minimumCalendarDays,
                'message' => $message,
            ],
            'model_evaluation' => [
                'method' => 'rolling_one_step_backtest',
                'days' => $holdoutDays,
                'wma_mae' => $wmaMae,
                'naive_mae' => $naiveMae,
                'seasonal_naive_mae' => $seasonalMae,
                'wma_better_than_naive' => $wmaMae < $naiveMae,
                'wma_better_than_seasonal_naive' => $wmaMae < $seasonalMae,
                'best_by_mae' => $bestByMae,
                'note' => 'Backtesting 14 hari terakhir (tanpa hari ini). Hari tutup belum dibedakan dari hari tanpa transaksi. Hanya 14 titik uji, jadi perbandingan ini masih kasar.',
            ],
        ];
    }
}