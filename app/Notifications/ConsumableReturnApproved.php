<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\ConsumableReturn;
use App\Notifications\Channels\SynologyChannel;
use Illuminate\Support\Facades\Http;

class ConsumableReturnApproved extends Notification
{
    use Queueable;

    protected $consumableReturn;

    public function __construct(ConsumableReturn $consumableReturn)
    {
        $this->consumableReturn = $consumableReturn;
    }

    public function via(object $notifiable): array
    {
        return [SynologyChannel::class];
    }

    public function toSynology(object $notifiable): void
    {
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) { return; }

        $equipmentName = $this->consumableReturn->originalTransaction->equipment->name ?? 'N/A';
        $quantity = $this->consumableReturn->quantity_returned;
        $unit = $this->consumableReturn->originalTransaction->equipment->unit->name ?? 'ชิ้น';
        $approverName = $this->consumableReturn->approver->fullname ?? 'N/A';

        // ✅✅✅ แก้ไข $notifiable->fullname เป็น $notifiable->username ตรงนี้ ✅✅✅
        $message = "👍 **คำขอคืนพัสดุอนุมัติแล้ว (ถึง @{$notifiable->username})**\n" .
                   "📝 **อุปกรณ์:** {$equipmentName}\n" .
                   "🔢 **จำนวน:** {$quantity} {$unit}\n" .
                   "👤 **ผู้อนุมัติ:** {$approverName}\n";
        
        $payload = ['text' => $message];
        Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);
    }
}