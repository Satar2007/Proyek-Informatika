# Pembaruan GUI: sidebar + wallpaper kopi + animasi transisi

Diterapkan di atas Platform_FULL (vendor dan node_modules asli tetap ada).

## Menjalankan
1. Ekstrak ke `C:\laragon\www\Platform` (atau folder lain).
2. Arahkan document root ke folder `public`, atau jalankan `php artisan serve` dari folder yang berisi `artisan`.
3. `php artisan view:clear`, lalu Ctrl+Shift+R di browser.
4. Aset sudah ada di `public/build`; `npm ci` hanya bila ingin mengubah aset Vite.
5. Database & `.env` memakai milik FIXED. Jalankan `php artisan migrate --seed` bila database masih kosong.

## Berkas GUI yang berubah
- `resources/views/layouts/app.blade.php` (sidebar, topbar, transisi halaman)
- `resources/views/layouts/guest.blade.php` (transisi halaman di halaman login/guest)
- `resources/views/kasir/index.blade.php` (teks "Semua menu ditampilkan" dihapus)
- `resources/views/components/smart-assistant.blade.php` (logo JSA = Coffee Cup Splash)
- `public/css/sidebar-layout.css` (baru), `pos-theme.css`, `ember-theme.css`
- `public/js/page-motion.js` (animasi transisi dari versi PREMIUM)
- `public/images/`: `coffee-cup-splash.png`, `coffee-beans-logo.png`, `coffee-bg-seamless.jpg`

## Revisi 2
- Halaman login: latar wallpaper kopi (`public/css/guest-theme.css`), logo bundar Jimny.
- Logo biji kopi di sidebar dan topbar diganti logo bundar Jimny (`public/images/jimny-logo-round.png`).
- Transisi halaman: ilustrasi kopi penuh dengan zoom pelan, uap dari cangkir, daun dan biji melayang, serta logo Jimny.
  Berkas: `resources/views/components/page-transition.blade.php`, `public/css/page-transition.css`,
  `public/js/page-motion.js`, `public/images/coffee-illustration.jpg`. Dipakai juga saat login dan logout.
