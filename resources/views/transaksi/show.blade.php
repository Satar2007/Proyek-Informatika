@extends('layouts.app')

@section('content')
@php
    $payment = $transaksi->payment;
    $method = strtolower($transaksi->payment_method ?? $payment?->metode ?? '-');
    $isCash = $method === 'cash';

    $isPaid = ($transaksi->payment_status === 'paid') || ($transaksi->status === 'success');
    $isPending = !$isPaid && $transaksi->status === 'pending';
    $canManagePending = $isPending && in_array(auth()->user()->role, ['kasir', 'admin']);
@endphp

<div class="mx-auto max-w-5xl space-y-6">
    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Detail Transaksi
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                {{ $transaksi->kode_transaksi }}
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Rincian transaksi, item pesanan, metode pembayaran, dan total pembayaran.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            {{-- Cetak Struk hanya untuk Kasir/Admin: route payment.struk memang dibatasi
                 untuk dua role itu, jadi Owner tidak ditawari tombol yang akan 403. --}}
            @if($payment && $isPaid && auth()->user()->role !== 'owner')
                <a href="{{ route('payment.struk', $payment->id) }}" target="_blank"
                    class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                    Cetak Struk
                </a>
            @endif

            <a href="{{ route('transaksi.index') }}"
                class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                Kembali
            </a>
        </div>
    </div>

    {{-- Flash Message --}}
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-4 text-sm font-black text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-sm font-black text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Status Summary --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">
                Grand Total
            </p>
            <p class="mt-2 text-3xl font-black text-[#7B4B2A]">
                Rp {{ number_format($transaksi->grand_total, 0, ',', '.') }}
            </p>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">
                Status Transaksi
            </p>

            <div class="mt-3">
                @if($transaksi->status === 'success')
                    <span class="inline-flex rounded-full bg-emerald-50 px-4 py-2 text-xs font-black text-emerald-700">
                        Sukses
                    </span>
                @elseif($transaksi->status === 'pending')
                    <span class="inline-flex rounded-full bg-[#FFF3E4] px-4 py-2 text-xs font-black text-[#C98A4A]">
                        Pending
                    </span>
                @elseif($transaksi->status === 'expired')
                    <span class="inline-flex rounded-full bg-red-50 px-4 py-2 text-xs font-black text-red-700">
                        Expired
                    </span>
                @elseif($transaksi->status === 'cancelled')
                    <span class="inline-flex rounded-full bg-red-50 px-4 py-2 text-xs font-black text-red-700">
                        Dibatalkan
                    </span>
                @else
                    <span class="inline-flex rounded-full bg-[#F8F5F0] px-4 py-2 text-xs font-black text-[#7B4B2A]">
                        {{ ucfirst($transaksi->status ?? '-') }}
                    </span>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-5 shadow-sm shadow-[#4B2E1F]/5">
            <p class="text-sm font-bold text-[#7B4B2A]/75">
                Metode Pembayaran
            </p>

            <div class="mt-3">
                @if($method === 'cash')
                    <span class="inline-flex rounded-full bg-emerald-50 px-4 py-2 text-xs font-black uppercase text-emerald-700">
                        Cash
                    </span>
                @elseif($method === 'qris')
                    <span class="inline-flex rounded-full bg-[#F1E5D8] px-4 py-2 text-xs font-black uppercase text-[#7B4B2A]">
                        QRIS
                    </span>
                @else
                    <span class="inline-flex rounded-full bg-[#F8F5F0] px-4 py-2 text-xs font-black uppercase text-[#7B4B2A]">
                        {{ $transaksi->payment_method ?? '-' }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Aksi Pending --}}
    @if($canManagePending)
        <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
            <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
                <h2 class="text-lg font-black text-[#4B2E1F]">
                    Aksi Transaksi Pending
                </h2>

                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    @if($method === 'qris')
                        Pembayaran QRIS menunggu verifikasi dari Midtrans.
                    @else
                        Transaksi belum lunas. Pilih aksi sesuai kondisi pembayaran.
                    @endif
                </p>
            </div>

            <div class="grid grid-cols-1 gap-3 p-6 {{ $method === 'qris' ? 'md:grid-cols-1' : 'md:grid-cols-4' }}">
                @if($method === 'qris')
                    @if($payment)
                        <a href="{{ route('payment.show', $payment->id) }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                            Lanjutkan QRIS
                        </a>

                        <form action="{{ route('transaksi.cancel', $transaksi->id) }}"
                            method="POST"
                            onsubmit="return konfirmasiAksiTransaksi(event, this, 'cancel')">
                            @csrf

                            <button type="submit"
                                class="w-full rounded-2xl border border-red-300 bg-red-50 px-5 py-3 text-sm font-black text-red-700 shadow-sm transition hover:bg-red-100">
                                Batalkan Transaksi
                            </button>
                        </form>
                    @endif

                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold leading-relaxed text-amber-900">
                        @if(!$payment)
                            Data pembayaran belum tersedia. Hubungi admin untuk memeriksa transaksi.
                        @elseif(blank($payment->midtrans_snap_token))
                            Pembayaran Midtrans belum dibuka.
                            Pilih <strong>Lanjutkan QRIS</strong> untuk memulai pembayaran,
                            atau <strong>Batalkan Transaksi</strong> jika pesanan tidak jadi dilanjutkan.
                        @else
                            Pembayaran Midtrans sudah pernah dibuka.
                            Pilih <strong>Lanjutkan QRIS</strong> untuk membuka kembali pembayaran
                            atau memeriksa statusnya.
                            Pembatalan hanya dicatat setelah status pembayaran diverifikasi melalui Midtrans.
                        @endif
                    </div>
                @else
                    <form action="{{ route('transaksi.cancel', $transaksi->id) }}"
                        method="POST"
                        onsubmit="return konfirmasiAksiTransaksi(event, this, 'cancel')">
                        @csrf

                        <button type="submit"
                            class="w-full rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                            Batalkan
                        </button>
                    </form>

                    <form action="{{ route('transaksi.expire', $transaksi->id) }}"
                        method="POST"
                        onsubmit="return konfirmasiAksiTransaksi(event, this, 'expire')">
                        @csrf

                        <button type="submit"
                            class="w-full rounded-2xl bg-[#C98A4A] px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-[#7B4B2A]">
                            Tandai Expired
                        </button>
                    </form>

                    <form action="{{ route('transaksi.destroy', $transaksi->id) }}"
                        method="POST"
                        onsubmit="return konfirmasiAksiTransaksi(event, this, 'delete')">
                        @csrf
                        @method('DELETE')

                        <button type="submit"
                            class="w-full rounded-2xl bg-red-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-red-700">
                            Hapus
                        </button>
                    </form>
                @endif
            </div>

            <div class="border-t border-[#E8D8C7] bg-[#FFFDF9] px-6 py-4">
                <p class="text-xs font-semibold leading-relaxed text-[#7B4B2A]/75">
                    @if($method === 'qris')
                        Status pembayaran QRIS mengikuti hasil verifikasi Midtrans.
                        Kembali ke POS tidak otomatis membatalkan pesanan dan stok tetap dicadangkan selama pembayaran menunggu.
                    @else
                        Transaksi yang sudah lunas tidak bisa dibatalkan, di-expire, atau dihapus.
                    @endif
                </p>
            </div>
        </div>
    @endif
    {{-- Informasi Transaksi --}}
    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
            <h2 class="text-lg font-black text-[#4B2E1F]">
                Informasi Transaksi
            </h2>
            <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                Data umum transaksi dan pihak yang memproses.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
            <div class="rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] p-4">
                <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                    Kode Transaksi
                </p>
                <p class="mt-2 font-mono text-sm font-black text-[#4B2E1F]">
                    {{ $transaksi->kode_transaksi }}
                </p>
            </div>

            <div class="rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] p-4">
                <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                    Tanggal
                </p>
                <p class="mt-2 font-black text-[#4B2E1F]">
                    {{ $transaksi->created_at->format('d/m/Y H:i') }}
                </p>
            </div>

            <div class="rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] p-4">
                <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                    Kasir
                </p>
                <p class="mt-2 font-black text-[#4B2E1F]">
                    {{ $transaksi->user?->name ?? '-' }}
                </p>
            </div>

            <div class="rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] p-4">
                <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                    Pelanggan
                </p>
                <p class="mt-2 font-black text-[#4B2E1F]">
                    {{ $transaksi->nama_pelanggan ?? 'Umum' }}
                </p>
            </div>

            <div class="rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] p-4">
                <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                    Status Pembayaran
                </p>
                <p class="mt-2 font-black text-[#4B2E1F]">
                    {{ ucfirst($transaksi->payment_status ?? '-') }}
                </p>
            </div>

            <div class="rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] p-4">
                <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                    Dibayar Pada
                </p>
                <p class="mt-2 font-black text-[#4B2E1F]">
                    {{ $transaksi->paid_at ? \Carbon\Carbon::parse($transaksi->paid_at)->format('d/m/Y H:i') : '-' }}
                </p>
            </div>

            @if($isCash)
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                    <p class="text-xs font-black uppercase tracking-wider text-emerald-700">
                        Uang Diterima
                    </p>
                    <p class="mt-2 text-lg font-black text-emerald-800">
                        Rp {{ number_format($payment?->uang_diterima ?? 0, 0, ',', '.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-[#F2D6B5] bg-[#FFF3E4] p-4">
                    <p class="text-xs font-black uppercase tracking-wider text-[#C98A4A]">
                        Kembalian
                    </p>
                    <p class="mt-2 text-lg font-black text-[#7B4B2A]">
                        Rp {{ number_format($payment?->kembalian ?? 0, 0, ',', '.') }}
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Detail Item --}}
    <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
        <div class="flex flex-col gap-3 border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-black text-[#4B2E1F]">
                    Item Pesanan
                </h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Daftar menu yang dibeli dalam transaksi ini.
                </p>
            </div>

            <span class="rounded-full bg-[#F1E5D8] px-4 py-2 text-xs font-black text-[#7B4B2A]">
                {{ $transaksi->details->count() }} item
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px]">
                <thead>
                    <tr class="border-b border-[#E8D8C7] bg-[#F8F5F0] text-left text-xs font-black uppercase tracking-wider text-[#7B4B2A]">
                        <th class="px-6 py-4">Menu</th>
                        <th class="px-6 py-4 text-right">Harga</th>
                        <th class="px-6 py-4 text-center">Qty</th>
                        <th class="px-6 py-4 text-right">Subtotal</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#E8D8C7]">
                    @forelse($transaksi->details as $detail)
                        <tr class="transition hover:bg-[#FFFDF9]">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#F8F5F0] text-lg text-[#7B4B2A] ring-1 ring-[#D9B08C]/60">
                                        ☕
                                    </div>

                                    <div>
                                        <p class="font-black text-[#4B2E1F]">
                                            {{ $detail->menu?->nama_menu ?? 'Menu dihapus' }}
                                        </p>
                                        <p class="text-xs font-semibold text-[#7B4B2A]/70">
                                            ID Menu: {{ $detail->menu_id }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <span class="font-black text-[#4B2E1F]">
                                    Rp {{ number_format($detail->harga, 0, ',', '.') }}
                                </span>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex rounded-full bg-[#F8F5F0] px-3 py-1 text-xs font-black text-[#7B4B2A]">
                                    {{ $detail->qty }}
                                </span>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <span class="font-black text-[#4B2E1F]">
                                    Rp {{ number_format($detail->subtotal, 0, ',', '.') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-3xl bg-[#F8F5F0] text-2xl ring-1 ring-[#D9B08C]/60">
                                        🛒
                                    </div>

                                    <p class="font-black text-[#4B2E1F]">
                                        Detail transaksi kosong
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                                        Tidak ada item yang tercatat pada transaksi ini.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Total Summary --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_420px]">
        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-6 shadow-sm shadow-[#4B2E1F]/5">
            <h2 class="text-lg font-black text-[#4B2E1F]">
                Catatan Pembayaran
            </h2>
            <p class="mt-2 text-sm font-semibold text-[#7B4B2A]/75">
                Transaksi ini diproses menggunakan metode
                <span class="font-black uppercase text-[#4B2E1F]">
                    {{ $transaksi->payment_method ?? '-' }}
                </span>
                dengan status pembayaran
                <span class="font-black text-[#4B2E1F]">
                    {{ ucfirst($transaksi->payment_status ?? '-') }}
                </span>.
            </p>

            @if($isCash)
                <p class="mt-3 text-sm font-semibold text-[#7B4B2A]/75">
                    Pembayaran cash menerima uang sebesar
                    <span class="font-black text-emerald-700">
                        Rp {{ number_format($payment?->uang_diterima ?? 0, 0, ',', '.') }}
                    </span>
                    dan menghasilkan kembalian sebesar
                    <span class="font-black text-[#C98A4A]">
                        Rp {{ number_format($payment?->kembalian ?? 0, 0, ',', '.') }}
                    </span>.
                </p>
            @endif
        </div>

        <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-6 shadow-sm shadow-[#4B2E1F]/5">
            <h2 class="mb-5 text-lg font-black text-[#4B2E1F]">
                Ringkasan Total
            </h2>

            <div class="space-y-3">
                <div class="flex justify-between text-sm font-semibold text-[#7B4B2A]/75">
                    <span>Total</span>
                    <span class="font-black text-[#4B2E1F]">
                        Rp {{ number_format($transaksi->total, 0, ',', '.') }}
                    </span>
                </div>

                <div class="flex justify-between text-sm font-semibold text-[#7B4B2A]/75">
                    <span>Pajak</span>
                    <span class="font-black text-[#4B2E1F]">
                        Rp {{ number_format($transaksi->pajak, 0, ',', '.') }}
                    </span>
                </div>

                <div class="flex justify-between text-sm font-semibold text-[#7B4B2A]/75">
                    <span>Diskon</span>
                    <span class="font-black text-[#4B2E1F]">
                        Rp {{ number_format($transaksi->diskon, 0, ',', '.') }}
                    </span>
                </div>

                <div class="mt-4 border-t border-[#E8D8C7] pt-4">
                    <div class="flex items-end justify-between">
                        <span class="text-sm font-black text-[#4B2E1F]">
                            Grand Total
                        </span>
                        <span class="text-2xl font-black text-[#7B4B2A]">
                            Rp {{ number_format($transaksi->grand_total, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                @if($isCash)
                    <div class="mt-4 border-t border-[#E8D8C7] pt-4">
                        <div class="flex justify-between text-sm font-semibold text-[#7B4B2A]/75">
                            <span>Uang Diterima</span>
                            <span class="font-black text-emerald-700">
                                Rp {{ number_format($payment?->uang_diterima ?? 0, 0, ',', '.') }}
                            </span>
                        </div>

                        <div class="mt-3 flex justify-between text-sm font-semibold text-[#7B4B2A]/75">
                            <span>Kembalian</span>
                            <span class="font-black text-[#C98A4A]">
                                Rp {{ number_format($payment?->kembalian ?? 0, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection


@push('scripts')
<script>
    function konfirmasiAksiTransaksi(event, form, tipe) {
        event.preventDefault();

        const konfigurasi = {
            cancel: {
                title: 'Batalkan Transaksi?',
                html: `
                    <p>Apakah Anda yakin ingin membatalkan transaksi ini?</p>
                    <p style="margin-top: 8px; color: #7B4B2A; font-size: 13px;">
                        Status transaksi akan berubah menjadi <strong>dibatalkan</strong>.
                    </p>
                `,
                icon: 'warning',
                confirmButtonText: 'Ya, Batalkan',
                confirmButtonColor: '#7B4B2A',
            },
            expire: {
                title: 'Tandai Expired?',
                html: `
                    <p>Apakah Anda yakin ingin menandai transaksi ini sebagai expired?</p>
                    <p style="margin-top: 8px; color: #7B4B2A; font-size: 13px;">
                        Gunakan aksi ini jika waktu pembayaran QRIS sudah habis.
                    </p>
                `,
                icon: 'warning',
                confirmButtonText: 'Ya, Expired',
                confirmButtonColor: '#C98A4A',
            },
            delete: {
                title: 'Hapus Transaksi?',
                html: `
                    <p>Apakah Anda yakin ingin menghapus transaksi ini?</p>
                    <p style="margin-top: 8px; color: #dc2626; font-size: 13px; font-weight: 700;">
                        Data transaksi, detail item, dan payment akan ikut dihapus. Aksi ini tidak bisa dibatalkan.
                    </p>
                `,
                icon: 'error',
                confirmButtonText: 'Ya, Hapus',
                confirmButtonColor: '#dc2626',
            },
        };

        const opsi = konfigurasi[tipe];

        Swal.fire({
            title: opsi.title,
            html: opsi.html,
            icon: opsi.icon,
            showCancelButton: true,
            confirmButtonText: opsi.confirmButtonText,
            cancelButtonText: 'Batal',
            confirmButtonColor: opsi.confirmButtonColor,
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });

        return false;
    }
</script>
@endpush
