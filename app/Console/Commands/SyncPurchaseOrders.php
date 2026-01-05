<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPurchaseOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-purchase-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Purchase Order status and PO Number from PU Hub for orders that only have PR Number';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Purchase Order Sync...');

        // 1. Find POs with PR Number but NO PO Number
        // We also check status to ensure we don't sync completed or cancelled ones unnecessarily, 
        // though if they lack PO number they might need sync regardless.
        $orders = PurchaseOrder::whereNotNull('pr_number')
            ->whereNull('po_number')
            ->whereNotIn('status', ['cancelled', 'completed', 'received', 'closed']) // Adjust based on actual end-states
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No orders found requiring sync.');
            return;
        }

        $this->info("Found {$orders->count()} orders to sync.");

        $baseUrl = config('services.pu_hub.base_url');
        $token = config('services.pu_hub.token');
        // Default to a status check endpoint. Adjust 'check_status_path' as needed.
        // Assuming PU API follows a pattern or we decided on '/api/v1/pr-status'
        $statusPath = config('services.pu_hub.check_status_path', '/api/v1/pr-status'); 

        $url = rtrim($baseUrl, '/') . '/' . ltrim($statusPath, '/');

        foreach ($orders as $order) {
            try {
                $this->info("Syncing PR: {$order->pr_number} (ID: {$order->id})...");

                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(10)
                    ->post($url, [
                        'pr_code' => $order->pr_number,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Logic similar to Controller
                    $updated = false;
                    
                    // 1. Check for PO Number
                    if (isset($data['po_code']) && !empty($data['po_code'])) {
                        $order->po_number = $data['po_code'];
                        $updated = true;
                        $this->info("  -> Found PO Number: {$data['po_code']}");
                    } elseif (isset($data['po_number']) && !empty($data['po_number'])) {
                        $order->po_number = $data['po_number'];
                        $updated = true;
                        $this->info("  -> Found PO Number: {$data['po_number']}");
                    }

                    // 2. Check for Status Update (Optional, but good to have)
                    // Map PU status to local status if needed. 
                    // For now, if we get a PO number, we might want to ensure status is at least 'ordered' or 'approved'
                    if (isset($data['status'])) {
                         // Basic mapping, can be expanded
                         if ($data['status'] === 'approved' && $order->status !== 'approved') {
                             $order->status = 'approved';
                             $updated = true;
                         }
                    }

                    if ($updated) {
                         // Merge new data into pu_data
                         $currentData = $order->pu_data ?? [];
                         // We append/merge the new check result
                         $currentData['last_sync'] = now()->toIso8601String();
                         $currentData['sync_response'] = $data;
                         
                         $order->pu_data = $currentData;
                         $order->save();
                         $this->info("  -> Updated successfully.");
                    } else {
                        $this->info("  -> No new updates found.");
                    }

                } else {
                    $this->error("  -> Failed to sync. Status: {$response->status()}");
                    Log::error("PO Sync Failed for #{$order->id}: " . $response->body());
                }

            } catch (\Exception $e) {
                $this->error("  -> Exception: " . $e->getMessage());
                Log::error("PO Sync Exception for #{$order->id}: " . $e->getMessage());
            }
        }

        $this->info('Sync completed.');
    }
}
