@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ stokModal: false, stokMode: 'tambah', stokMenuId: null, stokMenuName: '' }"
    @open-stok-modal.window="stokModal = true; stokMode = $event.detail.mode; stokMenuId = $event.detail.id; stokMenuName = $event.detail.name">
    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Menu & Stok
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Kelola Menu
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Atur data menu, kategori, harga, stok, dan status menu JIMNY COFFEE.
            </p>
        </div>

        <a href="{{ route('admin.menu.create') }}"
            class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
            + Tambah Menu
        </a>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">
                Total Menu
            </p>
            <p class="mt-2 text-3xl font-black text-[#4B2E1F]">
                {{ $totalMenu }}
            </p>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">
                Menu Aktif
            </p>
            <p class="mt-2 text-3xl font-black text-emerald-600">
                {{ $menuAktif }}
            </p>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">
                Stok Menipis
            </p>
            <p class="mt-2 text-3xl font-black text-orange-600">
                {{ $stokRendah }}
            </p>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">
                Stok Habis
            </p>
            <p class="mt-2 text-3xl font-black text-red-600">
                {{ $stokHabis }}
            </p>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">

            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-black text-[#4B2E1F]">
                        Daftar Menu
                    </h2>

                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                        Cari dan kelola menu berdasarkan nama, kategori, harga, atau stok.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('admin.stok-log') }}"
                        class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-4 py-2 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                        Lihat Log Stok
                    </a>

                    <a
                        href="{{ route('admin.menu.price-history') }}"
                        class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-4 py-2 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                        Riwayat Harga
                    </a>
                </div>
            </div>


            {{-- SEARCH --}}
            <form
                method="GET"
                action="{{ route('admin.menu.index') }}"
                class="mt-4 flex w-full flex-col gap-2 md:flex-row md:items-center">

                @if($selectedCategory !== '')
                    <input
                        type="hidden"
                        name="category"
                        value="{{ $selectedCategory }}">
                @endif

                @if($selectedStockStatus !== '')
                    <input
                        type="hidden"
                        name="stock_status"
                        value="{{ $selectedStockStatus }}">
                @endif

                <div class="relative flex-1">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#9B8574]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.3-4.3"></path>
                    </svg>

                    <input
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Cari nama menu, kategori, stok, atau harga..."
                        autocomplete="off"
                        aria-label="Cari menu"
                        class="w-full rounded-2xl border border-[#D9B08C] bg-white py-3 pl-11 pr-4 text-sm font-semibold text-[#4B2E1F] outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:ring-4 focus:ring-[#D9B08C]/30">

                </div>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-6 py-3 text-sm font-black text-white transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                    Cari
                </button>

                @if(
                    $search !== '' ||
                    $selectedCategory !== '' ||
                    $selectedStockStatus !== ''
                )
                    <a
                        href="{{ route('admin.menu.index') }}"
                        class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] transition hover:bg-[#F8F5F0]">
                        Reset
                    </a>
                @endif
            </form>


            {{-- CATEGORY FILTER --}}
            <div
                class="mt-3 flex flex-wrap gap-2"
                aria-label="Filter kategori menu">

                <a
                    href="{{ route(
                        'admin.menu.index',
                        array_filter([
                            'q' => $search,
                            'stock_status' => $selectedStockStatus,
                        ])
                    ) }}"
                    class="rounded-full border px-4 py-2 text-xs font-black transition
                        {{ $selectedCategory === ''
                            ? 'border-[#4B2E1F] bg-[#4B2E1F] text-white shadow-sm'
                            : 'border-[#D9B08C] bg-white text-[#7B4B2A] hover:bg-[#F8F5F0]'
                        }}">
                    Semua
                </a>

                @foreach($categories as $category)
                    <a
                        href="{{ route(
                            'admin.menu.index',
                            array_filter([
                                'q' => $search,
                                'category' => $category->id,
                                'stock_status' => $selectedStockStatus,
                            ])
                        ) }}"
                        class="rounded-full border px-4 py-2 text-xs font-black transition
                            {{ (string) $selectedCategory === (string) $category->id
                                ? 'border-[#4B2E1F] bg-[#4B2E1F] text-white shadow-sm'
                                : 'border-[#D9B08C] bg-white text-[#7B4B2A] hover:bg-[#F8F5F0]'
                            }}">
                        {{ $category->nama_kategori }}
                    </a>
                @endforeach

            </div>

            {{-- STOCK STATUS FILTER --}}
            <div class="mt-4">
                <p class="mb-2 text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                    Status stok
                </p>

                <div class="flex flex-wrap gap-2" aria-label="Filter status stok">
                    @foreach([
                        '' => 'Semua',
                        'aman' => 'Aman',
                        'menipis' => 'Menipis',
                        'habis' => 'Habis',
                    ] as $stockStatus => $stockLabel)
                        <a
                            href="{{ route(
                                'admin.menu.index',
                                array_filter([
                                    'q' => $search,
                                    'category' => $selectedCategory,
                                    'stock_status' => $stockStatus,
                                ], fn ($value) => $value !== '')
                            ) }}"
                            class="rounded-full border px-4 py-2 text-xs font-black transition
                                {{ $selectedStockStatus === $stockStatus
                                    ? 'border-[#4B2E1F] bg-[#4B2E1F] text-white shadow-sm'
                                    : 'border-[#D9B08C] bg-white text-[#7B4B2A] hover:bg-[#F8F5F0]'
                                }}">
                            {{ $stockLabel }}
                        </a>
                    @endforeach
                </div>
            </div>


            @if(
                $search !== '' ||
                $selectedCategory !== '' ||
                $selectedStockStatus !== ''
            )
                <p class="mt-3 text-xs font-bold text-[#7B4B2A]/70">
                    Menampilkan {{ $menus->total() }} menu sesuai pencarian/filter.
                </p>
            @endif

        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px]">
                <thead>
                    <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        <th class="px-6 py-4">Menu</th>
                        <th class="px-6 py-4">Kategori</th>
                        <th class="px-6 py-4">Harga</th>
                        <th class="px-6 py-4">Stok</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#E8D8C7]">
                    @forelse($menus as $menu)
                        <tr class="transition hover:bg-[#FFFDF9]">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-lg text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                        <x-menu-photo :menu="$menu" />
                                    </div>

                                    <div>
                                        <p class="font-black text-[#4B2E1F]">
                                            {{ $menu->nama_menu }}
                                        </p>

                                        <p class="mt-0.5 line-clamp-1 max-w-xs text-xs font-semibold text-[#7B4B2A]/70">
                                            {{ $menu->deskripsi ?? 'Tidak ada deskripsi' }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex rounded-full border border-[#D9B08C]/70 bg-[#F1E5D8] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                    {{ $menu->category->nama_kategori ?? '-' }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <span class="font-black text-[#4B2E1F]">
                                    Rp {{ number_format($menu->harga, 0, ',', '.') }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                @if($menu->stok <= 0)
                                    <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                                        Habis
                                    </span>
                                @elseif($menu->stok <= $menu->minimum_stok)
                                    <span class="inline-flex rounded-full bg-orange-50 px-3 py-1 text-xs font-black text-orange-700">
                                        Menipis · {{ $menu->stok }}
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                        Aman · {{ $menu->stok }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                @if($menu->is_active)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-[#F8F5F0] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                        Nonaktif
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button type="button"
                                        @click="$dispatch('open-stok-modal', { mode: 'tambah', id: {{ $menu->id }}, name: @js($menu->nama_menu) })"
                                        class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black text-white transition hover:bg-emerald-700">
                                        + Stok
                                    </button>

                                    <button type="button"
                                        @click="$dispatch('open-stok-modal', { mode: 'kurangi', id: {{ $menu->id }}, name: @js($menu->nama_menu) })"
                                        class="rounded-xl bg-orange-600 px-3 py-2 text-xs font-black text-white transition hover:bg-orange-700">
                                        - Stok
                                    </button>

                                    <a href="{{ route('admin.menu.edit', $menu->id) }}"
                                        class="rounded-xl bg-[#7B4B2A] px-3 py-2 text-xs font-black text-white transition hover:bg-[#4B2E1F]">
                                        Edit
                                    </a>

                                    <form action="{{ route('admin.menu.destroy', $menu->id) }}"
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus menu ini?')">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit"
                                            class="rounded-xl bg-red-600 px-3 py-2 text-xs font-black text-white transition hover:bg-red-700">
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
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M4 3v6a3 3 0 0 0 6 0V3M7 3v19M20 3c-4 3-5 8 0 9v10M20 3v9"/></svg>
                                    </div>

                                    <p class="font-black text-[#4B2E1F]">
                                        Belum ada menu
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                        Tambahkan menu pertama untuk mulai menggunakan sistem kasir.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-[#E8D8C7] bg-white px-6 py-4">
            {{ $menus->links() }}
        </div>
    </div>

    {{-- Modal Tambah/Kurangi Stok --}}
    <div x-cloak x-show="stokModal" x-transition.opacity.duration.150ms
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        @click.self="stokModal = false">

        <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl">
            <h3 class="text-lg font-black text-[#4B2E1F]">
                <span x-show="stokMode === 'tambah'">Tambah Stok</span>
                <span x-show="stokMode === 'kurangi'">Kurangi Stok</span>
            </h3>

            <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75" x-text="stokMenuName"></p>

            <form method="POST" class="mt-4 space-y-4"
                :action="stokMode === 'tambah'
                    ? '{{ url('admin/menu') }}/' + stokMenuId + '/tambah-stok'
                    : '{{ url('admin/menu') }}/' + stokMenuId + '/kurangi-stok'">
                @csrf

                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Jumlah
                    </label>
                    <input type="number" name="qty" min="1" required
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">
                </div>

                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Catatan (opsional)
                    </label>
                    <input type="text" name="catatan" maxlength="255"
                        placeholder="Misal: restock dari supplier / rusak / kadaluarsa"
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="stokModal = false"
                        class="rounded-2xl border border-[#D9B08C] bg-white px-4 py-2 text-sm font-black text-[#7B4B2A] transition hover:bg-[#F8F5F0]">
                        Batal
                    </button>
                    <button type="submit"
                        class="rounded-2xl bg-[#7B4B2A] px-4 py-2 text-sm font-black text-white transition hover:bg-[#4B2E1F]">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
