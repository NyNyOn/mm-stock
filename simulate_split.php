<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Setup Test Data
$uniq = uniqid();
$po = PurchaseOrder::create([
    'po_number' => 'PO-TEST-' . $uniq,
    'pr_number' => 'PR-TEST-' . $uniq,
    'status' => 'ordered',
    'ordered_by_user_id' => 1,
    'type' => 'scheduled'
]);

$item1 = $po->items()->create([
    'item_description' => 'Item 1 (Stay)',
    'quantity_ordered' => 10,
    'pr_item_id' => 9001 + rand(0, 1000)
]);

$item2 = $po->items()->create([
    'item_description' => 'Item 2 (Move)',
    'quantity_ordered' => 5,
    'pr_item_id' => 9002 + rand(0, 1000)
]);

echo "Created PO #{$po->id} ({$po->po_number}) with Items...\n";

// 2. Simulate Webhook for Item 2 -> New PO
// Set temporary secret
\App\Models\Setting::updateOrCreate(['key' => 'pu_api_webhook_secret'], ['value' => 'test-secret']);

$controller = app(\App\Http\Controllers\Api\PurchaseOrderController::class);
$request = new \Illuminate\Http\Request();
$request->replace([
    'event' => 'item_inspection_result', // Force Approve is usually inside this event or similar
    'action' => 'force_approve',
    'pr_item_id' => $item2->pr_item_id,
    'po_code' => 'PO-NEW-' . $uniq, // New Code
    'status' => 'force', // status is 'force' or just 'shipped' depending on logic? Controller uses $action=force_approve
    'received_quantity' => 3, // Partial receive
    'inspector' => 'SimAdmin'
]);
$request->headers->set('X-Hub-Secret', 'test-secret'); 

echo "Simulating Webhook for Item 9002 -> PO-TEST-NEW-SPLIT...\n";

try {
    $response = $controller->receiveHubNotification($request);
    echo "Response: " . json_encode($response->getData()) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 3. Verify Result
$item2->refresh();
$newPoId = $item2->purchase_order_id;
$newPo = PurchaseOrder::find($newPoId);

if ($newPo && $newPo->po_number === 'PO-TEST-NEW-SPLIT') {
    echo "SUCCESS: Item 2 moved to New PO #{$newPo->id} ({$newPo->po_number}).\n";
    echo "New PO PR Number: {$newPo->pr_number} (Should be PR-TEST-SHARED)\n";
} else {
    echo "FAILED: Item 2 is still on PO #{$item2->purchase_order_id} or New PO not created.\n";
}

// Cleanup
$po->items()->delete();
$po->delete();
if (isset($newPo)) {
    $newPo->items()->delete();
    $newPo->delete();
}
