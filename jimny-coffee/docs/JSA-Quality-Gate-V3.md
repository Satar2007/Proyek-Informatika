# JSA Quality Gate V3: audit konservatif

V3 adalah alat **audit offline atas kutipan jawaban** dari laporan Fase 9D. Laporan menyimpan `answer_excerpt` maksimum 450 karakter, bukan respons lengkap. Karena itu, V3 **tidak menerbitkan skor akurasi baru, tidak memberi status lulus dari kutipan, dan tidak mengizinkan promosi**.

Pemeriksaan mengidentifikasi kontradiksi eksplisit, termasuk `0% (arah negatif)`, pernyataan keliru bahwa pembagian nol dapat dihitung, dan klaim CSV belum diaudit pasti berisi transaksi nyata. Frasa yang benar seperti `positif` dan `tidak ada perubahan` tidak otomatis dianggap gagal. Klaim lain berstatus `review` karena tidak dapat diputuskan secara andal dari kutipan.

## Jalankan audit tanpa Ollama

```powershell
$InputReport = 'storage\app\private\jsa\learning\qwen3\qa-reports\benchmark-120-20261115-200454.json'
$OutputReport = 'storage\app\private\jsa\learning\qwen3\qa-reports\benchmark-120-v3-audit.json'
python -B scripts\jsa_quality_gate_v3.py --input $InputReport --output $OutputReport
```

File input tidak diubah. Output baru tidak boleh menimpa file yang sudah ada. Hasil audit bukan bukti perbaikan kualitas model. Untuk evaluasi lengkap diperlukan penyimpanan respons penuh, dataset evaluasi independen, dan penilaian yang diaudit secara manual sebelum mengizinkan promosi.
