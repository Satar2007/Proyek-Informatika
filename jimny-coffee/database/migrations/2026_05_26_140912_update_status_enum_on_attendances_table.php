<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendances MODIFY status ENUM('hadir', 'terlambat', 'izin', 'tidak_hadir') DEFAULT 'tidak_hadir'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attendances MODIFY status ENUM('hadir', 'izin', 'tidak_hadir') DEFAULT 'tidak_hadir'");
    }
};