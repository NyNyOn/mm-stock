<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Transaction;
use App\Notifications\Channels\SynologyChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserConfirmedReceipt extends Notification
{
    use Queueable;

    protected $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function via(object $notifiable): array
    {
        return [SynologyChannel::class];
    }

    public function toSynology(object $notifiable): void
    {
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) { return; }

        try {
            $recipientName = $this->transaction->user?->fullname ?? 'N/A';
            $equipmentName = $this->transaction->equipment?->name ?? 'N/A';
            $transactionType = $this->transaction->type === 'withdraw' ? 'เบิก' : 'ยืม';
            $transactionUrl = route('transactions.index');
            
            $message = "✅ **ปิดเคส: ผู้ใช้ยืนยันรับของแล้ว**\n" .
                       "📝 **อุปกรณ์:** {$equipmentName}\n" .
                       "👤 **ผู้รับ:** {$recipientName}\n" .
                       "📋 **ประเภท:** {$transactionType}\n" .
                       "📊 **สถานะ:** เสร็จสมบูรณ์ (Completed)\n" .
                       "📌 **URL:** {$transactionUrl}";
            
            $payload = ['text' => $message];
            Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);
        } catch (\Exception $e) {
            Log::error(
                'FATAL ERROR during UserConfirmedReceipt notification for transaction ID ' . 
                $this->transaction->id . ': ' . $e->getMessage()
            );
        }
    }
}