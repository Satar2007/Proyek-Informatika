@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Monitoring Owner
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Rekap Kehadiran Kasir
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Pantau kehadiran kasir berdasarkan hadir, terlambat, izin, alfa, dan jadwal shift.
            </p>
        </div>

        <a href="{{ route('owner.dashboard') }}"
            class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
            Kembali ke Dashboard
        </a>
    </div>

    {{-- Filter --}}
    <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
        <form action="{{ route('owner.rekap-kehadiran') }}" method="GET" class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
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
                Filter Rekap
            </button>
        </form>
    </div>

    {{-- Rekap per Kasir --}}
    <div class="space-y-6">
        @forelse($kasirs as $kasir)
            @php
                $totalShift = $kasir->shifts->count();

                $hadir = 0;
                $terlambat = 0;
                $izin = 0;
                $alfa = 0;
                $terjadwal = 0;

                foreach ($kasir->shifts as $shift) {
                    $attendance = $kasir->attendances->first(function ($attendance) use ($shift) {
                        return \Carbon\Carbon::parse($attendance->tanggal)->format('Y-m-d') === $shift->tanggal->format('Y-m-d');
                    });

                    if ($attendance && $attendance->status === 'hadir') {
                        $hadir++;
                    } elseif ($attendance && $attendance->status === 'terlambat') {
                        $terlambat++;
                    } elseif ($attendance && $attendance->status === 'izin') {
                        $izin++;
                    } elseif ($attendance && $attendance->status === 'tidak_hadir') {
                        $alfa++;
                    } elseif ($shift->tanggal->lt(today())) {
                        $alfa++;
                    } else {
                        $terjadwal++;
                    }
                }

                // Opsi A:
                // terlambat tetap dihitung hadir.
                $totalDihitung = max($totalShift - $izin - $terjadwal, 0);
                $persentase = $totalDihitung > 0 ? round((($hadir + $terlambat) / $totalDihitung) * 100, 2) : 0;
            @endphp

            <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
                {{-- Header Kasir --}}
                <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-lg font-black text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                {{ strtoupper(substr($kasir->name, 0, 1)) }}
                            </div>

                            <div>
                                <h2 class="text-xl font-black text-[#4B2E1F]">
                                    {{ $kasir->name }}
                                </h2>
                                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                    Rekap bulan {{ DateTime::createFromFormat('!m', $bulan)->format('F') }} {{ $tahun }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-[#F1E5D8] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                Total Shift: {{ $totalShift }}
                            </span>

                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                Hadir: {{ $hadir }}
                            </span>

                            <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-black text-orange-700">
                                Terlambat: {{ $terlambat }}
                            </span>

                            <span class="rounded-full bg-[#FFF3E4] px-3 py-1 text-xs font-black text-[#C98A4A]">
                                Izin: {{ $izin }}
                            </span>

                            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                                Alfa: {{ $alfa }}
                            </span>

                            <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-black text-cyan-700">
                                Terjadwal: {{ $terjadwal }}
                            </span>

                            <span class="rounded-full bg-[#4B2E1F] px-3 py-1 text-xs font-black text-white">
                                Kehadiran: {{ $persentase }}%
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Progress --}}
                <div class="border-b border-[#E8D8C7] bg-[#FFFDF9] px-6 py-5">
                    <div class="mb-3 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-black text-[#4B2E1F]">
                                Persentase Kehadiran
                            </p>
                            <p class="mt-1 text-xs font-semibold text-[#7B4B2A]/70">
                                Rumus: (Hadir + Terlambat) / (Total Shift - Izin - Terjadwal) × 100.
                            </p>
                        </div>

                        <p class="text-2xl font-black text-emerald-600">
                            {{ $persentase }}%
                        </p>
                    </div>

                    <div class="h-3 w-full overflow-hidden rounded-full bg-[#E8D8C7]">
                        <div class="h-3 rounded-full bg-emerald-500 transition-all"
                            style="width: {{ min($persentase, 100) }}%">
                        </div>
                    </div>

                    <p class="mt-3 text-xs font-semibold text-[#7B4B2A]/70">
                        Terlambat maksimal 30 menit tetap dihitung hadir. Lebih dari 30 menit tercatat alfa.
                    </p>
                </div>

                {{-- Detail Shift --}}
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[850px]">
                        <thead>
                            <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                                <th class="px-6 py-4">Tanggal</th>
                                <th class="px-6 py-4">Shift</th>
                                <th class="px-6 py-4">Clock In</th>
                                <th class="px-6 py-4">Clock Out</th>
                                <th class="px-6 py-4">Status</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-[#E8D8C7]">
                            @forelse($kasir->shifts->sortBy('tanggal') as $shift)
                                @php
                                    $attendance = $kasir->attendances->first(function ($attendance) use ($shift) {
                                        return \Carbon\Carbon::parse($attendance->tanggal)->format('Y-m-d') === $shift->tanggal->format('Y-m-d');
                                    });

                                    if ($attendance && $attendance->status === 'hadir') {
                                        $status = 'hadir';
                                    } elseif ($attendance && $attendance->status === 'terlambat') {
                                        $status = 'terlambat';
                                    } elseif ($attendance && $attendance->status === 'izin') {
                                        $status = 'izin';
                                    } elseif ($attendance && $attendance->status === 'tidak_hadir') {
                                        $status = 'alfa';
                                    } elseif ($shift->tanggal->lt(today())) {
                                        $status = 'alfa';
                                    } else {
                                        $status = 'terjadwal';
                                    }
                                @endphp

                                <tr class="transition hover:bg-[#FFFDF9]">
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-black text-[#4B2E1F]">
                                                {{ $shift->tanggal->format('d/m/Y') }}
                                            </p>
                                            <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                                {{ $shift->tanggal->translatedFormat('l') }}
                                            </p>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full bg-[#F1E5D8] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                            {{ \Carbon\Carbon::parse($shift->jam_masuk)->format('H:i') }}
                                            -
                                            {{ \Carbon\Carbon::parse($shift->jam_keluar)->format('H:i') }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="font-black text-[#4B2E1F]">
                                            {{ $attendance?->clock_in ? $attendance->clock_in->format('H:i:s') : '-' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="font-black text-[#4B2E1F]">
                                            {{ $attendance?->clock_out ? $attendance->clock_out->format('H:i:s') : '-' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        @if($status === 'hadir')
                                            <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                                Hadir
                                            </span>
                                        @elseif($status === 'terlambat')
                                            <span class="inline-flex rounded-full bg-orange-50 px-3 py-1 text-xs font-black text-orange-700">
                                                Terlambat
                                            </span>
                                        @elseif($status === 'izin')
                                            <span class="inline-flex rounded-full bg-[#FFF3E4] px-3 py-1 text-xs font-black text-[#C98A4A]">
                                                Izin
                                            </span>
                                        @elseif($status === 'alfa')
                                            <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                                                Alfa
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-cyan-50 px-3 py-1 text-xs font-black text-cyan-700">
                                                Terjadwal
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center">
                                        <p class="text-sm font-semibold text-[#7B4B2A]/70">
                                            Tidak ada jadwal shift pada bulan ini.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-10 text-center shadow-sm shadow-[#4B2E1F]/5">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-3xl bg-[#F8F5F0] text-2xl ring-1 ring-[#D9B08C]/60">
                    👥
                </div>

                <p class="font-black text-[#4B2E1F]">
                    Belum ada kasir terdaftar
                </p>

                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Data rekap kehadiran akan muncul setelah ada akun kasir dan jadwal shift.
                </p>
            </div>
        @endforelse
    </div>
</div>
@endsection