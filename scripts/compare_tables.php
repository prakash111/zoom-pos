<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$liveTables = DB::connection()->getPdo()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$sqlContent = file_get_contents(__DIR__ . '/../public/zoom-sales-crm-database-clean.sql');
preg_match_all('/CREATE TABLE [`"]?([a-zA-Z0-9_]+)[`"]?/', $sqlContent, $matches);
$dumpTables = $matches[1] ?? [];

$missing = array_diff($liveTables, $dumpTables);
echo 'Live tables: ' . count($liveTables) . PHP_EOL;
echo 'Dump tables: ' . count($dumpTables) . PHP_EOL;
echo 'Missing tables in dump (' . count($missing) . '):' . PHP_EOL;
foreach ($missing as $m) {
    echo '  - ' . $m . PHP_EOL;
}
