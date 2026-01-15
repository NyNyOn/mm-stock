<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Equipment;
use App\Models\Transaction;
use App\Services\PuHubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseInspectionController extends Controller
{
    protected PuHubService $puHub;

    public function __construct(PuHubService $puHub)
    {
        $this->puHub = $puHub;
    }

    /**
     * แสดงหน้ารายการของที่รอตรวจสอบ
     */
    public function index()
    {
        // ดึงรายการ PO ที่มีสินค้ามาถึงแล้ว (status = shipped_from_supplier หรือ arrived)
        $purchaseOrders = PurchaseOrder::with(['items.equipment', 'orderedBy'])
            ->whereIn('status', ['shipped_from_supplier', 'arrived_at_hub'])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return view('admin.purchase-inspections.index', compact('purchaseOrders'));
    }

    /**
     * รับผลการตรวจสอบและอัปเดตสต็อก
     */
    public function confirmBatch(Request $request)
    {
        $request->validate([
            'inspections' => 'required|array',
            'inspections.*.item_id' => 'required|exists:purchase_order_items,id',
            'inspections.*.status' => 'required|in:accepted,rejected',
            'inspections.*.received_quantity' => 'required|integer|min:0',
            'inspections.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $results = [];
            $puHubInspections = []; // สำหรับส่งกลับ PU-HUB

            foreach ($request->inspections as $inspection) {
                $item = PurchaseOrderItem::with(['equipment', 'purchaseOrder'])->findOrFail($inspection['item_id']);
                
                // อัปเดตข้อมูลการตรวจสอบ
                $item->update([
                    'inspection_status' => $inspection['status'],
                    'quantity_received' => $inspection['received_quantity'],
                    'inspection_notes' => $inspection['notes'] ?? null,
                    'status' => $inspection['status'], // accepted หรือ rejected
                ]);

                // ✅ ถ้า Accept → เพิ่มสต็อกอัตโนมัติ
                if ($inspection['status'] === 'accepted' && $item->equipment_id) {
                    $equipment = Equipment::lockForUpdate()->find($item->equipment_id);
                    
                    if ($equipment) {
                        // 1. เพิ่มจำนวนสต็อก
                        $equipment->increment('quantity', $inspection['received_quantity']);

                        // 2. สร้าง Transaction Log
                        Transaction::create([
                            'equipment_id' => $equipment->id,
                            'user_id' => Auth::id(),
                            'handler_id' => Auth::id(),
                            'type' => 'receive',
                            'quantity_change' => $inspection['received_quantity'],
                            'notes' => "รับจาก PO: {$item->purchaseOrder->po_number} (PR Item ID: {$item->id})",
                            'transaction_date' => now(),
                            'status' => 'completed',
                            'confirmed_at' => now(),
                            'admin_confirmed_at' => now(),
                        ]);

                        Log::info("[Inspection] Accepted item #{$item->id}, added {$inspection['received_quantity']} to Equipment #{$equipment->id}");
                    }
                }

                // เตรียมข้อมูลส่งกลับ PU-HUB
                // หมายเหตุ: ถ้า PU-HUB ใช้ pr_item_id ต้องเก็บไว้ใน purchase_order_items
                // สมมติว่าเก็บไว้ใน field 'reference_link' หรือ field ใหม่
                $puHubInspections[] = [
                    'pr_item_id' => $item->id, // ใช้ ID ของเราไปก่อน (อาจต้องแก้)
                    'status' => $inspection['status'],
                    'received_quantity' => $inspection['received_quantity'],
                    'notes' => $inspection['notes'] ?? '',
                ];

                $results[] = [
                    'item_id' => $item->id,
                    'status' => $inspection['status'],
                    'equipment_name' => $item->equipment->name ?? $item->item_description,
                ];
            }

            // ส่งผลกลับไปยัง PU-HUB
            try {
                $puHubResponse = $this->puHub->confirmInspectionBatch($puHubInspections);
                Log::info('[Inspection] Successfully sent results to PU-HUB', $puHubResponse);
            } catch (\Exception $e) {
                Log::error('[Inspection] Failed to send to PU-HUB: ' . $e->getMessage());
                // ไม่ rollback เพราะเรายังต้องการบันทึกข้อมูลในระบบเรา
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'บันทึกผลการตรวจสอบเรียบร้อยแล้ว',
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Inspection] Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }
}
