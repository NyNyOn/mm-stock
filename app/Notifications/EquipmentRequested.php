<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use App\Models\Transaction;
use App\Notifications\Channels\SynologyChannel;
use Illuminate\Support\Facades\Log;
// ✅✅✅ 1. เพิ่ม Use Statement สำหรับ User Model ✅✅✅
use App\Models\User; 

// ✅ ลบ implements ShouldQueue ออกจากบรรทัดนี้
class EquipmentRequested extends Notification
{
    use Queueable;

    protected $transaction;
    // ✅✅✅ 2. เพิ่มตัวแปรสำหรับเก็บ "ผู้กด" (Submitter) ✅✅✅
    protected $submitter;

    /**
     * Create a new notification instance.
     *
     * @param Transaction $transaction (ข้อมูลรายการ)
     * @param User $submitter (ผู้ที่ล็อกอินและกดส่ง)
     */
    // ✅✅✅ 3. แก้ไข Constructor ให้รับ User $submitter เพิ่ม ✅✅✅
    public function __construct(Transaction $transaction, User $submitter)
    {
        $this->transaction = $transaction;
        $this->submitter = $submitter; // <-- เก็บค่าผู้กด
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
            // --- ข้อมูลพื้นฐาน (จากโค้ดของคุณ) ---
            $transactionId = $this->transaction->id;
            $equipmentName = $this->transaction->equipment->name ?? 'N/A'; // (ป้องกัน Error ถ้า equipment ถูกลบ)
            $dateOpened = $this->transaction->transaction_date->format('d-m-Y H:i');
            $transactionUrl = route('transactions.index');
            $quantity = abs($this->transaction->quantity_change);
            $unit = $this->transaction->equipment->unit->name ?? 'ชิ้น';

            // ✅✅✅ 4. แก้ไข Logic การสร้างข้อความ (ใช้ $submitter) ✅✅✅
            
            // ข้อมูลผู้ใช้ (ใหม่)
            $recipientName = $this->transaction->user->fullname ?? 'N/A'; // ผู้รับของ (user_id)
            $submitterName = $this->submitter->fullname ?? 'N/A';    // ผู้กด (loggedInUser)

            $message = "";

            // ตรวจสอบว่าผู้กด กับ ผู้รับ เป็นคนเดียวกันหรือไม่
            if ($this->submitter->id === $this->transaction->user_id) {
                // --- กรณีที่ 1: เบิกให้ตัวเอง ---
                $message = "📢 **มีคำขอใหม่ใน WH Stock Pro**\n" .
                           "🎫 **Transaction ID:** {$transactionId}\n" .
                           "📝 **อุปกรณ์:** {$equipmentName} (จำนวน: {$quantity} {$unit})\n" .
                           "👤 **ผู้ขอ:** {$recipientName}\n" .
                           "📅 **วันที่ขอ:** {$dateOpened}\n" .
                           "📊 **สถานะ:** 🟠 Pending\n" .
                           "📌 **URL:** {$transactionUrl}";
            } else {
                // --- กรณีที่ 2: เบิกให้คนอื่น (ตามที่คุณต้องการ) ---
                $message = "📢 **มีคำขอใหม่ (เบิกให้ผู้อื่น)**\n" .
                           "🎫 **Transaction ID:** {$transactionId}\n" .
                           "👤 **ผู้เบิก (ผู้กด):** {$submitterName}\n" .
                           "👤 **เบิกให้กับ:** {$recipientName}\n" .
                           "📝 **อุปกรณ์:** {$equipmentName} (จำนวน: {$quantity} {$unit})\n" .
                           "📅 **วันที่ขอ:** {$dateOpened}\n" .
                           "📊 **สถานะ:** 🟠 Pending\n" .
                           "📌 **URL:** {$transactionUrl}";
            }
            // ✅✅✅ END: 4. แก้ไข Logic การสร้างข้อความ ✅✅✅
            
            $payload = ['text' => $message];
            Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);

        } catch (\Exception $e) {
            Log::error('FATAL ERROR during EquipmentRequested notification: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
        }
    }
}