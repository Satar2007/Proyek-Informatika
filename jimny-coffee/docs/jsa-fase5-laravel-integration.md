# JSA Fase 5 — Laravel Read-only Integration

## Tujuan

Fase 5 menghubungkan immutable decision package Fase 4
dengan aplikasi Laravel JIMNY COFFEE.

Integrasi bersifat read-only.

Laravel tidak menghitung ulang keputusan Fase 3
dan tidak mengubah stok.

## Komponen

### JsaDecisionService

Service membaca:

`storage/app/private/jsa/latest.json`

Kemudian:

1. memvalidasi schema version;
2. memvalidasi run ID;
3. membaca manifest dari run directory;
4. memastikan `read_only = true`;
5. memastikan `auto_restock = false`;
6. memverifikasi SHA-256 seluruh artifact;
7. memverifikasi package fingerprint;
8. membaca payload restock Fase 3.

Laravel tidak mempercayai path manifest dari input.
Run directory dibangun dari run ID yang divalidasi.

## Endpoint

`GET /smart-assistant/decisions`

Route name:

`smart-assistant.decisions`

Akses:

- Admin: diizinkan;
- Owner: diizinkan;
- Kasir: 403;
- Guest: ditangani middleware auth.

## Response

Response berisi:

- run ID;
- waktu pembuatan;
- status handoff;
- review flags;
- payload restock asli;
- hasil integrity verification;
- safety declaration.

## Safety

Fase 5 selalu menyatakan:

- `read_only = true`
- `auto_restock = false`
- `stock_mutated = false`
- `reservation_recalculated = false`
- `human_review_required = true`

Tidak ada operasi:

- update `menus.stok`;
- insert stock movement;
- auto purchase;
- auto order;
- pengurangan reservation kedua kali.

## Integrity failure

Jika artifact berubah setelah Fase 4,
endpoint mengembalikan:

HTTP `409`

dengan status:

`BLOCKED`

Payload rekomendasi tidak disajikan.

## Pipeline akhir

Fase 0 — analytics export

→ Fase 1 — benchmark

→ Fase 2 — forecast

→ Fase 3 — restock recommendation

→ Fase 4 — immutable operational handoff

→ **Fase 5 — verified Laravel read-only delivery**

Keputusan pembelian/restock tetap dilakukan manusia.
