<?php

namespace App\Console\Commands;

use App\Services\JimnyIntelligenceService;
use Illuminate\Console\Command;

class JimnyForecastRefresh extends Command
{
    protected $signature = 'jimny:intelligence-refresh';
    protected $description = 'Refresh read-only intelligence snapshot from paid POS transactions';

    public function handle(JimnyIntelligenceService $service): int
    {
        $data = $service->snapshot();
        $dir = storage_path('app/jimny-intelligence');
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            $this->error('Folder snapshot tidak dapat dibuat.');
            return self::FAILURE;
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $target = $dir . '/latest.json';
        $temporary = tempnam($dir, 'jimny-');
        try {
            if ($temporary === false || file_put_contents($temporary, $json, LOCK_EX) === false) {
                throw new \RuntimeException('Gagal menulis snapshot sementara.');
            }
            if (!rename($temporary, $target)) {
                throw new \RuntimeException('Gagal menerbitkan snapshot.');
            }
        } finally {
            if ($temporary && is_file($temporary)) @unlink($temporary);
        }
        $this->info('Snapshot JIMNY diperbarui: ' . $data['generated_at']);
        $this->line('Status 365 hari: ' . $data['forecasts']['365']['status']);
        return self::SUCCESS;
    }
}
