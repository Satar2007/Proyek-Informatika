<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\StockReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $query = Transaction::with(['user', 'details.menu', 'payment'])
            ->latest();

        if ($user->role === 'kasir') {
            $query->where('user_id', $user->id);
        }

        $statsQuery = Transaction::query();

        if ($user->role === 'kasir') {
            $statsQuery->where('user_id', $user->id);
        }

        $transactionStats = [
            'success' => (clone $statsQuery)
                ->where('status', 'success')
                ->count(),

            'pending' => (clone $statsQuery)
                ->where('status', 'pending')
                ->count(),

            'failed' => (clone $statsQuery)
                ->whereIn('status', ['cancelled', 'expired'])
                ->count(),
        ];

        $transaksis = $query->paginate(20);

        return view(
            'transaksi.index',
            compact('transaksis', 'transactionStats')
        );
    }

    public function show($id)
    {
        $user = auth()->user();

        $query = Transaction::with(['user', 'details.menu', 'payment']);

        if ($user->role === 'kasir') {
            $query->where('user_id', $user->id);
        }

        $transaksi = $query->findOrFail($id);

        return view('transaksi.show', compact('transaksi'));
    }

    public function cancel($id): RedirectResponse
    {
        $user = auth()->user();

        $query = Transaction::with('payment');

        if ($user->role === 'kasir') {
            $query->where('user_id', $user->id);
        }

        $transaksi = $query->findOrFail($id);

        if ($transaksi->payment_method === 'qris') {
            try {
                // Alur ini khusus untuk pembayaran QRIS melalui Midtrans Sandbox.
                if ((bool) config('midtrans.is_production')) {
                    return back()->with(
                        'error',
                        'Alur pembatalan QRIS Sandbox tidak tersedia pada mode produksi.'
                    );
                }

                $payment = $transaksi->payment;

                if (!$payment || $payment->metode !== 'qris') {
                    return back()->with(
                        'error',
                        'Data pembayaran QRIS tidak ditemukan.'
                    );
                }

                if (
                    $transaksi->status !== 'pending'
                    || !in_array(
                        $transaksi->payment_status,
                        ['unpaid', 'waiting'],
                        true
                    )
                    || $payment->payment_status !== 'waiting'
                ) {
                    return back()->with(
                        'error',
                        'Transaksi tidak lagi menunggu pembayaran.'
                    );
                }

                // KASUS 1: Belum pernah dibuatkan Snap Token.
                // Tidak ada permintaan pembatalan ke Midtrans.
                if (blank($payment->midtrans_snap_token)) {
                    $hasil = DB::transaction(function () use ($id, $user) {
                        $p = \App\Models\Payment::where(
                            'transaction_id',
                            $id
                        )
                            ->lockForUpdate()
                            ->first();

                        $q = Transaction::whereKey($id)
                            ->lockForUpdate();

                        if ($user->role === 'kasir') {
                            $q->where('user_id', $user->id);
                        }

                        $t = $q->firstOrFail();

                        if (
                            !$p
                            || $p->metode !== 'qris'
                            || $p->payment_status !== 'waiting'
                            || $t->status !== 'pending'
                            || !in_array(
                                $t->payment_status,
                                ['unpaid', 'waiting'],
                                true
                            )
                        ) {
                            return [
                                'ok' => false,
                                'message' => 'Status transaksi sudah berubah.',
                            ];
                        }

                        // Token bisa saja dibuat oleh permintaan lain
                        // ketika kasir menekan tombol Batalkan.
                        if (filled($p->midtrans_snap_token)) {
                            return [
                                'ok' => false,
                                'message' => 'Snap Token baru saja dibuat. Muat ulang halaman lalu coba pembatalan kembali.',
                            ];
                        }

                        app(StockReservationService::class)->release((int) $t->id);

                        $p->update([
                            'payment_status' => 'cancelled',
                        ]);

                        $t->update([
                            'status' => 'cancelled',
                            'payment_status' => 'cancelled',
                        ]);

                        return [
                            'ok' => true,
                            'message' => 'Transaksi QRIS berhasil dibatalkan.',
                        ];
                    }, 3);

                    if (!$hasil['ok']) {
                        return back()->with('error', $hasil['message']);
                    }

                    return redirect()
                        ->route('transaksi.show', $id)
                        ->with('success', $hasil['message']);
                }

                // KASUS 2: Snap Token sudah tersedia.
                // Simpan identitas awal untuk divalidasi kembali saat
                // pembaruan database dilakukan.
                $tokenAwal = $payment->midtrans_snap_token;
                $orderIdAwal = $payment->external_id;
                $nominalAwal = (float) $transaksi->grand_total;

                $midtrans = app(\App\Services\MidtransService::class);

                // PENTING: seluruh panggilan jaringan Midtrans berada
                // DI LUAR DB::transaction() dan database lock.
                $remote = $midtrans->ambilStatus($payment);
                $sesiDibatalkan = false;

                if ($remote === null) {
                    // Status API belum menemukan transaksi pembayaran.
                    // Pembatalan sesi harus dikonfirmasi oleh Midtrans.
                    $sesiDibatalkan = $midtrans
                        ->batalkanSesiSnap($payment);

                    if (!$sesiDibatalkan) {
                        return back()->with(
                            'error',
                            'Midtrans belum mengonfirmasi pembatalan sesi Snap. Status SATAR tidak diubah.'
                        );
                    }

                    // Periksa lagi setelah sesi Snap dibatalkan.
                    $remote = $midtrans->ambilStatus($payment);
                } else {
                    // Jangan mengirim pembatalan untuk respons yang
                    // identitas atau nominalnya berbeda.
                    if (
                        (string) ($remote->order_id ?? '') !== $orderIdAwal
                        || !is_numeric($remote->gross_amount ?? null)
                        || (float) $remote->gross_amount !== $nominalAwal
                    ) {
                        return back()->with(
                            'error',
                            'Identitas atau nominal pembayaran Midtrans tidak sesuai.'
                        );
                    }

                    $statusRemote = strtolower(
                        (string) ($remote->transaction_status ?? '')
                    );

                    if ($statusRemote === 'pending') {
                        // SDK cancel() mengembalikan kode status,
                        // bukan objek dengan transaction_status.
                        //
                        // Jika permintaan mengalami kendala setelah
                        // diproses oleh Midtrans, Status API tetap
                        // diperiksa ulang.
                        try {
                            $midtrans->batalkanTransaksiMidtrans($payment);
                        } catch (\Throwable $e) {
                            report($e);
                        }

                        $remote = $midtrans->ambilStatus($payment);
                    }
                    // Jika status sebelumnya sudah cancel atau expire,
                    // tidak perlu mengirim permintaan pembatalan lagi.
                }

                // Tentukan status akhir berdasarkan respons Midtrans.
                if ($remote === null) {
                    if (!$sesiDibatalkan) {
                        return back()->with(
                            'error',
                            'Status akhir pembatalan belum dapat diverifikasi.'
                        );
                    }

                    // Sesi Snap telah dikonfirmasi batal dan Status API
                    // masih belum menemukan transaksi pembayaran.
                    $statusLokalBaru = 'cancelled';
                } else {
                    if (
                        (string) ($remote->order_id ?? '') !== $orderIdAwal
                        || !is_numeric($remote->gross_amount ?? null)
                        || (float) $remote->gross_amount !== $nominalAwal
                    ) {
                        return back()->with(
                            'error',
                            'Identitas atau nominal pembayaran akhir tidak sesuai.'
                        );
                    }

                    $statusAkhir = strtolower(
                        (string) ($remote->transaction_status ?? '')
                    );

                    $statusLokalBaru = match ($statusAkhir) {
                        'cancel' => 'cancelled',
                        'expire' => 'expired',
                        default => null,
                    };

                    if ($statusLokalBaru === null) {
                        return back()->with(
                            'error',
                            'Midtrans sekarang berstatus '
                                . ($statusAkhir ?: 'unknown')
                                . '. Pembatalan belum dikonfirmasi. Periksa status pembayaran.'
                        );
                    }
                }

                // Kunci database HANYA untuk memvalidasi ulang
                // dan menyimpan status lokal.
                $hasil = DB::transaction(function () use (
                    $id,
                    $user,
                    $tokenAwal,
                    $orderIdAwal,
                    $nominalAwal,
                    $statusLokalBaru
                ) {
                    $p = \App\Models\Payment::where(
                        'transaction_id',
                        $id
                    )
                        ->lockForUpdate()
                        ->first();

                    $q = Transaction::whereKey($id)
                        ->lockForUpdate();

                    if ($user->role === 'kasir') {
                        $q->where('user_id', $user->id);
                    }

                    $t = $q->firstOrFail();

                    if (
                        !$p
                        || $p->metode !== 'qris'
                        || $p->payment_status !== 'waiting'
                        || $t->status !== 'pending'
                        || !in_array(
                            $t->payment_status,
                            ['unpaid', 'waiting'],
                            true
                        )
                        || $p->midtrans_snap_token !== $tokenAwal
                        || $p->external_id !== $orderIdAwal
                        || (float) $t->grand_total !== $nominalAwal
                    ) {
                        return [
                            'ok' => false,
                            'message' => 'Data lokal berubah saat status Midtrans diperiksa. Tidak ada status yang ditimpa.',
                        ];
                    }

                    app(StockReservationService::class)->release((int) $t->id);

                    $p->update([
                        'payment_status' => $statusLokalBaru,
                    ]);

                    $t->update([
                        'status' => $statusLokalBaru,
                        'payment_status' => $statusLokalBaru,
                    ]);

                    return [
                        'ok' => true,
                        'message' => $statusLokalBaru === 'cancelled'
                            ? 'Pembatalan QRIS telah dikonfirmasi Midtrans dan dicatat di SATAR.'
                            : 'Pembayaran telah kedaluwarsa di Midtrans. Status SATAR diperbarui.',
                    ];
                }, 3);

                if (!$hasil['ok']) {
                    return back()->with('error', $hasil['message']);
                }

                return redirect()
                    ->route('transaksi.show', $id)
                    ->with('success', $hasil['message']);

            } catch (\Throwable $e) {
                report($e);

                return back()->with(
                    'error',
                    'Pembatalan atau pemeriksaan status Midtrans mengalami kendala. Status SATAR belum dipastikan berubah. Periksa status pembayaran sebelum mencoba lagi.'
                );
            }
        }

        // Pertahankan alur pembatalan transaksi non-QRIS.
        if (
            $transaksi->payment_status === 'paid'
            || $transaksi->status === 'success'
        ) {
            return back()->with(
                'error',
                'Transaksi yang sudah lunas tidak bisa dibatalkan.'
            );
        }

        DB::transaction(function () use ($transaksi) {
            $transaksi->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
            ]);

            if ($transaksi->payment) {
                $transaksi->payment->update([
                    'payment_status' => 'cancelled',
                ]);
            }
        });

        return redirect()
            ->route('transaksi.show', $transaksi->id)
            ->with('success', 'Transaksi berhasil dibatalkan.');
    }
    public function expire($id): RedirectResponse
    {
        $user = auth()->user();

        $query = Transaction::with('payment');

        if ($user->role === 'kasir') {
            $query->where('user_id', $user->id);
        }

        $transaksi = $query->findOrFail($id);

        // Status kedaluwarsa QRIS harus mengikuti status dari Midtrans.
        if ($transaksi->payment_method === 'qris') {
            return back()->with(
                'error',
                'Kedaluwarsa QRIS sementara dinonaktifkan selama integrasi Midtrans.'
            );
        }

        if ($transaksi->payment_status === 'paid' || $transaksi->status === 'success') {
            return back()->with('error', 'Transaksi yang sudah lunas tidak bisa ditandai expired.');
        }

        DB::transaction(function () use ($transaksi) {
            $transaksi->update([
                'status' => 'expired',
                'payment_status' => 'expired',
            ]);

            if ($transaksi->payment) {
                $transaksi->payment->update([
                    'payment_status' => 'expired',
                ]);
            }
        });

        return redirect()
            ->route('transaksi.show', $transaksi->id)
            ->with('success', 'Transaksi berhasil ditandai expired.');
    }

    public function destroy($id): RedirectResponse
    {
        $user = auth()->user();

        $query = Transaction::with(['payment', 'details']);

        if ($user->role === 'kasir') {
            $query->where('user_id', $user->id);
        }

        $transaksi = $query->findOrFail($id);

        // Jangan hapus transaksi yang mungkin masih diproses Midtrans.
        if ($transaksi->payment_method === 'qris') {
            return back()->with(
                'error',
                'Penghapusan QRIS sementara dinonaktifkan selama integrasi Midtrans.'
            );
        }

        if ($transaksi->payment_status === 'paid' || $transaksi->status === 'success') {
            return back()->with('error', 'Transaksi yang sudah lunas tidak boleh dihapus.');
        }

        DB::transaction(function () use ($transaksi) {
            if ($transaksi->payment) {
                $transaksi->payment->delete();
            }

            $transaksi->details()->delete();
            $transaksi->delete();
        });

        return redirect()
            ->route('transaksi.index')
            ->with('success', 'Transaksi berhasil dihapus.');
    }
}