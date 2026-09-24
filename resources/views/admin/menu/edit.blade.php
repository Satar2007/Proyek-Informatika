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
                Edit Menu
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Perbarui data menu, harga, stok, gambar, dan status aktif menu.
            </p>
        </div>

        <a href="{{ route('admin.menu.index') }}"
            class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
            Kembali
        </a>
    </div>

    {{-- Current Menu Info --}}
    <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[#F8F5F0] text-2xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                @if($menu->gambar)
                    <img src="{{ asset('storage/'.$menu->gambar) }}" alt="{{ $menu->nama_menu }}" class="h-full w-full object-cover">
                @else
                    ☕
                @endif
            </div>

            <div>
                <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                    Menu yang diedit
                </p>
                <h2 class="text-lg font-black text-[#4B2E1F]">
                    {{ $menu->nama_menu }}
                </h2>
                <p class="text-sm font-semibold text-[#7B4B2A]/75">
                    {{ $menu->category->nama_kategori ?? 'Tanpa kategori' }} · Rp {{ number_format($menu->harga, 0, ',', '.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Form Card --}}
    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
            <h2 class="text-lg font-black text-[#4B2E1F]">
                Informasi Menu
            </h2>
            <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                Pastikan data menu sesuai sebelum menyimpan perubahan.
            </p>
        </div>

        <form action="{{ route('admin.menu.update', $menu->id) }}" method="POST" enctype="multipart/form-data" class="p-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Nama Menu --}}
                <div class="lg:col-span-2">
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Nama Menu <span class="text-red-500">*</span>
                    </label>

                    <input type="text" name="nama_menu" value="{{ old('nama_menu', $menu->nama_menu) }}" placeholder="Contoh: Caramel Latte"
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
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id', $menu->category_id) == $cat->id ? 'selected' : '' }}>
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

                    <input type="number" name="minimum_stok" value="{{ old('minimum_stok', $menu->minimum_stok) }}" min="0"
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

                        <input type="number" name="harga" value="{{ old('harga', $menu->harga) }}" min="0" placeholder="18000"
                            class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] py-3 pl-11 pr-4 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">
                    </div>

                    @error('harga')
                        <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Stok (read-only) --}}
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Stok Saat Ini
                    </label>

                    <input type="text" value="{{ $menu->stok }}" disabled
                        class="w-full cursor-not-allowed rounded-2xl border border-[#D9B08C] bg-[#F1E5D8] px-4 py-3 text-sm font-semibold text-[#7B4B2A]/70 outline-none">

                    <p class="mt-2 text-xs font-semibold text-[#7B4B2A]/70">
                        Stok tidak diubah dari form ini. Gunakan tombol "Tambah Stok" / "Kurangi Stok" di halaman
                        <a href="{{ route('admin.menu.index') }}" class="font-black underline">Kelola Menu</a>
                        supaya perubahannya tercatat di Log Stok.
                    </p>
                </div>

                {{-- Deskripsi --}}
                <div class="lg:col-span-2">
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Deskripsi
                    </label>

                    <textarea name="deskripsi" rows="4" placeholder="Tuliskan deskripsi singkat menu..."
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">{{ old('deskripsi', $menu->deskripsi) }}</textarea>

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
                        @if($menu->gambar)
                            <div class="mb-4 flex items-center gap-4 rounded-2xl border border-[#D9B08C]/70 bg-white p-3">
                                <img src="{{ asset('storage/'.$menu->gambar) }}" alt="{{ $menu->nama_menu }}" class="h-20 w-20 rounded-2xl object-cover">

                                <div>
                                    <p class="text-sm font-black text-[#4B2E1F]">
                                        Gambar saat ini
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-[#7B4B2A]/70">
                                        Upload gambar baru jika ingin mengganti gambar menu.
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="mb-4 rounded-2xl border border-[#D9B08C]/70 bg-white p-4 text-sm font-semibold text-[#7B4B2A]/75">
                                Belum ada gambar untuk menu ini.
                            </div>
                        @endif

                        <input type="file" name="gambar" accept="image/*"
                            class="block w-full cursor-pointer rounded-2xl border border-[#D9B08C] bg-white text-sm font-semibold text-[#7B4B2A] file:mr-4 file:border-0 file:bg-[#7B4B2A] file:px-4 file:py-3 file:text-sm file:font-black file:text-white hover:file:bg-[#4B2E1F]">

                        <p class="mt-3 text-xs font-semibold text-[#7B4B2A]/70">
                            Format disarankan: JPG, PNG, atau WEBP. Kosongkan jika tidak ingin mengganti gambar.
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
                            {{ old('is_active', $menu->is_active) ? 'checked' : '' }}
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
                    Update Menu
                </button>
            </div>
        </form>
    </div>
</div>
@endsection