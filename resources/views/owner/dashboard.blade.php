@extends('layouts.app')
@section('content')
<style>
.owner-overview{display:grid;gap:22px;color:#4b2e1f}.owner-overview *{box-sizing:border-box}.owner-heading{display:flex;justify-content:space-between;align-items:center;gap:16px}.owner-heading h1{font-size:30px;font-weight:800}.owner-heading p,.owner-panel header p{color:#8b6b55;font-size:13px;margin-top:5px}.owner-date{white-space:nowrap;padding:12px 16px;background:#fffaf2;border:1px solid #ead8c2;border-radius:14px;font-size:13px}.owner-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.owner-stat,.owner-panel{background:#fffaf3;border:1px solid #ecd9c2;border-radius:20px;box-shadow:0 2px 5px #4b2e1f08;min-width:0}.owner-stat{padding:23px 16px 23px 80px;position:relative}.owner-stat-icon{position:absolute;left:18px;top:24px;display:flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:14px;background:#f9ead5;color:#92581e}.owner-stat h2{font-size:13px;color:#987257;font-weight:700}.owner-stat strong{display:block;font-size:clamp(20px,2vw,30px);margin-top:12px;overflow-wrap:anywhere}.owner-stat small{display:block;font-size:11px;color:#8b6b55;margin-top:6px}.owner-middle{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,1fr);gap:20px}.owner-panel header{padding:20px 22px;border-bottom:1px solid #efdfcc}.owner-panel h2{font-size:18px;font-weight:800}.owner-chart{padding:18px 20px}.owner-chart svg{display:block;width:100%;height:auto}.owner-chart text{font-family:inherit;fill:#785943;font-size:12px}.owner-bar{fill:#b7884e;transition:fill .15s}.owner-bar:hover,.owner-bar:focus{fill:#784923;outline:none}.owner-data{margin-top:8px;font-size:12px;color:#785943}.owner-data summary{cursor:pointer}.owner-data table{width:100%;border-collapse:collapse;margin-top:10px}.owner-data td,.owner-data th{padding:6px;text-align:left;border-bottom:1px solid #ecddcb}.owner-list{padding:18px;display:grid;gap:12px;list-style:none;margin:0}.owner-list li{display:flex;align-items:center;gap:10px;border:1px solid #ead8c2;border-radius:13px;padding:14px 12px}.owner-rank{flex:none;color:#9a642d}.owner-menu-name{flex:1;font-size:13px;font-weight:700;overflow-wrap:anywhere}.owner-qty{white-space:nowrap;font-size:12px;color:#8c501d;background:#f7e7ce;padding:5px 8px;border-radius:9px}.owner-stock-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:18px;max-height:260px;overflow:auto}.owner-stock-item{border:1px solid #f0cd9c;background:#fff5e6;border-radius:13px;padding:14px}.owner-stock-item strong{display:block;font-size:13px}.owner-stock-item p{font-size:12px;margin-top:7px;color:#945714}.owner-stock-item.is-empty{background:#fff0ee;border-color:#f0c4bf}.owner-stock-item.is-empty p{color:#ac382a}.owner-empty{padding:24px;color:#8a6a53;font-size:13px}.owner-safe{margin:18px;padding:20px;border-radius:13px;color:#16664d;background:#eaf8ef;font-size:13px}.owner-chart-note{font-size:12px;color:#88664c;margin-bottom:8px}
@media(max-width:1200px){.owner-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.owner-middle{grid-template-columns:1fr}.owner-stock-list{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.owner-heading{align-items:flex-start;flex-direction:column}.owner-stats{gap:10px}.owner-stat{padding:15px}.owner-stat-icon{display:none}.owner-stock-list{grid-template-columns:1fr}.owner-chart{padding:12px 8px}}
</style>
<div class="owner-overview">
    <div class="owner-heading">
        <div><h1>Dashboard</h1><p>Ringkasan bisnis dan operasional JIMNY COFFEE.</p></div>
        <time class="owner-date" datetime="{{ today()->toDateString() }}">{{ today()->format('d M Y') }}</time>
    </div>
    <div class="owner-stats">
        <section class="owner-stat"><span class="owner-stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9zM8 11h8M8 15h8"/></svg></span><h2>Transaksi Hari Ini</h2><strong>{{ $totalTransaksi }}</strong><small>Transaksi lunas</small></section>
        <section class="owner-stat"><span class="owner-stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01M18 12h.01"/></svg></span><h2>Omzet Hari Ini</h2><strong style="color:#059669">Rp {{ number_format($omzetHarian, 0, ',', '.') }}</strong></section>
        <section class="owner-stat"><span class="owner-stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M4 3v17h17M7 14l4-4 4 2 5-7"/></svg></span><h2>Omzet Bulan Ini</h2><strong>Rp {{ number_format($omzetBulanan, 0, ',', '.') }}</strong></section>
        <section class="owner-stat"><span class="owner-stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0"><circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5"/></svg></span><h2>Kasir Hadir Hari Ini</h2><strong>{{ $kasirHadir }} <span style="font-size:16px;font-weight:500">dari {{ $totalKasir }}</span></strong><small>Sudah clock-in hari ini, termasuk yang selesai shift</small></section>
    </div>
    <div class="owner-middle">
        <section class="owner-panel">
            <header><h2>Omzet 7 Hari Terakhir</h2><p>Transaksi lunas berdasarkan tanggal transaksi · {{ $omzetMingguan->first()['label'] }}–{{ $omzetMingguan->last()['label'] }} {{ today()->year }}</p></header>
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
            <header><h2>Menu Terlaris</h2><p>7 hari terakhir · jumlah item dari transaksi lunas</p></header>
            <ol class="owner-list">@forelse($menuTerlaris as $menu)<li><span class="owner-rank">{{ $loop->iteration }}</span><span class="owner-menu-name">{{ $menu->nama_menu }}</span><span class="owner-qty">{{ $menu->total_terjual }} terjual</span></li>@empty<li class="owner-empty">Belum ada menu terjual dalam 7 hari terakhir.</li>@endforelse</ol>
        </section>
    </div>
    <section class="owner-panel">
        <header><h2>Stok Perlu Diperhatikan @if($stokHampirHabis->isNotEmpty())<span style="font-size:13px;font-weight:500">({{ $stokHampirHabis->count() }} menu)</span>@endif</h2><p>Menu aktif dengan stok tersedia pada atau di bawah batas minimum.</p></header>
        @if($stokHampirHabis->isEmpty())<p class="owner-safe">Semua stok masih aman. Tidak ada menu aktif yang mencapai batas minimum.</p>
        @else<div class="owner-stock-list">@foreach($stokHampirHabis as $menu)<div class="owner-stock-item {{ $menu->stok <= 0 ? 'is-empty' : '' }}"><strong>{{ $menu->nama_menu }}</strong><p>Stok: {{ $menu->stok }} · Minimum: {{ $menu->minimum_stok }} @if($menu->stok <= 0) · Habis @endif</p></div>@endforeach</div>@endif
    </section>
</div>
@endsection
