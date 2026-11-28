<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('payments')
            ->select('transaction_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('transaction_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('transaction_id')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Migrasi dihentikan: payments memiliki lebih dari satu record '
                . 'untuk transaction_id=' . $duplicate->transaction_id
                . '. Rekonsiliasi manual diperlukan; tidak ada data yang dihapus.'
            );
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('transaction_id', 'payments_transaction_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_transaction_id_unique');
        });
    }
};
