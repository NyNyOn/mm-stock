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

        // Batch Processing: Chunk orders (e.g., 50 at a time) to reduce API calls
        $chunks = $orders->chunk(50);
        
        foreach ($chunks as $chunk) {
            $prCodes = $chunk->pluck('pr_number')->filter()->values()->toArray();
            
            if (empty($prCodes)) continue;

            $this->info("Syncing Batch of " . count($prCodes) . " PRs...");

            // Use batch endpoint (defaulting to same as single if not configured, but payload differs)
            // Ideally should be a dedicated batch endpoint if PU supports it. 
            // Assuming we use the same base status path but with batch payload or dedicated path.
            $batchPath = config('services.pu_hub.batch_status_path', '/api/v1/pr-batch-status');
            $batchUrl = rtrim($baseUrl, '/') . '/' . ltrim($batchPath, '/');

            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(30)
                    ->post($batchUrl, [
                        'pr_codes' => $prCodes,
                    ]);

                if ($response->successful()) {
                    $results = $response->json(); 
                    // Normalize: Support both wrapped 'data' and direct array
                    if (isset($results['data'])) $results = $results['data'];

                    $this->info("  -> Received response. Processing updates...");

                    // Map results by PR Code
                    $resultsMap = [];
                    foreach ($results as $key => $val) {
                        $prKey = $val['pr_code'] ?? $key; // robust keying
                        if ($prKey) $resultsMap[$prKey] = $val;
                    }

                    foreach ($chunk as $order) {
                        $data = $resultsMap[$order->pr_number] ?? null;
                        if (!$data) continue;

                        $updated = false;
                        
                        // 1. Check for PO Number
                        if (isset($data['po_code']) && !empty($data['po_code'])) {
                            if ($order->po_number !== $data['po_code']) {
                                $order->po_number = $data['po_code'];
                                $updated = true;
                                $this->info("  -> [{$order->pr_number}] Found PO: {$data['po_code']}");
                            }
                        } elseif (isset($data['po_number']) && !empty($data['po_number'])) {
                             if ($order->po_number !== $data['po_number']) {
                                $order->po_number = $data['po_number'];
                                $updated = true;
                                $this->info("  -> [{$order->pr_number}] Found PO: {$data['po_number']}");
                            }
                        }

                        // 2. Check and Update Status
                        if (isset($data['status'])) {
                             if ($data['status'] === 'approved' && $order->status !== 'approved') {
                                 if (in_array($order->status, ['pending', 'ordered'])) {
                                     $order->status = 'approved';
                                     $updated = true;
                                 }
                             }
                        }

                        if ($updated) {
                             $currentData = $order->pu_data ?? [];
                             $currentData['last_sync'] = now()->toIso8601String();
                             $currentData['sync_response'] = $data;
                             $order->pu_data = $currentData;
                             $order->save();
                        }
                    }

                } else {
                    $this->error("  -> Batch Request Failed. Status: {$response->status()}");
                    Log::error("PO Batch Sync Failed: " . $response->body());
                }

            } catch (\Exception $e) {
                $this->error("  -> Batch Exception: " . $e->getMessage());
                Log::error("PO Batch Sync Exception: " . $e->getMessage());
            }
        }

        $this->info('Sync completed.');
    }
}
