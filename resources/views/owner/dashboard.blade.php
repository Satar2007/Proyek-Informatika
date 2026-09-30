@extends('layouts.app')
@section('content')
<style>
.owner-overview{display:grid;gap:20px;color:#4b2e1f}.owner-overview *{box-sizing:border-box}.owner-heading{display:flex;justify-content:space-between;align-items:center;gap:16px}.owner-heading h1{font-size:30px;font-weight:800}.owner-heading p,.owner-panel header p{color:#8b6b55;font-size:13px;margin-top:5px}.owner-date{white-space:nowrap;padding:12px 16px;background:#fffaf2;border:1px solid #ead8c2;border-radius:14px;font-size:13px}.owner-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.owner-stat,.owner-panel,.owner-compact{background:#fffaf3;border:1px solid #ecd9c2;border-radius:20px;box-shadow:0 2px 5px #4b2e1f08;min-width:0}.owner-stat{padding:21px 16px 20px 72px;position:relative}.owner-stat-icon{position:absolute;left:17px;top:22px;display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:13px;background:#f9ead5;color:#92581e}.owner-stat h2{font-size:12px;color:#987257;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.owner-stat strong{display:block;font-size:clamp(20px,2vw,29px);margin-top:10px;overflow-wrap:anywhere}.owner-stat small{display:block;font-size:11px;color:#8b6b55;margin-top:6px;line-height:1.4}.owner-delta{font-weight:800}.owner-delta.up{color:#087a58}.owner-delta.down{color:#b74334}.owner-delta.flat{color:#8b6b55}.owner-compact-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.owner-compact{padding:17px 20px;display:flex;justify-content:space-between;align-items:center;gap:18px}.owner-compact span{display:block;font-size:12px;color:#987257;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.owner-compact strong{display:block;margin-top:6px;font-size:20px}.owner-compact p{font-size:12px;color:#8b6b55;text-align:right;max-width:260px}.owner-middle{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,1fr);gap:18px}.owner-bottom{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.2fr);gap:18px}.owner-panel header{padding:18px 20px;border-bottom:1px solid #efdfcc}.owner-panel h2{font-size:18px;font-weight:800}.owner-chart{padding:16px 18px}.owner-chart svg{display:block;width:100%;height:auto}.owner-chart text{font-family:inherit;fill:#785943;font-size:12px}.owner-bar{fill:#b7884e;transition:fill .15s}.owner-bar:hover,.owner-bar:focus{fill:#784923;outline:none}.owner-data{margin-top:8px;font-size:12px;color:#785943}.owner-data summary{cursor:pointer}.owner-data table{width:100%;border-collapse:collapse;margin-top:10px}.owner-data td,.owner-data th{padding:6px;text-align:left;border-bottom:1px solid #ecddcb}.owner-list{padding:16px;display:grid;gap:10px;list-style:none;margin:0}.owner-list li{display:flex;align-items:center;gap:10px;border:1px solid #ead8c2;border-radius:13px;padding:12px}.owner-rank{flex:none;color:#9a642d;font-weight:800}.owner-menu-name{flex:1;font-size:13px;font-weight:700;overflow-wrap:anywhere}.owner-qty{white-space:nowrap;font-size:12px;color:#8c501d;background:#f7e7ce;padding:5px 8px;border-radius:9px}.owner-qty.is-low{background:#f3eee7;color:#775b45}.owner-stock-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:16px;max-height:320px;overflow:auto}.owner-stock-item{border:1px solid #f0cd9c;background:#fff5e6;border-radius:13px;padding:13px}.owner-stock-item strong{display:block;font-size:13px}.owner-stock-item p{font-size:12px;margin-top:7px;color:#945714}.owner-stock-item.is-empty{background:#fff0ee;border-color:#f0c4bf}.owner-stock-item.is-empty p{color:#ac382a}.owner-empty{padding:20px;color:#8a6a53;font-size:13px}.owner-safe{margin:16px;padding:18px;border-radius:13px;color:#16664d;background:#eaf8ef;font-size:13px}.owner-chart-note{font-size:12px;color:#88664c;margin-bottom:8px}
@media(max-width:1200px){.owner-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.owner-middle,.owner-bottom{grid-template-columns:1fr}.owner-stock-list{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.owner-compact-grid{grid-template-columns:1fr}.owner-compact{align-items:flex-start;flex-direction:column}.owner-compact p{text-align:left}}@media(max-width:560px){.owner-heading{align-items:flex-start;flex-direction:column}.owner-stats{grid-template-columns:1fr;gap:10px}.owner-stat{padding:15px}.owner-stat-icon{display:none}.owner-stock-list{grid-template-columns:1fr}.owner-chart{padding:12px 8px}}
</style>
<div class="owner-overview">
    <div class="owner-heading">
        <div><h1>Dashboard</h1><p>Ringkasan bisnis dan operasional JIMNY COFFEE.</p></div>
        <time class="owner-date" datetime="{{ today()->toDateString() }}">{{ today()->format('d M Y') }}</time>
    </div>

    <div class="owner-stats">
        <section class="owner-stat">
            <span class="owner-stat-icon" aria-hidden="true">Rp</span>
            <h2>Omzet Hari Ini</h2>
            <strong style="color:#059669">Rp {{ number_format($omzetHarian, 0, ',', '.') }}</strong>
            <small>
                @if($omzetKemarin > 0)
                    <span class="owner-delta {{ $perubahanOmzetNominal > 0 ? 'up' : ($perubahanOmzetNominal < 0 ? 'down' : 'flat') }}">
                        {{ $perubahanOmzetNominal > 0 ? '+' : '' }}{{ number_format($perubahanOmzetPersen, 1, ',', '.') }}%
                    </span>
                    dari kemarin (Rp {{ number_format($omzetKemarin, 0, ',', '.') }})
                @elseif($omzetHarian > 0)
                    Kemarin belum memiliki omzet lunas sebagai pembanding.
                @else
                    Hari ini dan kemarin belum memiliki omzet lunas.
                @endif
            </small>
        </section>

        <section class="owner-stat">
            <span class="owner-stat-icon" aria-hidden="true">#</span>
            <h2>Transaksi Hari Ini</h2>
            <strong>{{ $totalTransaksi }}</strong>
            <small>Transaksi sukses dan sudah dibayar.</small>
        </section>

        <section class="owner-stat">
            <span class="owner-stat-icon" aria-hidden="true">Ø</span>
            <h2>Rata-rata Transaksi</h2>
            <strong>Rp {{ number_format($rataRataTransaksi, 0, ',', '.') }}</strong>
            <small>Omzet hari ini ÷ transaksi lunas hari ini.</small>
        </section>

        <section class="owner-stat">
            <span class="owner-stat-icon" aria-hidden="true">!</span>
            <h2>Stok Kritis</h2>
            <strong>{{ $stokKritis }} <span style="font-size:15px;font-weight:600">menu</span></strong>
            <small>Menu aktif dengan stok ≤ batas minimum.</small>
        </section>
    </div>

    <div class="owner-compact-grid">
        <section class="owner-compact">
            <div><span>Omzet Bulan Ini</span><strong>Rp {{ number_format($omzetBulanan, 0, ',', '.') }}</strong></div>
            <p>Akumulasi transaksi lunas sejak awal {{ today()->translatedFormat('F') }}.</p>
        </section>
        <section class="owner-compact">
            <div><span>Kehadiran Kasir</span><strong>{{ $kasirHadir }} dari {{ $totalKasir }}</strong></div>
            <p>{{ max(0, $totalKasir - $kasirHadir) }} kasir belum tercatat clock-in hari ini.</p>
        </section>
    </div>

    <div class="owner-middle">
        <section class="owner-panel">
            <header><h2>Omzet 7 Hari Terakhir</h2><p>Omzet transaksi lunas · {{ $omzetMingguan->first()['label'] }}–{{ $omzetMingguan->last()['label'] }} {{ today()->year }}</p></header>
            <div class="owner-chart">
                @php
                    $maximum = max(0, (int) $omzetMingguan->max('omzet'));
                    $axisStep = $maximum > 0 ? (int) (ceil($maximum / 4 / (10 ** floor(log10(max(1, $maximum / 4))))) * (10 ** floor(log10(max(1, $maximum / 4))))) : 1;
                    $axisMax = $axisStep * 4;
                @endphp
                @if($maximum === 0)<p class="owner-chart-note">Belum ada omzet dari transaksi lunas dalam 7 hari terakhir.</p>@endif
                <svg viewBox="0 0 760 290" role="img" aria-labelledby="owner-chart-title owner-chart-desc">
                    <title id="owner-chart-title">Omzet tujuh hari terakhir</title>
                    <desc id="owner-chart-desc">Nilai lengkap tersedia pada tabel data di bawah grafik. Hari tanpa omzet bernilai nol.</desc>
                    @for($tick = 0; $tick <= 4; $tick++)
                        @php $y = 240 - $tick * 52; @endphp
                        <line x1="110" y1="{{ $y }}" x2="745" y2="{{ $y }}" stroke="#eadbc8" stroke-dasharray="4 4" />
                        <text x="100" y="{{ $y + 4 }}" text-anchor="end">{{ $maximum === 0 ? ($tick === 0 ? 'Rp 0' : '') : 'Rp '.number_format($tick * $axisStep, 0, ',', '.') }}</text>
                    @endfor
                    @foreach($omzetMingguan as $index => $day)
                        @php $x = 125 + $index * 89; $height = max(0, $day['omzet']) / $axisMax * 208; @endphp
                        <rect class="owner-bar" x="{{ $x }}" y="{{ 240 - $height }}" width="57" height="{{ $height }}" rx="5" tabindex="0" aria-label="{{ $day['label'] }}: Rp {{ number_format($day['omzet'], 0, ',', '.') }}"><title>{{ $day['label'] }}: Rp {{ number_format($day['omzet'], 0, ',', '.') }}</title></rect>
                        <text x="{{ $x + 28.5 }}" y="264" text-anchor="middle">{{ $day['label'] }}</text>
                    @endforeach
                </svg>
                <details class="owner-data"><summary>Lihat angka omzet per hari</summary><table><thead><tr><th scope="col">Tanggal</th><th scope="col">Omzet</th></tr></thead><tbody>@foreach($omzetMingguan as $day)<tr><td>{{ $day['tanggal'] }}</td><td>Rp {{ number_format($day['omzet'], 0, ',', '.') }}</td></tr>@endforeach</tbody></table></details>
            </div>
        </section>

        <section class="owner-panel">
            <header><h2>Menu Terlaris</h2><p>7 hari terakhir · jumlah item dari transaksi lunas.</p></header>
            <ol class="owner-list">@forelse($menuTerlaris as $menu)<li><span class="owner-rank">{{ $loop->iteration }}</span><span class="owner-menu-name">{{ $menu->nama_menu }}</span><span class="owner-qty">{{ $menu->total_terjual }} terjual</span></li>@empty<li class="owner-empty">Belum ada menu terjual dalam 7 hari terakhir.</li>@endforelse</ol>
        </section>
    </div>

    <div class="owner-bottom">
        <section class="owner-panel">
            <header><h2>Menu Kurang Laku</h2><p>5 menu aktif dengan penjualan item terendah dalam 30 hari terakhir.</p></header>
            <ol class="owner-list">@forelse($menuKurangLaku as $menu)<li><span class="owner-rank">{{ $loop->iteration }}</span><span class="owner-menu-name">{{ $menu->nama_menu }}</span><span class="owner-qty is-low">{{ $menu->total_terjual }} terjual</span></li>@empty<li class="owner-empty">Belum ada menu aktif untuk dianalisis.</li>@endforelse</ol>
        </section>

        <section class="owner-panel">
            <header><h2>Perlu Restock @if($stokHampirHabis->isNotEmpty())<span style="font-size:13px;font-weight:500">({{ $stokHampirHabis->count() }} menu)</span>@endif</h2><p>Menu aktif yang sudah mencapai atau melewati batas minimum stok.</p></header>
            @if($stokHampirHabis->isEmpty())
                <p class="owner-safe">Semua stok masih aman. Tidak ada menu aktif yang mencapai batas minimum.</p>
            @else
                <div class="owner-stock-list">@foreach($stokHampirHabis as $menu)<div class="owner-stock-item {{ $menu->stok <= 0 ? 'is-empty' : '' }}"><strong>{{ $menu->nama_menu }}</strong><p>Stok: {{ $menu->stok }} · Minimum: {{ $menu->minimum_stok }} @if($menu->stok <= 0) · Habis @endif</p></div>@endforeach</div>
            @endif
        </section>
    </div>
</div>
@endsection
