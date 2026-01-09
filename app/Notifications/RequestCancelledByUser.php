<?php

namespace App\Notifications;

// (ไม่มี use Illuminate\Bus\Queueable;)
// (ไม่มี implements ShouldQueue)
use Illuminate\Notifications\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\Channels\SynologyChannel;

class RequestCancelledByUser extends Notification
{
    // (ไม่มี use Queueable;)

    protected $transaction;
    protected $canceller; // คือ $transaction->user

    /**
     * Create a new notification instance.
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
        $this->canceller = $transaction->user; // ผู้ใช้ที่เป็นคนกดยกเลิก
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return [SynologyChannel::class];
    }

    /**
     * Get the Synology Chat representation of the notification.
     */
    public function toSynology(object $notifiable): string
    {
        $equipmentName = $this->transaction->equipment->name ?? 'N/A';
        $txId = $this->transaction->id;
        $cancellerName = $this->canceller->fullname ?? 'N/A';
        $url = route('transactions.index', ['status' => 'all_history', 'search' => "TXN-{$txId}"]);

        // (ข้อความนี้จะถูกส่งไปหา Admin)
        $message  = "🗑️ **รายการเบิกถูกยกเลิกโดยผู้ใช้**\n";
        $message .= "👤 **ผู้ยกเลิก:** `{$cancellerName}`\n";
        $message .= "📝 **อุปกรณ์:** `{$equipmentName}`\n";
        $message .= "🎫 **รหัสรายการ:** `#{$txId}`\n";
        $message .= "📌 <{$url}|ดูรายการ>";

        return $message;
    }
}