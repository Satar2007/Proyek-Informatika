<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Str;

class PaymentService
{
    public function buatPayment(Transaction $transaksi, string $metode = 'qris'): Payment
    {
        return Payment::create([
            'transaction_id' => $transaksi->id,
            'metode'         => $metode,
            'external_id'    => 'PAY-' . strtoupper(Str::uuid()),
            'qris_string'    => $metode === 'qris' ? 'QRIS-' . $transaksi->kode_transaksi . '-' . $transaksi->grand_total : null,
            'payment_status' => 'waiting',
            'expired_at'     => now()->addMinutes(3),
        ]);
    }

    public function konfirmasiPayment(Payment $payment): bool
    {
        $payment->update([
            'payment_status' => 'paid',
            'paid_at'        => now(),
        ]);

        $payment->transaction->update([
            'status'         => 'success',
            'payment_status' => 'paid',
            'paid_at'        => now(),
        ]);

        return true;
    }
}