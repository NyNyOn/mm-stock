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
        elseif ($this->action === 'stock_received') {
            $title = "รับของเข้าสต๊อกแล้ว (Received)";
            $body = "รายการสินค้าถูกเพิ่มเข้าคลังเรียบร้อยแล้ว";
        }
        // ✅ NEW: Notifications for Issue Interaction
        elseif ($this->action === 'problem_report') { 
            $title = "พบปัญหาการรับของ";
            $body = "มีการแจ้งสินค้าเสียหาย/ไม่ครบ ส่งเรื่องให้จัดซื้อพิจารณาแล้ว";
        } elseif ($this->action === 'force_approve') {
            $title = "จัดซื้ออนุมัติรับของ (Force Approve)";
            $body = "PU อนุมัติให้รับของได้ทันที";
        } elseif ($this->action === 'return') {
            $title = "ยืนยันการคืนของ";
            $body = "PU แจ้งให้ดำเนินการคืนของ (ห้ามรับเข้าสต๊อก)";
        } elseif ($this->action === 'recheck') {
            $title = "ขอให้ตรวจสอบใหม่";
            $body = "PU ขอให้ตรวจสอบสินค้าอีกครั้ง (Recheck)";
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
        
        // ✅ Icon/Type Logic
        $type = 'info';
        $icon = 'fas fa-file-invoice-dollar';

        if (in_array($this->action, ['cancelled', 'rejected', 'problem_report', 'return'])) {
            $type = 'error'; // Red
            $icon = 'fas fa-exclamation-circle';
        } elseif ($this->action === 'recheck') {
            $type = 'warning'; // Yellow
            $icon = 'fas fa-sync-alt';
        } elseif ($this->action === 'force_approve' || $this->action === 'stock_received') {
            $type = 'success'; // Green
            $icon = 'fas fa-check-circle';
        }

        return [
            'title' => $title,
            'body' => $body,
            'action_url' => route('purchase-orders.index'), // Link to history/list
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
            // $status = ucfirst($this->purchaseOrder->status);
            $requester = $this->purchaseOrder->requester->fullname ?? 'N/A';
            $url = route('purchase-orders.index');

            $title = "🔔 **แจ้งเตือนใบสั่งซื้อ (PU Update)**";
            $messageBody = "มีการอัปเดตข้อมูล";

            if ($this->action === 'ordered') {
                $title = "✅ **PU ตอบรับใบสั่งซื้อแล้ว**";
                $messageBody = "PU Hub ได้รับเรื่องและออกเลข PR/PO เรียบร้อยแล้ว";
            } elseif ($this->action === 'stock_received') {
                $title = "✅ **รับของเข้าสต๊อกแล้ว (Received)**";
                $messageBody = "📦 **รายการสินค้าถูกเพิ่มเข้าคลังเรียบร้อยแล้ว**";
            } elseif ($this->action === 'shipped_from_supplier') {
                $title = "🚚 **อัปเดตสถานะ: สินค้ากำลังจัดส่ง**";
                $messageBody = "PU แจ้งว่า Supplier ได้จัดส่งสินค้าแล้ว";
            } elseif ($this->action === 'cancelled' || $this->action === 'rejected') {
                $reason = $this->purchaseOrder->pu_data['rejection_reason'] ?? 'ไม่ระบุเหตุผล';
                $rejectedBy = $this->purchaseOrder->pu_data['rejected_by'] ?? 'PU';
                
                // ✅ Check for Single Item Rejection (Phase 3)
                if (isset($this->data['item_id'])) {
                    $item = $this->purchaseOrder->items->find($this->data['item_id']);
                    if ($item) {
                        $reason = $item->rejection_reason ?? $reason;
                        // Use note from notification data if available, else item status
                        $rejectedBy = explode(' (', $this->data['note'] ?? '')[0]; // extract name from note? Or just use note.
                        // Actually, the controller sends: "note" => "ปฏิเสธโดย: Name (เหตุผล: ...)"
                         
                        // Better to just use the Note provided in data if available
                        if (!empty($this->data['note'])) {
                             // Extract Name and Reason parsed or just display the note
                        }
                    }
                    $title = "🚫 **แจ้งเตือน: รายการถูกปฏิเสธ (Item Rejected)**";
                    $messageBody = "⚠️ **มีรายการสินค้าถูกปฏิเสธ**\n**ดูรายละเอียด:** {$url}"; // Body will be enriched by item list below
                }
                // ✅ Check for Partial Rejection (Phase 1 but PO not cancelled)
                elseif ($this->purchaseOrder->status !== 'cancelled') {
                    $title = "⚠️ **แจ้งเตือน: มีรายการถูกปฏิเสธบางส่วน (Partial Rejection)**";
                     $messageBody = "⚠️ **มีสินค้าบางรายการถูกปฏิเสธ**\n**เหตุผล:** {$reason}\n👤 **โดย:** {$rejectedBy}\n💡 *กรุณาตรวจสอบและกดแก้ไขเฉพาะรายการที่ถูกปฏิเสธ*";
                } else {
                    $title = "🚫 **แจ้งเตือน: ใบสั่งซื้อถูกปฏิเสธ (Rejected)**";
                     $messageBody = "⚠️ **เหตุผล:** {$reason}\n👤 **โดย:** {$rejectedBy}\n💡 *กรุณาตรวจสอบและกดแก้ไขเพื่อส่งใหม่*";
                }
            }
            // ✅ NEW TYPES
            elseif ($this->action === 'problem_report') {
                $title = "🔴 **พบปัญหาการรับของ (Submission)**";
                $messageBody = "⚠️ **มีการแจ้งสินค้าเสียหาย/ไม่ครบ**\nสถานะ: ส่งเรื่องให้จัดซื้อพิจารณาแล้ว";
            } elseif ($this->action === 'force_approve') {
                $title = "🟢 **จัดซื้ออนุมัติรับของ (Force Approve)**";
                $note = $this->data['note'] ?? '-';
                $messageBody = "✅ **ผลการพิจารณา: อนุมัติให้รับของได้ทันที**\n📝 **Note:** {$note}";
            } elseif ($this->action === 'return') {
                $title = "⚫ **ยืนยันการคืนของ (Return)**";
                $note = $this->data['note'] ?? '-';
                $messageBody = "⛔ **คำสั่ง: ห้ามนำเข้าสต๊อก และดำเนินการส่งคืน**\n📝 **Note:** {$note}";
            } elseif ($this->action === 'recheck') {
                $title = "🟡 **ขอให้ตรวจสอบใหม่ (Re-Check)**";
                $note = $this->data['note'] ?? '-';
                $messageBody = "🔄 **ข้อความจากจัดซื้อ:** {$note}\n💡 *กรุณาตรวจสอบสินค้าอีกครั้งและกดรับใหม่*";
            }

            // ✅ Add Item Details (Adjusted for Context)
            $itemsList = "";
            $displayItems = $this->purchaseOrder->items;

            // 1. If specific item targeted (Force Approve, Return, Recheck), show ONLY that item
            if (isset($this->data['item_id'])) {
                $displayItems = $displayItems->where('id', $this->data['item_id']);
            }
            // 2. If Problem Report, show ONLY items with issues
            elseif ($this->action === 'problem_report') {
                if (isset($this->data['problem_items'])) {
                    // Use passed specific items (names are pre-resolved)
                    $displayItems = collect($this->data['problem_items']); // Collection of arrays
                } else {
                    // Fallback to scanning all issues (Legacy behavior)
                    $displayItems = $displayItems->filter(function($item) {
                         return in_array($item->status, ['cancelled', 'rejected', 'inspection_failed', 'returned']) || 
                                in_array($item->inspection_status, ['damaged', 'wrong_item', 'quality_issue']);
                    });
                }
            }
            // 3. If standard Rejection (PO level), show rejected items if any (or all if PO rejected)
            elseif ($this->action === 'cancelled' || $this->action === 'rejected') {
                 $rejectedItems = $displayItems->where('status', 'cancelled');
                 if ($rejectedItems->isNotEmpty()) {
                     $displayItems = $rejectedItems;
                 }
            }
            // 4. If Stock Received, show only received items provided in data
            elseif ($this->action === 'stock_received' && !empty($this->data['received_items'])) {
                $recItems = $this->data['received_items'];
                $itemsList = "\n📦 **รายการสินค้าที่ได้รับ: (" . count($recItems) . " รายการ)**";
                foreach ($recItems as $rItem) {
                    $rName = $rItem['name'] ?? 'Unknown';
                    $rQty = $rItem['qty'] ?? 0;
                    $itemsList .= "\n- {$rName} (x{$rQty})";
                }
                // Clear displayItems to prevent double listing below (though we can just skip the loop below)
                $displayItems = collect([]); 
            }

            if ($displayItems->count() > 0) {
                $itemsList = "\n📦 **รายการสินค้า: (" . $displayItems->count() . " รายการ)**";
                foreach ($displayItems as $item) {
                    // Check if item is Array (Data) or Object (Model)
                    if (is_array($item)) {
                        // Handle passed data (Problem Items / Received Items)
                        $name = $item['name'] ?? 'Unknown Item';
                        $reason = $item['reason'] ?? $item['status'] ?? '';
                        // Logic for passed data notes
                        $itemsList .= "\n- {$name}";
                        if ($reason) $itemsList .= " ⚠️ {$reason}";
                        
                    } else {
                        // Handle Model Object
                        $name = $item->equipment->name ?? $item->item_description ?? 'Unknown Item';
                        $qty = $item->quantity_ordered;
                        
                        // ✅ Override quantity if provided (e.g. Force Approve with specific qty)
                        if (isset($this->data['item_id']) && $item->id == $this->data['item_id'] && isset($this->data['quantity'])) {
                            $qty = $this->data['quantity'];
                        }
                        
                        // Highlight Focused Item (if provided in expected data 'item_id')
                        $focusMark = "";
                        if (isset($this->data['item_id']) && $item->id == $this->data['item_id']) {
                             $focusMark = "👉 ";
                        }
                        
                        $itemsList .= "\n- {$focusMark}{$name} (x{$qty})";
                        
                        // Show Inspection Notes/Reason if relevant
                        if (in_array($this->action, ['problem_report', 'return'])) {
                            $reasons = [
                                'damaged' => 'สินค้าเสียหาย',
                                'wrong_item' => 'สินค้าผิดรุ่น',
                                'quality_issue' => 'คุณภาพไม่ได้มาตรฐาน',
                                'incomplete' => 'ของไม่ครบ',
                                'returned' => 'ส่งคืน'
                            ];
                            $stat = $item->inspection_status ?? $item->status;
                            $reason = $reasons[$stat] ?? $stat;
                            
                            $notePart = "";
                            if ($reason) $notePart .= "⚠️ {$reason}";
                            if ($item->inspection_notes) $notePart .= " ({$item->inspection_notes})";
                            
                            if ($notePart) $itemsList .= " {$notePart}";
                        }
                        // Show Rejection Reason
                        if ($item->status === 'cancelled' && $item->rejection_reason) {
                            $itemsList .= " *({$item->rejection_reason})*";
                        }
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
