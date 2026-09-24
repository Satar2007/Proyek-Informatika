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
                Pengajuan Izin
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Kelola pengajuan izin kasir. Izin yang disetujui akan masuk ke rekap kehadiran sebagai status izin.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.shift.index') }}"
                class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                Kembali ke Shift
            </a>

            <a href="{{ route('admin.shift.rekap') }}"
                class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                Lihat Rekap
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Pending
                    </p>
                    <p class="mt-2 text-3xl font-black text-[#C98A4A]">
                        {{ $requests->where('status', 'pending')->count() }}
                    </p>
                </div>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FFF3E4] text-xl text-[#C98A4A] ring-1 ring-[#F2D6B5]">
                    …
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#7B4B2A]/75">
                        Disetujui
                    </p>
                    <p class="mt-2 text-3xl font-black text-emerald-600">
                        {{ $requests->where('status', 'approved')->count() }}
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
                        Ditolak
                    </p>
                    <p class="mt-2 text-3xl font-black text-red-600">
                        {{ $requests->where('status', 'rejected')->count() }}
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
                    Daftar Pengajuan Izin
                </h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Admin dapat menyetujui atau menolak pengajuan izin yang masih pending.
                </p>
            </div>

            <span class="rounded-full bg-[#F1E5D8] px-4 py-2 text-xs font-black text-[#7B4B2A]">
                {{ $requests->total() }} pengajuan
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px]">
                <thead>
                    <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        <th class="px-6 py-4">Kasir</th>
                        <th class="px-6 py-4">Tanggal Izin</th>
                        <th class="px-6 py-4">Alasan</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Diproses Oleh</th>
                        <th class="px-6 py-4">Tanggal Diproses</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#E8D8C7]">
                    @forelse($requests as $req)
                        <tr class="transition hover:bg-[#FFFDF9]">
                            {{-- Kasir --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-sm font-black text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                        {{ strtoupper(substr($req->user?->name ?? 'K', 0, 1)) }}
                                    </div>

                                    <div>
                                        <p class="font-black text-[#4B2E1F]">
                                            {{ $req->user?->name ?? '-' }}
                                        </p>
                                        <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                            Kasir
                                        </p>
                                    </div>
                                </div>
                            </td>

                            {{-- Tanggal --}}
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-black text-[#4B2E1F]">
                                        {{ $req->tanggal->format('d/m/Y') }}
                                    </p>
                                    <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                        {{ $req->tanggal->translatedFormat('l') }}
                                    </p>
                                </div>
                            </td>

                            {{-- Alasan --}}
                            <td class="px-6 py-4">
                                <p class="max-w-xs line-clamp-2 text-sm font-semibold text-[#7B4B2A]/75">
                                    {{ $req->alasan }}
                                </p>
                            </td>

                            {{-- Status --}}
                            <td class="px-6 py-4">
                                @if($req->status === 'pending')
                                    <span class="inline-flex rounded-full bg-[#FFF3E4] px-3 py-1 text-xs font-black text-[#C98A4A]">
                                        Pending
                                    </span>
                                @elseif($req->status === 'approved')
                                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                        Disetujui
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                                        Ditolak
                                    </span>
                                @endif
                            </td>

                            {{-- Diproses Oleh --}}
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-black text-[#4B2E1F]">
                                        {{ $req->approvedBy?->name ?? '-' }}
                                    </p>
                                    <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                        {{ $req->approvedBy?->role ?? '-' }}
                                    </p>
                                </div>
                            </td>

                            {{-- Tanggal Diproses --}}
                            <td class="px-6 py-4">
                                @if($req->approved_at)
                                    <div>
                                        <p class="font-black text-[#4B2E1F]">
                                            {{ $req->approved_at->format('d/m/Y') }}
                                        </p>
                                        <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                            {{ $req->approved_at->format('H:i') }}
                                        </p>
                                    </div>
                                @else
                                    <span class="text-sm font-semibold text-[#7B4B2A]/50">
                                        -
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-6 py-4">
                                <div class="flex justify-end">
                                    @if($req->status === 'pending')
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <form action="{{ route('admin.izin.approve', $req->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('Setujui izin ini?')">
                                                @csrf

                                                <button type="submit"
                                                    class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white transition hover:bg-emerald-700">
                                                    Setujui
                                                </button>
                                            </form>

                                            <form action="{{ route('admin.izin.reject', $req->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('Tolak izin ini?')">
                                                @csrf

                                                <button type="submit"
                                                    class="rounded-xl bg-red-600 px-4 py-2 text-xs font-black text-white transition hover:bg-red-700">
                                                    Tolak
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="rounded-full bg-[#F8F5F0] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                            Sudah diproses
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-3xl bg-[#F8F5F0] text-2xl ring-1 ring-[#D9B08C]/60">
                                        📝
                                    </div>

                                    <p class="font-black text-[#4B2E1F]">
                                        Belum ada pengajuan izin
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                        Pengajuan izin dari kasir akan muncul di halaman ini.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-[#E8D8C7] bg-white px-6 py-4">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection