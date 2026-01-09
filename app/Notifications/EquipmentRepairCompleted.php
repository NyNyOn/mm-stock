<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Equipment;
use App\Notifications\Channels\SynologyChannel;

class EquipmentRepairCompleted extends Notification
{
    use Queueable;

    protected $equipment; // อุปกรณ์หลัก (Main Stock) หลังจากรวมกลับแล้ว
    protected $quantityRestored;

    public function __construct(Equipment $equipment, int $quantityRestored)
    {
        $this->equipment = $equipment;
        $this->quantityRestored = $quantityRestored;
    }

    public function via($notifiable)
    {
        return [SynologyChannel::class];
    }

    public function toSynology($notifiable)
    {
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) { return; }

        try {
            $equipmentName = $this->equipment->name ?? 'N/A';
            $currentStock = $this->equipment->quantity;
            $unit = $this->equipment->unit->name ?? 'ชิ้น';
            $url = route('maintenance.index');

            $message  = "✅ **การซ่อมบำรุงเสร็จสิ้น (Repair Completed)**\n" .
                        "📝 **อุปกรณ์:** `{$equipmentName}`\n" .
                        "➕ **คืนสต็อก:** {$this->quantityRestored} {$unit} | 📦 **คงเหลือ:** {$currentStock} {$unit}\n" .
                        "📌 <{$url}|ดูบันทึกการซ่อม>";

             $payload = ['text' => $message];
             Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);

        } catch (\Exception $e) {
            Log::error("Failed to send EquipmentRepairCompleted notification: " . $e->getMessage());
        }
    }
}
