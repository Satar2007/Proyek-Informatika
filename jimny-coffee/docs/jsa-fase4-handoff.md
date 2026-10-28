# JSA Fase 4 — Operational Handoff & Audit Trail

## Tujuan

Fase 4 mengubah hasil analitik Fase 2 dan rekomendasi Fase 3
menjadi paket keputusan yang dapat diaudit sebelum dibaca Laravel.

Fase ini tetap bersifat **read-only** terhadap stok dan database.

## Input

Artifact minimum:

1. forecast JSON dari Fase 2;
2. stock snapshot;
3. policy restock;
4. restock result dari Fase 3.

Artifact opsional:

- CSV rekomendasi;
- report manusia.

## Output

Setiap eksekusi menghasilkan immutable run directory:

`storage/app/private/jsa/runs/<run_id>/`

Isi:

- `manifest.json`
- `artifacts/forecast.json`
- `artifacts/stock.json`
- `artifacts/policy.json`
- `artifacts/restock.json`
- artifact opsional bila diberikan.

Pointer:

`storage/app/private/jsa/latest.json`

menunjuk run terakhir yang berhasil dipublikasikan.

## Auditability

Manifest menyimpan:

- schema version;
- run ID;
- UTC timestamp;
- SHA-256 setiap artifact;
- ukuran setiap artifact;
- package fingerprint;
- review flags;
- status;
- deklarasi read-only;
- deklarasi auto-restock = false.

## Status

- `READY_FOR_REVIEW`
  Artifact valid dan tidak ditemukan flag konservatif.

- `REVIEW_REQUIRED`
  Artifact valid tetapi ditemukan sinyal seperti
  insufficient_data, uncalibrated, warning, atau review_required.

- `BLOCKED`
  Digunakan CLI untuk kegagalan validasi/integritas.
  Run yang gagal tidak menggantikan `latest.json`.

## Atomic publication

Artifact disalin terlebih dahulu ke temporary directory.

Setelah semua hash hasil copy cocok dengan sumber,
directory dipublikasikan menggunakan atomic rename.

Dengan demikian Laravel pada Fase 5 tidak membaca run setengah jadi.

## Integrity verification

`verify_run()` menghitung kembali SHA-256 seluruh artifact
dan package fingerprint.

Jika satu byte artifact diubah setelah handoff,
verifikasi harus gagal.

## Batasan

Fase 4 tidak:

- mengubah `menus.stok`;
- mengurangi reservation;
- membuat purchase order;
- menjalankan auto-restock;
- menentukan keputusan pembelian secara otomatis.

Keputusan akhir tetap membutuhkan review manusia.

## Posisi pipeline

Fase 0 — data export
→ Fase 1 — benchmark
→ Fase 2 — forecast
→ Fase 3 — restock recommendation
→ **Fase 4 — immutable operational handoff**
→ Fase 5 — Laravel read-only integration
