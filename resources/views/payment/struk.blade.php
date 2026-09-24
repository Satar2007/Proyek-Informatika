<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Pembayaran - {{ $payment->transaction->kode_transaksi ?? '-' }}</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            background: #ffffff;
            color: #000000;
        }

        body {
            width: 300px;
            margin: 0 auto;
            padding: 12px 10px;
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            line-height: 1.35;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: 700;
        }

        .title {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 0.8px;
        }

        .brand-line {
            margin: 4px auto 5px;
            width: 52px;
            border-top: 2px solid #000;
        }

        .subtitle {
            font-size: 11px;
            margin-top: 2px;
        }

        .small {
            font-size: 10px;
        }

        .line {
            border-top: 1px dashed #000;
            margin: 9px 0;
        }

        .line-solid {
            border-top: 1px solid #000;
            margin: 9px 0;
        }

        .section-title {
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.4px;
            text-align: center;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 3px 0;
        }

        .row span:first-child {
            flex: 1;
        }

        .row span:last-child {
            text-align: right;
            max-width: 150px;
            word-break: break-word;
        }

        .item {
            margin-bottom: 8px;
        }

        .item-name {
            font-weight: 900;
            margin-bottom: 2px;
        }

        .item-note {
            font-size: 11px;
        }

        .total {
            font-size: 15px;
            font-weight: 900;
        }

        .grand-total {
            padding-top: 2px;
            padding-bottom: 2px;
        }

        .cash-info {
            font-weight: 700;
        }

        .payment-box {
            border: 1px dashed #000;
            padding: 6px;
            margin-top: 8px;
        }

        .paid-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 3px 8px;
            border: 1px solid #000;
            font-weight: 900;
            letter-spacing: 0.5px;
        }

        .footer {
            margin-top: 10px;
        }

        .no-print {
            margin-top: 16px;
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 9px 14px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
        }

        .btn-print {
            background: #7B4B2A;
        }

        .btn-close {
            background: #6b7280;
            margin-left: 6px;
        }

        @page {
            size: 80mm auto;
            margin: 0;
        }

        @media print {
            body {
                width: 80mm;
                margin: 0;
                padding: 8px;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    @php
        $transaction = $payment->transaction;
        $paidAt = $payment->paid_at ?? $transaction?->paid_at ?? $payment->created_at;
        $metode = strtolower($payment->metode ?? $transaction?->payment_method ?? '-');

        $uangDiterima = $payment->uang_diterima ?? null;
        $kembalian = $payment->kembalian ?? null;

        $isCash = $metode === 'cash';
    @endphp

    {{-- Header --}}
    <div class="center">
        <p class="title">JIMNY COFFEE</p>
        <div class="brand-line"></div>
        <p class="subtitle">Coffee Shop</p>
        <p class="subtitle">Jl. Kopi Nikmat No. 1</p>
        <p class="subtitle">Telp: 0812-3456-7890</p>
    </div>

    <div class="line"></div>

    {{-- Info Transaksi --}}
    <div class="row">
        <span>No. Transaksi</span>
        <span class="bold">{{ $transaction->kode_transaksi ?? '-' }}</span>
    </div>

    <div class="row">
        <span>Tanggal</span>
        <span>{{ $paidAt ? \Carbon\Carbon::parse($paidAt)->format('d/m/Y H:i') : '-' }}</span>
    </div>

    <div class="row">
        <span>Kasir</span>
        <span>{{ $transaction->user?->name ?? '-' }}</span>
    </div>

    <div class="row">
        <span>Pelanggan</span>
        <span>{{ $transaction->nama_pelanggan ?? 'Umum' }}</span>
    </div>

    <div class="row">
        <span>Metode</span>
        <span>{{ strtoupper($metode) }}</span>
    </div>

    <div class="line"></div>

    {{-- Detail Pesanan --}}
    <p class="section-title">DETAIL PESANAN</p>

    <div class="line"></div>

    @forelse($transaction->details as $detail)
        <div class="item">
            <p class="item-name">
                {{ $detail->menu?->nama_menu ?? 'Menu dihapus' }}
            </p>

            <div class="row item-note">
                <span>{{ $detail->qty }} x Rp {{ number_format($detail->harga, 0, ',', '.') }}</span>
                <span>Rp {{ number_format($detail->subtotal, 0, ',', '.') }}</span>
            </div>
        </div>
    @empty
        <p class="center">Tidak ada item.</p>
    @endforelse

    <div class="line"></div>

    {{-- Total --}}
    <div class="row">
        <span>Subtotal</span>
        <span>Rp {{ number_format($transaction->total ?? 0, 0, ',', '.') }}</span>
    </div>

    <div class="row">
        <span>Pajak (3%)</span>
        <span>Rp {{ number_format($transaction->pajak ?? 0, 0, ',', '.') }}</span>
    </div>

    @if(($transaction->diskon ?? 0) > 0)
        <div class="row">
            <span>Diskon</span>
            <span>- Rp {{ number_format($transaction->diskon, 0, ',', '.') }}</span>
        </div>
    @endif

    <div class="line-solid"></div>

    <div class="row total grand-total">
        <span>TOTAL</span>
        <span>Rp {{ number_format($transaction->grand_total ?? 0, 0, ',', '.') }}</span>
    </div>

    {{-- Detail Cash --}}
    @if($isCash)
        <div class="payment-box">
            <div class="row cash-info">
                <span>Uang Diterima</span>
                <span>Rp {{ number_format($uangDiterima ?? 0, 0, ',', '.') }}</span>
            </div>

            <div class="row cash-info">
                <span>Kembalian</span>
                <span>Rp {{ number_format($kembalian ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>
    @endif

    <div class="line"></div>

    {{-- Footer --}}
    <div class="center footer">
        <p class="paid-badge">LUNAS</p>
        <p style="margin-top: 8px;">Terima kasih telah berkunjung.</p>
        <p>Selamat menikmati.</p>
        <p class="small" style="margin-top: 6px;">Simpan struk ini sebagai bukti pembayaran.</p>
    </div>

    <div class="line"></div>

    {{-- Tombol Print --}}
    <div class="center no-print">
        <button onclick="window.print()" class="btn btn-print">
            Print Struk
        </button>

        <a href="{{ route('kasir.index') }}" class="btn btn-close" style="display: inline-block; text-decoration: none;">
            Kembali ke POS Kasir
        </a>
    </div>

    <script>
        window.onload = function () {
            setTimeout(() => {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>