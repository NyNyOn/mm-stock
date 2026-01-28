<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Equipment;
use Illuminate\Support\Facades\Log;

class UpdateRatings extends Command
{
    protected $signature = 'inventory:update-ratings';
    protected $description = 'Recalculate Smart Ratings for all equipment';

    public function handle()
    {
        $this->info("Starting Smart Rating Calculation...");

        $departments = \Illuminate\Support\Facades\Config::get('department_stocks.departments', []);
        $defaultConnection = \Illuminate\Support\Facades\Config::get('database.default', 'mysql');
        $defaultDbName = \Illuminate\Support\Facades\Config::get("database.connections.{$defaultConnection}.database");

        if (empty($departments)) {
            $this->error("No departments configuration found.");
            return;
        }

        foreach ($departments as $key => $dept) {
            $dbName = $dept['db_name'];
            $this->info("Processing Department: {$dept['name']} ({$dbName})...");

            try {
                // Switch Database
                \Illuminate\Support\Facades\DB::purge($defaultConnection);
                \Illuminate\Support\Facades\Config::set("database.connections.{$defaultConnection}.database", $dbName);
                \Illuminate\Support\Facades\DB::reconnect($defaultConnection);

                $equipments = Equipment::all();
                $bar = $this->output->createProgressBar($equipments->count());
                $bar->start();

                foreach ($equipments as $equipment) {
                    try {
                        $equipment->calculateSmartRating();
                    } catch(\Throwable $e) {
                         // Ignore error for missing table/column if migration not run yet
                    }
                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
                $this->info("Done.");

            } catch (\Exception $e) {
                $this->error("Failed to process {$dept['name']}: " . $e->getMessage());
            }
        }

        // Restore Default DB
        \Illuminate\Support\Facades\DB::purge($defaultConnection);
        \Illuminate\Support\Facades\Config::set("database.connections.{$defaultConnection}.database", $defaultDbName);
        \Illuminate\Support\Facades\DB::reconnect($defaultConnection);
        
        $this->info("All departments processed.");
    }
}
