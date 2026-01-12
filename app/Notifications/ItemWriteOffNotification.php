<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\Channels\SynologyChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ItemWriteOffNotification extends Notification
{
    use Queueable;

    protected $transaction;
    protected $handler;

    public function __construct(Transaction $transaction, User $handler)
    {
        $this->transaction = $transaction;
        $this->handler = $handler;
    }

    public function via(object $notifiable): array
    {
        return ['database', SynologyChannel::class]; // ✅ Added Database
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'ตัดยอดสูญหาย (Write-Off)',
            'body' => "อุปกรณ์ '{$this->transaction->equipment->name}' ถูกตัดยอดโดย Admin",
            'action_url' => route('transactions.index'),
            'type' => 'error', // Use error type for red color/alert
            'icon' => 'fas fa-trash-alt'
        ];
    }

    public function toSynology(object $notifiable): void
    {
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) { return; }

        try {
            $equipmentName = $this->transaction->equipment?->name ?? 'N/A';
            $sender = $this->handler->fullname ?? 'Admin';
            $transactionUrl = route('transactions.index');
            $originalUser = $this->transaction->user?->fullname ?? 'N/A';
            
            $message = "🚫 **แจ้งเตือน: ตัดยอดสูญหาย (Write-Off)**\n" .
                       "📝 **อุปกรณ์:** {$equipmentName}\n" .
                       "👤 **ผู้ดำเนินการ:** {$sender}\n" .
                       "📉 **จากรายการของ:** {$originalUser}\n" .
                       "📋 **รายละเอียด:** มีการตัดยอดสินค้าสูญหาย/ชำรุด โดย Admin\n" .
                       "📌 **URL:** {$transactionUrl}";
            
            $payload = ['text' => $message];
            Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);
        } catch (\Exception $e) {
            Log::error('WriteOff Notification Error: ' . $e->getMessage());
        }
    }
}
