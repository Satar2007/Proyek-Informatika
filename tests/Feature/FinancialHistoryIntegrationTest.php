<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialHistoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_keeps_cashier_identity_after_user_is_deleted(): void
    {
        $cashier = User::factory()->create([
            'name' => 'Kasir Historis',
            'role' => 'kasir',
        ]);

        $transaction = Transaction::create([
            'kode_transaksi' => 'HISTORY-'.uniqid(),
            'user_id' => $cashier->id,
            'nama_pelanggan' => 'Pelanggan Historis',
            'total' => 25000,
            'pajak' => 0,
            'diskon' => 0,
            'grand_total' => 25000,
            'status' => 'success',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        $this->assertSame(
            'Kasir Historis',
            $transaction->cashier_name_snapshot
        );

        $this->assertSame(
            'kasir',
            $transaction->cashier_role_snapshot
        );

        $cashier->delete();

        $transaction->refresh();

        $this->assertNull($transaction->user_id);
        $this->assertNull($transaction->user);

        $this->assertSame(
            'Kasir Historis',
            $transaction->cashier_display_name
        );

        $this->assertSame(
            'kasir',
            $transaction->cashier_display_role
        );
    }

    public function test_financial_history_views_use_cashier_display_name_accessor(): void
    {
        $views = [
            resource_path('views/transaksi/index.blade.php'),
            resource_path('views/transaksi/show.blade.php'),
            resource_path('views/laporan/harian.blade.php'),
            resource_path('views/laporan/report.blade.php'),
            resource_path('views/payment/struk.blade.php'),
        ];

        foreach ($views as $view) {
            $contents = file_get_contents($view);

            $this->assertIsString($contents);
            $this->assertStringContainsString(
                'cashier_display_name',
                $contents,
                $view
            );

            $this->assertStringNotContainsString(
                '->user?->name',
                $contents,
                $view
            );

            $this->assertStringNotContainsString(
                '->user->name',
                $contents,
                $view
            );
        }
    }
}
