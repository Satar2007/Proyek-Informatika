@extends('layouts.app')

@section('content')
<div class="space-y-8">
    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Admin Panel
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Dashboard
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Ringkasan bisnis dan operasional JIMNY COFFEE hari ini.
            </p>
        </div>

        <div class="rounded-2xl border border-[#D9B08C]/70 bg-white px-4 py-3 text-sm text-[#7B4B2A] shadow-sm">
            <span class="font-black text-[#4B2E1F]">
                {{ now()->format('d M Y') }}
            </span>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        {{-- Total Transaksi --}}
        <div class="group rounded-3xl border border-[#D9B08C]/60 bg-white p-6 shadow-sm shadow-[#4B2E1F]/5 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-[#4B2E1F]/10">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Total Transaksi Sukses
                    </p>
                    <p class="mt-3 text-3xl font-black text-[#4B2E1F]">
                        {{ $totalTransaksi }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    📋
                </div>
            </div>

            <div class="mt-5 h-1.5 rounded-full bg-[#F1E5D8]">
                <div class="h-1.5 w-2/3 rounded-full bg-[#7B4B2A]"></div>
            </div>
        </div>

        {{-- Omzet Hari Ini --}}
        <div class="group rounded-3xl border border-[#D9B08C]/60 bg-white p-6 shadow-sm shadow-[#4B2E1F]/5 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-[#4B2E1F]/10">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Omzet Hari Ini
                    </p>
                    <p class="mt-3 text-3xl font-black text-[#7B4B2A]">
                        Rp {{ number_format($omzetHarian, 0, ',', '.') }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700 ring-1 ring-emerald-100">
                    💰
                </div>
            </div>

            <div class="mt-5 h-1.5 rounded-full bg-[#F1E5D8]">
                <div class="h-1.5 w-3/4 rounded-full bg-[#C98A4A]"></div>
            </div>
        </div>

        {{-- Omzet Bulan Ini --}}
        <div class="group rounded-3xl border border-[#D9B08C]/60 bg-white p-6 shadow-sm shadow-[#4B2E1F]/5 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-[#4B2E1F]/10">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Omzet Bulan Ini
                    </p>
                    <p class="mt-3 text-3xl font-black text-[#4B2E1F]">
                        Rp {{ number_format($omzetBulanan, 0, ',', '.') }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FFF3E4] text-xl text-[#C98A4A] ring-1 ring-[#F2D6B5]">
                    📈
                </div>
            </div>

            <div class="mt-5 h-1.5 rounded-full bg-[#F1E5D8]">
                <div class="h-1.5 w-4/5 rounded-full bg-[#C98A4A]"></div>
            </div>
        </div>

        {{-- Jumlah Kasir --}}
        <div class="group rounded-3xl border border-[#D9B08C]/60 bg-white p-6 shadow-sm shadow-[#4B2E1F]/5 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-[#4B2E1F]/10">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Jumlah Kasir
                    </p>
                    <p class="mt-3 text-3xl font-black text-[#7B4B2A]">
                        {{ $totalKasir }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#4B2E1F] ring-1 ring-[#D9B08C]/60">
                    👥
                </div>
            </div>

            <div class="mt-5 h-1.5 rounded-full bg-[#F1E5D8]">
                <div class="h-1.5 w-1/2 rounded-full bg-[#7B4B2A]"></div>
            </div>
        </div>
    </div>

    {{-- Content Grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Menu Terlaris --}}
        <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
                <div>
                    <h2 class="text-lg font-black text-[#4B2E1F]">
                        Menu Terlaris
                    </h2>
                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                        Menu dengan penjualan tertinggi.
                    </p>
                </div>

                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#FFF3E4] text-xl text-[#C98A4A] ring-1 ring-[#F2D6B5]">
                    🏆
                </div>
            </div>

            <div class="p-6">
                <div class="space-y-3">
                    @forelse($menuTerlaris as $index => $menu)
                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] px-4 py-3 transition hover:bg-[#F8F5F0]">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-sm font-black text-[#7B4B2A] shadow-sm ring-1 ring-[#D9B08C]/50">
                                    {{ $index + 1 }}
                                </div>

                                <div>
                                    <p class="font-black text-[#4B2E1F]">
                                        {{ $menu->nama_menu }}
                                    </p>
                                    <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                        Menu favorit pelanggan
                                    </p>
                                </div>
                            </div>

                            <span class="rounded-full bg-[#F1E5D8] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                {{ $menu->total_terjual }} terjual
                            </span>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-[#D9B08C] bg-[#FFFDF9] p-8 text-center">
                            <p class="text-sm font-bold text-[#7B4B2A]/70">
                                Belum ada data menu terlaris.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Stok Hampir Habis --}}
        <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
                <div>
                    <h2 class="text-lg font-black text-[#4B2E1F]">
                        Stok Hampir Habis
                    </h2>
                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                        Menu yang perlu segera dicek stoknya.
                    </p>
                </div>

                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-red-50 text-xl text-red-700 ring-1 ring-red-100">
                    ⚠️
                </div>
            </div>

            <div class="p-6">
                <div class="space-y-3">
                    @forelse($stokHampirHabis as $menu)
                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-red-100 bg-red-50/70 px-4 py-3">
                            <div>
                                <p class="font-black text-[#4B2E1F]">
                                    {{ $menu->nama_menu }}
                                </p>
                                <p class="text-xs font-semibold text-red-600">
                                    Stok berada di batas minimum.
                                </p>
                            </div>

                            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-black text-red-700">
                                Sisa {{ $menu->stok }}
                            </span>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
                            <p class="text-sm font-black text-emerald-700">
                                Semua stok masih aman.
                            </p>
                            <p class="mt-1 text-xs font-semibold text-emerald-600/80">
                                Tidak ada menu yang berada di bawah batas minimum.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection