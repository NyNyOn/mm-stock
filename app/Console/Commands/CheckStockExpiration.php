<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Equipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckStockExpiration extends Command
{
    protected $signature = 'stock:check-expiration';
    protected $description = 'ตรวจสอบและแช่แข็งอุปกรณ์ที่ไม่ได้นับสต็อกเกิน 105 วัน';

    public function handle()
    {
        try {
            $limitDays = 105;
            $expiredDate = Carbon::now()->subDays($limitDays);

            $this->info("Starting stock expiration check... Cut-off: " . $expiredDate->toDateString());

            // ค้นหาอุปกรณ์ที่หมดอายุ
            $expiredEquipments = Equipment::whereNotIn('status', ['sold', 'disposed', 'frozen'])
                ->where(function($q) use ($expiredDate) {
                    $q->where('last_stock_check_at', '<', $expiredDate)
                      ->orWhereNull('last_stock_check_at');
                })
                ->get();

            $count = 0;
            foreach ($expiredEquipments as $equipment) {
                $equipment->status = 'frozen';
                $equipment->save();
                $this->info("Frozen Item: {$equipment->name} (ID: {$equipment->id})");
                $count++;
            }

            $message = "Stock Expiration Check Completed. Total frozen: {$count}";
            $this->info($message);
            Log::info($message);
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("CheckStockExpiration Command Failed: " . $e->getMessage());
        }
    }
}