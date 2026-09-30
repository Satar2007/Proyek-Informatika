@extends('layouts.app')

@section('content')
@php
    $sharedFilters = array_filter([
        'kasir_id' => auth()->user()->role === 'kasir' ? null : $filters['kasir_id'],
        'status' => $filters['status'] !== 'success' ? $filters['status'] : null,
        'metode' => $filters['metode'],
    ], fn ($value) => $value !== null && $value !== '');

    $periodParams = $monthly
        ? ['bulan' => $start->month, 'tahun' => $start->year]
        : ['tanggal' => $start->toDateString()];

    $exportParams = array_merge(
        ['periode' => $monthly ? 'bulanan' : 'harian'],
        $periodParams,
        $sharedFilters
    );

    $valueLabel = $isRevenueReport ? 'Pendapatan' : 'Nilai transaksi';
    $statusContext = $statusLabels[$filters['status']] ?? ucfirst($filters['status']);
@endphp

<div class="report-page" x-data="{ metric: 'total' }">
    <header class="page-heading">
        <div>
            <span class="eyebrow">LAPORAN & ANALISIS</span>
            <h1>Kenali perkembangan penjualan.</h1>
            <p>
                Ringkasan {{ $monthly ? 'bulanan' : 'harian' }}
                {{ auth()->user()->role === 'kasir' ? 'untuk transaksi Anda.' : 'untuk operasional kedai.' }}
            </p>
        </div>

        <div class="no-print" style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
            <a class="soft-button" href="{{ route('laporan.export', $exportParams) }}">
                Export CSV (Excel)
            </a>
            <button class="soft-button" type="button" onclick="window.print()">
                Cetak / Simpan PDF
            </button>
        </div>
    </header>

    <div class="report-toolbar panel">
        <nav class="segmented" aria-label="Periode laporan">
            <a
                href="{{ route('laporan.harian', array_merge(['tanggal' => $start->toDateString()], $sharedFilters)) }}"
                @if(!$monthly) aria-current="page" @endif
            >Harian</a>
            <a
                href="{{ route('laporan.bulanan', array_merge(['bulan' => $start->month, 'tahun' => $start->year], $sharedFilters)) }}"
                @if($monthly) aria-current="page" @endif
            >Bulanan</a>
        </nav>

        <form
            method="GET"
            action="{{ route($monthly ? 'laporan.bulanan' : 'laporan.harian') }}"
            class="report-filter"
        >
            @if($monthly)
                <div>
                    <label for="bulan">Bulan</label>
                    <select id="bulan" name="bulan">
                        @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $name)
                            <option value="{{ $loop->iteration }}" @selected($start->month === $loop->iteration)>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="tahun">Tahun</label>
                    <input
                        id="tahun"
                        name="tahun"
                        type="number"
                        min="1900"
                        max="2100"
                        value="{{ $start->year }}"
                        required
                    >
                </div>
            @else
                <div>
                    <label for="tanggal">Tanggal</label>
                    <input
                        id="tanggal"
                        name="tanggal"
                        type="date"
                        value="{{ $start->toDateString() }}"
                        required
                    >
                </div>
            @endif

            @if(auth()->user()->role !== 'kasir')
                <div>
                    <label for="kasir_id">Petugas</label>
                    <select id="kasir_id" name="kasir_id">
                        <option value="">Semua petugas</option>
                        @foreach($cashiers as $cashier)
                            <option value="{{ $cashier->id }}" @selected((string) $filters['kasir_id'] === (string) $cashier->id)>
                                {{ $cashier->name }} ({{ ucfirst($cashier->role) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="metode">Pembayaran</label>
                    <select id="metode" name="metode">
                        <option value="">Semua metode</option>
                        @foreach($paymentLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['metode'] === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <button class="primary-action" type="submit">Tampilkan</button>
        </form>
    </div>

    <p class="report-context">
        Periode {{ $start->format($monthly ? 'm/Y' : 'd/m/Y') }}
        · {{ $statusContext }}
        @if($filters['metode'])
            · {{ $paymentLabels[$filters['metode']] ?? strtoupper($filters['metode']) }}
        @endif
        @if(auth()->user()->role === 'kasir')
            · Hanya transaksi milik Anda
        @elseif($filters['kasir_id'])
            · Filter petugas aktif
        @endif
        · Berdasarkan waktu pembuatan transaksi
    </p>

    <section class="metric-grid" aria-label="Ringkasan laporan">
        <article class="metric-card metric-sage">
            <span>{{ $isRevenueReport ? 'Pendapatan transaksi' : 'Nilai transaksi terfilter' }}</span>
            <strong>Rp {{ number_format($total, 0, ',', '.') }}</strong>
            <small>
                {{ $isRevenueReport
                    ? 'Total transaksi dengan status lunas.'
                    : 'Nilai transaksi sesuai status/filter yang dipilih; bukan pendapatan terealisasi.' }}
            </small>
        </article>

        <article class="metric-card metric-blue">
            <span>Jumlah transaksi</span>
            <strong>{{ number_format($count, 0, ',', '.') }} <em>transaksi</em></strong>
            <small>Jumlah transaksi yang sesuai dengan filter aktif.</small>
        </article>

        <article class="metric-card metric-sand">
            <span>Rata-rata per transaksi</span>
            <strong>Rp {{ number_format($count ? $total / $count : 0, 0, ',', '.') }}</strong>
            <small>{{ $valueLabel }} ÷ jumlah transaksi terfilter.</small>
        </article>

        <article class="metric-card metric-lilac">
            <span>Dibanding {{ $monthly ? 'bulan' : 'hari' }} sebelumnya</span>
            <strong>
                {{ $growth === null
                    ? 'Belum tersedia'
                    : (($growth > 0 ? '+' : '') . number_format($growth, 1, ',', '.') . '%') }}
            </strong>
            <small>
                {{ $previousTotal > 0
                    ? 'Periode sebelumnya: Rp ' . number_format($previousTotal, 0, ',', '.')
                    : 'Periode sebelumnya belum memiliki data pada filter yang sama.' }}
            </small>
        </article>
    </section>

    <section class="panel trend-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">TREN TRANSAKSI</span>
                <h2>{{ $monthly ? $valueLabel . ' dari hari ke hari' : 'Aktivitas transaksi per jam' }}</h2>
                <p>Semua {{ $monthly ? 'tanggal' : 'jam' }} tetap tampil, termasuk saat tidak ada transaksi.</p>
            </div>

            <div class="segmented no-print" role="group" aria-label="Metrik grafik">
                <button @click="metric = 'total'" :aria-pressed="metric === 'total'">{{ $valueLabel }}</button>
                <button @click="metric = 'count'" :aria-pressed="metric === 'count'">Transaksi</button>
            </div>
        </div>

        @foreach(['total' => $valueLabel . ' (Rp)', 'count' => 'Jumlah transaksi'] as $key => $axis)
            @php
                $max = max(1, $series->max($key));
                $points = $series->map(
                    fn($row, $i) => (70 + $i * (800 / max(1, $series->count() - 1)))
                        . ','
                        . (220 - $row[$key] / $max * 175)
                )->implode(' ');
            @endphp

            <div
                x-show="metric === '{{ $key }}'"
                @if($key === 'count') x-cloak @endif
                class="chart-scroll"
            >
                <svg
                    class="trend-chart"
                    viewBox="0 0 900 270"
                    role="img"
                    aria-labelledby="chart-title-{{ $key }} chart-desc-{{ $key }}"
                >
                    <title id="chart-title-{{ $key }}">
                        {{ $axis }} {{ $monthly ? 'per tanggal' : 'per jam' }}
                    </title>
                    <desc id="chart-desc-{{ $key }}">
                        Grafik periode {{ $start->format($monthly ? 'm/Y' : 'd/m/Y') }}.
                        Nilai lengkap tersedia pada tabel data grafik di bawah.
                    </desc>
                    <defs>
                        <linearGradient id="area-{{ $key }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#bd7a32" stop-opacity=".3"/>
                            <stop offset="100%" stop-color="#bd7a32" stop-opacity=".02"/>
                        </linearGradient>
                    </defs>

                    @for($tick = 0; $tick <= 4; $tick++)
                        <line
                            x1="70"
                            y1="{{ 220 - $tick * 43.75 }}"
                            x2="870"
                            y2="{{ 220 - $tick * 43.75 }}"
                            stroke="#e8dac6"
                            stroke-dasharray="4 5"
                        />
                        <text
                            x="59"
                            y="{{ 224 - $tick * 43.75 }}"
                            text-anchor="end"
                        >{{ number_format($max * $tick / 4, $key === 'count' && $max < 4 ? 1 : 0, ',', '.') }}</text>
                    @endfor

                    <polygon points="70,220 {{ $points }} 870,220" fill="url(#area-{{ $key }})"/>
                    <polyline
                        points="{{ $points }}"
                        fill="none"
                        stroke="#a0441b"
                        stroke-width="3"
                        stroke-linejoin="round"
                    />

                    @foreach($series as $row)
                        <circle
                            cx="{{ 70 + $loop->index * (800 / max(1, $series->count() - 1)) }}"
                            cy="{{ 220 - $row[$key] / $max * 175 }}"
                            r="4"
                            fill="#a0441b"
                            stroke="#fff"
                            stroke-width="2"
                        >
                            <title>
                                {{ $row['label'] }}:
                                {{ $key === 'total' ? 'Rp ' : '' }}{{ number_format($row[$key], 0, ',', '.') }}
                            </title>
                        </circle>

                        @if($loop->index % ($monthly ? 3 : 2) === 0 || $loop->last)
                            <text
                                x="{{ 70 + $loop->index * (800 / max(1, $series->count() - 1)) }}"
                                y="245"
                                text-anchor="middle"
                            >{{ $row['label'] }}</text>
                        @endif
                    @endforeach

                    <text x="70" y="20">{{ $axis }}</text>
                </svg>
            </div>
        @endforeach

        <div class="insight-strip">
            {{ $count
                ? $valueLabel . ' tertinggi: ' . ($monthly ? 'tanggal ' : 'pukul ') . $peak['label'] . ' · Rp ' . number_format($peak['total'], 0, ',', '.')
                : 'Belum ada transaksi pada periode dan filter ini. Pilih periode atau filter lain.' }}
        </div>

        <details class="chart-data">
            <summary>Lihat data grafik</summary>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>{{ $monthly ? 'Tanggal' : 'Jam' }}</th>
                            <th>Transaksi</th>
                            <th>{{ $valueLabel }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($series as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td>{{ $row['count'] }}</td>
                                <td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </section>

    <div class="analytics-grid">
        <section class="panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">PEMBAYARAN</span>
                    <h2>Komposisi {{ strtolower($valueLabel) }}</h2>
                    <p>Kontribusi metode pembayaran pada transaksi yang sesuai filter.</p>
                </div>
            </div>

            @forelse($payments as $payment)
                <div class="bar-item">
                    <div>
                        <strong>{{ $payment['label'] }}</strong>
                        <span>{{ number_format($total > 0 ? $payment['total'] / $total * 100 : 0, 1, ',', '.') }}%</span>
                    </div>
                    <div class="bar-track">
                        <div
                            class="bar-fill payment-{{ $loop->index % 3 }}"
                            style="width:{{ $total > 0 ? max(0, min(100, $payment['total'] / $total * 100)) : 0 }}%"
                        ></div>
                    </div>
                    <small>
                        Rp {{ number_format($payment['total'], 0, ',', '.') }}
                        · {{ $payment['count'] }} transaksi
                    </small>
                </div>
            @empty
                <p class="empty-state">Belum ada pembayaran untuk ditampilkan.</p>
            @endforelse
        </section>

        <section class="panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">{{ $isRevenueReport ? 'MENU TERLARIS' : 'MENU TERBANYAK' }}</span>
                    <h2>Lima menu paling banyak tercatat</h2>
                    <p>Peringkat berdasarkan jumlah item pada transaksi yang sesuai filter.</p>
                </div>
            </div>

            @forelse($topMenus as $menu)
                <div class="bar-item">
                    <div>
                        <strong>{{ $loop->iteration }}. {{ $menu->menu->nama_menu ?? 'Menu tidak tersedia' }}</strong>
                        <span>{{ $menu->units }} item</span>
                    </div>
                    <div class="bar-track">
                        <div
                            class="bar-fill"
                            style="width:{{ max(0, $menu->units / max(1, $topMenus->max('units')) * 100) }}%"
                        ></div>
                    </div>
                    <small>
                        Nilai item Rp {{ number_format($menu->revenue, 0, ',', '.') }}
                        sebelum pajak/diskon transaksi
                    </small>
                </div>
            @empty
                <p class="empty-state">Belum ada data menu pada periode dan filter ini.</p>
            @endforelse
        </section>
    </div>

    <section class="panel report-table">
        <div class="section-heading">
            <div>
                <span class="eyebrow">RINCIAN TRANSAKSI</span>
                <h2>{{ $monthly ? 'Rekap per tanggal' : 'Transaksi pada periode ini' }}</h2>
                <p>
                    {{ $monthly
                        ? 'Pilih tanggal untuk melihat rincian harian dengan filter yang sama.'
                        : '15 transaksi per halaman, dari yang terbaru.' }}
                </p>
            </div>
        </div>

        <div class="table-scroll">
            <table>
                @if($monthly)
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Transaksi</th>
                            <th>{{ $valueLabel }}</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($series as $row)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                <td>{{ $row['count'] }}</td>
                                <td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                                <td>
                                    <a href="{{ route('laporan.harian', array_merge(['tanggal' => $row['date']], $sharedFilters)) }}">
                                        Lihat harian ↗
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @else
                    <thead>
                        <tr>
                            <th>Kode / waktu</th>
                            <th>Pelanggan</th>
                            <th>Petugas</th>
                            <th>Status / metode</th>
                            <th>Nilai</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            <tr>
                                <td>
                                    <strong>{{ $transaction->kode_transaksi }}</strong>
                                    <small>{{ $transaction->created_at->format('H:i') }}</small>
                                </td>
                                <td>{{ $transaction->nama_pelanggan ?: '-' }}</td>
                                <td>{{ $transaction->cashier_display_name }}</td>
                                <td>
                                    {{ $statusLabels[$transaction->status] ?? ucfirst($transaction->status) }}
                                    <small>{{ $paymentLabels[$transaction->payment_method] ?? strtoupper($transaction->payment_method) }}</small>
                                </td>
                                <td>Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
                                <td>
                                    <a href="{{ route('transaksi.show', $transaction->id) }}">
                                        Lihat transaksi ↗
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty-state">
                                    @if($isRevenueReport)
                                        Belum ada transaksi sukses pada periode dan filter ini.
                                    @else
                                        Belum ada transaksi pada periode dan filter ini.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                @endif
            </table>
        </div>

        @if(!$monthly)
            <div class="report-pagination">{{ $transactions->links() }}</div>
        @endif
    </section>

    <p class="report-context">
        @if($isRevenueReport)
            Pendapatan merupakan nilai transaksi berstatus lunas, bukan laba.
        @else
            Nilai transaksi pada status selain lunas tidak dianggap sebagai pendapatan terealisasi.
        @endif
        Perbandingan memakai filter yang sama terhadap satu periode kalender sebelumnya.
    </p>
</div>
@endsection
