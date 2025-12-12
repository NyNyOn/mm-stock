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
}