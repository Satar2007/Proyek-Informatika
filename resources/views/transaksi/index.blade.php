@extends('layouts.app')

@section('content')
@php
    $activeFilters = collect([
        $filters['q'] ?? null,
        $filters['status'] ?? null,
        $filters['metode'] ?? null,
        $filters['tanggal_mulai'] ?? null,
        $filters['tanggal_akhir'] ?? null,
    ])->filter(fn ($value) => filled($value))->count();
@endphp

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
                Cari transaksi, periksa status pembayaran, buka detail, dan cetak ulang struk dari satu halaman.
            </p>
        </div>

        <div class="rounded-2xl border border-[#D9B08C]/70 bg-white px-4 py-3 text-sm text-[#7B4B2A] shadow-sm">
            <span class="font-black text-[#4B2E1F]">
                {{ $transaksis->total() }}
            </span>
            hasil
            @if($activeFilters > 0)
                <span class="ml-1 text-xs font-black text-[#C98A4A]">
                    · {{ $activeFilters }} filter aktif
                </span>
            @endif
        </div>
    </div>

    {{-- Filter Operasional --}}
    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-black text-[#4B2E1F]">
                        Cari & Filter
                    </h2>
                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                        Pencarian mendukung kode transaksi, ID transaksi, dan nama pelanggan.
                    </p>
                </div>

                @if($activeFilters > 0)
                    <a href="{{ route('transaksi.index') }}"
                        class="inline-flex items-center justify-center rounded-xl border border-[#D9B08C] bg-white px-4 py-2 text-xs font-black text-[#7B4B2A] transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                        Reset Filter
                    </a>
                @endif
            </div>
        </div>

        <form method="GET" action="{{ route('transaksi.index') }}" class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2 xl:grid-cols-5">
            <div class="md:col-span-2 xl:col-span-1">
                <label for="q" class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]/75">
                    Pencarian
                </label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="Kode, ID, pelanggan..."
                    class="w-full rounded-2xl border border-[#D9B08C] bg-white px-4 py-3 text-sm font-bold text-[#4B2E1F] outline-none transition focus:border-[#7B4B2A] focus:ring-2 focus:ring-[#D9B08C]/40"
                >
            </div>

            <div>
                <label for="status" class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]/75">
                    Status
                </label>
                <select
                    id="status"
                    name="status"
                    class="w-full rounded-2xl border border-[#D9B08C] bg-white px-4 py-3 text-sm font-bold text-[#4B2E1F] outline-none transition focus:border-[#7B4B2A] focus:ring-2 focus:ring-[#D9B08C]/40"
                >
                    <option value="">Semua status</option>
                    <option value="success" @selected(($filters['status'] ?? '') === 'success')>Lunas</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                    <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>Dibatalkan</option>
                    <option value="expired" @selected(($filters['status'] ?? '') === 'expired')>Expired</option>
                </select>
            </div>

            <div>
                <label for="metode" class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]/75">
                    Pembayaran
                </label>
                <select
                    id="metode"
                    name="metode"
                    class="w-full rounded-2xl border border-[#D9B08C] bg-white px-4 py-3 text-sm font-bold text-[#4B2E1F] outline-none transition focus:border-[#7B4B2A] focus:ring-2 focus:ring-[#D9B08C]/40"
                >
                    <option value="">Semua metode</option>
                    <option value="cash" @selected(($filters['metode'] ?? '') === 'cash')>Tunai</option>
                    <option value="qris" @selected(($filters['metode'] ?? '') === 'qris')>QRIS</option>
                </select>
            </div>

            <div>
                <label for="tanggal_mulai" class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]/75">
                    Dari Tanggal
                </label>
                <input
                    id="tanggal_mulai"
                    name="tanggal_mulai"
                    type="date"
                    value="{{ $filters['tanggal_mulai'] ?? '' }}"
                    class="w-full rounded-2xl border border-[#D9B08C] bg-white px-4 py-3 text-sm font-bold text-[#4B2E1F] outline-none transition focus:border-[#7B4B2A] focus:ring-2 focus:ring-[#D9B08C]/40"
                >
            </div>

            <div>
                <label for="tanggal_akhir" class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]/75">
                    Sampai Tanggal
                </label>
                <input
                    id="tanggal_akhir"
                    name="tanggal_akhir"
                    type="date"
                    value="{{ $filters['tanggal_akhir'] ?? '' }}"
                    class="w-full rounded-2xl border border-[#D9B08C] bg-white px-4 py-3 text-sm font-bold text-[#4B2E1F] outline-none transition focus:border-[#7B4B2A] focus:ring-2 focus:ring-[#D9B08C]/40"
                >
            </div>

            <div class="md:col-span-2 xl:col-span-5 flex justify-end">
                <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-2xl bg-[#7B4B2A] px-6 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98] md:w-auto">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Hasil Filter
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#4B2E1F]">
                        {{ $transaksis->total() }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9zM8 11h8M8 15h8"/></svg>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Lunas
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
                        Batal / Expired
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
                    Data terbaru ditampilkan lebih dulu. Buka detail untuk melihat item dan informasi pembayaran lengkap.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px]">
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
                        @php
                            $method = strtolower($trx->payment_method ?? '-');
                            $canPrintReceipt = $trx->payment
                                && (($trx->payment_status === 'paid') || ($trx->status === 'success'))
                                && auth()->user()->role !== 'owner';
                        @endphp

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
                                            {{ $trx->nama_pelanggan ?? 'Umum' }}
                                        </p>
                                        <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                            {{ $trx->cashier_display_name }}
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
                                @if($method === 'cash')
                                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black uppercase text-emerald-700">
                                        Tunai
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
                                        Lunas
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
                                        <span class="text-xs font-black text-emerald-600">Paid</span>
                                    @elseif(in_array($trx->payment_status, ['unpaid', 'waiting'], true))
                                        <span class="text-xs font-black text-[#C98A4A]">
                                            {{ ucfirst($trx->payment_status) }}
                                        </span>
                                    @elseif(in_array($trx->payment_status, ['expired', 'cancelled', 'failed'], true))
                                        <span class="text-xs font-black text-red-600">
                                            {{ ucfirst($trx->payment_status) }}
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
                                <div class="flex justify-end gap-2">
                                    @if($canPrintReceipt)
                                        <a href="{{ route('payment.struk', $trx->payment->id) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="rounded-xl border border-[#D9B08C] bg-white px-4 py-2 text-xs font-black text-[#7B4B2A] transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                                            Struk
                                        </a>
                                    @endif

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
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9zM8 11h8M8 15h8"/></svg>
                                    </div>

                                    <p class="font-black text-[#4B2E1F]">
                                        Tidak ada transaksi yang cocok
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                        Ubah kata pencarian atau filter untuk melihat transaksi lainnya.
                                    </p>

                                    @if($activeFilters > 0)
                                        <a href="{{ route('transaksi.index') }}"
                                            class="mt-4 inline-flex rounded-xl border border-[#D9B08C] bg-white px-4 py-2 text-xs font-black text-[#7B4B2A] transition hover:bg-[#F8F5F0]">
                                            Reset Filter
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transaksis->hasPages())
            <div class="border-t border-[#E8D8C7] bg-white px-6 py-4">
                {{ $transaksis->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
