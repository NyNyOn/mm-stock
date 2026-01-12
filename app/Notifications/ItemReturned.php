<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Notifications\Channels\SynologyChannel;

class ItemReturned extends Notification
{
    use Queueable;

    protected $transaction; // Transaction ประเภท 'return'

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function via($notifiable)
    {
        return ['database', SynologyChannel::class]; // ✅ Added Database
    }

    // ✅ Database Notification Structure
    public function toArray($notifiable)
    {
        return [
            'title' => 'มีการคืนอุปกรณ์',
            'body' => "ผู้ใช้คืนอุปกรณ์ '{$this->transaction->equipment->name}' เรียบร้อยแล้ว",
            'action_url' => route('transactions.index'),
            'type' => 'info',
            'icon' => 'fas fa-undo'
        ];
    }

    public function toSynology($notifiable)
    {
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) { return; }

        try {
            $equipmentName = $this->transaction->equipment->name ?? 'N/A';
            $returnerName = $this->transaction->user->fullname ?? 'N/A'; // ผู้คืน (User ID ใน Transaction Return คือผู้ยืมเดิม)
            
            $quantity = abs($this->transaction->quantity_change);
            $currentStock = $this->transaction->equipment->quantity;
            $unit = $this->transaction->equipment->unit->name ?? 'ชิ้น';
            $notes = $this->transaction->notes;

            // ตรวจสอบสภาพ
            $conditionText = "สภาพดี (Good)";
            if (str_contains($notes, 'defective')) {
               $conditionText = "⚠️ ชำรุด (Defective)";
            }

            $message  = "↩️ **มีการคืนอุปกรณ์ (Return Received)**\n" .
                        "👤 **ผู้คืน:** `{$returnerName}`\n" .
                        "📝 **อุปกรณ์:** `{$equipmentName}`\n" .
                        "🔎 **สภาพ:** {$conditionText}\n" .
                        "➕ **รับคืน:** {$quantity} {$unit} | 📦 **คงเหลือ:** {$currentStock} {$unit}";

             $payload = ['text' => $message];
             Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);

        } catch (\Exception $e) {
            Log::error("Failed to send ItemReturned notification: " . $e->getMessage());
        }
    }
}
