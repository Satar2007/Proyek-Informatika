@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Transaksi
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Riwayat Transaksi
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Daftar transaksi penjualan yang tercatat di sistem JIMNY COFFEE.
            </p>
        </div>

        <div class="rounded-2xl border border-[#D9B08C]/70 bg-white px-4 py-3 text-sm text-[#7B4B2A] shadow-sm">
            <span class="font-black text-[#4B2E1F]">
                {{ $transaksis->total() }}
            </span>
            transaksi
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Total Data
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#4B2E1F]">
                        {{ $transaksis->total() }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    📋
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Sukses
                    </p>
                    <p class="mt-2 text-3xl font-black text-emerald-600">
                        {{ $transactionStats['success'] }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700 ring-1 ring-emerald-100">
                    ✓
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Pending
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#C98A4A]">
                        {{ $transactionStats['pending'] }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FFF3E4] text-xl text-[#C98A4A] ring-1 ring-[#F2D6B5]">
                    …
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Gagal/Batal
                    </p>
                    <p class="mt-2 text-3xl font-black text-red-600">
                        {{ $transactionStats['failed'] }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-xl text-red-700 ring-1 ring-red-100">
                    ×
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="flex flex-col gap-3 border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-black text-[#4B2E1F]">
                    Daftar Transaksi
                </h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Data diurutkan berdasarkan transaksi terbaru.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px]">
                <thead>
                    <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        <th class="px-6 py-4">Kode</th>
                        <th class="px-6 py-4">Pelanggan</th>
                        <th class="px-6 py-4">Total</th>
                        <th class="px-6 py-4">Metode</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Tanggal</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#E8D8C7]">
                    @forelse($transaksis as $trx)
                        <tr class="transition hover:bg-[#FFFDF9]">
                            {{-- Kode --}}
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-mono text-sm font-black text-[#4B2E1F]">
                                        {{ $trx->kode_transaksi }}
                                    </p>
                                    <p class="mt-0.5 text-xs font-semibold text-[#7B4B2A]/70">
                                        ID: {{ $trx->id }}
                                    </p>
                                </div>
                            </td>

                            {{-- Pelanggan --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-sm font-black text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                        {{ strtoupper(substr($trx->nama_pelanggan ?? 'P', 0, 1)) }}
                                    </div>

                                    <div>
                                        <p class="font-black text-[#4B2E1F]">
                                            {{ $trx->nama_pelanggan ?? '-' }}
                                        </p>
                                        <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                            {{ $trx->user?->name ?? 'Kasir tidak diketahui' }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            {{-- Total --}}
                            <td class="px-6 py-4">
                                <span class="font-black text-[#4B2E1F]">
                                    Rp {{ number_format($trx->grand_total, 0, ',', '.') }}
                                </span>
                            </td>

                            {{-- Metode --}}
                            <td class="px-6 py-4">
                                @php
                                    $method = strtolower($trx->payment_method ?? '-');
                                @endphp

                                @if($method === 'cash')
                                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black uppercase text-emerald-700">
                                        Cash
                                    </span>
                                @elseif($method === 'qris')
                                    <span class="inline-flex rounded-full bg-[#F1E5D8] px-3 py-1 text-xs font-black uppercase text-[#7B4B2A]">
                                        QRIS
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-[#F8F5F0] px-3 py-1 text-xs font-black uppercase text-[#7B4B2A]">
                                        {{ $trx->payment_method ?? '-' }}
                                    </span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="px-6 py-4">
                                @if($trx->status === 'success')
                                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                        Sukses
                                    </span>
                                @elseif($trx->status === 'pending')
                                    <span class="inline-flex rounded-full bg-[#FFF3E4] px-3 py-1 text-xs font-black text-[#C98A4A]">
                                        Pending
                                    </span>
                                @elseif($trx->status === 'expired')
                                    <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                                        Expired
                                    </span>
                                @elseif($trx->status === 'cancelled')
                                    <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                                        Dibatalkan
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-[#F8F5F0] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                        {{ ucfirst($trx->status ?? '-') }}
                                    </span>
                                @endif

                                <div class="mt-1">
                                    @if($trx->payment_status === 'paid')
                                        <span class="text-xs font-black text-emerald-600">
                                            Paid
                                        </span>
                                    @elseif($trx->payment_status === 'unpaid')
                                        <span class="text-xs font-black text-[#C98A4A]">
                                            Unpaid
                                        </span>
                                    @elseif($trx->payment_status === 'waiting')
                                        <span class="text-xs font-black text-[#C98A4A]">
                                            Waiting
                                        </span>
                                    @elseif($trx->payment_status === 'expired')
                                        <span class="text-xs font-black text-red-600">
                                            Expired
                                        </span>
                                    @elseif($trx->payment_status === 'cancelled')
                                        <span class="text-xs font-black text-red-600">
                                            Cancelled
                                        </span>
                                    @elseif($trx->payment_status === 'failed')
                                        <span class="text-xs font-black text-red-600">
                                            Failed
                                        </span>
                                    @elseif($trx->payment_status)
                                        <span class="text-xs font-bold text-[#7B4B2A]/70">
                                            {{ ucfirst($trx->payment_status) }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Tanggal --}}
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-black text-[#4B2E1F]">
                                        {{ $trx->created_at->format('d/m/Y') }}
                                    </p>
                                    <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                        {{ $trx->created_at->format('H:i') }}
                                    </p>
                                </div>
                            </td>

                            {{-- Aksi --}}
                            <td class="px-6 py-4">
                                <div class="flex justify-end">
                                    <a href="{{ route('transaksi.show', $trx->id) }}"
                                        class="rounded-xl bg-[#7B4B2A] px-4 py-2 text-xs font-black text-white transition hover:bg-[#4B2E1F]">
                                        Detail
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-3xl bg-[#F8F5F0] text-2xl ring-1 ring-[#D9B08C]/60">
                                        📋
                                    </div>

                                    <p class="font-black text-[#4B2E1F]">
                                        Belum ada transaksi
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                        Data transaksi akan muncul setelah kasir melakukan checkout.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-[#E8D8C7] bg-white px-6 py-4">
            {{ $transaksis->links() }}
        </div>
    </div>
</div>
@endsection