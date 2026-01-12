<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Equipment;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Notifications\Channels\SynologyChannel;

class EquipmentSentForRepair extends Notification
{
    use Queueable;

    protected $equipment;
    protected $log;

    public function __construct(Equipment $equipment, MaintenanceLog $log)
    {
        $this->equipment = $equipment;
        $this->log = $log;
    }

    public function via($notifiable)
    {
        return ['database', SynologyChannel::class]; // ✅ Added Database
    }

    // ✅ Database Notification Structure
    public function toArray($notifiable)
    {
        return [
            'title' => 'อุปกรณ์ส่งซ่อม',
            'body' => "อุปกรณ์ '{$this->equipment->name}' ถูกส่งซ่อม (อาการ: {$this->log->problem_description})",
            'action_url' => route('equipment.index'),
            'type' => 'warning',
            'icon' => 'fas fa-tools'
        ];
    }

    public function toSynology($notifiable)
    {
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) { return; }

        try {
            $equipmentName = $this->equipment->name ?? 'N/A';
            $reporterName = $this->log->reportedBy->fullname ?? 'N/A';
            $problem = $this->log->problem_description ?? 'ไม่ระบุ';
            $date = $this->log->created_at->format('d/m/Y H:i');
            
            // อ่านค่า ID ของอุปกรณ์หลัก ถ้ามี
            $mainStockInfo = "";
            if (preg_match('/ID: (\d+)/', $this->equipment->notes, $matches)) {
                $mainStockInfo = " (แยกซ่อมจาก ID: {$matches[1]})";
            }

            // แจ้ง Admin / IT Support
            $message  = "🛠️ **แจ้งซ่อมอุปกรณ์ (Sent for Repair)**\n" .
                        "📝 **อุปกรณ์:** `{$equipmentName}`{$mainStockInfo}\n" .
                        "⚠️ **อาการเสีย:** `{$problem}`\n" .
                        "👤 **ผู้แจ้ง:** `{$reporterName}`\n" .
                        "📅 **วันที่แจ้ง:** {$date}";

             $payload = ['text' => $message];
             Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);

        } catch (\Exception $e) {
            Log::error("Failed to send EquipmentSentForRepair notification: " . $e->getMessage());
        }
    }
}
