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

        Config::$serverKey = $serverKey;
        Config::$isProduction = (bool) config('midtrans.is_production');
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

        return Snap::getSnapToken([
            'transaction_details' => [
                'order_id' => $payment->external_id,
                'gross_amount' => $total,
            ],
            'enabled_payments' => ['other_qris'],
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
    public function batalkanSesiSnap(Payment $payment): bool
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

        return $response->successful()
            && filled($response->json('canceled_at'));
    }

    /**
     * Meminta pembatalan transaksi pembayaran melalui Midtrans.
     * Responsnya harus diperiksa sebelum status lokal diubah.
     */
    public function batalkanTransaksiMidtrans(Payment $payment): string
    {
        if (
            $payment->metode !== 'qris'
            || blank($payment->external_id)
        ) {
            throw new RuntimeException(
                'Data transaksi Midtrans tidak tersedia.'
            );
        }

        return (string) MidtransTransaction::cancel($payment->external_id);
    }

}