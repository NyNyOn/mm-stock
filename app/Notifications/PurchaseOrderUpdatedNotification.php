<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\PurchaseOrder;
use App\Notifications\Channels\SynologyChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PurchaseOrderUpdatedNotification extends Notification
{
    use Queueable;

    protected $purchaseOrder;
    protected $action;
    protected $data;

    /**
     * Create a new notification instance.
     *
     * @param PurchaseOrder $purchaseOrder
     * @param string $action 'ordered' (Info received), 'shipped' (Status update), etc.
     * @param array $data Extra data (like PR/PO codes)
     */
    public function __construct(PurchaseOrder $purchaseOrder, string $action = 'updated', array $data = [])
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->action = $action;
        $this->data = $data;
    }

    public function via($notifiable): array
    {
        if ($notifiable instanceof \App\Services\SynologyService) {
            return [SynologyChannel::class];
        }
        return ['database']; 
    }

    // ✅ Database Notification Structure
    public function toArray($notifiable)
    {
        $poNumber = $this->purchaseOrder->po_number ?? '-';
        // $status = ucfirst($this->purchaseOrder->status);
        $status = $this->purchaseOrder->status_label;
        
        $title = "PU อัปเดตข้อมูล";
        $body = "มีการอัปเดตข้อมูลใบสั่งซื้อ #{$this->purchaseOrder->id}";

        if ($this->action === 'ordered') {
            $title = "ตอบรับใบสั่งซื้อแล้ว";
            $body = "PU ออกเลข PR/PO เรียบร้อยแล้ว (PO: {$poNumber})";
        } elseif ($this->action === 'shipped_from_supplier') {
            $title = "สินค้ากำลังจัดส่ง";
            $body = "PU แจ้งว่าสินค้ากำลังเดินทางมาส่ง";
        } elseif ($this->action === 'cancelled' || $this->action === 'rejected') {
            $title = "ใบสั่งซื้อถูกปฏิเสธ";
            $reason = $this->purchaseOrder->pu_data['rejection_reason'] ?? 'ไม่ระบุเหตุผล';
            $body = "PU ปฏิเสธรายการนี้: {$reason}";
        }

        // ✅ Add Summary of Items to Body
        $itemCount = $this->purchaseOrder->items->count();
        if ($itemCount > 0) {
            $firstItem = $this->purchaseOrder->items->first();
            $itemName = $firstItem->equipment->name ?? $firstItem->item_description ?? 'สินค้า';
            $itemQty = $firstItem->quantity_ordered;
            
            $moreText = $itemCount > 1 ? " และอื่นๆ รวม {$itemCount} รายการ" : "";
            $body .= "\n📦 {$itemName} (x{$itemQty}){$moreText}";
        }
        
        $type = ($this->action === 'cancelled' || $this->action === 'rejected') ? 'error' : 'info';
        $icon = ($this->action === 'cancelled' || $this->action === 'rejected') ? 'fas fa-ban' : 'fas fa-file-invoice-dollar';

        return [
            'title' => $title,
            'body' => $body,
            'action_url' => route('purchase-orders.index'),
            'type' => $type, 
            'icon' => $icon
        ];
    }

    public function toSynology(object $notifiable): void
    {
        $webhookUrl = config('services.synology.chat_webhook_url');
        if (!$webhookUrl) { 
            Log::warning('Synology Webhook URL not configured in Notification.');
            return; 
        }

        // ✅ Robust Clean URL (Copied from CheckLowStockAndNotifyPU)
        $webhookUrl = str_replace(['"', "'", '%22'], '', $webhookUrl);

        try {
            $poId = $this->purchaseOrder->id;
            $poNumber = $this->purchaseOrder->po_number ?? '-';
            $prNumber = $this->purchaseOrder->pr_number ?? '-';
            $status = ucfirst($this->purchaseOrder->status);
            $requester = $this->purchaseOrder->requester->fullname ?? 'N/A';
            $url = route('purchase-orders.index');

            $title = "🔔 **แจ้งเตือนใบสั่งซื้อ (PU Update)**";

            if ($this->action === 'ordered') {
                $title = "✅ **PU ตอบรับใบสั่งซื้อแล้ว**";
                $messageBody = "PU Hub ได้รับเรื่องและออกเลข PR/PO เรียบร้อยแล้ว";
            } elseif ($this->action === 'shipped_from_supplier') {
                $title = "🚚 **อัปเดตสถานะ: สินค้ากำลังจัดส่ง**";
                $messageBody = "PU แจ้งว่า Supplier ได้จัดส่งสินค้าแล้ว";
            } elseif ($this->action === 'cancelled' || $this->action === 'rejected') {
                $reason = $this->purchaseOrder->pu_data['rejection_reason'] ?? 'ไม่ระบุเหตุผล';
                $rejectedBy = $this->purchaseOrder->pu_data['rejected_by'] ?? 'PU';
                
                // ✅ Check for Partial Rejection
                if ($this->purchaseOrder->status !== 'cancelled') {
                    $title = "⚠️ **แจ้งเตือน: มีรายการถูกปฏิเสธบางส่วน (Partial Rejection)**";
                     $messageBody = "⚠️ **มีสินค้าบางรายการถูกปฏิเสธ**\n**เหตุผล:** {$reason}\n👤 **โดย:** {$rejectedBy}\n💡 *กรุณาตรวจสอบและกดแก้ไขเฉพาะรายการที่ถูกปฏิเสธ*";
                } else {
                    $title = "🚫 **แจ้งเตือน: ใบสั่งซื้อถูกปฏิเสธ (Rejected)**";
                     $messageBody = "⚠️ **เหตุผล:** {$reason}\n👤 **โดย:** {$rejectedBy}\n💡 *กรุณาตรวจสอบและกดแก้ไขเพื่อส่งใหม่*";
                }
            } else {
                 $messageBody = "มีการอัปเดตข้อมูลใบสั่งซื้อจาก PU";
            }

            // ✅ Add Item Details (Name + Qty + Status)
            $itemsList = "";
            if ($this->purchaseOrder->items->count() > 0) {
                $itemsList = "\n📦 **รายการสินค้า:**";
                foreach ($this->purchaseOrder->items as $item) {
                    $name = $item->equipment->name ?? $item->item_description ?? 'Unknown Item';
                    $qty = $item->quantity_ordered;
                    
                    // Mark Rejected Items
                    $statusMark = "";
                    if ($item->status === 'cancelled') {
                         $statusMark = "❌ **(ถูกปฏิเสธ)** ";
                    }
                    
                    $itemsList .= "\n- {$statusMark}{$name} (x{$qty})";
                    if ($item->status === 'cancelled' && $item->rejection_reason) {
                        $itemsList .= " *({$item->rejection_reason})*";
                    }
                }
            }

            $message = "{$title}\n" .
                       "{$messageBody}\n" .
                       "{$itemsList}\n" . 
                       "🆔 **ID:** #{$poId}\n" .
                       "🔖 **PO No:** {$poNumber}\n" .
                       "📄 **PR No:** {$prNumber}\n" .
                       "👤 **ผู้ขอ:** {$requester}\n" .
                       "📊 **สถานะปัจจุบัน:** {$this->purchaseOrder->status_label}\n" .
                       "📌 **URL:** {$url}";
            
            $payload = ['text' => $message];
            
            Log::info("Sending Notification to Synology for PO #{$poId}...", ['url' => $webhookUrl]);

            $response = Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);
            
            if (!$response->successful()) {
                Log::error("Synology Notification Failed: " . $response->body());
            } else {
                Log::info("Synology Notification Sent Successfully.");
            }

        } catch (\Exception $e) {
            Log::error('PurchaseOrderUpdated Notification Error: ' . $e->getMessage());
        }
    }
}
