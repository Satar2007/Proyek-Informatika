<?php
declare(strict_types=1);

use App\Models\Category;
use App\Models\Menu;
use App\Models\StockLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 2);
chdir($root);

foreach (['APP_ENV'=>'testing','DB_CONNECTION'=>'mysql','DB_DATABASE'=>'satar_integrated_test'] as $k=>$v) {
    putenv("$k=$v"); $_ENV[$k]=$v; $_SERVER[$k]=$v;
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$db = DB::connection();
if (!app()->environment('testing') || $db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'satar_integrated_test') {
    exit("STOP: hanya boleh memakai MySQL satar_integrated_test.\n");
}

$wait = function (string $file, int $seconds=10): bool {
    $end = microtime(true)+$seconds;
    while (microtime(true)<$end) {
        if (is_file($file)) return true;
        usleep(20000);
    }
    return is_file($file);
};

if (($argv[1] ?? '') === 'worker') {
    [$role,$dir,$menuId,$userId,$runId] = array_slice($argv,2);
    $service = app(TransactionService::class);

    if ($role === 'A') {
        $started=microtime(true);
        $db->beginTransaction();
        try {
            $t=$service->buatTransaksi([['menu_id'=>(int)$menuId,'qty'=>1]],(int)$userId,0,'qris',"CONC-A-$runId");
            file_put_contents("$dir/a.flag",'1');
            if (!$wait("$dir/b.flag")) throw new RuntimeException('Worker B timeout.');
            usleep(2500000);
            $db->commit();
            echo json_encode(['status'=>'success','id'=>$t->id,'duration'=>round(microtime(true)-$started,3)]);
            exit(0);
        } catch (Throwable $e) {
            if ($db->transactionLevel()>0) $db->rollBack();
            echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
            exit(1);
        }
    }

    if (!$wait("$dir/a.flag")) {
        echo json_encode(['status'=>'error','message'=>'Worker A timeout.']); exit(1);
    }

    file_put_contents("$dir/b.flag",'1');
    $started=microtime(true);

    try {
        $t=$service->buatTransaksi([['menu_id'=>(int)$menuId,'qty'=>1]],(int)$userId,0,'qris',"CONC-B-$runId");
        echo json_encode(['status'=>'unexpected_success','id'=>$t->id,'duration'=>round(microtime(true)-$started,3)]);
        exit(2);
    } catch (Throwable $e) {
        $duration=round(microtime(true)-$started,3);
        if (!str_contains($e->getMessage(),'tidak mencukupi')) {
            echo json_encode(['status'=>'error','message'=>$e->getMessage(),'duration'=>$duration]); exit(3);
        }
        echo json_encode(['status'=>'rejected','message'=>$e->getMessage(),'duration'=>$duration]);
        exit(0);
    }
}

foreach (['users','categories','menus','transactions','transaction_details','stock_logs'] as $table) {
    if (!Schema::hasTable($table)) exit("STOP: tabel $table tidak ada.\n");
    $r=$db->selectOne(
        'SELECT ENGINE engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
        [$db->getDatabaseName(),$table]
    );
    if (!$r || strtolower((string)$r->engine)!=='innodb') exit("STOP: tabel $table bukan InnoDB.\n");
}
if (!Schema::hasColumn('transactions','stock_reservation_status')) exit("STOP: kolom reservasi belum ada.\n");

$runId=date('YmdHis').'-'.bin2hex(random_bytes(3));
$dir=storage_path("app/concurrency-smoke/$runId");
mkdir($dir,0777,true);

$category=$menu=$userA=$userB=null;
$failed=false;

try {
    $category=Category::create(['nama_kategori'=>"CONCURRENCY-$runId"]);
    $menu=Menu::create([
        'category_id'=>$category->id,'nama_menu'=>"Concurrency $runId",
        'slug'=>'concurrency-'.Str::lower($runId),'harga'=>10000,'stok'=>1,
        'minimum_stok'=>0,'is_active'=>true
    ]);
    $userA=User::factory()->create(['name'=>"Concurrency A $runId",'email'=>"conc-a-$runId@example.test",'role'=>'kasir']);
    $userB=User::factory()->create(['name'=>"Concurrency B $runId",'email'=>"conc-b-$runId@example.test",'role'=>'kasir']);

    $outA="$dir/a.out"; $outB="$dir/b.out"; $errA="$dir/a.err"; $errB="$dir/b.err";
    $specA=[0=>['pipe','r'],1=>['file',$outA,'w'],2=>['file',$errA,'w']];
    $specB=[0=>['pipe','r'],1=>['file',$outB,'w'],2=>['file',$errB,'w']];

    $pA=proc_open([PHP_BINARY,__FILE__,'worker','A',$dir,(string)$menu->id,(string)$userA->id,$runId],$specA,$pipesA,$root);
    if (!is_resource($pA)) throw new RuntimeException('Worker A gagal start.');
    fclose($pipesA[0]);

    $pB=proc_open([PHP_BINARY,__FILE__,'worker','B',$dir,(string)$menu->id,(string)$userB->id,$runId],$specB,$pipesB,$root);
    if (!is_resource($pB)) throw new RuntimeException('Worker B gagal start.');
    fclose($pipesB[0]);

    $exitA=proc_close($pA); $exitB=proc_close($pB);
    $a=json_decode(trim((string)file_get_contents($outA)),true);
    $b=json_decode(trim((string)file_get_contents($outB)),true);

    echo 'WORKER A: '.json_encode($a).PHP_EOL;
    echo 'WORKER B: '.json_encode($b).PHP_EOL;

    DB::purge();
    $fresh=Menu::findOrFail($menu->id);
    $tx=Transaction::whereIn('nama_pelanggan',["CONC-A-$runId","CONC-B-$runId"])->get();
    $reserved=$tx->where('stock_reservation_status','reserved')->count();
    $logs=StockLog::where('menu_id',$menu->id)->where('tipe','out')->count();

    $checks=[
        [$exitA===0 && ($a['status']??'')==='success','Worker A sukses.'],
        [$exitB===0 && ($b['status']??'')==='rejected','Worker B ditolak karena stok habis.'],
        [((float)($b['duration']??0))>=1.5,'Worker B benar-benar menunggu row lock.'],
        [(int)$fresh->stok===0,'Stok akhir tepat 0, tidak negatif.'],
        [$tx->count()===1 && $reserved===1 && $logs===1,'Hanya satu transaksi/reservasi/log stok terbentuk.'],
    ];

    foreach ($checks as $i=>[$ok,$msg]) {
        if (!$ok) throw new RuntimeException('CHECK '.($i+1).' GAGAL: '.$msg);
        echo 'PASS '.($i+1).': '.$msg.PHP_EOL;
    }
    echo "SEMUA 5 SKENARIO CONCURRENCY LULUS (2 proses PHP, 2 koneksi MySQL).\n";
} catch (Throwable $e) {
    $failed=true; echo 'FAIL: '.get_class($e).' - '.$e->getMessage().PHP_EOL;
} finally {
    try {
        DB::transaction(function () use ($menu,$category,$userA,$userB,$runId) {
            if ($menu) StockLog::where('menu_id',$menu->id)->delete();
            Transaction::whereIn('nama_pelanggan',["CONC-A-$runId","CONC-B-$runId"])->delete();
            if ($menu) Menu::withTrashed()->whereKey($menu->id)->forceDelete();
            if ($category) Category::whereKey($category->id)->delete();
            if ($userA) User::whereKey($userA->id)->delete();
            if ($userB) User::whereKey($userB->id)->delete();
        });
        foreach (glob("$dir/*") ?: [] as $f) @unlink($f);
        @rmdir($dir);
        echo "CLEANUP: fixture concurrency test dihapus dari satar_integrated_test.\n";
    } catch (Throwable $e) {
        $failed=true; echo 'CLEANUP FAIL: '.$e->getMessage().PHP_EOL;
    }
}
exit($failed?1:0);
