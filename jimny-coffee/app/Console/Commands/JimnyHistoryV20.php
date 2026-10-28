<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable as Day;
use RuntimeException;

class JimnyHistoryV20 extends Command
{
    protected $signature='jimny:history-v20 {--apply} {--verify} {--undo} {--as-of=2026-10-28}';
    protected $description='Backfill dataset tugas dua tahun; preview default, apply eksplisit; tanpa perubahan stok.';
    private string $dir;
    private function hashRows(string $table,array $ids): string {
        $rows=[];foreach(array_chunk($ids,500) as $chunk)foreach(DB::table($table)->whereIn('id',$chunk)->orderBy('id')->get() as $r)$rows[]=(array)$r;
        usort($rows,fn($a,$b)=>$a['id']<=>$b['id']);
        return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
    }
    private function save(string $path,array $data):void {
        $tmp=$path.'.tmp';
        if(file_put_contents($tmp,json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false || !rename($tmp,$path))throw new RuntimeException('Tidak bisa menulis manifest.');
    }
    private function preflight():void {
        foreach(['transactions'=>['id','kode_transaksi','user_id','nama_pelanggan','total','pajak','diskon','grand_total','status','payment_status','payment_method','paid_at','created_at','updated_at'],
            'transaction_details'=>['id','transaction_id','menu_id','qty','harga','subtotal','created_at','updated_at'],
            'payments'=>['id','transaction_id','metode','external_id','payment_status','paid_at','created_at','updated_at'],
            'menus'=>['id','nama_menu','harga','stok','minimum_stok','is_active','deleted_at']] as $t=>$cols)
            foreach($cols as $c)if(!Schema::hasColumn($t,$c))throw new RuntimeException("Skema tidak cocok: $t.$c");
        $driver=DB::connection()->getDriverName();
        if(!in_array($driver,['mysql','sqlite']))throw new RuntimeException('Database harus MySQL atau SQLite.');
        if($driver==='mysql') {
            foreach(DB::select('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (\'transactions\',\'transaction_details\',\'payments\')') as $t)
                if(strtolower($t->ENGINE)!=='innodb')throw new RuntimeException('Import membutuhkan tabel InnoDB.');
            $tr=DB::select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE IN (\'transactions\',\'transaction_details\',\'payments\')');
            if($tr)throw new RuntimeException('Ada trigger database. Perlu review sebelum import.');
        }
    }
    public function handle():int {
        $this->dir=storage_path('app/jimny-v20-history');
        if(!is_dir($this->dir)&&!mkdir($this->dir,0700,true))throw new RuntimeException('Direktori backup tidak tersedia.');
        $lock=fopen($this->dir.'/lock','c');
        if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){ $this->error('Proses V20 lain masih berjalan.');return 1; }
        $mysqlLock=false;
        try {
            $this->preflight();
            if(DB::connection()->getDriverName()==='mysql') {
                $row=DB::selectOne("SELECT GET_LOCK(CONCAT(DATABASE(), ':jimny-v20'), 0) AS acquired");
                if((int)$row->acquired!==1)throw new RuntimeException('Database sedang diproses V20 lain.');
                $mysqlLock=true;
            }
            $path=$this->dir.'/manifest.json';
            if(is_file($path)) {
                $m=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
                $count=DB::table('transactions')->where('kode_transaksi','like','J20-%')->count();
                if($m['count']>0 && $count===0 && ($m['state']??'')==='prepared') {
                    rename($path,$this->dir.'/aborted-'.date('YmdHis').'.json');
                } else {
                    $this->verifyManifest($m);
                    if($this->option('undo')) {
                        DB::transaction(function()use($m){
                            foreach(['payments','transaction_details','transactions'] as $t)
                                foreach(array_chunk($m['ids'][$t],500) as $ids)DB::table($t)->whereIn('id',$ids)->delete();
                        });
                        $m['state']='undone';$this->save($this->dir.'/undone-'.date('YmdHis').'.json',$m);unlink($path);
                        $this->info('UNDO data selesai: hanya batch V20 dihapus. Stok tidak diubah.');return 0;
                    }
                    $this->info('PASS: batch V20 utuh, '.$m['count'].' transaksi. Tidak mengimpor ulang.');return 0;
                }
            }
            if($this->option('undo')){ $this->info('Tidak ada batch aktif untuk Undo.');return 0; }
            if($this->option('verify'))throw new RuntimeException('Manifest import belum tersedia.');
            if(DB::table('transactions')->where('kode_transaksi','like','J20-%')->exists())throw new RuntimeException('Kode J20 ada tanpa manifest; berhenti untuk review.');
            $raw=(string)$this->option('as-of');
            $asof=Day::createFromFormat('!Y-m-d',$raw);
            if(!$asof || $asof->format('Y-m-d')!==$raw || $asof->isFuture())throw new RuntimeException('Tanggal as-of tidak valid/di masa depan.');
            $start=$asof->subYearsNoOverflow(2);$end=$asof->subDay();
            $first=DB::table('transactions')->min('created_at');
            if($first)$end=$end->min(Day::parse($first)->startOfDay()->subDay());
            $menus=DB::table('menus')->whereNull('deleted_at')->where('is_active',1)->where('harga','>',0)->orderBy('id')->get();
            $user=DB::table('users')->whereIn('role',['kasir','admin','owner'])->orderBy('id')->first();
            if(!$menus->count()||!$user)throw new RuntimeException('Butuh menu aktif berharga positif dan satu akun kasir/admin/owner.');
            $days=max(0,(int)$start->diffInDays($end,false)+1);
            $base=DB::table('transactions')->where('status','success')->where('payment_status','paid')->whereBetween('created_at',[$asof->subDays(90),$asof])->count()/90;
            $rate=max(4,min(80,$base?:12));
            $this->info("Rencana: $days hari, ".$start->toDateString().' s.d. '.$end->toDateString().'; transaksi lama dipertahankan.');
            if(!$this->option('apply')) { $this->info('Preview saja. --apply untuk memasukkan data.');return 0; }
            if($days>732)throw new RuntimeException('Rentang melebihi batas.');
            // Immutable row backup of affected tables, plus stock baseline; no credentials exported.
            $backup=$this->dir.'/before-'.date('YmdHis').'-'.bin2hex(random_bytes(3)).'.jsonl.gz';
            $gz=gzopen($backup,'wb9');if(!$gz)throw new RuntimeException('Backup tidak dapat dibuat.');
            try { foreach(['transactions','transaction_details','payments','menus'] as $t) {
                DB::table($t)->orderBy('id')->chunkById(500,function($rows)use($gz,$t){foreach($rows as $r)
                    if(gzwrite($gz,json_encode(['table'=>$t,'row'=>$r],JSON_THROW_ON_ERROR)."\n")===false)throw new RuntimeException('Backup gagal ditulis.');});
            }}finally{gzclose($gz);}
            $m=['version'=>20,'state'=>'prepared','as_of'=>$raw,'from'=>$start->toDateString(),'through'=>$end->toDateString(),
                'backup'=>$backup,'backup_sha256'=>hash_file('sha256',$backup),'count'=>0,'ids'=>['transactions'=>[],'transaction_details'=>[],'payments'=>[]],
                'assumptions'=>['synthetic_coursework_history','current_menu_prices','3_percent_tax','all_imported_paid_cash','no_historical_stock_movements']];
            $optionalTx=array_flip(Schema::getColumnListing('transactions'));
            $optionalPay=array_flip(Schema::getColumnListing('payments'));
            DB::transaction(function()use(&$m,$menus,$user,$days,$start,$rate,$path,$optionalTx,$optionalPay){
                $stockBefore=DB::table('menus')->orderBy('id')->pluck('stok','id')->all();
                for($i=0;$i<$days;$i++) {
                    $date=$start->addDays($i);$seed=(int)sprintf('%u',crc32('J20'.$date->toDateString()));
                    $rand=function(int $lo,int $hi)use(&$seed){$seed=(int)(($seed*48271)%2147483647);return $lo+($seed%($hi-$lo+1));};
                    $factor=($date->isWeekend()?1.25:1)*(.9+.12*sin(2*M_PI*$date->dayOfYear/365))*(.85+.15*$i/max(1,$days));
                    $n=max(2,(int)round($rate*$factor*$rand(75,125)/100));
                    for($j=1;$j<=$n;$j++) {
                        $at=$date->setTime($rand(9,21),$rand(0,59),$rand(0,59))->format('Y-m-d H:i:s');
                        $items=[];for($k=0,$len=$rand(1,3);$k<$len;$k++) {
                            $menu=$menus[$rand(0,$menus->count()-1)];$id=(int)$menu->id;
                            if(!isset($items[$id]))$items[$id]=['menu_id'=>$id,'qty'=>0,'harga'=>(int)$menu->harga];
                            $items[$id]['qty']+=$rand(1,2);
                        }
                        $total=0;foreach($items as &$item){$item['subtotal']=$item['harga']*$item['qty'];$total+=$item['subtotal'];}unset($item);
                        $tax=(int)round($total*.03);$code='J20-'.$date->format('Ymd').'-'.str_pad((string)$j,4,'0',STR_PAD_LEFT);
                        $row=['kode_transaksi'=>$code,'user_id'=>$user->id,'nama_pelanggan'=>'Pelanggan Umum','total'=>$total,'pajak'=>$tax,'diskon'=>0,
                            'grand_total'=>$total+$tax,'status'=>'success','payment_status'=>'paid','payment_method'=>'cash','paid_at'=>$at,'created_at'=>$at,'updated_at'=>$at];
                        foreach(['cashier_name_snapshot'=>$user->name,'cashier_role_snapshot'=>$user->role,'stock_reservation_status'=>'none'] as $c=>$v)
                            if(isset($optionalTx[$c]))$row[$c]=$v;
                        $id=DB::table('transactions')->insertGetId($row);$m['ids']['transactions'][]=$id;
                        foreach($items as $item)$m['ids']['transaction_details'][]=DB::table('transaction_details')->insertGetId($item+['transaction_id'=>$id,'created_at'=>$at,'updated_at'=>$at]);
                        $pay=['transaction_id'=>$id,'metode'=>'cash','external_id'=>$code,'payment_status'=>'paid','paid_at'=>$at,'created_at'=>$at,'updated_at'=>$at];
                        foreach(['uang_diterima'=>$total+$tax,'kembalian'=>0] as $c=>$v)if(isset($optionalPay[$c]))$pay[$c]=$v;
                        $m['ids']['payments'][]=DB::table('payments')->insertGetId($pay);$m['count']++;
                    }
                }
                if($stockBefore!==DB::table('menus')->orderBy('id')->pluck('stok','id')->all())throw new RuntimeException('Stok berubah saat import. Dibatalkan.');
                foreach($m['ids'] as $t=>$ids)$m['hashes'][$t]=$this->hashRows($t,$ids);
                $this->save($path,$m);$this->verifyManifest($m);
            });
            $m['state']='committed';$this->save($path,$m);
            $this->info('PASS: '.$m['count'].' transaksi ditambahkan; detail dan pembayaran cocok; stok tetap. Backup: '.$backup);
            return 0;
        }catch(\Throwable $e){$this->error($e->getMessage());return 1;}
        finally{if($mysqlLock)DB::select("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':jimny-v20'))");flock($lock,LOCK_UN);fclose($lock);}
    }
    private function verifyManifest(array $m):void {
        if(DB::table('transactions')->where('kode_transaksi','like','J20-%')->count()!==$m['count'])throw new RuntimeException('Jumlah batch berubah; tidak menghapus atau mengimpor otomatis.');
        foreach($m['ids'] as $t=>$ids)if($this->hashRows($t,$ids)!==$m['hashes'][$t])throw new RuntimeException("Batch $t telah berubah. Perlu review sebelum Undo.");
        $detailIds=array_fill_keys($m['ids']['transaction_details'],true);
        $paymentIds=array_fill_keys($m['ids']['payments'],true);
        foreach(array_chunk($m['ids']['transactions'],400) as $ids) {
            $allDetails=DB::table('transaction_details')->whereIn('transaction_id',$ids)->get();
            $allPayments=DB::table('payments')->whereIn('transaction_id',$ids)->get();
            foreach($allDetails as $d)if(!isset($detailIds[$d->id]))throw new RuntimeException('Detail tambahan ditemukan; Undo ditolak.');
            foreach($allPayments as $p)if(!isset($paymentIds[$p->id]))throw new RuntimeException('Pembayaran tambahan ditemukan; Undo ditolak.');
            $details=$allDetails->groupBy('transaction_id');
            $payments=DB::table('payments')->whereIn('transaction_id',$ids)->get()->groupBy('transaction_id');
            foreach(DB::table('transactions')->whereIn('id',$ids)->get() as $t) {
                $ds=$details->get($t->id,collect());$ps=$payments->get($t->id,collect());
                if($ds->sum('subtotal')!=(int)$t->total || $t->grand_total!=$t->total+$t->pajak-$t->diskon || $ps->count()!==1 || $ps[0]->payment_status!=='paid')throw new RuntimeException('Rekonsiliasi struk gagal.');
                foreach($ds as $d)if($d->subtotal!=$d->qty*$d->harga)throw new RuntimeException('Subtotal detail tidak cocok.');
            }
        }
    }
}
