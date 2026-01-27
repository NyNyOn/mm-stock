<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Equipment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use App\Services\PuHubService;

class ReceiveController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('receive:view');

        try {
            $pendingPOs = PurchaseOrder::with([
                'items' => function ($query) {
                    $query->where(function ($q) {
                        $q->whereNull('quantity_received')
                          ->orWhereRaw('quantity_received < quantity_ordered')
                          ->orWhere('status', 'pending_inspection'); // ✅ Show Rechecked Items
                    })
                    // ✅ Exclude Rejected/Cancelled items from the Receive View
                    ->whereNotIn('status', ['returned', 'cancelled', 'rejected', 'inspection_failed'])
                    ->with(['equipment.latestImage', 'equipment.unit'])
                    ->orderBy('item_description');
                },
                'orderedBy'
            ])
            // Reverted: 'ordered' removed. User wants items to appear ONLY after PU Webhook (shipped) AND PO Number is assigned.
            ->whereIn('status', ['shipped_from_supplier', 'partial_receive', 'contact_vendor']) 
            ->whereNotNull('po_number') // ✅ Enforce PO Number existence 
            ->whereHas('items', function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('quantity_received')
                      ->orWhereRaw('quantity_received < quantity_ordered')
                      ->orWhere('status', 'pending_inspection'); // ✅ Show Rechecked Items
                })->whereNotIn('status', ['returned', 'cancelled', 'rejected', 'inspection_failed']); // ✅ Apply same filter to PO detection
            })
            ->orderBy('created_at', 'desc')
            ->get();

             $currentDeptKey = Config::get('app.dept_key', 'mm');
             $departmentsConfig = Config::get('department_stocks.departments', []);
             $currentDeptName = $departmentsConfig[$currentDeptKey]['name'] ?? strtoupper($currentDeptKey);

            if (request()->ajax()) {
                return view('receive.partials._list', compact('pendingPOs', 'currentDeptName'))->render();
            }

            return view('receive.index', compact('pendingPOs', 'currentDeptName'));

        } catch (\Exception $e) {
            Log::error("[ReceiveController::index] Error: " . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'ไม่สามารถโหลดหน้ารับเข้าได้: ' . $e->getMessage());
        }
    }

    public function process(Request $request)
    {
        $this->authorize('receive:manage');

        // Validation พื้นฐาน
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', 'ข้อมูลไม่ถูกต้อง');
        }

        $inputItems = $request->input('items');
        $poIdsToUpdate = [];
        $processedCount = 0;
        $skippedItems = [];
        $receivedItemsByPo = []; // ✅ Track items for notification
        $problemItemsByPo = [];  // ✅ Track problem items for notification

        DB::beginTransaction();
        
        Log::info("DEBUG: Receive Process Started. User: " . Auth::id());
        Log::info("DEBUG: Input Items Keys: " . json_encode(array_keys($inputItems)));
        foreach($inputItems as $id => $data) {
             if(isset($data['selected'])) {
                 Log::info("DEBUG: Item $id SELECTED. Data: " . json_encode($data));
             } else {
                 Log::info("DEBUG: Item $id PRESENT but NOT SELECTED.");
             }
        }
        
        try {
            foreach ($inputItems as $poItemId => $data) {

                // 1. เช็คว่า User ติ๊กเลือกรายการนี้ไหม? (Checkbox)
                if (!isset($data['selected'])) {
                    continue; // ข้ามรายการที่ไม่ได้เลือก
                }

                $receiveNowQty = (int)($data['receive_now_quantity'] ?? 0);
                $issueQty = (int)($data['issue_qty_handled'] ?? 0); // ✅ Capture Issue/Reject Qty
                $inspectionStatus = $data['inspection_status'] ?? 'pass';
                $inspectionNotes = $data['inspection_notes'] ?? $data['notes_reject_description'] ?? null;

                $poItem = PurchaseOrderItem::lockForUpdate()->find($poItemId);
                if (!$poItem) continue;

                $poId = $poItem->purchase_order_id;
                $poIdsToUpdate[$poId] = $poId;
                
                $totalOrdered = (int)$poItem->quantity_ordered;
                $alreadyReceived = (int)$poItem->quantity_received;
                
                // ตรวจสอบ Equipment
                $equipmentId = $poItem->equipment_id;
                $equipment = $equipmentId ? Equipment::lockForUpdate()->find($equipmentId) : null;

                if (!$equipment) {
                    $skippedItems[] = "รายการ '{$poItem->item_description}' ยังไม่ได้ผูกอุปกรณ์";
                    continue;
                }

                // 2. Logic การเพิ่มสต๊อก (เพิ่มเฉพาะของดี 'pass' หรือ 'incomplete' ที่เป็นของดีแต่มาน้อย)
                // ถ้าเป็น damaged, wrong_item จะไม่เพิ่มสต๊อก แต่จะบันทึก Transaction ไว้เป็นหลักฐาน
                if (in_array($inspectionStatus, ['pass', 'incomplete']) && $receiveNowQty > 0) {
                    
                    // ALLOW OVER-SHIPMENT (Per Final Guide)
                    // if (($alreadyReceived + $receiveNowQty) > $totalOrdered) { ... }

                    // $equipment->increment('quantity', $receiveNowQty); // ❌ Increment does not fire model events
                    $equipment->quantity += $receiveNowQty;
                    $equipment->save(); // ✅ Save fires 'saving' event which updates status
                    
                    // Fetch PO Details for Log
                    $po = $poItem->purchaseOrder;
                    $poNum = $po->po_number ?? '-';
                    $prNum = $po->pr_number ?? '-';
                    
                    // ✅ Track for Notification
                    $receivedItemsByPo[$poId][] = [
                        'name' => $equipment->name,
                        'qty' => $receiveNowQty
                    ];

                    // (Step 2) ✅ Notify Arrival to PU-HUB
                    // ERROR FIX: This step was trying to call '/api/v1/notify-hub-arrival' on the remote server, 
                    // which is actually OUR OWN local webhook path. 
                    // Per user feedback, "notify-hub-arrival is ours". 
                    // So we should NOT be sending this request out. 
                    // Commenting out to prevent 404 Error.
                    /*
                    try {
                         $puHubService = app(\App\Services\PuHubService::class);
                         $arrivalPayload = [
                            'pr_item_id' => $poItem->pr_item_id, // ใช้ ID อ้างอิงจาก PU
                            'status' => 'arrived_at_hub',
                            'po_code' => $poNum,
                            'pr_code' => $prNum,
                            'received_quantity' => $receiveNowQty,
                            'is_manual_pr' => false,
                            'origin_item_id' => null
                         ];
                         $puHubService->notifyHubArrival($arrivalPayload);
                    } catch (\Exception $e) {
                         Log::warning("Step 2 (Arrival Notify) Failed for Item {$poItemId}: " . $e->getMessage());
                    }
                    */

                    Transaction::create([
                        'equipment_id'    => $equipment->id,
                        'user_id'         => Auth::id(),
                        'handler_id'      => Auth::id(),
                        'type'            => 'receive',
                        'quantity_change' => $receiveNowQty,
                        'notes'           => "รับของเข้าคลัง: PO {$poNum} / PR {$prNum} (จำนวน: {$receiveNowQty}) - {$inspectionStatus}",
                        'transaction_date'=> now(),
                        'status'          => 'completed',
                        'admin_confirmed_at' => now(),
                        'user_confirmed_at' => now(),
                        'confirmed_at' => now(),
                    ]);

                    // อัปเดตจำนวนที่รับแล้วใน PO Item
                    $poItem->quantity_received = $alreadyReceived + $receiveNowQty;
                } else {
                    /* 
                    // ❌ REMOVED: User requested to hide 0-qty transactions to avoid confusion.
                    // Data is still saved in purchase_order_items (inspection_status/notes).
                    
                     Transaction::create([
                        'equipment_id'    => $equipment->id,
                        'user_id'         => Auth::id(),
                        'handler_id'      => Auth::id(),
                        'type'            => 'receive',
                        'quantity_change' => 0, // ไม่เพิ่มสต๊อก (ถูกต้องแล้ว)
                        'notes'           => "ปฏิเสธรับของ PO #{$poId}: {$inspectionStatus} (จำนวน: {$issueQty}) - {$inspectionNotes}",
                        'transaction_date'=> now(),
                        'status'          => 'completed',
                        'admin_confirmed_at' => now(),
                        'user_confirmed_at' => now(),
                        'confirmed_at' => now(),
                    ]);
                    */
                }

                // 3. บันทึกผลการตรวจ (Inspection Result) กลับลง DB
                // (เพื่อให้ API ของ PU สามารถมาดึงข้อมูลนี้ไปดูได้ว่าทำไมถึงรับไม่ครบ)
                $poItem->inspection_status = $inspectionStatus;
                $poItem->inspection_notes = $inspectionNotes;

                // อัปเดตสถานะ Item
                // ถ้าครบ หรือ User ตั้งใจกดรับแล้ว (แม้จะไม่ครบแต่จบงาน)
                if ($poItem->quantity_received >= $totalOrdered || $inspectionStatus == 'pass') {
                     $poItem->status = ($poItem->quantity_received >= $totalOrdered) ? 'received' : 'partial_receive';
                }
                
                // กรณีของเสีย ให้ถือว่า pending รอเคลม หรือ partial
                if (in_array($inspectionStatus, ['damaged', 'wrong_item', 'quality_issue'])) {
                    $poItem->status = 'inspection_failed'; // หรือสถานะที่สื่อว่ามีปัญหา
                }

                $poItem->save();
                $processedCount++;
            }

            // Update PO Status
            foreach (array_unique($poIdsToUpdate) as $poId) {
                $purchaseOrder = PurchaseOrder::find($poId);
                if ($purchaseOrder) {
                    $purchaseOrder->refresh(); // ✅ Ensure fresh data

                    // 1. Check for Pending Items (Excluding Finalized Rejections)
                    $pendingItemsCount = $purchaseOrder->items()
                        ->where(function ($q) {
                            $q->whereRaw('ifnull(quantity_received, 0) < quantity_ordered')
                              ->whereNotIn('status', ['returned', 'inspection_failed', 'cancelled', 'rejected']);
                        })->count();

                    if ($pendingItemsCount == 0) {
                        // All items handled -> Determine Final Status
                        $successCount = $purchaseOrder->items()->where(function($q){ 
                            $q->where('status', 'received')->orWhere('status', 'completed'); 
                        })->count();
                        
                        $issueCount = $purchaseOrder->items()->whereIn('status', ['returned', 'inspection_failed'])->count();
                        $rejectCount = $purchaseOrder->items()->whereIn('status', ['cancelled', 'rejected'])->count();

                        $newStatus = 'completed';

                        if ($successCount > 0 && ($issueCount > 0 || $rejectCount > 0)) {
                            // Mixed: Keep Open as Partial Receive
                            $newStatus = 'partial_receive';
                        } elseif ($successCount == 0 && $issueCount > 0) {
                            $newStatus = 'inspection_failed';
                        } elseif ($successCount == 0 && $rejectCount > 0) {
                            $newStatus = 'cancelled';
                        }
                        
                        // ✅ Force update if changed
                        if ($purchaseOrder->status !== $newStatus) {
                            $purchaseOrder->update(['status' => $newStatus]);
                            Log::info("Auto-Update PO #{$purchaseOrder->id} Status to {$newStatus}");
                        }

                    } else {
                        // Still pending items -> Set to Partial Receive if currently in non-progress state
                        if ($purchaseOrder->status !== 'partial_receive') {
                             $purchaseOrder->update(['status' => 'partial_receive']);
                        }
                    }
                }
            }

            DB::commit();

            // ✅ ส่งผลการตรวจสอบกลับไปยัง PU-HUB (Phase 3)
            try {
                $puHubService = app(PuHubService::class);
                $inspections = [];

                foreach ($inputItems as $poItemId => $data) {
                    if (!isset($data['selected'])) continue;

                    $poItem = PurchaseOrderItem::find($poItemId);
                    if (!$poItem || !$poItem->inspection_status) continue;

                    // ✅ FIX: Use 'pr_item_id' (External ID) instead of 'id' (Local ID)
                    if (empty($poItem->pr_item_id)) {
                        Log::warning("[ReceiveController] Item #{$poItem->id} has no pr_item_id. Skipping PU sync.");
                        continue;
                    }

                    // ✅ FIX: Use 'receive_now_quantity' OR 'issue_qty_handled' (Batch Qty)
                    // accepted uses receive_now_quantity, rejected uses issue_qty_handled
                    $currentBatchQty = (int)($data['receive_now_quantity'] ?? $data['issue_qty_handled'] ?? 0);

                    // แปลงสถานะเป็น accepted/rejected ตาม PU-HUB API
                    // Spec Ref: Final Guide
                    // - pass (Perfect) -> accepted
                    // - incomplete (Short Shipment) -> rejected (PU Manual Handle)
                    // - damaged/wrong_item -> rejected
                    
                    $status = 'rejected'; 

                    if ($poItem->inspection_status === 'pass') {
                        $status = 'accepted';
                    } 
                    
                    // ✅ CHECK OVER-SHIPMENT: If receiving MORE than ordered -> Send 'rejected'
                    // Spec: "Scenario: Ordered 10, Arrived 15. Action: Send status: rejected"
                    $totalOrderedForCheck = (int)$poItem->quantity_ordered;
                    // Note: We updated quantity_received earlier (Line ~139) so it includes current batch
                    if ($poItem->quantity_received > $totalOrderedForCheck) {
                         $status = 'rejected';
                         Log::info("[ReceiveController] Over-shipment detected for Item #{$poItem->id} (Ordered: {$totalOrderedForCheck}, Current Total: {$poItem->quantity_received}). Force status to REJECTED.");
                    } 
                    // Note: Even if 'incomplete' (Good but partial), Guide says send 'rejected' so PU knows to intervene.

                    // Log the decision for debugging
                    Log::info("[ReceiveController] Mapped Item #{$poItem->id} (Status: {$poItem->inspection_status}, Qty: {$currentBatchQty}) -> API Status: {$status}");
                    
                    // ✅ FORMAT NOTES: Prepend Reason (Thai) for Rejected items
                    $finalNotes = $poItem->inspection_notes ?? '';
                    if ($status === 'rejected') {
                        // Map internal status to Thai Label for PU Reader
                        $reasonMap = [
                            'incomplete' => 'ของไม่ครบ',
                            'damaged' => 'สินค้าเสียหาย',
                            'wrong_item' => 'สินค้าผิดรุ่น',
                            'quality_issue' => 'คุณภาพไม่ได้มาตรฐาน',
                            'pass' => 'ครบถ้วนสมบูรณ์'
                        ];
                        $reason = $reasonMap[$poItem->inspection_status] ?? $poItem->inspection_status;
                        
                        if (!empty($finalNotes)) {
                            $finalNotes = "{$reason} ({$finalNotes})";
                        } else {
                            $finalNotes = $reason;
                        }
                    }

                    // ✅ TRACK PROBLEMS (Reject/Issue)
                    if ($status === 'rejected') {
                         $problemItemsByPo[$poItem->purchase_order_id][] = [
                             'name' => $poItem->equipment->name ?? $poItem->item_description ?? 'Unknown',
                             'reason' => $finalNotes,
                             'status' => $poItem->inspection_status
                         ];
                    }

                    $inspections[] = [
                        'pr_item_id' => $poItem->pr_item_id,
                        'status' => $status,
                        'received_quantity' => $currentBatchQty, 
                        'notes' => $finalNotes
                    ];
                }

                if (!empty($inspections)) {
                    $result = $puHubService->confirmInspectionBatch($inspections);
                    
                    if (empty($result['results']['failed'])) {
                         Log::info('[ReceiveController] Successfully sent inspection results to PU-HUB', [
                            'count' => count($inspections)
                        ]);
                    } elseif (!empty($result['results']['failed'])) {
                         // Logic for failed items warning...
                         $failedCount = count($result['results']['failed']);
                         session()->flash('warning', "บันทึกสำเร็จ แต่ PU-HUB แจ้งเตือนข้อผิดพลาด {$failedCount} รายการ");
                    }
                }

            } catch (\Exception $e) {
                Log::error('[ReceiveController] Failed to send inspection results to PU-HUB: ' . $e->getMessage());
                session()->flash('warning', "บันทึกในระบบสำเร็จ แต่ไม่สามารถส่งข้อมูลไปยัง PU-HUB ได้ (Error: {$e->getMessage()})");
            }

            // ✅ NOTIFICATION: Send "Problem Report" (Issues/Rejections) separated by PO
            try {
                if (!empty($problemItemsByPo)) {
                    foreach ($problemItemsByPo as $poId => $pItems) {
                        $poForNotify = PurchaseOrder::find($poId);
                        if ($poForNotify) {
                            $notify = new \App\Notifications\PurchaseOrderUpdatedNotification($poForNotify, 'problem_report', ['problem_items' => $pItems]);
                            (new \App\Services\SynologyService())->notify($notify);
                            Log::info("[ReceiveController] Sent Problem Report for PO #{$poId} (Items: " . count($pItems) . ")");
                        }
                    }
                }
            } catch (\Exception $e) {
                 Log::error("Failed to send Problem Report Notification: " . $e->getMessage());
            }

            // ✅ NOTIFICATION: Send "Stock Received" (Success)
            try {
                if (!empty($receivedItemsByPo)) {
                     foreach ($receivedItemsByPo as $poId => $items) {
                        $po = PurchaseOrder::find($poId);
                        if ($po) {
                             $notify = new \App\Notifications\PurchaseOrderUpdatedNotification($po, 'stock_received', ['received_items' => $items]);
                             (new \App\Services\SynologyService())->notify($notify);
                        }
                     }
                }
            } catch (\Exception $e) {
                Log::error("Failed to send Stock Received Notification: " . $e->getMessage());
            }

            if ($processedCount == 0) {
                return redirect()->back()->with('warning', 'กรุณาเลือกรายการที่ต้องการรับ (ติ๊กถูกช่อง Checkbox)');
            }

            return redirect()->route('receive.index')->with('success', "บันทึกผลการตรวจรับเรียบร้อย ({$processedCount} รายการ)");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Receive Process Error: " . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    public function resendInspection(Request $request, PurchaseOrderItem $poItem)
    {
        $this->authorize('receive:manage');

        try {
            if (!$poItem->inspection_status) {
                return redirect()->back()->with('error', 'รายการนี้ยังไม่ได้ทำการตรวจรับ (ไม่มีสถานะ Inspection)');
            }

            if (empty($poItem->pr_item_id)) {
                return redirect()->back()->with('error', 'รายการนี้ไม่มี PR Item ID (ไม่สามารถส่งไป PU Hub ได้)');
            }

            $puHubService = app(PuHubService::class);
            
            // Re-construct the payload logic
            $status = 'rejected';
            if ($poItem->inspection_status === 'pass') {
                $status = 'accepted';
            }
            
            // Check Over-shipment
            if ($status === 'accepted' && $poItem->quantity_received > $poItem->quantity_ordered) {
                $status = 'rejected'; 
            }

            $finalNotes = $poItem->inspection_notes ?? '';
            if ($status === 'rejected') {
                $reasonMap = [
                    'incomplete' => 'ของไม่ครบ',
                    'damaged' => 'สินค้าเสียหาย',
                    'wrong_item' => 'สินค้าผิดรุ่น',
                    'quality_issue' => 'คุณภาพไม่ได้มาตรฐาน',
                    'pass' => 'ครบถ้วนสมบูรณ์'
                ];
                $reason = $reasonMap[$poItem->inspection_status] ?? $poItem->inspection_status;
                
                // Avoid double prefixing
                if (!str_contains($finalNotes, $reason)) {
                    if (!empty($finalNotes)) {
                        $finalNotes = "{$reason} ({$finalNotes})";
                    } else {
                        $finalNotes = $reason;
                    }
                }
            }
            
            $qtyToSend = ($poItem->quantity_received > 0) ? $poItem->quantity_received : ($poItem->quantity_ordered > 0 ? $poItem->quantity_ordered : 1);
            
            $inspections = [[
                'pr_item_id' => $poItem->pr_item_id,
                'status' => $status,
                'received_quantity' => $qtyToSend,
                'notes' => $finalNotes
            ]];

            $result = $puHubService->confirmInspectionBatch($inspections);

             if (!empty($result['results']['failed'])) {
                $failedItem = $result['results']['failed'][0] ?? [];
                $reason = $failedItem['reason'] ?? 'Unknown Error';

                // ✅ Self-Healing: If PU says "delivered", it means they finalized it. Auto-complete locally.
                if (str_contains(strtolower($reason), 'delivered')) {
                     DB::beginTransaction();
                     try {
                         $qtyToReceive = $poItem->quantity_ordered - $poItem->quantity_received;
                         if ($qtyToReceive > 0) {
                             $equipment = $poItem->equipment;
                             if ($equipment) {
                                 $equipment->quantity += $qtyToReceive;
                                 $equipment->save();
                                 
                                 // Log Transaction
                                 Transaction::create([
                                     'equipment_id'    => $equipment->id,
                                     'user_id'         => Auth::id(),
                                     'handler_id'      => Auth::id(), // System/User triggered
                                     'type'            => 'receive',
                                     'quantity_change' => $qtyToReceive,
                                     'notes'           => "Auto-Completed via Resend (PU status: delivered)",
                                     'transaction_date'=> now(),
                                     'status'          => 'completed'
                                 ]);
                             }
                             $poItem->quantity_received += $qtyToReceive;
                         }

                         $poItem->status = 'received';
                         $poItem->inspection_status = 'pass';
                         $poItem->inspection_notes = "System Auto-Completed: PU reported delivered during resend.";
                         $poItem->save();
                         
                         // Check Parent PO Completion
                         $po = $poItem->purchaseOrder;
                         if ($po) {
                            $pendingCount = $po->items()
                                ->whereRaw('ifnull(quantity_received, 0) < quantity_ordered')
                                ->whereNotIn('status', ['returned', 'inspection_failed'])
                                ->count();
                            if ($pendingCount == 0) {
                                $po->status = 'completed';
                                $po->save();
                            }
                         }

                         DB::commit();
                         return redirect()->back()->with('success', 'PU แจ้งว่ารายการนี้สำเร็จแล้ว (Delivered) - ระบบได้ปรับยอดรับเข้าให้โดยอัตโนมัติ เรียบร้อยแล้ว ✅');

                     } catch (\Exception $ex) {
                         DB::rollBack();
                         Log::error("Auto-Complete Failed: " . $ex->getMessage());
                         return redirect()->back()->with('error', "PU Rejected & Auto-Fix Failed: " . $ex->getMessage());
                     }
                }

                return redirect()->back()->with('error', "PU Rejected: " . $reason);
            }

            return redirect()->back()->with('success', 'ส่งข้อมูลไปยัง PU Hub เรียบร้อยแล้ว 🚀');

        } catch (\Exception $e) {
            Log::error("[ReceiveController::resend] Error: " . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    // ✅ New Search Method for Link Modal (No Quantity Filter)
    public function searchEquipment(Request $request) {
        $query = $request->input('q');
        if (strlen($query) < 2) return response()->json([]);

        $equipments = Equipment::with('unit')
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('part_no', 'LIKE', "%{$query}%")
                  ->orWhere('serial_number', 'LIKE', "%{$query}%");
            })
            ->limit(20)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'part_no' => $item->part_no,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit ? $item->unit->name : ''
                ];
            });

        return response()->json($equipments);
    }
    public function linkEquipment(Request $request, $poItemId)
    {
        $this->authorize('receive:manage');

        $request->validate([
            'equipment_id' => 'nullable|exists:equipments,id',
            'create_new' => 'nullable|boolean',
        ]);

        $poItem = PurchaseOrderItem::findOrFail($poItemId);

        if ($request->create_new) {
             // Redirect to create equipment page with pre-filled data (optional, or just go to create page)
             // For now, let's assume they go to create page manually and come back to link.
             return redirect()->route('equipments.create', ['from_po_item' => $poItemId]);
        }

        if ($request->equipment_id) {
            $equipment = \App\Models\Equipment::findOrFail($request->equipment_id);

            $poItem->equipment_id = $equipment->id;
            // ✅ Sync Description to match System Master
            $poItem->item_description = $equipment->name;
            // Fix: Safely access unit name (relationship)
            $poItem->unit_name = $equipment->unit ? $equipment->unit->name : 'ea';
            $poItem->save();
            
            Log::info("[ReceiveController] Linked PO Item #{$poItem->id} to Equipment #{$equipment->id} ({$equipment->name}) by User " . Auth::id());

            return redirect()->back()->with('success', 'เชื่อมโยงอุปกรณ์เรียบร้อยแล้ว');
        }

        return redirect()->back()->with('error', 'กรุณาเลือกอุปกรณ์ที่จะเชื่อมโยง');
    }
}