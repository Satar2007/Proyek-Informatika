# JSA Fase 3 — rekomendasi restok Dion

## Cakupan

Modul Python offline, berangkat dari commit Fase 2 `9575d13`.
Tidak mengganti kode Fase 0–2; tidak menulis database, memesan barang,
menambah stok, membuat endpoint, scheduler, atau UI Laravel.

Input terdiri atas `forecast_result.json` Fase 2, snapshot menu/stok JSON,
dan kebijakan layanan JSON. Output: `restock_result.json`,
`restock_menu.csv`, dan `restock_report.txt`.

## Dasar kode stok yang telah diperiksa

- `StockReservationService::reserve()` memanggil `kurangiStok` pada checkout QRIS.
- `complete()` mengubah status reservasi menjadi consumed, tanpa pengurangan kedua.
- `release()` mengembalikan stok setelah pembatalan yang terkonfirmasi.
- `StockService` melakukan perubahan dalam transaksi dengan row lock.
- Jadi `menus.stok` adalah stok tersedia, sudah di luar reservasi.
- `Menu` memiliki `category_id`, `stok`, `minimum_stok`, `is_active`, soft delete.
- `MenuController` memisahkan tambah/kurangi stok dari edit menu.
  Perubahan tipe log koreksi bukan bagian paket ini.

Sumber permintaan tetap transaksi success+paid yang diekspor Fase 0,
bukan stock_logs. Stok contoh dalam paket TIDAK berasal dari database.

## Rumus dan kebijakan

Untuk tiap menu, e = aktual - prediksi pada backtest Fase 2.
Gunakan paling banyak 60 residual terakhir; paling sedikit 20 residual.
Untuk target layanan alpha, rank = ceil((n+1)*alpha).
Jika rank > n, jangan mengeluarkan rekomendasi numerik.

    target_demand = max(0, ceil(point + residual_urut[rank-1]))
    target_stock  = max(minimum_stok, target_demand)
    restock_qty   = max(0, target_stock - stok)

Residual bertanda memungkinkan koreksi bias kelebihan prediksi.
Ini berbeda dari interval simetris Fase 2 yang memakai nilai absolut galat.
`minimum_stok` merupakan floor target, tidak ditambahkan sebagai cadangan
akhir hari. Jumlah target menu tidak harus sama dengan total prediksi titik.

File policy_demo.json memakai 90% semata-mata contoh yang dinyatakan eksplisit.
Bukan kebijakan toko yang sudah disetujui. `category_service_levels` dapat diisi
misalnya `{"1": 0.90, "2": 0.95}` menggunakan ID kategori asli.
Target yang didukung 50–95%. Jangan menyamakan target dengan jaminan tercapai.

## Batas waktu dan cakupan operasional

Horizon hanya satu hari, lead time nol: barang harus siap sebelum pelayanan.
Snapshot wajib diambil sebelum pelayanan pada tanggal prediksi yang sama.
Bukan ramalan sisa hari, bukan prediksi waktu stok habis, bukan rekomendasi
beberapa hari. Jika ada reservasi lintas hari atau transaksi sudah berjalan,
perlu rekonsiliasi demand/snapshot tambahan sebelum digunakan operasional.

Mode tanpa --demo memeriksa tanggal Asia/Jakarta saat dijalankan, mewajibkan
forecast non-replay dan snapshot berlabel actual. Label actual adalah metadata
input, bukan bukti verifikasi database otomatis. Jangan mengganti label snapshot
contoh menjadi actual. Kualitas dan asal data penjualan tetap perlu diverifikasi.

Menu nonaktif/dihapus mendapat status inactive_or_deleted dan restok nol.
Menu tanpa snapshot, tanpa forecast, atau tanpa kalibrasi cukup mendapat null
untuk jumlah restok: harus direview, tidak dianggap permintaan/stok nol.
Nama dan status terbaru mengacu snapshot, identitas selalu menu_id.

## Snapshot stok nyata (kontrak input)

Format `scripts/examples/jsa_phase3/stock_demo.json` menjadi contoh struktur.
Field header: schema_version=1, date=YYYY-MM-DD, source=actual,
stock_basis=available_after_reservations, timing=before_service.
Field tiap menu wajib:

