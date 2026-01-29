<?php

namespace App\Observers;

use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;

class PurchaseOrderItemObserver
{
    /**
     * Handle the PurchaseOrderItem "deleted" event.
     */
    public function deleted(PurchaseOrderItem $purchaseOrderItem): void
    {
        // Load the parent Purchase Order
        $purchaseOrder = $purchaseOrderItem->purchaseOrder;

        if ($purchaseOrder) {
            // Count remaining items
            $remainingItemsCount = $purchaseOrder->items()->count();

            // Check if status is 'pending' and no items remain
            if ($remainingItemsCount === 0 && $purchaseOrder->status === 'pending') {
                Log::info("Purchase Order #{$purchaseOrder->id} automatically deleted by Observer because all items were removed.");
                
                // Force Delete as requested to remove it completely from DB
                $purchaseOrder->forceDelete();
            }
        }
    }
}
