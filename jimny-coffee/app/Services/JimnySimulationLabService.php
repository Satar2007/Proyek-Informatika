<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\JimnySimulationEngine;
use RuntimeException;

final class JimnySimulationLabService
{
    private function file(): string
    {
        return storage_path('app/jimny-simulation-v19/latest.json');
    }

    public function build(): array
    {
        $result = (new JimnySimulationEngine())->build();
        $target = $this->file();
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Tidak bisa membuat folder simulasi.');
        }
        $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $tmp = tempnam($directory, 'sim-');
        if ($tmp === false) throw new RuntimeException('Gagal membuat file sementara.');
        try {
            if (file_put_contents($tmp, $json, LOCK_EX) === false) {
                throw new RuntimeException('Gagal menyimpan dataset simulasi.');
            }
            // On Windows PHP rename overwrites on same volume in supported versions;
            // if it fails, leave the previous snapshot untouched.
            if (!rename($tmp, $target)) {
                throw new RuntimeException('Gagal memublikasikan dataset simulasi (file lama aman).');
            }
        } finally {
            if (is_file($tmp)) @unlink($tmp);
        }
        return $this->publicView($result);
    }

    public function read(): array
    {
        $f = $this->file();
        if (!is_file($f)) {
            throw new RuntimeException('Dataset simulasi belum dibuat. Jalankan php artisan jimny:simulation-build.');
        }
        if (filesize($f) > 5_000_000) {
            throw new RuntimeException('Dataset simulasi melewati batas aman.');
        }
        $data = json_decode((string) file_get_contents($f), true, 512, JSON_THROW_ON_ERROR);
        if (($data['schema'] ?? null) !== 'jimny-simulation-lab-v19/1'
            || ($data['synthetic'] ?? null) !== true) {
            throw new RuntimeException('Dataset simulasi tidak valid.');
        }
        return $data;
    }

    public function publicView(?array $snapshot=null): array
    {
        $s = $snapshot ?? $this->read();
        return [
            'mode' => 'SIMULATION_ONLY',
            'synthetic' => true,
            'warnings' => $s['warnings'],
            'generated_at' => $s['generated_at'],
            'history_from' => $s['history_from'],
            'history_through' => $s['history_through'],
            'history_days' => $s['history_days'],
            'summary' => $s['summary'],
            'monthly' => $s['monthly'],
            'forecasts' => $s['forecasts'],
            'restock' => array_slice($s['restock'],0,12),
        ];
    }

    public function factsForAi(string $question): array
    {
        $data = $this->publicView();
        $history = $data['monthly'];
        $lastMonth = count($history) > 1 ? $history[count($history)-2] : null;
        return [
            'source' => 'SIMULATION_ONLY_24_MONTH_SYNTHETIC',
            'strict_notice' => 'Seluruh angka SIMULASI. Jangan diklaim sebagai data POS sungguhan; jangan memerintahkan pembelian stok nyata.',
            'history_days' => $data['history_days'],
            'period' => [$data['history_from'], $data['history_through']],
            'totals' => $data['summary'],
            'last_completed_simulated_month' => $lastMonth,
            'forecasts' => $data['forecasts'],
            'top_simulated_restock' => array_slice($data['restock'],0,6),
            'warning' => 'Forecast 365 hari adalah demonstrasi; dengan 24 bulan belum ada holdout 365 hari yang memenuhi 540 hari training. Model belum teruji pada bisnis sebenarnya.',
        ];
    }
}
