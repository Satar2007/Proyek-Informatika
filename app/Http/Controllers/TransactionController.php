<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
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

        $transaksis = $query->paginate(20);

        return view('transaksi.index', compact('transaksis'));
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

        if ($transaksi->payment_status === 'paid' || $transaksi->status === 'success') {
            return back()->with('error', 'Transaksi yang sudah lunas tidak bisa dibatalkan.');
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