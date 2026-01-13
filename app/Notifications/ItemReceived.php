<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Transaction;
use App\Models\User;

class ItemReceived extends Notification
{
    use Queueable;

    protected $transaction;
    protected $sender;

    public function __construct(Transaction $transaction, User $sender)
    {
        $this->transaction = $transaction;
        $this->sender = $sender;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'คุณได้รับอุปกรณ์',
            'body' => "Admin {$this->sender->fullname} ได้ทำรายการเบิก '{$this->transaction->equipment->name}' ให้คุณ",
            'action_url' => route('transactions.index', ['status' => 'my_history']),
            'type' => 'info',
            'icon' => 'fas fa-box-open'
        ];
    }
}
