# JSA Self-Learning Fase 2: Automated Forecast Model Training

## Apa yang *benar-benar* berubah

Modul `jsa_auto_train.py` melakukan **fitting model linear ridge (hari dalam minggu + tren waktu)**, memilih konfigurasi berdasarkan walk-forward evaluation pada data *sebelum* holdout, lalu mengevaluasinya pada 28 hari terakhir. Baseline musiman dan moving average menjadi pembanding. Model Ridge betul-betul mempunyai koefisien yang diestimasi ulang dari data terbaru. Proses menyimpan model, evaluasi, dan ringkasan bisnis 28 hari yang teragregasi.

**Belum ada fine-tuning bobot `qwen3:4b-instruct`.** Ollama masih menjadi LLM yang memerlukan langkah integrasi read-only supaya ringkasan `business_context.json` bisa masuk ke prompt secara terkontrol. Dengan sengaja modul ini tidak menimpa controller JSA lama, `jsa_forecast.py`, atau model produksi karena struktur kerja lokal berisi banyak perubahan belum committed.

## File baru

- `scripts/jsa_auto_train.py`: candidate training + model registry read-only terhadap POS.
- `scripts/tests/test_jsa_auto_train.py`: 10 unit test.
- `scripts/Run-JsaSelfLearning.ps1`: ekspor CSV, dataset rolling, dan training tanggal 1 (atau `-ForceTrain`).
- `scripts/Install-JsaSelfLearningTask.ps1`: memasang tugas Windows **hanya jika dijalankan sengaja**.
- `docs/jsa-self-learning-phase2.md`: dokumentasi ini.

## Cara instalasi

Ekstrak paket pada root `jimny-coffee` dari ZIP. Jangan menimpa `scripts/jsa_dataset_manager.py` dari fase 1; file itu sudah terpasang. Lakukan pengecekan file/branch sebelum pemasangan. Tidak perlu `composer install`, `npm install`, atau `php artisan migrate`.

## Training manual pertama

```powershell
python -B -m unittest discover -s scripts/tests -p "test_jsa_auto_train.py" -v
& .\scripts\Run-JsaSelfLearning.ps1 -ForceTrain
python -B scripts/jsa_auto_train.py --verify
```

Proses di atas akan melakukan ekspor **baru** dari database yang terhubung ke `.env` (saat audit bernama `satar_acceptance`), membuat dataset harian 365 hari, dan melatih model kandidat. Tidak ada update/insert/delete data POS oleh modul Python. `jsa:export` dipanggil sebagai **command read-only** milik proyek yang harus tetap diaudit terpisah sebelum dipakai di production.

## Otomatis 03:10 lokal

Setelah training manual berhasil dan environment PHP/Python/MariaDB stabil:

```powershell
& .\scripts\Install-JsaSelfLearningTask.ps1
Get-ScheduledTask -TaskName "JSA-SelfLearning-Daily"
```

Task Windows menjalankan ekspor + dataset setiap hari pada pukul 03:10 dan training pada tanggal 1. Windows harus menyala, user log-in, serta layanan MySQL aktif. `StartWhenAvailable` meminta menjalankan saat tersedia apabila jadwal terlewat, tetapi bukan jaminan. Scheduled Task tidak sama dengan scheduler Laravel. Log berada dalam `storage/logs/jsa-learning-*.log`. Pastikan PATH php/python tersedia di konteks Task Scheduler.

Jangan memasang task sebelum run manual berhasil.

## Lokasi hasil dan retensi

- `storage/app/private/jsa/learning/exports/export-*/` hanya 3 snapshot terbaru hasil wrapper; CSV sumber lama di `storage/app/jsa` tidak disentuh.
- `storage/app/private/jsa/learning/datasets` maksimal 5 versi hasil fase 1.
- `storage/app/private/jsa/learning/models/runs/train_*/` maksimal 5 versi kandidat; `latest_candidate.json` menunjuk kandidat terbaru dan diverifikasi hash.

Isi tiap run model: `model.json` (koefisien ridge), `evaluation.json` (walk-forward backtest), `business_context.json` (agregat dan prediksi untuk integrasi Ollama berikutnya), `manifest.json` (metadata dan checksum).

**Catatan penting:** apabila dataset berasal dari `satar_acceptance`, data simulasi, atau hasil seeding, evaluasi tidak dapat dianggap bukti akurasi bisnis nyata. Semua kandidat ditandai `source_verified = false`, `production_promoted = false`, `ollama_finetuned = false`. Restock, stok, transaksi, dan forecast produksi **tidak berubah**.

## Cara memeriksa hasil (tanpa mengubahnya)

```powershell
python -B scripts/jsa_auto_train.py --verify
Get-Content storage\app\private\jsa\learning\models\latest_candidate.json
```

## Tahap selanjutnya

1. Validasi bahwa data `satar_acceptance` representatif, bukan simulasi, sebelum memengaruhi keputusan bisnis.
2. Integrasikan `business_context.json` ke controller Ollama sebagai konteks **terbatas dan read-only** setelah memeriksa source code yang benar-benar sedang terpasang pada laptop.
3. Tambahkan evaluasi model produksi vs kandidat dan promosi dengan batasan serta rollback yang disetujui.
4. Fine-tuning LoRA Qwen3 adalah langkah opsional terpisah, dengan dataset instruksi berlabel dan evaluasi khusus. Ollama sendiri tidak melakukan automatic fine-tuning dari chat.
