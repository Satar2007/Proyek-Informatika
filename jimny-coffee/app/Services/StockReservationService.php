<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\StockLog;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockReservationService
{
    public function __construct(private StockService $stockService)
    {
    }

    // menus.stok is the quantity available to sell, excluding reservations.
    public function reserve(int $transactionId): void
    {
        DB::transaction(function () use ($transactionId) {
            $transaction = $this->pendingTransaction($transactionId);
            if ($transaction->stock_reservation_status === 'reserved') {
                return;
            }
            if ($transaction->stock_reservation_status !== 'none') {
                throw new RuntimeException('Status reservasi tidak dapat digunakan kembali.');
            }

            if ($transaction->user_id === null) {
                throw new RuntimeException('Akun pembuat transaksi tidak tersedia untuk reservasi baru.');
            }
            $details = $transaction->details()->orderBy('menu_id')->get();
            if ($details->isEmpty()) {
                throw new RuntimeException('Detail transaksi tidak tersedia.');
            }

            foreach ($details as $detail) {
                $menu = Menu::lockForUpdate()->findOrFail($detail->menu_id);
                if (!$menu->is_active || (int) $detail->qty <= 0) {
                    throw new RuntimeException('Menu atau jumlah reservasi tidak valid.');
                }
                $this->stockService->kurangiStok(
                    $menu,
                    (int) $detail->qty,
                    (int) $transaction->user_id,
                    'Reservasi QRIS - Transaksi #' . $transaction->id
                );
            }
            $transaction->update(['stock_reservation_status' => 'reserved']);
        });
    }

    // Caller must hold the payment lock and save paid status in the same DB transaction.
    public function complete(int $transactionId): void
    {
        DB::transaction(function () use ($transactionId) {
            $transaction = $this->pendingTransaction($transactionId);
            if ($transaction->stock_reservation_status === 'consumed') {
                return;
            }
            if ($transaction->stock_reservation_status !== 'reserved') {
                throw new RuntimeException('Pembayaran tidak memiliki reservasi stok yang aktif.');
            }
            // Stock was deducted during checkout; never deduct it again here.
            $transaction->update(['stock_reservation_status' => 'consumed']);
        });
    }

    // Call only after confirmed cancellation/failure, or before any Snap token exists.
    public function release(int $transactionId): void
    {
        DB::transaction(function () use ($transactionId) {
            $transaction = $this->pendingTransaction($transactionId);
            if ($transaction->stock_reservation_status === 'released') {
                return;
            }
            if ($transaction->stock_reservation_status === 'none') {
                // Legacy orders never reserved stock: do not invent a stock return.
                return;
            }
            if ($transaction->stock_reservation_status !== 'reserved') {
                throw new RuntimeException('Reservasi yang sudah digunakan tidak boleh dikembalikan.');
            }

            $details = $transaction->details()->orderBy('menu_id')->get();
            if ($details->isEmpty()) {
                throw new RuntimeException('Detail reservasi tidak tersedia.');
            }
            foreach ($details as $detail) {
                $menu = Menu::withTrashed()->lockForUpdate()->findOrFail($detail->menu_id);
                $qty = (int) $detail->qty;
                if ($qty <= 0) {
                    throw new RuntimeException('Jumlah pengembalian reservasi tidak valid.');
                }
                $before = (int) $menu->stok;
                $menu->stok = $before + $qty;
                $menu->save();
                StockLog::create([
                    'menu_id' => $menu->id,
                    'tipe' => 'in',
                    'qty_before' => $before,
                    'qty_change' => $qty,
                    'qty_after' => $menu->stok,
                    'catatan' => 'Pengembalian reservasi QRIS - Transaksi #' . $transaction->id,
                    'created_by' => $transaction->user_id,
                    'created_by_name' => $transaction->cashier_name,
                    'created_by_role' => $transaction->cashier_role,
                ]);
            }
            $transaction->update(['stock_reservation_status' => 'released']);
        });
    }

    private function pendingTransaction(int $id): Transaction
    {
        $transaction = Transaction::lockForUpdate()->findOrFail($id);
        if (
            $transaction->payment_method !== 'qris'
            || $transaction->status !== 'pending'
            || !in_array($transaction->payment_status, ['unpaid', 'waiting'], true)
        ) {
            throw new RuntimeException('Transaksi tidak lagi menunggu pembayaran QRIS.');
        }
        return $transaction;
    }
}
