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

class ReceiveController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('receive:view');

        try {
            $purchaseOrders = PurchaseOrder::with([
                'items' => function ($query) {
                    $query->where(function ($q) {
                        $q->whereNull('quantity_received')
                          ->orWhereRaw('quantity_received < quantity_ordered');
                    })
                    ->with(['equipment.latestImage', 'equipment.unit'])
                    ->orderBy('item_description');
                },
                'orderedBy'
            ])
            ->whereIn('status', ['ordered', 'shipped_from_supplier', 'partial_receive', 'pending']) 
            ->whereHas('items', function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('quantity_received')
                      ->orWhereRaw('quantity_received < quantity_ordered');
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

             $currentDeptKey = Config::get('app.dept_key', 'it');
             $departmentsConfig = Config::get('department_stocks.departments', []);
             $currentDeptName = $departmentsConfig[$currentDeptKey]['name'] ?? strtoupper($currentDeptKey);

            return view('receive.index', compact('purchaseOrders', 'currentDeptName'));

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
                $inspectionStatus = $data['inspection_status'] ?? 'pass';
                $inspectionNotes = $data['inspection_notes'] ?? null;

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
                    
                    if (($alreadyReceived + $receiveNowQty) > $totalOrdered) {
                        $skippedItems[] = "รายการ '{$poItem->item_description}' รับเกินจำนวนที่สั่ง";
                        continue;
                    }

                    $equipment->increment('quantity', $receiveNowQty);
                    
                    Transaction::create([
                        'equipment_id'    => $equipment->id,
                        'user_id'         => Auth::id(),
                        'handler_id'      => Auth::id(),
                        'type'            => 'receive',
                        'quantity_change' => $receiveNowQty,
                        'notes'           => "รับของจาก PO #{$poId} ({$inspectionStatus})",
                        'transaction_date'=> now(),
                        'status'          => 'completed',
                        'admin_confirmed_at' => now(),
                        'user_confirmed_at' => now(),
                        'confirmed_at' => now(),
                    ]);

                    // อัปเดตจำนวนที่รับแล้วใน PO Item
                    $poItem->quantity_received = $alreadyReceived + $receiveNowQty;
                } else {
                    // กรณีของเสีย/ผิดรุ่น: บันทึก Log หรือ Transaction แบบ 0 qty เพื่อเป็นหลักฐานว่าตรวจแล้ว
                     Transaction::create([
                        'equipment_id'    => $equipment->id,
                        'user_id'         => Auth::id(),
                        'handler_id'      => Auth::id(),
                        'type'            => 'receive',
                        'quantity_change' => 0, // ไม่เพิ่มสต๊อก
                        'notes'           => "ปฏิเสธรับของ PO #{$poId}: {$inspectionStatus} - {$inspectionNotes}",
                        'transaction_date'=> now(),
                        'status'          => 'completed',
                        'admin_confirmed_at' => now(),
                        'user_confirmed_at' => now(),
                        'confirmed_at' => now(),
                    ]);
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
                    $remainingItemsCount = $purchaseOrder->items()
                        ->where(function ($q) {
                            $q->whereNull('quantity_received')
                              ->orWhereRaw('quantity_received < quantity_ordered');
                        })->count();

                    if ($remainingItemsCount == 0) {
                        $purchaseOrder->update(['status' => 'completed']);
                    } else {
                        $purchaseOrder->update(['status' => 'partial_receive']);
                    }
                }
            }

            DB::commit();

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
}