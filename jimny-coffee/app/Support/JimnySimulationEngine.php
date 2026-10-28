<?php
declare(strict_types=1);

namespace App\Support;

/**
 * SIMULASI SAJA. Generator deterministik tanpa DB, tanpa transaksi/stock real.
 * Backtest menggunakan holdout kronologis; annual penuh butuh > 24 bulan.
 */
final class JimnySimulationEngine
{
    public const HORIZONS = [1 => 14, 7 => 21, 30 => 60, 90 => 180, 365 => 540];
    private int $rng = 19072026;

    private function random(): float
    {
        $this->rng = (int) (($this->rng * 1103515245 + 12345) % 2147483647);
        return $this->rng / 2147483647;
    }

    public static function catalog(): array
    {
        $names = [
            'Tubruk','Espresso','Lungo','Ristretto','Americano','Limericano','Long Black',
            'Americano Premium Beans','Magic Latte','Dirty Latte','Cafe Latte','Cappuccino',
            'Aren Latte','Spanish Latte','Hazelnut Latte','Caramel Latte','Vanilla Latte',
            'Sea Salt Latte','Butterscotch Latte','Caramel Macchiato',
            'Butterscotch Caramel','Offroad Creamy Latte','Matcha','Signature Chocolate',
            'Taro Latte','Red Velvet Latte','Signature Tea','Tea Telang','Vanilla Tea',
            'Lychee Tea','Lemon Tea','Milk Tea','Nasi Ayam Betutu','Indomie',
            'Indomie Telur','Roti','Dimsum','Roti Bakar','Kentang Goreng','Nugget',
            'Affogato','Extra Ice Cream'
        ];
        $items = [];
        foreach ($names as $i => $name) {
            $items[] = [
                'id' => $i + 1,
                'name' => $name,
                'price_rp' => 10000 + ($i % 8) * 2000,
                'minimum_portions' => 10,
                'weight' => round(max(0.6, 7 - ($i % 11) * 0.43), 2),
            ];
        }
        return $items;
    }

