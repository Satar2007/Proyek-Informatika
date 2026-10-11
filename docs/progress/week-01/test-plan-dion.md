# Week 1 Test Plan - Dion

## Ownership
POS, search, kategori, cart, checkout, transaksi, dan struk.

## Skenario Pengujian

| ID | Skenario | Expected Result | Status |
|---|---|---|---|
| DION-01 | Pencarian menu | Hasil relevan | UI TEST PASS |
| DION-02 | Filter kategori | Menu sesuai kategori | UI TEST PASS |
| DION-03 | Menu sold out | Tidak bisa dibeli | AUTOMATED PASS |
| DION-04 | Quantity guard | Batas stok diterapkan | AUTOMATED PASS |
| DION-05 | Kasir lihat transaksi sendiri | Data terisolasi | AUTOMATED PASS |
| DION-06 | Admin cari transaksi | Filter berfungsi | AUTOMATED PASS |
| DION-07 | Invalid transaction filter | Ditolak | AUTOMATED PASS |
| DION-08 | Keranjang kosong | Checkout ditolak | PLANNED |
| DION-09 | Produk duplikat | Qty dan subtotal benar | PLANNED |
| DION-10 | Nama pelanggan | Tersimpan dengan benar | PLANNED |
| DION-11 | Checkout Cash | Transaksi dan stok konsisten | PLANNED |
| DION-12 | Cancel transaksi | State sesuai aturan | PLANNED |
| DION-13 | Expire transaksi | State sesuai aturan | PLANNED |
| DION-14 | Detail dan struk | Data sesuai transaksi | PLANNED |
| DION-15 | Stok berubah saat checkout | Guard bekerja | PLANNED |

## Catatan
Status PASS berdasarkan automated/UI regression
yang telah dijalankan pada Week 1.

Pengujian black-box manual dengan actual result dan
screenshot belum dilaksanakan secara menyeluruh.

Diagram Tahap II belum dibandingkan dalam evidence ini.
