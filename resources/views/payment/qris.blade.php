@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl space-y-6"
    x-data="paymentApp({{ $payment->id }}, {{ $payment->transaction->id }}, '{{ $payment->expired_at }}')"
    x-init="init()">

    {{-- Header --}}
    <div class="page-lead flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                Pembayaran
            </div>

            <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                Pembayaran QRIS
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                Scan QRIS JIMNY COFFEE, lalu konfirmasi setelah pembayaran diterima.
            </p>
        </div>

        <button type="button" @click="konfirmasiKembaliKeKasir()"
            class="inline-flex items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
            Kembali ke Kasir
        </button>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_380px]">
        {{-- QRIS Card --}}
        <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
            <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
                <h2 class="text-lg font-black text-[#4B2E1F]">
                    Scan QRIS
                </h2>
                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                    Gunakan mobile banking atau e-wallet yang mendukung QRIS.
                </p>
            </div>

            <div class="flex flex-col items-center p-8">
                <div class="rounded-[2rem] border border-[#D9B08C]/70 bg-[#FFFDF9] p-6 shadow-inner">
                    <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-[#E8D8C7]">
                        <img
                            src="{{ asset('images/qris-asli.jpeg') }}"
                            alt="QRIS JIMNY COFFEE"
                            class="h-auto w-[300px] max-w-full rounded-2xl object-contain"
                        >
                    </div>
                </div>

                <p class="mt-4 max-w-md text-center text-xs font-semibold leading-relaxed text-[#7B4B2A]/70">
                    QRIS ini menggunakan kode QR toko. Pastikan nominal yang dibayar sesuai dengan total transaksi.
                </p>

                {{-- Status --}}
                <div class="mt-6">
                    <span class="inline-flex rounded-full px-4 py-2 text-sm font-black"
                        :class="{
                            'bg-[#FFF3E4] text-[#C98A4A]': status === 'waiting',
                            'bg-emerald-50 text-emerald-700': status === 'paid',
                            'bg-red-50 text-red-700': status === 'expired' || status === 'failed' || status === 'cancelled'
                        }"
                        x-text="statusLabel">
                    </span>
                </div>

                {{-- Countdown batas waktu QRIS --}}
                <div class="mt-6 text-center">
                    <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                        Batas Waktu Pembayaran
                    </p>

                    <p class="mt-2 text-4xl font-black text-red-600" x-text="countdown"></p>

                    <p class="mt-2 text-sm font-semibold text-[#7B4B2A]/75">
                        Konfirmasi pembayaran setelah pelanggan membayar.
                    </p>
                </div>

            </div>
        </div>

        {{-- Detail Payment --}}
        <div class="space-y-6">
            <div class="overflow-hidden rounded-3xl border border-[#D9B08C]/60 bg-white shadow-sm shadow-[#4B2E1F]/5">
                <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
                    <h2 class="text-lg font-black text-[#4B2E1F]">
                        Detail Transaksi
                    </h2>
                    <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/75">
                        Informasi pembayaran pelanggan.
                    </p>
                </div>

                <div class="space-y-5 p-6">
                    <div class="rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] p-4">
                        <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                            Kode Transaksi
                        </p>
                        <p class="mt-1 font-mono text-sm font-black text-[#4B2E1F]">
                            {{ $payment->transaction->kode_transaksi }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] p-4">
                        <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                            Pelanggan
                        </p>
                        <p class="mt-1 font-black text-[#4B2E1F]">
                            {{ $payment->transaction->nama_pelanggan ?? 'Umum' }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-[#E8D8C7] bg-[#FFFDF9] p-4">
                        <p class="text-xs font-black uppercase tracking-wider text-[#7B4B2A]/70">
                            Metode
                        </p>
                        <span class="mt-2 inline-flex rounded-full bg-[#F1E5D8] px-3 py-1 text-xs font-black uppercase text-[#7B4B2A]">
                            QRIS
                        </span>
                    </div>

                    <div class="rounded-3xl border border-[#F2D6B5] bg-[#FFF3E4] p-5">
                        <p class="text-xs font-black uppercase tracking-wider text-[#C98A4A]">
                            Total Pembayaran
                        </p>
                        <p class="mt-2 text-3xl font-black text-[#7B4B2A]">
                            Rp {{ number_format($payment->transaction->grand_total, 0, ',', '.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="rounded-3xl border border-[#D9B08C]/60 bg-white p-6 shadow-sm shadow-[#4B2E1F]/5">
                <h3 class="font-black text-[#4B2E1F]">
                    Aksi Pembayaran
                </h3>

                <div class="mt-4 space-y-3">
                    <button @click="konfirmasiBayar()" x-show="status === 'waiting'"
                        :disabled="isProcessing || isExpired"
                        class="w-full rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 active:scale-[0.98] disabled:cursor-not-allowed disabled:bg-[#E8D8C7] disabled:text-[#7B4B2A]/50">
                        <span x-show="!isProcessing">Konfirmasi Sudah Dibayar</span>
                        <span x-show="isProcessing">Memproses...</span>
                    </button>

                    <button type="button" @click="konfirmasiKembaliKeKasir()"
                        class="flex w-full items-center justify-center rounded-2xl border border-[#D9B08C] bg-white px-5 py-3 text-sm font-black text-[#7B4B2A] shadow-sm transition hover:bg-[#F8F5F0] hover:text-[#4B2E1F]">
                        Kembali ke Kasir
                    </button>
                </div>

                <p class="mt-4 text-xs font-semibold leading-relaxed text-[#7B4B2A]/70">
                    Karena memakai QRIS toko statis, sistem tidak menerima callback otomatis. Pastikan pembayaran sudah masuk sebelum menekan tombol konfirmasi.
                </p>

                <div x-show="status === 'waiting'" class="mt-5 border-t border-red-100 pt-5">
                    <div class="rounded-2xl border border-red-100 bg-red-50/60 p-4">
                        <h4 class="text-sm font-black text-red-700">
                            Zona Berbahaya
                        </h4>

                        <p class="mt-1 text-xs font-semibold leading-relaxed text-red-700/80">
                            Gunakan hanya jika pelanggan batal membayar atau transaksi salah dibuat.
                        </p>

                        <button type="button" @click="batalkanTransaksi()"
                            :disabled="isProcessing || isCancelling || isExpired"
                            class="mt-3 w-full rounded-2xl bg-red-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-red-700 active:scale-[0.98] disabled:cursor-not-allowed disabled:bg-red-200 disabled:text-red-700/60">
                            <span x-show="!isCancelling">Batalkan Transaksi</span>
                            <span x-show="isCancelling">Membatalkan...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function paymentApp(paymentId, transactionId, expiredAt) {
        return {
            status: 'waiting',
            countdown: '',
            interval: null,
            countdownInterval: null,
            expiredAt: new Date(expiredAt),
            isExpired: false,
            isProcessing: false,
            isCancelling: false,

            get statusLabel() {
                const labels = {
                    waiting: 'Menunggu Pembayaran',
                    paid: 'Pembayaran Berhasil',
                    expired: 'Kadaluarsa',
                    failed: 'Gagal',
                    cancelled: 'Dibatalkan',
                };

                return labels[this.status] || this.status;
            },

            init() {
                this.startCountdown();
                this.startPolling();
            },

            startCountdown() {
                this.countdownInterval = setInterval(() => {
                    const now = new Date();
                    const diff = this.expiredAt - now;

                    if (diff <= 0) {
                        this.countdown = '00:00';
                        clearInterval(this.countdownInterval);

                        if (this.status === 'waiting') {
                            this.handleExpired();
                        }

                        return;
                    }

                    const mins = Math.floor(diff / 60000);
                    const secs = Math.floor((diff % 60000) / 1000);

                    this.countdown = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
                }, 1000);
            },

            async handleExpired() {
                if (this.isExpired || this.status !== 'waiting') return;

                this.isExpired = true;
                this.countdown = '00:00';

                clearInterval(this.interval);
                clearInterval(this.countdownInterval);

                try {
                    const res = await fetch(`/transaksi/${transactionId}/expire`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        },
                        credentials: 'same-origin',
                    });

                    if (!res.ok && !res.redirected) {
                        throw new Error('Status expired gagal disimpan.');
                    }

                    this.status = 'expired';

                    await Swal.fire({
                        title: 'Waktu Pembayaran Habis',
                        html: `
                            <p>Transaksi QRIS otomatis ditandai sebagai <strong>expired</strong>.</p>
                            <p style="color: #666; font-size: 14px; margin-top: 8px;">
                                Status expired telah disimpan ke database.
                            </p>
                        `,
                        icon: 'error',
                        confirmButtonText: 'Kembali ke Kasir',
                        confirmButtonColor: '#7B4B2A',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                    });

                    window.location.href = '/kasir';
                } catch (e) {
                    this.status = 'expired';

                    await Swal.fire({
                        title: 'Waktu Pembayaran Habis',
                        html: `
                            <p>Batas waktu pembayaran telah habis.</p>
                            <p style="color: #dc2626; font-size: 14px; margin-top: 8px;">
                                Status expired gagal disimpan otomatis. Silakan cek transaksi dari riwayat.
                            </p>
                        `,
                        icon: 'warning',
                        confirmButtonText: 'Kembali ke Kasir',
                        confirmButtonColor: '#7B4B2A',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                    });

                    window.location.href = '/kasir';
                }
            },

            showSuccessModal() {
                Swal.fire({
                    title: 'Pembayaran Berhasil',
                    html: `
                        <div style="font-size: 72px; margin-bottom: 12px;">✅</div>

                        <p style="font-size: 18px; font-weight: bold; color: #15803d;">
                            Transaksi Selesai
                        </p>

                        <p style="color: #666; margin-top: 8px;">
                            Terima kasih. Pesanan sedang diproses.
                        </p>

                        <div style="margin-top: 15px; padding: 12px; background: #f0fdf4; border-radius: 12px;">
                            <p style="color: #166534; font-size: 14px;">
                                Silakan tunggu pesanan pelanggan.
                            </p>
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
                        window.location.href = `/payment/struk/${paymentId}`;
                    } else if (result.isDenied) {
                        window.location.href = '/kasir';
                    }
                });
            },

            startPolling() {
                this.interval = setInterval(async () => {
                    if (this.status !== 'waiting') {
                        clearInterval(this.interval);
                        return;
                    }

                    const res = await fetch(`/payment/check/${paymentId}`);
                    const data = await res.json();

                    this.status = data.status;

                    if (this.status === 'paid') {
                        clearInterval(this.interval);
                        clearInterval(this.countdownInterval);

                        this.showSuccessModal();
                        return;
                    }

                    if (this.status === 'expired' || this.status === 'cancelled' || this.status === 'failed') {
                        clearInterval(this.interval);
                        clearInterval(this.countdownInterval);
                        this.countdown = '00:00';
                    }
                }, 3000);
            },

            async konfirmasiKembaliKeKasir() {
                const result = await Swal.fire({
                    title: 'Kembali ke POS?',
                    html: `
                        <div style="text-align: left; line-height: 1.6;">
                            <p>
                                Transaksi QRIS ini akan tetap berstatus <strong>pending/waiting</strong>.
                            </p>

                            <p style="margin-top: 10px; color: #7B4B2A; font-size: 13px;">
                                Gunakan riwayat transaksi atau detail transaksi jika ingin melanjutkan pembayaran, membatalkan, menandai expired, atau menghapus transaksi.
                            </p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Kembali',
                    cancelButtonText: 'Tetap di QRIS',
                    confirmButtonColor: '#7B4B2A',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true,
                    focusCancel: true,
                });

                if (result.isConfirmed) {
                    window.location.href = '/kasir';
                }
            },

            async batalkanTransaksi() {
                if (this.status !== 'waiting' || this.isExpired) {
                    Swal.fire({
                        title: 'Tidak Bisa Dibatalkan',
                        text: 'Transaksi ini sudah tidak berada pada status menunggu pembayaran.',
                        icon: 'warning',
                        confirmButtonColor: '#7B4B2A',
                    });
                    return;
                }

                const result = await Swal.fire({
                    title: 'Batalkan Transaksi?',
                    html: `
                        <div style="text-align: left; line-height: 1.6;">
                            <p>
                                Transaksi QRIS ini akan dibatalkan.
                            </p>

                            <div style="padding: 12px; background: #fee2e2; border-radius: 12px; margin-top: 10px;">
                                <p style="margin: 0; font-size: 13px; color: #991b1b;">
                                    <strong>Kode Transaksi:</strong><br>
                                    {{ $payment->transaction->kode_transaksi }}
                                </p>
                                <p style="margin: 8px 0 0; font-size: 13px; color: #991b1b;">
                                    <strong>Total:</strong><br>
                                    Rp {{ number_format($payment->transaction->grand_total, 0, ',', '.') }}
                                </p>
                            </div>

                            <p style="margin-top: 12px; color: #dc2626; font-size: 13px; font-weight: 700;">
                                Setelah dibatalkan, status transaksi menjadi cancelled/cancelled dan transaksi tidak dapat dikonfirmasi sebagai pembayaran sukses.
                            </p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Batalkan',
                    cancelButtonText: 'Jangan Batalkan',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#7B4B2A',
                    reverseButtons: true,
                    focusCancel: true,
                });

                if (!result.isConfirmed) return;

                this.isCancelling = true;

                try {
                    const res = await fetch(`/transaksi/${transactionId}/cancel`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        },
                        credentials: 'same-origin',
                    });

                    if (res.ok || res.redirected) {
                        this.status = 'cancelled';

                        clearInterval(this.interval);
                        clearInterval(this.countdownInterval);

                        await Swal.fire({
                            title: 'Transaksi Dibatalkan',
                            text: 'Transaksi QRIS berhasil dibatalkan.',
                            icon: 'success',
                            confirmButtonText: 'Kembali ke Kasir',
                            confirmButtonColor: '#7B4B2A',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                        });

                        window.location.href = '/kasir';
                    } else {
                        this.isCancelling = false;

                        Swal.fire({
                            title: 'Gagal',
                            text: 'Transaksi gagal dibatalkan.',
                            icon: 'error',
                            confirmButtonColor: '#7B4B2A',
                        });
                    }
                } catch (e) {
                    this.isCancelling = false;

                    Swal.fire({
                        title: 'Error',
                        text: 'Terjadi kesalahan saat membatalkan transaksi.',
                        icon: 'error',
                        confirmButtonColor: '#7B4B2A',
                    });
                }
            },

            async konfirmasiBayar() {
                if (this.status !== 'waiting' || this.isExpired) {
                    Swal.fire({
                        title: 'Tidak Bisa Dikonfirmasi',
                        text: 'Pembayaran ini sudah tidak berada pada status menunggu pembayaran.',
                        icon: 'warning',
                        confirmButtonColor: '#7B4B2A',
                    });
                    return;
                }

                const result = await Swal.fire({
                    title: 'Konfirmasi Pembayaran QRIS?',
                    html: `
                        <div style="text-align: left; line-height: 1.6;">
                            <p style="margin-bottom: 10px;">
                                Pastikan pembayaran QRIS pelanggan sudah benar-benar masuk sebelum melanjutkan.
                            </p>

                            <div style="padding: 12px; background: #FFF3E4; border-radius: 12px; margin-top: 10px;">
                                <p style="margin: 0; font-size: 13px; color: #7B4B2A;">
                                    <strong>Kode Transaksi:</strong><br>
                                    {{ $payment->transaction->kode_transaksi }}
                                </p>
                                <p style="margin: 8px 0 0; font-size: 13px; color: #7B4B2A;">
                                    <strong>Total:</strong><br>
                                    Rp {{ number_format($payment->transaction->grand_total, 0, ',', '.') }}
                                </p>
                            </div>

                            <p style="margin-top: 12px; color: #dc2626; font-size: 13px; font-weight: 700;">
                                Setelah dikonfirmasi, transaksi akan dianggap lunas, status berubah menjadi success/paid, dan stok menu akan dikurangi.
                            </p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Pembayaran Sudah Masuk',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#7B4B2A',
                    reverseButtons: true,
                    focusCancel: true,
                });

                if (!result.isConfirmed) return;

                this.isProcessing = true;

                try {
                    const res = await fetch(`/payment/success/${paymentId}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    });

                    const data = await res.json();

                    if (data.success) {
                        this.status = 'paid';

                        clearInterval(this.interval);
                        clearInterval(this.countdownInterval);

                        this.showSuccessModal();
                    } else {
                        this.isProcessing = false;

                        Swal.fire({
                            title: 'Gagal',
                            text: data.message || 'Pembayaran gagal dikonfirmasi.',
                            icon: 'error',
                            confirmButtonColor: '#7B4B2A',
                        });
                    }
                } catch (e) {
                    this.isProcessing = false;

                    Swal.fire({
                        title: 'Error',
                        text: 'Terjadi kesalahan saat mengonfirmasi pembayaran.',
                        icon: 'error',
                        confirmButtonColor: '#7B4B2A',
                    });
                }
            },
        }
    }
</script>
@endpush