    public function build(?\DateTimeImmutable $asOf = null): array
    {
        $this->rng = 19072026;
        $today = ($asOf ?? new \DateTimeImmutable('today'))->setTime(0,0);
        $first = $today->modify('-2 years');
        $end = $today->modify('-1 day');
        $catalog = self::catalog();
        $daily = [];
        $monthlyMap = [];
        $totalsPerMenu = array_fill(0, count($catalog), 0);
        $date = $first;
        $dayIndex = 0;
        $salesTotal = 0;
        $revenueTotal = 0;
        $twoPi = 2 * M_PI;
        while ($date <= $end) {
            $dateKey = $date->format('Y-m-d');
            $monthKey = $date->format('Y-m');
            $weekday = (int) $date->format('N');
            $dayYear = (int) $date->format('z');
            $growth = 1 + 0.16 * $dayIndex / 730;
            $weekend = $weekday >= 6 ? 1.26 : ($weekday === 5 ? 1.12 : 0.92);
            $seasonal = 1 + 0.16 * sin($twoPi * ($dayYear - 40) / 365.25);
            $total = 0;
            $revenue = 0;
            $quantities = [];
            foreach ($catalog as $idx => $menu) {
                $mean = $menu['weight'] * 0.52 * $growth * $weekend * $seasonal;
                $mean *= (0.77 + 0.46 * $this->random());
                $qty = max(0, (int) floor($mean + $this->random() - 0.10));
                // Promo fiktif pendek hanya untuk menguji deteksi pola.
                if ($idx < 4 && ($dayIndex % 68) >= 50 && ($dayIndex % 68) <= 55) {
                    $qty += (int) floor(2 * $this->random());
                }
                $quantities[] = $qty;
                $total += $qty;
                $revenue += $qty * $menu['price_rp'];
                $totalsPerMenu[$idx] += $qty;
            }
            $daily[] = [
                'date' => $dateKey, 'portions' => $total, 'revenue_rp' => $revenue,
                'qty' => $quantities
            ];
            if (!isset($monthlyMap[$monthKey])) {
                $monthlyMap[$monthKey] = ['month' => $monthKey, 'portions' => 0, 'revenue_rp' => 0, 'days' => 0];
            }
            $monthlyMap[$monthKey]['portions'] += $total;
            $monthlyMap[$monthKey]['revenue_rp'] += $revenue;
            $monthlyMap[$monthKey]['days']++;
            $salesTotal += $total;
            $revenueTotal += $revenue;
            $dayIndex++;
            $date = $date->modify('+1 day');
        }
        $forecast = [];
        foreach (self::HORIZONS as $h => $min) {
            $forecast[(string) $h] = $this->forecast($daily, $h, $min);
        }
        // Omzet forecast uses a simulated, constant historical revenue per portion.
        // This is an explicit assumption, not a learned price/customer model.
        $avgRevenuePerPortion = $salesTotal > 0 ? $revenueTotal / $salesTotal : 0;
        foreach ($forecast as &$f) {
            $f['revenue_method'] = 'simulated_constant_revenue_per_portion';
            $f['revenue_point_rp'] = $f['available'] ? (int) round($f['point'] * $avgRevenuePerPortion) : null;
            $f['revenue_lower_rp'] = $f['available'] ? (int) round($f['lower'] * $avgRevenuePerPortion) : null;
            $f['revenue_upper_rp'] = $f['available'] ? (int) round($f['upper'] * $avgRevenuePerPortion) : null;
        }
        unset($f);
        $lastDays = array_slice($daily, -7);
        $restock = [];
        foreach ($catalog as $idx => $menu) {
            $demand7 = 0;
            foreach ($lastDays as $day) $demand7 += $day['qty'][$idx];
            $perDay = $demand7 / 7;
            $syntheticStock = ($idx % 6 === 0)
                ? max(1, (int) round($perDay * 1.7))
                : max(8, (int) round($perDay * 5 + 10 + ($idx % 4)));
            $need = max(0, (int) ceil($perDay * 5) + $menu['minimum_portions'] - $syntheticStock);
            $restock[] = [
                'menu' => $menu['name'],
                'stock' => $syntheticStock,
                'minimum' => $menu['minimum_portions'],
                'avg_daily_portions' => round($perDay, 2),
                'suggested_portions' => $need,
                'review_required' => true,
                'source' => 'synthetic_inventory',
            ];
        }
        usort($restock, static fn($a,$b) => $b['suggested_portions'] <=> $a['suggested_portions']);
        return [
            'schema' => 'jimny-simulation-lab-v19/1',
            'mode' => 'SIMULATION_ONLY',
            'synthetic' => true,
            'warnings' => [
                'SELURUH angka ini adalah data sintetis 24 bulan; bukan omzet, transaksi, atau stok POS nyata.',
                'Forecast merupakan simulasi eksperimental, tidak boleh digunakan untuk membeli stok sungguhan.',
                'Backtest 365 hari penuh belum tersedia dengan hanya 24 bulan jika minimal training 540 hari.',
                'Data sintetis tidak membuktikan akurasi prediksi pada penjualan sebenarnya.',
            ],
            'generated_at' => $today->format(DATE_ATOM),
            'history_from' => $first->format('Y-m-d'),
            'history_through' => $end->format('Y-m-d'),
            'history_days' => count($daily),
            'forecast_unit' => 'menu_portions',
            'catalog' => $catalog,
            'summary' => [
                'menu_count' => count($catalog),
                'total_portions' => $salesTotal,
                'total_revenue_rp' => $revenueTotal,
                'average_daily_portions' => round($salesTotal/max(1,count($daily)), 1),
            ],
            'monthly' => array_values($monthlyMap),
            'forecasts' => $forecast,
            'restock' => $restock,
            'daily' => $daily,
        ];
    }

