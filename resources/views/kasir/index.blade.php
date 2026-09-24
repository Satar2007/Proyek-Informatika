@extends('layouts.app')

@section('content')
<div class="pos-workspace" x-data="kasirApp()" @open-presensi.window="openPresensi()">
    {{-- Konten POS --}}
    <div class="pos-content">

    <header class="pos-compact-heading">
        <div><h1>Kasir <span> / Pesanan baru</span></h1><p>Pilih menu untuk menambahkan pesanan.</p></div>
        <div class="pos-heading-actions"><span>{{ now()->format('d M Y') }}</span><button type="button" class="soft-button" @click="toggleFullscreen()" x-text="fullscreen ? 'Keluar layar penuh' : 'Layar penuh'"></button></div>
    </header>
    <dialog x-ref="presensiDialog" class="presensi-dialog" aria-labelledby="presensi-title" @click="if ($event.target === $refs.presensiDialog) $refs.presensiDialog.close()">
        <header><div><span class="eyebrow">AKTIVITAS KARYAWAN</span><h2 id="presensi-title">Presensi & izin</h2></div><button type="button" autofocus aria-label="Tutup presensi" class="soft-button" @click="$refs.presensiDialog.close()">Tutup ✕</button></header>

    <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                    🕒
                </div>

                <div>
                    <h2 class="text-lg font-black text-[#4B2E1F]">
                        Presensi Hari Ini
                    </h2>

                    <template x-if="shift">
                        <div class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                            <span>Shift:</span>
                            <strong class="text-[#4B2E1F]" x-text="formatJam(shift.jam_masuk)"></strong>
                            <span>-</span>
                            <strong class="text-[#4B2E1F]" x-text="formatJam(shift.jam_keluar)"></strong>
                        </div>
                    </template>

                    <template x-if="!shift">
                        <p class="mt-1 text-sm font-bold text-red-500">
                            Tidak ada jadwal shift hari ini.
                        </p>
                    </template>

                    <template x-if="attendance">
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-emerald-50 px-3 py-1 font-black text-emerald-700">
                                Status: <span x-text="statusLabel(attendance.status)"></span>
                            </span>

                            <span x-show="clockInTime" class="rounded-full bg-[#F1E5D8] px-3 py-1 font-black text-[#7B4B2A]">
                                Clock In: <span x-text="clockInTime"></span>
                            </span>

                            <span x-show="clockOutTime" class="rounded-full bg-[#FFF3E4] px-3 py-1 font-black text-[#C98A4A]">
                                Clock Out: <span x-text="clockOutTime"></span>
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button @click="$refs.presensiDialog.close(); clockIn()"
                    :disabled="!shift || clockInTime || attendance?.status === 'izin' || attendance?.status === 'tidak_hadir'"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-[#E8D8C7] disabled:text-[#7B4B2A]/50">
                    Clock In
                </button>

                <button @click="$refs.presensiDialog.close(); clockOut()"
                    :disabled="!clockInTime || clockOutTime || attendance?.status === 'izin' || attendance?.status === 'tidak_hadir'"
                    class="rounded-xl bg-[#C98A4A] px-4 py-2 text-sm font-black text-white shadow-sm transition hover:bg-[#7B4B2A] disabled:cursor-not-allowed disabled:bg-[#E8D8C7] disabled:text-[#7B4B2A]/50">
                    Clock Out
                </button>

                <button @click="$refs.presensiDialog.close(); ajukanIzin()"
                    :disabled="attendance?.status === 'hadir' || attendance?.status === 'terlambat' || attendance?.status === 'izin' || attendance?.status === 'tidak_hadir'"
                    class="rounded-xl bg-[#7B4B2A] px-4 py-2 text-sm font-black text-white shadow-sm transition hover:bg-[#4B2E1F] disabled:cursor-not-allowed disabled:bg-[#E8D8C7] disabled:text-[#7B4B2A]/50">
                    Ajukan Izin
                </button>
            </div>
        </div>
    </div>

    </dialog>
    <a class="mobile-cart-link" href="#order-cart">Lihat pesanan <span x-text="cart.reduce((n, item) => n + item.qty, 0)"></span> item ↓</a>
    {{-- POS Layout --}}
    <div class="pos-columns">

        {{-- Kiri: Menu --}}
        <div class="pos-catalog">
            {{-- Search + Category --}}
            <div class="catalog-toolbar rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <label for="menu-search" class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                            Cari Menu
                        </label>
                        <input id="menu-search" aria-label="Cari menu" type="search" x-model="search" placeholder="Cari espresso, latte, nasi..."
                            class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">
                    </div>

                    <div class="text-sm font-semibold text-[#7B4B2A]/75">
                        <span class="font-black text-[#4B2E1F]" x-text="filteredMenus.length"></span>
                        menu tersedia
                    </div>
                </div>

                {{-- Tab Kategori --}}
                <div class="category-tabs">
                    <button @click="selectedCategory = ''" :aria-pressed="selectedCategory === ''"
                        :class="selectedCategory === '' ? 'bg-[#4B2E1F] text-white' : 'border border-[#D9B08C] bg-white text-[#7B4B2A] hover:bg-[#F8F5F0]'"
                        class="rounded-full px-4 py-2 text-xs font-black transition">
                        Semua
                    </button>

                    @foreach($categories as $cat)
                        @php
                            $catName = $cat->nama_kategori;
                            $catClass = match($catName) {
                                'Coffee' => 'border-[#D9B08C] bg-[#F1E5D8] text-[#7B4B2A] hover:bg-[#E8D8C7]',
                                'Coffee Flavoured' => 'border-orange-200 bg-orange-50 text-orange-800 hover:bg-orange-100',
                                'Milk Base' => 'border-sky-200 bg-sky-50 text-sky-800 hover:bg-sky-100',
                                'Non Coffee' => 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100',
                                'Tea' => 'border-teal-200 bg-teal-50 text-teal-800 hover:bg-teal-100',
                                'Food' => 'border-red-200 bg-red-50 text-red-800 hover:bg-red-100',
                                'Snack' => 'border-yellow-200 bg-yellow-50 text-yellow-800 hover:bg-yellow-100',
                                'Ice Cream' => 'border-pink-200 bg-pink-50 text-pink-800 hover:bg-pink-100',
                                default => 'border-[#D9B08C] bg-[#FFFDF9] text-[#7B4B2A] hover:bg-[#F8F5F0]',
                            };
                        @endphp

                        <button @click="selectedCategory = '{{ $cat->id }}'" :aria-pressed="selectedCategory === '{{ $cat->id }}'"
                            :class="selectedCategory === '{{ $cat->id }}' ? 'ring-2 ring-[#7B4B2A] scale-[1.02]' : ''"
                            class="rounded-full border px-4 py-2 text-xs font-black transition {{ $catClass }}">
                            {{ $cat->nama_kategori }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Menu Cards --}}
            <div class="product-grid" x-ref="productGrid" :style="{ '--catalog-cols': gridColumns, '--catalog-rows': gridRows }">
                @foreach($menus as $menu)
                    @php
                        $categoryName = $menu->category->nama_kategori ?? '';

                        $cardClass = match ($categoryName) {
                            'Milk Base' => 'product-blue',
                            'Non Coffee', 'Tea' => 'product-sage',
                            'Food', 'Snack' => 'product-sand',
                            'Ice Cream' => 'product-lilac',
                            default => 'product-cream',
                        };
                        $accentClass = 'product-badge';
                        $cursor = $menu->stok <= 0 ? 'product-sold-out' : '';
                    @endphp

                    <button type="button" data-product-id="{{ $menu->id }}" class="product-card {{ $cardClass }} {{ $cursor }}"
                        :class="{ 'just-added': recentItem === {{ $menu->id }} }"
                        @disabled($menu->stok <= 0)
                        title="{{ $menu->nama_menu }}{{ $menu->deskripsi ? ' · ' . $menu->deskripsi : '' }}"
                        aria-label="{{ $menu->nama_menu }}, Rp {{ number_format($menu->harga, 0, ',', '.') }}, {{ $menu->stok > 0 ? 'tambahkan ke pesanan' : 'stok habis' }}"
                        x-show="visibleMenuIds.includes({{ $menu->id }})"
                        @click="tambahCart({{ $menu->id }}, {{ Illuminate\Support\Js::from($menu->nama_menu) }}, {{ $menu->harga }}, {{ $menu->stok }}, $event.currentTarget)">
                        <div class="product-meta"><span class="product-badge">{{ $categoryName ?: 'Menu' }}</span><span class="stock-badge {{ $menu->stok <= $menu->minimum_stok ? 'stock-low' : '' }}">{{ $menu->stok > 0 ? $menu->stok : 'Habis' }}</span></div>
                        <span class="product-name">{{ $menu->nama_menu }}</span>
                        <span class="product-bottom"><strong>Rp {{ number_format($menu->harga, 0, ',', '.') }}</strong><span class="product-plus" aria-hidden="true" x-text="recentItem === {{ $menu->id }} ? '✓' : '+'">+</span></span>
                    </button>
                @endforeach
            </div>
            <div class="empty-state panel" x-show="!hasVisibleMenus()" x-cloak>
                <strong>Menu tidak ditemukan</strong><p>Coba kata kunci lain atau pilih semua kategori.</p>
                <button class="soft-button" @click="search = ''; selectedCategory = ''">Reset pencarian</button>
            </div>
            <div class="catalog-pager" aria-label="Halaman menu">
                <span aria-live="polite" x-text="filteredMenus.length ? (pageStart + 1) + '–' + Math.min(pageStart + pageSize, filteredMenus.length) + ' dari ' + filteredMenus.length + ' menu' : '0 menu'"></span>
                <div x-show="pageCount > 1" x-cloak><button type="button" @click="page--" :disabled="page <= 1" aria-label="Halaman menu sebelumnya">←</button><span x-text="page + ' / ' + pageCount"></span><button type="button" @click="page++" :disabled="page >= pageCount" aria-label="Halaman menu berikutnya">→</button></div>
            </div>
        </div>

        {{-- Kanan: Cart --}}
        <div id="order-cart" class="order-cart rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5 xl:sticky xl:top-24 xl:h-fit">
            <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-5 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-black text-[#4B2E1F]">
                            Pesanan
                        </h2>
                        <p class="text-xs font-semibold text-[#7B4B2A]/75">
                            Keranjang transaksi pelanggan
                        </p>
                    </div>

                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#F8F5F0] text-xl text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                        🛒
                    </div>
                </div>
            </div>

            <div class="cart-body p-5">
                {{-- Nama Pelanggan --}}
                <div class="mb-4">
                    <label class="mb-2 block text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        Nama Pelanggan <span class="text-red-500">*</span>
                    </label>

                    <input type="text" x-model="namaPelanggan" placeholder="Masukkan nama pelanggan..."
                        class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">
                </div>

                {{-- Cart Items --}}
                <div class="cart-items mb-4 space-y-2 overflow-y-auto pr-1">
                    <template x-if="cart.length === 0">
                        <div class="rounded-2xl border border-dashed border-[#D9B08C] bg-[#FFFDF9] p-8 text-center">
                            <p class="text-sm font-bold text-[#7B4B2A]/70">
                                Belum ada pesanan
                            </p>
                            <p class="mt-1 text-xs font-semibold text-[#7B4B2A]/60">
                                Klik menu untuk menambahkan item.
                            </p>
                        </div>
                    </template>

                    <template x-for="item in cart" :key="item.id">
                        <div class="cart-line rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] p-3" :class="{ 'just-added': recentItem === item.id }">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-[#4B2E1F]" x-text="item.nama"></p>
                                    <p class="text-xs font-black text-[#7B4B2A]" x-text="'Rp ' + formatRupiah(item.harga)"></p>
                                </div>

                                <button @click="konfirmasiHapusItem(item)" class="rounded-lg px-2 py-1 text-xs font-black text-red-500 transition hover:bg-red-50 hover:text-red-700">
                                    ✕
                                </button>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <button :aria-label="'Kurangi jumlah ' + item.nama" @click="kurangiQty(item.id)" class="flex h-8 w-8 items-center justify-center rounded-xl bg-white text-sm font-black text-[#7B4B2A] shadow-sm ring-1 ring-[#D9B08C]/60 transition hover:bg-[#F8F5F0]">
                                        -
                                    </button>

                                    <span class="w-7 text-center text-sm font-black text-[#4B2E1F]" x-text="item.qty"></span>

                                    <button :aria-label="'Tambah jumlah ' + item.nama" @click="tambahQty(item.id)" class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#7B4B2A] text-sm font-black text-white shadow-sm transition hover:bg-[#4B2E1F]">
                                        +
                                    </button>
                                </div>

                                <p class="text-sm font-black text-[#4B2E1F]" x-text="'Rp ' + formatRupiah(item.harga * item.qty)"></p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Total --}}
                <div data-motion-total class="cart-totals mb-4 rounded-2xl border border-[#D9B08C]/70 bg-[#FFFDF9] p-4">
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm font-semibold text-[#7B4B2A]/75">
                            <span>Subtotal</span>
                            <span class="font-black text-[#4B2E1F]" x-text="'Rp ' + formatRupiah(subtotal)"></span>
                        </div>

                        <div class="flex justify-between text-sm font-semibold text-[#7B4B2A]/75">
                            <span>Pajak (3%)</span>
                            <span class="font-black text-[#4B2E1F]" x-text="'Rp ' + formatRupiah(pajak)"></span>
                        </div>

                        <div class="border-t border-[#E8D8C7] pt-3">
                            <div class="flex items-end justify-between">
                                <span class="text-sm font-black text-[#4B2E1F]">Total</span>
                                <span class="text-2xl font-black text-[#7B4B2A]" x-text="'Rp ' + formatRupiah(grandTotal)"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tombol Bayar --}}
                <div class="grid grid-cols-2 gap-3">
                    <button @click="checkout('cash')" :disabled="cart.length === 0"
                        class="rounded-2xl bg-emerald-600 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-[#E8D8C7] disabled:text-[#7B4B2A]/50">
                        Cash
                    </button>

                    <button @click="checkout('qris')" :disabled="cart.length === 0"
                        class="rounded-2xl bg-[#7B4B2A] py-3 text-sm font-black text-white shadow-sm transition hover:bg-[#4B2E1F] disabled:cursor-not-allowed disabled:bg-[#E8D8C7] disabled:text-[#7B4B2A]/50">
                        QRIS
                    </button>
                </div>

                <button @click="konfirmasiHapusSemuaPesanan()" x-show="cart.length > 0"
                    class="mt-3 w-full rounded-2xl border border-red-200 bg-red-50 py-2 text-xs font-black text-red-600 transition hover:bg-red-100">
                    Hapus Semua Pesanan
                </button>
            </div>
        </div>
    </div>
    </div>
    <div class="pos-toast" role="status" aria-live="polite" x-show="notice" x-cloak x-text="notice"></div>
