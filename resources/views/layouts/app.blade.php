<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>JIMNY COFFEE</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --coffee-dark: #4B2E1F;
            --coffee-medium: #7B4B2A;
            --coffee-caramel: #C98A4A;
            --coffee-light: #D9B08C;
            --coffee-cream: #F8F5F0;
            --coffee-soft: #FFFDF9;
        }

        input,
        textarea,
        select {
            color: #000000 !important;
            caret-color: #000000 !important;
        }

        input::placeholder,
        textarea::placeholder {
            color: #8b7a6f !important;
            opacity: 1 !important;
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        textarea:-webkit-autofill,
        textarea:-webkit-autofill:hover,
        textarea:-webkit-autofill:focus,
        select:-webkit-autofill,
        select:-webkit-autofill:hover,
        select:-webkit-autofill:focus {
            -webkit-text-fill-color: #000000 !important;
            caret-color: #000000 !important;
            box-shadow: 0 0 0px 1000px #ffffff inset !important;
            -webkit-box-shadow: 0 0 0px 1000px #ffffff inset !important;
            transition: background-color 9999s ease-in-out 0s;
        }

        input[type="date"],
        input[type="time"],
        input[type="number"],
        input[type="email"],
        input[type="password"],
        input[type="text"],
        textarea,
        select {
            color-scheme: light !important;
        }

        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="time"]::-webkit-calendar-picker-indicator {
            filter: invert(0) !important;
            opacity: 1 !important;
        }

        [x-cloak] {
            display: none !important;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(201, 138, 74, 0.13), transparent 34rem),
                radial-gradient(circle at bottom right, rgba(217, 176, 140, 0.22), transparent 36rem),
                var(--coffee-cream);
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/pos-theme.css') }}?v=6">
<link rel="stylesheet" href="{{ asset('css/ember-theme.css') }}?v=6">
<link rel="stylesheet" href="{{ asset('css/sidebar-layout.css') }}?v=7">
<link rel="stylesheet" href="{{ asset('css/page-transition.css') }}?v=7">
    <script src="{{ asset('js/pos-motion.js') }}?v=6" defer></script>
    <script src="{{ asset('js/page-motion.js') }}?v=2" defer></script>
</head>

