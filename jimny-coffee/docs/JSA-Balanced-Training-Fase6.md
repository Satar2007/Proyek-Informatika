# JSA Fase 6 — Balanced Numeracy QLoRA & Semantic QA Gate

## Latar belakang

Kandidat `jimny-qwen3:small-a6d71e8f` gagal 5/6 tes Semantic QA Fase 5: arah kenaikan,
penurunan persentase, tidak berubah, arah penurunan dan nilai awal nol.
Model dilarang dipromosikan ke JSA produksi. Penyebab yang dapat diverifikasi pada kode adalah
kurikulum Fase 3 melatih **selisih dan arah**, bukan **persentase** secara eksplisit.
Tidak ada jaminan retraining akan menyelesaikan persoalan penalaran numerik.

## Prinsip dan pengamanan

- Tidak mengganti modul Fase 1–5, tidak mengubah Laravel, tidak mengubah transaksi atau stok.
- Hanya membaca snapshot agregat yang hash dan status kesiapannya terverifikasi.
- Dataset 365 hari adalah CSV asal belum terverifikasi. **Semua kandidat sandbox**.
- Data latihan seimbang: naik, turun, tetap, pembagi nol, dan kejujuran asal data.
- Angka sintetis dibangkitkan deterministik, **bukan fakta bisnis**.
- Lima pasang contoh pada Semantic QA Fase 5 **dikecualikan** dari training dan evaluasi.
- Data POS disekat kronologis dengan embargo 14 hari; kombinasi angka identik antar-split dibuang.
- Hash fingerprint baru mencakup versi resep dan isi train/eval, supaya tidak mengulang run lama.
- Training GPU hanya ketika tanggal 1 atau `-ForceTrain`, di alias sandbox terpisah.
- Impor sandbox baru jika loss membaik, lalu QA wajib. Jika QA gagal, model tidak dipromosikan.
- **Kandidat tidak pernah otomatis dipakai di controller Laravel**, meski QA lulus.
- Manajemen retensi model belum diotomatisasi. Evaluasi kapasitas disk sebelum menambah banyak kandidat.

## Urutan PowerShell 5.1

Setelah mengekstrak empat file script/test dan satu file dokumentasi ke root Laravel:

```powershell
# Hentikan task lama yang masih memakai kurikulum yang gagal QA; tidak menghapus file/model.
Disable-ScheduledTask -TaskName 'JSA-Qwen3-Small-FineTune'

# Lihat dulu konfigurasi dan ukuran contoh; ini tidak menulis berkas.
python -B scripts/jsa_qwen3_balanced.py dry-run

# Latih model kandidat baru, tidak diimpor dulu ke Ollama.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\scripts\Run-JsaQwen3Balanced.ps1' -ForceTrain -Sandbox -SkipDeploy

# Jika training berhasil, gunakan adapter sama lalu impor sebagai sandbox dan uji QA.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\scripts\Run-JsaQwen3Balanced.ps1' -ForceTrain -Sandbox

# Sesudah kualitasnya ditinjau, baru aktifkan jadwal baru (bukan task lama).
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\scripts\Install-JsaQwen3BalancedTask.ps1' -AllowSandbox
```

Jadwal lama Ollama 4B `JSA-Qwen3-AutoLearning` pukul 03.40 tidak disentuh.
Jadwal Fase 6, bila dipasang, memeriksa setiap hari 05.10 dan hanya melatih adapter pada tanggal 1. Otomasi selalu memakai `-SkipDeploy`: impor Ollama dan QA tetap langkah manual, tidak otomatis dipromosikan.
Laptop, MySQL, Python dan Ollama harus tersedia. Paket tidak mengeksekusi perintah itu secara otomatis.

## Batasan

*Model yang menghafal pola aritmetika masih dapat membuat kesalahan bahasa atau generalisasi.*
Untuk angka bisnis yang berdampak operasional, hitung metrik di PHP/Python secara deterministik,
berikan angka dan provenance ke LLM hanya untuk penjelasan, lalu validasi jawaban sebelum ditampilkan.
Empat skrip ini **belum** memasang kalkulator deterministik ke endpoint chat Laravel.
