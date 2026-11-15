# JSA Fase 8 — Integrasi konservatif kalkulator hipotetis

## Ruang lingkup

Patch ini hanya menangani pertanyaan **hipotetis eksplisit** yang dimulai `Jika`, `Misal`, `Misalnya`, `Contoh`, `Simulasi`, atau `Hipotetis`, menyertakan tepat dua bilangan bulat nonnegatif, satuan `porsi` atau `transaksi`, dan meminta `persen`/`persentase`. Format yang didukung: `kemarin 120 porsi dan hari ini 150 porsi`, atau `dari 150 porsi menjadi 120 porsi`, atau `dari 90 ke 60 porsi`.

Pertanyaan aktual (`berapa omzet hari ini`), forecast, rekomendasi, konteks riwayat, nilai negatif, angka tambahan, dan permintaan campuran tidak dicegat. Mereka tetap melalui jalur lama. Tidak ada klaim bahwa angka input berasal dari POS.

## Alur dan keamanan

- Owner/Admin: otorisasi dan validasi request asli di `JimnyOwnerV20Controller` **tetap berjalan lebih dulu**. Hanya setelah validasi, kalkulator menangani pertanyaan hipotetis sebelum snapshot DB.
- Kasir: validasi request asli di `JimnyLocalAiController` **tetap berjalan lebih dulu**. Pertanyaan hipotetis dijawab tanpa membaca transaksi, sehingga tidak membocorkan data role lain.
- Jawaban berupa JSON dengan `answer`, `engine=calculator:explicit_change`, `source=user_hypothetical`, `calculation_verified=true`, `data_source_verified=false`. Frontend yang diunggah mendukung JSON dan NDJSON.
- Semua query POS aktual, stream Ollama kasir, serta respons Owner V20 lain tetap tidak berubah.
- Tidak mengubah `JsaBusinessCalculator` Fase 7, routes, Blade, model Ollama, scheduler, DB atau stok.
- Ini **bukan** validator bebas untuk seluruh teks AI, dan tidak menjamin akurasi jawaban analitik aktual. Integrasi berikutnya harus menguji konteks sumber dan konsistensi snapshot Owner dengan pengujian endpoint berbasis DB yang terisolasi.

## Instalasi

Ekstrak ZIP ke folder sementara, lalu di PowerShell 5.1:

```powershell
$Project = 'C:\Users\b7235\Folder Baru\Downloads\Proyek Informatika C\Satar-Integrated-Final\jimny-coffee'
# Dari direktori hasil ekstraksi ZIP:
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\Install-JsaFase8.ps1' -Project $Project
# Jika dry-run lulus, instal secara eksplisit:
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\Install-JsaFase8.ps1' -Project $Project -Apply
```

Installer memeriksa SHA256 kedua controller yang diunggah pada source review; jika berubah, instalasi **ditolak** tanpa menimpa file. Backup controller disimpan di `storage/app/private/jsa/fase8-backup-*`. Jika pengujian atau route list gagal, installer berusaha mengembalikan controller dan menghapus hanya file Fase 8 yang baru dibuat.

## Pengujian

```powershell
php artisan test --filter=JsaExplicitChangeResponderTest
php artisan test --filter=JsaBusinessAccuracyTest
php artisan route:list --path=smart-assistant
```

Uji manual melalui UI chat setelah instalasi:

1. `Jika penjualan kemarin 120 porsi dan hari ini 150 porsi, berapa persen kenaikannya?` → naik 25%, sumber `user_hypothetical`.
2. `Misalnya penjualan berubah dari 150 porsi menjadi 120 porsi. Berapa persen perubahan?` → turun 20%.
3. `Contoh: dari 80 porsi menjadi 80 porsi, berapa persen perubahan?` → tetap 0%.
4. `Misal dari 0 porsi menjadi 12 porsi, berapa persen perubahan?` → tidak terdefinisi.
5. `Berapa omzet hari ini?` → **tetap jalur POS role-scoped**; jangan samakan data tugas sintetis dengan transaksi bisnis nyata.

Jika ada masalah, gunakan backup controller dari direktori `fase8-backup-*`; jangan reset repo atau menimpa file lain.