| Field | Sumber / tipe |
|---|---|
| menu_id | menus.id, integer positif |
| nama_menu | menus.nama_menu, string |
| category_id | menus.category_id, integer positif |
| stok | menus.stok, integer nonnegatif |
| minimum_stok | menus.minimum_stok, integer nonnegatif |
| is_active | boolean JSON true/false |
| deleted_at | null atau timestamp string |

Ambil snapshot konsisten dari database toko yang benar sebelum pelayanan.
Termasuk menu nonaktif/dihapus agar keputusan skip dapat diaudit. Referensi query:

```sql
SELECT id AS menu_id, nama_menu, category_id, stok, minimum_stok,
       is_active, deleted_at
FROM menus
ORDER BY id;
```

Query ini hanya kontrak pengambilan data; ekspor otomatis Laravel dan koneksi
MySQL belum dibuat/diuji dalam Fase 3 ini. Konversi is_active 0/1 ke boolean JSON.
Jangan mengurangi reservasi lagi dan jangan menebak jumlah stok yang tidak ada.

## Jalankan tes dan demo di laptop Dion

Folder: C:\Users\dion1\Proyek-Informatika\jimny-coffee
Interpreter: C:\Users\dion1\jsa-venv\Scripts\python.exe

```powershell
& "$env:USERPROFILE\jsa-venv\Scripts\python.exe" -B -m unittest discover -s scripts/tests -p "test_*.py" -v
& "$env:USERPROFILE\jsa-venv\Scripts\python.exe" scripts/jsa_forecast.py --today 2026-10-25 --out storage/app/jsa/benchmark/phase2-phase3-demo
& "$env:USERPROFILE\jsa-venv\Scripts\python.exe" scripts/jsa_restock.py --forecast storage/app/jsa/benchmark/phase2-phase3-demo/forecast_result.json --stock scripts/examples/jsa_phase3/stock_demo.json --policy scripts/examples/jsa_phase3/policy_demo.json --out storage/app/jsa/benchmark/phase3-demo --demo
```

Demo memakai CSV handoff sampai 24 Oktober 2026 dan replay 25 Oktober 2026.
Stok, kategori, minimum_stok dan status di stock_demo.json adalah SINTETIS.
Output demo tidak boleh menjadi instruksi pembelian atau klaim stok toko.
CLI gagal dengan exit 1 jika validasi/input gagal; output baru hanya dibuat
setelah seluruh input lolos validasi. Jika output folder lama sudah ada,
kegagalan tidak menghapusnya: jangan menyangka hasil lama adalah run terbaru.

## Evaluasi dan keterbatasan

Coverage satu sisi dihitung prequential: ambang hari t memakai residual sebelum t,
baru dibandingkan aktual hari t. Hasilnya n dan coverage per menu.
Itu evaluasi ambang permintaan, bukan backtest biaya/keberhasilan restok:
riwayat stok nyata, umur simpan, dan biaya belum tersedia.
Data dummy hanya membuktikan alur perhitungan. Permintaan tidak terpenuhi saat
stockout juga tidak tercatat sebagai penjualan. Tidak ada jaminan probabilitas
pada deret waktu dependen. Tidak ada optimasi biaya newsvendor tanpa biaya/HPP.

23 tes baru mencakup residual bertanda, floor minimum, stok cukup, kategori,
menu hilang/nonaktif, tanggal, kalibrasi, kebocoran data, validasi dan CLI.
Tes sandbox memakai versi dependency yang tersedia, berbeda dari laptop Dion;
jalankan ulang di laptop (NumPy 2.5.3, scikit-learn 1.9.1) sebelum commit.

## Git

Paket menambahkan lima file: script, test, dua contoh JSON, dokumentasi ini.
Tidak memasukkan data/output storage, venv, .env, vendor atau node_modules.
Jangan memakai git add .; jangan hapus bootstrap/storage lama di root.
Commit/push tetap dilakukan sendiri oleh Dion setelah hasil tes diperiksa.
Fase 5 integrasi Laravel masih terpisah.

## Hasil validasi paket

Sandbox: Python 3.12.14, NumPy 2.3.5, scikit-learn 1.8.0.
72 tes lulus (49 sebelumnya + 23 baru), 81.665 detik.
Alur CSV handoff -> Fase 2 auto replay -> Fase 3 berhasil untuk 42 menu.
Hasil Windows Dion untuk Fase 3 belum tersedia. Tidak ada tes PHP/MySQL.
