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
                Laporan Bulanan
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Ringkasan pendapatan dan jumlah transaksi berdasarkan bulan.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('laporan.harian') }}"
                class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                Laporan Harian
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

    {{-- Filter --}}
    <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
        <form action="{{ route('laporan.bulanan') }}" method="GET" class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="flex flex-col gap-4 md:flex-row">
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Bulan
                    </label>

                    <select name="bulan"
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40 md:w-48">
                        @for($i = 1; $i <= 12; $i++)
                            <option value="{{ $i }}" {{ (int) $bulan === $i ? 'selected' : '' }}>
                                {{ DateTime::createFromFormat('!m', $i)->format('F') }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Tahun
                    </label>

                    <select name="tahun"
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40 md:w-40">
                        @for($y = now()->year + 1; $y >= now()->year - 2; $y--)
                            <option value="{{ $y }}" {{ (int) $tahun === $y ? 'selected' : '' }}>
                                {{ $y }}
                            </option>
                        @endfor
                    </select>
                </div>
            </div>

            <button type="submit"
                class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-6 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                Filter Laporan
            </button>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Periode
                    </p>
                    <p class="mt-2 text-2xl font-black text-[#7B4B2A]">
                        {{ DateTime::createFromFormat('!m', $bulan)->format('F') }} {{ $tahun }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    📆
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Jumlah Transaksi
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#4B2E1F]">
                        {{ $harian->sum('jumlah') }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F1E5D8] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
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
                        Rp {{ number_format($totalBulan, 0, ',', '.') }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700 ring-1 ring-emerald-100">
                    💰
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Harian --}}
    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="flex flex-col gap-3 border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-black text-[#4B2E1F]">
                    Rekap Harian
                </h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Daftar pendapatan harian pada bulan {{ DateTime::createFromFormat('!m', $bulan)->format('F') }} {{ $tahun }}.
                </p>
            </div>

            <span class="rounded-full bg-[#F1E5D8] px-4 py-2 text-xs font-black text-[#7B4B2A]">
                {{ $harian->count() }} hari transaksi
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[750px]">
                <thead>
                    <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        <th class="px-6 py-4">Tanggal</th>
                        <th class="px-6 py-4">Jumlah Transaksi</th>
                        <th class="px-6 py-4 text-right">Total Pendapatan</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#E8D8C7]">
                    @forelse($harian as $h)
                        <tr class="transition hover:bg-[#FFFDF9]">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-sm font-black text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                        {{ \Carbon\Carbon::parse($h->tanggal)->format('d') }}
                                    </div>

                                    <div>
                                        <p class="font-black text-[#4B2E1F]">
                                            {{ \Carbon\Carbon::parse($h->tanggal)->format('d/m/Y') }}
                                        </p>
                                        <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                            {{ \Carbon\Carbon::parse($h->tanggal)->translatedFormat('l') }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full bg-[#F1E5D8] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                    {{ $h->jumlah }} transaksi
                                </span>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <span class="font-black text-emerald-600">
                                    Rp {{ number_format($h->total, 0, ',', '.') }}
                                </span>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('laporan.harian', ['tanggal' => $h->tanggal]) }}"
                                    class="inline-flex rounded-xl bg-[#7B4B2A] px-4 py-2 text-xs font-black text-white transition hover:bg-[#4B2E1F]">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-3xl bg-[#F8F5F0] text-2xl ring-1 ring-[#D9B08C]/60">
                                        📆
                                    </div>

                                    <p class="font-black text-[#4B2E1F]">
                                        Tidak ada transaksi
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                        Tidak ada transaksi pada bulan ini.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if($harian->count() > 0)
                    <tfoot>
                        <tr class="border-t border-[#E8D8C7] bg-[#F8F5F0]">
                            <td class="px-6 py-4 text-sm font-black text-[#4B2E1F]">
                                Total
                            </td>

                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-black text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                    {{ $harian->sum('jumlah') }} transaksi
                                </span>
                            </td>

                            <td class="px-6 py-4 text-right text-lg font-black text-emerald-600">
                                Rp {{ number_format($totalBulan, 0, ',', '.') }}
                            </td>

                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection