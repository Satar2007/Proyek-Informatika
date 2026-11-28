<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MidtransService;
use App\Services\StockService;
use App\Services\StockReservationService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        protected MidtransService $midtransService,
        protected StockService $stockService
    ) {
    }

    /**
     * Menampilkan halaman pembayaran QRIS.
     *
     * Membuka halaman ini tidak membuat Snap Token.
     * Token akan diminta melalui endpoint khusus ketika
     * kasir menekan tombol Buka Pembayaran Midtrans.
     */
    public function show($paymentId)
    {
        $payment = $this->findAccessiblePayment($paymentId, ['transaction.details.menu']);

        abort_unless($payment->metode === 'qris', 404);

        return view('payment.midtrans', compact('payment'));
    }

    /**
     * Membuat Snap Token hanya ketika tombol pembayaran ditekan.
     */
    public function token($paymentId)
    {
        $payment = $this->findAccessiblePayment($paymentId, ['transaction']);

        abort_unless($payment->metode === 'qris', 404);

        if (auth()->user()->role === 'kasir') {
            abort_unless(
                $payment->transaction->user_id === auth()->id(),
                403
            );
        }

        if (
            $payment->payment_status !== 'waiting'
            || $payment->transaction->status !== 'pending'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak lagi menunggu pembayaran.',
            ], 409);
        }

        if ($payment->expired_at && $payment->expired_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Batas waktu transaksi telah habis. Batalkan transaksi ini dan buat transaksi baru.',
            ], 409);
        }

        try {
            $snapToken = Cache::store(
                config('midtrans.lock_store', config('cache.default'))
            )
                ->lock(
                    'midtrans:snap-token:' . $paymentId,
                    (int) config('midtrans.lock_seconds', 180)
                )
                ->block(10, function () use ($paymentId) {
                    $currentPayment = Payment::with('transaction')
                        ->findOrFail($paymentId);

                    if (
                        !$currentPayment->transaction
                        || $currentPayment->payment_status !== 'waiting'
                        || $currentPayment->transaction->status !== 'pending'
                        || $currentPayment->transaction->stock_reservation_status !== 'reserved'
                    ) {
                        throw new ConflictHttpException(
                            'Transaksi tidak lagi menunggu pembayaran.'
                        );
                    }

                    // Re-check expiry after acquiring the shared token lock.
                    // The request may have waited after its first preflight check.
                    if ($currentPayment->expired_at && $currentPayment->expired_at->isPast()) {
                        throw new ConflictHttpException(
                            'Batas waktu transaksi telah habis. Batalkan transaksi ini dan buat transaksi baru.'
                        );
                    }

                    if (filled($currentPayment->midtrans_snap_token)) {
                        return $currentPayment->midtrans_snap_token;
                    }

                    // Request Midtrans berjalan di luar database transaction.
                    $newToken = $this->midtransService
                        ->buatSnapToken($currentPayment);

                    $expiryMinutes = max(
                        5,
                        min(10080, (int) config('midtrans.snap_expiry_minutes', 5))
                    );
                    $expiresAt = now()->addMinutes($expiryMinutes);

                    return DB::transaction(function () use (
                        $paymentId,
                        $newToken,
                        $expiresAt
                    ) {
                        $freshPayment = Payment::with('transaction')
                            ->lockForUpdate()
                            ->findOrFail($paymentId);

                        if (
                            !$freshPayment->transaction
                            || $freshPayment->payment_status !== 'waiting'
                            || $freshPayment->transaction->status !== 'pending'
                            || $freshPayment->transaction->stock_reservation_status !== 'reserved'
                        ) {
                            throw new ConflictHttpException(
                                'Transaksi tidak lagi menunggu pembayaran.'
                            );
                        }

                        if (filled($freshPayment->midtrans_snap_token)) {
                            return $freshPayment->midtrans_snap_token;
                        }

                        $freshPayment->update([
                            'midtrans_snap_token' => $newToken,
                            'expired_at' => $expiresAt,
                        ]);

                        return $newToken;
                    });
                });

            return response()->json([
                'success' => true,
                'snap_token' => $snapToken,
            ]);
        } catch (LockTimeoutException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan token pembayaran sedang diproses. Coba lagi beberapa saat.',
            ], 409)->header('Retry-After', '2');
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Pembayaran Midtrans belum dapat dibuka.',
            ], 503);
        }
    }

    /**
     * Memeriksa status pembayaran melalui Midtrans
     * dan menyinkronkannya dengan database SATAR.
     */
    public function check($paymentId)
    {
        $payment = $this->findAccessiblePayment(
            $paymentId,
            ['transaction']
        );

        if ($payment->metode !== 'qris') {
            return response()->json([
                'status' => $payment->payment_status,
            ]);
        }

        if ($payment->payment_status !== 'waiting') {
            return response()->json([
                'status' => $payment->payment_status,
            ]);
        }

        if (blank($payment->midtrans_snap_token)) {
            return response()->json([
                'status' => 'waiting',
            ]);
        }

        try {
            /*
             * Request jaringan dilakukan di luar database transaction
             * agar tidak menahan lock selama komunikasi dengan Midtrans.
             */
            $statusMidtrans = $this->midtransService
                ->ambilStatus($payment);

            if ($statusMidtrans === null) {
                return response()->json([
                    'status' => 'waiting',
                ]);
            }

            /*
             * Polling dan webhook menggunakan SATU service yang sama.
             * Service bertanggung jawab atas validasi identitas,
             * nominal, idempotensi, reservasi stok, dan status lokal.
             */
            $statusLokal = app(
                \App\Services\PaymentStatusService::class
            )->applyRemoteStatus(
                (int) $payment->id,
                $statusMidtrans
            );

            return response()->json([
                'status' => $statusLokal,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'unknown',
                'message' => 'Status pembayaran belum dapat diverifikasi.',
            ], 503);
        }
    }

    /**
     * Konfirmasi pembayaran manual tetap dinonaktifkan.
     */
    public function success($paymentId)
    {
        $this->findAccessiblePayment($paymentId);

        return response()->json([
            'success' => false,
            'message' => 'Konfirmasi pembayaran manual dinonaktifkan. '
                . 'Status pembayaran harus diverifikasi melalui Midtrans.',
        ], 403);
    }

    /**
     * Struk hanya tersedia setelah pembayaran tercatat lunas.
     */
    public function struk($paymentId)
    {
        $payment = $this->findAccessiblePayment($paymentId, ['transaction.details.menu', 'transaction.user']);

        abort_unless($payment->payment_status === 'paid', 403);

        return view('payment.struk', compact('payment'));
    }

    /**
     * Batasi akses sebelum informasi pembayaran digunakan.
     * Kasir lain mendapat 404, termasuk sebelum panggilan Midtrans.
     */
    private function findAccessiblePayment(
        $paymentId,
        array $relations = []
    ): Payment {
        $user = auth()->user();

        abort_unless(
            $user && in_array($user->role, ['kasir', 'admin'], true),
            403
        );

        $query = Payment::query()->with($relations);

        if ($user->role === 'kasir') {
            $query->whereHas('transaction', function ($transactionQuery) use ($user) {
                $transactionQuery->where('user_id', $user->id);
            });
        }

        return $query->findOrFail($paymentId);
    }
}
