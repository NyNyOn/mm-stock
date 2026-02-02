<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PruneOldNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:prune 
                            {--days=30 : จำนวนวันที่เก็บ notifications (default: 30)}
                            {--read-days=7 : ลบ notifications ที่อ่านแล้วหลังจากกี่วัน (default: 7)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ลบ notifications เก่าเพื่อลดขนาดฐานข้อมูล';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $maxDays = (int) $this->option('days');
        $readDays = (int) $this->option('read-days');
        
        $this->info("🗑️  กำลังลบ notifications เก่า...");
        
        // 1. ลบ notifications ที่อ่านแล้ว และเกิน X วัน
        $readDeleted = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', Carbon::now()->subDays($readDays))
            ->delete();
        
        $this->line("   ✓ ลบ notifications ที่อ่านแล้ว (>{$readDays} วัน): {$readDeleted} รายการ");
        
        // 2. ลบ notifications ทั้งหมดที่เกิน max days (ไม่ว่าอ่านหรือไม่)
        $oldDeleted = DB::table('notifications')
            ->where('created_at', '<', Carbon::now()->subDays($maxDays))
            ->delete();
        
        $this->line("   ✓ ลบ notifications เก่า (>{$maxDays} วัน): {$oldDeleted} รายการ");
        
        // 3. แสดงสถิติ
        $remaining = DB::table('notifications')->count();
        $this->info("📊 เหลือ notifications ทั้งหมด: {$remaining} รายการ");
        
        return Command::SUCCESS;
    }
}
