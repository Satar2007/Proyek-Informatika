<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use App\Services\StockService;
use App\Services\TransactionService;
use App\Support\SqlErrorMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class SqlSecurityAdditionalTest extends TestCase
{
    private const MARKER = 'jimny_sqli_nonexistent_column_6f2b';
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
        config(['app.debug' => false]);
        $db->beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transactionStarted) {
                DB::connection()->disableQueryLog();
                DB::connection()->flushQueryLog();
                DB::connection()->rollBack(0);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function makeMenu(): Menu
    {
        $tag = (string) Str::uuid();
        $category = Category::create(['nama_kategori' => 'SQL security '.$tag]);

        return Menu::create([
            'category_id' => $category->id, 'nama_menu' => 'SQL security '.$tag,
            'slug' => 'sql-security-'.$tag, 'deskripsi' => 'Test only',
            'harga' => 15000, 'stok' => 10, 'minimum_stok' => 5, 'is_active' => true,
        ]);
    }

    public function test_menu_search_keeps_sql_structure_and_binds_unusual_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeMenu();
        $this->actingAs($admin);
        $db = DB::connection();
        $tag = 'SQLProbe-'.Str::random(12);
        $probes = [
            $tag." O'Connor",
            $tag." ' OR '1'='1",
            $tag.' UNION SELECT NULL',
            $tag.' SLEEP(0)',
            $tag.' ; SELECT 1',
            $tag.' /* comment */',
        ];
        $expectedShape = null;
        foreach ($probes as $probe) {
            $db->flushQueryLog();
            $db->enableQueryLog();
            try {
                $this->get(route('admin.menu.index', ['q' => $probe]))
                    ->assertOk()->assertViewHas('menus', fn ($rows) => $rows->total() === 0);
                $queries = $db->getQueryLog();
            } finally {
                $db->disableQueryLog();
            }
            $matched = [];
            foreach ($queries as $entry) {
                if (in_array('%'.$probe.'%', $entry['bindings'], true)) {
                    $this->assertStringContainsString('?', $entry['query']);
                    $this->assertStringNotContainsString($probe, $entry['query']);
                    $matched[] = $entry['query'];
                }
            }
            $this->assertNotEmpty($matched, 'Search values must be bound as data.');
            sort($matched);
            $expectedShape ??= $matched;
            $this->assertSame($expectedShape, $matched);
        }
    }

    public function test_stock_error_does_not_flash_raw_sql(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $menu = $this->makeMenu();
        $this->mock(StockService::class, function ($mock) {
            $mock->shouldReceive('tambahStok')->once()->andReturnUsing(function () {
                DB::select('SELECT '.self::MARKER.' FROM menus LIMIT 0');
                return true;
            });
        });
        $this->actingAs($admin)->post(route('admin.menu.tambah-stok', ['menu' => $menu->id]), ['qty' => 1])
            ->assertRedirect(route('admin.menu.index'))
            ->assertSessionHas('error', SqlErrorMessage::MESSAGE);
        $this->assertSame(10, (int) $menu->fresh()->stok);
    }

    public function test_checkout_database_error_does_not_return_raw_sql(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $menu = $this->makeMenu();
        $this->mock(TransactionService::class, function ($mock) {
            $mock->shouldReceive('buatTransaksi')->once()->andReturnUsing(function () {
                DB::select('SELECT '.self::MARKER.' FROM menus LIMIT 0');
                throw new \LogicException('Unreachable');
            });
        });
        $response = $this->actingAs($cashier)->postJson(route('kasir.checkout'), [
            'nama_pelanggan' => 'Test customer', 'cart' => [['menu_id' => $menu->id, 'qty' => 1]],
            'metode' => 'cash', 'uang_diterima' => 20000,
        ]);
        $response->assertStatus(422)->assertJson(['success' => false, 'message' => SqlErrorMessage::MESSAGE]);
        $response->assertDontSee(self::MARKER, false)->assertDontSee('SQLSTATE', false);
        $this->assertSame(10, (int) $menu->fresh()->stok);
    }

    public function test_framework_hides_unhandled_database_error_with_debug_disabled(): void
    {
        // This route exists only in this test application, never in production routes.
        Route::get('/__jimny-test/sql-error', function () {
            DB::select('SELECT '.self::MARKER.' FROM menus LIMIT 0');
        });
        $this->getJson('/__jimny-test/sql-error')->assertStatus(500)
            ->assertDontSee(self::MARKER, false)->assertDontSee('SQLSTATE', false);
        $this->get('/__jimny-test/sql-error')->assertStatus(500)
            ->assertDontSee(self::MARKER, false)->assertDontSee('SQLSTATE', false);
    }

    public function test_business_rule_message_is_preserved(): void
    {
        $message = 'Stok tidak mencukupi.';
        $this->assertSame($message, SqlErrorMessage::forUser(new \Exception($message)));
        $wrapped = new \RuntimeException('wrapper', 0, new \PDOException('SQLSTATE test marker'));
        $this->assertSame(SqlErrorMessage::MESSAGE, SqlErrorMessage::forUser($wrapped));
    }
}
