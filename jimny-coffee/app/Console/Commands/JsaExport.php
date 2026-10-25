<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class JsaExport extends Command
{
    protected $signature = 'jsa:export
        {--days= : Batasi ke N hari terakhir (tanpa hari ini). Kosong = seluruh riwayat}
        {--out= : Folder keluaran. Default storage/app/jsa}';

    protected $description = 'Ekspor permintaan harian (total dan per menu) ke CSV untuk analisis JSA';

    public function handle(): int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null && $daysOption !== '') {
            if (! ctype_digit((string) $daysOption) || (int) $daysOption < 1) {
                $this->error('Opsi --days harus bilangan bulat positif.');

                return self::FAILURE;
            }
        }

        // Hari ini belum selesai, jadi data berakhir pada hari kemarin.
        $end = Carbon::yesterday()->endOfDay();

        $baseQuery = fn () => DB::table('transactions as t')
            ->join(
                'transaction_details as d',
                't.id',
                '=',
                'd.transaction_id'
            )
            ->where('t.status', 'success')
            ->where('t.payment_status', 'paid')
            ->where('t.created_at', '<=', $end);

        $start = null;

        if ($daysOption !== null && $daysOption !== '') {
            $start = Carbon::yesterday()
                ->subDays((int) $daysOption - 1)
                ->startOfDay();
        } else {
            $first = $baseQuery()->min('t.created_at');

            if ($first !== null) {
                $start = Carbon::parse($first)->startOfDay();
            }
        }

        if ($start === null) {
            $this->warn('Belum ada transaksi sukses (paid) sebelum hari ini. Tidak ada yang diekspor.');

            return self::SUCCESS;
        }

        $inRange = fn () => $baseQuery()
            ->where('t.created_at', '>=', $start);

        // Total porsi dan jumlah transaksi per hari.
        $totalRows = $inRange()
            ->selectRaw(
                'DATE(t.created_at) AS sale_date,
                 SUM(d.qty) AS qty,
                 COUNT(DISTINCT t.id) AS orders'
            )
            ->groupByRaw('DATE(t.created_at)')
            ->get();

        $totalMap = [];

        foreach ($totalRows as $row) {
            $totalMap[$row->sale_date] = [
                (int) $row->qty,
                (int) $row->orders,
            ];
        }

        // Porsi per menu per hari (hanya baris yang ada penjualannya).
        $menuRows = $inRange()
            ->join('menus as m', 'm.id', '=', 'd.menu_id')
            ->selectRaw(
                'DATE(t.created_at) AS sale_date,
                 d.menu_id AS menu_id,
                 m.nama_menu AS menu_name,
                 SUM(d.qty) AS qty'
            )
            ->groupByRaw('DATE(t.created_at), d.menu_id, m.nama_menu')
            ->orderByRaw('DATE(t.created_at)')
            ->orderBy('d.menu_id')
            ->get();

        $dir = rtrim(
            (string) ($this->option('out') ?: storage_path('app/jsa')),
            '/\\'
        );

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Tidak bisa membuat folder: {$dir}");

            return self::FAILURE;
        }

        // Tulis deret harian dengan hari kosong diisi 0 (rangka waktu penuh).
        $totalPath = $dir . DIRECTORY_SEPARATOR . 'daily_total.csv';
        $handle = fopen($totalPath, 'w');

        if ($handle === false) {
            $this->error("Tidak bisa menulis file: {$totalPath}");

            return self::FAILURE;
        }

        fputcsv($handle, ['tanggal', 'total_porsi', 'jumlah_transaksi']);

        $dayCount = 0;
        $totalQty = 0;

        for (
            $cursor = $start->copy();
            $cursor->lte($end);
            $cursor->addDay()
        ) {
            $date = $cursor->toDateString();
            [$qty, $orders] = $totalMap[$date] ?? [0, 0];

            fputcsv($handle, [$date, $qty, $orders]);

            $dayCount++;
            $totalQty += $qty;
        }

        fclose($handle);

        // Tulis rincian per menu.
        $menuPath = $dir . DIRECTORY_SEPARATOR . 'daily_menu.csv';
        $handle = fopen($menuPath, 'w');

        if ($handle === false) {
            $this->error("Tidak bisa menulis file: {$menuPath}");

            return self::FAILURE;
        }

        fputcsv($handle, ['tanggal', 'menu_id', 'nama_menu', 'porsi']);

        foreach ($menuRows as $row) {
            fputcsv($handle, [
                $row->sale_date,
                (int) $row->menu_id,
                $row->menu_name,
                (int) $row->qty,
            ]);
        }

        fclose($handle);

        $this->info('Ekspor selesai.');
        $this->line("Periode      : {$start->toDateString()} s/d {$end->toDateString()} ({$dayCount} hari, hari ini tidak ikut)");
        $this->line("Total porsi  : {$totalQty}");
        $this->line("File total   : {$totalPath}");
        $this->line("File per menu: {$menuPath} ({$menuRows->count()} baris)");

        return self::SUCCESS;
    }
}