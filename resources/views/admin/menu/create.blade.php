@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Menu & Stok
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Tambah Menu
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Tambahkan menu baru ke sistem kasir JIMNY COFFEE.
            </p>
        </div>

        <a href="{{ route('admin.menu.index') }}"
            class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
            Kembali
        </a>
    </div>

    {{-- Form Card --}}
    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
            <h2 class="text-lg font-black text-[#4B2E1F]">
                Informasi Menu
            </h2>
            <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                Lengkapi data menu, kategori, harga, stok, dan status aktif.
            </p>
        </div>

        <form action="{{ route('admin.menu.store') }}" method="POST" enctype="multipart/form-data" class="p-6">
            @csrf

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Nama Menu --}}
                <div class="lg:col-span-2">
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Nama Menu <span class="text-red-500">*</span>
                    </label>

                    <input type="text" name="nama_menu" value="{{ old('nama_menu') }}" placeholder="Contoh: Caramel Latte"
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                    @error('nama_menu')
                        <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Kategori --}}
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Kategori <span class="text-red-500">*</span>
                    </label>

                    <select name="category_id"
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">
                        <option value="">Pilih kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->nama_kategori }}
                            </option>
                        @endforeach
                    </select>

                    @error('category_id')
                        <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Minimum Stok --}}
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Minimum Stok
                    </label>

                    <input type="number" name="minimum_stok" value="{{ old('minimum_stok', 5) }}" min="0"
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                    @error('minimum_stok')
                        <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Harga --}}
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Harga <span class="text-red-500">*</span>
                    </label>

                    <div class="relative">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-black text-[#7B4B2A]/70">
                            Rp
                        </span>

                        <input type="number" name="harga" value="{{ old('harga') }}" min="0" placeholder="18000"
                            class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] py-3 pl-11 pr-4 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">
                    </div>

                    @error('harga')
                        <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Stok --}}
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Stok Awal <span class="text-red-500">*</span>
                    </label>

                    <input type="number" name="stok" value="{{ old('stok', 0) }}" min="0"
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                    @error('stok')
                        <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Deskripsi --}}
                <div class="lg:col-span-2">
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Deskripsi
                    </label>

                    <textarea name="deskripsi" rows="4" placeholder="Tuliskan deskripsi singkat menu..."
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">{{ old('deskripsi') }}</textarea>

                    @error('deskripsi')
                        <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Gambar --}}
                <div class="lg:col-span-2">
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Gambar Menu
                    </label>

                    <div class="rounded-3xl border border-dashed border-[#D9B08C] bg-[#FFFDF9] p-5">
                        <input type="file" name="gambar" accept="image/*"
                            class="block w-full cursor-pointer rounded-2xl border border-[#D9B08C] bg-white text-sm font-semibold text-[#7B4B2A] file:mr-4 file:border-0 file:bg-[#7B4B2A] file:px-4 file:py-3 file:text-sm file:font-black file:text-white hover:file:bg-[#4B2E1F]">

                        <p class="mt-3 text-xs font-semibold text-[#7B4B2A]/70">
                            Format disarankan: JPG, PNG, atau WEBP. Gambar bersifat opsional.
                        </p>
                    </div>

                    @error('gambar')
                        <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Status --}}
                <div class="lg:col-span-2">
                    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-3xl border border-[#D9B08C]/70 bg-[#FFFDF9] p-4 transition hover:bg-[#F8F5F0]">
                        <div>
                            <p class="font-black text-[#4B2E1F]">
                                Menu Aktif
                            </p>
                            <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                Jika aktif, menu akan tampil di halaman Kasir / POS.
                            </p>
                        </div>

                        <input type="checkbox" name="is_active" id="is_active" value="1"
                            {{ old('is_active', '1') ? 'checked' : '' }}
                            class="h-5 w-5 rounded border-[#D9B08C] text-[#7B4B2A] focus:ring-[#D9B08C]">
                    </label>
                </div>
            </div>

            {{-- Actions --}}
            <div class="mt-8 flex flex-col-reverse gap-3 border-t border-[#E8D8C7] pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.menu.index') }}"
                    class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-6 py-3 text-sm font-black text-[#7B4B2A] transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                    Batal
                </a>

                <button type="submit"
                    class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-6 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                    Simpan Menu
                </button>
            </div>
        </form>
    </div>
</div>
@endsection