<?php

use App\Models\Payment;
use App\Services\PaymentStatusService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'payments:reconcile-expired-qris {--limit=100}',
    function (PaymentStatusService $statusService) {
        $limit = max(1, min(500, (int) $this->option('limit')));

        $payments = Payment::query()
            ->where('metode', 'qris')
            ->where('payment_status', 'waiting')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->orderBy('expired_at')
            ->limit($limit)
            ->get(['id']);

        if ($payments->isEmpty()) {
            $this->info('Tidak ada reservasi QRIS kedaluwarsa yang perlu direkonsiliasi.');
            return 0;
        }

        $failed = 0;
        foreach ($payments as $payment) {
            try {
                $status = $statusService->expireExpiredQris((int) $payment->id);
                $this->line("Payment #{$payment->id}: {$status}");
            } catch (\Throwable $exception) {
                $failed++;
                report($exception);
                $this->error("Payment #{$payment->id}: gagal diproses; periksa log aplikasi.");
            }
        }

        return $failed > 0 ? 1 : 0;
    }
)->purpose('Reconcile expired QRIS sessions and safely release stock reservations');

Schedule::command('payments:reconcile-expired-qris --limit=100')
    ->everyMinute()
    ->withoutOverlapping(5);

// JIMNY_INTELLIGENCE_360: scheduled snapshot; scheduler must be running.
Schedule::command('jimny:intelligence-refresh')
    ->dailyAt('02:15')
    ->withoutOverlapping(15);
