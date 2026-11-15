# JSA Fase 5 — Semantic Quality Gate (Read-only)

Masalah nyata yang memotivasi fase ini: kandidat Qwen3 0.6B mengatakan "kenaikan 25%" lalu "penjualan menurun 25%". Ini bukti bahwa penurunan *evaluation loss* saja belum membuktikan konsistensi kesimpulan.

## Fungsi

- Menguji enam pertanyaan sintetis melalui API Ollama lokal `127.0.0.1:11434/api/chat`.
- Memeriksa kenaikan, penurunan, tidak berubah, pembagi nol, dan klaim asal data.
- Memeriksa bahwa semua persentase eksplisit cocok dengan jawaban yang benar dan arah perubahan tidak kontradiktif.
- Menyimpan laporan pada `storage/app/private/jsa/learning/qwen3/qa-reports` hanya saat dijalankan melalui PowerShell runner.
- Tidak menulis database, memperbarui model, mengubah controller, melakukan fine-tuning, atau memasang scheduler.
- Tidak menjadikan model produksi apabila lulus. Keputusan promosi tetap membutuhkan evaluasi lebih luas dan verifikasi sumber dataset.

## Instalasi

Salin tiga file baru dari ZIP ke folder proyek `jimny-coffee`, tanpa menimpa file yang telah ada.

## Pengujian

```powershell
python -B -m unittest discover -s scripts/tests -p 'test_jsa_semantic_gate.py' -v
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\scripts\Run-JsaSemanticGate.ps1' -Model 'jimny-qwen3:small-a6d71e8f'
```

`python ...` adalah 15 unit test berbasis contoh sintetis; tidak memanggil Ollama. Skrip PowerShell berikutnya benar-benar memanggil Ollama dan menghasilkan laporan JSON. Exit code 0: semua tes terbatas lulus; 2: satu atau lebih pemeriksaan gagal; 3: error konfigurasi. Tidak perlu memaksakan semua model lulus; kegagalan adalah hasil QA yang bermanfaat.

Opsional, bandingkan model utama tanpa mengubah model tersebut:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\scripts\Run-JsaSemanticGate.ps1' -Model 'jimny-qwen3:adaptive'
```

## Batasan

- Pemeriksa berbasis pola dapat salah menilai jawaban yang memiliki konstruksi bahasa kompleks. Kegagalan harus ditinjau manual; kelulusan tidak membuktikan kebenaran umum.
- Hanya enam pertanyaan sintetis. Belum menguji kausalitas, kemampuan rekomendasi restock, *business reasoning* tingkat lanjut, atau keandalan model pada transaksi nyata.
- Dataset Fase 3 masih didominasi contoh selisih porsi dan arah perubahan, belum variasi persentase yang memadai. Perbaikan dataset latihan menjadi pekerjaan berikutnya setelah kegagalan QA dicatat.
- Task `JSA-Qwen3-Small-FineTune` hanya berjalan pada tanggal 1 setiap bulan meskipun jadwal pemeriksaan harian terpasang. Kode 267011 sebelum tanggal tersebut menandakan task belum pernah berjalan.
