# Week 1 Technical Evidence — Rafael

## Identitas

- Nama: Rafael
- NIM: 245314089
- Area: Integrasi, Payment, Stock Integrity, dan Quality Assurance
- Periode: 5–11 Oktober 2026
- Presentasi progress: 12 Oktober 2026
- Baseline commit: `021e5ca`
- Branch evidence: `progress/week1-rafael`

## Tujuan Pengujian

Validasi baseline teknis sistem JIMNY COFFEE pada area integrasi transaksi,
payment Cash/QRIS, Midtrans, stock reservation, webhook, concurrency,
database safety, regression aplikasi, dan build frontend.

Pengujian dilakukan terhadap program yang telah tersedia. Fokus Week 1 bukan
membangun ulang fitur, melainkan memastikan kondisi baseline dan menemukan
defect atau risiko sebelum pengujian lanjutan.

## Hasil Pengujian

| Area | Pengujian | Hasil |
|---|---|---|
| Payment security | PaymentTokenHardeningTest | PASS — 3 tests, 9 assertions |
| Database safety | DatabaseSafetyTest | PASS — 1 test, 3 assertions |
| Stock & payment | stock_reservation_smoke.php | PASS — 24/24 skenario |
| Midtrans webhook | midtrans_webhook_smoke.php | PASS — 6/6 skenario |
| Concurrency | concurrency_smoke.php | PASS — 5/5 skenario |
| Full regression | php artisan test | PASS — 60 tests, 272 assertions |
| Frontend | npm run build | PASS |
| Git hygiene | git diff --check | PASS |

## Validasi Payment

Payment hardening memverifikasi:

1. payment pada state terkunci mengembalikan HTTP 409 tanpa memanggil Midtrans;
2. kegagalan Midtrans ditangani dengan HTTP 503;
3. kasir tidak dapat meminta token payment milik kasir lain.

Semua skenario lulus.

## Validasi Stock Reservation dan Payment State

Manual smoke test memverifikasi 24 skenario, antara lain:

- item duplikat digabung sebelum reservasi;
- quantity melebihi stok ditolak;
- stok terakhir tidak dapat dipakai transaksi lain;
- settlement berulang tidak mengurangi stok lebih dari satu kali;
- expire/cancel/deny/failure melepas reservasi tepat satu kali;
- status pending tetap mempertahankan reservasi;
- wrong order dan wrong amount tidak merusak state lokal;
- Cash mengurangi stok tepat satu kali;
- QRIS total nol ditolak;
- kegagalan checkout melakukan rollback;
- token hanya dibuat jika reservasi aktif.

Seluruh 24 skenario PASS.

## Validasi Webhook Midtrans

Webhook diuji pada enam kondisi:

1. signature invalid;
2. order ID tidak dikenal;
3. nominal transaksi tidak sesuai;
4. status pending;
5. settlement;
6. expire.

Semua skenario PASS dan perubahan data uji di-rollback setelah pengujian.

## Validasi Concurrency

Pengujian dilakukan menggunakan dua proses PHP dan dua koneksi MySQL
pada kondisi stok terakhir = 1.

Hasil:

- satu worker berhasil melakukan transaksi;
- worker lainnya ditolak karena stok sudah tidak mencukupi;
- row lock bekerja;
- stok akhir tepat 0 dan tidak negatif;
- hanya satu transaksi/reservasi/log stok yang terbentuk.

Kesimpulan: mekanisme anti-overselling bekerja pada skenario yang diuji.

## Catatan Eksekusi Manual Smoke

Pada eksekusi awal, `stock_reservation_smoke.php` dan
`midtrans_webhook_smoke.php` tidak dapat menjalankan skenario karena database
test belum memiliki minimal satu menu aktif dan akun kasir.

Kondisi ini diklasifikasikan sebagai **blocked test akibat precondition/fixture
belum tersedia**, bukan defect fungsional aplikasi.

Sebelum menyiapkan fixture dilakukan safety guard dan diperoleh:

- environment: `testing`
- database: `satar_integrated_test`

Database test kemudian di-seed menggunakan DatabaseSeeder. Setelah fixture
tersedia:

- stock/payment smoke: 24/24 PASS;
- webhook smoke: 6/6 PASS.

Tidak ada database utama yang digunakan untuk proses pengujian tersebut.

## Temuan Week 1

### SEC-PKG-01 — Packaging Security

Arsip project final yang diperiksa sebelumnya masih membawa file `.env`,
meskipun `.env` tidak di-track oleh Git.

Risiko:
secret atau konfigurasi lokal dapat ikut terbawa apabila ZIP didistribusikan.

Tindakan:
paket distribusi selanjutnya tidak boleh menyertakan `.env`.
Gunakan `.env.example` dan keluarkan `.git`, `vendor`, serta `node_modules`
sesuai kebutuhan distribusi.

Temuan ini merupakan masalah packaging/distribusi dan bukan kegagalan logic
payment atau stock.

## Kesimpulan Rafael Week 1

Baseline teknis pada area payment, stock reservation, Midtrans webhook,
concurrency, database safety, full Laravel regression, dan frontend build
telah diverifikasi.

Pada pengujian Week 1 ini tidak ditemukan defect fungsional Critical/High
pada area Payment, Stock Integrity, dan Integrasi.

Seluruh pengujian yang dapat dieksekusi setelah prerequisite data uji
dipenuhi dinyatakan PASS.

Pengujian black-box, invalid input, edge case, dan regression akan
dilanjutkan pada Week 2 sesuai jadwal proyek.
