# JSA Fase 7: kalkulator deterministik dan validator klaim terstruktur

Paket **tambahan**, tidak mengubah controller, routes, model Ollama, database, scheduler, atau kode sebelumnya.

- `JsaBusinessCalculator::change($previous, $current)` menghasilkan `difference`, `direction` (`naik`, `turun`, `tetap`), dan persentase bertanda; `null` bila nilai awal nol.
- `JsaBusinessCalculator::explain()` menghasilkan teks pasti dari angka yang diberikan, **tanpa menyatakan sumbernya telah diverifikasi**.
- `JsaAnswerValidator::validateChangeClaim()` memeriksa **klaim terstruktur** berupa `['direction' => 'naik', 'percentage' => 25.0]`. Ini **bukan** validator teks bebas LLM.
- `safeExplanation()` selalu mengembalikan jawaban hasil kalkulator, tidak pernah menampilkan klaim angka AI yang salah.

## Uji di proyek Laravel

```powershell
php -l app/Services/JsaBusinessCalculator.php
php -l app/Services/JsaAnswerValidator.php
php -l tests/Unit/JsaBusinessAccuracyTest.php
php artisan test --filter=JsaBusinessAccuracyTest
```

## Integrasi selanjutnya

Jangan langsung menempatkan validator ini di jalur streaming NDJSON karena keluaran token sudah ditampilkan sebelum dapat diperiksa. Untuk pertanyaan numerik dengan dua nilai eksplisit, hitung sebelum streaming dan kembalikan respons deterministik. Untuk pertanyaan data POS, ambil angka dari query **sesuai role** sebelum memanggil kalkulator. Jangan menafsirkan semua angka dari chat bebas sebagai pasangan penjualan tanpa parser/intent teruji. Owner V20 sudah menghitung `changes` di `snapshot`; hindari query ulang atau menimpa nilainya. Kualitas data tetap perlu dibedakan dari kebenaran perhitungan.

Paket ini tidak memperbaiki Fase 5 semantic gate Python. Evaluator itu harus diperbaiki terpisah, dengan uji negasi, persentase negatif, dan kesimpulan kontradiktif.
