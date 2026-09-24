# JIMNY Coffee POS: pembaruan UI dan analisis

## Perubahan

- Tema sage, krem, biru lembut, dan lilac dengan gradasi tipis.
- Latar POS bersih tanpa pola foto berulang.
- Kartu menu berwarna pastel, teks gelap, dan penanda stok.
- Kartu menu mendukung keyboard. Nama menu dengan tanda kutip aman untuk ekspresi Alpine.
- Navigasi ponsel, tombol menuju keranjang, fokus keyboard, dan pesan pencarian kosong.
- Keranjang tetap terlihat saat menggulir pada desktop.
- Laporan harian dan bulanan memakai halaman analisis yang sama.
- Grafik tren per jam/per tanggal, pilihan pendapatan atau jumlah transaksi, grafik batang metode pembayaran, dan lima menu terlaris.
- Ringkasan pendapatan, jumlah transaksi, nilai rata-rata transaksi, dan perubahan terhadap periode sebelumnya.
- Filter tervalidasi, keadaan data kosong, tabel pendamping grafik, tautan detail, dan cetak lewat browser.
- Daftar transaksi harian menggunakan pagination 15 baris.

## Menjalankan pembaruan pada instalasi yang sudah ada

1. Cadangkan proyek dan database Anda.
2. Salin file berikut dari ZIP ke proyek aktif:
   - `app/Http/Controllers/LaporanController.php`
   - `resources/views/layouts/app.blade.php`
   - `resources/views/kasir/index.blade.php`
   - `resources/views/laporan/report.blade.php`
   - `public/css/pos-theme.css`
   - `tests/Feature/LaporanAnalyticsTest.php`
3. Jalankan `php artisan view:clear` dari direktori proyek.
4. Muat ulang browser dengan Ctrl+Shift+R.

Tidak ada migrasi database baru. Tema tambahan menggunakan CSS lokal, sehingga pembaruan ini tidak memerlukan npm build. Aset build bawaan tetap disertakan. Jangan menimpa `.env` atau database instalasi aktif dengan salinan dari ZIP apabila data instalasi Anda sudah berubah.

## Menjalankan salinan lengkap

Gunakan versi PHP dan ekstensi sesuai `composer.json` serta `composer.lock` proyek. Jalankan `composer install` jika vendor bawaan tidak sesuai lingkungan Anda. Sesuaikan `.env` dengan koneksi database lokal Anda, lalu jalankan `php artisan view:clear` dan `php artisan serve`. Konfigurasi dan database dari lampiran tetap dipertahankan. Tidak ada transaksi contoh yang ditambahkan ke database.

## Definisi analisis

- Sumber: transaksi dengan `status = success`.
- Periode: `created_at`, mengikuti definisi laporan sebelumnya dan konfigurasi waktu aplikasi. Grafik bukan pengelompokan berdasarkan `paid_at`.
- Pendapatan: jumlah `grand_total`, sudah mencakup pajak dan diskon transaksi. Ini bukan laba.
- Rata-rata transaksi: pendapatan dibagi jumlah transaksi sukses.
- Perbandingan: satu hari atau satu bulan kalender sebelumnya. Jika pendapatan sebelumnya nol, persentase tampil sebagai belum tersedia. Periode berjalan dibandingkan dengan seluruh periode sebelumnya, bukan durasi berjalan yang setara.
- Menu terlaris: jumlah `qty` pada detail transaksi sukses. Nilai penjualan menu menggunakan `subtotal` sebelum pajak/diskon transaksi. Nama menu mengikuti katalog saat ini; menu yang tidak dapat ditemukan diberi label pengganti.
- Tren menyertakan semua 24 jam atau semua tanggal dalam bulan, termasuk nilai nol. Saat nilai tertinggi sama, ringkasan memilih slot paling awal.
- Grafik menggunakan SVG dan CSS lokal. Grafik tidak membutuhkan layanan eksternal atau pustaka grafik tambahan.
- Laporan bulanan tetap mengambil ringkasan setiap transaksi dalam periode ke memori untuk membentuk tren. Untuk jutaan transaksi per bulan, pertimbangkan agregasi database atau tabel ringkasan berkala.
- Cetak memakai fitur browser. Daftar harian yang tercetak mengikuti halaman pagination aktif; grafik dan ringkasan mencakup seluruh tanggal terpilih.

## Verifikasi dan batas pengujian

- Pemeriksaan sintaks JavaScript POS dengan `node --check`: lulus setelah ekspresi Blade diganti placeholder khusus pemeriksaan.
- Database dan `.env`: identik dengan lampiran sumber.
- Aset CSS baru dimuat setelah aset bawaan, tanpa perubahan manifest Vite.
- Pemeriksaan browser langsung tidak dapat dijalankan karena browser memblokir akses ke server pratinjau lokal.
- PHP tidak tersedia di lingkungan pengerjaan. Kompilasi Blade, query database, dan pengujian Laravel belum dijalankan. Tidak ada klaim bahwa seluruh alur transaksi sudah lolos pengujian runtime.

Tersedia tes regresi untuk laporan: transaksi sukses vs pending/batal, batas tanggal, jam kosong, metode pembayaran, menu terlaris, pergantian tahun, Februari tahun kabisat, filter tidak valid, dan autentikasi. Jalankan pada lingkungan pengujian dengan database SQLite sementara:

```bash
php artisan test --filter=LaporanAnalyticsTest
```

Uji penerimaan pada database uji: masuk sebagai admin/kasir/owner, buka POS pada desktop dan ponsel, cari menu dengan apostrof, tambah/kurangi item, periksa stok habis, jalankan pembayaran tunai/QRIS sesuai konfigurasi proyek, kemudian cocokkan laporan dengan transaksi sukses. Jangan memakai transaksi produksi untuk pengujian.
