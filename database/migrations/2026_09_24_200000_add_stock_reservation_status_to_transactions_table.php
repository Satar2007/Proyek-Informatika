<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function ensureSupportedDatabase(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException(
                'Migration reservasi stok SATAR hanya mendukung database MySQL.'
            );
        }
    }

    public function up(): void
    {
        $this->ensureSupportedDatabase();

        if (
            !Schema::hasColumn(
                'transactions',
                'stock_reservation_status'
            )
        ) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('stock_reservation_status', 20)
                    ->default('none');
            });
        }
    }

    public function down(): void
    {
        $this->ensureSupportedDatabase();

        if (
            !Schema::hasColumn(
                'transactions',
                'stock_reservation_status'
            )
        ) {
            return;
        }

        // Jangan hilangkan penanda setelah fitur mulai digunakan.
        if (
            DB::table('transactions')
                ->where(
                    'stock_reservation_status',
                    '<>',
                    'none'
                )
                ->exists()
        ) {
            throw new RuntimeException(
                'Rollback ditolak: terdapat riwayat reservasi stok.'
            );
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('stock_reservation_status');
        });
    }
};