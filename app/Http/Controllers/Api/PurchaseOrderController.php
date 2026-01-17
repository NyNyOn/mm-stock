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

                // ✅ Add Log Entry for History View
                $history = $puData['history'] ?? [];
                $history[] = [
                    'event' => 'Rejected',
                    'reason' => $reason,
                    'at' => now()->toIso8601String()
                ];
                $puData['history'] = $history; 
                
                // ✅ Normalization: Support Single Item Payload (from PU Log)
                if (empty($rejectedItemsList) && $request->has('pr_item_id')) {
                    $rejectedItemsList = [[
                        'pr_item_id' => $request->input('pr_item_id'),
                        'rejection_code' => $request->input('rejection_code') ?? $request->input('reason_code') ?? $mainRejectionCode,
                        'reason' => $request->input('reason')
                    ]];
                }

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
                        
                        // ✅ Fallback: Match by description if ID not found (Robustness)
                        if (!$item && !empty($rItem['reason']) && str_contains($rItem['reason'], 'Failed to match')) {
                             // Contextual fallback not easy without item name.
                        }
                        // Note: If payload includes item name, we could use it. 
                        // Assuming payload might have 'item_name' in future.
                        if (!$item && !empty($rItem['item_name'])) {
                             $item = $po->items()->where('item_description', $rItem['item_name'])->first();
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

                // 🔔 Notify Rejection (Synology + In-App)
                try {
                    $po->refresh();
                    $notification = new \App\Notifications\PurchaseOrderUpdatedNotification($po, 'rejected');

                    // 1. Synology
                    (new \App\Services\SynologyService())->notify($notification);

                    // 2. Database (In-App) - Notify Users with Permission
                    // Target: Users with 'po:view' or 'po:manage' OR the Requester
                    $notifiableUsers = \App\Models\User::permission(['po:view', 'po:manage'])->get();
                    if ($po->requester) {
                        $notifiableUsers = $notifiableUsers->merge([$po->requester]);
                    }
                    \Illuminate\Support\Facades\Notification::send($notifiableUsers->unique('id'), $notification);

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
                    case 'force_approve': // 1. Force Approve (PU overrides - Auto Receive)
                        // Objective: Immediately add stock and mark as successful.
                        
                        $qtyOrdered = $item->quantity_ordered;
                        $qtyReceived = $item->quantity_received;
                        
                        // Check if PU sent specific quantity, otherwise use remaining
                        $qtyToReceive = $request->input('quantity', $request->input('approved_quantity', ($qtyOrdered - $qtyReceived)));
                        
                        if ($qtyToReceive > 0) {
                            $equipment = $item->equipment;
                            if ($equipment) {
                                $equipment->quantity += $qtyToReceive;
                                $equipment->save();
                                
                                // Create Transaction Log
                                \App\Models\Transaction::create([
                                    'equipment_id'    => $equipment->id,
                                    'user_id'         => $po->ordered_by_user_id ?? 1, // Fallback to Owner or Admin
                                    'handler_id'      => null, // System Action
                                    'type'            => 'receive',
                                    'quantity_change' => $qtyToReceive,
                                    'notes'           => "Force Approved by PU ({$request->inspector}) - Auto Received",
                                    'transaction_date'=> now(),
                                    'status'          => 'completed',
                                    'admin_confirmed_at' => now(),
                                    'confirmed_at' => now(),
                                ]);
                            }
                            
                            $item->quantity_received += $qtyToReceive;
                        }
                        
                        $item->inspection_status = 'pass';
                        $item->status = ($item->quantity_received >= $qtyOrdered) ? 'received' : 'partial_receive';
                        $item->rejection_code = null;
                        $item->rejection_reason = null;
                        $item->inspection_notes = "Force Approved by PU ({$request->inspector})";
                        $item->save();
                        
                        Log::info("API: Auto-Received Item #{$item->id} (Qty: {$qtyToReceive}) via Force Approve.");
                        
                        // ✅ Check PO Completion
                        $po = $item->purchaseOrder;
                        $pendingCount = $po->items()
                            ->whereRaw('ifnull(quantity_received, 0) < quantity_ordered')
                            ->whereNotIn('status', ['returned', 'inspection_failed', 'cancelled', 'rejected'])
                            ->count();
                            
                        if ($pendingCount == 0) {
                            $po->status = 'completed';
                            $po->save();
                            Log::info("API: Set PO #{$po->id} to 'completed' after Force Approve.");
                        } else {
                            // If partially done, ensure it's 'partial_receive' logic
                            if ($po->status == 'ordered' || $po->status == 'pending') {
                                $po->status = 'partial_receive';
                                $po->save();
                            }
                        }
                        
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
                        
                        // Force status to something NOT cancelled/returned
                        $item->status = 'pending_inspection'; 
                        $item->save();

                        // ✅ Ensure PO is visible in Receive List
                        // ReceiveController filters for: shipped_from_supplier, partial_receive, contact_vendor
                        // ✅ Ensure PO is visible in Receive List
                        // ReceiveController filters for: shipped_from_supplier, partial_receive, contact_vendor
                        $po = $item->purchaseOrder;
                        if (in_array($po->status, ['ordered', 'cancelled', 'pending', 'completed', 'partial_receive'])) {
                            if ($po->status == 'completed' || $po->status == 'cancelled') {
                                $po->status = 'partial_receive'; 
                                $po->save();
                                Log::info("API: Re-Opened PO #{$po->id} (Status: partial_receive) for Re-Check.");
                            }
                            elseif ($po->status == 'ordered' || $po->status == 'pending') {
                                $po->status = 'shipped_from_supplier';
                                $po->save();
                            }
                        }

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
            
            // ✅ DETECT PO CHANGE (Item Moved to New PO - SPLITTING)
            // Logic:
            // 1. If Current PO has NO Code -> Just Assign (Handled by $po update below)
            // 2. If Current PO HAS Code AND it differs from Webhook -> Move Item (Split)
            $currentPoCode = $item->purchaseOrder->po_number ?? null;
            $newPoCode = $request->po_code;
            
            if (!empty($newPoCode) && $currentPoCode && $currentPoCode !== $newPoCode) {
                Log::info("API: Item #{$item->id} (PR Item {$request->pr_item_id}) indicates new PO Code '{$newPoCode}' (Current: {$currentPoCode}). Processing Move/Split...");
                
                // 1. Check if Target PO already exists
                $targetPO = PurchaseOrder::where('po_number', $newPoCode)->first();
                
                if (!$targetPO) {
                    // 2. If NOT exists -> Duplicate (Split) from Original PO
                    Log::info("API: Target PO '{$newPoCode}' not found. Creating new Split PO from #{$item->purchase_order_id}...");
                    
                    $originalPO = $item->purchaseOrder;
                    $targetPO = $originalPO->replicate(); 
                    
                    // Set New Details
                    $targetPO->po_number = $newPoCode;
                    $targetPO->status = 'ordered'; // Default status for new split PO
                    // Reset created/updated timestamps will be handled by Eloquent, but logic:
                    $targetPO->ordered_at = now();
                    $targetPO->pu_data = array_merge($originalPO->pu_data ?? [], ['split_from' => $originalPO->po_number, 'split_at' => now()->toIso8601String()]);
                    $targetPO->save();
                    
                    Log::info("API: Created Split PO #{$targetPO->id} (PO: {$newPoCode} / PR: {$targetPO->pr_number})");
                }

                // 3. Move Item to Target PO
                $oldPOId = $item->purchase_order_id;
                $item->purchase_order_id = $targetPO->id;
                $item->save();
                
                Log::info("API: Moved Item #{$item->id} from PO #{$oldPOId} to PO #{$targetPO->id} ({$targetPO->po_number}).");
                
                // Switch context to New PO
                $po = $targetPO; 

            } else {
                 // First assignment or Same Code -> Use current PO, will be updated below
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
            if (in_array($newStatus, ['shipped_from_supplier', 'partial_receive', 'contact_vendor', 'ordered', 'rejected', 'cancelled'])) {
                 $po->status = $newStatus;
                 
                 // If PO is cancelled/rejected, mark all pending items as cancelled too
                 if (in_array($newStatus, ['rejected', 'cancelled'])) {
                     foreach($po->items as $item) {
                         if ($item->status != 'received') {
                             $item->status = 'cancelled';
                             $item->save();
                         }
                     }
                 }
            }
        } else {
             Log::info("API: Skipping status update for PO #{$po->id} because it is already COMPLETED.");
        }

        $po->save();
        Log::info("API: Updated PO #{$po->id} (Ref: {$request->po_code}/{$request->pr_code}) to '{$po->status}'");
        
        // ✅ Notify User (Synology + In-App) about Status Update / PO Number Assignment
        try {
            // Re-load to ensure we have latest data (po_number, etc.)
            $po->refresh();
            $notification = new \App\Notifications\PurchaseOrderUpdatedNotification($po, $po->status);

            // 1. Synology
            (new \App\Services\SynologyService())->notify($notification);

            // 2. Database (In-App)
            // 2. Database (In-App)
            // Fix: User::permission() scope not available. Manually find users with permission.
            $permissionNames = ['po:view', 'po:manage'];
            
            // Get Permission IDs
            $permIds = DB::connection('mysql')->table('permissions')
                ->whereIn('name', $permissionNames)
                ->pluck('id');
                
            // Get Group IDs having these permissions
            $groupIds = DB::connection('mysql')->table('group_permissions')
                ->whereIn('permission_id', $permIds)
                ->pluck('user_group_id');
                
            // Get User IDs in these groups
            $userIds = DB::connection('mysql')->table('service_user_roles')
                ->whereIn('group_id', $groupIds)
                ->pluck('user_id');
                
            $notifiableUsers = \App\Models\User::whereIn('id', $userIds)->get();
            if ($po->requester) {
                $notifiableUsers = $notifiableUsers->merge([$po->requester]);
            }
            \Illuminate\Support\Facades\Notification::send($notifiableUsers->unique('id'), $notification);

        } catch (\Exception $e) { Log::error("Webhook Notification Error: " . $e->getMessage()); }
        
        return response()->json(['success' => true, 'message' => 'Notification processed', 'po_id' => $po->id]);

    } else {
        Log::warning("API: PO not found for code: " . ($request->po_code ?? $request->pr_code ?? 'N/A') . " / Item: " . $request->pr_item_id);
        return response()->json(['success' => false, 'message' => 'PO/PR Reference not found'], 404);
    }
    }
}