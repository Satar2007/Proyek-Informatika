<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    public function buatPayment(Transaction $transaksi, string $metode = 'qris'): Payment
    {
        $expiryMinutes = max(
            5,
            min(10080, (int) config('midtrans.snap_expiry_minutes', 5))
        );

        return Payment::create([
            'transaction_id' => $transaksi->id,
            'metode'         => $metode,
            'external_id'    => 'PAY-' . strtoupper(Str::uuid()),
            'qris_string'    => $metode === 'qris' ? 'QRIS-' . $transaksi->kode_transaksi . '-' . $transaksi->grand_total : null,
            'payment_status' => 'waiting',
            'expired_at'     => now()->addMinutes($expiryMinutes),
        ]);
    }

    /**
     * @deprecated Manual payment confirmation is intentionally disabled.
     * Only PaymentStatusService may mark a QRIS payment as paid after
     * validating a signed Midtrans notification or Status API response.
     */
    public function konfirmasiPayment(Payment $payment): bool
    {
        throw new RuntimeException(
            'Konfirmasi pembayaran manual dinonaktifkan. '
            .'Gunakan PaymentStatusService setelah verifikasi Midtrans.'
        );
    }
}