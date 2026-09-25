<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class MidtransWebhookController extends Controller
{
    public function handle(
        Request $request,
        PaymentStatusService $statusService
    ): JsonResponse {
        $orderId = (string) $request->input('order_id', '');
        $statusCode = (string) $request->input('status_code', '');
        $grossAmount = (string) $request->input('gross_amount', '');
        $signature = (string) $request->input('signature_key', '');

        if (
            $orderId === ''
            || $statusCode === ''
            || $grossAmount === ''
            || $signature === ''
        ) {
            return response()->json([
                'received' => false,
                'message' => 'Payload notifikasi tidak lengkap.',
            ], 422);
        }

        $serverKey = (string) config('midtrans.server_key');

        if ($serverKey === '') {
            report(new RuntimeException(
                'Server Key Midtrans belum dikonfigurasi.'
            ));

            return response()->json([
                'received' => false,
                'message' => 'Konfigurasi pembayaran tidak tersedia.',
            ], 500);
        }

        $expectedSignature = hash(
            'sha512',
            $orderId
            . $statusCode
            . $grossAmount
            . $serverKey
        );

        if (
            !hash_equals(
                strtolower($expectedSignature),
                strtolower($signature)
            )
        ) {
            return response()->json([
                'received' => false,
                'message' => 'Signature notifikasi tidak valid.',
            ], 403);
        }

        $payment = Payment::query()
            ->where('external_id', $orderId)
            ->where('metode', 'qris')
            ->first();

        if (!$payment) {
            return response()->json([
                'received' => false,
                'message' => 'Pembayaran tidak ditemukan.',
            ], 404);
        }

        try {
            $status = $statusService->applyRemoteStatus(
                (int) $payment->id,
                $request->all()
            );

            return response()->json([
                'received' => true,
                'status' => $status,
            ]);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json([
                'received' => false,
                'message' => 'Notifikasi tidak dapat diterapkan.',
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'received' => false,
                'message' => 'Terjadi kesalahan saat memproses notifikasi.',
            ], 500);
        }
    }
}