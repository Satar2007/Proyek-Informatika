# JSA Fase 9A/9B: training terjadwal dan quality gate v2

## Apa yang tersedia
- Fase 6 yang sudah ada dapat menjadwalkan QLoRA **Qwen3 0.6B** tanggal 1 pukul 05:10, dengan `-Sandbox -SkipDeploy`. Task didaftarkan harian tetapi runner hanya melatih tanggal 1. Perangkat harus hidup, user login, dependensi siap, dan data lolos pemeriksaan. Task `Ready` bukan bukti berhasil berjalan.
- `jsa_quality_gate_v2.py` melakukan evaluasi **read-only** terhadap kandidat Ollama sandbox dan 4B adaptif pada enam soal yang sama, menghasilkan JSON perbandingan.
- `promotion_authorized` **selalu false**. Evaluasi enam soal tidak cukup untuk promosi produksi, terlebih sumber CSV belum terverifikasi dan kandidat 0.6B berbeda ukuran dari 4B.

## Instalasi
Jalankan `Install-JsaFase9.ps1 -Project <folder>` untuk preflight, lalu `-Apply` untuk memasang hanya file baru. Installer tidak mendaftarkan scheduler.

## Mengaktifkan training bulanan (setelah siap)
Dari root proyek, dan setelah memeriksa bahwa task lama nonaktif:

```powershell
Get-ScheduledTask | Where-Object TaskName -like 'JSA*' | Select-Object TaskName,State
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\Install-JsaQwen3BalancedTask.ps1 -AllowSandbox
```

Installer Fase 6 akan menolak jika task bernama `JSA-Qwen3-Balanced-FineTune` sudah ada. Jangan mendaftarkan dua training GPU sekaligus. Untuk memeriksa hasil scheduler, gunakan `Get-ScheduledTaskInfo -TaskName JSA-Qwen3-Balanced-FineTune` dan log `storage/logs/jsa-qwen3-balanced-*.log`.

## QA kandidat sandbox (opsional)
Runner Fase 6 dengan `-SkipDeploy` hanya menghasilkan adapter; kandidat belum tersedia di Ollama sampai proses `deploy --sandbox` manual dilakukan. **Jangan menjalankan deployment secara otomatis tanpa peninjauan.** Setelah kandidat sudah tersedia di `ollama list`:

```powershell
python -B scripts\jsa_quality_gate_v2.py --candidate jimny-qwen3:small-df754658 --incumbent jimny-qwen3:adaptive --report storage\app\private\jsa\learning\qwen3\qa-reports\comparison-v2.json
```

Hasil QA ini adalah smoke test, bukan persetujuan mengganti model. Jika Ollama tidak tersedia, evaluasi gagal tertutup.

## Langkah yang masih diperlukan untuk promosi otomatis
- Sumber data dan izin pemakaiannya terverifikasi, data uji terpisah dari training dan prompt.
- Evaluasi tugas POS dan keamanan role Owner/Kasir pada dataset yang jauh lebih besar, dengan ambang yang ditetapkan sebelumnya dan pembandingan terhadap incumbent.
- Konfigurasi alias model aktif yang terpisah dari model 4B, health check pasca-promosi, rollback atomik, serta log audit.
- Validasi bahwa hasil model 0.6B benar-benar mengungguli 4B pada tugas yang akan dialihkan, bukan hanya loss training.

Tidak mengubah POS, controller Laravel, Ollama, model aktif, maupun scheduler selama pemasangan.
