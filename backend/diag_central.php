<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'DB_CONNECTION=' . config('database.default') . PHP_EOL;
echo 'central.database=' . config('database.connections.central.database') . PHP_EOL;
echo 'central.host=' . config('database.connections.central.host') . PHP_EOL;

try {
    Illuminate\Support\Facades\DB::connection('central')->select('SELECT 1');
    echo 'CENTRAL OK' . PHP_EOL;
} catch (\Throwable $e) {
    echo 'CENTRAL ERR: ' . $e->getMessage() . PHP_EOL;
}
