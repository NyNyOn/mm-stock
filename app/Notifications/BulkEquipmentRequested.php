<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use App\Models\User;
use App\Notifications\Channels\SynologyChannel;
use Illuminate\Support\Facades\Log;

class BulkEquipmentRequested extends Notification
{
    use Queueable;

    protected $transactions;
    protected $submitter;

    /**
     * Create a new notification instance.
     *
     * @param Collection $transactions (Collection of Transaction models)
     * @param User $submitter (User who initiated the request)
     */
    public function __construct(Collection $transactions, User $submitter)
    {
        $this->transactions = $transactions;
        $this->submitter = $submitter;
    }

    public function via(object $notifiable): array
    {
        return ['database', SynologyChannel::class]; // ✅ Added Database
    }

    // ✅ Database Notification Structure
    public function toArray($notifiable)
    {
        $count = $this->transactions->count();
        $submitterName = $this->submitter->fullname ?? 'N/A';
        
        return [
            'title' => "มีคำขอเบิกแบบกลุ่ม ({$count} รายการ)",
            'body' => "คุณ {$submitterName} ทำรายการเบิกอุปกรณ์แบบกลุ่ม จำนวน {$count} รายการ",
            'action_url' => route('transactions.index'),
            'type' => 'info',
            'icon' => 'fas fa-layer-group'
        ];
    }

    public function toSynology(object $notifiable): void
    {
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) { return; }

        try {
            $count = $this->transactions->count();
            if ($count === 0) return;

            $submitterName = $this->submitter->fullname ?? 'N/A';
            $firstTx = $this->transactions->first();
            $dateOpened = $firstTx->transaction_date->format('d-m-Y H:i');
            $transactionUrl = route('transactions.index');
            
            // Header
            $headerText = "📢 **มีคำขอใหม่ (แบบกลุ่ม {$count} รายการ)**";
            $message = "{$headerText}\n" .
                       "👤 **ผู้ทำรายการ:** {$submitterName}\n" .
                       "📅 **วันที่:** {$dateOpened}\n" .
                       "📌 **URL:** {$transactionUrl}\n" .
                       "----------------------------------------\n";

            // List items
            foreach ($this->transactions as $index => $tx) {
                $equipmentName = $tx->equipment->name ?? 'N/A';
                $quantity = abs($tx->quantity_change);
                $unit = $tx->equipment->unit->name ?? 'ชิ้น';
                $recipientName = $tx->user->fullname ?? 'N/A';
                
                // Determine if 'behalf'
                $forText = "";
                if ($tx->user_id !== $this->submitter->id) {
                    $forText = " (เบิกให้: {$recipientName})";
                }

                $statusLabel = "🟠 Waiting";
                if ($tx->status === 'completed') {
                    $statusLabel = "🟢 Auto-Approved";
                }

                $remaining = $tx->equipment->quantity;
                // Note: If status is pending, the quantity has NOT been decremented yet.
                // If status is completed, it HAS been decremented.
                // So $remaining is always the "Current Real-time Stock in DB".
                
                $message .= ($index + 1) . ". **{$equipmentName}** x {$quantity} {$unit} (📦 {$remaining}){$forText}\n";
            }

            $payload = ['text' => $message];
            Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);

        } catch (\Exception $e) {
            Log::error('FATAL ERROR during BulkEquipmentRequested notification: ' . $e->getMessage());
        }
    }
}
