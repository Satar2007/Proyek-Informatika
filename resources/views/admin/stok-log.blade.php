@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Menu & Stok
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Log Stok
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Riwayat perubahan stok menu, baik stok masuk, stok keluar, maupun penyesuaian stok.
            </p>
        </div>

        <a href="{{ route('admin.menu.index') }}"
            class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
            Kembali ke Menu
        </a>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Total Log
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#4B2E1F]">
                        {{ $logs->total() }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    📦
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Stok Masuk
                    </p>
                    <p class="mt-2 text-3xl font-black text-emerald-600">
                        {{ $logs->where('tipe', 'in')->count() }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700 ring-1 ring-emerald-100">
                    ↗
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Stok Keluar
                    </p>
                    <p class="mt-2 text-3xl font-black text-red-600">
                        {{ $logs->where('tipe', 'out')->count() }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-xl text-red-700 ring-1 ring-red-100">
                    ↘
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="flex flex-col gap-3 border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-black text-[#4B2E1F]">
                    Riwayat Perubahan Stok
                </h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Data perubahan stok diurutkan dari yang terbaru.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px]">
                <thead>
                    <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        <th class="px-6 py-4">Menu</th>
                        <th class="px-6 py-4">Tipe</th>
                        <th class="px-6 py-4">Sebelum</th>
                        <th class="px-6 py-4">Perubahan</th>
                        <th class="px-6 py-4">Sesudah</th>
                        <th class="px-6 py-4">Oleh</th>
                        <th class="px-6 py-4">Tanggal</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#E8D8C7]">
                    @forelse($logs as $log)
                        <tr class="transition hover:bg-[#FFFDF9]">
                            {{-- Menu --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-lg text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                        ☕
                                    </div>

                                    <div>
                                        <p class="font-black text-[#4B2E1F]">
                                            {{ $log->menu?->nama_menu ?? 'Menu tidak ditemukan' }}
                                        </p>

                                        <p class="mt-0.5 text-xs font-semibold text-[#7B4B2A]/70">
                                            ID Menu: {{ $log->menu_id }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            {{-- Tipe --}}
                            <td class="px-6 py-4">
                                @if($log->tipe === 'in')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                        ↗ Masuk
                                    </span>
                                @elseif($log->tipe === 'out')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                                        ↘ Keluar
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[#F1E5D8] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                        ↔ Adjustment
                                    </span>
                                @endif
                            </td>

                            {{-- Sebelum --}}
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full bg-[#F8F5F0] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                    {{ $log->qty_before }}
                                </span>
                            </td>

                            {{-- Perubahan --}}
                            <td class="px-6 py-4">
                                @php
                                    $isIn = $log->tipe === 'in';
                                    $isOut = $log->tipe === 'out';
                                    $changeSign = $isIn ? '+' : ($isOut ? '-' : '±');
                                    $changeClass = $isIn
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : ($isOut
                                            ? 'bg-red-50 text-red-700'
                                            : 'bg-[#F1E5D8] text-[#7B4B2A]');
                                @endphp

                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-black {{ $changeClass }}">
                                    {{ $changeSign }}{{ $log->qty_change }}
                                </span>
                            </td>

                            {{-- Sesudah --}}
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full bg-[#4B2E1F] px-3 py-1 text-xs font-black text-white">
                                    {{ $log->qty_after }}
                                </span>
                            </td>

                            {{-- Oleh --}}
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-black text-[#4B2E1F]">
                                        {{ $log->creator_display_name }}
                                    </p>
                                    <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                        {{ $log->creator_display_role }}
                                    </p>
                                </div>
                            </td>

                            {{-- Tanggal --}}
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-black text-[#4B2E1F]">
                                        {{ $log->created_at->format('d/m/Y') }}
                                    </p>
                                    <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                        {{ $log->created_at->format('H:i') }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-3xl bg-[#F8F5F0] text-2xl ring-1 ring-[#D9B08C]/60">
                                        📦
                                    </div>

                                    <p class="font-black text-[#4B2E1F]">
                                        Belum ada log stok
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                        Riwayat perubahan stok akan muncul setelah ada penambahan stok atau transaksi berhasil.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-[#E8D8C7] bg-white px-6 py-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