@php
    // Ikon garis sederhana (SVG inline) agar tampil konsisten di semua perangkat.
    $svgPaths = [
        'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
        'cup'      => '<path d="M4 8h13v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V8Z"/><path d="M17 10h1.5a2.5 2.5 0 0 1 0 5H17"/><path d="M8 2.5v2M12 2.5v2"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'receipt'  => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/>',
        'chart'    => '<path d="M3 3v18h18"/><path d="M7 15v3M12 10v8M17 6v12"/>',
        'box'      => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'list'     => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/>',
        'check'    => '<path d="m9 11 3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.4c1.8.8 3 2.6 3 5.6"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    ];
    $svg = fn (string $name) => '<svg class="sidebar-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($svgPaths[$name] ?? '') . '</svg>';
@endphp

<body class="ember-theme {{ request()->routeIs('kasir.index') ? 'pos-screen' : '' }} min-h-screen text-[#4B2E1F] antialiased">
    <x-page-transition />

    <a href="#main-content" class="skip-link">Langsung ke konten</a>

    <div class="app-main-shell" x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false">
        {{-- Sidebar --}}
        <aside class="app-sidebar" :class="{ 'is-open': mobileOpen }" aria-label="Navigasi utama">
            <div class="sidebar-inner">
                <div class="brand-card">
                    <img src="{{ asset('images/jimny-logo-round.png') }}" alt="Logo JIMNY COFFEE" class="brand-logo">
                    <div class="min-w-0">
                        <div class="brand-title">JIMNY COFFEE</div>
                        <div class="brand-sub">GOOD COFFEE · BETTER MOOD</div>
                    </div>
                </div>

                <nav class="sidebar-nav" aria-label="Menu">
                    @auth
                        <div class="sidebar-section">Navigasi</div>

                        @if(auth()->user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>{!! $svg('home') !!}<span>Dashboard</span></a>
                            <a href="{{ route('kasir.index') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('kasir.index') ? 'active' : '' }}" @if(request()->routeIs('kasir.index')) aria-current="page" @endif>{!! $svg('cup') !!}<span>Kasir / POS</span></a>
                        @elseif(auth()->user()->role === 'owner')
                            <a href="{{ route('owner.dashboard') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('owner.dashboard') ? 'active' : '' }}" @if(request()->routeIs('owner.dashboard')) aria-current="page" @endif>{!! $svg('home') !!}<span>Dashboard</span></a>
                        @else
                            <a href="{{ route('kasir.index') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('kasir.index') ? 'active' : '' }}" @if(request()->routeIs('kasir.index')) aria-current="page" @endif>{!! $svg('cup') !!}<span>Kasir / POS</span></a>
                        @endif

                        @if(in_array(auth()->user()->role, ['admin', 'kasir']))
                            @if(request()->routeIs('kasir.index'))
                                <button type="button" class="sidebar-link" @click="mobileOpen = false; $dispatch('open-presensi')">{!! $svg('clock') !!}<span>Presensi &amp; Izin</span></button>
                            @else
                                <a href="{{ route('kasir.index', ['presensi' => 1]) }}" @click="mobileOpen = false" class="sidebar-link">{!! $svg('clock') !!}<span>Presensi &amp; Izin</span></a>
                            @endif
                        @endif

                        @if(auth()->user()->role === 'admin')
                            <div class="sidebar-section">Operasional</div>
                            <a href="{{ route('admin.menu.index') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('admin.menu.*') ? 'active' : '' }}">{!! $svg('box') !!}<span>Menu &amp; Stok</span></a>
                            <a href="{{ route('admin.stok-log') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('admin.stok-log') ? 'active' : '' }}">{!! $svg('list') !!}<span>Log Stok</span></a>
                            <a href="{{ route('admin.shift.index') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('admin.shift.*') ? 'active' : '' }}">{!! $svg('calendar') !!}<span>Shift &amp; Absensi</span></a>
                            <a href="{{ route('admin.izin.index') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('admin.izin.*') ? 'active' : '' }}">{!! $svg('check') !!}<span>Pengajuan Izin</span></a>
                            <a href="{{ route('admin.akun.index') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('admin.akun.*') ? 'active' : '' }}">{!! $svg('users') !!}<span>Kelola Akun</span></a>

                            <div class="sidebar-section">Bisnis</div>
                            <a href="{{ route('transaksi.index') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('transaksi.*') ? 'active' : '' }}">{!! $svg('receipt') !!}<span>Transaksi</span></a>
                            <a href="{{ route('laporan.harian') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('laporan.*') ? 'active' : '' }}">{!! $svg('chart') !!}<span>Laporan</span></a>
                        @elseif(auth()->user()->role === 'owner')
                            <div class="sidebar-section">Bisnis</div>
                            <a href="{{ route('laporan.harian') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('laporan.harian') ? 'active' : '' }}">{!! $svg('chart') !!}<span>Laporan Harian</span></a>
                            <a href="{{ route('laporan.bulanan') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('laporan.bulanan') ? 'active' : '' }}">{!! $svg('chart') !!}<span>Laporan Bulanan</span></a>
                            <a href="{{ route('transaksi.index') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('transaksi.*') ? 'active' : '' }}">{!! $svg('receipt') !!}<span>Transaksi</span></a>
                            <a href="{{ route('owner.rekap-kehadiran') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('owner.rekap-kehadiran') ? 'active' : '' }}">{!! $svg('calendar') !!}<span>Rekap Kehadiran</span></a>
                        @else
                            <div class="sidebar-section">Kasir</div>
                            <a href="{{ route('transaksi.index') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('transaksi.*') ? 'active' : '' }}">{!! $svg('receipt') !!}<span>Transaksi</span></a>
                            <a href="{{ route('laporan.harian') }}" @click="mobileOpen = false" class="sidebar-link {{ request()->routeIs('laporan.*') ? 'active' : '' }}">{!! $svg('chart') !!}<span>Laporan Harian</span></a>
                        @endif
                    @endauth
                </nav>

                @auth
                    <div class="sidebar-user">
                        <div class="sidebar-user-row">
                            <div class="sidebar-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                            <div class="min-w-0">
                                <div class="sidebar-user-name truncate">{{ auth()->user()->name }}</div>
                                <div class="sidebar-role">{{ auth()->user()->role }}</div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="sidebar-logout">{!! $svg('logout') !!}<span>Keluar dari akun</span></button>
                        </form>
                    </div>
                @endauth
            </div>
        </aside>

        <div class="sidebar-overlay" x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false"></div>

        {{-- Topbar --}}
        <header class="app-topbar">
            <div class="topbar-left">
                <button type="button" class="mobile-sidebar-toggle" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen" aria-label="Buka menu navigasi">{!! $svg('menu') !!}</button>
                <img src="{{ asset('images/jimny-logo-round.png') }}" class="topbar-bean" alt="Logo JIMNY COFFEE">
                <div class="min-w-0">
                    <div class="topbar-title">
                        @auth
                            Selamat datang, {{ auth()->user()->name }}
                        @else
                            JIMNY COFFEE
                        @endauth
                    </div>
                    <div class="topbar-caption">Nikmati setiap tegukan, rasakan bedanya.</div>
                </div>
            </div>
            @auth
                <div class="topbar-role"><span class="topbar-dot"></span>{{ ucfirst(auth()->user()->role) }} mode</div>
            @endauth
        </header>

        {{-- Main --}}
        <main id="main-content" class="app-content">
            <div class="content-glass">
                @if(session('success'))
                    <div class="mb-6 rounded-3xl border border-emerald-200 bg-emerald-50 px-6 py-4 text-sm font-bold text-emerald-700 shadow-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 rounded-3xl border border-red-200 bg-red-50 px-6 py-4 text-sm font-bold text-red-700 shadow-sm">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="form-errors" role="alert"><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif

                <div class="page-ready">
                    {{ $slot ?? '' }}

                    @yield('content')
                </div>
            </div>
        </main>
    </div>

    @include('components.smart-assistant')

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @stack('scripts')
</body>
</html>
