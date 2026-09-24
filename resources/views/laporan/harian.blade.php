@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Laporan
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Laporan Harian
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Ringkasan transaksi dan pendapatan berdasarkan tanggal.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('laporan.bulanan') }}"
                class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                Laporan Bulanan
            </a>

            @if(auth()->user()->role === 'admin')
                <a href="{{ route('admin.dashboard') }}"
                    class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                    Dashboard
                </a>
            @elseif(auth()->user()->role === 'owner')
                <a href="{{ route('owner.dashboard') }}"
                    class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                    Dashboard
                </a>
            @else
                <a href="{{ route('kasir.index') }}"
                    class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                    Kasir
                </a>
            @endif
        </div>
    </div>

    {{-- Filter Tanggal --}}
    <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
        <form action="{{ route('laporan.harian') }}" method="GET" class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                    Tanggal Laporan
                </label>

                <input type="date" name="tanggal" value="{{ $tanggal }}"
                    class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40 md:w-64">
            </div>

            <button type="submit"
                class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-6 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                Filter Laporan
            </button>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Total Transaksi
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#4B2E1F]">
                        {{ $totalTransaksi }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    📋
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Total Pendapatan
                    </p>
                    <p class="mt-2 text-3xl font-black text-emerald-600">
                        Rp {{ number_format($totalPendapatan, 0, ',', '.') }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700 ring-1 ring-emerald-100">
                    💰
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Cash
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#7B4B2A]">
                        Rp {{ number_format($totalCash, 0, ',', '.') }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F1E5D8] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    💵
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        QRIS
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#C98A4A]">
                        Rp {{ number_format($totalQris, 0, ',', '.') }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FFF3E4] text-xl text-[#C98A4A] ring-1 ring-[#F2D6B5]">
                    📱
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Transaksi --}}
    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="flex flex-col gap-3 border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-black text-[#4B2E1F]">
                    Detail Transaksi
                </h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Daftar transaksi sukses pada tanggal {{ \Carbon\Carbon::parse($tanggal)->format('d/m/Y') }}.
                </p>
            </div>

            <span class="rounded-full bg-[#F1E5D8] px-4 py-2 text-xs font-black text-[#7B4B2A]">
                {{ $transaksis->count() }} transaksi
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[950px]">
                <thead>
                    <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        <th class="px-6 py-4">Jam</th>
                        <th class="px-6 py-4">Kode</th>
                        <th class="px-6 py-4">Pelanggan</th>
                        <th class="px-6 py-4">Kasir</th>
                        <th class="px-6 py-4">Item</th>
                        <th class="px-6 py-4">Metode</th>
                        <th class="px-6 py-4 text-right">Total</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#E8D8C7]">
                    @forelse($transaksis as $trx)
                        <tr class="transition hover:bg-[#FFFDF9]">
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full bg-[#FFF3E4] px-3 py-1 text-xs font-black text-[#C98A4A]">
                                    {{ $trx->created_at->format('H:i') }}
                                </span>
                            </td>

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

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-sm font-black text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                        {{ strtoupper(substr($trx->nama_pelanggan ?? 'P', 0, 1)) }}
                                    </div>

                                    <p class="font-black text-[#4B2E1F]">
                                        {{ $trx->nama_pelanggan ?? 'Umum' }}
                                    </p>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <p class="font-black text-[#4B2E1F]">
                                    {{ $trx->user?->name ?? '-' }}
                                </p>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex max-w-md flex-wrap gap-1.5">
                                    @foreach($trx->details as $detail)
                                        <span class="inline-flex rounded-full bg-[#F8F5F0] px-3 py-1 text-xs font-bold text-[#7B4B2A]">
                                            {{ $detail->menu?->nama_menu ?? 'Menu dihapus' }} x{{ $detail->qty }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>

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

                            <td class="px-6 py-4 text-right">
                                <span class="font-black text-emerald-600">
                                    Rp {{ number_format($trx->grand_total, 0, ',', '.') }}
                                </span>
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
                                        Tidak ada transaksi
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                        Tidak ada transaksi sukses pada tanggal ini.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if($transaksis->count() > 0)
                    <tfoot>
                        <tr class="border-t border-[#E8D8C7] bg-[#F8F5F0]">
                            <td colspan="6" class="px-6 py-4 text-right text-sm font-black text-[#4B2E1F]">
                                Total Pendapatan
                            </td>
                            <td class="px-6 py-4 text-right text-lg font-black text-emerald-600">
                                Rp {{ number_format($totalPendapatan, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection