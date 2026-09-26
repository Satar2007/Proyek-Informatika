<?php
// Run with: php tests/Manual/stock_reservation_smoke.php
// This is a rollback test, never a live Midtrans payment.
require getcwd() . '/tests/Manual/testing_env.php';
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Menu;
use App\Models\Payment;
use App\Models\StockLog;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\PaymentService;
use App\Services\StockReservationService;
use App\Services\TransactionService;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$db = DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'satar_integrated_test'
    || (bool) config('midtrans.is_production')) {
    echo "STOP: tes hanya boleh memakai satar_integrated_test + Midtrans Sandbox.\n";
    exit(1);
}
if (!Schema::hasColumn('transactions', 'stock_reservation_status')) {
    echo "STOP: kolom reservasi belum tersedia.\n";
    exit(1);
}
foreach (['menus', 'transactions', 'transaction_details', 'payments', 'stock_logs'] as $table) {
    $row = $db->selectOne(
        'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [$db->getDatabaseName(), $db->getTablePrefix() . $table]
    );
    if (!$row || strtolower($row->engine) !== 'innodb') {
        echo "STOP: tabel {$table} tidak mendukung rollback InnoDB.\n";
        exit(1);
    }
}

class ReservationFakeMidtrans extends MidtransService
{
    public ?object $response = null;
    public int $checks = 0;
    public int $cancels = 0;
    public bool $sessionCancelled = false;
    public bool $throwOnStatus = false;
    public function __construct() {}
    public function ambilStatus(Payment $payment): ?object
    {
        $this->checks++;
        if ($this->throwOnStatus) {
            throw new RuntimeException('SIMULASI status tidak tersedia.');
        }
        return $this->response;
    }
    public function buatSnapToken(Payment $payment): string
    {
        return 'TEST-TOKEN-NOT-A-REAL-MIDTRANS-TOKEN';
    }
    public function batalkanSesiSnap(Payment $payment): bool
    {
        $this->cancels++;
        return $this->sessionCancelled;
    }
    public function batalkanTransaksiMidtrans(Payment $payment): string
    {
        $this->cancels++;
        $this->response->transaction_status = 'cancel';
        return '200';
    }
}
function verify(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}
function remoteFor(Payment $p, string $status): object
{
    return (object) [
        'order_id' => $p->external_id,
        'gross_amount' => (string) $p->transaction->grand_total,
        'transaction_status' => $status,
        'fraud_status' => 'accept',
    ];
}

