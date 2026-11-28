# JIMNY COFFEE — Security Patch R3

Tanggal paket: 28 November 2026

Patch ini ditujukan untuk source snapshot `JIMNY-FINAL-SOURCE-REVIEW-20261128-120653.zip`.
Patch tidak mengubah data bisnis, tidak menjalankan migration, dan tidak mengubah `.env` aktif.
## Perubahan

### 1. Seeder aman untuk production
- `DatabaseSeeder` menolak dijalankan pada `APP_ENV=production`.
- `MenuSeeder` hanya menambahkan menu yang belum ada; tidak memperbarui stok, harga, minimum stok, status aktif, atau deskripsi menu yang sudah ada.
- `CategorySeeder` hanya menambahkan kategori yang belum ada.
- `UserSeeder` hanya berjalan pada environment `local` dengan `JIMNY_SEED_DEFAULT_USERS=true`.
- Password default tidak lagi hardcoded. UserSeeder mewajibkan `JIMNY_SEED_DEFAULT_PASSWORD` minimal 16 karakter dan memakai `firstOrCreate`, sehingga akun yang sudah ada tidak diubah.

Untuk local development, isi `.env` secara manual:
```dotenv
JIMNY_SEED_DEFAULT_USERS=true
JIMNY_SEED_DEFAULT_PASSWORD=isi-dengan-password-lokal-yang-kuat-minimal-16-karakter
```
Jangan aktifkan opsi ini di production.

### 2. Signature Secret Key eksplisit
- `config/midtrans.php` tidak lagi fallback ke `MIDTRANS_SERVER_KEY`.
- Webhook akan menolak konfigurasi yang tidak memiliki `MIDTRANS_SIGNATURE_SECRET_KEY`.
- `.env.example` sekarang mendokumentasikan variabel khusus tersebut.

Sebelum mengaktifkan webhook production, masukkan nilai Signature Secret Key yang benar dari dashboard Midtrans ke `.env` production. Jangan menyalin nilai contoh atau mengirim secret melalui chat.

### 3. Lock pembayaran QRIS terpadu
- Pembuatan Snap Token, pembatalan QRIS, dan expiry otomatis memakai lock key yang sama: `midtrans:snap-token:{payment_id}`.
- Ketiganya menggunakan backend lock yang sama melalui `MIDTRANS_LOCK_STORE`.
- Lease lock default 180 detik.
- Pada production, `MidtransService` menolak backend `file`, `array`, atau backend lokal lain; gunakan `database` atau `redis`.
- Panggilan jaringan Midtrans tetap berada di luar transaksi database.
- Respons `token not found` dari Snap cancel hanya dianggap terminal jika caller sebelumnya mendapat `404` dari Status API dan akan melakukan pemeriksaan Status API ulang. Respons `transaction is in progress` tetap bukan konfirmasi pembatalan.

### 4. Audit perubahan role
- Tabel `user_role_audits` mencatat pembuatan akun dan perubahan role.
- Catatan menyimpan pembuatan akun, perubahan role, dan penghapusan akun; actor, target, role lama/baru, IP, user-agent, serta snapshot nama/email ikut disimpan.
- Foreign key actor/target menggunakan `ON DELETE SET NULL`; snapshot tetap tersedia setelah akun dihapus.
- Audit ditulis dalam transaksi yang sama dengan perubahan akun.
- Kebijakan RBAC tidak diubah: Admin tetap dapat membuat/mengangkat Owner; Owner tidak memperoleh akses pengelolaan akun Admin.

## File yang berubah
- `.env.example`
- `config/jimny.php` (baru)
- `config/midtrans.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/CategorySeeder.php`
- `database/seeders/MenuSeeder.php`
- `database/seeders/UserSeeder.php`
- `database/migrations/2026_11_28_160000_create_user_role_audits_table.php` (baru)
- `app/Models/UserRoleAudit.php` (baru)
- `app/Http/Controllers/UserRoleAuditController.php` (baru)
- `app/Http/Controllers/AkunController.php`
- `routes/web.php`
- `resources/views/admin/akun/index.blade.php`
- `resources/views/admin/audit-role/index.blade.php` (baru)
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/TransactionController.php`
- `app/Services/MidtransService.php`
- `app/Services/PaymentStatusService.php`
- `tests/Feature/JimnySeederSafetyTest.php` (baru)
- `tests/Feature/UserRoleAuditTest.php` (baru, termasuk audit penghapusan akun dan akses halaman audit Admin-only)
- `tests/Feature/MidtransSnapCancellationTest.php` (baru)
- `tests/Feature/MidtransSignatureSecretTest.php` (baru)
- `tests/Manual/stock_reservation_smoke.php`

## Penerapan aman

1. Backup source dan database.
2. Jalankan `Apply-JIMNY-Security-Patch-R3.ps1` pada project yang benar.
3. Periksa `.env` production secara lokal; set:
   - `MIDTRANS_SIGNATURE_SECRET_KEY` dengan secret khusus yang benar.
   - `MIDTRANS_LOCK_STORE=database` atau `redis`.
   - `MIDTRANS_LOCK_SECONDS=180`.
4. Jalankan `php artisan config:clear` setelah mengubah `.env`, lalu jalankan test terlebih dahulu:
```powershell
php artisan test --filter="JimnySeederSafetyTest|UserRoleAuditTest|MidtransSnapCancellationTest|MidtransSignatureSecretTest|PaymentTokenHardeningTest|JimnySecurityInvariantTest"
```
5. Jalankan regression penuh dan frontend build:
```powershell
php artisan test
py -3 -m pytest -q
npm run build
composer audit
```
6. Periksa migration:
```powershell
php artisan migrate:status
php artisan migrate --pretend --
path=database/migrations/2026_11_28_160000_create_user_role_audits_table.php
```
7. Setelah backup database dan review hasil `--pretend`, terapkan hanya migration audit:
```powershell
php artisan migrate --
path=database/migrations/2026_11_28_160000_create_user_role_audits_table.php --force
```

Jangan menjalankan `php artisan db:seed --force` di production. `DatabaseSeeder` sengaja diblokir di environment production. Bila penambahan katalog memang diperlukan, review dan jalankan `CategorySeeder` atau `MenuSeeder` secara terpisah.

## Status verifikasi paket
- PHP syntax check untuk seluruh file PHP yang diubah: PASS.
- PHPUnit/Pest feature tests belum dijalankan dari lingkungan pembuat paket karena ZIP source tidak menyertakan `vendor/` maupun koneksi database project.
- Migration belum diterapkan.
- Midtrans Sandbox dan Production belum diuji dari paket ini.
- Ini adalah patch source; deployment tetap memerlukan test di komputer/server project.
