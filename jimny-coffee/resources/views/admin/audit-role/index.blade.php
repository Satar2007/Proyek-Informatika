@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Keamanan & Akuntabilitas
            </div>
            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Audit Perubahan Role
            </h1>
            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Riwayat pembuatan akun dan perubahan hak akses. Halaman ini bersifat read-only dan hanya dapat diakses Admin.
            </p>
        </div>
        <a href="{{ route('admin.akun.index') }}"
            class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0]">
            Kembali ke Kelola Akun
        </a>
    </div>

    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="flex items-center justify-between border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
            <div>
                <h2 class="text-lg font-black text-[#4B2E1F]">Riwayat Aktivitas</h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    {{ $audits->total() }} catatan audit
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px]">
                <thead>
                    <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        <th class="px-5 py-4">Waktu</th>
                        <th class="px-5 py-4">Pelaku</th>
                        <th class="px-5 py-4">Target Akun</th>
                        <th class="px-5 py-4">Aktivitas</th>
                        <th class="px-5 py-4">Perubahan Role</th>
                        <th class="px-5 py-4">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8D8C7]">
                    @forelse ($audits as $audit)
                        <tr class="align-top transition hover:bg-[#FFFDF9]">
                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-[#4B2E1F]">
                                {{ $audit->created_at?->format('d M Y H:i:s') ?? '—' }}
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-black text-[#4B2E1F]">{{ $audit->actor_name }}</p>
                                <p class="text-xs text-[#7B4B2A]/80">{{ $audit->actor_email }}</p>
                                <p class="text-[11px] font-bold uppercase text-[#9B8574]">{{ $audit->actor_role ?: 'System' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-black text-[#4B2E1F]">{{ $audit->target_name }}</p>
                                <p class="text-xs text-[#7B4B2A]/80">{{ $audit->target_email }}</p>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-[#4B2E1F]">
                                @switch($audit->event)
                                    @case('account_created')
                                        Pembuatan akun
                                        @break
                                    @case('account_deleted')
                                        Penghapusan akun
                                        @break
                                    @default
                                        Perubahan role
                                @endswitch
                            </td>
                            <td class="px-5 py-4 text-sm font-bold text-[#4B2E1F]">
                                {{ $audit->old_role ? ucfirst($audit->old_role) : '—' }}
                                <span class="px-1 text-[#C98A4A]">→</span>
                                {{ $audit->new_role ? ucfirst($audit->new_role) : '—' }}
                            </td>
                            <td class="px-5 py-4 text-xs font-semibold text-[#7B4B2A]">
                                {{ $audit->ip_address ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-sm font-semibold text-[#7B4B2A]/70">
                                Belum ada catatan perubahan role.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-[#E8D8C7] px-5 py-4">
            {{ $audits->links() }}
        </div>
    </div>
</div>
@endsection