$failed = false;
$passed = 0;
$db->beginTransaction();
try {
    $menu = Menu::where('is_active', true)->orderBy('id')->lockForUpdate()->first();
    $user = User::where('role', 'kasir')->orderBy('id')->first();
    if (!$menu || !$user) throw new RuntimeException('Perlu satu menu aktif dan akun kasir.');
    $menu->harga = 10000;
    $menu->save();
    auth()->setUser($user);
    config(['session.driver' => 'array']);
    $request = Request::create('http://127.0.0.1:8002/transaksi', 'GET');
    $request->headers->set('referer', 'http://127.0.0.1:8002/transaksi');
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => $user);
    $app->instance('request', $request);
    app('url')->setRequest($request);

    $fake = new ReservationFakeMidtrans();
    $app->instance(MidtransService::class, $fake);
    $service = app(TransactionService::class);
    $stock = fn () => (int) Menu::withTrashed()->findOrFail($menu->id)->stok;
    $setStock = function (int $qty) use ($menu): void {
        Menu::whereKey($menu->id)->update(['stok' => $qty]);
    };
    $new = function (int $qty = 1, bool $token = true) use ($service, $menu, $user): Payment {
        $t = $service->buatTransaksi([
            ['menu_id' => $menu->id, 'qty' => $qty],
        ], (int) $user->id, 0, 'qris', 'UJI RESERVASI ROLLBACK');
        $p = app(PaymentService::class)->buatPayment($t, 'qris');
        if ($token) $p->update(['midtrans_snap_token' => 'TEST-TOKEN-NOT-REAL']);
        return $p->load('transaction');
    };
    $run = function (string $name, callable $test) use ($db, $fake, &$passed): void {
        $db->beginTransaction();
        try {
            $fake->response = null;
            $fake->checks = 0;
            $fake->cancels = 0;
            $fake->sessionCancelled = false;
            $fake->throwOnStatus = false;
            $test();
            $passed++;
            echo "PASS {$passed}: {$name}\n";
        } finally {
            $db->rollBack();
        }
    };

    $run('duplikat digabung dan stok dicadangkan sekali', function () use ($service,$menu,$user,$setStock,$stock) {
        $setStock(5);
        $logs = StockLog::count();
        $t = $service->buatTransaksi([
            ['menu_id'=>$menu->id,'qty'=>2], ['menu_id'=>$menu->id,'qty'=>3],
        ], (int)$user->id);
        verify($t->details->count() === 1 && (int)$t->details->first()->qty === 5, 'Duplikat tidak digabung.');
        verify($stock() === 0 && $t->stock_reservation_status === 'reserved', 'Reservasi tidak sesuai.');
        verify(StockLog::count() === $logs+1, 'Log reservasi harus satu.');
    });
    $run('qty gabungan berlebih ditolak tanpa sisa data', function () use ($service,$menu,$user,$setStock,$stock) {
        $setStock(5);
        $before = [Transaction::count(),TransactionDetail::count(),StockLog::count()];
        $rejected = false;
        try {
            $service->buatTransaksi([
                ['menu_id'=>$menu->id,'qty'=>3], ['menu_id'=>$menu->id,'qty'=>3],
            ], (int)$user->id);
        } catch (Exception $e) {
            if (!str_contains($e->getMessage(),'tidak mencukupi')) throw $e;
            $rejected = true;
        }
        verify($rejected && $stock()===5, 'Stok berlebih tidak ditolak.');
        verify($before === [Transaction::count(),TransactionDetail::count(),StockLog::count()], 'Ada sisa data.');
    });
    $run('stok terakhir tidak bisa dipakai pesanan QRIS/cash berikutnya', function () use ($new,$setStock,$service,$menu,$user,$stock) {
        $setStock(1); $new();
        foreach (['qris','cash'] as $method) {
            $rejected = false;
            try { $service->buatTransaksi([['menu_id'=>$menu->id,'qty'=>1]],(int)$user->id,0,$method); }
            catch (Exception $e) {
                if (!str_contains($e->getMessage(),'tidak mencukupi')) throw $e;
                $rejected = true;
            }
            verify($rejected, 'Pesanan kedua lolos: '.$method);
        }
        verify($stock()===0, 'Stok berubah setelah penolakan.');
    });
    $run('settlement dan polling ulang tidak mengurangi stok lagi', function () use ($new,$fake,$setStock,$stock) {
        $setStock(1); $p=$new(); $logs=StockLog::count();
        $fake->response=remoteFor($p,'settlement');
        $controller=app(PaymentController::class);
        verify($controller->check($p->id)->getData(true)['status']==='paid','Belum paid.');
        verify($controller->check($p->id)->getData(true)['status']==='paid','Polling ulang salah.');
        $t=$p->transaction->fresh();
        verify($t->stock_reservation_status==='consumed' && $t->payment_status==='paid' && $t->status==='success','Status lunas tidak konsisten.');
        verify($stock()===0 && StockLog::count()===$logs && $fake->checks===1,'Stok/polling terulang.');
    });
    foreach (['expire'=>'expired','cancel'=>'cancelled','deny'=>'failed','failure'=>'failed'] as $remote=>$local) {
        $run('status '.$remote.' melepas stok tepat sekali', function () use ($new,$fake,$setStock,$stock,$remote,$local) {
            $setStock(3); $p=$new(2); $logs=StockLog::count();
            $fake->response=remoteFor($p,$remote);
            $c=app(PaymentController::class);
            verify($c->check($p->id)->getData(true)['status']===$local,'Status gagal tidak sesuai.');
            $c->check($p->id);
            verify($stock()===3 && StockLog::count()===$logs+1,'Pengembalian tidak tepat sekali.');
            verify($p->transaction->fresh()->stock_reservation_status==='released','Reservasi belum dilepas.');
        });
    }
    foreach (['pending','missing','wrong_order','wrong_amount','unavailable'] as $case) {
        $run('status '.$case.' tetap menahan reservasi', function () use ($new,$fake,$setStock,$stock,$case) {
            $setStock(2); $p=$new(); $logs=StockLog::count();
            $fake->response=$case==='missing' ? null : remoteFor($p,'pending');
            if ($case==='wrong_order') $fake->response->order_id='WRONG-TEST-ORDER';
            if ($case==='wrong_amount') $fake->response->gross_amount='1';
            if ($case==='unavailable') $fake->throwOnStatus=true;
            $r=app(PaymentController::class)->check($p->id);
            verify(in_array($r->getData(true)['status'],['waiting','unknown'],true),'Status tidak aman.');
            verify($stock()===1 && StockLog::count()===$logs,'Stok dilepas tanpa konfirmasi.');
            verify($p->fresh()->payment_status==='waiting' && $p->transaction->fresh()->stock_reservation_status==='reserved','Reservasi berubah.');
        });
    }
    foreach (['no_token','snap_confirmed','snap_unconfirmed','remote_pending','remote_expired','already_settled'] as $case) {
        $run('pembatalan '.$case, function () use ($new,$fake,$setStock,$stock,$case) {
            $setStock(2); $p=$new(1,$case!=='no_token'); $logs=StockLog::count();
            if ($case==='snap_confirmed') $fake->sessionCancelled=true;
            if ($case==='remote_pending') $fake->response=remoteFor($p,'pending');
            if ($case==='remote_expired') $fake->response=remoteFor($p,'expire');
            if ($case==='already_settled') $fake->response=remoteFor($p,'settlement');
            $c=app(TransactionController::class);
            $c->cancel($p->transaction_id);
            $release=!in_array($case,['snap_unconfirmed','already_settled'],true);
            verify($stock()===($release?2:1),'Stok pembatalan salah.');
            verify($p->transaction->fresh()->stock_reservation_status===($release?'released':'reserved'),'Penanda pembatalan salah.');
            verify(StockLog::count()===$logs+($release?1:0),'Log pembatalan salah.');
            if ($release) {
                $c->cancel($p->transaction_id);
                verify($stock()===2 && StockLog::count()===$logs+1,'Pembatalan ulang menggandakan stok.');
            }
        });
    }
    $run('menu soft-delete tetap bisa mengembalikan reservasi', function () use ($new,$fake,$setStock,$stock,$menu) {
        $setStock(2); $p=$new(); Menu::findOrFail($menu->id)->delete();
        $fake->response=remoteFor($p,'expire');
        $r=app(PaymentController::class)->check($p->id);
        verify($r->getData(true)['status']==='expired' && $stock()===2,'Pengembalian menu terhapus gagal.');
    });
    $run('cash tetap lunas dan hanya mengurangi stok sekali', function () use ($setStock,$stock,$menu,$user) {
        $setStock(3); $logs=StockLog::count();
        $r=Request::create('/kasir/checkout','POST',[
            'nama_pelanggan'=>'UJI CASH ROLLBACK','cart'=>[['menu_id'=>$menu->id,'qty'=>2]],
            'diskon'=>0,'metode'=>'cash','uang_diterima'=>100000,
        ]);
        $r->setUserResolver(fn()=>$user);
        $response=app(KasirController::class)->checkout($r)->getData(true);
        verify(($response['success']??false)===true,'Checkout cash gagal.');
        $t=Transaction::findOrFail($response['transaksi_id']);
        verify($t->payment_status==='paid' && $t->stock_reservation_status==='none','Cash salah status.');
        verify($stock()===1 && StockLog::count()===$logs+1,'Cash mengurangi stok berulang.');
    });
    $run('QRIS total nol ditolak sebelum reservasi', function () use ($service,$menu,$user,$setStock,$stock) {
        $setStock(1); $before=StockLog::count(); $rejected=false;
        try { $service->buatTransaksi([['menu_id'=>$menu->id,'qty'=>1]],(int)$user->id,10300,'qris'); }
        catch (Exception $e) {
            if (!str_contains($e->getMessage(),'lebih besar dari nol')) throw $e;
            $rejected=true;
        }
        verify($rejected && $stock()===1 && StockLog::count()===$before,'QRIS nol menyisakan reservasi.');
    });
    $run('kegagalan checkout membatalkan reservasi dan seluruh data', function () use ($new,$setStock,$stock,$db) {
        $setStock(2); $before=[Transaction::count(),Payment::count(),StockLog::count()];
        try {
            $db->transaction(function () use ($new) { $new(); throw new RuntimeException('SIMULASI ROLLBACK'); });
        } catch (RuntimeException $e) { if ($e->getMessage()!=='SIMULASI ROLLBACK') throw $e; }
        verify($stock()===2 && $before===[Transaction::count(),Payment::count(),StockLog::count()],'Rollback tidak utuh.');
    });
    $run('token dibuat hanya untuk transaksi dengan reservasi aktif', function () use ($new,$setStock) {
        $setStock(2); $p=$new(1,false);
        $r=app(PaymentController::class)->token($p->id);
        verify($r->getData(true)['success']===true,'Token tiruan tidak dibuat.');
        verify(filled($p->fresh()->midtrans_snap_token),'Token tidak tersimpan.');
    });
    echo "SEMUA {$passed} SKENARIO LULUS (Midtrans tiruan, bukan uji konkurensi).\n";
} catch (Throwable $e) {
    $failed=true;
    echo 'FAIL: '.get_class($e).' - '.$e->getMessage()."\n";
} finally {
    $db->rollBack();
    echo "ROLLBACK: perubahan data uji dibatalkan; nomor auto-increment dapat meloncat.\n";
}
exit($failed?1:0);
