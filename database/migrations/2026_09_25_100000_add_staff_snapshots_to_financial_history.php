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
            $table->string('cashier_name_snapshot')
                ->nullable()
                ->after('user_id');

            $table->string('cashier_role_snapshot')
                ->nullable()
                ->after('cashier_name_snapshot');
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->string('creator_name_snapshot')
                ->nullable()
                ->after('created_by');

            $table->string('creator_role_snapshot')
                ->nullable()
                ->after('creator_name_snapshot');
        });

        DB::table('transactions')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(500, function ($transactions) {
                $users = DB::table('users')
                    ->whereIn(
                        'id',
                        $transactions
                            ->pluck('user_id')
                            ->filter()
                            ->unique()
                            ->values()
                    )
                    ->get(['id', 'name', 'role'])
                    ->keyBy('id');

                foreach ($transactions as $transaction) {
                    $user = $users->get($transaction->user_id);

                    if (!$user) {
                        continue;
                    }

                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update([
                            'cashier_name_snapshot' => $user->name,
                            'cashier_role_snapshot' => $user->role,
                        ]);
                }
            });

        DB::table('stock_logs')
            ->whereNotNull('created_by')
            ->orderBy('id')
            ->chunkById(500, function ($logs) {
                $users = DB::table('users')
                    ->whereIn(
                        'id',
                        $logs
                            ->pluck('created_by')
                            ->filter()
                            ->unique()
                            ->values()
                    )
                    ->get(['id', 'name', 'role'])
                    ->keyBy('id');

                foreach ($logs as $log) {
                    $user = $users->get($log->created_by);

                    if (!$user) {
                        continue;
                    }

                    DB::table('stock_logs')
                        ->where('id', $log->id)
                        ->update([
                            'creator_name_snapshot' => $user->name,
                            'creator_role_snapshot' => $user->role,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropColumn([
                'creator_name_snapshot',
                'creator_role_snapshot',
            ]);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'cashier_name_snapshot',
                'cashier_role_snapshot',
            ]);
        });
    }
};
