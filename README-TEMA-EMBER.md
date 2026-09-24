# JIMNY Coffee: tema Ember dan animasi

Tema ini melanjutkan versi kasir ringkas. Palet mengikuti referensi: oranye bata #BC430D, cokelat gelap #241705, kartu krem, dan aksen emas. Tema berlaku pada layout aplikasi dan autentikasi: kasir, dashboard, transaksi, laporan, presensi, halaman pengelolaan, login, serta formulir akun. Struk tetap memakai format hitam-putih agar hasil cetak terbaca dan hemat tinta.

## Pemasangan di proyek aktif

Cadangkan proyek sebelum memperbarui. Dari ZIP ini, salin:

- Seluruh folder `resources/views`.
- `public/css/pos-theme.css` dan `public/css/ember-theme.css`.
- `public/js/pos-motion.js`.
- Folder `tests/ui` bila ingin menjalankan tes JavaScript.

Jangan menimpa `.env` atau database aktif yang telah berubah. Jalankan dari folder berisi `artisan`:

```bat
php artisan view:clear
```

Kemudian muat ulang browser dengan Ctrl+Shift+R. Tidak ada migrasi baru dan tidak perlu build Vite untuk pembaruan tema/animasi ini. CSS dan JavaScript tambahan tersedia sebagai aset lokal. Versi URL aset sudah dinaikkan ke v4.

## Salinan lengkap

ZIP langsung berisi file proyek tanpa lapisan folder Platform tambahan. Ekstrak ke satu folder, misalnya C:\laragon\www\Platform. `artisan` dan `package.json` harus berada langsung di sana. Untuk `platform.test`, arahkan document root ke folder `public`. Alternatif: jalankan `php artisan serve` dan buka alamat yang muncul.

Paket lengkap menyertakan vendor, node_modules, aset build, serta konfigurasi/database dari sumber yang tersedia di percakapan. Node_modules bersifat khusus lingkungan; jalankan npm ci jika hendak mengembangkan Vite pada sistem berbeda. Tidak ada data baru dari instalasi lokal Anda yang otomatis masuk ke ZIP ini.

## Ukuran layar dan katalog

Target desktop adalah 1920 × 1080, zoom browser 100%. Kapasitas dihitung dari area konten yang benar-benar tersedia setelah toolbar browser dan panel aplikasi. Pada ruang katalog sekitar 1530 × 700 piksel, 42 menu muat dalam satu halaman. Pencarian dan kategori mencakup seluruh katalog. Jika ruang lebih kecil, pagination menjaga keterbacaan. Tombol Layar penuh menambah ruang kerja. Ponsel tetap memakai susunan vertikal.

Presensi berada di Menu → Presensi & izin. Pada desktop dialog muncul sebagai panel di sisi kanan, tanpa meninggalkan kasir. Tombol clock in/out atau izin menutup panel sebelum membuka konfirmasi tindakan. Pesanan yang belum dibayar tetap berada dalam memori halaman; jangan memuat ulang halaman untuk membuka presensi.

## Animasi

- Kartu produk muncul bertahap saat halaman/filter berubah.
- Gelombang klik, sorotan kartu, dan indikator bergerak menuju keranjang.
- Jika keranjang berada di luar layar, konfirmasi memakai respons lokal pada kartu.
- Judul keranjang merespons kedatangan indikator.
- Angka total bertransisi saat isi pesanan berubah. Nilai transaksi diperbarui langsung, tanpa menunggu animasi.
- Panel presensi bergeser masuk; dropdown memudar masuk/keluar.
- Grafik tren digambar bertahap dan batang analisis tumbuh ketika terlihat.
- Partikel dibatasi maksimal delapan sekaligus dan dibuang setelah selesai.
- Preferensi sistem Reduce Motion mematikan efek tambahan. Animasi menggunakan Web Animations API, tanpa dependensi animasi eksternal.

## Verifikasi

Pemeriksaan sintaks JavaScript, tes logika kasir, dan tes pengelolaan efek animasi lulus:

```bat
node tests/ui/compact-pos.cjs
node tests/ui/motion.cjs
```

Tes mencakup kapasitas 42 menu pada ruang desktop besar, kelengkapan pagination, pencarian apostrof, kategori, data kosong, batas stok, jumlah item, subtotal, pajak, Reduce Motion, pembatasan partikel saat klik cepat, dan pembersihan efek.

PHP tidak tersedia di lingkungan pengerjaan. Kompilasi Blade, integrasi Laravel, dan alur pembayaran/presensi belum diuji dengan runtime PHP. Browser pengujian menolak alamat pratinjau karena kebijakan keamanan sehingga tampilan akhir belum diverifikasi melalui browser. Periksa langsung pada layar kasir Anda, lalu uji alur operasional pada database uji sebelum penggunaan produksi.
