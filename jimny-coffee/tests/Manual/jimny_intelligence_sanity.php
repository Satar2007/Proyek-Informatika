<?php
/** No DB connection, safe deterministic smoke-test for forecast guard and ranges. */
require_once dirname(__DIR__, 2) . '/app/Services/JimnyIntelligenceService.php';
$service = new App\Services\JimnyIntelligenceService();
$forecast = new ReflectionMethod($service, 'forecast');
$short = $forecast->invoke($service, array_fill(0, 21, 11.0), 365, 21);
if ($short['available'] || $short['point'] !== null) {
    fwrite(STDERR, "FAIL: forecast setahun tidak boleh tampil saat data hanya 21 hari.\n"); exit(1);
}
$series = [];
for ($i = 0; $i < 210; $i++) $series[] = 10 + ($i % 7);
$ok = $forecast->invoke($service, $series, 90, 210);
if (!$ok['available'] || $ok['point'] <= 0 || $ok['lower'] < 0 || $ok['upper'] < $ok['point']) {
    fwrite(STDERR, "FAIL: bounds/horizon tidak valid.\n"); exit(1);
}
$annual = $forecast->invoke($service, $series, 365, 210);
if ($annual['available']) { fwrite(STDERR,"FAIL: year must still be gated.\n"); exit(1); }
echo "PASS: gate horizon, bounds, non-negative forecast. No DB accessed.\n";
