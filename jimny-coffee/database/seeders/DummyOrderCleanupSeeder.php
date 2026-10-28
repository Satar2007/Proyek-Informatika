<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus SEMUA data dummy yang dibuat DummyOrderSeeder
 * (transaksi dengan kode berawalan "DMY-" beserta detail dan pembayarannya).
 * Data asli tidak disentuh.
 *
 * Jalankan:  php artisan db:seed --class=DummyOrderCleanupSeeder
 */
class DummyOrderCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $ids = DB::table('transactions')
            ->where('kode_transaksi', 'like', DummyOrderSeeder::PREFIX . '%')
            ->pluck('id');

        DB::transaction(function () use ($ids) {
            foreach ($ids->chunk(500) as $chunk) {
                DB::table('payments')->whereIn('transaction_id', $chunk)->delete();
                DB::table('transaction_details')->whereIn('transaction_id', $chunk)->delete();
                DB::table('transactions')->whereIn('id', $chunk)->delete();
            }
        });

        if ($this->command) {
            $this->command->info('Data dummy dihapus: ' . $ids->count() . ' transaksi.');
        }
    }
}