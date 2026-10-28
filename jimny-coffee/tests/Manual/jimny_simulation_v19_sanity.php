<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/Support/JimnySimulationEngine.php';
$engine=new \App\Support\JimnySimulationEngine();
$s=$engine->build(new DateTimeImmutable('2026-10-28'));
$must=function($v,$msg) { if (!$v) { fwrite(STDERR,"FAIL: $msg\n");exit(1); } };
$must(($s['synthetic'] ?? false) === true,'Label data sintetis');
$must($s['history_days'] >= 730,'Riwayat kurang dari 24 bulan');
$must(count($s['catalog'])===42,'Jumlah menu demo 42');
$must(count($s['daily'])===$s['history_days'],'Dataset tidak lengkap');
$must(count($s['monthly'])>=24,'Bulan simulasi kurang dari 24');
$must($s['forecasts']['365']['available']===true,'Gate 365 hari tidak aktif');
$must($s['forecasts']['365']['backtest']['status']==='insufficient_full_horizon_holdout','Annual backtest mesti tidak tersedia');
$must($s['forecasts']['7']['backtest']['tests'] > 0,'Backtest 7 hari tidak berjalan');
$must($s['forecasts']['365']['lower'] <= $s['forecasts']['365']['point'],'Rentang bawah');
$must($s['forecasts']['365']['upper'] >= $s['forecasts']['365']['point'],'Rentang atas');
$must(count($s['restock'])===42,'Restock 42 menu');
$must(array_sum(array_column($s['daily'],'portions'))===$s['summary']['total_portions'],'Total porsi tidak cocok');
$must((new \App\Support\JimnySimulationEngine())->build(new DateTimeImmutable('2026-10-28'))['summary']
      ===$s['summary'],'Simulasi seharusnya deterministik');
echo "PASS: synthetic data, 42 menus, forecast gate, holdout, inventory, deterministic.\n";
