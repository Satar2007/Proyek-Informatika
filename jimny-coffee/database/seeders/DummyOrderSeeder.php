<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DATA DUMMY PESANAN (SINTETIS) - HANYA UNTUK PENGUJIAN / EKSPERIMEN ML.
 *
 * - Membuat 1000 pesanan berstatus success + paid.
 * - Tersebar 90 hari terakhir, berakhir KEMARIN (hari ini tidak diisi).
 * - Pola dibuat realistis: akhir pekan lebih ramai, jam makan siang
 *   dan sore/malam lebih ramai, ada tren naik ringan, menu tertentu
 *   lebih laris.
 * - Semua kode transaksi diawali "DMY-" supaya mudah dikenali dan dihapus
 *   lewat DummyOrderCleanupSeeder.
 * - Stok menu dan stock_logs TIDAK diubah.
 * - Hasilnya dapat diulang: seed yang sama menghasilkan data yang sama.
 *
 * Jalankan:  php artisan db:seed --class=DummyOrderSeeder
 */
class DummyOrderSeeder extends Seeder
{
    public const PREFIX = 'DMY-';

    private const TOTAL_ORDERS   = 1000;
    private const DAYS           = 90;
    private const MIN_PER_DAY    = 3;
    private const SEED           = 20261025;

    // Carbon dayOfWeek: 0 = Minggu ... 6 = Sabtu
    private const WEEKDAY_FACTOR = [
        0 => 1.45, // Minggu
        1 => 0.80, // Senin
        2 => 0.85, // Selasa
        3 => 0.90, // Rabu
        4 => 1.00, // Kamis
        5 => 1.30, // Jumat
        6 => 1.60, // Sabtu
    ];

    // Jam buka 08:00 - 21:59
    private const HOUR_WEIGHT = [
        8  => 0.6, 9  => 1.0, 10 => 1.2, 11 => 1.1, 12 => 1.8,
        13 => 1.9, 14 => 1.2, 15 => 1.0, 16 => 1.3, 17 => 1.6,
        18 => 1.8, 19 => 1.7, 20 => 1.3, 21 => 0.7,
    ];

    private const CATEGORY_WEIGHT = [
        'Coffee'           => 1.4,
        'Coffee Flavoured' => 1.2,
        'Milk Base'        => 1.0,
        'Non Coffee'       => 0.9,
        'Tea'              => 0.8,
        'Snack'            => 0.9,
        'Food'             => 0.8,
        'Ice Cream'        => 0.5,
    ];

    private const NAMES = [
        'Budi', 'Siti', 'Andi', 'Rina', 'Dewi', 'Agus', 'Putri', 'Rizky',
        'Nadia', 'Fajar', 'Maya', 'Dimas', 'Intan', 'Yoga', 'Laras',
        'Bagas', 'Salsa', 'Rama', 'Tika', 'Hendra',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Seeder data dummy tidak boleh dijalankan di production.');
        }

        $exists = DB::table('transactions')
            ->where('kode_transaksi', 'like', self::PREFIX . '%')
            ->exists();

        if ($exists) {
            throw new RuntimeException(
                'Data dummy sudah ada. Jalankan dulu: '
                . 'php artisan db:seed --class=DummyOrderCleanupSeeder'
            );
        }

        mt_srand(self::SEED);

        $menus = $this->loadMenus();
        $staff = $this->loadStaff();

        $dayPlan = $this->planOrdersPerDay();
        $orders  = $this->buildOrders($dayPlan, $menus, $staff);

        usort($orders, fn ($a, $b) => strcmp($a['created_at'], $b['created_at']));

        // Hanya isi kolom yang benar-benar ada di database kamu,
        // supaya tidak error kalau ada migration tambahan yang belum jalan.
        $trxColumns = array_flip(Schema::getColumnListing('transactions'));
        $detColumns = array_flip(Schema::getColumnListing('transaction_details'));
        $payColumns = array_flip(Schema::getColumnListing('payments'));

