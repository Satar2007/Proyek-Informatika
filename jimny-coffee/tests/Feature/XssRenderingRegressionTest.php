<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class XssRenderingRegressionTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        $db = DB::connection();
        if (!app()->environment('testing')
            || $db->getDriverName() !== 'mysql'
            || $db->getDatabaseName() !== 'satar_integrated_test'
            || config('midtrans.is_production')) {
            throw new \RuntimeException('STOP: dedicated testing database and non-production mode required.');
        }
        if ($db->selectOne('SELECT DATABASE() AS db_name', [], false)->db_name !== 'satar_integrated_test') {
            throw new \RuntimeException('STOP: unexpected connected database.');
        }

        // No migrations, seeders, or payment network calls in this suite.
        config(['midtrans.server_key' => 'TEST-KEY-NOT-REAL']);
        $this->mock(MidtransService::class);
        $db->beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transactionStarted) {
                DB::connection()->rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    private function marker(): string
    {
        // Inert markup: a regression must not create an actual HTML element.
        return '<b data-jimny-probe="xss">Jimny & test</b>';
    }

    private function assertEscaped($response, string $value): void
    {
        $response->assertOk()
            ->assertSee(e($value), false)
            ->assertDontSee($value, false);
    }

    public function test_stored_menu_name_is_escaped_in_admin_and_pos_html(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $category = Category::create(['nama_kategori' => 'XSS test '.bin2hex(random_bytes(4))]);
        $name = $this->marker();
        Menu::create([
            'category_id' => $category->id,
            'nama_menu' => $name,
            'slug' => 'xss-test-'.bin2hex(random_bytes(5)),
            'harga' => 18000,
            'stok' => 5,
            'minimum_stok' => 1,
            'is_active' => true,
        ]);

        $this->assertEscaped($this->actingAs($admin)->get(route('admin.menu.index', ['q' => $name])), $name);
        $this->assertEscaped($this->actingAs($cashier)->get(route('kasir.index')), $name);
    }

    public function test_reflected_search_input_is_escaped_in_html_attributes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $name = $this->marker();
        foreach (['admin.menu.index', 'transaksi.index'] as $route) {
            $response = $this->actingAs($admin)->get(route($route, ['q' => $name]));
            $this->assertEscaped($response, $name);
            $response->assertSee('value="'.e($name).'"', false);
        }
    }

    private function transactionFor(User $cashier, string $method, bool $paid): Transaction
    {
        return Transaction::create([
            'kode_transaksi' => 'XSS-'.strtoupper(bin2hex(random_bytes(5))),
            'user_id' => $cashier->id,
            'nama_pelanggan' => $this->marker(),
            'total' => 20000,
            'pajak' => 0,
            'diskon' => 0,
            'grand_total' => 20000,
            'status' => $paid ? 'success' : 'pending',
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'payment_method' => $method,
        ]);
    }

    private function paymentFor(Transaction $transaction, string $method, bool $paid): Payment
    {
        return Payment::create([
            'transaction_id' => $transaction->id,
            'metode' => $method,
            'external_id' => 'PAY-XSS-'.strtoupper(bin2hex(random_bytes(5))),
            'payment_status' => $paid ? 'paid' : 'waiting',
            'expired_at' => now()->addMinutes(5),
        ]);
    }

    public function test_stored_customer_name_is_escaped_in_transaction_history_detail_and_receipt(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->transactionFor($cashier, 'cash', true);
        $payment = $this->paymentFor($transaction, 'cash', true);
        $this->actingAs($cashier);

        $this->assertEscaped($this->get(route('transaksi.index', ['q' => $transaction->kode_transaksi])), $this->marker());
        $this->assertEscaped($this->get(route('transaksi.show', $transaction->id)), $this->marker());
        $this->assertEscaped($this->get(route('payment.struk', $payment->id)), $this->marker());
    }

    public function test_stored_customer_name_is_escaped_on_midtrans_payment_page(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->transactionFor($cashier, 'qris', false);
        $payment = $this->paymentFor($transaction, 'qris', false);
        $response = $this->actingAs($cashier)->get(route('payment.show', $payment->id));
        $response->assertViewIs('payment.midtrans');
        $this->assertEscaped($response, $this->marker());
    }
}
