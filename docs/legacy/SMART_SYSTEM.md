# JIMNY COFFEE Smart Assistant

Sistem cerdas ini bukan menu baru. Ia menjadi lapisan bantuan yang muncul sebagai tombol bot di seluruh halaman yang membutuhkan autentikasi.

## Per role
- Kasir: bantuan stok, menu terlaris, rekomendasi menu tambahan, omzet transaksi akun, dan ringkasan operasional.
- Admin: bantuan stok/restock, tren menu, jam ramai, dan penjualan.
- Owner: insight omzet, tren penjualan, jam ramai, rekomendasi pasangan menu, dan prediksi omzet.

## Mesin analitik
- Low-stock detection berdasarkan `stok <= minimum_stok`.
- Top menu berdasarkan jumlah `qty` pada transaksi sukses.
- Peak hour berdasarkan jumlah transaksi sukses 30 hari.
- Forecast menggunakan Weighted Moving Average dari omzet harian 7 hari.
- Rekomendasi pasangan menu berdasarkan frekuensi dua menu muncul dalam transaksi yang sama selama 60 hari.

Tidak ada tabel database baru. Fitur membaca data POS yang sudah ada.
