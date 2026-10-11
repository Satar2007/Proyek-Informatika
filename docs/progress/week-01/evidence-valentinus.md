# Evidence Week 1 — Valentinus

## Identitas dan ruang lingkup
- Proyek: SATAR / JIMNY COFFEE — Proyek Informatika Tahap III
- Periode Week 1: 5–11 Oktober 2026
- Anggota: Valentinus
- Branch: `progress/week1-valentinus`
- Baseline: `29b3f30`
- Area: Authentication, Profile, role access, dan account management

## Lingkungan pengujian
- Laravel 13
- PHP 8.5.11
- Database testing terisolasi: `satar_valentinus_week1_test`
- Migrasi: 24 file berhasil dijalankan
- Frontend: `npm run build` berhasil
- Vite manifest: berhasil dihasilkan

## Hasil automated test

| Kelompok | Tests | Assertions | Status |
|---|---:|---:|---|
| Authentication | 4 | 8 | PASS |
| Password Confirmation | 3 | 5 | PASS |
| Password Reset | 4 | 8 | PASS |
| Password Update | 2 | 8 | PASS |
| Profile | 5 | 21 | PASS |
| Role Access | 9 | 11 | PASS |
| Account Management | 8 | 28 | PASS |
| **Total** | **35** | **89** | **PASS** |

Regresi gabungan tujuh kelompok: **35 passed (89 assertions)**,
durasi 5,73 detik pada PC Valentinus.

## Kontribusi pengujian baru
1. `tests/Feature/ValentinusRoleAccessTest.php`
   - 9 skenario akses guest, Kasir, Admin, dan Owner.
   - Memastikan redirect login dan penolakan HTTP 403
     untuk endpoint terlarang yang diuji.
2. `tests/Feature/ValentinusAccountManagementTest.php`
   - 8 skenario manajemen akun.
   - Meliputi tampilan, tambah akun, email duplikat,
     role tidak valid, password pendek, update tanpa
     mengganti password, larangan hapus diri sendiri,
     serta hapus akun tanpa transaksi aktif.

Kedua file lulus pemeriksaan sintaks `php -l`.

## Temuan dan penanganan

### QA-SETUP-VAL-01 — RESOLVED
- Gejala awal: halaman login pada AuthenticationTest
  menghasilkan HTTP 500.
- Penyebab terkonfirmasi: Vite manifest belum dibuat.
- Tindakan: menjalankan `npm run build`.
- Verifikasi: AuthenticationTest menjadi 4 PASS.

### QA-CONFIG-W1-01 — OPEN / TEST ENVIRONMENT
- Full Laravel regression pada database khusus Valentinus:
  66 PASS, 11 FAIL, 226 assertions.
- Sebelas kegagalan disebabkan test bawaan yang secara
  eksplisit mengharuskan database `satar_integrated_test`,
  bukan database terisolasi milik Valentinus.
- Tidak diklasifikasikan sebagai sebelas bug aplikasi.
- Full regression lintas modul belum dinyatakan PASS.
- Tindak lanjut: koordinasi penyesuaian strategi database
  testing bersama tim, tanpa mengubah database yang sudah
  dipakai atau menghilangkan pengaman.

### SEC-DEP-W1-01 — OPEN / TRIAGE
- Audit Composer mengidentifikasi empat advisory pada
  tiga dependency: `laravel/framework`,
  `league/commonmark`, dan `league/flysystem`.
- Audit npm untuk production dependencies (`--omit=dev`)
  melaporkan nol vulnerability saat pemeriksaan.
- Full npm audit sebelumnya menampilkan sepuluh temuan.
- Advisory dependency belum merupakan bukti eksploitasi
  atau defect fungsional yang terkonfirmasi.
- Tindak lanjut: tinjau versi aman dan kompatibilitas
  pembaruan bersama tim, tanpa forced upgrade sembarangan.

## Batas pengujian
- Penghapusan akun dengan transaksi QRIS pending atau
  reservasi stok aktif belum diuji secara khusus.
- Pengujian UI manual lintas browser belum didokumentasikan.
- Sinkronisasi diagram UML Tahap II ke implementasi Tahap III
  masih membutuhkan sumber diagram asli.
- Hasil PASS tidak berarti semua endpoint dan kondisi
  keamanan telah tercakup.

## Status Week 1
- Auth/Profile regression: PASS
- Role access regression: PASS
- Account management regression dasar: PASS
- Full cross-module regression: BLOCKED BY TEST CONFIGURATION
- Dependency security triage: OPEN
- Evidence ini merekam hasil aktual, bukan hasil asumsi.