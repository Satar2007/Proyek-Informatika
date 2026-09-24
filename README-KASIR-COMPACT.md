# Pembaruan kasir ringkas

## Cara memasang pada proyek aktif

Salin hanya empat file berikut ke lokasi yang sama di proyek Anda:

- `resources/views/kasir/index.blade.php`
- `resources/views/layouts/app.blade.php`
- `public/css/pos-theme.css`
- `tests/ui/compact-pos.cjs` (opsional, untuk menjalankan tes)

Dari folder yang berisi `artisan`, jalankan:

```bat
php artisan view:clear
```

Lalu tekan Ctrl+Shift+R di browser. Pembaruan ini tidak memerlukan npm build atau migrasi database. Jangan timpa `.env` dan database instalasi aktif Anda.

ZIP ini langsung berisi file proyek tanpa folder `Platform` tambahan. Jika menggunakan salinan lengkap, ekstrak seluruh isinya ke satu folder proyek, misalnya `C:\laragon\www\Platform`. Arahkan document root Laragon ke `C:\laragon\www\Platform\public`, atau jalankan `php artisan serve` dari folder proyek dan buka alamat yang ditampilkan terminal.

Aset `public/build` dan dependensi PHP `vendor` disertakan. Folder `node_modules` tidak disertakan karena dependensinya bergantung pada sistem operasi. Jika hendak mengembangkan aset dengan Vite, jalankan `npm ci` pada proyek Anda terlebih dahulu.

## Perilaku baru

- Presensi, clock in/out, dan pengajuan izin berada pada Menu → Presensi & izin.
- Di halaman kasir, presensi membuka dialog tanpa berpindah halaman. Pesanan yang sedang disusun tetap ada.
- Dari halaman lain, menu presensi mengarah ke kasir dan membuka dialog presensi otomatis.
- Kartu produk menampilkan kategori, stok, nama, dan harga. Deskripsi tersedia pada tooltip bawaan browser saat pointer diarahkan ke kartu.
- Pada desktop mulai lebar 1024 piksel, area katalog mengikuti tinggi layar. Kolom dan kapasitas halaman mengikuti ukuran ruang yang tersedia.
- Semua 42 menu dapat tampil sekaligus jika layar cukup besar. Saat ruang lebih kecil atau zoom diperbesar, gunakan tombol halaman. Tidak ada menu yang disembunyikan permanen.
- Pencarian dan kategori mencakup seluruh katalog, termasuk menu pada halaman lain. Mengubah filter mengembalikan halaman ke awal.
- Tombol Layar penuh menggunakan fitur fullscreen browser. Tekan Escape atau tombol Keluar layar penuh untuk kembali.
- Daftar pesanan memiliki scroll sendiri saat banyak item. Pada layar pendek, panel pesanan tetap dapat digulir untuk menjangkau pembayaran.
- Ponsel memakai susunan vertikal dengan kartu ringkas dan halaman katalog. Scroll tetap tersedia agar tombol dan teks dapat digunakan dengan nyaman.
- Animasi ringan pada kartu, penambahan baris pesanan, dropdown, dan dialog. Penambahan produk memberi sorotan dan notifikasi singkat tanpa perlu ditutup.
- Preferensi sistem Reduce Motion mematikan animasi tambahan.
- Logika pembayaran, laporan analisis, endpoint presensi, pajak, dan batas stok tetap memakai alur proyek yang ada.

## Pemeriksaan

Jalankan:

```bat
node tests/ui/compact-pos.cjs
```

Tes logika JavaScript lulus untuk 42 produk dalam ruang desktop besar, kelengkapan pagination pada ruang sempit, pencarian berapostrof, kategori, hasil kosong, batas stok, perubahan jumlah, subtotal, dan pajak. Pemeriksaan sintaks JavaScript juga lulus.

PHP tidak tersedia di lingkungan pengerjaan, sehingga Blade dan integrasi Laravel belum diuji dengan runtime PHP. Pemeriksaan browser lokal sebelumnya terhalang akses browser ke server lokal. Lakukan pemeriksaan visual pada resolusi layar Anda serta uji presensi dan pembayaran menggunakan database uji sebelum memakai versi ini untuk operasional.
