<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$item = \App\Models\PurchaseOrderItem::where('pr_item_id', 472)->first();
if (!$item) {
    echo "Item 472 not found.\n";
    exit;
}

echo "Item ID: " . $item->id . "\n";
echo "PR Item ID: " . $item->pr_item_id . "\n";
echo "Status: " . $item->status . "\n";
echo "Quantity Ordered: " . $item->quantity_ordered . "\n";
echo "Quantity Received: " . $item->quantity_received . "\n";
echo "Inspection Status: " . $item->inspection_status . "\n";
echo "Current PO ID: " . $item->purchase_order_id . "\n";

$po = $item->purchaseOrder;
echo "PO Number: " . $po->po_number . "\n";
echo "PO Status: " . $po->status . "\n";
