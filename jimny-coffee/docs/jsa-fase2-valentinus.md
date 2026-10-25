# JSA Fase 2 — handoff Valentinus

## Posisi pekerjaan

Folder Windows: `C:\Users\MSI\Projects\Proyek-Informatika\jimny-coffee`.
Branch: `feature/valentinus-jsa-fase2-forecast`, dibuat dari Fase 1 `8f1421e`.
Repository: https://github.com/Satar2007/Proyek-Informatika.
Tidak ada commit/push otomatis. Jangan gunakan `git add .` karena folder lama
`../bootstrap/` dan `../storage/` masih untracked. Jangan hapus folder itu dulu.

Paket ini hanya menambahkan tiga file berikut pada aplikasi:

- `scripts/jsa_forecast.py`
- `scripts/tests/test_jsa_forecast.py`
- `docs/jsa-fase2-valentinus.md`

Script Fase 0/Fase 1, requirements, UI, .env, dan database tidak diubah.
Integrasi Laravel, penyimpanan tabel jsa_*, scheduler, dan fallback PHP masuk Fase 5.

## Menjalankan di PowerShell

```powershell
Set-Location "C:\Users\MSI\Projects\Proyek-Informatika\jimny-coffee"
& "$env:USERPROFILE\jsa-venv\Scripts\python.exe" -B -m unittest discover -s scripts/tests -p "test_*.py" -v
```

Replay snapshot yang terakhir bertanggal 24 Oktober 2026:

```powershell
& "$env:USERPROFILE\jsa-venv\Scripts\python.exe" scripts\jsa_forecast.py --today 2026-10-25
```

`--today` mengatur tanggal acuan replay, bukan mengganti tanggal data.
Untuk pemakaian harian sesudah integrasi database siap, ekspor CSV terbaru sampai
kemarin lalu jalankan tanpa `--today`. Script menolak data usang dan tidak
memajukan tanggal snapshot lama seolah-olah prediksi hari ini.

Opsi pembanding Ridge, dengan folder hasil terpisah agar hasil auto tetap ada:

```powershell
& "$env:USERPROFILE\jsa-venv\Scripts\python.exe" scripts\jsa_forecast.py --today 2026-10-25 --model ridge_dow_trend --out storage\app\jsa\benchmark\phase2-ridge
```

Tidak perlu aktivasi venv; perintah memakai interpreter venv langsung.
CSV dummy sudah diambil dari `D:\Satar-Integrated-Final.zip` pada komputer Valentinus.
Tidak perlu Laravel/MySQL untuk menjalankan eksperimen offline ini.

## Metode

1. Validasi tanggal harian berurutan pada daily_total dari loader Fase 1.
   CSV menu memerlukan tanggal, menu_id, nama_menu, porsi. ID harus positif,
   porsi bilangan bulat nonnegatif, dan satu pasangan tanggal/ID hanya sekali.
   Isian menu yang tidak ada pada suatu hari diberi nol. Jumlah menu harus
   tepat cocok dengan total setiap hari; kesalahan menghentikan proses.
2. Rolling-origin expanding window dimulai setelah 42 hari. Delapan kandidat
   Fase 1 dipakai ulang; semua model hanya menerima prefix y[:t]. Prediksi
   negatif dipotong menjadi nol sebelum evaluasi/pemilihan.
3. Mode auto: 14 origin awal memakai seasonal_mean4 sebagai warmup. Setelah
   itu, aturan 1-SE memilih model dengan galat kandidat yang sudah diketahui
   sebelum tanggal prediksi. Tidak membaca hasil benchmark JSON lama atau
   memilih model dengan seluruh data masa depan untuk menilai hari sebelumnya.
   Perhitungan 1-SE tetap heuristik Fase 1; SE biasa belum mengoreksi autokorelasi.
4. Proporsi menu memakai jumlah porsi 28 hari terakhir. Menu yang ADI pada
   jendela itu >=1.32, termasuk tanpa penjualan, memakai skor Croston-SBA.
   ADI = banyak hari / banyak hari dengan penjualan positif.
   Croston memperbarui ukuran permintaan dan interval antarpenjualan positif,
   alpha=0.1 tetap; koreksi SBA = (1-alpha/2) * ukuran/interval.
   Semua skor menu lalu diskalakan supaya jumlah prediksi menu sama dengan
   prediksi total. Karena itu hasil menu SBA adalah SBA yang direkonsiliasi,
   bukan output SBA mentah. JSON mencatat skor mentah dan faktor skalanya.
5. Porsi bulat memakai largest remainder dengan target pembulatan half-up
   total. Jumlah porsi bulat menu selalu sama dengan porsi bulat total.
   Titik pecahan tetap disimpan untuk evaluasi agar tidak mencampur pembulatan
   operasional dengan hasil model.
6. Interval simetris memakai kuantil galat absolut pipeline sebelumnya,
   minimum 20 residual, maksimum 60 terbaru. Rank = ceil((m+1)*level),
   default level 0.80. Batas bawah floor(point-radius) dipotong minimal nol;
   batas atas ceil(point+radius). Tidak memakai aktual hari yang sedang diuji.
   Menu memakai galat menu sesudah rekonsiliasi, bukan galat total dibagi rata.
