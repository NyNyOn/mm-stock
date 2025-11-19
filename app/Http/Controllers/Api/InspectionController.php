<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Exception;

// เราจะ Import ไฟล์นี้ไว้รอเลย (จะสร้างใน Part 4)
use App\Http\Requests\Api\SubmitInspectionRequest;

class InspectionController extends Controller
{
    /**
     * (ขารับ) รับข้อมูลการตรวจสอบสินค้า (Inspection) แบบ Batch
     * (นี่คือ Method สำหรับ Route 'inspections.submit')
     */
    public function submit(SubmitInspectionRequest $request)
    {
        $validated = $request->validated();
        $updatedItems = [];
        $failedItems = [];

        try {
            DB::beginTransaction();

            foreach ($validated['inspections'] as $inspectionData) {
                
                $item = PurchaseOrderItem::find($inspectionData['pr_item_id']);
                
                if ($item) {
                    $item->update([
                        // อัปเดต field ที่เรา migrate ใน Part 1 (แก้ไข)
                        'inspection_status' => $inspectionData['status'],
                        'inspection_notes'  => $inspectionData['notes'] ?? null,
                        
                        // อัปเดต field ที่มีอยู่แล้วในตาราง
                        'quantity_received' => $inspectionData['received_quantity'], 
                    ]);
                    
                    // (เราสามารถเพิ่ม Logic อัปเดตสถานะ PO หลักได้ที่นี่ ถ้าจำเป็น)

                    $updatedItems[] = $item->id;
                } else {
                    $failedItems[] = $inspectionData['pr_item_id'];
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Inspection results submitted successfully.',
                'updated_item_ids' => $updatedItems,
                'failed_item_ids' => $failedItems,
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to submit inspection results.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}