# JSA Self-Learning — Fase 1: Dataset Manager

## Tujuan

Membuat dataset **rolling 365 hari** sebagai bahan pembelajaran, **tanpa** memodifikasi database Laravel, stok, transaksi, pembayaran, model forecast, atau Ollama. Tidak ada training model dalam fase ini.

## Penempatan

- `scripts/jsa_dataset_manager.py`
- `scripts/tests/test_jsa_dataset_manager.py`
- `docs/jsa-self-learning-phase1.md`

Semua file baru. **Jangan menimpa** file yang sudah ada. Gunakan branch `feature/jsa-self-learning`.

## Persyaratan

Python 3.11 atau lebih baru; tidak memerlukan paket tambahan. MySQL tidak diperlukan untuk menjalankan Dataset Manager apabila CSV sumber sudah tersedia.

## Format CSV

Dua file hasil ekspor yang bisa memiliki header Indonesia maupun Inggris:

**Total harian**

```csv
tanggal,total_porsi
2026-10-01,3
2026-10-02,0
```

**Menu harian**

```csv
tanggal,menu_id,nama_menu,porsi
2026-10-01,1,Kopi Susu,2
2026-10-01,2,Americano,1
```

CSV sumber harus konsisten (jumlah porsi per menu = total setiap hari). Header alias seperti `date`, `total_qty`, `menu_name`, `qty` juga diterima. Nama menu tidak digunakan sebagai identitas; `menu_id` digunakan sebagai identitas menu.

## Cara menjalankan

Temukan file hasil ekspor melalui `php artisan jsa:export --help` dan pemeriksaan direktori hasil ekspor. **Jangan menebak jalurnya**.

Setelah jalur diketahui, uji tanpa menulis hasil:

```powershell
python scripts/jsa_dataset_manager.py --total "PATH_KE_DAILY_TOTAL.csv" --menu "PATH_KE_DAILY_MENU.csv" --days 365 --dry-run
```

Jika valid, jalankan tanpa `--dry-run`:

```powershell
python scripts/jsa_dataset_manager.py --total "PATH_KE_DAILY_TOTAL.csv" --menu "PATH_KE_DAILY_MENU.csv" --days 365 --keep 5
python scripts/jsa_dataset_manager.py --verify
```

Tanggal akhir default adalah **kemarin dalam WIB (UTC+7)** sehingga data hari ini tidak tercampur dalam training. Gunakan `--as-of YYYY-MM-DD` hanya untuk replay/snapshot historis. Dataset 365 hari *bukan* batas penyimpanan transaksi; seluruh transaksi mentah tetap berada dalam sistem asal.

## Lokasi hasil (default)

`storage/app/private/jsa/learning/datasets/`

- `latest.json` : penunjuk run valid terbaru
- `runs/<run_id>/manifest.json`
- `runs/<run_id>/active_total.csv`
- `runs/<run_id>/active_menu.csv`

Publikasi dibuat dengan direktori sementara dan penulisan pointer secara atomik. Hash SHA-256 memvalidasi isi output; manifest terbaru juga memiliki SHA-256 pada pointer. Jika satu input invalid, tidak ada versi terbaru yang dipublikasikan. Snapshot identik tidak menghasilkan run tambahan.

Maksimal **5 run terbaru** disimpan secara default (`--keep 5`, rentang 2–20). Pruning hanya menyasar folder run yang dikenali sebagai hasil modul ini. **Tidak pernah menghapus CSV sumber atau data POS**.

## Pemeriksaan kualitas

- Menolak tanggal atau jumlah porsi tidak valid.
- Menolak `menu_id` nonpositif atau duplikat dalam satu tanggal.
- Menolak jumlah per-menu tidak cocok dengan total harian.
- Mengecualikan tanggal di luar jendela dan tanggal setelah cutoff.
- Mencatat tanggal yang hilang, **tanpa mengubahnya menjadi nol palsu**.
- Memberi tanda `ready_for_training` jika setidaknya 60 tanggal, tidak ada tanggal hilang di rentang data yang diamati, dan data terbaru maksimum 7 hari sebelum cutoff. Ini **heuristik kesiapan data**, bukan indikator akurasi model; tiap horizon forecast memiliki persyaratan sendiri.
- Sumber data tetap dilabeli `csv_unverified_origin`; program tidak bisa membuktikan CSV berasal dari transaksi aktual hanya dengan memeriksa header.

## Uji mandiri

```powershell
python -m unittest discover -s scripts/tests -p "test_jsa_dataset_manager.py" -v
```

## Batasan & tahap berikutnya

1. Fase ini **bukan training**, tidak mengubah bobot Qwen3, tidak menambahkan memory JSA dan belum dijadwalkan.
2. Integrasi dengan `jsa:export` pada laptop harus diverifikasi dari hasil `php artisan jsa:export --help` dan CSV yang benar-benar dihasilkan.
3. Penghapusan versi lama berarti riwayat run dataset hanya tersedia sampai jumlah versi yang ditetapkan; data mentah tidak dihapus.
4. Laravel scheduler yang muncul pada `schedule:list` tidak menjamin bahwa Windows Task Scheduler sudah menjalankan `schedule:run` tiap menit.
5. Fase berikutnya membangun evaluasi model dan pelatihan berkala setelah kontrak CSV dan pipeline forecasting lokal sudah diuji.
