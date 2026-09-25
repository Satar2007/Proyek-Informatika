@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold text-amber-900 dark:text-amber-400 mb-6">📋 Daftar Transaksi</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
        <table class="w-full">
            <thead class="bg-amber-900 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Kode</th>
                    <th class="px-4 py-3 text-left">Kasir</th>
                    <th class="px-4 py-3 text-left">Total</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Tanggal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($transaksis as $trx)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-4 py-3 font-mono text-sm">{{ $trx->kode_transaksi }}</td>
                    <td class="px-4 py-3">{{ $trx->cashier_display_name }}</td>
                    <td class="px-4 py-3 font-bold">Rp {{ number_format($trx->grand_total, 0, ',', '.') }}</td>
                    <td class="px-4 py-3">
                        @if($trx->status === 'success')
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">Sukses</span>
                        @elseif($trx->status === 'pending')
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs">Pending</span>
                        @else
                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">Batal</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $trx->created_at->format('d/m/Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada transaksi</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">
            {{ $transaksis->links() }}
        </div>
    </div>
</div>
@endsection
