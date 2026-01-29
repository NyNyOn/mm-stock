<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;

class CleanupOrphanedPos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:cleanup-orphaned-pos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned Purchase Orders (pending status with 0 items)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of orphaned Purchase Orders...');

        // Find all pending POs
        $pendingPos = PurchaseOrder::where('status', 'pending')->get();
        $deletedCount = 0;

        foreach ($pendingPos as $po) {
            // Check item count
            $itemCount = $po->items()->count();

            if ($itemCount === 0) {
                $this->info("Deleting Orphaned PO #{$po->id} (Type: {$po->type})");
                $po->forceDelete();
                $deletedCount++;
            }
        }

        $this->info("Cleanup completed. Total deleted: {$deletedCount}");
        Log::info("CleanupOrphanedPos: Deleted {$deletedCount} empty pending purchase orders.");
    }
}