7. Cakupan hanya dihitung pada origin yang intervalnya sudah tersedia:
   jumlah hari aktual berada di dalam batas / jumlah hari dengan interval.
   Dengan 90 hari, 48 origin evaluasi titik, 20 origin awal untuk kalibrasi,
   sehingga ada 28 origin untuk mengukur cakupan interval.

Interval ini rentang prediksi permintaan satu hari, bukan confidence interval
rata-rata. Koreksi rank finite-sample tidak memberi jaminan cakupan conformal
pada deret waktu dependen. Rentang menu masing-masing bersifat marginal;
jangan menjumlahkan semua batas bawah/atas untuk membuat rentang total.

## Label kepercayaan: heuristik, bukan persentase probabilitas

- `data belum cukup`: interval belum ada, kurang dari 20 evaluasi interval,
  atau kurang dari 14 hari penjualan positif pada seri.
- `tinggi`: minimal 60 evaluasi interval, coverage >=75%, dan rerata lebar
  interval <=2*max(prediksi titik,1).
- `sedang`: syarat data cukup dan coverage >=65%, tetapi syarat tinggi belum terpenuhi.
- `rendah`: data cukup, tetapi coverage <65%.

Threshold label adalah kebijakan awal yang transparan, bukan validasi ilmiah
bahwa suatu menu mudah diprediksi. Coverage yang besar bisa disebabkan rentang
lebar dan banyak nol. Gunakan mean_width, MAE, dan jumlah sampel pada JSON
bersamaan; jangan mengklaim bahwa 100% coverage berarti model sempurna.

## Hasil verifikasi pada snapshot teman

90 hari (27 Juli–24 Oktober 2026), 2828 porsi, 42 menu.
Replay untuk 25 Oktober 2026, kebijakan auto:

| Ukuran | Hasil |
|---|---|
| Model akhir | seasonal_mean4 |
| Prediksi titik total | 49.75 porsi |
| Porsi bulat total / jumlah porsi bulat menu | 50 / 50 |
| Rentang target 80% total | 32–68 porsi |
| Cakupan total | 23/28 = 82.14% |
| Label total | sedang |
| Tea Telang | data belum cukup |

Seluruh 49 tes lulus di sandbox: 27 tes lama + 22 tes Fase 2, Python 3.12,
numpy 2.3.5, scikit-learn 1.8.0. CLI auto juga dijalankan pada kedua CSV asli.
Komputer Valentinus: 27 tes Fase 1 sudah lulus pada Python 3.14.8,
numpy 2.5.3, scikit-learn 1.9.1. Tes Fase 2 di Windows masih harus dijalankan
setelah paket dipasang. Perbedaan kecil hasil antarversi library dimungkinkan.

Tes baru meliputi sparse zero-fill, konsistensi total, duplikasi/tanggal/angka
invalid, SBA, pembulatan, semua nol, konservasi total, prediksi deterministik,
mode Ridge, kalibrasi kurang, warmup tidak dihitung ke coverage, penolakan data
usang, output CLI, pemilihan model dan interval tanpa melihat aktual masa depan.

## File hasil

Default: `storage\app\jsa\benchmark\phase2\`:

- `forecast_result.json`: prediksi, model, konfigurasi, evaluasi, backtest, catatan.
- `forecast_menu.csv`: prediksi menu, porsi bulat, interval, label, coverage, MAE.
- `forecast_backtest.csv`: aktual/prediksi/interval per hari, total dan semua menu.
- `forecast_report.txt`: laporan yang sama dengan terminal.

Output di storage/app mengikuti aturan ignore Laravel, tidak perlu di-commit.

## Batasan dan kelanjutan

Data dummy hanya membuktikan pipeline, bukan ketepatan pada bisnis nyata.
Nol penjualan tidak membedakan hari tutup, stok habis, atau tidak ada permintaan.
Menu yang belum pernah terjual tidak muncul di daily_menu.csv. Belum ada data
aktivasi/penghapusan menu; nol sebelum penjualan pertama merupakan asumsi.
SBA dapat bertahan pada menu yang lama tidak terjual: belum ada informasi menu
nonaktif untuk mematikannya. Stok tidak dipakai untuk mengubah prediksi permintaan.

Snapshot hanya satu horizon: hari sesudah data terakhir, bukan prediksi 7 hari.
Ini bukan keputusan restok: Fase 3 perlu memisahkan permintaan dari stok saat
ini dan target tingkat layanan. Fase 5 baru menghubungkan Python ke Laravel.

Sebelum commit oleh Valentinus, jalankan tes di Windows dan periksa:

```powershell
git status --short
```

Jika hasil sudah ditinjau, hanya tambahkan tiga file baru spesifik ini dari
folder aplikasi. Jangan memakai `git add .` dan jangan mengubah identitas Git
menjadi identitas teman. Commit/push tetap dikerjakan sendiri oleh Valentinus.
