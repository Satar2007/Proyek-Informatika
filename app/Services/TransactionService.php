<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class TransactionService
{
    public function buatTransaksi(
        array $cart,
        int $userId,
        int $diskon = 0,
        string $metode = 'qris',
        string $namaPelanggan = 'Umum'
    ): Transaction {
        return DB::transaction(function () use ($cart, $userId, $diskon, $metode, $namaPelanggan) {
            if (empty($cart)) {
                throw new Exception('Keranjang masih kosong.');
            }

            if ($diskon < 0) {
                throw new Exception('Diskon tidak boleh kurang dari 0.');
            }

            if (!in_array($metode, ['cash', 'qris'], true)) {
                throw new Exception('Metode pembayaran tidak valid.');
            }

            // Serialize new checkout with account deletion before locking menu rows.
            \App\Models\User::whereKey($userId)->lockForUpdate()->firstOrFail();

            $total = 0;
            $validatedItems = [];
            $quantities = [];

            foreach ($cart as $item) {
                $menuId = filter_var(
                    $item['menu_id'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );
                $qty = filter_var(
                    $item['qty'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );
                if ($menuId === false || $qty === false) {
                    throw new Exception('Data item transaksi tidak valid.');
                }
                $previousQty = $quantities[$menuId] ?? 0;
                if ($qty > PHP_INT_MAX - $previousQty) {
                    throw new Exception('Jumlah item transaksi terlalu besar.');
                }
                $quantities[$menuId] = $previousQty + $qty;
            }

            ksort($quantities, SORT_NUMERIC);

            foreach ($quantities as $menuId => $qty) {
                $menu = Menu::lockForUpdate()->find($menuId);

                if (!$menu || !$menu->is_active) {
                    throw new Exception('Menu tidak tersedia.');
                }

                if ($menu->stok < $qty) {
                    throw new Exception("Stok {$menu->nama_menu} tidak mencukupi.");
                }

                $subtotal = $menu->harga * $qty;
                $total += $subtotal;

                $validatedItems[] = [
                    'menu'     => $menu,
                    'qty'      => $qty,
                    'harga'    => $menu->harga,
                    'subtotal' => $subtotal,
                ];
            }

            $pajak = (int) round($total * 0.03);

            if ($diskon > ($total + $pajak)) {
                throw new Exception('Diskon tidak boleh lebih besar dari total pembayaran.');
            }

            $grandTotal = $total + $pajak - $diskon;

            if ($metode === 'qris' && $grandTotal <= 0) {
                throw new Exception('Total pembayaran QRIS harus lebih besar dari nol.');
            }

            $transaksi = Transaction::create([
                'kode_transaksi' => $this->generateKodeTransaksi(),
                'user_id'        => $userId,
                'nama_pelanggan' => $namaPelanggan ?: 'Umum',
                'total'          => $total,
                'pajak'          => $pajak,
                'diskon'         => $diskon,
                'grand_total'    => $grandTotal,
                'status'         => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $metode,
            ]);

            foreach ($validatedItems as $item) {
                TransactionDetail::create([
                    'transaction_id' => $transaksi->id,
                    'menu_id'        => $item['menu']->id,
                    'qty'            => $item['qty'],
                    'harga'          => $item['harga'],
                    'subtotal'       => $item['subtotal'],
                ]);
            }

            if ($metode === 'qris') {
                app(StockReservationService::class)->reserve((int) $transaksi->id);
            }

            return $transaksi->refresh()->load('details.menu');
        });
    }

    private function generateKodeTransaksi(): string
    {
        do {
            $kode = 'TRX-' . strtoupper(Str::random(8));
        } while (Transaction::where('kode_transaksi', $kode)->exists());

        return $kode;
    }
}