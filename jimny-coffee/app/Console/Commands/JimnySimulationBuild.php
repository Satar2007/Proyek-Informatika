<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\JimnySimulationLabService;
use Illuminate\Console\Command;
use Throwable;

final class JimnySimulationBuild extends Command
{
    protected $signature = 'jimny:simulation-build';
    protected $description = 'Generate 24 months SYNTHETIC POS data in storage/app only; no database writes';

    public function handle(JimnySimulationLabService $service): int
    {
        try {
            $view = $service->build();
            $this->info('JIMNY V19: DATA SINTETIS berhasil dibuat (TANPA DB).');
            $this->line('Rentang: '.$view['history_from'].' s.d. '.$view['history_through']);
            $this->line('Hari simulasi: '.$view['history_days']);
            $this->line('Prediksi 365 hari: '.$view['forecasts']['365']['status']);
            $this->line('Annual backtest: '.$view['forecasts']['365']['backtest']['status']);
            $this->warn('Semua angka hanyalah DEMO. Tidak dicampur dengan transaksi/omzet/stok live.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Gagal membuat simulasi: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
