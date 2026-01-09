<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\Channels\SynologyChannel;

class TransactionReversedByAdmin extends Notification
{
    use Queueable;

    protected $transaction;
    protected $canceller;

    public function __construct(Transaction $transaction, User $canceller)
    {
        $this->transaction = $transaction;
        $this->canceller = $canceller;
    }

    public function via($notifiable)
    {
        return [SynologyChannel::class];
    }

    public function toSynology(object $notifiable): void
    {
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) { return; }

        try {
            $equipmentName = $this->transaction->equipment->name ?? 'N/A';
            $txId = $this->transaction->id;
            $adminName = $this->canceller->fullname ?? 'N/A';
            $url = route('transactions.index', ['status' => 'my_history']);
            
            // ข้อมูลการคืนสต็อก
            $restoredAmount = abs($this->transaction->quantity_change);
            $currentStock = $this->transaction->equipment->quantity;
            $unit = $this->transaction->equipment->unit->name ?? 'ชิ้น';

            // (ข้อความนี้จะถูกส่งไปหา User เจ้าของรายการ)
            $message  = "⚠️ **รายการเบิกถูกยกเลิก (Reversed)**\n" .
                        "🎫 **รหัสรายการ:** `#{$txId}`\n" .
                        "📝 **อุปกรณ์:** `{$equipmentName}`\n" .
                        "➕ **คืนสต็อก:** {$restoredAmount} {$unit} | 📦 **คงเหลือหลังจากคืน:** {$currentStock} {$unit}\n" .
                        "👤 **ดำเนินการโดย:** `{$adminName}` (Admin)\n" .
                        "❗ หากมีข้อสงสัย กรุณาติดต่อ IT\n" .
                        "📌 <{$url}|ดูประวัติของฉัน>";

             $payload = ['text' => $message];
             Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);

        } catch (\Exception $e) {
            Log::error("Failed to send TransactionReversedByAdmin notification: " . $e->getMessage());
        }
    }
}