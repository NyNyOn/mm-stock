<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$item = \App\Models\PurchaseOrderItem::where('pr_item_id', 472)->first();
if ($item) {
    $item->status = 'shipped_from_supplier';
    $item->inspection_status = null;
    $item->inspection_notes = null; // Clear old reject notes
    $item->save();
    echo "Fixed Item 472 (ID: {$item->id}). Status set to shipped_from_supplier.\n";
} else {
    echo "Item not found.\n";
}
