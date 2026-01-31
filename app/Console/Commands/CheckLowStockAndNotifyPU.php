<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Equipment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckLowStockAndNotifyPU extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:monthly-check {--force : Force run without checking schedule} {--draft-only : Create PO but do not auto-submit to API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for low stock items, create a scheduled PO, and notify Synology Chat.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isForce = $this->option('force');
        $isDraftOnly = $this->option('draft-only');

        // 0. Check Schedule (if not forced AND not draft-only)
        if (!$isForce && !$isDraftOnly) {
            // Default: 24th of month at 23:50
            $scheduleDay = \App\Models\Setting::where('key', 'auto_po_schedule_day')->value('value') ?? 24;
            $scheduleTime = \App\Models\Setting::where('key', 'auto_po_schedule_time')->value('value') ?? '23:50';

            // ✅ Fix: Force Timezone to Asia/Bangkok for consistency
            $now = Carbon::now('Asia/Bangkok');
            
            // Check if today is the scheduled day
            if ($now->day != $scheduleDay) {
                return;
            }

            // Check time (compare H:i)
            if ($now->format('H:i') !== $scheduleTime) {
                return;
            }

            // ✅ Fix: Use Setting to track monthly run instead of 'existing PO' check
            // This prevents manual 'scheduled' POs (like from Webhooks) from blocking the monthly run
            $lastRunMonth = \App\Models\Setting::where('key', 'last_auto_po_run_month')->value('value');
            $currentMonth = $now->format('Y-m');

            if ($lastRunMonth === $currentMonth) {
                \Illuminate\Support\Facades\Log::info("Skipped: Monthly check already ran for {$currentMonth}.");
                return;
            }
        } else {
            $now = Carbon::now('Asia/Bangkok');
        }

        $this->info('Starting monthly stock check...');
        \Illuminate\Support\Facades\Log::info("Starting stock check processing...");

        // 1. Find Low Stock Items
        $query = Equipment::whereColumn('quantity', '<=', 'min_stock')
            ->where('min_stock', '>', 0);

        // ✅ Anti-Duplicate Logic: Prevent re-ordering if item is already in an active PO
        // ONLY applies to Automatic Run (!force). Manual run (User) can override.
        if (!$isForce) {
            $query->whereDoesntHave('purchaseOrderItems.purchaseOrder', function ($q) {
                $q->whereIn('status', [
                    'pending', 
                    'ordered', 
                    'approved', 
                    'shipped_from_supplier', 
                    'partial_receive'
                ]);
            });
        }

        $lowStockItems = $query->get();

        if ($lowStockItems->isEmpty()) {
            $this->info('No low stock items found.');
            \Illuminate\Support\Facades\Log::info("Result: No low stock items found.");
            // ✅ Mark as run even if no items, to prevents re-running every minute? 
            // Actually, if no items, we might WANT to run again if items become low later today?
            // User requirement: "Monthly Check" -> implies one-shot.
            // Let's mark it as run to be safe and avoid log spam.
            if (!$isForce && !$isDraftOnly) {
                 \App\Models\Setting::updateOrCreate(
                    ['key' => 'last_auto_po_run_month'],
                    ['value' => $now->format('Y-m')]
                );
            }
            return;
        }

        $this->info("Found {$lowStockItems->count()} low stock items.");
        \Illuminate\Support\Facades\Log::info("Result: Found {$lowStockItems->count()} items.");

        \Illuminate\Support\Facades\Log::info("STEP 1: Starting DB Transaction");
        DB::beginTransaction();

        try {
            // ✅ "Existing PO" Check removed (Handled by Settings)
            
            // 2. Create Purchase Order (Original Logic)


            // 2. Create Purchase Order (Mimic Manual Creation)
            // Leave po_number NULL and status 'pending' to act like "Check Low Stock" button.
            // The API call later will assign the number and change status to 'ordered'.
            
            // Get Auto Requester ID from settings
            $requesterId = \App\Models\Setting::where('key', 'automation_requester_id')->value('value');
            \Illuminate\Support\Facades\Log::info("DEBUG: Settings Requester ID: " . json_encode($requesterId));

            if (!$requesterId) {
                $u = \App\Models\User::first();
                $requesterId = $u ? $u->id : null;
            }

            $poData = [
                'po_number' => null, // Let API assign it
                'status' => 'pending', // Pending submission
                'type' => 'scheduled', 
                'ordered_at' => Carbon::now(),
                'notes' => 'Auto-generated monthly stock check for ' . Carbon::now()->format('F Y'),
                'ordered_by_user_id' => $requesterId,
            ];

            // ✅ Find existing Pending Scheduled PO to reuse (Cart behavior)
            $purchaseOrder = PurchaseOrder::where('type', 'scheduled')
                ->where('status', 'pending')
                ->latest() // Get the most recent one if duplicates exist
                ->first();

            if ($purchaseOrder) {
                 \Illuminate\Support\Facades\Log::info("STEP 3: Reusing Existing Pending PO #{$purchaseOrder->id}");
                 // Update timestamp or notes if needed? Maybe not.
            } else {
                 \Illuminate\Support\Facades\Log::info("STEP 3: Creating New Pending PurchaseOrder");
                 $purchaseOrder = PurchaseOrder::create($poData);
            }
            $poNumber = "PENDING-{$purchaseOrder->id}"; // Temporary placeholder for logs
            \Illuminate\Support\Facades\Log::info("STEP 4: PurchaseOrder Created. ID: " . $purchaseOrder->id);

            $totalAmount = 0;
            $itemsList = [];

            foreach ($lowStockItems as $item) {
                \Illuminate\Support\Facades\Log::info("STEP 5: Processing Item: {$item->name} (ID: {$item->id})");
                // Calculate suggested order quantity (e.g., up to max_stock or a fixed amount)
                // For now, let's order enough to reach max_stock, or min_stock + buffer.
                // If max_stock is set, use it. Otherwise, order min_stock * 2.
                $orderQty = ($item->max_stock > 0) 
                    ? ($item->max_stock - $item->quantity)
                    : ($item->min_stock); // Fallback logic
                
                if ($orderQty <= 0) $orderQty = 1; // Ensure at least 1

                // ✅ Check if item already exists in this PO (Merge/Skip)
                $existingItem = $purchaseOrder->items()->where('equipment_id', $item->id)->first();
                
                if ($existingItem) {
                    \Illuminate\Support\Facades\Log::info("   -> Item {$item->id} already in PO. Updating Qty.");
                    // Optional: Update quantity? Or just skip? User wants "Low Stock Check"
                    // If we just skip, and they consumed more, maybe they want updated Qty?
                    // Let's update Qty to new calculation.
                    $existingItem->quantity_ordered = $orderQty;
                    $existingItem->save();
                    $itemsList[] = "- {$item->name} (Updated Qty: {$orderQty})";
                } else {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'equipment_id' => $item->id,
                        'quantity_ordered' => $orderQty,
                        'unit_price' => 0, // Unknown price
                        'total_price' => 0,
                    ]);
                    $itemsList[] = "- {$item->name} (Qty: {$orderQty})";
                }

                // $itemsList[] = "- {$item->name} (Qty: {$orderQty})"; // REMOVED: Duplicate addition
            }
            \Illuminate\Support\Facades\Log::info("STEP 6: Items added. Committing DB Transaction.");

            DB::commit();
            \Illuminate\Support\Facades\Log::info("STEP 7: DB Transaction Committed.");

            // ✅ DRAFT ONLY MODE: Stop here (Manual Check Button Usage)
            if ($this->option('draft-only')) {
                $this->info("DRAFT MODE: Pending PO created/updated. Stopping before API submission.");
                \Illuminate\Support\Facades\Log::info("CheckLowStock: Draft PO #{$purchaseOrder->id} created/updated. User must submit manually.");
                return;
            }

            // ✅ TRIGGER API SUBMISSION (Like pressing "Submit PO")
            \Illuminate\Support\Facades\Log::info("STEP 7.5: Submitting PO to PU Hub API...");
            
            $attempts = 0;
            $maxAttempts = 3;
            $success = false;

            while (!$success && $attempts < $maxAttempts) {
                $attempts++;
                try {
                    $controller = new \App\Http\Controllers\PurchaseOrderController();
                    $request = new \Illuminate\Http\Request(); // Empty request
                    
                    // Call the public method to send to API
                    // ✅ Pass TRUE to suppress the standard "PU Accepted" notification
                    $controller->sendPurchaseOrderToApi($purchaseOrder, $request, true);
                    
                    // Refresh to get the assigned PO Number from API
                    $purchaseOrder->refresh();
                    $poNumber = $purchaseOrder->po_number; 
                    
                    $this->info("PO Submitted to API. Assigned Number: {$poNumber}");
                    \Illuminate\Support\Facades\Log::info("API Submission Success. PO Number: {$poNumber}");
                    $success = true;

                } catch (\Exception $e) {
                    $this->error("Attempt {$attempts} failed: " . $e->getMessage());
                    \Illuminate\Support\Facades\Log::error("API Submission Failed (Attempt {$attempts}): " . $e->getMessage());
                    
                    if ($attempts < $maxAttempts) {
                        sleep(5); // Wait 5 seconds before retry
                    }
                }
            }
            
            $this->info("Purchase Order processed successfully.");

            // 3. Notify Synology Chat
            \Illuminate\Support\Facades\Log::info("STEP 8: Sending Notification.");
            // 3. Notify Synology Chat
            \Illuminate\Support\Facades\Log::info("STEP 8: Sending Notification.");
            sleep(2); // ✅ Prevent Rate Limit (411 Error)
            $this->notifySynology($purchaseOrder, $itemsList);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error creating Purchase Order: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('CRITICAL ERROR at step: ' . $e->getLine());
            \Illuminate\Support\Facades\Log::error('Error Message: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());

            // 🔴 แจ้งเตือน Error ไปยัง Synology Chat
            // 🔴 แจ้งเตือน Error ไปยัง Synology Chat
            sleep(2); // ✅ Prevent Rate Limit (411 Error)
            $this->notifySynologyError($e->getMessage());
        }
    }

    private function notifySynology($po, $items)
    {
        $webhookUrl = config('services.synology.chat_webhook_url') ?? env('SYNOLOGY_CHAT_WEBHOOK_URL');

        if (!$webhookUrl) {
            $this->warn('Synology Webhook URL not configured. Notification skipped.');
            \Illuminate\Support\Facades\Log::warning("Synology Webhook URL NOT configured.");
            return;
        }

        // ✅ Clean URL: Remove potential invalid quotes/characters from .env injection
        $webhookUrl = str_replace(['"', "'", '%22'], '', $webhookUrl);

        \Illuminate\Support\Facades\Log::info("DEBUG: Webhook URL: " . $webhookUrl);

        $message = "📢 **ตรวจสอบสต็อกรายเดือนอัตโนมัติ**\n";
        $message .= "เลขที่ใบสั่งซื้อ: **{$po->po_number}** สร้างสำเร็จแล้ว\n";
        $message .= "จำนวนรายการทั้งหมด: **" . count($items) . "** รายการ\n\n";
        $message .= "**รายการสินค้า:**\n";
        
        // Limit items in chat to avoid huge messages
        $displayItems = array_slice($items, 0, 10);
        $message .= implode("\n", $displayItems);
        
        if (count($items) > 10) {
            $message .= "\n...และอีก " . (count($items) - 10) . " รายการ";
        }

        $message .= "\n\nกรุณาตรวจสอบและดำเนินการในระบบ";

        try {
            // ✅ Fix: Fallback to PR Number if PO Number is waiting for approval
            $displayNumber = $po->po_number ?? $po->pr_number ?? 'รอเลขที่ (PR Created)';
            $message = str_replace("**{$po->po_number}**", "**{$displayNumber}**", $message);

            \Illuminate\Support\Facades\Log::info("DEBUG: Sending POST request to Synology (Form URL Encoded)...");
            
            // Note: sleep(2) removed as we now suppress the duplicate notification cleanly.

            // ✅ Fix: Synology expects form-data with 'payload' key containing JSON string
            $response = Http::asForm()->post($webhookUrl, [
                'payload' => json_encode(['text' => $message])
            ]);

            \Illuminate\Support\Facades\Log::info("DEBUG: Response Status: " . $response->status());

            if ($response->successful()) {
                $this->info('Synology Chat notification sent.');
                \Illuminate\Support\Facades\Log::info("SUCCESS: Synology Chat notification sent. Body: " . $response->body());
            } else {
                $this->error('Failed to send Synology notification: ' . $response->body());
                \Illuminate\Support\Facades\Log::error("FAILED: Synology API Error: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Exception sending Synology notification: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error("EXCEPTION: sending Synology notification: " . $e->getMessage());
        }
    }

    /**
     * แจ้งเตือน Error ไปยัง Synology Chat
     * เมื่อระบบตรวจสอบสต็อกและส่ง PO ไม่ทำงาน
     */
    private function notifySynologyError(string $errorMessage)
    {
        $webhookUrl = config('services.synology.chat_webhook_url') ?? env('SYNOLOGY_CHAT_WEBHOOK_URL');

        if (!$webhookUrl) {
            \Illuminate\Support\Facades\Log::warning("[ErrorNotify] Synology Webhook URL NOT configured.");
            return;
        }

        $webhookUrl = str_replace(['"', "'", '%22'], '', $webhookUrl);

        $message = "🔴 **ระบบแจ้งเตือนปัญหา**\n\n";
        $message .= "**ระบบตรวจสอบสต็อกต่ำและส่ง PO อัตโนมัติ ไม่ทำงาน!**\n\n";
        $message .= "📍 **Error Message:**\n```\n{$errorMessage}\n```\n\n";
        $message .= "⚠️ กรุณาติดต่อ **IT** เพื่อตรวจสอบการทำงาน\n";
        $message .= "📅 เวลา: " . Carbon::now()->format('d/m/Y H:i:s');

        try {
            $response = Http::asForm()->post($webhookUrl, [
                'payload' => json_encode(['text' => $message])
            ]);

            if ($response->successful()) {
                \Illuminate\Support\Facades\Log::info("[ErrorNotify] Error notification sent to Synology Chat.");
            } else {
                \Illuminate\Support\Facades\Log::error("[ErrorNotify] Failed to send error notification: " . $response->body());
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("[ErrorNotify] Exception: " . $e->getMessage());
        }
    }
}
