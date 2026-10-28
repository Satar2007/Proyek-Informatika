<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable as Day;
use App\Support\JimnyOwnerForecast as Forecast;

class JimnyOwnerV20Service
{
    public function snapshot(int $days=30,int $horizon=7):array {
        $today=Day::today();$from=$today->subDays($days);$prior=$from->subDays($days);
        $paid=fn()=>DB::table('transactions')->where('status','success')->where('payment_status','paid');
        $summarize=function($a,$b)use($paid){
            $r=$paid()->where('created_at','>=',$a)->where('created_at','<',$b)
                ->selectRaw('COUNT(*) AS receipts, COALESCE(SUM(total-diskon),0) AS net, COALESCE(SUM(pajak),0) AS tax, COALESCE(SUM(grand_total),0) AS collected')->first();
            return ['transactions'=>(int)$r->receipts,'sales_rp'=>(int)$r->net,'tax_rp'=>(int)$r->tax,'collected_rp'=>(int)$r->collected,
                'average_ticket_rp'=>$r->receipts?round($r->net/$r->receipts):0,'from'=>$a->toDateString(),'through'=>$b->subDay()->toDateString()];
        };
        $current=$summarize($from,$today);$previous=$summarize($prior,$from);
        $delta=fn($a,$b)=>$b>0?round(100*($a-$b)/$b,1):null;
        $changes=['sales_pct'=>$delta($current['sales_rp'],$previous['sales_rp']),
            'transactions_pct'=>$delta($current['transactions'],$previous['transactions']),
            'ticket_pct'=>$delta($current['average_ticket_rp'],$previous['average_ticket_rp'])];
        $rows=$paid()->where('created_at','>=',$today->subDays(730))->where('created_at','<',$today)
            ->selectRaw('DATE(created_at) AS day, SUM(total-diskon) AS sales, COUNT(*) AS receipts')->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        $first=$rows->first()?->day;
        $calendar=[];$sales=[];$qty=[];$map=$rows->keyBy('day');
        $detail=DB::table('transactions as t')->join('transaction_details as d','d.transaction_id','=','t.id')
            ->where('t.status','success')->where('t.payment_status','paid')->where('t.created_at','>=',$today->subDays(730))->where('t.created_at','<',$today)
            ->selectRaw('DATE(t.created_at) AS day, d.menu_id, SUM(d.qty) AS qty, SUM(d.subtotal) AS sales')
            ->groupByRaw('DATE(t.created_at), d.menu_id')->get();
        $daily=[];$menuDaily=[];$menuNow=[];$menuPrior=[];
        foreach($detail as $r){
            $daily[$r->day]=($daily[$r->day]??0)+(int)$r->qty;$menuDaily[$r->menu_id][$r->day]=(int)$r->qty;
            if($r->day >= $from->toDateString()){
                $menuNow[$r->menu_id]['qty']=($menuNow[$r->menu_id]['qty']??0)+(int)$r->qty;
                $menuNow[$r->menu_id]['sales']=($menuNow[$r->menu_id]['sales']??0)+(int)$r->sales;
            }elseif($r->day >= $prior->toDateString())$menuPrior[$r->menu_id]=($menuPrior[$r->menu_id]??0)+(int)$r->qty;
        }
        if($first)for($d=Day::parse($first);$d->lt($today);$d=$d->addDay()){
            $key=$d->toDateString();$sales[]=(float)($map->get($key)?->sales??0);$qty[]=(float)($daily[$key]??0);$calendar[]=$key;
        }
        $f=Forecast::forecast($qty,$horizon);$rf=Forecast::forecast($sales,$horizon);
        if($f['evaluation']){
            $i=$f['evaluation']['origin_index'];$f['evaluation']['from']=$calendar[$i];$f['evaluation']['through']=$calendar[$i+$horizon-1];
        }
        $f['from']=$today->toDateString();$f['through']=$today->addDays($horizon-1)->toDateString();
        $f['revenue']=$rf;
        $menus=DB::table('menus')->orderBy('nama_menu')->get();$performance=[];$stock=[];
        foreach($menus as $m){
            $a=$menuNow[$m->id]??['qty'=>0,'sales'=>0];$b=$menuPrior[$m->id]??0;
            $performance[]=['name'=>$m->nama_menu,'qty'=>$a['qty'],'item_sales_rp'=>$a['sales'],'previous_qty'=>$b,'change_pct'=>$delta($a['qty'],$b)];
            if(!$m->is_active || $m->deleted_at)continue;
            $series=[];foreach($calendar as $day)$series[]=(float)($menuDaily[$m->id][$day]??0);
            $mf=Forecast::forecast($series,7);$avg=$mf['available']?$mf['point']/7:null;
            $available=max(0,(int)$m->stok);$minimum=max(0,(int)$m->minimum_stok);
            $target=$avg===null?$minimum:(int)ceil($avg*7)+$minimum;
            $stock[]=['name'=>$m->nama_menu,'stock'=>$available,'minimum'=>$minimum,'demand_7'=>$mf['point'],
                'coverage_days'=>$avg>0?round($available/$avg,1):null,'suggested_portions'=>max(0,$target-$available),
                'basis'=>$avg===null?'minimum_only':'7_days_plus_minimum'];
        }
        usort($performance,fn($a,$b)=>$b['qty']<=>$a['qty']);
        usort($stock,fn($a,$b)=>$b['suggested_portions']<=>$a['suggested_portions']);
        $actions=[];
        $lastSale=$rows->last()?->day;
        if(!$lastSale || Day::parse($lastSale)->lt($today->subDays(2)))$actions[]=[
            'title'=>'Periksa kesegaran data penjualan',
            'reason'=>'Transaksi lunas terakhir dalam jendela analisis: '.($lastSale??'belum tersedia').'.',
            'action'=>'Pastikan pencatatan sudah lengkap dan bedakan hari tutup dari transaksi yang belum masuk sebelum memakai prediksi.'];
        if($changes['sales_pct']!==null && $changes['sales_pct']<0)$actions[]=['title'=>'Penjualan turun '.abs($changes['sales_pct']).'%',
            'reason'=>'Dibanding '.$previous['from'].'–'.$previous['through'].'. Jumlah transaksi berubah '.($changes['transactions_pct']??0).'%.',
            'action'=>'Periksa perubahan jam buka, ketersediaan menu, dan jumlah pengunjung sebelum memilih promosi.'];
        $needs=array_values(array_filter($stock,fn($r)=>$r['suggested_portions']>0));
        if($needs)$actions[]=['title'=>count($needs).' menu perlu review persiapan','reason'=>$needs[0]['name'].': stok '.$needs[0]['stock'].' porsi; usulan tambahan '.$needs[0]['suggested_portions'].' porsi.',
            'action'=>'Cocokkan stok fisik dan kapasitas penyimpanan. Target memakai kebutuhan 7 hari ditambah batas minimum, bukan jadwal pembelian otomatis.'];
        $down=array_values(array_filter($performance,fn($r)=>$r['previous_qty']>=10 && $r['change_pct']!==null && $r['change_pct']<=-20));
        if($down)$actions[]=['title'=>'Periksa permintaan '.$down[0]['name'],'reason'=>'Turun dari '.$down[0]['previous_qty'].' menjadi '.$down[0]['qty'].' porsi.',
            'action'=>'Cek apakah menu pernah tidak tersedia; penjualan turun belum membuktikan minat pelanggan turun.'];
        if(!$actions)$actions[]=['title'=>'Belum ada pemicu prioritas dari aturan saat ini','reason'=>'Aturan memeriksa penurunan penjualan, penurunan menu, dan kebutuhan persiapan.',
            'action'=>'Tetap cocokkan laporan dan stok fisik. Ini bukan penilaian bahwa seluruh bisnis sehat.'];
        $trend=[];foreach(array_slice($calendar,-$days) as $day)$trend[]=['date'=>$day,'sales_rp'=>(int)($map->get($day)?->sales??0),'portions'=>$daily[$day]??0];
        $current['portions']=array_sum(array_column($trend,'portions'));
        $hours=$paid()->where('created_at','>=',$from)->where('created_at','<',$today)->get(['created_at']);$hist=array_fill(0,24,0);
        foreach($hours as $r)$hist[(int)substr($r->created_at,11,2)]++;
        $peak=max($hist)>0?sprintf('%02d:00',array_search(max($hist),$hist,true)):null;
        return ['source'=>'pos_database','generated_at'=>now()->toIso8601String(),'data_through'=>$today->subDay()->toDateString(),'days'=>$days,
            'history_days'=>count($calendar),'history_from'=>$first,'last_sale_date'=>$lastSale,'current'=>$current,'previous'=>$previous,'changes'=>$changes,'peak_hour'=>$peak,
            'actions'=>array_slice($actions,0,3),'trend'=>$trend,'menus'=>$performance,'forecast'=>$f,'stock'=>$stock,
            'definitions'=>['sales'=>'Total item dikurangi diskon, sebelum pajak; hanya transaksi success dan paid.',
                'menu_sales'=>'Subtotal item sebelum diskon tingkat struk dan pajak; bukan kontribusi omzet bersih.',
                'stock'=>'Stok tersedia per menu. Tidak mengurangi reservasi lagi; checkout aplikasi sudah mengurangi stok saat reservasi.',
                'history'=>'Riwayat tugas mencakup transaksi sintetis yang diimpor. Hari tanpa transaksi dianggap nol; jam tutup belum dicatat.',
                'forecast'=>'Rentang skenario, bukan interval probabilitas terkalibrasi. Model dipilih pada jendela terdahulu; jendela terakhir untuk evaluasi.',
                'profit'=>'Laba, HPP, bahan baku, dan biaya belum tersedia. Tidak dihitung.']];
    }
    public function facts(array $s):array {
        unset($s['trend']);$s['menus']=array_slice($s['menus'],0,5);$s['stock']=array_slice($s['stock'],0,5);return $s;
    }
}
