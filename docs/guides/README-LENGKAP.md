<div align="center">

<img src="../assets/jimny-coffee-brown-hero.gif" alt="JIMNY COFFEE hero gradasi cokelat dengan emblem mengambang" width="100%" />

# JIMNY COFFEE

<img src="https://readme-typing-svg.demolab.com?font=Fira+Code&amp;weight=600&amp;size=23&amp;duration=3300&amp;pause=1200&amp;color=B78C62&amp;center=true&amp;vCenter=true&amp;width=820&amp;height=48&amp;lines=Smart+Point+of+Sale;Coffee+Shop+Management;Sales+Analytics+%26+Smart+Assistant+V2" alt="JIMNY COFFEE animated tagline" />

Sistem informasi kasir dan pengelolaan operasional kedai kopi berbasis web, dengan antarmuka khusus **Owner**, **Admin**, dan **Kasir**.

![Laravel](https://img.shields.io/badge/Laravel-PHP-FF2D20?logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/Database-MySQL-4479A1?logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/UI-Tailwind_CSS-06B6D4?logo=tailwindcss&logoColor=white)
![GitHub](https://img.shields.io/badge/Project-Kolaborasi-181717?logo=github&logoColor=white)

[**Fitur**](#fitur-aplikasi) · [**Preview**](#preview-antarmuka) · [**Instalasi**](#menjalankan-project) · [**Dokumentasi**](#dokumentasi-pendukung-arsip)

</div>

---

## Sekilas Project

<p align="center"><img src="../assets/readme-brown/sekilas-project.png" width="100%" alt="01 / Overview - Jimny Coffee brown theme" /></p>

<img src="../assets/jimny-coffee-brown-dashboard.png" alt="Ringkasan empat modul sistem JIMNY COFFEE" width="100%" />

**JIMNY COFFEE** adalah project aplikasi Point of Sale (POS) berbasis Laravel untuk membantu pengelolaan pesanan, transaksi, menu, stok, laporan penjualan, dan aktivitas karyawan dalam satu sistem. Tampilan aplikasi menggunakan nuansa warna kopi dan menyediakan halaman sesuai peran pengguna.

> **Status repository:** Fitur utama JIMNY COFFEE dan Smart Assistant V2 sudah terintegrasi ke branch `main`. Analitik penjualan telah tersedia; model prediksi machine learning belum diimplementasikan dan masih direncanakan.

## Fitur Aplikasi

<p align="center"><img src="../assets/readme-brown/fitur-aplikasi.png" width="100%" alt="02 / Core features - Jimny Coffee brown theme" /></p>

| Peran | Area yang ditampilkan dalam project |
| :-- | :-- |
| **Kasir** | Pemilihan menu & pesanan, pencatatan transaksi, pilihan pembayaran Cash/QRIS, presensi & izin, riwayat transaksi, laporan penjualan. |
| **Admin** | Dashboard, pengelolaan menu, log stok, pengaturan shift, pengajuan izin, pengelolaan akun, dan riwayat transaksi. |
| **Owner** | Dashboard ringkasan bisnis, laporan penjualan, riwayat transaksi, dan rekap kehadiran. |
| **Smart Insight** | Panel informasi kontekstual yang muncul pada antarmuka sebagai pendamping aktivitas pengguna. |

## Preview Antarmuka

<p align="center"><img src="../assets/readme-brown/preview-antarmuka.png" width="100%" alt="03 / Screenshots - Jimny Coffee brown theme" /></p>

### Halaman Login

<img src="../screenshots/01-login.webp" alt="Halaman login JIMNY COFFEE" width="100%" />

### Kasir / Point of Sale

<img src="../screenshots/02-kasir-pos.webp" alt="Tampilan kasir dan pencatatan pesanan" width="100%" />

### Dashboard Owner

<img src="../screenshots/06-owner-dashboard.webp" alt="Dashboard Owner: ringkasan omzet, transaksi, dan menu terlaris" width="100%" />

### Dashboard Admin

<img src="../screenshots/11-admin-dashboard.webp" alt="Dashboard Admin" width="100%" />

### Menu, Stok & Shift

| Kelola menu | Log stok |
| :--: | :--: |
| <img src="../screenshots/13-admin-kelola-menu.webp" alt="Pengelolaan menu" width="100%" /> | <img src="../screenshots/14-admin-log-stok.webp" alt="Log stok" width="100%" /> |

| Kelola shift | Kelola akun |
| :--: | :--: |
| <img src="../screenshots/15-admin-kelola-shift.webp" alt="Pengelolaan shift" width="100%" /> | <img src="../screenshots/17-admin-kelola-akun.webp" alt="Pengelolaan akun" width="100%" /> |

### Laporan & Aktivitas

| Laporan penjualan | Riwayat transaksi |
| :--: | :--: |
| <img src="../screenshots/07-owner-laporan-penjualan.webp" alt="Laporan penjualan Owner" width="100%" /> | <img src="../screenshots/09-owner-riwayat-transaksi.webp" alt="Riwayat transaksi Owner" width="100%" /> |

<details>
<summary><strong>Lihat galeri tampilan lainnya (9 screenshot)</strong></summary>

<br />

| Tampilan | Preview |
| :-- | :-- |
| Presensi & izin Kasir | <img src="../screenshots/03-kasir-presensi-izin.webp" alt="Presensi dan izin Kasir" width="100%" /> |
| Riwayat transaksi Kasir | <img src="../screenshots/04-kasir-riwayat-transaksi.webp" alt="Riwayat transaksi Kasir" width="100%" /> |
| Laporan penjualan Kasir | <img src="../screenshots/05-kasir-laporan-penjualan.webp" alt="Laporan penjualan Kasir" width="100%" /> |
| Analitik penjualan Owner | <img src="../screenshots/08-owner-analitik-penjualan.webp" alt="Analitik penjualan Owner" width="100%" /> |
| Rekap kehadiran Owner | <img src="../screenshots/10-owner-rekap-kehadiran.webp" alt="Rekap kehadiran Owner" width="100%" /> |
| POS Admin | <img src="../screenshots/12-admin-pos.webp" alt="POS Admin" width="100%" /> |
| Pengajuan izin Admin | <img src="../screenshots/16-admin-pengajuan-izin.webp" alt="Pengajuan izin Admin" width="100%" /> |
| Riwayat transaksi Admin | <img src="../screenshots/18-admin-riwayat-transaksi.webp" alt="Riwayat transaksi Admin" width="100%" /> |
| Analitik penjualan Admin | <img src="../screenshots/19-admin-analitik-penjualan.webp" alt="Analitik penjualan Admin" width="100%" /> |

</details>

> Screenshot menunjukkan antarmuka pada saat pengambilan gambar. Angka penjualan, stok, dan transaksi yang terlihat merupakan contoh tampilan, bukan data operasional terkini.

## Teknologi

<p align="center"><img src="../assets/readme-brown/teknologi.png" width="100%" alt="04 / Technology - Jimny Coffee brown theme" /></p>

<p align="center"><img src="../assets/readme-brown/technology-stack.png" width="100%" alt="Technology stack cards" /></p>

- **Backend:** PHP & Laravel
- **Database:** MySQL
- **Frontend:** Blade, Tailwind CSS, CSS, dan JavaScript
- **Build tools:** Composer, NPM, dan Vite
- **Kolaborasi:** Git & GitHub (branch dan pull request)

## Menjalankan Project

<p align="center"><img src="../assets/readme-brown/menjalankan-project.png" width="100%" alt="05 / Setup - Jimny Coffee brown theme" /></p>

<p align="center"><img src="../assets/readme-brown/installation-roadmap.png" width="100%" alt="Installation roadmap" /></p>

> Setelah seluruh source code tersedia, ikuti langkah berikut untuk
> menyiapkan project JIMNY COFFEE pada komputer lokal. Pastikan PHP,
> Composer, Node.js/NPM, Laragon, dan MySQL telah terpasang.

------------------------------------------------------------------------

### Persyaratan Sistem

Pastikan perangkat memiliki:

  Komponen        Keterangan
  --------------- -------------------------------
  PHP             Sesuai kebutuhan Laravel
  Composer        Dependency Laravel
  Node.js & NPM   Build frontend
  Laragon         Local development environment
  MySQL           Database aplikasi
  Git             Source code management

Cek instalasi:

``` bash
php -v
composer -v
node -v
npm -v
mysql --version
```

------------------------------------------------------------------------

### 1. Menyalakan Laragon

Buka aplikasi **Laragon**.

Pastikan:

MySQL aktif\
Apache/Nginx dapat diaktifkan jika diperlukan

Laragon digunakan untuk menyediakan environment Laravel dan database
MySQL lokal.

------------------------------------------------------------------------

### 2. Masuk ke Folder Project

Buka PowerShell atau Terminal.

Contoh:

``` powershell
Set-Location "C:\laragon\www\Satar"
```

Pastikan berada pada folder utama project yang berisi:

    artisan
    composer.json
    package.json
    .env.example

------------------------------------------------------------------------

### 3. Install Dependency Project

Install dependency backend Laravel:

``` bash
composer install
```

Install dependency frontend:

``` bash
npm install
```

------------------------------------------------------------------------

### 4. Konfigurasi Environment (.env)

Jika file `.env` belum tersedia, buat dari `.env.example`.

Windows PowerShell:

``` powershell
if (-not (Test-Path '.env')) {
    Copy-Item '.env.example' '.env'
}
```

Generate application key:

``` bash
php artisan key:generate
```

------------------------------------------------------------------------

### 5. Konfigurasi Database

Buka file:

    .env

Atur konfigurasi database:

``` env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=satar_integrated
DB_USERNAME=root
DB_PASSWORD=
```

Catatan:

-   Pada Laragon default, username MySQL biasanya `root`
-   Password biasanya kosong
-   Jika MySQL menggunakan password, sesuaikan dengan konfigurasi lokal

------------------------------------------------------------------------

### 6. Membuat Database Lokal

Buka salah satu:

-   HeidiSQL
-   SQLyog
-   phpMyAdmin Laragon

Buat database:

``` sql
CREATE DATABASE satar_integrated
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Tidak perlu membuat tabel secara manual karena tabel dibuat melalui
migration Laravel.

------------------------------------------------------------------------

### 7. Membersihkan Cache Laravel

Setelah mengubah `.env`, jalankan:

``` bash
php artisan optimize:clear
```

------------------------------------------------------------------------

### 8. Verifikasi Database Laravel

Pastikan Laravel membaca database yang benar:

``` bash
php artisan tinker --execute="echo config('database.connections.mysql.database');"
```

Output yang benar:

    satar_integrated

Jika hasil berbeda, periksa kembali file `.env`.

------------------------------------------------------------------------

### 9. Migration dan Seeder

Buat tabel database dan data awal aplikasi:

``` bash
php artisan migrate --seed
```

Seeder yang dijalankan:

-   CategorySeeder
-   MenuSeeder
-   UserSeeder

------------------------------------------------------------------------

### Konfigurasi Midtrans Sandbox

<p align="center"><img src="../assets/readme-brown/midtrans-sandbox.png" width="100%" alt="Midtrans Sandbox" /></p>

#### Penting

Fitur pembayaran QRIS **tidak dapat digunakan apabila pemilik project
belum membuat credential Midtrans sendiri.**

Pemilik project harus membuat akun Midtrans dan memasukkan:

-   Server Key
-   Client Key

ke dalam file `.env`.

Dashboard Midtrans:

    https://dashboard.midtrans.com

Tambahkan:

``` env
MIDTRANS_IS_PRODUCTION=false

MIDTRANS_SERVER_KEY=isi_server_key_midtrans
MIDTRANS_CLIENT_KEY=isi_client_key_midtrans
```

Jangan membagikan:

-   Server Key
-   Client Key
-   APP_KEY
-   File `.env`

ke repository publik.

------------------------------------------------------------------------

### Midtrans Webhook Notification

<p align="center"><img src="../assets/readme-brown/midtrans-webhook.png" width="100%" alt="Midtrans Webhook" /></p>

Endpoint pembayaran:

``` text
POST /midtrans/notification
```

digunakan untuk menerima perubahan status transaksi dari Midtrans.

Untuk pengujian lokal, gunakan tunnel HTTPS karena Midtrans tidak dapat
mengakses localhost secara langsung.

Contoh:

``` bash
ngrok http 8000
```

Kemudian masukkan:

    https://domain-tunnel.example/midtrans/notification

sebagai Payment Notification URL pada Midtrans Sandbox.

------------------------------------------------------------------------

### 10. Build Frontend

Mode development:

``` bash
npm run dev
```

Build production:

``` bash
npm run build
```

------------------------------------------------------------------------

### 11. Menjalankan Aplikasi

Jalankan Laravel:

``` bash
php artisan serve
```

Buka browser:

    http://127.0.0.1:8000

------------------------------------------------------------------------

### Akun Login Awal

<p align="center"><img src="../assets/readme-brown/akun-login.png" width="100%" alt="Initial accounts" /></p>

Berikut adalah akun default yang dapat digunakan untuk pengujian aplikasi:

| Role | Email | Password |
| :-- | :-- | :-- |
| Admin | admin@coffee.com | password |
| Kasir | kasir@coffee.com | password |
| Owner | owner@coffee.com | password |

> Penting: Disarankan untuk mengganti password default setelah deployment atau saat aplikasi digunakan pada lingkungan production.

------------------------------------------------------------------------

### Pengujian QRIS

<p align="center"><img src="../assets/readme-brown/pengujian-qris.png" width="100%" alt="QRIS testing" /></p>

Alur pengujian:

1.  Login sebagai Kasir
2.  Pilih menu
3.  Buat transaksi
4.  Pilih pembayaran QRIS
5.  Sistem membuat transaksi Midtrans Sandbox
6.  Simulasikan pembayaran

Simulator QRIS:

    https://simulator.sandbox.midtrans.com/qris/index

------------------------------------------------------------------------

### Troubleshooting

<p align="center"><img src="../assets/readme-brown/troubleshooting.png" width="100%" alt="Troubleshooting" /></p>

Jika terjadi error, jangan langsung mengubah source code.

Jalankan:

``` bash
php artisan tinker --execute="echo config('database.connections.mysql.database');"

php artisan migrate:status

php artisan about
```

------------------------------------------------------------------------

## Catatan Keamanan

<p align="center"><img src="../assets/readme-brown/catatan-keamanan.png" width="100%" alt="06 / Security - Jimny Coffee brown theme" /></p>

Jangan membagikan file:

    .env

karena dapat berisi:

-   APP_KEY Laravel
-   Credential database
-   Credential Midtrans

Gunakan:

    .env.example

sebagai template konfigurasi.

------------------------------------------------------------------------

## Dokumentasi Pendukung (Arsip)

<p align="center"><img src="../assets/readme-brown/dokumentasi-pendukung-arsip.png" width="100%" alt="07 / Documentation - Jimny Coffee brown theme" /></p>

Dokumentasi teknis dari tahap pengembangan sebelumnya:

- [Panduan Kasir Compact](../legacy/README-KASIR-COMPACT.md)
- [Pembaruan Antarmuka](../legacy/README-PEMBARUAN-UI.md)
- [Panduan Sidebar dan GUI](../legacy/README-SIDEBAR-GUI.md)
- [Dokumentasi Tema Ember](../legacy/README-TEMA-EMBER.md)
- [Dokumentasi Smart System](../legacy/SMART_SYSTEM.md)

> Dokumen dalam folder ini merupakan arsip pengembangan. Untuk status fitur terbaru, gunakan README utama dan source code pada branch main.

<p align="center"><img src="../assets/readme-brown/footer-brown.png" width="100%" alt="JIMNY COFFEE footer" /></p>

<div align="center">

**JIMNY COFFEE** · Project kolaborasi Informatika

</div>
