<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE payments
            MODIFY payment_status ENUM('waiting', 'paid', 'expired', 'failed', 'cancelled')
            DEFAULT 'waiting'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE payments
            MODIFY payment_status ENUM('waiting', 'paid', 'expired', 'failed')
            DEFAULT 'waiting'
        ");
    }
};