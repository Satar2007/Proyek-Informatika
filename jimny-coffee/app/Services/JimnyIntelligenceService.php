<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Menu;

/**
 * V16-18: business intelligence from POS data, READ-ONLY.
 * Never touches orders, stock levels, or payments.
 * Forecasts are experimental statistical estimates, not certified ML results.
 */
class JimnyIntelligenceService
{
    public const HORIZONS = [1, 7, 30, 90, 365];
    private const MAX_HISTORY = 730;

    public function snapshot(?int $cashierUserId = null): array
    {
        $today = Carbon::today();
        $end = $today->copy()->subDay()->endOfDay();
        $start = $today->copy()->subDays(self::MAX_HISTORY)->startOfDay();

        // Filter both success AND paid, consistent with JSA analytics.
        $query = DB::table('transactions as t')
            ->join('transaction_details as d', 'd.transaction_id', '=', 't.id')
            ->where('t.status', 'success')
            ->where('t.payment_status', 'paid')
            ->whereBetween('t.created_at', [$start, $end]);
        if ($cashierUserId !== null) {
            $query->where('t.user_id', $cashierUserId);
        }
        $rows = $query->selectRaw('DATE(t.created_at) as sale_day, d.menu_id, SUM(d.qty) as portions')
            ->groupByRaw('DATE(t.created_at), d.menu_id')
            ->orderBy('sale_day')->get();

        $byDay = [];
        $byMenu = [];
        foreach ($rows as $row) {
            $date = (string) $row->sale_day;
            $qty = max(0, (int) $row->portions);
            $byDay[$date] = ($byDay[$date] ?? 0) + $qty;
            $id = (int) $row->menu_id;
            $byMenu[$id][$date] = ($byMenu[$id][$date] ?? 0) + $qty;
        }
        $dates = array_keys($byDay);
        sort($dates);
        $historyDays = count($dates) ? abs((float) $today->copy()->subDay()->diffInDays(Carbon::parse($dates[0]))) + 1 : 0;
        $historyDays = (int) min(self::MAX_HISTORY, max(0, $historyDays));
        $daily = $this->fillCalendar($byDay, $historyDays, $today);
        $activeDays = count($dates);
        $forecasts = [];
        foreach (self::HORIZONS as $horizon) {
            $forecasts[(string) $horizon] = $this->forecast($daily, $horizon, $activeDays);
        }

        // Restock guidance is MENU-LEVEL portions, not ingredient/BOM quantities.
        $menus = Menu::query()->where('is_active', true)
            ->orderBy('nama_menu')->get(['id', 'nama_menu', 'stok', 'minimum_stok']);
        $restock = [];
        foreach ($menus as $menu) {
            $id = (int) $menu->id;
            $stock = (int) $menu->stok;
            $min = (int) $menu->minimum_stok;
            $series = $this->fillCalendar($byMenu[$id] ?? [], $historyDays, $today);
            $short = $this->forecast($series, 7, count($byMenu[$id] ?? []));
            // Lead time: 3 days + safety 2 days, configurable after validation.
            $dailyDemand = $short['available'] ? $short['point'] / 7 : null;
            $target = $dailyDemand === null ? $min : (int) ceil(5 * $dailyDemand) + $min;
            $recommendation = max(0, $target - $stock);
            $restock[] = [
                'menu_id' => $id,
                'menu' => (string) $menu->nama_menu,
                'stock' => $stock,
                'minimum' => $min,
                'suggested_portions' => $recommendation,
                'basis' => $dailyDemand === null ? 'minimum_stock_only' : '5_day_coverage_plus_minimum',
                'review_required' => true,
                'forecast_quality' => $short['status'],
            ];
        }
        usort($restock, static fn($a, $b) => $b['suggested_portions'] <=> $a['suggested_portions']);

        return [
            'generated_at' => now()->toIso8601String(),
            'data_through' => $end->toDateString(),
            'history_days' => $historyDays,
            'active_sales_days' => $activeDays,
            'scope' => $cashierUserId === null ? 'business' : 'own_cashier_transactions',
            'forecast_unit' => 'menu_portions',
            'forecasts' => $forecasts,
            'restock' => array_slice($restock, 0, 12),
            'warnings' => [
                'Hari tutup belum ditandai; hari tanpa transaksi dianggap nol.',
                'Forecast adalah estimasi statistik dan butuh evaluasi historis; bukan kepastian.',
                'Saran restock adalah porsi/menu, bukan jumlah bahan baku; tidak mengubah stok.',
            ],
        ];
    }

    private function fillCalendar(array $map, int $days, Carbon $today): array
    {
        if ($days < 1) return [];
        $series = [];
        for ($i = $days; $i >= 1; $i--) {
            $date = $today->copy()->subDays($i)->toDateString();
            $series[] = (float) ($map[$date] ?? 0);
        }
        return $series;
    }

    private function predictOne(array $series, int $offset): float
    {
        $n = count($series);
        if ($n === 0) return 0.0;
        $recent = array_slice($series, -min(28, $n));
        $avg = array_sum($recent) / count($recent);
        // Day-of-week seasonal mean: last 4 observations for matching weekday.
        $seasonal = [];
        for ($i = $n - 1; $i >= 0 && count($seasonal) < 4; $i--) {
            if ((($n - 1 + $offset - $i) % 7) === 0) $seasonal[] = $series[$i];
        }
        $dayMean = $seasonal ? array_sum($seasonal) / count($seasonal) : $avg;
        // Damped trend, never extrapolate a short-term slope indefinitely.
        $half = (int) floor(count($recent)/2);
        $old = array_slice($recent, 0, $half);
        $new = array_slice($recent, $half);
        $slope = $old && $new ? ((array_sum($new)/count($new)) - (array_sum($old)/count($old))) / max(1, $half) : 0.0;
        $boundedSlope = max(-0.15*$avg, min(0.15*$avg, $slope));
        $damp = exp(-$offset/14);
        return max(0.0, (0.60*$dayMean + 0.40*$avg) + $boundedSlope * $damp);
    }

