# JIMNY COFFEE – JSA Qwen3 Adaptive + Actual LoRA Fine-Tuning (Fase 3)

## Status per 15 November 2026

Fase 1 **Dataset Manager** dan Fase 2 **Forecast Candidate Training** harus sudah terpasang pada direktori `jimny-coffee` sebelum memasang paket ini. Pada log pengguna: ekspor 365 hari (11.001 porsi), dataset siap, Ridge terlatih sebagai kandidat tetapi **MAE Ridge 24,615 > baseline 8,097**, sehingga model tidak boleh dipromosikan. Data berasal dari database lokal `satar_acceptance` dan masih diberi label `csv_unverified_origin`.

Paket Fase 3 menambah **dua mekanisme yang tidak boleh disamakan**:

1. **Adaptive knowledge Ollama, berjalan harian**: membangun ringkasan teragregasi 7 dan 28 hari dari dataset yang diverifikasi SHA-256, mengubah SYSTEM prompt dalam model **alias baru** `jimny-qwen3:adaptive` (FROM `qwen3:4b-instruct`). Ini *bukan* training bobot Qwen3. Alias diperbarui dengan `ollama create` dari snapshot baru. Source selalu ditandai belum terverifikasi sebagai penjualan riil.
2. **LoRA / QLoRA Qwen3 nyata, calon terjadwal bulanan**: membuat dataset instruksi berlabel deterministik (perubahan jumlah porsi mingguan), lalu **melatih bobot LoRA** dari `Qwen/Qwen3-4B-Instruct-2507` menggunakan CUDA + PyTorch + PEFT. Menguji *holdout evaluation loss* vs model sebelum training. Kandidat yang lulus dapat digabung, dikonversi menjadi GGUF menggunakan llama.cpp, lalu dimuat sebagai **alias terpisah** `jimny-qwen3:ft-candidate`. Tidak ada promosi otomatis ke model produksi, perubahan stok, atau update MySQL.

**Alasan dua base model berbeda:** versi Ollama `qwen3:4b-instruct` adalah model kuantisasi inferensi. Adapter LoRA tidak boleh sembarang dicampur ke base yang berbeda. QLoRA dilatih pada checkpoint HF yang ditetapkan secara eksplisit, kemudian model HF dan adapter digabung dan dikonversi menjadi GGUF. Tidak mencoba menerapkan adapter HF itu ke base Ollama yang belum terbukti cocok.

## File baru

- `scripts/jsa_qwen3_learning.py`: verifikasi sumber Fase 1; update alias Ollama; generasi dataset instruksi; manifest & checksum.
- `scripts/jsa_qwen3_lora.py`: **training LoRA sebenarnya** menggunakan PyTorch/Transformers/PEFT/QLoRA di NVIDIA CUDA (lazy import).
- `scripts/jsa_qwen3_deploy.py`: merge hasil LoRA yang lolos guardrail, konversi GGUF, impor Ollama sebagai alias kandidat terpisah.
- `scripts/Run-JsaQwen3All.ps1`: gabungan Fase 2 + pembaruan konteks Ollama + data instruksi + LoRA bulanan jika GPU memadai.
- `scripts/Install-JsaQwen3Task.ps1`: instal Windows Task Scheduler pukul 03.40 harian.
- `scripts/Setup-JsaQwen3Gpu.ps1`: pasang dependency pelatihan di virtualenv terpisah dalam `%LOCALAPPDATA%` setelah cek GPU.
- `scripts/Setup-JsaLlamaCpp.ps1`: opsional pasang converter GGUF.
- `scripts/Link-JsaAdaptiveModel.ps1`: integrasi opsional fallback literal controller Laravel dengan backup dan `php -l` rollback; berhenti jika kode lokal tidak cocok.
- `scripts/tests/test_jsa_qwen3_*.py`: 22 unit test standard-library, tidak memerlukan Ollama/GPU.
- `scripts/requirements-qwen3-lora.txt`.

## Urutan instalasi dengan PowerShell 5.1

