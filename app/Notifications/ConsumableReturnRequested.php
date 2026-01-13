<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ConsumableReturn;
use App\Notifications\Channels\SynologyChannel;

class ConsumableReturnRequested extends Notification
{
    use Queueable;

    protected $consumableReturn;

    public function __construct(ConsumableReturn $consumableReturn)
    {
        $this->consumableReturn = $consumableReturn;
    }

    public function via($notifiable)
    {
        // หากส่งผ่าน SynologyService ให้ใช้ SynologyChannel
        if ($notifiable instanceof \App\Services\SynologyService) {
            return [SynologyChannel::class];
        }
        // ทั่วไปลง Database (Bell Notification)
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $equipmentName = $this->consumableReturn->originalTransaction->equipment->name ?? 'N/A';
        $userName = $this->consumableReturn->requester->fullname ?? 'Unknown User';
        $type = ($this->consumableReturn->action_type == 'write_off') ? 'แจ้งใช้หมด' : 'ขอคืนของ';

        return [
            'title' => 'มีคำขอคืนพัสดุใหม่',
            'body' => "$userName ได้ส่งคำขอ $type: $equipmentName",
            'action_url' => route('consumable-returns.index'),
            'type' => 'info',
            'icon' => 'fas fa-inbox'
        ];
    }

    public function toSynology($notifiable)
    {
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) { return; }

        try {
            $equipmentName = $this->consumableReturn->originalTransaction->equipment->name ?? 'N/A';
            $userName = $this->consumableReturn->requester->fullname ?? 'Unknown User';
            $qty = $this->consumableReturn->quantity_returned;
            $typeLabel = ($this->consumableReturn->action_type == 'write_off') ? '🔥 แจ้งใช้หมด (Write-off)' : '📦 ขอคืนของ (Return)';
            $notes = $this->consumableReturn->notes ?? '-';

            // ✅ Calculate Predicted Stock (Current + Returning)
            $currentStock = $this->consumableReturn->originalTransaction->equipment->quantity ?? 0;
            $predictedStock = $currentStock + $qty;
            
            // Format Stock Info
            $stockInfo = "";
            if ($this->consumableReturn->action_type !== 'write_off') {
                $stockInfo = "📦 **คงคลัง:** {$currentStock} + {$qty} = `{$predictedStock}`\n";
            }

            $message  = "📢 **มีคำขอคืนพัสดุ (Consumable Return Request)**\n" .
                        "👤 **ผู้ขอ:** `{$userName}`\n" .
                        "🛠 **อุปกรณ์:** `{$equipmentName}`\n" .
                        "📌 **ประเภท:** {$typeLabel}\n" .
                        "🔢 **คืนจำนวน:** {$qty}\n" .
                        $stockInfo . 
                        "📝 **หมายเหตุ:** {$notes}\n" .
                        "👉 [จัดการคำขอ](" . route('consumable-returns.index') . ")";

             $payload = ['text' => $message];
             Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);

        } catch (\Exception $e) {
            Log::error("Failed to send ConsumableReturnRequested Synology notification: " . $e->getMessage());
        }
    }
}
