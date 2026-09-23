<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE transactions
            MODIFY status ENUM('pending', 'success', 'cancelled', 'expired')
            DEFAULT 'pending'
        ");

        DB::statement("
            ALTER TABLE transactions
            MODIFY payment_status ENUM('unpaid', 'paid', 'expired', 'failed', 'cancelled')
            DEFAULT 'unpaid'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE transactions
            MODIFY status ENUM('pending', 'success', 'cancelled')
            DEFAULT 'pending'
        ");

        DB::statement("
            ALTER TABLE transactions
            MODIFY payment_status ENUM('unpaid', 'paid', 'expired', 'failed')
            DEFAULT 'unpaid'
        ");
    }
};