        DB::transaction(function () use ($orders, $trxColumns, $detColumns, $payColumns) {
            foreach ($orders as $order) {
                $transactionId = DB::table('transactions')->insertGetId(
                    array_intersect_key($order['transaction'], $trxColumns)
                );

                $details = [];
                foreach ($order['details'] as $detail) {
                    $details[] = array_intersect_key(
                        $detail + ['transaction_id' => $transactionId],
                        $detColumns
                    );
                }
                DB::table('transaction_details')->insert($details);

                DB::table('payments')->insert(
                    array_intersect_key(
                        $order['payment'] + ['transaction_id' => $transactionId],
                        $payColumns
                    )
                );
            }
        });

        $this->printSummary($orders);
    }

    // ------------------------------------------------------------
    // Data master
    // ------------------------------------------------------------

    private function loadMenus(): array
    {
        $rows = DB::table('menus as m')
            ->join('categories as c', 'c.id', '=', 'm.category_id')
            ->whereNull('m.deleted_at')
            ->where('m.is_active', true)
            ->orderBy('m.id')
            ->get(['m.id', 'm.nama_menu', 'm.harga', 'c.nama_kategori']);

        if ($rows->isEmpty()) {
            throw new RuntimeException('Tabel menus kosong. Jalankan dulu: php artisan db:seed');
        }

        $menus = [];
        foreach ($rows as $row) {
            $categoryWeight = self::CATEGORY_WEIGHT[$row->nama_kategori] ?? 1.0;
            $randomFactor   = 0.5 + $this->rand01();                       // 0.5 - 1.5
            $priceFactor    = 1 / pow(max($row->harga, 1000) / 15000, 0.3); // lebih murah -> lebih laris

            $menus[] = [
                'id'     => (int) $row->id,
                'name'   => $row->nama_menu,
                'harga'  => (int) $row->harga,
                'weight' => $categoryWeight * $randomFactor * $priceFactor,
            ];
        }

        return $menus;
    }

    private function loadStaff(): array
    {
        $rows = DB::table('users')
            ->whereIn('role', ['kasir', 'admin'])
            ->orderBy('id')
            ->get(['id', 'name', 'role']);

        if ($rows->isEmpty()) {
            throw new RuntimeException('Tidak ada user kasir/admin. Jalankan dulu: php artisan db:seed');
        }

        $staff = [];
        foreach ($rows as $row) {
            $staff[] = [
                'id'     => (int) $row->id,
                'name'   => $row->name,
                'role'   => $row->role,
                'weight' => $row->role === 'kasir' ? 3.0 : 1.0,
            ];
        }

        return $staff;
    }

    // ------------------------------------------------------------
    // Perencanaan jumlah pesanan per hari
    // ------------------------------------------------------------

    private function planOrdersPerDay(): array
    {
        $today = Carbon::today();
        $days  = [];

        for ($i = 0; $i < self::DAYS; $i++) {
            $date  = $today->copy()->subDays(self::DAYS - $i); // hari terakhir = kemarin
            $trend = 1 + 0.003 * $i;
            $noise = exp($this->gauss() * 0.25);

            $days[] = [
                'date'   => $date,
                'weight' => self::WEEKDAY_FACTOR[$date->dayOfWeek] * $trend * $noise,
                'count'  => self::MIN_PER_DAY,
            ];
        }

        $remaining = self::TOTAL_ORDERS - (self::MIN_PER_DAY * self::DAYS);
        $weights   = array_column($days, 'weight');

        for ($n = 0; $n < $remaining; $n++) {
            $days[$this->pickIndex($weights)]['count']++;
        }

        return $days;
    }

    // ------------------------------------------------------------
    // Membuat pesanan
    // ------------------------------------------------------------

    private function buildOrders(array $dayPlan, array $menus, array $staff): array
    {
        $menuWeights  = array_column($menus, 'weight');
        $staffWeights = array_column($staff, 'weight');
        $orders       = [];

        foreach ($dayPlan as $day) {
            /** @var Carbon $date */
            $date      = $day['date'];
            $isWeekend = in_array($date->dayOfWeek, [0, 5, 6], true);

            $hours   = array_keys(self::HOUR_WEIGHT);
            $hourW   = [];
            foreach ($hours as $hour) {
                $w = self::HOUR_WEIGHT[$hour];
                if ($isWeekend && $hour >= 17) {
                    $w *= 1.4;
                }
                if ($isWeekend && $hour <= 10) {
                    $w *= 0.8;
                }
                $hourW[] = $w;
            }

            for ($seq = 1; $seq <= $day['count']; $seq++) {
                $hour   = $hours[$this->pickIndex($hourW)];
                $created = $date->copy()->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));

                $items   = $this->buildItems($menus, $menuWeights);
                $total   = array_sum(array_column($items, 'subtotal'));
                $pajak   = (int) round($total * 0.03);
                $diskon  = $this->pickDiscount($total + $pajak);
                $grand   = $total + $pajak - $diskon;

                $isQris  = $this->rand01() < 0.55;
                $method  = $isQris ? 'qris' : 'cash';
                $paidAt  = $created->copy()->addSeconds($isQris ? mt_rand(30, 150) : mt_rand(5, 60));
                $cashier = $staff[$this->pickIndex($staffWeights)];

                $code = sprintf('%s%s-%03d', self::PREFIX, $date->format('Ymd'), $seq);

                $createdStr = $created->format('Y-m-d H:i:s');
                $paidStr    = $paidAt->format('Y-m-d H:i:s');

                $payment = [
                    'metode'              => $method,
                    'external_id'         => 'PAY-' . strtoupper((string) Str::uuid()),
                    'qris_string'         => $isQris ? 'QRIS-' . $code . '-' . $grand : null,
                    'midtrans_snap_token' => null,
                    'payment_status'      => 'paid',
                    'expired_at'          => $created->copy()->addMinutes(3)->format('Y-m-d H:i:s'),
                    'paid_at'             => $paidStr,
                    'uang_diterima'       => null,
                    'kembalian'           => null,
                    'created_at'          => $createdStr,
                    'updated_at'          => $paidStr,
                ];

                if (!$isQris) {
                    $received = $this->cashReceived($grand);
                    $payment['uang_diterima'] = $received;
                    $payment['kembalian']     = $received - $grand;
                }

                $orders[] = [
                    'created_at'  => $createdStr,
                    'method'      => $method,
                    'grand_total' => $grand,
                    'weekday'     => $date->dayOfWeek,
                    'date'        => $date->toDateString(),
                    'items'       => $items,
                    'transaction' => [
                        'kode_transaksi'           => $code,
                        'user_id'                  => $cashier['id'],
                        'cashier_name_snapshot'    => $cashier['name'],
                        'cashier_role_snapshot'    => $cashier['role'],
                        'nama_pelanggan'           => $this->rand01() < 0.7
                            ? 'Umum'
                            : self::NAMES[mt_rand(0, count(self::NAMES) - 1)],
                        'total'                    => $total,
                        'pajak'                    => $pajak,
                        'diskon'                   => $diskon,
                        'grand_total'              => $grand,
                        'status'                   => 'success',
                        'payment_status'           => 'paid',
                        'payment_method'           => $method,
                        'stock_reservation_status' => $isQris ? 'consumed' : 'none',
                        'paid_at'                  => $paidStr,
                        'created_at'               => $createdStr,
                        'updated_at'               => $paidStr,
                    ],
                    'details'     => array_map(fn ($item) => [
                        'menu_id'    => $item['menu_id'],
                        'qty'        => $item['qty'],
                        'harga'      => $item['harga'],
                        'subtotal'   => $item['subtotal'],
                        'created_at' => $createdStr,
                        'updated_at' => $createdStr,
                    ], $items),
                    'payment'     => $payment,
                ];
            }
        }

        return $orders;
    }

    private function buildItems(array $menus, array $menuWeights): array
    {
        $itemCount = [1, 2, 3, 4][$this->pickIndex([35, 35, 20, 10])];
        $itemCount = min($itemCount, count($menus));

        $weights = $menuWeights;
        $items   = [];

        for ($n = 0; $n < $itemCount; $n++) {
            $index = $this->pickIndex($weights);
            $weights[$index] = 0.0; // menu yang sama tidak diambil dua kali dalam satu pesanan

            $qty = [1, 2, 3][$this->pickIndex([70, 22, 8])];

            $items[] = [
                'menu_id'  => $menus[$index]['id'],
                'name'     => $menus[$index]['name'],
                'qty'      => $qty,
                'harga'    => $menus[$index]['harga'],
                'subtotal' => $menus[$index]['harga'] * $qty,
            ];
        }

        return $items;
    }

    private function pickDiscount(int $maxAllowed): int
    {
        if ($this->rand01() >= 0.06) {
            return 0;
        }

        $discount = [1000, 2000, 5000][mt_rand(0, 2)];

        return min($discount, $maxAllowed);
    }

    private function cashReceived(int $amount): int
    {
        // Pelanggan membayar dengan pecahan yang wajar (dibulatkan ke atas).
        $options = [
            (int) (ceil($amount / 5000) * 5000),
            (int) (ceil($amount / 10000) * 10000),
            (int) (ceil($amount / 50000) * 50000),
            (int) (ceil($amount / 100000) * 100000),
        ];

        return $options[$this->pickIndex([35, 30, 20, 15])];
    }

    // ------------------------------------------------------------
    // Pembangkit angka acak (deterministik karena mt_srand)
    // ------------------------------------------------------------

    private function rand01(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    private function gauss(): float
    {
        // Box-Muller
        $u1 = max($this->rand01(), 1e-12);
        $u2 = $this->rand01();

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    private function pickIndex(array $weights): int
    {
        $sum = array_sum($weights);
        $r   = $this->rand01() * $sum;

        foreach ($weights as $index => $weight) {
            $r -= $weight;
            if ($r <= 0) {
                return (int) $index;
            }
        }

        // Cadangan bila terjadi pembulatan: ambil indeks terakhir berbobot > 0
        for ($i = count($weights) - 1; $i >= 0; $i--) {
            if ($weights[$i] > 0) {
                return $i;
            }
        }

        return 0;
    }

    // ------------------------------------------------------------
    // Ringkasan
    // ------------------------------------------------------------

    private function printSummary(array $orders): void
    {
        if (!$this->command) {
            return;
        }

        $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $perDay   = array_fill(0, 7, 0);
        $perDate  = [];
        $methods  = ['cash' => 0, 'qris' => 0];
        $revenue  = 0;
        $qtyTotal = 0;

        foreach ($orders as $order) {
            $perDay[$order['weekday']]++;
            $perDate[$order['date']] = ($perDate[$order['date']] ?? 0) + 1;
            $methods[$order['method']]++;
            $revenue += $order['grand_total'];
            foreach ($order['items'] as $item) {
                $qtyTotal += $item['qty'];
            }
        }

        ksort($perDate);

        $this->command->info('');
        $this->command->info('Data dummy berhasil dibuat.');
        $this->command->line('  Jumlah pesanan : ' . count($orders));
        $this->command->line('  Rentang tanggal: ' . array_key_first($perDate) . ' s/d ' . array_key_last($perDate));
        $this->command->line('  Hari tercatat  : ' . count($perDate) . ' hari');
        $this->command->line('  Total porsi    : ' . $qtyTotal);
        $this->command->line('  Total omzet    : Rp ' . number_format($revenue, 0, ',', '.'));
        $this->command->line('  Cash / QRIS    : ' . $methods['cash'] . ' / ' . $methods['qris']);
        $this->command->line('  Pesanan per hari dalam seminggu:');
        foreach ($perDay as $dow => $count) {
            $this->command->line('    ' . str_pad($dayNames[$dow], 7) . ' ' . $count);
        }
    }
}