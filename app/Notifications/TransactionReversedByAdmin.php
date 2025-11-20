<?php

namespace App\Notifications;

// (ไม่มี use Illuminate\Bus\Queueable;)
// (ไม่มี implements ShouldQueue)
use Illuminate\Notifications\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\Channels\SynologyChannel;

class TransactionReversedByAdmin extends Notification
{
    // (ไม่มี use Queueable;)

    protected $transaction;
    protected $canceller; // คือ Admin ที่กดยกเลิก

    /**
     * Create a new notification instance.
     */
    public function __construct(Transaction $transaction, User $canceller)
    {
        $this->transaction = $transaction;
        $this->canceller = $canceller; // Admin ที่กดยกเลิก
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
        $adminName = $this->canceller->fullname ?? 'N/A';
        $url = route('transactions.index', ['status' => 'my_history']);

        // (ข้อความนี้จะถูกส่งไปหา User เจ้าของรายการ)
        $message  = "*⚠️ รายการเบิก (Completed) ของคุณถูกยกเลิก (Reversed)*\n";
        $message .= "Admin: `{$adminName}` ได้ทำการ *Reversal (คืนสต็อก)* รายการของคุณ\n";
        $message .= "อุปกรณ์: `{$equipmentName}`\n";
        $message .= "TXN ID: `#{$txId}`\n";
        $message .= "หากมีข้อสงสัย กรุณาติดต่อ IT\n";
        $message .= "<{$url}|ดูประวัติของฉัน>";

        return $message;
    }
}