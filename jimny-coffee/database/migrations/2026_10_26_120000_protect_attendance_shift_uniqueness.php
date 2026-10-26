<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jangan menghapus / menyatukan duplikat. Tahan migrasi agar data
        // historis dapat direkonsiliasi secara sadar sebelum unique index dibuat.
        foreach (['shifts', 'attendances'] as $table) {
            $duplicate = DB::table($table)
                ->select('user_id', 'tanggal')
                ->groupBy('user_id', 'tanggal')
                ->havingRaw('COUNT(*) > 1')
                ->first();

            if ($duplicate !== null) {
                throw new RuntimeException(
                    "Migrasi dihentikan: {$table} memiliki lebih dari satu record "
                    ."untuk user_id={$duplicate->user_id} dan tanggal={$duplicate->tanggal}. "
                    .'Rekonsiliasi manual diperlukan; tidak ada data dihapus.'
                );
            }
        }

        Schema::table('shifts', function (Blueprint $table) {
            $table->unique(['user_id', 'tanggal'], 'shifts_user_tanggal_unique');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['user_id', 'tanggal'], 'attendances_user_tanggal_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_user_tanggal_unique');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropUnique('shifts_user_tanggal_unique');
        });
    }
};
