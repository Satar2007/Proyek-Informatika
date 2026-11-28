<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentStatusService
{
    public function applyRemoteStatus(
        int $paymentId,
        object|array $remote
    ): string {
        $value = static function (object|array $source, string $key) {
            if (is_array($source)) {
                return $source[$key] ?? null;
            }

            return $source->{$key} ?? null;
        };

        $orderId = (string) $value($remote, 'order_id');
        $grossAmount = $value($remote, 'gross_amount');
        $statusCode = (string) $value($remote, 'status_code');

        if ($orderId === '') {
            throw new RuntimeException('Order ID Midtrans tidak tersedia.');
        }

        if (!is_string($grossAmount) && !is_int($grossAmount) && !is_float($grossAmount)) {
            throw new RuntimeException('Nominal Midtrans tidak valid.');
        }

        $currency = strtoupper(trim((string) $value($remote, 'currency')));
        if ($currency !== '' && $currency !== 'IDR') {
            throw new RuntimeException('Mata uang pembayaran Midtrans bukan IDR.');
        }

        $transactionStatus = strtolower((string) $value($remote, 'transaction_status'));
        $fraudStatus = strtolower((string) $value($remote, 'fraud_status'));

        return DB::transaction(function () use (
            $paymentId,
            $orderId,
            $grossAmount,
            $statusCode,
            $transactionStatus,
            $fraudStatus
        ) {
            $payment = Payment::with('transaction')
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ($payment->metode !== 'qris') {
                throw new RuntimeException('Sinkronisasi Midtrans hanya berlaku untuk QRIS.');
            }

            $transaction = $payment->transaction;
            if (!$transaction) {
                throw new RuntimeException('Transaksi pembayaran tidak ditemukan.');
            }

            if ($orderId !== (string) $payment->external_id) {
                throw new RuntimeException('Order ID Midtrans tidak sesuai.');
            }

            if ($transaction->payment_method !== 'qris') {
                throw new RuntimeException('Metode transaksi lokal tidak cocok dengan pembayaran QRIS.');
            }

            if (!$this->grossAmountMatches($grossAmount, (int) $transaction->grand_total)) {
                throw new RuntimeException('Nominal pembayaran Midtrans tidak sesuai.');
            }

            $successful = $transactionStatus === 'settlement'
                && $statusCode === '200'
                && ($fraudStatus === '' || $fraudStatus === 'accept');

            // A late settlement after local cancellation/expiry is a reconciliation
            // conflict. Do not silently acknowledge it as an ordinary terminal state.
            if (
                $payment->payment_status !== 'waiting'
                && $successful
                && in_array($payment->payment_status, ['cancelled', 'expired', 'failed'], true)
            ) {
                throw new RuntimeException(
                    'Konflik pembayaran: Midtrans menyatakan settlement setelah status lokal terminal. Rekonsiliasi manual diperlukan.'
                );
            }

            // Idempotensi: webhook duplikat dan polling bersamaan tidak memproses stok dua kali.
            if ($payment->payment_status !== 'waiting') {
                return (string) $payment->payment_status;
            }

            $failedStatus = match ($transactionStatus) {
                'expire' => 'expired',
                'cancel' => 'cancelled',
                'deny', 'failure' => 'failed',
                default => null,
            };

            if ($successful) {
                app(StockReservationService::class)->complete((int) $transaction->id);
                $now = now();
                $payment->update(['payment_status' => 'paid', 'paid_at' => $now]);
                $transaction->update([
                    'status' => 'success',
                    'payment_status' => 'paid',
                    'paid_at' => $now,
                ]);
                return 'paid';
            }

            if ($failedStatus !== null) {
                app(StockReservationService::class)->release((int) $transaction->id);
                $payment->update(['payment_status' => $failedStatus]);
                $transaction->update([
                    'status' => $failedStatus === 'failed' ? 'cancelled' : $failedStatus,
                    'payment_status' => $failedStatus,
                ]);
                return $failedStatus;
            }

            return 'waiting';
        }, 3);
    }

    /**
     * Midtrans sends gross_amount as a decimal string (for example 10300.00).
     * SATAR stores IDR totals as whole rupiah, so only an exact whole-rupiah
     * representation is accepted; fractional or over-precision values fail closed.
     */
    private function grossAmountMatches(mixed $remoteAmount, int $expectedRupiah): bool
    {
        if (!is_string($remoteAmount) && !is_int($remoteAmount) && !is_float($remoteAmount)) {
            return false;
        }

        $raw = trim((string) $remoteAmount);
        if (!preg_match('/^\d+(?:\.0+)?$/D', $raw)) {
            return false;
        }

        $whole = explode('.', $raw, 2)[0];
        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }

        return $whole === (string) $expectedRupiah;
    }

    /**
     * Reconcile an expired QRIS reservation without releasing stock until
     * Midtrans confirms that a Snap session/transaction cannot still be paid.
     * The same cache lock is used by PaymentController::token to prevent a
     * token-creation request racing with local expiry.
     */
    public function expireExpiredQris(int $paymentId): string
    {
        return Cache::store(
            config('midtrans.lock_store', config('cache.default'))
        )
            ->lock(
                'midtrans:snap-token:' . $paymentId,
                (int) config('midtrans.lock_seconds', 180)
            )
            ->block(2, function () use ($paymentId) {
                $payment = Payment::with('transaction')->findOrFail($paymentId);

                if ($payment->metode !== 'qris') {
                    throw new RuntimeException('Kedaluwarsa otomatis hanya berlaku untuk QRIS.');
                }

                if ($payment->payment_status !== 'waiting') {
                    return (string) $payment->payment_status;
                }

                if (!$payment->expired_at || $payment->expired_at->isFuture()) {
                    return 'waiting';
                }

                if (!$payment->transaction || $payment->transaction->status !== 'pending') {
                    return (string) $payment->payment_status;
                }

                $snapToken = (string) ($payment->midtrans_snap_token ?? '');
                $orderId = (string) $payment->external_id;

                // No Snap token means no remote checkout session was created.
                if ($snapToken === '') {
                    return $this->finalizeLocallyExpired(
                        $paymentId,
                        null,
                        $orderId
                    );
                }

                $midtrans = app(MidtransService::class);
                $remote = $midtrans->ambilStatus($payment);

                if ($remote !== null) {
                    $remoteStatus = strtolower((string) ($remote->transaction_status ?? ''));

                    // If a payment transaction exists and is still pending,
                    // request remote cancellation, then trust only a confirmed status.
                    if ($remoteStatus === 'pending') {
                        try {
                            $cancelResponse = $midtrans->batalkanTransaksiMidtrans($payment);
                            if (is_object($cancelResponse) || is_array($cancelResponse)) {
                                $cancelStatus = strtolower((string) (
                                    is_array($cancelResponse)
                                        ? ($cancelResponse['transaction_status'] ?? '')
                                        : ($cancelResponse->transaction_status ?? '')
                                ));
                                if (in_array($cancelStatus, ['cancel', 'expire', 'settlement', 'deny', 'failure'], true)) {
                                    return $this->applyRemoteStatus($paymentId, $cancelResponse);
                                }
                            }
                        } catch (\Throwable $exception) {
                            report($exception);
                        }

                        $remote = $midtrans->ambilStatus($payment);
                    }

                    if ($remote !== null) {
                        return $this->applyRemoteStatus($paymentId, $remote);
                    }

                    // A transaction was previously visible. If it disappears
                    // during cancellation, do not infer a final status locally.
                    return 'waiting';
                }

                // No payment transaction exists yet. Cancel the Snap session
                // itself before releasing the local stock reservation.
                if (!$midtrans->batalkanSesiSnap($payment, true)) {
                    return 'waiting';
                }

                // Re-check after cancellation to catch a payment that raced
                // with the session-cancel request.
                $remoteAfterCancel = $midtrans->ambilStatus($payment);
                if ($remoteAfterCancel !== null) {
                    return $this->applyRemoteStatus($paymentId, $remoteAfterCancel);
                }

                return $this->finalizeLocallyExpired(
                    $paymentId,
                    $snapToken,
                    $orderId
                );
            });
    }

    private function finalizeLocallyExpired(
        int $paymentId,
        ?string $expectedSnapToken,
        string $expectedOrderId
    ): string {
        return DB::transaction(function () use (
            $paymentId,
            $expectedSnapToken,
            $expectedOrderId
        ) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);
            $transaction = $payment->transaction()->lockForUpdate()->firstOrFail();

            if ($payment->payment_status !== 'waiting') {
                return (string) $payment->payment_status;
            }

            if (
                $payment->metode !== 'qris'
                || (string) $payment->external_id !== $expectedOrderId
                || (string) ($payment->midtrans_snap_token ?? '') !== (string) ($expectedSnapToken ?? '')
                || !$payment->expired_at
                || $payment->expired_at->isFuture()
                || $transaction->status !== 'pending'
                || !in_array($transaction->payment_status, ['unpaid', 'waiting'], true)
            ) {
                return 'waiting';
            }

            app(StockReservationService::class)->release((int) $transaction->id);
            $payment->update(['payment_status' => 'expired']);
            $transaction->update([
                'status' => 'expired',
                'payment_status' => 'expired',
            ]);

            return 'expired';
        }, 3);
    }
}
