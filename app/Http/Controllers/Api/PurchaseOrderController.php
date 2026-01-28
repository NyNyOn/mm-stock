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

                
                $rejectedItemNames = []; // ✅ Track names for summary

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
                             $item = $po->items()->where('item_description', $rItem['item_description'] ?? $rItem['item_name'])->first();
                        }
                        
                        $currentName = 'Unknown Item';

                        if ($item) {
                            $item->status = 'cancelled'; // Mark item as rejected
                            $item->rejection_code = $rItem['rejection_code'] ?? $mainRejectionCode;
                            $item->rejection_reason = $rItem['reason'] ?? $reason;
                            $item->save();
                            Log::info("Item Rejected: ID {$item->id} Code {$item->rejection_code}");

                             // ✅ Log History for specific Item
                             $currentName = $item->equipment ? $item->equipment->name : $item->item_description;
                             $itemName = $currentName;
                             $this->addPoHistoryLog($po, 'Item Rejected', "{$itemName} rejected by {$rejectedBy} (Reason: {$item->rejection_reason})");
                        } else {
                            // Try to get name from payload if item not found locally
                            $currentName = $rItem['item_name'] ?? $rItem['item_description'] ?? ("Item #" . ($rItem['pr_item_id'] ?? '?'));
                        }
                        $rejectedItemNames[] = $currentName;
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

                        // ✅ Log History for specific Item (Even in Full Rejection)
                        $itemName = $item->equipment ? $item->equipment->name : $item->item_description;
                        $rejectedItemNames[] = $itemName; // Track for summary
                        
                        // Use $rejectedBy from inputs (need to capture it first or use fallback here)
                        // Note: $rejectedBy is defined below in original code, so we use input directly here or move definition up.
                        // Safest: Use input fallback here to avoid re-ordering large chunks.
                        $actor = $request->rejected_by ?? $request->inspector ?? 'System'; 
                        $this->addPoHistoryLog($po, 'Item Rejected', "{$itemName} rejected by {$actor} (Reason: {$reason})");
                    }
                }

                // ✅ Capture Actor Name
                $rejectedBy = $request->rejected_by ?? $request->inspector ?? $request->user ?? 'PU Team';
                $puData['rejected_by'] = $rejectedBy; // Save for View
                $po->pu_data = $puData;
                $po->save();
                
                // ✅ Log History (Summary with Item Names)
                $itemsStr = !empty($rejectedItemNames) ? " (".implode(', ', $rejectedItemNames).")" : "";
                $this->addPoHistoryLog($po, 'Rejected', "Reason: {$reason}{$itemsStr} (By {$rejectedBy})");

                // 🔔 Notify Rejection (Synology + In-App)
                try {
                    $po->refresh();
                    $notification = new \App\Notifications\PurchaseOrderUpdatedNotification($po, 'rejected');

                    // 1. Synology
                    (new \App\Services\SynologyService())->notify($notification);

                    // 2. Database (In-App) - Notify Users with Permission
                    $permissionNames = ['po:view', 'po:manage'];
                    // Query IT Stock DB for Users with these permissions (via Roles/Groups)
                    $userIds = DB::connection('mysql')->table('service_user_roles')
                        ->join('group_permissions', 'service_user_roles.group_id', '=', 'group_permissions.user_group_id')
                        ->join('permissions', 'group_permissions.permission_id', '=', 'permissions.id')
                        ->whereIn('permissions.name', $permissionNames)
                        ->select('service_user_roles.user_id')
                        ->distinct() // Ensure unique user IDs
                        ->pluck('user_id');

                    $notifiableUsers = \App\Models\User::whereIn('id', $userIds)->get();
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

        // --------------------------------------------------------
        // Handler for PO COMPLETED (Phase 3.2 - Final Price & Status)
        // --------------------------------------------------------
        if ($eventType === 'po_completed') {
            $poCode = $request->input('po_code');
            $completedAt = $request->input('completed_at');
            $supplierName = $request->input('supplier_name');
            $itemsList = $request->input('items', []);

            Log::info("API: Received PO Completion for PO: {$poCode}");

            $po = PurchaseOrder::where('po_number', $poCode)->first();

            if ($po) {
                // 1. Update Header Info
                if ($supplierName && $supplierName !== 'Unknown') {
                    $po->supplier_name = $supplierName;
                }
                
                // 2. Process Items (Update Price)
                foreach ($itemsList as $rItem) {
                    // Try to match item by name or other heuristic
                    // Since we don't have ID in this payload snippet, we rely on name.
                    // Ideally, we should match exact string.
                    $itemName = $rItem['item_name'];
                    $unitPrice = $rItem['unit_price'] ?? 0;
                    
                    // Finds the item
                    $item = $po->items()
                        ->where('item_description', $itemName)
                        ->first();

                    if ($item) {
                        $item->unit_price = $unitPrice;
                        // $item->quantity_received = $rItem['quantity']; // Optional: Sync Qty?
                        $item->save();
                        
                        // Update Master Equipment Price if linked
                        if ($item->equipment_id && $unitPrice > 0) {
                            $equipment = $item->equipment;
                            $equipment->price = $unitPrice;
                            $equipment->save();
                        }
                    }
                }

                // 3. Mark as Completed (if not already)
                if ($po->status !== 'completed') {
                    $po->status = 'completed';
                    // $po->completed_at = $completedAt; // If column exists
                    $po->save();
                    Log::info("API: Set PO #{$po->id} to 'completed' via Webhook.");
                    
                    // Notify
                    try {
                         $notification = new \App\Notifications\PurchaseOrderUpdatedNotification($po, 'completed');
                         (new \App\Services\SynologyService())->notify($notification);
                    } catch (\Exception $e) {}
                }

                // Log History
                $this->addPoHistoryLog($po, 'Completed', "PO Completed by PU (Total Items: ".count($itemsList).")");

                return response()->json(['success' => true, 'message' => 'PO marked as completed and prices updated.']);
            }
            
            return response()->json(['success' => false, 'message' => 'PO not found'], 404);
        }

        // --- HANDLER FOR REJECTION RESPONSES (Phase 3) ---
        if ($eventType === 'item_inspection_result') {
            Log::info("API: Processing Inspection Result Action: {$action}");

            $item = \App\Models\PurchaseOrderItem::where('pr_item_id', $prItemId)->first();
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Item not found'], 404);
            }

            // ✅ Handle PO Splitting / Moving BEFORE Action
            // This ensures if Force Approve creates a new PO, the item is moved there first.
            if (!empty($request->po_code)) {
                $this->processPoSplit($item, $request->po_code, $prItemId);
                $item->refresh(); // Refresh relationship after potential move
            }

            DB::beginTransaction();
            try {
                switch ($action) {
                    case 'force_approve': // 1. Force Approve (PU overrides - Auto Receive)
                        // Objective: Immediately add stock and mark as successful.
                        
                        $qtyOrdered = $item->quantity_ordered;
                        $qtyReceived = $item->quantity_received;
                        
                        // Check if PU sent specific quantity (Prioritize received_quantity)
                        $qtyToReceive = $request->input('received_quantity', $request->input('quantity', $request->input('approved_quantity', ($qtyOrdered - $qtyReceived))));
                        
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
                        // ✅ FIX: USER REQ: Force Approve = Item is DONE (Received) regardless of quantity
                        $item->status = 'received'; 
                        $item->rejection_code = null;
                        $item->rejection_reason = null;
                        $item->inspection_notes = "Force Approved by PU ({$request->inspector})";
                        $item->save();
                        
                        $item->inspection_notes = "Force Approved by PU ({$request->inspector})";
                        $item->save();
                        
                        $itemName = $item->equipment ? $item->equipment->name : $item->item_description;
                        Log::info("API: Auto-Received Item '{$itemName}' via Force Approve.");
                        
                        // ✅ Check PO Completion (Smart Logic)
                        $po = $item->purchaseOrder;
                        $po->refresh(); // Refresh items status

                        $pendingCount = $po->items()
                            ->whereRaw('ifnull(quantity_received, 0) < quantity_ordered')
                            ->where('status', '!=', 'received')
                            ->whereNotIn('status', ['returned', 'inspection_failed', 'cancelled', 'rejected'])
                            ->count();
                            
                        if ($pendingCount == 0) {
                            // All "active" items are done. Now determine Final Status based on composition.
                            $successCount = $po->items()->where(function($q){
                                $q->where('status', 'received')->orWhere('status', 'completed');
                            })->count();
                            $issueCount = $po->items()->whereIn('status', ['returned', 'inspection_failed'])->count();
                            $rejectCount = $po->items()->whereIn('status', ['cancelled', 'rejected'])->count();
                            $totalCount = $po->items()->count();

                            if ($successCount > 0 && ($issueCount > 0 || $rejectCount > 0)) {
                                // Mixed: Keep Open as Partial Receive
                                $po->status = 'partial_receive';
                                Log::info("API: PO #{$po->id} set to 'partial_receive' (Mixed Success & Issues).");
                            } elseif ($successCount == 0 && $issueCount > 0) {
                                // All Issues: Inspection Failed
                                $po->status = 'inspection_failed';
                                Log::info("API: PO #{$po->id} set to 'inspection_failed' (All Items have Issues).");
                            } elseif ($successCount == 0 && $rejectCount > 0) {
                                // All Rejected: Cancelled
                                $po->status = 'cancelled';
                                Log::info("API: PO #{$po->id} set to 'cancelled' (All Items Rejected).");
                            } else {
                                // All Good
                                $po->status = 'completed';
                                Log::info("API: Set PO #{$po->id} to 'completed'.");
                            }
                            $po->save();
                        } else {
                            // If pending > 0 (Still has items left)
                            // Update status to reflect activity (e.g. from Rejected -> Partial)
                            $validActiveStatuses = ['ordered', 'pending', 'shipped_from_supplier', 'rejected', 'cancelled', 'inspection_failed'];
                            
                            if (in_array($po->status, $validActiveStatuses)) {
                                $po->status = 'partial_receive';
                                $po->save();
                            }
                        }

                        // ✅ Log History
                        $itemName = $item->equipment ? $item->equipment->name : $item->item_description;
                        $this->addPoHistoryLog($po, 'Force Approved', "{$itemName} - By {$request->inspector} (Qty: {$qtyToReceive})");
                        
                        // ✅ NOTIFY: Trigger "Force Approve" Notification
                        try {
                            $notify = new \App\Notifications\PurchaseOrderUpdatedNotification(
                                $po, 
                                'force_approve', 
                                ['note' => "ดำเนินการโดย: {$request->inspector}", 'item_id' => $item->id, 'quantity' => $qtyToReceive]
                            );
                            (new \App\Services\SynologyService())->notify($notify);
                        } catch (\Exception $e) { Log::error("Failed to send ForceApprove Notify: " . $e->getMessage()); }

                        break;

                    case 'return': // 2. Return (User confirms return)
                        // Objective: Item goes back. Unlink/Cleanup.
                        $item->status = 'returned'; // Special status to hide/archive
                        $item->inspection_notes = "Returned to Supplier by PU ({$request->inspector})";
                        // Note: Cannot set purchase_order_id to NULL (DB Constraint). 
                        // We leave it linked to old PO until new PO Webhook arrives to "adopt" it.
                        $item->save();
                        
                        Log::info("API: Returned Item #{$item->id}. Waiting for new PO alignment.");

                        // ✅ Log History
                        $itemName = $item->equipment ? $item->equipment->name : $item->item_description;
                        $this->addPoHistoryLog($item->purchaseOrder, 'Item Returned', "{$itemName} returned by {$request->inspector}");

                        // ✅ NOTIFY: Trigger "Return" Notification
                        try {
                            $notify = new \App\Notifications\PurchaseOrderUpdatedNotification(
                                $item->purchaseOrder, 
                                'return', 
                                ['note' => "ดำเนินการโดย: {$request->inspector} (รายการ: {$item->item_description})", 'item_id' => $item->id]
                            );
                            (new \App\Services\SynologyService())->notify($notify);
                        } catch (\Exception $e) { Log::error("Failed to send Return Notify: " . $e->getMessage()); }

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
                        $validReopenStatuses = ['ordered', 'cancelled', 'pending', 'completed', 'partial_receive', 'rejected', 'inspection_failed'];
                        
                        if (in_array($po->status, $validReopenStatuses)) {
                            // If it was Closed/Rejected -> Re-open to Partial Receive
                            if (in_array($po->status, ['completed', 'cancelled', 'rejected', 'inspection_failed'])) {
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

                        // ✅ Log History
                        $itemName = $item->equipment ? $item->equipment->name : $item->item_description;
                        $this->addPoHistoryLog($item->purchaseOrder, 'Recheck Requested', "{$itemName} recheck by {$request->inspector}");

                        // ✅ NOTIFY: Trigger "Recheck" Notification
                        try {
                            $notify = new \App\Notifications\PurchaseOrderUpdatedNotification(
                                $item->purchaseOrder, 
                                'recheck', 
                                ['note' => "ข้อความจากจัดซื้อ: {$request->inspector}", 'item_id' => $item->id]
                            );
                            (new \App\Services\SynologyService())->notify($notify);
                        } catch (\Exception $e) { Log::error("Failed to send Recheck Notify: " . $e->getMessage()); }

                        break;

                    case 'rejected': // 4. Rejected (PU confirms rejection)
                         // Set Item Status
                         $item->status = 'cancelled';
                         // Use provided code or default to 1 (General)
                         $item->rejection_code = $request->rejection_code ?? 1;
                         $item->rejection_reason = $request->reason ?? $request->note ?? 'Rejected by PU';
                         $item->save();

                         // Update PO Status (Smart Logic - similar to force_approve but for rejection)
                         $this->updatePoStatusAfterItemChange($item->purchaseOrder);

                         // ✅ Log History
                         $rejectedBy = $request->rejected_by ?? $request->inspector ?? 'PU Team';
                         $itemName = $item->equipment ? $item->equipment->name : $item->item_description;
                         $this->addPoHistoryLog($item->purchaseOrder, 'Item Rejected', "{$itemName} rejected by {$rejectedBy}: {$item->rejection_reason}");

                         // ✅ NOTIFY: Trigger "Rejected" Notification (Item Level)
                         try {
                              $notify = new \App\Notifications\PurchaseOrderUpdatedNotification(
                                  $item->purchaseOrder, 
                                  'rejected', 
                                  ['note' => "ปฏิเสธโดย: {$rejectedBy} (เหตุผล: {$item->rejection_reason})", 'item_id' => $item->id]
                              );
                              (new \App\Services\SynologyService())->notify($notify);
                         } catch (\Exception $e) { Log::error("Failed to send Rejected Notify: " . $e->getMessage()); }
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

    // B. Try matching by PR Number (Priority: Active POs without PO Number)
    if (!$po && !empty($request->pr_code)) {
        // Prioritize: 1. Active Status, 2. No PO Number yet
        $po = PurchaseOrder::where('pr_number', $request->pr_code)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->where(function($q) {
                $q->whereNull('po_number')->orWhere('po_number', '');
            })
            ->orderBy('id', 'desc')
            ->first();

        // Fallback: If no "clean" PO found, try any active PO (maybe updating existing one?)
        if (!$po) {
            $po = PurchaseOrder::where('pr_number', $request->pr_code)
                 ->whereNotIn('status', ['cancelled', 'rejected'])
                 ->first();
        }
    }

    // C. Try matching by Item ID (Reliable fallback if we saved pr_item_id)
    if (!empty($request->pr_item_id)) {
        $item = \App\Models\PurchaseOrderItem::where('pr_item_id', $request->pr_item_id)->first();
        if ($item) {
            
            // ✅ DETECT PO CHANGE (Item Moved to New PO - SPLITTING)
            // Logic handled by helper function now
            if (!empty($request->po_code)) {
                $this->processPoSplit($item, $request->po_code, $request->pr_item_id);
                // ✅ FIX: Refresh item to clear relationship cache (so $item->purchaseOrder returns the NEW PO)
                $item->refresh();
                $item->unsetRelation('purchaseOrder'); 
            }

            // switch context if moved (helper updates item, we need to update $po local var if needed)
            $po = $item->purchaseOrder; 
            
             // ✅ RESET INSPECTION STATUS (Logic moved to 'recheck' action above, but kept here for legacy/simple updates)
            if ($request->status == 'shipped_from_supplier' || $request->status == 'arrived_at_hub') {
                 // Only reset if previously inspected/rejected to allow retry if PU sends update?
                 // But 'recheck' action is now the formal way.
                 // let's keep this as backup for Phase 2 general updates.
                 if ($item->inspection_status || $item->status == 'returned') {
                      $item->inspection_status = null;
                      $item->inspection_notes = null;
                      $item->status = 'shipped_from_supplier'; // ✅ Undo 'returned' status so it shows up in Receive Page again
                 }
            }
            // ✅ Always update origin_pr_number if provided (for mixed PO tracking)
            if (!empty($request->origin_pr_number) || !empty($request->pr_code)) {
                 $item->origin_pr_number = $request->origin_pr_number ?? $request->pr_code;
                 $item->save();
            }
        }
    }

    // D. Auto-Create Scenario (Manual PR from PU / Floating PO)
    if (!$po && !empty($request->po_code) && ($request->is_manual_pr || $request->origin_pr_number)) {
        Log::info("API: Auto-Creating Manual PO for code: {$request->po_code}");

        // 1. Find or Default User (System or Admin)
        $adminUser = \App\Models\User::orderBy('id')->first();
        
        // 2. Create PO
        $po = PurchaseOrder::create([
            'po_number' => $request->po_code,
            'pr_number' => $request->pr_code ?? 'PR-' . uniqid(),
            'ordered_by_user_id' => $adminUser->id ?? 1,
            'department_id' => 1, // Default Dept
            'status' => 'pending', // Will be updated to 'shipped_from_supplier' below
            'notes' => 'Auto-created from PU Hub Webhook (Manual PR)',
        ]);

        // 3. Create Item (Placeholder)
        // We might not have item details, so we make a placeholder that requires linking
        if (!empty($request->pr_item_id)) {
             // ✅ Check for Auto-Link (origin_item_id)
             $equipmentId = null;
             $itemName = $request->item_name ?? "Manual Item #{$request->pr_item_id} (Waiting for Link)";
             $unitName = $request->unit_name ?? 'ea';
             
             if (!empty($request->origin_item_id)) {
                 $equipment = \App\Models\Equipment::find($request->origin_item_id);
                 if ($equipment) {
                     $equipmentId = $equipment->id;
                     $itemName = $equipment->name;
                     $unitName = $equipment->unit->name ?? 'ea'; // ✅ Fix: Access name from relationship
                     Log::info("API: Auto-Linked Item #{$request->pr_item_id} to Equipment #{$equipment->id}");
                 }
             }

             \App\Models\PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'item_description' => $itemName,
                'quantity_ordered' => $request->received_quantity ?? 1,
                'unit_name' => $unitName,
                'pr_item_id' => $request->pr_item_id,
                'equipment_id' => $equipmentId, // ✅ Auto-Link
                'status' => 'pending'
             ]);
             // Refresh relation
             $po->load('items');
        }
    }

    if ($po) {
        // ✅ Manual PR: Ensure Item Exists!
        // (Scenario: Multiple Webhooks for same PO -> Block D only runs for the first one)
        if (!empty($request->pr_item_id) && ($request->is_manual_pr || $request->origin_pr_number)) {
            // Check if this item exists in the PO
            $existingItem = $po->items()->where('pr_item_id', $request->pr_item_id)->exists();
            
            if (!$existingItem) {
                 Log::info("API: Adding Missing Manual Item #{$request->pr_item_id} to PO #{$po->id}");
                 
                 // ✅ Check for Auto-Link (origin_item_id)
                 $equipmentId = null;
                 $itemName = $request->item_name ?? "Manual Item #{$request->pr_item_id} (Waiting for Link)";
                 $unitName = $request->unit_name ?? 'ea';
                 
                 // ✅ RESTORED: Auto-Link if origin_item_id is present (User Request)
                 if (!empty($request->origin_item_id)) {
                     $equipment = \App\Models\Equipment::find($request->origin_item_id); 
                     if ($equipment) {
                         $equipmentId = $equipment->id;
                         $itemName = $equipment->name; 
                         $unitName = $equipment->unit->name ?? 'ea'; 
                         Log::info("API: Auto-Linked Item #{$request->pr_item_id} to Equipment #{$equipment->id}");
                     }
                 }

                 \App\Models\PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'item_description' => $itemName,
                    'quantity_ordered' => $request->received_quantity ?? 1,
                    'unit_name' => $unitName,
                    'pr_item_id' => $request->pr_item_id,
                    'origin_pr_number' => $request->origin_pr_number ?? $request->pr_code, // ✅ Save Origin PR
                    'equipment_id' => $equipmentId, // ✅ Auto-Link if found
                    'status' => 'pending'
                 ]);
                 // Refresh items to include new one in logic below
                 $po->refresh();
            }
        }
        // ✅ sync fields strict update: Always update if PU sends them
        if (!empty($request->po_code)) {
            // Check for potential duplicate before updating
            $existingOwner = PurchaseOrder::where('po_number', $request->po_code)->where('id', '!=', $po->id)->first();
            if ($existingOwner) {
                Log::warning("API: PO Number Collision! {$request->po_code} already belongs to PO #{$existingOwner->id}. Skipping update for PO #{$po->id}.");
            } else {
                $po->po_number = $request->po_code;
            }
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

    // Helper to handle PO Splitting logic
    private function processPoSplit($item, $newPoCode, $prItemId)
    {
        $currentPO = $item->purchaseOrder;
        $currentPoCode = $currentPO->po_number ?? null;

        if (!$newPoCode) return null;

        // 1. Check if Target PO already exists (Merging Logic)
        // If another PO already owns this code, we MUST join it.
        $targetPO = PurchaseOrder::where('po_number', $newPoCode)
            ->where('id', '!=', $currentPO->id)
            ->first();

        if ($targetPO) {
             Log::info("API: Found existing Target PO '{$newPoCode}' (ID #{$targetPO->id}). Moving Item #{$item->id} from PO #{$currentPO->id} to join it.");
             $item->purchase_order_id = $targetPO->id;
             $item->save();
             
             // Check if old PO is empty and useless (no po number), then maybe cancel/delete it?
             // For safety, we leave it for now, unless fully empty.
             if ($currentPO->items()->count() == 0 && empty($currentPO->po_number)) {
                 $currentPO->delete(); // Soft delete or just leave empty
                 Log::info("API: Old PO #{$currentPO->id} is now empty and has no PO Number. Deleted/Cleaned up.");
             }
             
             return $targetPO;
        }

        // 2. If NO Target PO, determine if we need to SPLIT from the current PO
        // Condition: PO Change (A -> B) OR Initial Assignment (Null -> A) with mixed items
        $isChange = ($currentPoCode && $currentPoCode !== $newPoCode);
        
        $isSplitRequired = false;
        if (!$currentPoCode && $newPoCode && $currentPO) {
            $otherItemsCount = $currentPO->items()->where('id', '!=', $item->id)->count();
            // If there are other items, we SHOULD split this one out
            if ($otherItemsCount > 0) {
                $isSplitRequired = true;
            }
        }

        if ($isChange || $isSplitRequired) {
            Log::info("API: Item #{$item->id} (PR Item {$prItemId}) needs split/move to new PO Code '{$newPoCode}' (Current: {$currentPoCode}). Creating new PO...");
            
            // Create duplicate PO Structure
            $targetPO = $currentPO->replicate(); 
            $targetPO->po_number = $newPoCode;
            
            // ✅ If item has origin_pr_number, use it as the main PR for this new Split PO
            if (!empty($item->origin_pr_number)) {
                 $targetPO->pr_number = $item->origin_pr_number;
            }

            $targetPO->status = 'ordered'; 
            $targetPO->ordered_at = now();
            // Clean history/data for new PO
            $targetPO->pu_data = array_merge($currentPO->pu_data ?? [], ['split_from' => $currentPO->po_number ?? $currentPO->pr_number, 'split_at' => now()->toIso8601String()]);
            $targetPO->push(); // save and reload
            
            Log::info("API: Created Split PO #{$targetPO->id} for '{$newPoCode}'");

            // Move Item
            $item->purchase_order_id = $targetPO->id;
            $item->save();
            
            Log::info("API: Moved Item #{$item->id} to PO #{$targetPO->id}.");
            
            return $targetPO;
        }
        
        return null;
    }

    private function addPoHistoryLog($po, $event, $reason = '-')
    {
        $puData = $po->pu_data ?? [];
        $history = $puData['history'] ?? [];
        $history[] = [
            'event' => $event,
            'reason' => $reason,
            'at' => now()->toIso8601String()
        ];
        $puData['history'] = $history;
        $po->pu_data = $puData;
        $po->saveQuietly(); // Use saveQuietly to avoid triggering observers/events if not needed, or just save()
    }

    // Helper: Logic to update PO Status after item change (Smart Completion Logic)
    private function updatePoStatusAfterItemChange($po) 
    {
        $po->refresh(); // Refresh items status

        $pendingCount = $po->items()
            ->whereRaw('ifnull(quantity_received, 0) < quantity_ordered')
            ->where('status', '!=', 'received')
            ->whereNotIn('status', ['returned', 'inspection_failed', 'cancelled', 'rejected'])
            ->count();
            
        if ($pendingCount == 0) {
            // All "active" items are done. Now determine Final Status based on composition.
            $successCount = $po->items()->where(function($q){
                $q->where('status', 'received')->orWhere('status', 'completed');
            })->count();
            $issueCount = $po->items()->whereIn('status', ['returned', 'inspection_failed'])->count();
            $rejectCount = $po->items()->whereIn('status', ['cancelled', 'rejected'])->count();

            if ($successCount > 0 && ($issueCount > 0 || $rejectCount > 0)) {
                // Mixed: Keep Open as Partial Receive
                $po->status = 'partial_receive';
            } elseif ($successCount == 0 && $issueCount > 0) {
                // All Issues: Inspection Failed
                $po->status = 'inspection_failed';
            } elseif ($successCount == 0 && $rejectCount > 0) {
                // All Rejected: Cancelled
                $po->status = 'cancelled';
            } else {
                // All Good
                $po->status = 'completed';
            }
            $po->save();
        } else {
            // If pending > 0 (Still has items left)
            $validActiveStatuses = ['ordered', 'pending', 'shipped_from_supplier', 'rejected', 'cancelled', 'inspection_failed'];
            if (in_array($po->status, $validActiveStatuses)) {
                $po->status = 'partial_receive';
                $po->save();
            }
        }
    }
}