    private function forecast(array $series, int $horizon, int $activeDays): array
    {
        $n = count($series);
        $required = match($horizon) { 1 => 14, 7 => 21, 30 => 60, 90 => 180, 365 => 540, default => 9999 };
        $eligible = $n >= $required && $activeDays >= min(10, (int) ceil($required / 10));
        $result = [
            'horizon_days' => $horizon,
            'available' => $eligible,
            'status' => $eligible ? 'experimental_review_required' : 'insufficient_history',
            'history_days' => $n,
            'min_history_days' => $required,
            'point' => null,
            'lower' => null,
            'upper' => null,
            'backtest_mae' => null,
            'method' => 'weekday_weighted_damped_trend',
        ];
        if (!$eligible) return $result;
        $sum = 0.0;
        for ($day=1; $day<=$horizon; $day++) $sum += $this->predictOne($series, $day);

        // Holdout walk-forward: train only on days BEFORE the evaluated day.
        $backtestDays = min(14, $n - 14);
        $errors = [];
        for ($idx = $n - $backtestDays; $idx < $n; $idx++) {
            $training = array_slice($series, 0, $idx);
            $errors[] = abs($series[$idx] - $this->predictOne($training, 1));
        }
        $mae = count($errors) ? array_sum($errors)/count($errors) : 0.0;
        $margin = max($sum * 0.18, $mae * sqrt($horizon) * 1.65);
        // Long horizon bounds intentionally much wider to reflect uncertainty.
        $margin *= 1 + max(0, $horizon - 14) / 120;
        $result['point'] = round($sum, 1);
        $result['lower'] = round(max(0.0, $sum - $margin), 1);
        $result['upper'] = round($sum + $margin, 1);
        $result['backtest_mae'] = round($mae, 2);
        return $result;
    }

    private function salesSummary(?int $cashierUserId): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth()->subMonth();
        $prevStart = $monthStart->copy()->subMonth();
        $end = $today->copy()->subDay()->endOfDay();
        $query = DB::table('transactions as t')
            ->where('t.status', 'success')->where('t.payment_status', 'paid');
        if ($cashierUserId !== null) $query->where('t.user_id', $cashierUserId);
        $periods = [
            'last_7_days' => [$today->copy()->subDays(7)->startOfDay(), $end],
            'last_30_days' => [$today->copy()->subDays(30)->startOfDay(), $end],
            'last_completed_month' => [$monthStart, $today->copy()->startOfMonth()->subSecond()],
            'month_before_last' => [$prevStart, $monthStart->copy()->subSecond()],
        ];
        $result = [];
        foreach ($periods as $label => [$a, $b]) {
            $row = (clone $query)->whereBetween('t.created_at', [$a, $b])
                ->selectRaw('COUNT(*) as transactions, COALESCE(SUM(t.grand_total),0) as revenue_rp')
                ->first();
            $result[$label] = [
                'from' => $a->toDateString(),
                'to' => $b->toDateString(),
                'transactions' => (int) ($row->transactions ?? 0),
                'revenue_rp' => (int) ($row->revenue_rp ?? 0),
            ];
        }
        $top = DB::table('transactions as t')
            ->join('transaction_details as d', 'd.transaction_id', '=', 't.id')
            ->join('menus as m', 'm.id', '=', 'd.menu_id')
            ->where('t.status', 'success')->where('t.payment_status', 'paid')
            ->whereBetween('t.created_at', [$today->copy()->subDays(30)->startOfDay(), $end]);
        if ($cashierUserId !== null) $top->where('t.user_id', $cashierUserId);
        $result['top_menus_30_days'] = $top
            ->selectRaw('m.nama_menu as name, SUM(d.qty) as qty')
            ->groupBy('m.id', 'm.nama_menu')
            ->orderByDesc('qty')->limit(5)->get()
            ->map(fn ($r) => ['name' => (string) $r->name, 'qty' => (int) $r->qty])->all();
        return $result;
    }

    /** Supply only relevant facts for grounded local AI; no arbitrary SQL from model. */
    public function contextForQuestion(string $role, int $userId, string $question): array
    {
        $topic = mb_strtolower($question);
        $scope = $role === 'kasir' ? $userId : null;
        $snap = $this->snapshot($scope);
        $data = [
            'data_through' => $snap['data_through'],
            'scope' => $snap['scope'],
            'history_days' => $snap['history_days'],
            'source' => 'paid_successful_pos_transactions',
        ];
        if (preg_match('/omzet|revenue|pendapatan|penjualan|terlaris|laku|menu|bulan|tahun|analisis|bisnis/u', $topic)) {
            $data['business_summary'] = $this->salesSummary($scope);
        }
        if (preg_match('/prediksi|forecast|ramal|besok|minggu|bulan|tahunan|tahun|triwulan|ke depan|penjualan/u', $topic)) {
            $data['forecast'] = $snap['forecasts'];
            $data['forecast_warning'] = 'status insufficient_history berarti jangan sebut angka atau tanggal hasil prediksi untuk horizon itu.';
        }
        if (preg_match('/stok|restok|restock|persediaan|habis|beli|pengadaan/u', $topic)) {
            $data['stock_advice'] = array_slice($snap['restock'], 0, 6);
            $data['stock_warning'] = 'Rekomendasi hanya estimasi porsi/menu, bukan pembelian otomatis.';
        }
        return $data;
    }
}
