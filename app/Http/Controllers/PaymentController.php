<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;
    protected StockService $stockService;

    public function __construct(PaymentService $paymentService, StockService $stockService)
    {
        $this->paymentService = $paymentService;
        $this->stockService = $stockService;
    }

    public function show($paymentId)
    {
        $payment = Payment::with('transaction.details.menu')->findOrFail($paymentId);
        $qrCode = QrCode::size(300)->generate($payment->qris_string);

        return view('payment.qris', compact('payment', 'qrCode'));
    }

    public function check($paymentId)
    {
        $payment = Payment::findOrFail($paymentId);

        return response()->json([
            'status' => $payment->payment_status,
        ]);
    }

    public function success($paymentId)
    {
        try {
            DB::transaction(function () use ($paymentId) {
                $payment = Payment::where('id', $paymentId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $payment->load('transaction.details.menu');

                if ($payment->payment_status !== 'waiting') {
                    throw new \Exception('Payment sudah diproses.');
                }

                foreach ($payment->transaction->details as $detail) {
                    $this->stockService->kurangiStok(
                        $detail->menu,
                        $detail->qty,
                        $payment->transaction->user_id,
                        'Penjualan QRIS - Transaksi #' . $payment->transaction->id
                    );
                }

                $payment->update([
                    'payment_status' => 'paid',
                    'paid_at'        => now(),
                ]);

                $payment->transaction->update([
                    'status'         => 'success',
                    'payment_status' => 'paid',
                    'paid_at'        => now(),
                ]);
            });

            return response()->json([
                'success' => true,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function struk($paymentId)
    {
        $payment = Payment::with('transaction.details.menu', 'transaction.user')->findOrFail($paymentId);

        return view('payment.struk', compact('payment'));
    }
}