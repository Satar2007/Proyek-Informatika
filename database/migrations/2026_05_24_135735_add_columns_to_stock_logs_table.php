<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom stock_logs sudah dibuat lengkap di migration:
        // 2026_05_22_113524_create_stock_logs_table.php
        //
        // Migration ini dibiarkan kosong untuk mencegah duplikasi kolom
        // saat menjalankan php artisan migrate:fresh.
    }

    public function down(): void
    {
        // Tidak ada perubahan yang perlu dibalik.
    }
};