<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Setting; // Added for fetching settings
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;

// --- Use Statements for API Call ---
use Illuminate\Support\Facades\Http;
use App\Http\Resources\PurchaseOrderResource;
use Illuminate\Http\Client\ConnectionException;

class PurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('po:view');

        try {
            // --- ✅ START: แก้ไข Query ตรงนี้ (เปลี่ยน latestImage เป็น images) ---
            $scheduledOrder = PurchaseOrder::with([
                'items.equipment.category',
                'items.equipment.unit',
                // 'items.equipment.latestImage', // <-- ลบ/คอมเมนต์ บรรทัดนี้
                'items.equipment.images',      // <-- เพิ่มบรรทัดนี้
                'requester'
            ])
                ->where('type', 'scheduled')->where('status', 'pending')->first();

            $urgentOrders = PurchaseOrder::with([
                'items.equipment.category',
                'items.equipment.unit',
                // 'items.equipment.latestImage', // <-- ลบ/คอมเมนต์ บรรทัดนี้
                'items.equipment.images',      // <-- เพิ่มบรรทัดนี้
                'requester'
            ])
                ->where('type', 'urgent')->where('status', 'pending')->orderBy('created_at', 'desc')->get();

            $glpiOrders = PurchaseOrder::with([
                'items.equipment.category',
                'items.equipment.unit',
                // 'items.equipment.latestImage', // <-- ลบ/คอมเมนต์ บรรทัดนี้
                'items.equipment.images',      // <-- เพิ่มบรรทัดนี้
                'requester'
            ])
                ->where('type', 'job_order_glpi')->where('status', 'pending')->orderBy('created_at', 'desc')->get();

            $jobOrders = PurchaseOrder::with([
                'items.equipment.unit',
                // 'items.equipment.latestImage', // <-- ลบ/คอมเมนต์ บรรทัดนี้
                'items.equipment.images',      // <-- เพิ่มบรรทัดนี้
                'requester'
            ])
                ->where('type', 'job_order')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();
            // --- ✅ END: แก้ไข Query ---

            $defaultDeptKey = config('department_stocks.default_key', 'it');

            return view('purchase-orders.index', compact(
                'scheduledOrder',
                'urgentOrders',
                'jobOrders',
                'glpiOrders',
                'defaultDeptKey'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading Purchase Orders index page: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'ไม่สามารถโหลดข้อมูลใบสั่งซื้อได้ กรุณาตรวจสอบ Log File');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('po:manage');
        if ($purchaseOrder->status !== 'pending') {
            return back()->with('error', 'ไม่สามารถลบใบสั่งซื้อที่ดำเนินการไปแล้วได้');
        }
        try {
            DB::transaction(function () use ($purchaseOrder) {
                // อัปเดต: ใช้ Soft Delete สำหรับรายการย่อยก่อน
                // $purchaseOrder->items()->delete(); // <- แบบเก่า Hard Delete
                $purchaseOrder->items()->delete(); // <- แบบใหม่ Soft Delete (ถ้า PurchaseOrderItem ใช้ SoftDeletes trait)
                $purchaseOrder->delete();
            });
            return redirect()->route('purchase-orders.index')->with('success', 'ลบใบสั่งซื้อเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            Log::error("Error deleting PO #{$purchaseOrder->id}: " . $e->getMessage());
            return redirect()->route('purchase-orders.index')->with('error', 'ไม่สามารถลบใบสั่งซื้อได้: ' . $e->getMessage());
        }
    }

    // =============================================
    // Custom Actions
    // =============================================

    public function runStockCheck(Request $request)
    {
        $this->authorize('po:create');
        try {
            Artisan::call('app:check-low-stock');
            return redirect()->route('purchase-orders.index')->with('success', 'รันคำสั่งตรวจสอบสต็อกต่ำสำเร็จแล้ว!');
        } catch (\Exception $e) {
            Log::error('Manual stock check failed: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการรันคำสั่ง: ' . $e->getMessage());
        }
    }

    public function runGlpiSync(Request $request)
    {
        $this->authorize('po:create');
        try {
            Artisan::call('app:sync-glpi-tickets');
            return redirect()->route('purchase-orders.index')->with('success', 'รันคำสั่งตรวจสอบใบงาน GLPI สำเร็จ!');
        } catch (\Exception $e) {
            Log::error('Manual GLPI sync failed: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการรันคำสั่ง: ' . $e->getMessage());
        }
    }

    // --- Helper Function to Send PO Data ---
    private function sendPurchaseOrderToApi(PurchaseOrder $order, Request $request)
    {
        $puApiBaseUrl = config('services.pu_hub.base_url');
        $puApiToken = config('services.pu_hub.token');
        $puApiIntakePath = config('services.pu_hub.intake_path');

        if (empty($puApiBaseUrl) || empty($puApiToken) || empty($puApiIntakePath)) {
            Log::error('PU Hub API configuration missing in config/services.php or .env.');
            throw new \Exception('ตั้งค่า API สำหรับ PU Hub ไม่ถูกต้อง (กรุณาตรวจสอบ .env และ config/services.php)');
        }

        $fullApiUrl = rtrim($puApiBaseUrl, '/') . '/' . ltrim($puApiIntakePath, '/');

        // อัปเดต: โหลด 'items.equipment.unit' และ 'items.purchaseOrder' เพิ่มเติม
        $poData = new PurchaseOrderResource($order->loadMissing('items.equipment.unit', 'requester', 'items.purchaseOrder'));

        $response = Http::withToken($puApiToken)
            ->acceptJson()
            ->timeout(15)
            ->post($fullApiUrl, $poData->toArray($request));

        if (!$response->successful()) {
            $status = $response->status();
            $errorBody = $response->json() ? json_encode($response->json()) : $response->body();
            $errorMessage = "ID {$order->id} ({$order->type}) ล้มเหลว (Status: {$status}) - URL: {$fullApiUrl} - Response: " . $errorBody;
            Log::error("Failed to send PO to PU API. " . $errorMessage);
            throw new \Exception($errorMessage);
        }

        $order->status = 'ordered';
        $order->ordered_at = now();
        // ❗️ FIXED: Removed the line that was incorrectly overwriting the requester ID.
        // $order->ordered_by_user_id = Auth::id(); // This was the bug.
        $order->save();

        return $response->json();
    }


    public function submitScheduled(Request $request)
    {
        $this->authorize('po:manage');
        $scheduledOrder = PurchaseOrder::with(['items.equipment', 'requester'])
            ->where('type', 'scheduled')->where('status', 'pending')->whereHas('items')->first();

        if (!$scheduledOrder) {
            return back()->with('warning', 'ไม่มีใบสั่งซื้อตามรอบที่รอดำเนินการ');
        }

        // ✅ FIX FOR OLD POs: If the scheduled PO has no requester, assign one before sending.
        if (is_null($scheduledOrder->ordered_by_user_id)) {
            $defaultRequesterId = Setting::where('key', 'automation_requester_id')->value('value');
            if (!$defaultRequesterId) {
                return back()->with('error', 'ยังไม่ได้ตั้งค่าผู้สั่งอัตโนมัติ! กรุณาตั้งค่าก่อนส่งใบสั่งซื้อ');
            }
            $scheduledOrder->ordered_by_user_id = $defaultRequesterId;
            $scheduledOrder->save();
            $scheduledOrder->load('requester'); // Reload the relationship after updating
        }

        try {
            $this->sendPurchaseOrderToApi($scheduledOrder, $request);
            return redirect()->route('purchase-orders.index')->with('success', 'ส่งใบสั่งซื้อตามรอบ (ID: ' . $scheduledOrder->id . ') สำเร็จ');
        } catch (ConnectionException $e) {
            $errorMessage = "ID {$scheduledOrder->id} ล้มเหลว (Connection Error): ไม่สามารถเชื่อมต่อกับ PU Hub API ได้ - " . $e->getMessage();
            Log::error($errorMessage);
            return back()->with('error', $errorMessage);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }


    public function submitUrgent(Request $request)
    {
        $this->authorize('po:manage');
        $urgentOrders = PurchaseOrder::with(['items.equipment', 'requester'])
            ->where('type', 'urgent')->where('status', 'pending')->whereHas('items')->get();

        if ($urgentOrders->isEmpty()) {
            return back()->with('warning', 'ไม่มีใบสั่งซื้อด่วนที่รอดำเนินการ');
        }

        $successCount = 0;
        $failedPoDetails = [];

        foreach ($urgentOrders as $order) {
            try {
                $this->sendPurchaseOrderToApi($order, $request);
                $successCount++;
            } catch (ConnectionException $e) {
                $errorMessage = "ID {$order->id} ล้มเหลว (Connection Error): " . $e->getMessage();
                Log::error($errorMessage);
                $failedPoDetails[] = $errorMessage;
            } catch (\Exception $e) {
                $failedPoDetails[] = $e->getMessage();
            }
        }

        if (!empty($failedPoDetails)) {
            $detailedErrors = implode("<br>", $failedPoDetails);
            $message = "ส่งสำเร็จ {$successCount} รายการ <br><b>ล้มเหลว " . count($failedPoDetails) . " รายการ:</b> <br><small>" . $detailedErrors . "</small>";
            return redirect()->route('purchase-orders.index')->with('error', $message);
        }

        return redirect()->route('purchase-orders.index')->with('success', "ส่งใบสั่งซื้อด่วนทั้งหมด ({$successCount} รายการ) สำเร็จ");
    }


    public function submitJobOrders(Request $request)
    {
        $this->authorize('po:manage');

        $jobOrders = PurchaseOrder::with(['items.equipment', 'requester'])
            ->whereIn('type', ['job_order', 'job_order_glpi'])->where('status', 'pending')->whereHas('items')->get();

        if ($jobOrders->isEmpty()) {
            return back()->with('warning', 'ไม่มีใบสั่งซื้อตาม Job ที่รอดำเนินการ');
        }

        // ✅ FIX FOR OLD POs: If a job PO has no requester, assign one before sending.
        $defaultJobRequesterId = Setting::where('key', 'automation_job_requester_id')->value('value');
        if (!$defaultJobRequesterId) {
            return back()->with('error', 'ยังไม่ได้ตั้งค่าผู้สั่งตาม Job! กรุณาตั้งค่าก่อนส่งใบสั่งซื้อ');
        }

        $successCount = 0;
        $failedPoDetails = [];

        foreach ($jobOrders as $order) {
            try {
                if (is_null($order->ordered_by_user_id)) {
                    $order->ordered_by_user_id = $defaultJobRequesterId;
                    $order->save();
                    $order->load('requester');
                }

                $this->sendPurchaseOrderToApi($order, $request);
                $successCount++;
            } catch (ConnectionException $e) {
                 $errorMessage = "ID {$order->id} (Job) ล้มเหลว (Connection Error): " . $e->getMessage();
                Log::error($errorMessage);
                $failedPoDetails[] = $errorMessage;
            } catch (\Exception $e) {
                $failedPoDetails[] = $e->getMessage();
            }
        }

        if (!empty($failedPoDetails)) {
            $detailedErrors = implode("<br>", $failedPoDetails);
            $message = "ส่งสำเร็จ {$successCount} รายการ <br><b>ล้มเหลว " . count($failedPoDetails) . " รายการ:</b> <br><small>" . $detailedErrors . "</small>";
             return redirect()->route('purchase-orders.index')->with('error', $message);
        }

        return redirect()->route('purchase-orders.index')->with('success', "ส่งใบสั่งซื้อตาม Job ทั้งหมด ({$successCount} รายการ) สำเร็จ");
    }

    public function addItemToUrgent(Request $request, Equipment $equipment)
    {
        $this->authorize('po:create');
        try {
            $urgentPo = PurchaseOrder::firstOrCreate(
                ['type' => 'urgent', 'status' => 'pending'],
                ['notes' => 'ใบสั่งซื้อด่วน (สร้างจากหน้า Equipment)', 'ordered_by_user_id' => Auth::id()]
            );

            $item = $urgentPo->items()->where('equipment_id', $equipment->id)->first();
            if ($item) {
                $item->increment('quantity_ordered', 1);
            } else {
                $urgentPo->items()->create([
                    'equipment_id' => $equipment->id,
                    'item_description' => $equipment->name,
                    'quantity_ordered' => 1,
                    'requester_id' => Auth::id(),
                    'status' => 'pending',
                ]);
            }
            return back()->with('success', 'เพิ่ม "' . $equipment->name . '" ในใบสั่งซื้อด่วนสำเร็จ');
        } catch (\Exception $e) {
            Log::error("Error adding item to urgent PO: " . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    public function addItemToScheduled(Request $request, Equipment $equipment)
    {
        $this->authorize('po:create');
        $request->validate(['quantity' => 'required|integer|min:1']);
        $quantityToAdd = (int)$request->quantity;

        try {
            $scheduledPo = PurchaseOrder::firstOrCreate(
                ['type' => 'scheduled', 'status' => 'pending'],
                ['notes' => 'ใบสั่งซื้อตามรอบ (สร้าง/แก้ไขโดยผู้ใช้)', 'ordered_by_user_id' => Auth::id()]
            );

            $item = $scheduledPo->items()->where('equipment_id', $equipment->id)->first();
            if ($item) {
                $item->increment('quantity_ordered', $quantityToAdd);
            } else {
                $scheduledPo->items()->create([
                    'equipment_id' => $equipment->id,
                    'item_description' => $equipment->name,
                    'quantity_ordered' => $quantityToAdd,
                    'requester_id' => Auth::id(),
                    'status' => 'pending',
                ]);
            }
            return back()->with('success', 'เพิ่ม "' . $equipment->name . '" จำนวน ' . $quantityToAdd . ' ชิ้น ในใบสั่งซื้อตามรอบสำเร็จ');
        } catch (\Exception $e) {
            Log::error("Error adding item to scheduled PO: " . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    // =============================================
    // AJAX Methods
    // =============================================

    public function addItem(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('po:create');
        $validator = Validator::make($request->all(), [
            'equipment_id' => 'required|integer|exists:equipments,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $equipment = Equipment::find($request->equipment_id);
            if (!$equipment) {
                 return response()->json(['success' => false, 'message' => 'ไม่พบอุปกรณ์ ID: ' . $request->equipment_id], 404);
            }

            // อัปเดต: ใช้ Soft Delete ด้วย
            $item = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)
                ->where('equipment_id', $request->equipment_id)
                ->first(); // ไม่ต้องใช้ withTrashed() ตอนค้นหา ถ้าเจอตัวที่ยังไม่ลบ ก็ update ตัวนั้น

            $quantityToAdd = (int)$request->quantity;
            if ($item) {
                $item->increment('quantity_ordered', $quantityToAdd);
            } else {
                // อัปเดต: ใช้ updateOrCreate เพื่อป้องกันการสร้างซ้ำ หากมีรายการที่ถูก Soft Delete อยู่
                PurchaseOrderItem::updateOrCreate(
                    [
                        'purchase_order_id' => $purchaseOrder->id,
                        'equipment_id'      => $request->equipment_id,
                    ],
                    [
                        'quantity_ordered'  => $quantityToAdd,
                        'requester_id'      => auth()->id(),
                        'item_description'  => $equipment->name,
                        'status'            => 'pending',
                        'deleted_at'        => null, // บังคับให้ไม่ถูก Soft Delete
                    ]
                );
            }
            return response()->json([
                'success' => true,
                'message' => 'เพิ่ม/อัปเดต รายการในใบสั่งซื้อสำเร็จแล้ว'
            ]);
        } catch (\Exception $e) {
            Log::error("Exception caught when adding item to PO #{$purchaseOrder->id}: " . $e->getMessage(), ['request' => $request->all()]);
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }


    public function getItemsView(PurchaseOrder $order)
    {
        $this->authorize('po:view');
        // --- ✅ START: แก้ไขการ Load ตรงนี้ (เปลี่ยนเป็น images และใช้ withTrashed) ---
        $order->load(['items' => function ($query) {
            $query->with(['equipment' => function ($eqQuery) {
                // โหลด Equipment ที่อาจถูกลบ และโหลด unit กับ images collection ของมัน
                $eqQuery->withTrashed()->with(['unit', 'images']);
            }]);
        }]);
        // --- ✅ END: แก้ไขการ Load ---

        $defaultDeptKey = config('department_stocks.default_key', 'it');
        return view('purchase-orders.partials._po_items_table_glpi', compact('order', 'defaultDeptKey'));
    }


    public function ajaxRemoveItem(PurchaseOrderItem $item)
    {
        $this->authorize('po:manage');

        if ($item->purchaseOrder->status !== 'pending') {
             return response()->json(['success' => false, 'message' => 'ไม่สามารถลบรายการจากใบสั่งซื้อที่ดำเนินการไปแล้วได้'], 403);
        }

        try {
            // อัปเดต: ใช้ Soft Delete แทน Hard Delete
            $item->delete(); // This performs a soft delete
            return response()->json(['success' => true, 'message' => 'ลบรายการสำเร็จแล้ว']);
        } catch (\Exception $e) {
            Log::error("Error AJAX removing PO Item #{$item->id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }
}

