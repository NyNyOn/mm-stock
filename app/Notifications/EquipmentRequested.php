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

    public function via($notifiable): array
    {
        // If notifying SynologyService, use only SynologyChannel
        if ($notifiable instanceof \App\Services\SynologyService) {
            return [SynologyChannel::class];
        }
        
        // Default to database for Users
        return ['database'];
    }

    // ✅ Database Notification Structure
    public function toArray($notifiable)
    {
        $equipmentName = $this->transaction->equipment->name ?? 'N/A';
        $quantity = abs($this->transaction->quantity_change);
        
        $title = "มีคำขอเบิกใหม่";
        $body = "คุณ {$this->submitter->fullname} ขอเบิก '{$equipmentName}' จำนวน {$quantity} ชิ้น";

        if ($this->transaction->status === 'completed') {
            $title = "เบิกสำเร็จ (Auto-Approved)";
            $body = "คุณ {$this->submitter->fullname} เบิก '{$equipmentName}' สำเร็จ (อนุมัติอัตโนมัติ)";
        }

        return [
            'title' => $title,
            'body' => $body,
            'action_url' => route('transactions.index'),
            'type' => 'info',
            'icon' => 'fas fa-clipboard-list'
        ];
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
            $remaining = $this->transaction->equipment->quantity;
            $unit = $this->transaction->equipment->unit->name ?? 'ชิ้น';

            // ✅✅✅ 4. แก้ไข Logic การสร้างข้อความ (ใช้ $submitter) ✅✅✅
            
            // ข้อมูลผู้ใช้ (ใหม่)
            $recipientName = $this->transaction->user->fullname ?? 'N/A'; // ผู้รับของ (user_id)
            $submitterName = $this->submitter->fullname ?? 'N/A';    // ผู้กด (loggedInUser)

            $message = "";

            // ตรวจสอบสถานะรายการ
            $statusLabel = "🟠 รออนุมัติ (Pending)";
            $headerText = "📢 **มีคำขอใหม่ใน WH Stock Pro**";

            if ($this->transaction->status === 'completed') {
                $statusLabel = "🟢 อนุมัติแล้ว (Auto-Approved)";
                $headerText = "✅ **เบิกสำเร็จ (Auto-Approved)**";
            }

            // ✅ Prepare Stock Info String (Show logic: only if completed)
            $stockInfo = "📉 **เบิก:** {$quantity} {$unit}";
            if ($this->transaction->status === 'completed') {
                 $stockInfo .= " | 📦 **คงเหลือ:** {$remaining} {$unit}";
            }

            // ตรวจสอบว่าผู้กด กับ ผู้รับ เป็นคนเดียวกันหรือไม่
            if ($this->submitter->id === $this->transaction->user_id) {
                // --- กรณีที่ 1: เบิกให้ตัวเอง ---
                $message = "{$headerText}\n" .
                           "🎫 **รหัสรายการ:** {$transactionId}\n" .
                           "📝 **อุปกรณ์:** {$equipmentName}\n" .
                           "{$stockInfo}\n" .
                           "👤 **ผู้เบิก:** {$recipientName}\n" .
                           "📅 **วันที่:** {$dateOpened}\n" .
                           "📊 **สถานะ:** {$statusLabel}\n" .
                           "📌 **URL:** {$transactionUrl}";
            } else {
                // --- กรณีที่ 2: เบิกให้คนอื่น ---
                $message = "{$headerText} (เบิกแทน)\n" .
                           "🎫 **รหัสรายการ:** {$transactionId}\n" .
                           "👤 **ผู้ทำรายการ:** {$submitterName}\n" .
                           "👤 **ผู้รับของ:** {$recipientName}\n" .
                           "📝 **อุปกรณ์:** {$equipmentName}\n" .
                           "{$stockInfo}\n" .
                           "📅 **วันที่:** {$dateOpened}\n" .
                           "📊 **สถานะ:** {$statusLabel}\n" .
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