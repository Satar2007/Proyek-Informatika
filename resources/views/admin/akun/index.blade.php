@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Manajemen User
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Kelola Akun
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Tambah, ubah, dan kelola akun pengguna sistem berdasarkan role admin, owner, dan kasir.
            </p>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">Total Akun</p>
            <p class="mt-2 text-3xl font-black text-[#4B2E1F]">{{ $users->count() }}</p>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">Admin</p>
            <p class="mt-2 text-3xl font-black text-[#7B4B2A]">{{ $users->where('role', 'admin')->count() }}</p>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">Owner</p>
            <p class="mt-2 text-3xl font-black text-[#C98A4A]">{{ $users->where('role', 'owner')->count() }}</p>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">Kasir</p>
            <p class="mt-2 text-3xl font-black text-emerald-600">{{ $users->where('role', 'kasir')->count() }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[420px_1fr]">
        {{-- Form Tambah Akun --}}
        <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5 xl:h-fit">
            <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
                <h2 class="text-lg font-black text-[#4B2E1F]">
                    Tambah Akun
                </h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Buat akun baru untuk admin, owner, atau kasir.
                </p>
            </div>

            <form action="{{ route('admin.akun.store') }}" method="POST" class="p-6">
                @csrf

                <div class="space-y-5">
                    <div>
                        <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                            Nama <span class="text-red-500">*</span>
                        </label>

                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Nama pengguna"
                            class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                        @error('name')
                            <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                            Email <span class="text-red-500">*</span>
                        </label>

                        <input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com"
                            class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                        @error('email')
                            <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                            Password <span class="text-red-500">*</span>
                        </label>

                        <input type="password" name="password" placeholder="Minimal 8 karakter"
                            class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                        @error('password')
                            <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                            Role <span class="text-red-500">*</span>
                        </label>

                        <select name="role"
                            class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">
                            <option value="kasir" {{ old('role') === 'kasir' ? 'selected' : '' }}>Kasir</option>
                            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="owner" {{ old('role') === 'owner' ? 'selected' : '' }}>Owner</option>
                        </select>

                        @error('role')
                            <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                        class="w-full rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                        Simpan Akun
                    </button>
                </div>
            </form>
        </div>

        {{-- Daftar Akun --}}
        <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
            <div class="flex flex-col gap-3 border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-black text-[#4B2E1F]">
                        Daftar Akun
                    </h2>
                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                        Data akun yang memiliki akses ke sistem.
                    </p>
                </div>

                <span class="rounded-full bg-[#F1E5D8] px-4 py-2 text-xs font-black text-[#7B4B2A]">
                    {{ $users->count() }} akun
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px]">
                    <thead>
                        <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-6 py-4">Email</th>
                            <th class="px-6 py-4">Role</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-[#E8D8C7]">
                        @forelse($users as $user)
                            <tr class="transition hover:bg-[#FFFDF9]" x-data="{ edit: false }">
                                {{-- Nama + Edit Form --}}
                                <td class="px-6 py-4 align-top">
                                    <div x-show="!edit" class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-sm font-black text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>

                                        <div>
                                            <p class="font-black text-[#4B2E1F]">
                                                {{ $user->name }}
                                            </p>
                                            @if($user->id === auth()->id())
                                                <p class="text-xs font-black text-[#C98A4A]">
                                                    Akun aktif
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    <form x-cloak x-show="edit" action="{{ route('admin.akun.update', $user->id) }}" method="POST" class="w-[420px] max-w-full rounded-2xl border border-[#D9B08C]/70 bg-[#FFFDF9] p-4">
                                        @csrf
                                        @method('PUT')

                                        <div class="space-y-3">
                                            <input type="text" name="name" value="{{ $user->name }}"
                                                class="w-full rounded-xl border border-[#D9B08C] bg-white px-3 py-2 text-sm font-semibold text-black outline-none focus:border-[#7B4B2A] focus:ring-4 focus:ring-[#D9B08C]/40">

                                            <input type="email" name="email" value="{{ $user->email }}"
                                                class="w-full rounded-xl border border-[#D9B08C] bg-white px-3 py-2 text-sm font-semibold text-black outline-none focus:border-[#7B4B2A] focus:ring-4 focus:ring-[#D9B08C]/40">

                                            <input type="password" name="password" placeholder="Password baru, kosongkan jika tidak diubah"
                                                class="w-full rounded-xl border border-[#D9B08C] bg-white px-3 py-2 text-sm font-semibold text-black outline-none placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:ring-4 focus:ring-[#D9B08C]/40">

                                            <select name="role"
                                                class="w-full rounded-xl border border-[#D9B08C] bg-white px-3 py-2 text-sm font-semibold text-black outline-none focus:border-[#7B4B2A] focus:ring-4 focus:ring-[#D9B08C]/40">
                                                <option value="kasir" {{ $user->role === 'kasir' ? 'selected' : '' }}>Kasir</option>
                                                <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                                <option value="owner" {{ $user->role === 'owner' ? 'selected' : '' }}>Owner</option>
                                            </select>

                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit"
                                                    class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white transition hover:bg-emerald-700">
                                                    Simpan
                                                </button>

                                                <button type="button" @click="edit = false"
                                                    class="rounded-xl border border-[#D9B08C] bg-white px-4 py-2 text-xs font-black text-[#7B4B2A] transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                                                    Batal
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </td>

                                {{-- Email --}}
                                <td class="px-6 py-4 align-top" x-show="!edit">
                                    <p class="text-sm font-semibold text-[#4B2E1F]">
                                        {{ $user->email }}
                                    </p>
                                </td>

                                {{-- Role --}}
                                <td class="px-6 py-4 align-top" x-show="!edit">
                                    @if($user->role === 'admin')
                                        <span class="inline-flex rounded-full bg-[#F1E5D8] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                            Admin
                                        </span>
                                    @elseif($user->role === 'owner')
                                        <span class="inline-flex rounded-full bg-[#FFF3E4] px-3 py-1 text-xs font-black text-[#C98A4A]">
                                            Owner
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                            Kasir
                                        </span>
                                    @endif
                                </td>

                                {{-- Aksi --}}
                                <td class="px-6 py-4 align-top" x-show="!edit">
                                    <div class="flex justify-end gap-2">
                                        <button @click="edit = true"
                                            class="rounded-xl bg-[#7B4B2A] px-4 py-2 text-xs font-black text-white transition hover:bg-[#4B2E1F]">
                                            Edit
                                        </button>

                                        @if($user->id !== auth()->id())
                                            <form action="{{ route('admin.akun.destroy', $user->id) }}" method="POST"
                                                onsubmit="return confirm('Yakin hapus akun ini?')">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                    class="rounded-xl bg-red-600 px-4 py-2 text-xs font-black text-white transition hover:bg-red-700">
                                                    Hapus
                                                </button>
                                            </form>
                                        @else
                                            <span class="rounded-xl bg-[#F8F5F0] px-4 py-2 text-xs font-black text-[#7B4B2A]/60">
                                                Terkunci
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="mx-auto max-w-sm">
                                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-3xl bg-[#F8F5F0] text-2xl ring-1 ring-[#D9B08C]/60">
                                            👥
                                        </div>

                                        <p class="font-black text-[#4B2E1F]">
                                            Belum ada akun
                                        </p>

                                        <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                            Tambahkan akun baru agar pengguna dapat mengakses sistem.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection