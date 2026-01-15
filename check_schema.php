<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$table = 'purchase_order_items';
$columns = Schema::getColumnListing($table);
echo "Columns: " . implode(', ', $columns) . "\n";

// To check nullability, we might need DB::select DESCRIBE
$details = DB::select("DESCRIBE {$table} purchase_order_id");
print_r($details);
