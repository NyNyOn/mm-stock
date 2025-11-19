<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Transaction;
use App\Notifications\Channels\SynologyChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RequestApproved extends Notification
{
    use Queueable;

    protected $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function via(object $notifiable): array
    {
        // This notification uses the SynologyChannel defined in the SynologyService
        // But Laravel's standard notification system calls this on the Notification itself.
        // The SynologyService usage bypasses the standard $notifiable->notify() flow slightly.
        // We still need to return the channel here for clarity, though it might not be strictly used
        // by the SynologyService->notify() call depending on its internal implementation.
        return [SynologyChannel::class];
    }

    /**
     * Get the Synology Chat representation of the notification.
     *
     * @param object $notifiable This will actually be the SynologyService instance when called via (new SynologyService())->notify(...)
     * @return void
     */
    public function toSynology(object $notifiable): void
    {
        // Get webhook URL directly using config(), $notifiable is SynologyService here
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) {
            Log::error('Synology Chat webhook URL not configured in config/services.php or .env');
            return;
        }

        try {
            // ✅✅✅ START: แก้ไขจุดนี้ ✅✅✅
            // ดึง User จาก Transaction ที่เราเก็บไว้ ไม่ใช่จาก $notifiable
            $user = $this->transaction->user;
            if (!$user) {
                Log::error("RequestApproved notification: Cannot find user for Transaction ID {$this->transaction->id}");
                return; // Exit if user relation is not loaded or missing
            }
            // ใช้ $user->username แทน $notifiable->username
            $requesterName = $user->username;
            // ✅✅✅ END: สิ้นสุดการแก้ไข ✅✅✅

            $equipmentName = $this->transaction->equipment->name ?? 'N/A'; // Use null coalescing
            $transactionUrl = route('user.equipment.index'); // Link to user's equipment page
            $message = "👍 **คำขออนุมัติแล้ว (ถึง @{$requesterName})**\n" .
                       "📝 **อุปกรณ์:** {$equipmentName}\n" .
                       "🚚 **สถานะ:** กำลังจัดส่ง\n" .
                       "*กรุณากดยืนยันในระบบเมื่อได้รับของ*\n" .
                       "📌 **URL:** {$transactionUrl}";

            $payload = ['text' => $message];
            // Send the notification via HTTP POST
            $response = Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);

            // Log if the request to Synology failed
            if (!$response->successful()) {
                 Log::error("Failed to send Synology notification for RequestApproved TXN ID {$this->transaction->id}. Status: " . $response->status() . " Body: " . $response->body());
            } else {
                 Log::info("Successfully sent Synology notification for RequestApproved TXN ID {$this->transaction->id}");
            }

        } catch (\Exception $e) {
            // Log any other exception during the process
            Log::error("FATAL ERROR during RequestApproved notification build/send for TXN ID {$this->transaction->id}: " . $e->getMessage());
        }
    }
}