    /** Projection from training prefix ONLY; never access future observations. */
    private function project(array $history, int $horizon): float
    {
        $n = count($history);
        if (!$n) return 0.0;
        $lastDate = new \DateTimeImmutable($history[$n-1]['date']);
        $byDate = [];
        foreach ($history as $d) $byDate[$d['date']] = (float)$d['portions'];
        $weekly = [];
        foreach (array_slice($history, -28) as $d) {
            $dow = (new \DateTimeImmutable($d['date']))->format('N');
            $weekly[$dow][] = (float) $d['portions'];
        }
        $recent90 = array_slice($history,-90);
        $previous90 = array_slice($history,-180,90);
        $current = array_sum(array_column($recent90, 'portions'))/max(1,count($recent90));
        $previous = array_sum(array_column($previous90, 'portions'))/max(1,count($previous90));
        $trend = $previous > 1 ? max(0.85,min(1.15,$current/$previous)) : 1.0;
        $overall = array_sum(array_column(array_slice($history,-35),'portions'))/min(35,$n);
        $total = 0.0;
        for ($offset=1; $offset<=$horizon; $offset++) {
            $future = $lastDate->modify('+' . $offset . ' days');
            $priorYear = $future->modify('-1 year')->format('Y-m-d');
            $dow = $future->format('N');
            $week = isset($weekly[$dow]) ? array_sum($weekly[$dow])/count($weekly[$dow]) : $overall;
            $season = $byDate[$priorYear] ?? $week;
            $base = $n >= 365 ? (0.65*$season + 0.35*$week) : $week;
            $factor = 1 + ($trend-1) * min(1.0, $offset / 365.0);
            $total += max(0, $base * $factor);
        }
        return $total;
    }

    private function forecast(array $daily, int $horizon, int $min): array
    {
        $n = count($daily);
        $eligible = $n >= $min;
        $result = [
            'horizon_days' => $horizon,
            'available' => $eligible,
            'status' => $eligible ? 'experimental_review_required' : 'insufficient_history',
            'min_history_days' => $min,
            'history_days' => $n,
            'point' => null,'lower' => null,'upper' => null,
            'backtest' => ['status'=>'not_evaluated','tests'=>0,'mae_total_portions'=>null],
            'method' => 'annual_seasonal_weekday_blend_with_damped_trend',
        ];
        if (!$eligible) return $result;
        $value = $this->project($daily,$horizon);
        $backtest = $this->backtest($daily,$horizon,$min);
        $mae = $backtest['mae_total_portions'] ?? null;
        $margin = max($value * ($horizon >= 365 ? 0.40 : ($horizon >= 90 ? 0.30 : 0.20)),
            $mae !== null ? $mae * 1.6 : 0);
        $result['point'] = round($value,1);
        $result['lower'] = round(max(0,$value-$margin),1);
        $result['upper'] = round($value+$margin,1);
        $result['backtest'] = $backtest;
        return $result;
    }

    private function backtest(array $daily,int $horizon,int $min): array
    {
        $n = count($daily);
        $possible = (int) floor(($n-$min)/$horizon);
        if ($possible < 1) {
            return [
                'status'=>'insufficient_full_horizon_holdout',
                'tests'=>0, 'mae_total_portions'=>null,
                'note'=>'Belum ada jendela holdout lengkap tanpa melanggar minimum training.'
            ];
        }
        $tests = min($possible, $horizon === 1 ? 14 : ($horizon === 7 ? 8 : ($horizon === 30 ? 4 : 2)));
        $errors = [];
        for ($j=$tests; $j>=1; $j--) {
            $cut=$n-$horizon*$j;
            if ($cut < $min) continue;
            $train=array_slice($daily,0,$cut);
            $actual=array_sum(array_column(array_slice($daily,$cut,$horizon),'portions'));
            $pred=$this->project($train,$horizon);
            $errors[]=abs($actual-$pred);
        }
        return [
            'status'=>count($errors) ? 'rolling_origin_holdout' : 'insufficient_full_horizon_holdout',
            'tests'=>count($errors),
            'mae_total_portions'=>count($errors) ? round(array_sum($errors)/count($errors),1) : null,
            'note'=>'MAE berbasis total porsi pada horizon ini, bukan MAE per hari.'
        ];
    }
}
