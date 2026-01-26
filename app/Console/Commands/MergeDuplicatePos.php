<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MergeDuplicatePos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:merge-duplicate-pos {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge Purchase Orders that have duplicate PO Numbers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Scanning for fragmented POs (Shared PR, Orphaned PO Number)...");

        // 1. Find PR Numbers appearing in multiple POs
        $sharedPRs = PurchaseOrder::select('pr_number', DB::raw('count(*) as total'))
            ->whereNotNull('pr_number')
            ->where('pr_number', '!=', '')
            ->whereNull('deleted_at') // Safety
            ->groupBy('pr_number')
            ->having('total', '>', 1)
            ->get();

        if ($sharedPRs->isEmpty()) {
            $this->info("No split PRs found.");
            return;
        }

        $this->info("Found " . $sharedPRs->count() . " PRs that are split across multiple PO records.");

        foreach ($sharedPRs as $prGroup) {
            $prNumber = $prGroup->pr_number;
            $this->info("Processing PR: {$prNumber} (Count: {$prGroup->total})");

            $pos = PurchaseOrder::where('pr_number', $prNumber)
                ->orderBy('id', 'asc')
                ->get();

            // Analyze the POs in this group
            $assignedPOs = $pos->whereNotNull('po_number')->where('po_number', '!=', '');
            $orphanPOs = $pos->whereNull('po_number'); // Or empty string?
            
            // Check for empty string too just in case
            if ($orphanPOs->count() == 0) {
                 $orphanPOs = $pos->filter(function($p) { return empty($p->po_number); });
            }

            $distinctPoNumbers = $assignedPOs->pluck('po_number')->unique();

            if ($distinctPoNumbers->count() === 1 && $orphanPOs->count() > 0) {
                // CASE: Exact Match - 1 Valid PO Number, N Orphans.
                // We assume all Orphans belong to this Valid PO Number.
                
                $masterPO = $assignedPOs->first();
                $targetPoNumber = $masterPO->po_number;
                
                $this->info("  -> Found SINGLE Target PO: {$targetPoNumber} (ID: {$masterPO->id}). Merging orphans...");

                foreach ($orphanPOs as $subPO) {
                    // Double check it's not the master (though filter should catch it)
                    if ($subPO->id == $masterPO->id) continue; 

                    $this->info("    -> Merging Orphan PO #{$subPO->id} (Items: {$subPO->items()->count()}) into #{$masterPO->id}");

                    if (!$this->option('dry-run')) {
                        DB::transaction(function () use ($subPO, $masterPO) {
                            foreach ($subPO->items as $item) {
                                $item->purchase_order_id = $masterPO->id;
                                $item->save();
                            }
                            
                            // Merge History
                            $oldHistory = $subPO->pu_data['history'] ?? [];
                            if (!empty($oldHistory)) {
                                 $masterPuData = $masterPO->pu_data ?? [];
                                 $masterHistory = $masterPuData['history'] ?? [];
                                 $oldHistory[] = ['event' => 'merged', 'reason' => "Merged from Orphan PO #{$subPO->id}", 'at' => now()->toIso8601String()];
                                 $masterPuData['history'] = array_merge($masterHistory, $oldHistory);
                                 $masterPO->pu_data = $masterPuData;
                                 $masterPO->saveQuietly();
                            }

                            $subPO->delete();
                        });
                        $this->info("       [OK] Merged.");
                    } else {
                        $this->info("       [DRY-RUN] Will Merge.");
                    }
                }

            } elseif ($distinctPoNumbers->count() > 1 && $orphanPOs->count() > 0) {
                // CASE: Ambiguous - Multiple Valid POs (Split by Vendor?). Orphans exist.
                $this->warn("  -> AMBIGUOUS: Found multiple Valid POs (" . $distinctPoNumbers->implode(', ') . "). Cannot auto-assign orphans.");
                // Future: Check logs/items to decide? For now, skip to be safe.
            
            } elseif ($distinctPoNumbers->count() == 0) {
                // CASE: All Orphans (PR Issued, no PO Number yet).
                // Should we merge them all back to one "PR Only" PO?
                // The user's issue was about "Splitting when PU sends".
                // If they are all orphans, maybe they haven't been processed by PU yet, or just local.
                // Leaving them split might be annoying too if they are just 1 PR.
                // Let's Propose merging them to the First Created One.
                
                $this->info("  -> All Orphans (No PO Numbers). Merging all into First Created PO #{$pos->first()->id} to consolidate PR.");
                
                $masterPO = $pos->first();
                
                foreach ($pos as $subPO) {
                    if ($subPO->id == $masterPO->id) continue;
                    
                    $this->info("    -> Merging Pending PO #{$subPO->id} into #{$masterPO->id}");
                    
                     if (!$this->option('dry-run')) {
                        DB::transaction(function () use ($subPO, $masterPO) {
                            foreach ($subPO->items as $item) {
                                $item->purchase_order_id = $masterPO->id;
                                $item->save();
                            }
                            $subPO->delete();
                        });
                     }
                }
                
            } else {
                $this->info("  -> No orphans or already clean (Valid split).");
            }
        }
        
        $this->info("Done.");
    }
}