1. Pastikan branch `feature/jsa-self-learning` dan backup lama masih ada. Ekstrak ZIP paket baru ke `jimny-coffee` dengan validasi **tidak menimpa file yang ada**. Jangan `git add .`, `git reset`, `migrate`, atau `push`.
2. Unit test: `python -B -m unittest discover -s scripts/tests -p "test_jsa_qwen3*.py" -v`. Target 22 tests, bukan `Ran 0 tests`.
3. Jalankan tanpa ekspor ulang pertama kali: `powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Run-JsaQwen3All.ps1 -SkipExport`. Ini **langsung membuat alias `jimny-qwen3:adaptive` di Ollama** dari snapshot terakhir (bukan fine-tuned). Jika gagal karena Ollama belum running, buka Ollama lalu ulangi.
4. Uji `ollama list`; `ollama run jimny-qwen3:adaptive` lalu tanyakan agregat 28 hari. Jangan menyebut ini hasil training bobot Qwen.
5. Untuk mencoba **fine-tuning sebenarnya**, jalankan `& .\scripts\Setup-JsaQwen3Gpu.ps1`; memerlukan CUDA NVIDIA, **VRAM minimum 10 GiB (16 GiB+ disarankan)**, ruang disk dan internet untuk model HF 4B. CPU/Intel/iGPU tidak dikonfigurasi untuk training 4B dalam paket ini. Jika gagal, update knowledge otomatis tetap berfungsi.
6. `& .\scripts\Setup-JsaLlamaCpp.ps1`, lalu `& .\scripts\Run-JsaQwen3All.ps1 -SkipExport -ForceFineTune` untuk menjalankan training LoRA secara nyata bila GPU siap. Sampel/holdout adalah **contoh kalkulasi sintetis turunan data**; hasilnya bukan peningkatan mutu bisnis yang sudah terbukti. Karena sumber CSV belum terverifikasi, tag hanya kandidat sandbox.
7. Setelah diuji, integrasi ke JSA Laravel bisa di-preview dengan `& .\scripts\Link-JsaAdaptiveModel.ps1` dan diaktifkan melalui `& .\scripts\Link-JsaAdaptiveModel.ps1 -Apply`. Script mengubah **hanya fallback string literal** model di controller yang cocok dan melakukan backup. Bila controller menggunakan config/env yang mengabaikan fallback, perlu pemeriksaan kode lokal.
8. Jika belum ada task lama `JSA-SelfLearning-Daily`: `& .\scripts\Install-JsaQwen3Task.ps1`. Akan menjalankan gabungan pada **03.40 lokal setiap hari, dengan pengguna Windows masuk dan MySQL menyala**. Training berat LoRA akan dieksekusi pada tanggal 1 bila environment GPU dan dependencies sudah siap; jika belum, log menyebutkan *skipped*, bukan sukses palsu.

## Batasan yang jelas

- Ollama **tidak melatih bobot otomatis dari chat**. Untuk itu pelatihan eksternal seperti LoRA, kemudian impor ke Ollama diperlukan.
- QLoRA tidak dijalankan dalam 2,5GB model GGUF yang terpasang di Ollama; model HF untuk training berukuran jauh lebih besar, membutuhkan GPU CUDA dan storage signifikan.
- Pengetahuan terkini lebih tepat melalui retrieval/ringkasan harian. Menghafalkan tanggal, nilai stok atau harga dalam bobot model dapat menjadi usang dan berisiko.
- Model hasil LoRA **tidak otomatis menjadi model produksi**. MAE Ridge sebelumnya gagal, dan asal CSV belum diverifikasi. Evaluasi LoRA di sini hanya kerugian prediksi token pada holdout label deterministik, bukan bukti kebenaran semua jawaban.
- Semua file baru disimpan di `storage/app/private/jsa/learning/qwen3`; snapshot CSV POS asli tidak dihapus. Arsip contoh otomatis mempertahankan hingga lima arsip dan tetap tidak menyentuh transaksi asli.
- Tidak menyimpan percakapan pribadi atau identitas pelanggan secara otomatis sebagai training samples. Bila nanti ingin belajar dari chat, perlu persetujuan eksplisit, minimisasi data dan mekanisme koreksi.
- `qwen3:4b-instruct` tetap tidak berubah. Jika perubahan integrasi controller menimbulkan masalah, restore controller dari backup di `%LOCALAPPDATA%\JSA-Controller-Backup-*`, dan gunakan model awal.
- Tugas terjadwal Windows hanya berjalan bila mesin hidup, login, PHP/Python/Ollama ada di PATH dan MySQL aktif. Tidak berarti proses selalu hidup di background ketika PC mati.

## Dokumentasi referensi

- Ollama official docs, Importing a Model: https://docs.ollama.com/import
- Ollama official docs, Modelfile Reference: https://docs.ollama.com/modelfile
- Qwen/Qwen3-4B-Instruct-2507 model card: https://huggingface.co/Qwen/Qwen3-4B-Instruct-2507
- Hugging Face PEFT/TRL LoRA documentation: https://huggingface.co/docs/trl/sft_trainer
- llama.cpp HF converter: https://github.com/ggml-org/llama.cpp/blob/master/convert_hf_to_gguf.py
