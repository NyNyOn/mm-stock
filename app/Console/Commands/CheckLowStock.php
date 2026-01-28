<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Equipment;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CheckLowStock extends Command
{
    protected $signature = 'app:check-low-stock';
    protected $description = 'Checks for equipment with low stock and creates/updates a scheduled purchase order.';

    public function handle()
    {
        // DISABLED: Conficts with 'stock:monthly-check' (Automated PO).
        // This command steals items into a local Pending PO, preventing the API-based Monthly Check from processing them.
        Log::info('CheckLowStock: (DISABLED) Job skipped to allow Monthly Check to run.');
        return 0;

        $this->info('Checking for low stock items...');
        Log::info('CheckLowStock: Starting job.');

        // ... (ส่วนดึง requesterId เหมือนเดิม) ...
        $requesterId = Setting::where('key', 'automation_requester_id')->value('value');
        if (!$requesterId) {
            $this->warn('Default Scheduled Requester is not set. Falling back to the first Admin user.');
            Log::warning('CheckLowStock: automation_requester_id not set. Falling back to Admin.');
            $firstAdmin = User::whereHas('serviceUserRole.userGroup', fn($q) => $q->where('slug', 'admin'))->first();
            $requesterId = $firstAdmin ? $firstAdmin->id : null;
        }
        if (!$requesterId) {
            $this->error('CRITICAL: No default Scheduled Requester is set and no Admin user found. Cannot create/update scheduled PO.');
            Log::error('CheckLowStock: CRITICAL - No default Scheduled Requester or fallback Admin found.');
            return 1;
        }
        $this->info("Using Requester ID: {$requesterId} for scheduled PO.");


        // --- ✅ START: แก้ไข Query ตรงนี้ ---
        // เปลี่ยน 'stock' เป็น 'quantity'
        // เปลี่ยน 'minimum_stock' เป็น 'min_stock'
        $lowStockItems = Equipment::whereColumn('quantity', '<=', 'min_stock')
            ->where('min_stock', '>', 0) // Only for items that have a minimum stock set
            ->where('max_stock', '>', 0) // And have a maximum stock set to calculate order quantity
            ->whereDoesntHave('purchaseOrderItems.purchaseOrder', function ($query) {
                $query->whereIn('status', ['pending', 'ordered']);
            })
            ->get();
        // --- ✅ END: แก้ไข Query ---


        if ($lowStockItems->isEmpty()) {
            $this->info('No new low stock items found to order.');
            Log::info('CheckLowStock: No new low stock items found.');
            return 0;
        }

        $this->info("Found {$lowStockItems->count()} low stock items.");

        // ... (ส่วนสร้าง/อัปเดต PO และเพิ่ม items เหมือนเดิม) ...
        $purchaseOrder = PurchaseOrder::firstOrCreate(
            ['type' => 'scheduled', 'status' => 'pending'],
            [
                'notes' => 'ใบสั่งซื้อตามรอบ (สร้างจากระบบตรวจสอบสต็อกอัตโนมัติ)',
                'ordered_by_user_id' => $requesterId
            ]
        );
        if (!$purchaseOrder->wasRecentlyCreated && is_null($purchaseOrder->ordered_by_user_id)) {
            $purchaseOrder->update(['ordered_by_user_id' => $requesterId]);
            $this->info("Updated existing scheduled PO with Requester ID: {$requesterId}.");
        }
        foreach ($lowStockItems as $item) {
            $existingPoItem = $purchaseOrder->items()->where('equipment_id', $item->id)->first();
            if (!$existingPoItem) {
                $quantityToOrder = $item->max_stock - $item->quantity;
                if ($quantityToOrder <= 0) continue;
                $purchaseOrder->items()->create([
                    'equipment_id' => $item->id,
                    'item_description' => $item->name,
                    'quantity_ordered' => $quantityToOrder,
                    'requester_id' => $requesterId,
                    'status' => 'pending',
                ]);
                $this->line(" - Added {$item->name} (Qty: {$quantityToOrder}) to PO #{$purchaseOrder->id}");
                Log::info("CheckLowStock: Added {$item->name} (Qty: {$quantityToOrder}) to PO #{$purchaseOrder->id}");
            }
        }

        $this->info('Low stock check complete.');
        Log::info('CheckLowStock: Job finished.');
        return 0;
    }
}

