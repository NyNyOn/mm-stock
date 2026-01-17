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
                          ->orWhereRaw('quantity_received < quantity_ordered');
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
                      ->orWhereRaw('quantity_received < quantity_ordered');
                })->whereNotIn('status', ['returned', 'cancelled', 'rejected', 'inspection_failed']); // ✅ Apply same filter to PO detection
            })
            ->orderBy('created_at', 'desc')
            ->get();

             $currentDeptKey = Config::get('app.dept_key', 'it');
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

        DB::beginTransaction();
        
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
                    // Count items that are NOT fully handled yet
                    // Handled = (received >= ordered) OR (status is returned/inspection_failed)
                    $pendingItemsCount = $purchaseOrder->items()
                        ->where(function ($q) {
                            // Conditions for being "Pending":
                            // 1. Not yet received enough quantity
                            $q->whereRaw('ifnull(quantity_received, 0) < quantity_ordered')
                              // 2. AND Status is NOT in a "Finalized Rejection" state
                              ->whereNotIn('status', ['returned', 'inspection_failed', 'cancelled', 'rejected']);
                        })->count();

                    if ($pendingItemsCount == 0) {
                        $purchaseOrder->update(['status' => 'completed']);
                    } else {
                        // If logic was previously completed but now we found pending (unlikely in this flow but safe)
                        // Or just to set partial
                        if ($purchaseOrder->status != 'completed') {
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

                    $inspections[] = [
                        'pr_item_id' => $poItem->pr_item_id,
                        'status' => $status,
                        'received_quantity' => $currentBatchQty, 
                        'notes' => $finalNotes
                    ];
                }

                if (!empty($inspections)) {
                    $result = $puHubService->confirmInspectionBatch($inspections);
                    
                    // Check for failures in the response
                    if (!empty($result['results']['failed'])) {
                        $failedCount = count($result['results']['failed']);
                        $failedItems = collect($result['results']['failed'])->pluck('reason', 'pr_item_id')->toArray();
                        
                        Log::warning('[ReceiveController] PU-HUB rejected some validations', ['failed' => $failedItems]);
                        
                        // Append warning to session
                        session()->flash('warning', "บันทึกสำเร็จ แต่ PU-HUB แจ้งเตือนข้อผิดพลาด {$failedCount} รายการ (โปรดติดต่อฝ่ายจัดซื้อ)");
                    } else {
                         Log::info('[ReceiveController] Successfully sent inspection results to PU-HUB', [
                            'count' => count($inspections)
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('[ReceiveController] Failed to send inspection results to PU-HUB: ' . $e->getMessage());
                session()->flash('warning', "บันทึกในระบบสำเร็จ แต่ไม่สามารถส่งข้อมูลไปยัง PU-HUB ได้ (Error: {$e->getMessage()})");
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
}