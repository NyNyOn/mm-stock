<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem; // เพิ่ม Model นี้เข้ามาเพื่อให้แน่ใจว่าเรียกใช้ได้
use App\Models\LdapUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;

use App\Http\Resources\PurchaseOrderResource;
use App\Http\Requests\Api\StorePurchaseOrderRequest;
use App\Http\Requests\Api\StorePurchaseRequestRequest;

class PurchaseOrderController extends Controller
{
    /**
     * (Outbound) Display a listing of purchase orders FROM THIS DEPARTMENT'S DB.
     * ใช้สำหรับให้ PU ดึงรายการ PO ของแผนกนี้ไปดู
     */
    public function index()
    {
        $purchaseOrders = PurchaseOrder::with(['items', 'orderedBy'])
                                      ->orderBy('created_at', 'desc')
                                      ->paginate(20);
        return PurchaseOrderResource::collection($purchaseOrders);
    }

    /**
     * (Outbound) Display the specified purchase order FROM THIS DEPARTMENT'S DB.
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        return new PurchaseOrderResource($purchaseOrder->load(['items', 'orderedBy']));
    }

    /**
     * (Inbound - Standard PO) Store a newly created purchase order IN THIS DEPARTMENT'S DB.
     * จุดรับข้อมูล PO มาตรฐานจากระบบ PU
     */
    public function store(StorePurchaseOrderRequest $request)
    {
        // 1. รับข้อมูลที่ผ่านการตรวจสอบจาก Request (Validation Rules) แล้ว
        $validated = $request->validated();
        
        Log::info("API: Received Standard PO from PU.", ['po_number' => $validated['po_number']]);

        DB::beginTransaction(); // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล
        try {
            // 2. สร้าง PO Header (หัวบิล)
            $po = PurchaseOrder::create([
                'po_number'          => $validated['po_number'],
                'ordered_by_user_id' => $validated['ordered_by_user_id'], // ID ผู้สั่งซื้อ (อิงตาม DB กลาง หรือ Mapping)
                'supplier_name'      => $validated['supplier_name'] ?? null,
                'status'             => 'ordered', // สถานะเริ่มต้นเมื่อ PU ส่งมาคือ "สั่งซื้อแล้ว"
                'ordered_at'         => $validated['order_date'],
                'type'               => 'Standard', 
                // หากมี field อื่นๆ เพิ่มเติม ใส่ตรงนี้
            ]);

            // 3. สร้างรายการสินค้า (Items)
            foreach ($validated['items'] as $itemData) {
                $po->items()->create([
                    'equipment_id'      => $itemData['equipment_id'] ?? null, // กรณีเป็นการซื้อเพื่อเติม Stock ของที่มีอยู่
                    'item_description'  => $itemData['item_name'], // ชื่อรายการสินค้า
                    'quantity_ordered'  => $itemData['quantity'],
                    'unit_name'         => $itemData['unit_name'] ?? 'ea', // หน่วยนับ (ถ้าไม่ส่งมาให้ default)
                    'unit_price'        => $itemData['unit_price'] ?? 0,
                    'status'            => 'ordered', // สถานะเริ่มต้นของสินค้า
                    'requester_id'      => $validated['ordered_by_user_id'], // ให้ Requester เป็นคนเดียวกับคนเปิด PO ไปก่อน
                ]);
            }

            DB::commit(); // ยืนยันการบันทึกข้อมูลทั้งหมด
            
            Log::info("API: Successfully created LOCAL PO #{$po->id} (Ref: {$po->po_number}).");

            // 4. ส่ง Response กลับไปหา PU
            return response()->json([
                'success' => true,
                'message' => 'Purchase Order created successfully.',
                'data' => new PurchaseOrderResource($po->load('items')),
            ], 201);

        } catch (Exception $e) {
            DB::rollBack(); // ยกเลิกการเปลี่ยนแปลงทั้งหมดหากเกิด Error
            
            Log::error("API: Failed to create Standard PO: " . $e->getMessage(), [
                'request_data' => $validated
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Purchase Order.', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * (Inbound - Custom PR) Store a newly created purchase request IN THIS DEPARTMENT'S DB.
     * รับคำขอซื้อ (PR) จากระบบอื่น เพื่อมาสร้างเป็น PO รอดำเนินการ
     */
    public function storeRequest(StorePurchaseRequestRequest $request)
    {
        $validated = $request->validated();
        Log::info("Received custom PR store request for local processing.", $validated);

        // ค้นหา User จาก DB กลาง (ถ้าจำเป็น)
        $requester = LdapUser::find($validated['requestor_user_id']); 
        if (!$requester) {
             Log::error("Requester user ID {$validated['requestor_user_id']} not found.");
             return response()->json(['message' => 'Requester user ID not found.'], 404);
        }

        DB::beginTransaction();
        try {
            $po = PurchaseOrder::create([
                'po_number'      => 'PR-' . uniqid(),
                'ordered_by_user_id' => $requester->id,
                'status'         => 'pending',
                'type'           => $validated['priority'],
            ]);
            Log::info("Created LOCAL PO #{$po->id} for PR.");

            foreach ($validated['items'] as $item) {
                $description = $item['item_name_custom'] . " (" . $item['unit_name'] . ")";
                if (!empty($item['notes'])) { $description .= " - " . $item['notes']; }

                $po->items()->create([
                    'equipment_id' => null,
                    'item_description'  => $description,
                    'quantity_ordered'     => $item['quantity'],
                    'status' => 'pending',
                    'requester_id' => $requester->id,
                ]);
            }
            DB::commit();
            
            return (new PurchaseOrderResource($po->load(['items', 'orderedBy'])))
                   ->response()
                   ->setStatusCode(201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to create PR: " . $e->getMessage());
            return response()->json(['message' => 'Failed to create Purchase Request.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * (Inbound) Receives notification from PU system that items for a PO have shipped.
     * รับแจ้งสถานะการจัดส่งจาก PU เพื่ออัปเดตสถานะ PO
     */
    public function notifyDelivery(Request $request, PurchaseOrder $purchaseOrder)
    {
        Log::info("[API notifyDelivery] Received delivery notification for LOCAL PO #{$purchaseOrder->id}.");

        // สถานะที่อนุญาตให้เปลี่ยนเป็น 'shipped' ได้
        $validPreviousStatuses = ['ordered', 'pending', 'partial_receive']; 

        if (!in_array($purchaseOrder->status, $validPreviousStatuses)) {
             Log::warning("[API notifyDelivery] Skipping PO #{$purchaseOrder->id}: Status '{$purchaseOrder->status}' invalid.");
             return response()->json([
                 'success' => true, // ส่ง success กลับไปเพื่อไม่ให้ PU retry error
                 'message' => "PO status '{$purchaseOrder->status}' is not eligible for shipment notification."
             ], 200);
        }

        DB::beginTransaction();
        try {
            // อัปเดตสถานะ PO เป็น "อยู่ระหว่างจัดส่ง"
            $purchaseOrder->update([
                'status' => 'shipped_from_supplier'
            ]);

            // อัปเดตรายการสินค้าข้างในด้วย (Optional)
            // $purchaseOrder->items()->where('status', 'ordered')->update(['status' => 'shipped']);

            DB::commit();
            
            Log::info("[API notifyDelivery] Updated PO #{$purchaseOrder->id} to shipped_from_supplier.");
            
            return response()->json([
                'success' => true,
                'message' => "PO #{$purchaseOrder->id} status updated to shipped_from_supplier."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[API notifyDelivery] Error updating PO #{$purchaseOrder->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating PO status.'
            ], 500);
        }
    }

    /**
     * (Inbound) Recieves notification from PU Hub (Phase 2 & 3).
     * Handles:
     * 1. item_status_updated (Phase 2): PO Update, Shipping Status
     * 2. item_inspection_result (Phase 3): Handling rejected items (Force Approve, Return, Recheck)
     */
    public function receiveHubNotification(Request $request)
    {
        Log::info("API: Received Hub Notification", $request->all());

        // 1. Validate Fields
        // ✅ Prioritize Secret from DB Setting (UI), Fallback to Config (.env)
        $secret = \App\Models\Setting::where('key', 'pu_api_webhook_secret')->value('value') ?? config('services.pu_hub.webhook_secret');
        
        if ($secret && $request->header('X-Hub-Secret') !== $secret) {
             Log::warning("API: Unauthorized Webhook Attempt from IP: " . $request->ip());
             return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Basic validation (event field might be new, so optional for backward compat)
        $request->validate([
            'pr_item_id' => 'required',
            'po_code'    => 'nullable',
            'pr_code'    => 'nullable',
            // 'status'     => 'required', // Status might depend on action
        ]);
        
        $eventType = $request->input('event', 'item_status_updated'); // Default to Phase 2 event
        $action = $request->input('action'); 
        $prItemId = $request->pr_item_id;

        // --------------------------------------------------------
        // Handler for PR REJECTION (Phase 3.1)
        // --------------------------------------------------------
        if ($eventType === 'pr_rejected') {
            $prCode = $request->input('pr_code');
            $poCode = $request->input('po_code');
            $reason = $request->input('reason', 'ไม่ระบุเหตุผล');
            $rejectedBy = $request->input('rejected_by', 'System');
            $rejectedItemsList = $request->input('rejected_items'); // Array of items

            Log::info("API: Received PR Rejection for PR: {$prCode} / PO: {$poCode}");

            $query = PurchaseOrder::query();
            if ($prCode) {
                $query->where('pr_number', $prCode);
            } elseif ($poCode) {
                $query->where('po_number', $poCode);
            } else {
                 return response()->json(['success' => false, 'message' => 'Missing pr_code or po_code for rejection event'], 400);
            }

            $po = $query->first();

            if ($po) {
                $puData = $po->pu_data ?? [];
                $puData['rejection_reason'] = $reason; // Main reason (or summary)
                $puData['rejected_by'] = $rejectedBy;
                $puData['rejected_at'] = now()->toDateTimeString();

                // ✅ Determine Rejection Code (Logic 4 Cases) - Main PO Code
                $mainRejectionCode = $request->input('rejection_code');
                if (!$mainRejectionCode) {
                    if (str_contains($reason, 'ไม่จำเป็น')) $mainRejectionCode = 1;
                    elseif (str_contains($reason, 'งบประมาณ')) $mainRejectionCode = 2;
                    elseif (str_contains($reason, 'ไม่ชัดเจน')) $mainRejectionCode = 3;
                    elseif (str_contains($reason, 'ทดแทน')) $mainRejectionCode = 4;
                    else $mainRejectionCode = 0;
                }
                $puData['rejection_code'] = $mainRejectionCode; 

                // --- ITEM LEVEL LOGIC ---
                if (!empty($rejectedItemsList) && is_array($rejectedItemsList)) {
                    foreach ($rejectedItemsList as $rItem) {
                        // Find item by pr_item_id (preferable) or maybe equipment/index?
                        // Assuming pr_item_id was synced previously. If not, this might fail to find item.
                        // Fallback: Try match by item_name ??
                        $itemRef = $rItem['pr_item_id'] ?? null;
                        
                        $item = null;
                        if ($itemRef) {
                            $item = $po->items()->where('pr_item_id', $itemRef)->first();
                        }
                        
                        if ($item) {
                            $item->status = 'cancelled'; // Mark item as rejected
                            $item->rejection_code = $rItem['rejection_code'] ?? $mainRejectionCode;
                            $item->rejection_reason = $rItem['reason'] ?? $reason;
                            $item->save();
                            Log::info("Item Rejected: ID {$item->id} Code {$item->rejection_code}");
                        }
                    }

                    // Check if ALL items are rejected
                    $activeItems = $po->items()->where('status', '!=', 'cancelled')->count();
                    if ($activeItems === 0) {
                        $po->status = 'cancelled';
                        $po->notes = "🚫 ถูกปฏิเสธครบทุกรายการ: {$reason}\n" . $po->notes;
                    } else {
                        // Partial Rejection
                        $po->notes = "⚠️ มีบางรายการถูกปฏิเสธ: {$reason}\n" . $po->notes;
                        // Keep PO status as is (e.g. pending/ordered) so users see active items.
                    }

                } else {
                    // --- WHOLE PO REJECTION (Legacy/Full) ---
                    $po->notes = "🚫 ถูกปฏิเสธทั้งใบ: {$reason} (Code: {$mainRejectionCode})\n" . $po->notes;
                    $po->status = 'cancelled';
                    
                    // Also mark all items as cancelled? Technically yes.
                    foreach($po->items as $item) {
                        $item->status = 'cancelled';
                        $item->rejection_code = $mainRejectionCode;
                        $item->rejection_reason = $reason;
                        $item->save();
                    }
                }

                $po->pu_data = $puData;
                $po->save();

                // 🔔 Notify Rejection
                try {
                    $po->refresh();
                    (new \App\Services\SynologyService())->notify(
                        new \App\Notifications\PurchaseOrderUpdatedNotification($po, 'rejected')
                    );
                } catch (\Exception $e) { Log::error("Notify Rejection Error: " . $e->getMessage()); }

                return response()->json(['success' => true, 'message' => 'PO designated as rejected/cancelled successfully']);
            } else {
                return response()->json(['success' => false, 'message' => 'PO not found for rejection'], 404);
            }
        }

        // --- HANDLER FOR REJECTION RESPONSES (Phase 3) ---
        if ($eventType === 'item_inspection_result') {
            Log::info("API: Processing Inspection Result Action: {$action}");

            $item = \App\Models\PurchaseOrderItem::where('pr_item_id', $prItemId)->first();
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Item not found'], 404);
            }

            DB::beginTransaction();
            try {
                switch ($action) {
                    case 'force_approve': // 1. Force Approve (User overrides rejection)
                        // Objective: Accept item into stock despite previous rejection.
                        // Logic: Add stock, Log Transaction, Update Status.
                        
                        $qtyToReceive = $item->quantity_ordered - $item->quantity_received;
                        if ($qtyToReceive <= 0) $qtyToReceive = 1; // Fallback if data sync issue

                        // Find Equipment
                        $equipment = $item->equipment_id ? \App\Models\Equipment::find($item->equipment_id) : null;
                        
                        // Update Stock
                        if ($equipment) {
                            $equipment->quantity += $qtyToReceive;
                            $equipment->save();

                            // Create Transaction Log
                            \App\Models\Transaction::create([
                                'equipment_id'    => $equipment->id,
                                'user_id'         => 1, // System User
                                'handler_id'      => 1,
                                'type'            => 'receive',
                                'quantity_change' => $qtyToReceive,
                                'notes'           => "Accepted by PU (Force Approve) - PO {$item->purchaseOrder->po_number}",
                                'transaction_date'=> now(),
                                'status'          => 'completed',
                            ]);
                        }

                        // Update Item Status
                        $item->quantity_received = $item->quantity_ordered; // Full receive
                        $item->inspection_status = 'pass';
                        $item->status = 'received';
                        $item->inspection_notes = "Force Approved by PU ({$request->inspector})";
                        $item->save();
                        
                        Log::info("API: Force Approved Item #{$item->id}. Stock added: {$qtyToReceive}");
                        break;

                    case 'return': // 2. Return (User confirms return)
                        // Objective: Item goes back. Unlink/Cleanup.
                        $item->status = 'returned'; // Special status to hide/archive
                        $item->inspection_notes = "Returned to Supplier by PU ({$request->inspector})";
                        // Note: Cannot set purchase_order_id to NULL (DB Constraint). 
                        // We leave it linked to old PO until new PO Webhook arrives to "adopt" it.
                        $item->save();
                        
                        Log::info("API: Returned Item #{$item->id}. Waiting for new PO alignment.");
                        break;

                    case 'inspection_recheck': // Alias from actual PU log
                    case 'recheck': // 3. Re-Check (User requests re-inspection)
                        // Objective: Reset status to allow inspector to check again.
                        $item->inspection_status = null;
                        $item->inspection_notes = null;
                        // $item->issue_qty_handled = null; 
                        // Ensure main status allows showing in Receive page
                        if ($item->status == 'received') {
                             $item->status = 'shipped_from_supplier'; // Revert to shipped
                             // WARNING: If stock was added, this is messy. But usually only comes from rejection state (no stock added).
                        }
                        $item->save();
                        Log::info("API: Reset Item #{$item->id} for Re-Check.");
                        break;
                        
                    default:
                        Log::warning("API: Unknown action '{$action}' for inspection result.");
                }
                
                DB::commit();
                return response()->json(['success' => true, 'message' => "Action '{$action}' processed successfully."]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("API: Action failed: " . $e->getMessage());
                return response()->json(['success' => false, 'message' => 'Internal Server Error'], 500);
            }
        }


        // --- EXISTING LOGIC (Phase 2 - Status Updates) ---
        // 2. Logic อัปเดตสถานะในฝั่ง MM (Improved Matching)
    $po = null;

    // A. Try matching by PO Number (if provided)
    if (!empty($request->po_code)) {
        $po = PurchaseOrder::where('po_number', $request->po_code)->first();
    }

    // B. Try matching by PR Number (if not found yet and pr_code provided)
    if (!$po && !empty($request->pr_code)) {
        $po = PurchaseOrder::where('pr_number', $request->pr_code)->first();
    }

    // C. Try matching by Item ID (Reliable fallback if we saved pr_item_id)
    if (!empty($request->pr_item_id)) {
        $item = \App\Models\PurchaseOrderItem::where('pr_item_id', $request->pr_item_id)->first();
        if ($item) {
            
            // ✅ DETECT PO CHANGE (Item Moved to New PO)
            // If PU sends a PO Code that is different from what the item currently has -> Move it!
            if (!empty($request->po_code) && $item->purchaseOrder && $item->purchaseOrder->po_number !== $request->po_code) {
                Log::info("API: Item #{$item->id} (PR Item {$request->pr_item_id}) indicates new PO Code '{$request->po_code}'. Processing Move...");
                
                // Find OK target PO or Create it? 
                // Usually we expect the PO to exist or be created via payload. 
                // If $po (found by A) exists, use it. If not, we might need to rely on the 'create if missing' logic below (if any) or just find strictly.
                
                $targetPO = PurchaseOrder::where('po_number', $request->po_code)->first();
                if ($targetPO) {
                    $oldPOId = $item->purchase_order_id;
                    $item->purchase_order_id = $targetPO->id;
                    $item->save();
                    Log::info("API: Moved Item #{$item->id} from PO #{$oldPOId} to PO #{$targetPO->id} ({$targetPO->po_number}).");
                    $po = $targetPO; // Set current context to new PO
                } else {
                    Log::warning("API: New PO Code '{$request->po_code}' not found in system via PO Number. Cannot move item yet.");
                    // Fallback: If we can't find the new PO, we might use the old one for now, or create?
                    // Let's stick to old one but Log warning.
                    $po = $item->purchaseOrder;
                }

            } else {
                 $po = $item->purchaseOrder;
            }

            
             // ✅ RESET INSPECTION STATUS (Logic moved to 'recheck' action above, but kept here for legacy/simple updates)
            if ($request->status == 'shipped_from_supplier' || $request->status == 'arrived_at_hub') {
                 // Only reset if previously inspected/rejected to allow retry if PU sends update?
                 // But 'recheck' action is now the formal way.
                 // let's keep this as backup for Phase 2 general updates.
                 if ($item->inspection_status || $item->status == 'returned') {
                      $item->inspection_status = null;
                      $item->inspection_notes = null;
                      $item->status = 'shipped_from_supplier'; // ✅ Undo 'returned' status so it shows up in Receive Page again
                      $item->save();
                 }
            }
        }
    }

    if ($po) {
        // ✅ sync fields strict update: Always update if PU sends them
        if (!empty($request->po_code)) {
            $po->po_number = $request->po_code;
        }
        if (!empty($request->pr_code)) {
            $po->pr_number = $request->pr_code;
        }

        // Update Status based on payload
        // If status is 'arrived_at_hub' or 'shipped_from_supplier', we consider it en route.
        // Or if 'ordered', it's ordered.
        
        // Map status if needed, otherwise use what they sent or default to 'shipped_from_supplier'
        // User previously wanted it to show in Receive page, so 'shipped_from_supplier' is appropriate for 'arrived_at_hub'.
        
        $newStatus = $request->status;
        if ($newStatus == 'arrived_at_hub') {
            $newStatus = 'shipped_from_supplier'; // Map to our system status
        } elseif ($newStatus == 'ordered') {
            $newStatus = 'ordered'; 
        } 
        
        // Only update status if it's advancing (optional, but good practice). 
        // For now, valid statuses for us are: pending, ordered, shipped_from_supplier, partial_receive, contact_vendor, completed
        
        // ✅ BUG FIX: Don't revert 'completed' status
        if ($po->status !== 'completed') {
            if (in_array($newStatus, ['shipped_from_supplier', 'partial_receive', 'contact_vendor', 'ordered'])) {
                 $po->status = $newStatus;
            }
        } else {
             Log::info("API: Skipping status update for PO #{$po->id} because it is already COMPLETED.");
        }

        $po->save();
        Log::info("API: Updated PO #{$po->id} (Ref: {$request->po_code}/{$request->pr_code}) to '{$po->status}'");
        
        // ✅ Notify User (Synology) about Status Update / PO Number Assignment
        try {
            // Re-load to ensure we have latest data (po_number, etc.)
            $po->refresh();
            (new \App\Services\SynologyService())->notify(
                new \App\Notifications\PurchaseOrderUpdatedNotification($po, $po->status)
            );
        } catch (\Exception $e) { Log::error("Webhook Notification Error: " . $e->getMessage()); }
        
        return response()->json(['success' => true, 'message' => 'Notification processed', 'po_id' => $po->id]);

    } else {
        Log::warning("API: PO not found for code: " . ($request->po_code ?? $request->pr_code ?? 'N/A') . " / Item: " . $request->pr_item_id);
        return response()->json(['success' => false, 'message' => 'PO/PR Reference not found'], 404);
    }
    }
}