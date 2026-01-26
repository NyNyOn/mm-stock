<?php

use App\Http\Controllers\Api\PurchaseOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

// 1. Simulate Webhook Payload with item_name
$payload = [
    'event' => 'item_status_updated',
    'status' => 'arrived_at_hub',
    'po_code' => 'PO-TEST-' . uniqid(),
    'pr_code' => 'PR-TEST-' . uniqid(),
    'pr_item_id' => rand(10000, 99999), 
    'is_manual_pr' => true,
    'origin_item_id' => null, // Simulate NOT in stock
    'item_name' => 'Drill Super X-2000', // ✅ The name PU sends
    'quantity' => 5,
    'received_quantity' => 5
];

echo "Simulating Webhook for Floating PO with Name: " . $payload['item_name'] . "\n";

// 2. Create Request
$request = Request::create('/api/v1/hub/notify', 'POST', $payload);
$request->headers->set('X-Hub-Secret', \App\Models\Setting::where('key', 'pu_api_webhook_secret')->value('value') ?? config('services.pu_hub.webhook_secret'));

// ✅ Bind Request to Container (Fix UrlGenerator Error)
$app->instance('request', $request);

// 3. Call Controller
$controller = new PurchaseOrderController();
$response = $controller->receiveHubNotification($request);

echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Content: " . $response->getContent() . "\n";

// 4. Verify Database
$po = PurchaseOrder::where('po_number', $payload['po_code'])->first();
if ($po) {
    echo "PO Created: " . $po->po_number . "\n";
    $item = $po->items->first();
    if ($item) {
        echo "Item Description: " . $item->item_description . "\n";
        
        if ($item->item_description === $payload['item_name']) {
            echo "✅ SUCCESS: Item Name captured correctly!\n";
        } else {
            echo "❌ FAILURE: Item Name mismatch. Expected '{$payload['item_name']}', Got '{$item->item_description}'\n";
        }
    } else {
        echo "❌ Item not found in PO.\n";
    }
} else {
    echo "❌ PO not found.\n";
}
