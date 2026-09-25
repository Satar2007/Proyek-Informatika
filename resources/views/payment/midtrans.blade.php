@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div class="rounded-3xl border border-amber-200 bg-white p-6 shadow-sm">
        <div class="mb-5">
            <p class="text-sm font-semibold text-amber-800">
                SATAR - Midtrans Sandbox
            </p>

            <h1 class="mt-2 text-3xl font-black text-amber-950">
                Pembayaran QRIS
            </h1>

            <p class="mt-2 text-sm text-gray-600">
                Ini adalah simulasi pembayaran. Tidak menggunakan uang sungguhan.
            </p>
        </div>

        <div class="space-y-4 rounded-2xl bg-amber-50 p-5">
            <div>
                <p class="text-sm text-gray-600">Kode transaksi</p>
                <p class="font-mono font-bold text-amber-950">
                    {{ $payment->transaction->kode_transaksi }}
                </p>
            </div>

            <div>
                <p class="text-sm text-gray-600">Pelanggan</p>
                <p class="font-bold text-amber-950">
                    {{ $payment->transaction->nama_pelanggan ?? 'Umum' }}
                </p>
            </div>

            <div class="border-t border-amber-200 pt-4">
                <p class="text-sm text-gray-600">Total pembayaran</p>
                <p class="mt-1 text-3xl font-black text-amber-950">
                    Rp {{ number_format($payment->transaction->grand_total, 0, ',', '.') }}
                </p>
            </div>
        </div>

        <div class="mt-6 space-y-4">
            <p
                id="payment-status"
                class="rounded-xl bg-gray-100 px-4 py-3 text-sm font-semibold text-gray-700"
                role="status"
            >
                Status: {{ $payment->payment_status }}
            </p>

            @if ($payment->payment_status === 'waiting')
                <button
                    id="pay-button"
                    type="button"
                    class="w-full rounded-2xl bg-amber-800 px-5 py-4 font-bold text-white hover:bg-amber-900 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Buka Pembayaran Midtrans
                </button>
            @elseif ($payment->payment_status === 'paid')
                <a
                    href="{{ route('payment.struk', $payment->id) }}"
                    class="block rounded-2xl bg-green-700 px-5 py-4 text-center font-bold text-white"
                >
                    Lihat Struk
                </a>
            @else
                <p class="text-sm text-gray-600">
                    Pembayaran tidak tersedia pada status saat ini.
                </p>
            @endif

            <a
                href="{{ route('kasir.index') }}"
                class="block text-center text-sm font-semibold text-amber-900 underline"
            >
                Kembali ke Kasir
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script
    src="{{ config('midtrans.snap_js_url') }}"
    data-client-key="{{ config('midtrans.client_key') }}">
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tokenUrl = {{ \Illuminate\Support\Js::from(route('payment.token', $payment->id)) }};
    const checkUrl = {{ \Illuminate\Support\Js::from(route('payment.check', $payment->id)) }};
    const strukUrl = {{ \Illuminate\Support\Js::from(route('payment.struk', $payment->id)) }};
    const csrfToken = {{ \Illuminate\Support\Js::from(csrf_token()) }};

    const payButton = document.getElementById('pay-button');
    const statusElement = document.getElementById('payment-status');

    let preparing = false;
    let finished = false;

    function tampilkanStatus(pesan) {
        if (statusElement) {
            statusElement.textContent = pesan;
        }
    }

    function aktifkanTombol() {
        if (payButton && !finished && !preparing) {
            payButton.disabled = false;
        }
    }

    async function cekStatus() {
        try {
            const response = await fetch(checkUrl, {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error('Gagal mengambil status pembayaran.');
            }

            const data = await response.json();

            if (data.status === 'paid') {
                finished = true;
                tampilkanStatus('Pembayaran berhasil diverifikasi.');

                if (payButton) {
                    payButton.disabled = true;
                }

                clearInterval(intervalStatus);
                window.location.href = strukUrl;
            } else if (['expired', 'failed', 'cancelled'].includes(data.status)) {
                finished = true;
                tampilkanStatus('Pembayaran berstatus: ' + data.status);

                if (payButton) {
                    payButton.disabled = true;
                }

                clearInterval(intervalStatus);
            } else if (!preparing) {
                tampilkanStatus('Menunggu pembayaran atau konfirmasi dari Midtrans.');
            }
        } catch (error) {
            if (!preparing) {
                tampilkanStatus('Status belum dapat diperiksa. Silakan tunggu.');
            }
        }
    }

    if (payButton) {
        payButton.addEventListener('click', async () => {
            if (preparing || finished) {
                return;
            }

            if (!window.snap) {
                tampilkanStatus(
                    'Midtrans Snap belum termuat. Coba muat ulang halaman.'
                );
                return;
            }

            preparing = true;
            payButton.disabled = true;
            tampilkanStatus('Menyiapkan pembayaran Midtrans...');

            try {
                // Token baru diminta setelah kasir mengeklik tombol.
                const response = await fetch(tokenUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                const data = await response.json();

                if (
                    !response.ok ||
                    !data.success ||
                    !data.snap_token
                ) {
                    throw new Error(
                        data.message || 'Pembayaran Midtrans belum dapat dibuka.'
                    );
                }

                // Callback browser bukan bukti pembayaran lunas.
                // Status akhir tetap diperiksa melalui backend SATAR.
                window.snap.pay(data.snap_token, {
                    onSuccess: function () {
                        tampilkanStatus(
                            'Pembayaran selesai di Snap. Menunggu verifikasi server.'
                        );
                        cekStatus();
                    },
                    onPending: function () {
                        tampilkanStatus(
                            'Pembayaran masih menunggu penyelesaian.'
                        );
                        cekStatus();
                    },
                    onError: function () {
                        tampilkanStatus(
                            'Terjadi kendala pembayaran. Periksa status transaksi.'
                        );
                        cekStatus();
                    },
                    onClose: function () {
                        // Menutup Snap bukan berarti membatalkan transaksi.
                        aktifkanTombol();
                        cekStatus();
                    }
                });
            } catch (error) {
                tampilkanStatus(
                    error.message || 'Pembayaran Midtrans belum dapat dibuka.'
                );
            } finally {
                preparing = false;
                aktifkanTombol();
            }
        });
    }

    const intervalStatus = setInterval(cekStatus, 3000);
});
</script>
@endpush