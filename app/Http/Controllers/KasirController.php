<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Category;
use App\Services\TransactionService;
use App\Services\PaymentService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KasirController extends Controller
{
    protected TransactionService $transactionService;
    protected PaymentService $paymentService;
    protected StockService $stockService;

    public function __construct(
        TransactionService $transactionService,
        PaymentService $paymentService,
        StockService $stockService
    ) {
        $this->transactionService = $transactionService;
        $this->paymentService = $paymentService;
        $this->stockService = $stockService;
    }

    public function index(Request $request)
    {
        $categories = Category::all();

        $query = Menu::with('category')
            ->where('is_active', true);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->search) {
            $query->where('nama_menu', 'like', '%' . $request->search . '%');
        }

        $menus = $query->get();

        return view('kasir.index', compact('menus', 'categories'));
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'nama_pelanggan' => 'required|string|max:255',
            'cart'           => 'required|array',
            'cart.*.menu_id' => 'required|exists:menus,id',
            'cart.*.qty'     => 'required|integer|min:1',
            'metode'         => 'nullable|in:cash,qris',
            'uang_diterima'  => 'nullable|integer|min:0',
        ]);

        try {
            $result = DB::transaction(function () use ($request) {
                $metode = $request->metode ?? 'qris';

                $transaksi = $this->transactionService->buatTransaksi(
                    $request->cart,
                    auth()->id(),
                    $request->diskon ?? 0,
                    $metode,
                    $request->nama_pelanggan
                );

                $payment = $this->paymentService->buatPayment($transaksi, $metode);

                if ($metode === 'cash') {
                    $uangDiterima = (int) $request->uang_diterima;
                    $grandTotal = (int) $transaksi->grand_total;

                    if ($uangDiterima <= 0) {
                        throw new \Exception('Uang diterima wajib diisi untuk pembayaran cash.');
                    }

                    if ($uangDiterima < $grandTotal) {
                        throw new \Exception('Uang diterima tidak boleh kurang dari total pembayaran.');
                    }

                    $kembalian = $uangDiterima - $grandTotal;

                    $transaksi->load('details.menu');

                    foreach ($transaksi->details as $detail) {
                        $this->stockService->kurangiStok(
                            $detail->menu,
                            $detail->qty,
                            auth()->id(),
                            'Penjualan cash - Transaksi #' . $transaksi->id
                        );
                    }

                    $payment->update([
                        'payment_status' => 'paid',
                        'uang_diterima'  => $uangDiterima,
                        'kembalian'      => $kembalian,
                        'paid_at'        => now(),
                    ]);

                    $transaksi->update([
                        'status'         => 'success',
                        'payment_status' => 'paid',
                        'paid_at'        => now(),
                    ]);
                } else {
                    $payment->update([
                        'uang_diterima' => null,
                        'kembalian'     => null,
                    ]);
                }

                return [
                    'transaksi_id' => $transaksi->id,
                    'payment_id'   => $payment->id,
                    'grand_total'  => $transaksi->grand_total,
                    'uang_diterima'=> $payment->uang_diterima,
                    'kembalian'    => $payment->kembalian,
                ];
            });

            return response()->json([
                'success'       => true,
                'transaksi_id'  => $result['transaksi_id'],
                'payment_id'    => $result['payment_id'],
                'grand_total'   => $result['grand_total'],
                'uang_diterima' => $result['uang_diterima'],
                'kembalian'     => $result['kembalian'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}