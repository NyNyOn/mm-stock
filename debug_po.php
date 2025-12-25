<?php

use App\Models\PurchaseOrder;

$po = PurchaseOrder::with('items')->where('po_number', 'PO-20251225-009')->first();

if ($po) {
    echo "PO ID: " . $po->id . "\n";
    echo "Status: " . $po->status . "\n";
    echo "Item Count: " . $po->items->count() . "\n";
    foreach($po->items as $item) {
        echo " - Item: {$item->item_description}, Ordered: {$item->quantity_ordered}, Received: " . ($item->quantity_received ?? 'NULL') . "\n";
    }
} else {
    echo "PO Found not found via po_number 'PO-20251225-009'\n";
    // Try searching by ID if it looks like an ID, but 2025... is likely code
}
