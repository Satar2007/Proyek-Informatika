<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')
                ->nullable()
                ->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')
                ->nullable()
                ->change();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (
            DB::table('transactions')->whereNull('user_id')->exists()
            || DB::table('stock_logs')->whereNull('created_by')->exists()
        ) {
            throw new \RuntimeException(
                'Rollback ditolak karena terdapat riwayat keuangan '
                .'yang sudah terlepas dari akun pengguna.'
            );
        }

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')
                ->nullable(false)
                ->change();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')
                ->nullable(false)
                ->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
