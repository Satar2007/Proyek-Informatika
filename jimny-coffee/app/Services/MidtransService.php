<?php

namespace App\Services;

use App\Models\Payment;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction as MidtransTransaction;
use RuntimeException;

class MidtransService
{
    public function __construct()
    {
        $serverKey = config('midtrans.server_key');

        if (blank($serverKey)) {
            throw new RuntimeException(
                'Server Key Midtrans belum dikonfigurasi.'
            );
        }

        $isProduction = (bool) config('midtrans.is_production');
        $lockStore = (string) config(
            'midtrans.lock_store',
            config('cache.default', 'database')
        );
        if (
            $isProduction
            && !in_array($lockStore, ['database', 'redis'], true)
        ) {
            throw new RuntimeException(
                'MIDTRANS_LOCK_STORE production wajib menggunakan database atau redis.'
            );
        }

        Config::$serverKey = $serverKey;
        Config::$isProduction = $isProduction;
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function buatSnapToken(Payment $payment): string
    {
        if ($payment->metode !== 'qris') {
            throw new RuntimeException(
                'Snap Token hanya dibuat untuk pembayaran QRIS.'
            );
        }

        if ($payment->payment_status !== 'waiting') {
            throw new RuntimeException(
                'Pembayaran tidak lagi menunggu pembayaran.'
            );
        }

        $transaksi = $payment->transaction;

        if (!$transaksi) {
            throw new RuntimeException(
                'Transaksi pembayaran tidak ditemukan.'
            );
        }

        $total = (int) $transaksi->grand_total;

        if ($total <= 0) {
            throw new RuntimeException(
                'Total pembayaran harus lebih besar dari nol.'
            );
        }

        $expiryMinutes = max(
            5,
            min(10080, (int) config('midtrans.snap_expiry_minutes', 5))
        );

        // Explicit start_time makes the expiry begin when the token is created,
        // not later when the customer eventually opens/selects a payment method.
        $expiryStart = now();

        return Snap::getSnapToken([
            'transaction_details' => [
                'order_id' => $payment->external_id,
                'gross_amount' => $total,
            ],
            'enabled_payments' => ['other_qris'],
            'expiry' => [
                'start_time' => $expiryStart->format('Y-m-d H:i:s O'),
                'unit' => 'minutes',
                'duration' => $expiryMinutes,
            ],
        ]);
    }

    /**
     * Mengambil status pembayaran langsung dari server Midtrans.
     *
     * Mengembalikan null jika transaksi belum ditemukan di Status API,
     * misalnya ketika pelanggan belum memilih metode pembayaran di Snap.
     */
    public function ambilStatus(Payment $payment): ?object
    {
        if ($payment->metode !== 'qris') {
            throw new RuntimeException(
                'Pemeriksaan Midtrans hanya berlaku untuk QRIS.'
            );
        }

        if (blank($payment->external_id)) {
            throw new RuntimeException(
                'Order ID Midtrans tidak tersedia.'
            );
        }

        try {
            return MidtransTransaction::status(
                $payment->external_id
            );
        } catch (\Exception $e) {
            if ((int) $e->getCode() === 404) {
                return null;
            }

            throw $e;
        }
    }
    /**
     * Membatalkan sesi Snap yang belum menjadi transaksi pembayaran.
     * Mengembalikan true hanya jika Midtrans mengonfirmasi pembatalan sesi.
     */
    public function batalkanSesiSnap(
        Payment $payment,
        bool $statusApiConfirmedNotFound = false
    ): bool
    {
        if (
            $payment->metode !== 'qris'
            || blank($payment->midtrans_snap_token)
        ) {
            throw new RuntimeException(
                'Sesi Snap QRIS tidak tersedia.'
            );
        }

        $baseUrl = config('midtrans.is_production')
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';

        $url = $baseUrl
            . '/snap/v1/transactions/'
            . rawurlencode($payment->midtrans_snap_token)
            . '/cancel';

        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth(
                (string) config('midtrans.server_key'),
                ''
            )
            ->post($url, []);

        if (
            $response->successful()
            && filled($response->json('canceled_at'))
        ) {
            return true;
        }

        // "Token already canceled" is an explicit terminal response.
        // "Token not found" is accepted only when the caller has just checked
        // Status API and received 404, and will perform another status check
        // after this request. This prevents an isolated 404 from releasing stock.
        $errors = $response->json('error_messages', []);
        foreach ((array) $errors as $error) {
            $message = strtolower((string) $error);

            if (str_contains($message, 'token already canceled')) {
                return true;
            }

            if (
                str_contains($message, 'token not found')
                && $statusApiConfirmedNotFound
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Meminta pembatalan transaksi pembayaran melalui Midtrans.
     * Responsnya harus diperiksa sebelum status lokal diubah.
     */
    public function batalkanTransaksiMidtrans(Payment $payment)
    {
        if (
            $payment->metode !== 'qris'
            || blank($payment->external_id)
        ) {
            throw new RuntimeException(
                'Data transaksi Midtrans tidak tersedia.'
            );
        }

        // Keep the SDK response object intact so the caller can inspect
        // transaction_status before deciding whether stock can be released.
        return MidtransTransaction::cancel($payment->external_id);
    }

}