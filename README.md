::: {align="center"}
`<img src="public/images/jimny-coffee-logo.png" alt="Logo JIMNY COFFEE" width="120" />`{=html}

# ☕ JIMNY COFFEE

### Smart Point of Sale & Coffee Shop Management

Sistem informasi kasir dan pengelolaan operasional kedai kopi berbasis
web, dengan antarmuka khusus **Owner**, **Admin**, dan **Kasir**.

![Laravel](https://img.shields.io/badge/Laravel-PHP-FF2D20?logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/Database-MySQL-4479A1?logo=mysql&logoColor=white)
![Tailwind
CSS](https://img.shields.io/badge/UI-Tailwind_CSS-06B6D4?logo=tailwindcss&logoColor=white)
![GitHub](https://img.shields.io/badge/Project-Kolaborasi-181717?logo=github&logoColor=white)

[**Fitur**](#-fitur-aplikasi) · [**Preview**](#-preview-antarmuka) ·
[**Instalasi**](#-menjalankan-project) · [**Tim**](#-tim-pengembang)
:::

------------------------------------------------------------------------

## ✨ Sekilas Project

**JIMNY COFFEE** adalah project aplikasi Point of Sale (POS) berbasis
Laravel untuk membantu pengelolaan pesanan, transaksi, menu, stok,
laporan penjualan, dan aktivitas karyawan dalam satu sistem. Tampilan
aplikasi menggunakan nuansa warna kopi dan menyediakan halaman sesuai
peran pengguna.

> **Status repository:** dokumentasi antarmuka dan source code sedang
> digabungkan secara bertahap melalui kolaborasi GitHub. Beberapa
> halaman pada preview mungkin belum tersedia di branch `main` sampai
> semua kontribusi digabungkan.

## 🚀 Fitur Aplikasi

  -----------------------------------------------------------------------
  Peran                               Area yang ditampilkan dalam project
  ----------------------------------- -----------------------------------
  **Kasir**                           Pemilihan menu & pesanan,
                                      pencatatan transaksi, pilihan
                                      pembayaran Cash/QRIS, presensi &
                                      izin, riwayat transaksi, laporan
                                      penjualan.

  **Admin**                           Dashboard, pengelolaan menu, log
                                      stok, pengaturan shift, pengajuan
                                      izin, pengelolaan akun, dan riwayat
                                      transaksi.

  **Owner**                           Dashboard ringkasan bisnis, laporan
                                      penjualan, riwayat transaksi, dan
                                      rekap kehadiran.

  **Smart Insight**                   Panel informasi kontekstual yang
                                      muncul pada antarmuka sebagai
                                      pendamping aktivitas pengguna.
  -----------------------------------------------------------------------

## 🖼️ Preview Antarmuka

### Halaman Login

`<img src="docs/screenshots/01-login.webp" alt="Halaman login JIMNY COFFEE" width="100%" />`{=html}

### Kasir / Point of Sale

`<img src="docs/screenshots/02-kasir-pos.webp" alt="Tampilan kasir dan pencatatan pesanan" width="100%" />`{=html}

### Dashboard Owner

`<img src="docs/screenshots/06-owner-dashboard.webp" alt="Dashboard Owner: ringkasan omzet, transaksi, dan menu terlaris" width="100%" />`{=html}

### Dashboard Admin

`<img src="docs/screenshots/11-admin-dashboard.webp" alt="Dashboard Admin" width="100%" />`{=html}

### Menu, Stok & Shift

  ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
                                                Kelola menu                                                                                         Log stok
  ------------------------------------------------------------------------------------------------------- --------------------------------------------------------------------------------------------
   `<img src="docs/screenshots/13-admin-kelola-menu.webp" alt="Pengelolaan menu" width="100%" />`{=html}   `<img src="docs/screenshots/14-admin-log-stok.webp" alt="Log stok" width="100%" />`{=html}

  ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

  -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
                                                Kelola shift                                                                                              Kelola akun
  --------------------------------------------------------------------------------------------------------- -------------------------------------------------------------------------------------------------------
   `<img src="docs/screenshots/15-admin-kelola-shift.webp" alt="Pengelolaan shift" width="100%" />`{=html}   `<img src="docs/screenshots/17-admin-kelola-akun.webp" alt="Pengelolaan akun" width="100%" />`{=html}

  -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

### Laporan & Aktivitas

  -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
                                                   Laporan penjualan                                                                                                    Riwayat transaksi
  -------------------------------------------------------------------------------------------------------------------- --------------------------------------------------------------------------------------------------------------------
   `<img src="docs/screenshots/07-owner-laporan-penjualan.webp" alt="Laporan penjualan Owner" width="100%" />`{=html}   `<img src="docs/screenshots/09-owner-riwayat-transaksi.webp" alt="Riwayat transaksi Owner" width="100%" />`{=html}

  -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

```{=html}
<details>
```
```{=html}
<summary>
```
`<strong>`{=html}📷 Lihat galeri tampilan lainnya (9
screenshot)`</strong>`{=html}
```{=html}
</summary>
```
`<br />`{=html}

  ----------------------------------------------------------------------------------------------------------------------------------------------------------
  Tampilan                            Preview
  ----------------------------------- ----------------------------------------------------------------------------------------------------------------------
  Presensi & izin Kasir               `<img src="docs/screenshots/03-kasir-presensi-izin.webp" alt="Presensi dan izin Kasir" width="100%" />`{=html}

  Riwayat transaksi Kasir             `<img src="docs/screenshots/04-kasir-riwayat-transaksi.webp" alt="Riwayat transaksi Kasir" width="100%" />`{=html}

  Laporan penjualan Kasir             `<img src="docs/screenshots/05-kasir-laporan-penjualan.webp" alt="Laporan penjualan Kasir" width="100%" />`{=html}

  Analitik penjualan Owner            `<img src="docs/screenshots/08-owner-analitik-penjualan.webp" alt="Analitik penjualan Owner" width="100%" />`{=html}

  Rekap kehadiran Owner               `<img src="docs/screenshots/10-owner-rekap-kehadiran.webp" alt="Rekap kehadiran Owner" width="100%" />`{=html}

  POS Admin                           `<img src="docs/screenshots/12-admin-pos.webp" alt="POS Admin" width="100%" />`{=html}

  Pengajuan izin Admin                `<img src="docs/screenshots/16-admin-pengajuan-izin.webp" alt="Pengajuan izin Admin" width="100%" />`{=html}

  Riwayat transaksi Admin             `<img src="docs/screenshots/18-admin-riwayat-transaksi.webp" alt="Riwayat transaksi Admin" width="100%" />`{=html}

  Analitik penjualan Admin            `<img src="docs/screenshots/19-admin-analitik-penjualan.webp" alt="Analitik penjualan Admin" width="100%" />`{=html}
  ----------------------------------------------------------------------------------------------------------------------------------------------------------

```{=html}
</details>
```
> Screenshot menunjukkan antarmuka pada saat pengambilan gambar. Angka
> penjualan, stok, dan transaksi yang terlihat merupakan contoh
> tampilan, bukan data operasional terkini.

## 🛠️ Teknologi

-   **Backend:** PHP & Laravel
-   **Database:** MySQL
-   **Frontend:** Blade, Tailwind CSS, CSS, dan JavaScript
-   **Build tools:** Composer, NPM, dan Vite
-   **Kolaborasi:** Git & GitHub (branch dan pull request)

## ⚙️ Menjalankan Project

> Setelah seluruh source code tersedia, ikuti langkah berikut untuk
> menyiapkan project JIMNY COFFEE pada komputer lokal. Pastikan PHP,
> Composer, Node.js/NPM, Laragon, dan MySQL telah terpasang.

------------------------------------------------------------------------

# 📋 Persyaratan Sistem

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

# 1. Menyalakan Laragon

Buka aplikasi **Laragon**.

Pastikan:

✅ MySQL aktif\
✅ Apache/Nginx dapat diaktifkan jika diperlukan

Laragon digunakan untuk menyediakan environment Laravel dan database
MySQL lokal.

------------------------------------------------------------------------

# 2. Masuk ke Folder Project

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

# 3. Install Dependency Project

Install dependency backend Laravel:

``` bash
composer install
```

Install dependency frontend:

``` bash
npm install
```

------------------------------------------------------------------------

# 4. Konfigurasi Environment (.env)

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

# 5. Konfigurasi Database

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

# 6. Membuat Database Lokal

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

# 7. Membersihkan Cache Laravel

Setelah mengubah `.env`, jalankan:

``` bash
php artisan optimize:clear
```

------------------------------------------------------------------------

# 8. Verifikasi Database Laravel

Pastikan Laravel membaca database yang benar:

``` bash
php artisan tinker --execute="echo config('database.connections.mysql.database');"
```

Output yang benar:

    satar_integrated

Jika hasil berbeda, periksa kembali file `.env`.

------------------------------------------------------------------------

# 9. Migration dan Seeder

Buat tabel database dan data awal aplikasi:

``` bash
php artisan migrate --seed
```

Seeder yang dijalankan:

-   CategorySeeder
-   MenuSeeder
-   UserSeeder

------------------------------------------------------------------------

# 💳 Konfigurasi Midtrans Sandbox

## ⚠️ Penting

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

# 🔔 Midtrans Webhook Notification

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

# 10. Build Frontend

Mode development:

``` bash
npm run dev
```

Build production:

``` bash
npm run build
```

------------------------------------------------------------------------

# 11. Menjalankan Aplikasi

Jalankan Laravel:

``` bash
php artisan serve
```

Buka browser:

    http://127.0.0.1:8000

------------------------------------------------------------------------

# 🔐 Akun Login Awal

  Role    Email              Password
  ------- ------------------ ----------
  Admin   admin@coffee.com   password
  Kasir   kasir@coffee.com   password
  Owner   owner@coffee.com   password

> Disarankan mengganti password default setelah deployment.

------------------------------------------------------------------------

# 🧪 Pengujian QRIS

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

# 🛠️ Troubleshooting

Jika terjadi error, jangan langsung mengubah source code.

Jalankan:

``` bash
php artisan tinker --execute="echo config('database.connections.mysql.database');"

php artisan migrate:status

php artisan about
```

------------------------------------------------------------------------

# 🔒 Catatan Keamanan

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

::: {align="center"}
**JIMNY COFFEE** · Project kolaborasi Informatika
:::
