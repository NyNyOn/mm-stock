<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Equipment;

class FixEquipmentStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'equipment:fix-status {--dry-run : Only show what would be fixed without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan and fix equipment statuses based on quantity and min_stock logic';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting Equipment Status Scan...');

        $equipments = Equipment::all();
        $fixedCount = 0;
        $dryRun = $this->option('dry-run');

        $manualStatuses = [
            'maintenance', 'disposed', 'sold',
            'on_loan', 'repairing', 'inactive', 'written_off',
            'frozen' // Frozen items should generally not be auto-updated unless we specifically want to
        ];

        foreach ($equipments as $equipment) {
            // Skip manual statuses
            if (in_array($equipment->status, $manualStatuses)) {
                continue;
            }

            $originalStatus = $equipment->status;
            $calculatedStatus = 'available';

            if ($equipment->quantity <= 0) {
                $calculatedStatus = 'out_of_stock';
            } elseif ($equipment->min_stock > 0 && $equipment->quantity <= $equipment->min_stock) {
                $calculatedStatus = 'low_stock';
            } else {
                $calculatedStatus = 'available';
            }

            if ($originalStatus !== $calculatedStatus) {
                $this->warn("Mismatch Found: [ID: {$equipment->id}] {$equipment->name}");
                $this->line("   - Qty: {$equipment->quantity}, Min: {$equipment->min_stock}");
                $this->line("   - Current Status: {$originalStatus}");
                $this->line("   - Should Be:      {$calculatedStatus}");

                if (!$dryRun) {
                    $equipment->status = $calculatedStatus;
                    // We use saveQuietly() to avoid triggering the 'saving' event loop again, 
                    // although the event logic is identical so it wouldn't hurt. 
                    // But here we want to be explicit.
                    $equipment->saveQuietly(); 
                    $this->info("   -> FIXED");
                    $fixedCount++;
                } else {
                    $this->info("   -> [Dry Run] Would be fixed");
                    $fixedCount++;
                }
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Scan Complete. Found {$fixedCount} items to fix (Dry Run).");
        } else {
            $this->info("Scan Complete. Fixed {$fixedCount} items.");
        }

        return 0;
    }
}
