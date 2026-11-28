<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\StockLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SqlInjectionRegressionTest extends TestCase
{
    private const PROBE = "' OR '1'='1";
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if (!app()->environment('testing') || $db->getDriverName() !== 'mysql'
            || $db->getDatabaseName() !== 'satar_integrated_test'
            || $db->selectOne('SELECT DATABASE() AS db_name')->db_name !== 'satar_integrated_test') {
            throw new \RuntimeException('STOP: dedicated MySQL testing database required.');
        }
        $db->beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transactionStarted) {
                DB::connection()->rollBack(0);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function makeMenu(): Menu
    {
        $tag = (string) Str::uuid();
        $category = Category::create(['nama_kategori' => 'SQLi test '.$tag]);
        return Menu::create([
            'category_id' => $category->id,
            'nama_menu' => 'SQLi menu '.$tag,
            'slug' => 'sqli-'.$tag,
            'deskripsi' => 'SQLi test menu',
            'harga' => 15000, 'stok' => 10, 'minimum_stok' => 5,
            'is_active' => true,
        ]);
    }

    private function makeOrder(User $cashier, string $customer): Transaction
    {
        return Transaction::create([
            'kode_transaksi' => 'SQLI-'.Str::random(12),
            'user_id' => $cashier->id, 'nama_pelanggan' => $customer,
            'total' => 20000, 'pajak' => 0, 'diskon' => 0, 'grand_total' => 20000,
            'status' => 'success', 'payment_status' => 'paid', 'payment_method' => 'cash',
        ]);
    }

    public function test_login_email_probe_does_not_authenticate(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->post('/login', ['email' => self::PROBE, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_password_probe_does_not_authenticate(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'password' => Hash::make('Local-test-password-123!')]);
        $this->post('/login', ['email' => $user->email, 'password' => self::PROBE])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_menu_search_treats_probe_as_a_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $menu = $this->makeMenu();
        $this->actingAs($admin)->get(route('admin.menu.index', ['q' => $menu->nama_menu]))
            ->assertOk()->assertViewHas('menus', fn ($rows) => $rows->getCollection()->contains('id', $menu->id));
        $this->get(route('admin.menu.index', ['q' => 'SQLi-'.Str::random(12).' '.self::PROBE]))
            ->assertOk()->assertViewHas('menus', fn ($rows) => $rows->total() === 0);
    }

    public function test_stock_log_search_does_not_expand_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $menu = $this->makeMenu();
        $note = 'SQLi stock '.Str::random(16);
        $log = StockLog::create([
            'menu_id' => $menu->id, 'tipe' => 'in', 'qty_before' => 5,
            'qty_change' => 5, 'qty_after' => 10, 'catatan' => $note, 'created_by' => $admin->id,
        ]);
        $this->actingAs($admin)->get(route('admin.stok-log', ['q' => $note]))
            ->assertOk()->assertViewHas('logs', fn ($rows) => $rows->getCollection()->contains('id', $log->id));
        $this->get(route('admin.stok-log', ['q' => 'SQLi-'.Str::random(12).' '.self::PROBE]))
            ->assertOk()->assertViewHas('logs', fn ($rows) => $rows->total() === 0);
    }

    public function test_stored_probe_is_data_and_does_not_bypass_cashier_scope(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $other = User::factory()->create(['role' => 'kasir']);
        $stored = 'SQLi '.Str::random(8).' '.self::PROBE;
        $own = $this->makeOrder($cashier, $stored);
        $unrelated = $this->makeOrder($cashier, 'Unrelated '.Str::random(8));
        $foreign = $this->makeOrder($other, $stored);
        $this->assertDatabaseHas('transactions', ['id' => $own->id, 'nama_pelanggan' => $stored]);
        $this->actingAs($cashier)->get(route('transaksi.index', ['q' => $stored]))
            ->assertOk()->assertViewHas('transaksis', fn ($rows) => $rows->total() === 1
                && $rows->getCollection()->contains('id', $own->id)
                && !$rows->getCollection()->contains('id', $foreign->id)
                && !$rows->getCollection()->contains('id', $unrelated->id));
    }

    public function test_transaction_and_report_filters_reject_sql_like_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson(route('transaksi.index', ['status' => self::PROBE, 'metode' => self::PROBE]))
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'metode']);
        $this->getJson(route('laporan.harian', ['kasir_id' => self::PROBE, 'tanggal' => self::PROBE]))
            ->assertUnprocessable()->assertJsonValidationErrors(['kasir_id', 'tanggal']);
    }

    public function test_transaction_url_probe_does_not_resolve_an_order(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $order = $this->makeOrder($cashier, 'SQLi route test');
        $this->actingAs($cashier)->get(route('transaksi.show', ['id' => $order->id]))->assertOk();
        $this->get('/transaksi/'.rawurlencode(self::PROBE))->assertNotFound();
    }

    public function test_jsa_sales_intent_does_not_execute_sql_like_message(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $other = User::factory()->create(['role' => 'kasir']);
        $this->makeOrder($cashier, 'SQLi JSA own');
        $this->makeOrder($other, 'SQLi JSA foreign');
        $baseline = $this->actingAs($cashier)->postJson(route('smart-assistant.chat'), ['message' => 'omzet hari ini'])->assertOk()->json();
        $this->postJson(route('smart-assistant.chat'), ['message' => 'omzet hari ini '.self::PROBE])
            ->assertOk()->assertExactJson($baseline);
    }
}