</div>
@endsection

@push('scripts')
<script>
    function kasirApp() {
        return {
            menuIndex: {{ Illuminate\Support\Js::from($menus->map(fn ($m) => ['id' => $m->id, 'name' => mb_strtolower($m->nama_menu), 'category' => (string) $m->category_id])->values()) }},
            page: 1, pageSize: 42, gridColumns: 7, gridRows: 6,
            recentItem: null, notice: '', feedbackTimer: null,
            resizeObserver: null, layoutFrame: null, fullscreen: false, fullscreenListener: null,
            get filteredMenus() {
                return this.menuIndex.filter(m => (!this.selectedCategory || m.category === this.selectedCategory) && m.name.includes(this.search.toLowerCase().trim()));
            },
            get pageCount() { return Math.max(1, Math.ceil(this.filteredMenus.length / this.pageSize)); },
            get pageStart() { return (this.page - 1) * this.pageSize; },
            get visibleMenuIds() { return this.filteredMenus.slice(this.pageStart, this.pageStart + this.pageSize).map(m => m.id); },
            hasVisibleMenus() { return this.filteredMenus.length > 0; },
            fitCatalog() {
                const grid = this.$refs.productGrid;
                if (!grid) return;
                const desktop = window.matchMedia('(min-width: 1024px)').matches;
                this.gridColumns = desktop ? Math.max(1, Math.floor((grid.clientWidth + 8) / 148)) : (grid.clientWidth > 550 ? 4 : 2);
                this.gridRows = desktop ? Math.max(1, Math.floor((grid.clientHeight + 8) / 108)) : 6;
                this.pageSize = this.gridColumns * this.gridRows;
                this.page = Math.min(this.page, this.pageCount);
            },
            feedback(id, message) {
                clearTimeout(this.feedbackTimer);
                this.recentItem = id;
                this.notice = message;
                this.feedbackTimer = setTimeout(() => { this.recentItem = null; this.notice = ''; }, 1400);
            },
            openPresensi() {
                if (!this.$refs.presensiDialog.open) this.$refs.presensiDialog.showModal();
                this.loadAttendanceStatus();
            },
            async toggleFullscreen() {
                try {
                    if (document.fullscreenElement) await document.exitFullscreen();
                    else await document.documentElement.requestFullscreen();
                } catch (e) { this.feedback(null, 'Layar penuh tidak tersedia di browser ini.'); }
            },
            destroy() {
                this.resizeObserver?.disconnect();
                cancelAnimationFrame(this.layoutFrame);
                clearTimeout(this.feedbackTimer);
                document.removeEventListener('fullscreenchange', this.fullscreenListener);
            },
            search: '', 
            selectedCategory: '',
            namaPelanggan: '',
            cart: [],

            shift: null,
            attendance: null,
            clockInTime: null,
            clockOutTime: null,

            async init() {
                this.$watch('search', () => { this.page = 1; });
                this.$watch('selectedCategory', () => { this.page = 1; });
                this.fullscreenListener = () => { this.fullscreen = !!document.fullscreenElement; };
                document.addEventListener('fullscreenchange', this.fullscreenListener);
                this.$nextTick(() => {
                    this.fitCatalog();
                    this.resizeObserver = new ResizeObserver(() => {
                        cancelAnimationFrame(this.layoutFrame);
                        this.layoutFrame = requestAnimationFrame(() => this.fitCatalog());
                    });
                    this.resizeObserver.observe(this.$refs.productGrid);
                    if (new URLSearchParams(window.location.search).get('presensi') === '1') this.openPresensi();
                });
                await this.loadAttendanceStatus();
            },

            async loadAttendanceStatus() {
                try {
                    const response = await fetch('{{ route("attendance.status") }}');
                    const data = await response.json();

                    this.shift = data.shift;
                    this.attendance = data.attendance;
                    this.clockInTime = data.clock_in;
                    this.clockOutTime = data.clock_out;
                } catch (e) {
                    console.error(e);
                }
            },

            statusLabel(status) {
                const labels = {
                    hadir: 'Hadir',
                    terlambat: 'Terlambat',
                    izin: 'Izin',
                    tidak_hadir: 'Alfa',
                };

                return labels[status] || '-';
            },

            formatJam(value) {
                if (!value) return '-';
                return value.substring(0, 5);
            },

            async clockIn() {
                const result = await Swal.fire({
                    title: 'Mulai Presensi?',
                    html: `
                        <p>Apakah Anda yakin ingin melakukan <strong>Clock In</strong> sekarang?</p>
                        <p style="margin-top: 8px; color: #7B4B2A; font-size: 13px;">
                            Jam masuk presensi akan tercatat sesuai waktu saat ini.
                        </p>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Clock In',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#7B4B2A',
                    reverseButtons: true,
                });

                if (!result.isConfirmed) {
                    return;
                }

                try {
                    const response = await fetch('{{ route("attendance.clock-in") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire('Berhasil', data.message, 'success');
                        await this.loadAttendanceStatus();
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Terjadi kesalahan saat clock in.', 'error');
                }
            },

            async clockOut() {
                const result = await Swal.fire({
                    title: 'Akhiri Shift?',
                    html: `
                        <p>Apakah Anda yakin ingin melakukan <strong>Clock Out</strong> sekarang?</p>
                        <p style="margin-top: 8px; color: #7B4B2A; font-size: 13px;">
                            Jam pulang presensi akan tercatat sesuai waktu saat ini.
                        </p>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Clock Out',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#C98A4A',
                    cancelButtonColor: '#7B4B2A',
                    reverseButtons: true,
                });

                if (!result.isConfirmed) {
                    return;
                }

                try {
                    const response = await fetch('{{ route("attendance.clock-out") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire('Berhasil', data.message, 'success');
                        await this.loadAttendanceStatus();
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Terjadi kesalahan saat clock out.', 'error');
                }
            },

            async ajukanIzin() {
                const { value: alasan } = await Swal.fire({
                    title: 'Ajukan Izin',
                    input: 'textarea',
                    inputLabel: 'Alasan izin',
                    inputPlaceholder: 'Contoh: sakit, urusan keluarga, dan lain-lain',
                    inputAttributes: {
                        maxlength: 500,
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Kirim Izin',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#7B4B2A',
                    inputValidator: (value) => {
                        if (!value || !value.trim()) {
                            return 'Alasan izin wajib diisi.';
                        }
                    }
                });

                if (!alasan) return;

                try {
                    const response = await fetch('{{ route("izin.store") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            tanggal: new Date().toISOString().slice(0, 10),
                            alasan: alasan,
                        }),
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire('Berhasil', data.message, 'success');
                        await this.loadAttendanceStatus();
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Terjadi kesalahan saat mengajukan izin.', 'error');
                }
            },

            get subtotal() {
                return this.cart.reduce((sum, item) => sum + (item.harga * item.qty), 0);
            },

            get pajak() {
                return Math.round(this.subtotal * 0.03);
            },

            get grandTotal() {
                return this.subtotal + this.pajak;
            },

            tambahCart(id, nama, harga, stok, source = null) {
                const existing = this.cart.find(i => i.id === id);

                if (existing) {
                    if (existing.qty < stok) {
                        existing.qty++;
                        this.feedback(id, nama + ' ditambahkan');
                        window.PosMotion?.flyToCart(source, document.getElementById('order-cart'));
                    } else {
                        Swal.fire('Stok Habis', 'Qty melebihi stok tersedia', 'warning');
                    }
                } else {
                    this.cart.push({ id, nama, harga, qty: 1, stok });
                    this.feedback(id, nama + ' ditambahkan');
                        window.PosMotion?.flyToCart(source, document.getElementById('order-cart'));
                }
            },

            tambahQty(id) {
                const item = this.cart.find(i => i.id === id);

                if (item && item.qty < item.stok) {
                    item.qty++;
                    this.feedback(id, 'Jumlah ' + item.nama + ': ' + item.qty);
                } else {
                    Swal.fire('Stok Habis', 'Qty melebihi stok tersedia', 'warning');
                }
            },

            kurangiQty(id) {
                const item = this.cart.find(i => i.id === id);

                if (item && item.qty > 1) {
                    item.qty--;
                    this.feedback(id, 'Jumlah ' + item.nama + ': ' + item.qty);
                } else {
                    this.konfirmasiHapusItem(item);
                }
            },

            hapusItem(id) {
                this.cart = this.cart.filter(i => i.id !== id);
            },

            async konfirmasiHapusItem(item) {
                if (!item) {
                    return;
                }

                const result = await Swal.fire({
                    title: 'Hapus Item?',
                    html: `
                        <p>Apakah Anda yakin ingin menghapus <strong>${item.nama}</strong> dari pesanan?</p>
                        <p style="margin-top: 8px; color: #7B4B2A; font-size: 13px;">
                            Item ini akan dihapus dari keranjang.
                        </p>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#7B4B2A',
                    reverseButtons: true,
                });

                if (result.isConfirmed) {
                    this.hapusItem(item.id);

                    Swal.fire({
                        title: 'Item Dihapus',
                        text: `${item.nama} berhasil dihapus dari pesanan.`,
                        icon: 'success',
                        timer: 1200,
                        showConfirmButton: false,
                    });
                }
            },

            async konfirmasiHapusSemuaPesanan() {
                if (this.cart.length === 0) {
                    return;
                }

                const result = await Swal.fire({
                    title: 'Hapus Semua Pesanan?',
                    html: `
                        <p>Apakah Anda yakin ingin menghapus semua pesanan?</p>
                        <p style="margin-top: 8px; color: #7B4B2A; font-size: 13px;">
                            Semua item di keranjang akan dihapus dan aksi ini tidak bisa dibatalkan.
                        </p>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#7B4B2A',
                    reverseButtons: true,
                });

                if (result.isConfirmed) {
                    this.cart = [];

                    Swal.fire({
                        title: 'Berhasil',
                        text: 'Semua pesanan berhasil dihapus.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                    });
                }
            },

            formatRupiah(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            },

            async checkout(metode) {
                if (this.cart.length === 0) return;

                if (!this.namaPelanggan.trim()) {
                    Swal.fire('Perhatian', 'Nama pelanggan wajib diisi!', 'warning');
                    return;
                }

                if (metode === 'cash') {
                    const pelanggan = this.namaPelanggan;
                    const grandTotal = this.grandTotal;

                    const { value: uangDiterima } = await Swal.fire({
                        title: '💵 Pembayaran Cash',
                        html: `
                            <p style="margin-bottom: 10px;">Pelanggan: <strong>${pelanggan}</strong></p>
                            <p style="margin-bottom: 10px;">Total: <strong>Rp ${this.formatRupiah(grandTotal)}</strong></p>
                            <input id="uang-input" type="number" class="swal2-input" placeholder="Masukkan jumlah uang" min="${grandTotal}">
                        `,
                        confirmButtonText: 'Proses',
                        confirmButtonColor: '#16a34a',
                        showCancelButton: true,
                        cancelButtonText: 'Batal',
                        didOpen: () => {
                            document.getElementById('uang-input').focus();
                        },
                        preConfirm: () => {
                            const val = parseInt(document.getElementById('uang-input').value);

                            if (!val || val < grandTotal) {
                                Swal.showValidationMessage('Uang tidak cukup!');
                                return false;
                            }

                            return val;
                        }
                    });

                    if (!uangDiterima) return;

                    const kembalian = uangDiterima - grandTotal;

                    try {
                        const response = await fetch('{{ route("kasir.checkout") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                nama_pelanggan: pelanggan,
                                cart: this.cart.map(i => ({ menu_id: i.id, qty: i.qty })),
                                metode: 'cash',
                                uang_diterima: uangDiterima,
                            }),
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.cart = [];
                            this.namaPelanggan = '';

                            await Swal.fire({
                                title: 'Pembayaran Berhasil! 🎉',
                                html: `
                                    <div style="font-size: 60px; margin-bottom: 10px;">✅</div>
                                    <p style="font-size: 16px; font-weight: bold; color: #15803d;">Transaksi Selesai!</p>

                                    <div style="margin-top: 15px; padding: 15px; background: #f0fdf4; border-radius: 8px;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                            <span>Pelanggan</span>
                                            <strong>${pelanggan}</strong>
                                        </div>

                                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                            <span>Total</span>
                                            <strong>Rp ${this.formatRupiah(grandTotal)}</strong>
                                        </div>

                                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                            <span>Uang Diterima</span>
                                            <strong>Rp ${this.formatRupiah(uangDiterima)}</strong>
                                        </div>

                                        <div style="display: flex; justify-content: space-between; border-top: 1px solid #86efac; padding-top: 8px;">
                                            <span style="font-weight: bold;">Kembalian</span>
                                            <strong style="color: #15803d; font-size: 18px;">Rp ${this.formatRupiah(kembalian)}</strong>
                                        </div>
                                    </div>

                                    <p style="color: #666; font-size: 13px; margin-top: 15px;">
                                        Pilih aksi berikutnya.
                                    </p>
                                `,
                                icon: 'success',
                                showConfirmButton: true,
                                confirmButtonText: 'Print Struk',
                                confirmButtonColor: '#7B4B2A',
                                showDenyButton: true,
                                denyButtonText: 'Kembali ke Kasir',
                                denyButtonColor: '#6b7280',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = `/payment/struk/${data.payment_id}`;
                                } else if (result.isDenied) {
                                    window.location.reload();
                                }
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('Error', 'Terjadi kesalahan', 'error');
                    }

                } else {
                    const pelanggan = this.namaPelanggan;

                    const result = await Swal.fire({
                        title: 'Konfirmasi Pembayaran QRIS',
                        html: `
                            <p>Pelanggan: <strong>${pelanggan}</strong></p>
                            <p>Total: <strong>Rp ${this.formatRupiah(this.grandTotal)}</strong></p>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Lanjut',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#7B4B2A',
                    });

                    if (!result.isConfirmed) return;

                    try {
                        const response = await fetch('{{ route("kasir.checkout") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                nama_pelanggan: pelanggan,
                                cart: this.cart.map(i => ({ menu_id: i.id, qty: i.qty })),
                                metode: 'qris',
                            }),
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.cart = [];
                            this.namaPelanggan = '';
                            window.location.href = `/payment/${data.payment_id}`;
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('Error', 'Terjadi kesalahan', 'error');
                    }
                }
            }
        }
    }
</script>
@endpush