@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Shift & Absensi
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Kelola Shift
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Kelola jadwal shift kasir, pantau rekap absensi, dan proses pengajuan izin.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.shift.rekap') }}"
                class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                Lihat Rekap
            </a>

            <a href="{{ route('admin.izin.index') }}"
                class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                Pengajuan Izin
            </a>
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Total Shift
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#4B2E1F]">
                        {{ $shifts->total() }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    📅
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Shift Aktif
                    </p>
                    <p class="mt-2 text-3xl font-black text-emerald-600">
                        {{ $shifts->where('status', 'aktif')->count() }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700 ring-1 ring-emerald-100">
                    ✓
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Jumlah Kasir
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#7B4B2A]">
                        {{ $kasirs->count() }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F1E5D8] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    👥
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[420px_1fr]">
        {{-- Form Generate Shift --}}
        <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5 xl:h-fit">
            <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
                <h2 class="text-lg font-black text-[#4B2E1F]">
                    Generate Shift
                </h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Buat jadwal shift otomatis berdasarkan rentang tanggal dan hari kerja.
                </p>
            </div>

            <form action="{{ route('admin.shift.store') }}" method="POST" class="p-6">
                @csrf

                <div class="space-y-5">
                    {{-- Kasir --}}
                    <div>
                        <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                            Kasir <span class="text-red-500">*</span>
                        </label>

                        <select name="user_id"
                            class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">
                            <option value="">Pilih Kasir</option>
                            @foreach($kasirs as $kasir)
                                <option value="{{ $kasir->id }}" {{ old('user_id') == $kasir->id ? 'selected' : '' }}>
                                    {{ $kasir->name }}
                                </option>
                            @endforeach
                        </select>

                        @error('user_id')
                            <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tanggal --}}
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                                Tanggal Mulai <span class="text-red-500">*</span>
                            </label>

                            <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}"
                                class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                            @error('tanggal_mulai')
                                <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                                Tanggal Akhir <span class="text-red-500">*</span>
                            </label>

                            <input type="date" name="tanggal_akhir" value="{{ old('tanggal_akhir') }}"
                                class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                            @error('tanggal_akhir')
                                <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Hari Kerja --}}
                    <div>
                        <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                            Hari Kerja <span class="text-red-500">*</span>
                        </label>

                        @php
                            $hariKerja = old('hari_kerja', ['1', '2', '3', '4', '5']);
                            $hariList = [
                                '1' => 'Senin',
                                '2' => 'Selasa',
                                '3' => 'Rabu',
                                '4' => 'Kamis',
                                '5' => 'Jumat',
                                '6' => 'Sabtu',
                                '0' => 'Minggu',
                            ];
                        @endphp

                        <div class="grid grid-cols-2 gap-2">
                            @foreach($hariList as $value => $label)
                                <label class="flex cursor-pointer items-center gap-2 rounded-2xl border border-[#D9B08C]/70 bg-[#FFFDF9] px-3 py-2 text-sm font-bold text-[#4B2E1F] transition hover:bg-[#F8F5F0]">
                                    <input type="checkbox" name="hari_kerja[]" value="{{ $value }}"
                                        {{ in_array($value, $hariKerja) ? 'checked' : '' }}
                                        class="rounded border-[#D9B08C] text-[#7B4B2A] focus:ring-[#D9B08C]">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>

                        @error('hari_kerja')
                            <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Jam --}}
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                                Jam Masuk <span class="text-red-500">*</span>
                            </label>

                            <input type="time" name="jam_masuk" value="{{ old('jam_masuk', '08:00') }}"
                                class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                            @error('jam_masuk')
                                <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                                Jam Keluar <span class="text-red-500">*</span>
                            </label>

                            <input type="time" name="jam_keluar" value="{{ old('jam_keluar', '16:00') }}"
                                class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                            @error('jam_keluar')
                                <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Catatan --}}
                    <div>
                        <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                            Catatan
                        </label>

                        <textarea name="catatan" rows="3" placeholder="Opsional"
                            class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">{{ old('catatan') }}</textarea>
                    </div>

                    <button type="submit"
                        class="w-full rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                        Generate Shift
                    </button>
                </div>
            </form>
        </div>

        {{-- Daftar Shift --}}
        <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex flex-col gap-3 border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-black text-[#4B2E1F]">
                        Daftar Shift
                    </h2>
                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                        Jadwal shift kasir yang sudah dibuat.
                    </p>
                </div>

                <span class="rounded-full bg-[#F1E5D8] px-4 py-2 text-xs font-black text-[#7B4B2A]">
                    {{ $shifts->total() }} shift
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px]">
                    <thead>
                        <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                            <th class="px-6 py-4">Kasir</th>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Jam</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Catatan</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-[#E8D8C7]">
                        @forelse($shifts as $shift)
                            <tr class="transition hover:bg-[#FFFDF9]">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-sm font-black text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                            {{ strtoupper(substr($shift->user?->name ?? 'K', 0, 1)) }}
                                        </div>

                                        <div>
                                            <p class="font-black text-[#4B2E1F]">
                                                {{ $shift->user?->name ?? '-' }}
                                            </p>
                                            <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                                Kasir
                                            </p>
                                        </div>
                                    </div>
                                </td>

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
                                    @if($shift->status === 'aktif')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                            Aktif
                                        </span>
                                    @elseif($shift->status === 'selesai')
                                        <span class="inline-flex rounded-full bg-[#FFF3E4] px-3 py-1 text-xs font-black text-[#C98A4A]">
                                            Selesai
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-[#F8F5F0] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                            Libur
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    <p class="max-w-xs truncate text-sm font-semibold text-[#7B4B2A]/75">
                                        {{ $shift->catatan ?? '-' }}
                                    </p>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex justify-end">
                                        <form action="{{ route('admin.shift.destroy', $shift->id) }}"
                                            method="POST"
                                            onsubmit="return confirm('Yakin hapus shift ini?')">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                class="rounded-xl bg-red-600 px-4 py-2 text-xs font-black text-white transition hover:bg-red-700">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="mx-auto max-w-sm">
                                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-3xl bg-[#F8F5F0] text-2xl ring-1 ring-[#D9B08C]/60">
                                            📅
                                        </div>

                                        <p class="font-black text-[#4B2E1F]">
                                            Belum ada shift
                                        </p>

                                        <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                            Generate jadwal shift untuk mulai mencatat absensi kasir.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#E8D8C7] bg-white px-6 py-4">
                {{ $shifts->links() }}
            </div>
        </div>
    </div>
</div>
@endsection