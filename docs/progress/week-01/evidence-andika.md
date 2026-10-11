# Week 1 Technical Evidence — Andika

## Identitas

- Nama: Andika
- NIM: 245314084
- Area: Reporting, Financial History, dan Dashboard
- Periode: 5–11 Oktober 2026
- Presentasi progress: 12 Oktober 2026
- Branch evidence: `progress/week1-andika`
- Baseline main: `89b19be`

## Tujuan Week 1

Melakukan validasi baseline terhadap modul laporan, histori finansial,
dashboard owner, filter laporan, dan integrasi data transaksi.

Fokus Week 1 adalah menguji implementasi yang telah tersedia,
mencatat defect atau risiko nyata, dan memastikan hasil pengujian dapat
ditelusuri melalui GitHub.

## Baseline Automated Test

### Laporan Analytics

Perintah:

`php artisan test --filter=LaporanAnalyticsTest`

Hasil:

- 4 tests PASS
- 22 assertions

Skenario yang tervalidasi mencakup:

- laporan harian hanya menghitung transaksi berhasil;
- jam kosong tetap terisi pada analytics;
- laporan bulanan menangani leap year;
- boundary tahun sebelumnya;
- laporan kosong;
- filter invalid;
- authentication requirement.

### Financial History

Perintah:

`php artisan test --filter=FinancialHistoryIntegrationTest`

Hasil:

- 2 tests PASS
- 26 assertions

Skenario:

- identitas kasir tetap tersimpan pada histori transaksi setelah akun kasir dihapus;
- tampilan histori finansial menggunakan cashier display name yang benar.

### Owner Dashboard

Pengujian dashboard memverifikasi:

- revenue;
- filter tanggal;
- transaksi paid;
- ranking menu;
- attendance;
- stock minimum;
- average;
- perbandingan dengan hari sebelumnya;
- critical stock;
- least-selling menu.

Seluruh automated test terkait dashboard dinyatakan PASS.

### Full Regression

Full Laravel regression:

- 60 tests PASS
- 272 assertions

Production frontend build menggunakan Vite juga berhasil.

## BUG-W1-ANDIKA-01 — Report CSV Download Transition

### Area

Reporting / Export CSV

### Penemu

Andika.

### Klasifikasi

- Jenis: UI / download navigation behavior
- Severity: Low
- Status: RESOLVED

### Verifikasi Kondisi Lama

Git history menunjukkan bahwa sebelum commit `021e5ca`, tombol
`Export CSV (Excel)` pada:

`resources/views/laporan/report.blade.php`

menggunakan link:

`<a class="soft-button" href="...">`

tanpa atribut HTML `download`.

### Perbaikan

Commit:

`021e5ca Fix report CSV download transition`

mengubah link tersebut menjadi:

`<a class="soft-button" href="..." download>`

Perubahan hanya dilakukan pada view laporan dan tidak mengubah logic
perhitungan laporan maupun controller export CSV.

Commit fix dibuat oleh Dion dan sudah termasuk di baseline main yang
digunakan Andika untuk Week 1.

### Kondisi Setelah Fix

Implementasi saat ini memiliki:

- route `laporan.export`;
- controller export CSV menggunakan streamed download;
- link export pada report mempunyai atribut `download`.

Regression setelah fix:

- Laporan tests: 8 PASS / 34 assertions
- Financial History: 2 PASS / 26 assertions
- Owner Dashboard related tests: 4 PASS / 30 assertions
- Full Laravel regression: 60 PASS / 272 assertions
- Vite production build: PASS

Dengan demikian issue tersebut dinyatakan RESOLVED pada baseline saat ini.

### Catatan Metode Verifikasi

Verifikasi Week 1 dilakukan melalui terminal menggunakan:

- Git history;
- commit ancestry;
- Git diff sebelum dan sesudah fix;
- source search;
- Laravel route inspection;
- automated regression tests.

Verifikasi ini membuktikan perubahan pada level source code dan regression.
Perilaku visual browser tidak diklaim sebagai hasil automated terminal test.

## Kesimpulan Andika Week 1

Baseline Reporting, Financial History, dan Owner Dashboard berhasil
diverifikasi.

Automated testing tidak menemukan defect Critical atau High pada area Andika.

Satu historical issue pada mekanisme download laporan CSV berhasil
ditelusuri melalui Git history dan telah berstatus RESOLVED pada baseline
terbaru.

Pengujian black-box, invalid input, boundary, dan regression lanjutan akan
dilakukan pada minggu berikutnya sesuai jadwal proyek.
