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
    protected $signature = 'stock:monthly-check {--force : Force run without checking schedule}';

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
        \Illuminate\Support\Facades\Log::info("Running stock:monthly-check. Force: " . ($isForce ? 'Yes' : 'No'));

        // 0. Check Schedule (if not forced)
        if (!$isForce) {
            // Default: 24th of month at 23:50
            $scheduleDay = \App\Models\Setting::where('key', 'auto_po_schedule_day')->value('value') ?? 24;
            $scheduleTime = \App\Models\Setting::where('key', 'auto_po_schedule_time')->value('value') ?? '23:50';

            $now = Carbon::now();
            
            \Illuminate\Support\Facades\Log::info("Checking Schedule. Now: {$now->format('d H:i')}, Scheduled: Day {$scheduleDay} Time {$scheduleTime}");

            // Check if today is the scheduled day
            if ($now->day != $scheduleDay) {
                // \Illuminate\Support\Facades\Log::info("Skipped: Not the scheduled day.");
                return;
            }

            // Check time (compare H:i)
            if ($now->format('H:i') !== $scheduleTime) {
                // \Illuminate\Support\Facades\Log::info("Skipped: Not the scheduled time.");
                return;
            }
        }

        $this->info('Starting monthly stock check...');
        \Illuminate\Support\Facades\Log::info("Starting stock check processing...");

        // 1. Find Low Stock Items
        $lowStockItems = Equipment::whereColumn('quantity', '<=', 'min_stock')
            ->where('min_stock', '>', 0) // Ensure we only check items that track stock
            ->get();

        if ($lowStockItems->isEmpty()) {
            $this->info('No low stock items found.');
            \Illuminate\Support\Facades\Log::info("Result: No low stock items found.");
            return;
        }

        $this->info("Found {$lowStockItems->count()} low stock items.");
        \Illuminate\Support\Facades\Log::info("Result: Found {$lowStockItems->count()} items.");

        \Illuminate\Support\Facades\Log::info("STEP 1: Starting DB Transaction");
        DB::beginTransaction();

        try {
            // 1.1 Check if a pending scheduled PO already exists for this month
            // This prevents duplicate POs if the script runs multiple times or force run is used repeatedly
            $currentMonth = Carbon::now()->format('Y-m');
            $existingPO = PurchaseOrder::where('type', 'scheduled')
                // ->where('status', 'pending') // REMOVED: Check ALL statuses to prevent duplicate monthly orders
                ->whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->first();

            if ($existingPO) {
                $this->warn("A scheduled Purchase Order already exists for this month (PO: {$existingPO->po_number}). Skipping creation.");
                \Illuminate\Support\Facades\Log::info("SKIPPED: Scheduled PO exists: {$existingPO->po_number}");
                DB::rollBack(); // Nothing done yet, but good practice to clear transaction context if any
                return;
            }

            // 2. Create Purchase Order
            $poNumber = 'PO-AUTO-' . Carbon::now()->format('YmdHis');
            \Illuminate\Support\Facades\Log::info("STEP 2: Preparing PO Data for {$poNumber}");
            
            // Get Auto Requester ID from settings
            $requesterId = \App\Models\Setting::where('key', 'automation_requester_id')->value('value');
            \Illuminate\Support\Facades\Log::info("DEBUG: Settings Requester ID: " . json_encode($requesterId));

            // Fallback: If no auto user set, try to use first admin or user ID 1 (Risky but better than crashing)
            // Or leave null if DB allows. However, typically we need a user.
            if (!$requesterId) {
                // Try to find a user to assign to
                $u = \App\Models\User::first();
                $requesterId = $u ? $u->id : null;
                \Illuminate\Support\Facades\Log::info("DEBUG: Fallback Requester ID: " . json_encode($requesterId));
            }

            $poData = [
                'po_number' => $poNumber,
                'status' => 'ordered', // ✅ CHANGED: Auto-submit to 'ordered'
                'type' => 'scheduled', 
                'ordered_at' => Carbon::now(),
                'notes' => 'Auto-generated monthly stock check for ' . Carbon::now()->format('F Y'),
                'ordered_by_user_id' => $requesterId,
            ];
            \Illuminate\Support\Facades\Log::info("STEP 3: Creating PurchaseOrder with data: " . json_encode($poData));

            $purchaseOrder = PurchaseOrder::create($poData);
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

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'equipment_id' => $item->id,
                    'quantity_ordered' => $orderQty,
                    'unit_price' => 0, // Unknown price
                    'total_price' => 0,
                ]);

                $itemsList[] = "- {$item->name} (Qty: {$orderQty})";
            }
            \Illuminate\Support\Facades\Log::info("STEP 6: Items added. Committing DB Transaction.");

            DB::commit();
            $this->info("Purchase Order {$poNumber} created successfully.");
            \Illuminate\Support\Facades\Log::info("STEP 7: DB Transaction Committed.");

            // 3. Notify Synology Chat
            \Illuminate\Support\Facades\Log::info("STEP 8: Sending Notification.");
            $this->notifySynology($purchaseOrder, $itemsList);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error creating Purchase Order: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('CRITICAL ERROR at step: ' . $e->getLine());
            \Illuminate\Support\Facades\Log::error('Error Message: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
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

        $message = "📢 **Automated Monthly Stock Check**\n";
        $message .= "Purchase Order: **{$po->po_number}** has been created.\n";
        $message .= "Total Items: **" . count($items) . "**\n\n";
        $message .= "**Items List:**\n";
        
        // Limit items in chat to avoid huge messages
        $displayItems = array_slice($items, 0, 10);
        $message .= implode("\n", $displayItems);
        
        if (count($items) > 10) {
            $message .= "\n...and " . (count($items) - 10) . " more items.";
        }

        $message .= "\n\nPlease review and process in the system.";

        try {
            \Illuminate\Support\Facades\Log::info("DEBUG: Sending POST request to Synology (Form URL Encoded)...");
            
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
}
