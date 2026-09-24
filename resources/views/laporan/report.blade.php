@extends('layouts.app')
@section('content')
<div class="report-page" x-data="{ metric: 'total' }">
    <header class="page-heading">
        <div><span class="eyebrow">LAPORAN & ANALISIS</span><h1>Kenali perkembangan penjualan.</h1><p>Ringkasan {{ $monthly ? 'bulanan' : 'harian' }} untuk membantu merencanakan operasional kedai.</p></div>
        <button class="soft-button no-print" onclick="window.print()">Cetak laporan</button>
    </header>
    <div class="report-toolbar panel">
        <nav class="segmented" aria-label="Periode laporan">
            <a href="{{ route('laporan.harian', ['tanggal' => $start->toDateString()]) }}" @if(!$monthly) aria-current="page" @endif>Harian</a>
            <a href="{{ route('laporan.bulanan', ['bulan' => $start->month, 'tahun' => $start->year]) }}" @if($monthly) aria-current="page" @endif>Bulanan</a>
        </nav>
        <form method="GET" action="{{ route($monthly ? 'laporan.bulanan' : 'laporan.harian') }}" class="report-filter">
            @if($monthly)
                <div><label for="bulan">Bulan</label><select id="bulan" name="bulan">@foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $name)<option value="{{ $loop->iteration }}" @selected($start->month === $loop->iteration)>{{ $name }}</option>@endforeach</select></div>
                <div><label for="tahun">Tahun</label><input id="tahun" name="tahun" type="number" min="1900" max="2100" value="{{ $start->year }}" required></div>
            @else
                <div><label for="tanggal">Tanggal</label><input id="tanggal" name="tanggal" type="date" value="{{ $start->toDateString() }}" required></div>
            @endif
            <button class="primary-action" type="submit">Tampilkan</button>
        </form>
    </div>
    <p class="report-context">Periode {{ $start->format($monthly ? 'm/Y' : 'd/m/Y') }} · Transaksi sukses · Berdasarkan waktu pembuatan transaksi</p>
    <section class="metric-grid" aria-label="Ringkasan penjualan">
        <article class="metric-card metric-sage"><span>Pendapatan transaksi</span><strong>Rp {{ number_format($total, 0, ',', '.') }}</strong><small>Total pembayaran setelah pajak dan diskon</small></article>
        <article class="metric-card metric-blue"><span>Transaksi sukses</span><strong>{{ number_format($count, 0, ',', '.') }} <em>transaksi</em></strong><small>Pesanan dengan status sukses</small></article>
        <article class="metric-card metric-sand"><span>Rata-rata per transaksi</span><strong>Rp {{ number_format($count ? $total / $count : 0, 0, ',', '.') }}</strong><small>Pendapatan ÷ transaksi sukses</small></article>
        <article class="metric-card metric-lilac"><span>Dibanding {{ $monthly ? 'bulan' : 'hari' }} sebelumnya</span><strong>{{ $growth === null ? 'Belum tersedia' : (($growth > 0 ? '+' : '') . number_format($growth, 1, ',', '.') . '%') }}</strong><small>{{ $previousTotal > 0 ? 'Periode sebelumnya: Rp ' . number_format($previousTotal, 0, ',', '.') : 'Periode sebelumnya belum memiliki pendapatan.' }}</small></article>
    </section>
    <section class="panel trend-panel">
        <div class="section-heading"><div><span class="eyebrow">TREN PENJUALAN</span><h2>{{ $monthly ? 'Pendapatan dari hari ke hari' : 'Aktivitas penjualan per jam' }}</h2><p>Semua {{ $monthly ? 'tanggal' : 'jam' }} tetap tampil, termasuk saat tidak ada transaksi.</p></div>
            <div class="segmented no-print" role="group" aria-label="Metrik grafik"><button @click="metric = 'total'" :aria-pressed="metric === 'total'">Pendapatan</button><button @click="metric = 'count'" :aria-pressed="metric === 'count'">Transaksi</button></div>
        </div>
        @foreach(['total' => 'Pendapatan (Rp)', 'count' => 'Jumlah transaksi'] as $key => $axis)
            @php
                $max = max(1, $series->max($key));
                $points = $series->map(fn($row, $i) => (70 + $i * (800 / max(1, $series->count() - 1))) . ',' . (220 - $row[$key] / $max * 175))->implode(' ');
            @endphp
            <div x-show="metric === '{{ $key }}'" @if($key === 'count') x-cloak @endif class="chart-scroll">
                <svg class="trend-chart" viewBox="0 0 900 270" role="img" aria-labelledby="chart-title-{{ $key }} chart-desc-{{ $key }}">
                    <title id="chart-title-{{ $key }}">{{ $axis }} {{ $monthly ? 'per tanggal' : 'per jam' }}</title>
                    <desc id="chart-desc-{{ $key }}">Grafik periode {{ $start->format($monthly ? 'm/Y' : 'd/m/Y') }}. Nilai lengkap tersedia pada tabel data grafik di bawah.</desc>
                    <defs><linearGradient id="area-{{ $key }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#bd7a32" stop-opacity=".3"/><stop offset="100%" stop-color="#bd7a32" stop-opacity=".02"/></linearGradient></defs>
                    @for($tick = 0; $tick <= 4; $tick++)
                        <line x1="70" y1="{{ 220 - $tick * 43.75 }}" x2="870" y2="{{ 220 - $tick * 43.75 }}" stroke="#e8dac6" stroke-dasharray="4 5"/>
                        <text x="59" y="{{ 224 - $tick * 43.75 }}" text-anchor="end">{{ number_format($max * $tick / 4, $key === 'count' && $max < 4 ? 1 : 0, ',', '.') }}</text>
                    @endfor
                    <polygon points="70,220 {{ $points }} 870,220" fill="url(#area-{{ $key }})"/>
                    <polyline points="{{ $points }}" fill="none" stroke="#a0441b" stroke-width="3" stroke-linejoin="round"/>
                    @foreach($series as $row)
                        <circle cx="{{ 70 + $loop->index * (800 / max(1, $series->count() - 1)) }}" cy="{{ 220 - $row[$key] / $max * 175 }}" r="4" fill="#a0441b" stroke="#fff" stroke-width="2"><title>{{ $row['label'] }}: {{ $key === 'total' ? 'Rp ' : '' }}{{ number_format($row[$key], 0, ',', '.') }}</title></circle>
                        @if($loop->index % ($monthly ? 3 : 2) === 0 || $loop->last)<text x="{{ 70 + $loop->index * (800 / max(1, $series->count() - 1)) }}" y="245" text-anchor="middle">{{ $row['label'] }}</text>@endif
                    @endforeach
                    <text x="70" y="20">{{ $axis }}</text>
                </svg>
            </div>
        @endforeach
        <div class="insight-strip">{{ $count ? 'Pendapatan tertinggi: ' . ($monthly ? 'tanggal ' : 'pukul ') . $peak['label'] . ' · Rp ' . number_format($peak['total'], 0, ',', '.') : 'Belum ada transaksi sukses pada periode ini. Pilih periode lain untuk melihat analisis.' }}</div>
        <details class="chart-data"><summary>Lihat data grafik</summary><div class="table-scroll"><table><thead><tr><th>{{ $monthly ? 'Tanggal' : 'Jam' }}</th><th>Transaksi</th><th>Pendapatan</th></tr></thead><tbody>@foreach($series as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['count'] }}</td><td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td></tr>@endforeach</tbody></table></div></details>
    </section>
    <div class="analytics-grid">
        <section class="panel"><div class="section-heading"><div><span class="eyebrow">PEMBAYARAN</span><h2>Komposisi pendapatan</h2><p>Kontribusi setiap metode pembayaran.</p></div></div>
            @forelse($payments as $payment)
                <div class="bar-item"><div><strong>{{ $payment['label'] }}</strong><span>{{ number_format($total > 0 ? $payment['total'] / $total * 100 : 0, 1, ',', '.') }}%</span></div><div class="bar-track"><div class="bar-fill payment-{{ $loop->index % 3 }}" style="width:{{ $total > 0 ? max(0, min(100, $payment['total'] / $total * 100)) : 0 }}%"></div></div><small>Rp {{ number_format($payment['total'], 0, ',', '.') }} · {{ $payment['count'] }} transaksi</small></div>
            @empty<p class="empty-state">Belum ada pembayaran untuk ditampilkan.</p>@endforelse
        </section>
        <section class="panel"><div class="section-heading"><div><span class="eyebrow">MENU TERLARIS</span><h2>Lima menu paling banyak terjual</h2><p>Peringkat berdasarkan jumlah item, bukan omzet.</p></div></div>
            @forelse($topMenus as $menu)
                <div class="bar-item"><div><strong>{{ $loop->iteration }}. {{ $menu->menu->nama_menu ?? 'Menu tidak tersedia' }}</strong><span>{{ $menu->units }} item</span></div><div class="bar-track"><div class="bar-fill" style="width:{{ max(0, $menu->units / max(1, $topMenus->max('units')) * 100) }}%"></div></div><small>Penjualan item Rp {{ number_format($menu->revenue, 0, ',', '.') }} sebelum pajak/diskon transaksi</small></div>
            @empty<p class="empty-state">Menu terlaris akan muncul setelah ada transaksi sukses.</p>@endforelse
        </section>
    </div>
    <section class="panel report-table"><div class="section-heading"><div><span class="eyebrow">RINCIAN PENJUALAN</span><h2>{{ $monthly ? 'Rekap per tanggal' : 'Transaksi pada periode ini' }}</h2><p>{{ $monthly ? 'Pilih tanggal untuk melihat transaksi harian.' : '15 transaksi per halaman, dari yang terbaru.' }}</p></div></div><div class="table-scroll"><table>
        <thead><tr><th>{{ $monthly ? 'Tanggal' : 'Kode / waktu' }}</th><th>{{ $monthly ? 'Transaksi' : 'Kasir' }}</th><th>Pendapatan</th><th>Detail</th></tr></thead><tbody>
        @if($monthly)
            @foreach($series as $row)<tr><td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td><td>{{ $row['count'] }}</td><td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td><td><a href="{{ route('laporan.harian', ['tanggal' => $row['date']]) }}" aria-label="Lihat laporan {{ $row['date'] }}">Lihat harian ↗</a></td></tr>@endforeach
        @else
            @forelse($transactions as $transaction)<tr><td><strong>{{ $transaction->kode_transaksi }}</strong><small>{{ $transaction->created_at->format('H:i') }} · {{ strtoupper($transaction->payment_method) }}</small></td><td>{{ $transaction->user->name ?? '-' }}</td><td>Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</td><td><a href="{{ route('transaksi.show', $transaction->id) }}">Lihat transaksi ↗</a></td></tr>@empty<tr><td colspan="4" class="empty-state">Belum ada transaksi sukses pada tanggal ini.</td></tr>@endforelse
        @endif
        </tbody></table></div>@if(!$monthly)<div class="report-pagination">{{ $transactions->links() }}</div>@endif
    </section>
    <p class="report-context">Pendapatan merupakan nilai transaksi sukses, bukan laba. Perbandingan memakai satu periode kalender penuh sebelumnya; periode berjalan dapat belum lengkap.</p>
</div>
@endsection
