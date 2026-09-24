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
                Riwayat Harga
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Riwayat setiap perubahan harga menu, dicatat otomatis setiap kali harga diubah lewat halaman Edit Menu.
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
                        Total Perubahan
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#4B2E1F]">
                        {{ $histories->total() }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    💰
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Kenaikan Harga
                    </p>
                    <p class="mt-2 text-3xl font-black text-emerald-600">
                        {{ $totalNaik }}
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
                        Penurunan Harga
                    </p>
                    <p class="mt-2 text-3xl font-black text-red-600">
                        {{ $totalTurun }}
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
                    Riwayat Perubahan Harga
                </h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Data perubahan harga diurutkan dari yang terbaru.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px]">
                <thead>
                    <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        <th class="px-6 py-4">Menu</th>
                        <th class="px-6 py-4">Harga Lama</th>
                        <th class="px-6 py-4">Harga Baru</th>
                        <th class="px-6 py-4">Perubahan</th>
                        <th class="px-6 py-4">Tanggal</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#E8D8C7]">
                    @forelse($histories as $history)
                        @php
                            $selisih = $history->harga_baru - $history->harga_lama;
                            $naik = $selisih > 0;
                            $turun = $selisih < 0;
                            $persen = $history->harga_lama > 0
                                ? abs($selisih) / $history->harga_lama * 100
                                : 0;
                        @endphp
                        <tr class="transition hover:bg-[#FFFDF9]">
                            {{-- Menu --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-lg text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                        ☕
                                    </div>

                                    <div>
                                        <p class="font-black text-[#4B2E1F]">
                                            {{ $history->menu?->nama_menu ?? 'Menu tidak ditemukan' }}
                                        </p>

                                        <p class="mt-0.5 text-xs font-semibold text-[#7B4B2A]/70">
                                            ID Menu: {{ $history->menu_id }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            {{-- Harga Lama --}}
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full bg-[#F8F5F0] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                    Rp {{ number_format($history->harga_lama, 0, ',', '.') }}
                                </span>
                            </td>

                            {{-- Harga Baru --}}
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full bg-[#4B2E1F] px-3 py-1 text-xs font-black text-white">
                                    Rp {{ number_format($history->harga_baru, 0, ',', '.') }}
                                </span>
                            </td>

                            {{-- Perubahan --}}
                            <td class="px-6 py-4">
                                @if($naik)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                        ↗ +Rp {{ number_format($selisih, 0, ',', '.') }} ({{ number_format($persen, 1) }}%)
                                    </span>
                                @elseif($turun)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                                        ↘ -Rp {{ number_format(abs($selisih), 0, ',', '.') }} ({{ number_format($persen, 1) }}%)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[#F1E5D8] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                        Tidak berubah
                                    </span>
                                @endif
                            </td>

                            {{-- Tanggal --}}
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-black text-[#4B2E1F]">
                                        {{ $history->tanggal_perubahan->format('d/m/Y') }}
                                    </p>
                                    <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                        {{ $history->tanggal_perubahan->format('H:i') }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-3xl bg-[#F8F5F0] text-2xl ring-1 ring-[#D9B08C]/60">
                                        💰
                                    </div>

                                    <p class="font-black text-[#4B2E1F]">
                                        Belum ada riwayat harga
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                        Riwayat akan muncul otomatis setelah harga menu diubah lewat halaman Edit Menu.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-[#E8D8C7] bg-white px-6 py-4">
            {{ $histories->links() }}
        </div>
    </div>
</div>
@endsection
