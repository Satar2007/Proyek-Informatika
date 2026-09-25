<?php

namespace App\Services;

use App\Models\Payment;
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

        if ($orderId === '') {
            throw new RuntimeException(
                'Order ID Midtrans tidak tersedia.'
            );
        }

        if (!is_numeric($grossAmount)) {
            throw new RuntimeException(
                'Nominal Midtrans tidak valid.'
            );
        }

        $transactionStatus = strtolower(
            (string) $value($remote, 'transaction_status')
        );

        $fraudStatus = strtolower(
            (string) $value($remote, 'fraud_status')
        );

        return DB::transaction(function () use (
            $paymentId,
            $orderId,
            $grossAmount,
            $transactionStatus,
            $fraudStatus
        ) {
            $payment = Payment::with('transaction')
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ($payment->metode !== 'qris') {
                throw new RuntimeException(
                    'Sinkronisasi Midtrans hanya berlaku untuk QRIS.'
                );
            }

            $transaction = $payment->transaction;

            if (!$transaction) {
                throw new RuntimeException(
                    'Transaksi pembayaran tidak ditemukan.'
                );
            }

            if ($orderId !== (string) $payment->external_id) {
                throw new RuntimeException(
                    'Order ID Midtrans tidak sesuai.'
                );
            }

            if (
                abs(
                    (float) $grossAmount
                    - (float) $transaction->grand_total
                ) > 0.00001
            ) {
                throw new RuntimeException(
                    'Nominal pembayaran Midtrans tidak sesuai.'
                );
            }

            /*
             * Idempotensi utama.
             *
             * Polling, webhook duplikat, atau request yang datang
             * bersamaan tidak boleh memproses stok dua kali.
             */
            if ($payment->payment_status !== 'waiting') {
                return (string) $payment->payment_status;
            }

            $successful =
                $transactionStatus === 'settlement'
                && (
                    $fraudStatus === ''
                    || $fraudStatus === 'accept'
                );

            $failedStatus = match ($transactionStatus) {
                'expire' => 'expired',
                'cancel' => 'cancelled',
                'deny', 'failure' => 'failed',
                default => null,
            };

            if ($successful) {
                app(StockReservationService::class)->complete(
                    (int) $transaction->id
                );

                $now = now();

                $payment->update([
                    'payment_status' => 'paid',
                    'paid_at' => $now,
                ]);

                $transaction->update([
                    'status' => 'success',
                    'payment_status' => 'paid',
                    'paid_at' => $now,
                ]);

                return 'paid';
            }

            if ($failedStatus !== null) {
                app(StockReservationService::class)->release(
                    (int) $transaction->id
                );

                $payment->update([
                    'payment_status' => $failedStatus,
                ]);

                $transaction->update([
                    'status' => $failedStatus === 'failed'
                        ? 'cancelled'
                        : $failedStatus,
                    'payment_status' => $failedStatus,
                ]);

                return $failedStatus;
            }

            /*
             * pending atau status Midtrans lain yang belum final
             * tetap mempertahankan reservasi.
             */
            return 'waiting';
        }, 3);
    }
}