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


     // =============================================
    // ✅ Auto-Create PO Logic (Scheduled & Urgent)
    // =============================================

    public function createScheduledPO(Request $request)
    {
        $this->authorize('po:create');
        
        try {
            // Check for existing pending scheduled PO
            $existingPO = PurchaseOrder::where('type', 'scheduled')
                ->where('status', 'pending')
                ->latest()
                ->first();
                
            if ($existingPO) {
                return response()->json([
                    'success' => true,
                    'po_id' => $existingPO->id,
                    'message' => 'Using existing scheduled PO'
                ]);
            }
            
            // Create new Scheduled PO
            $poNumber = 'PO-SCH-' . date('Ymd') . '-' . strtoupper(uniqid());
            
            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'type' => 'scheduled',
                'status' => 'pending',
                'ordered_by_user_id' => auth()->id() ?? 1, // Default or current user
                'ordered_at' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'po_id' => $po->id,
                'message' => 'Created new scheduled PO'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Create Scheduled PO failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createUrgentPO(Request $request)
    {
        $this->authorize('po:create');
        
        try {
            // Urgent POs are always new (or you can logic to group them if needed)
            // Here we assume create new one for specific urgent request
            $poNumber = 'PO-URG-' . date('Ymd') . '-' . strtoupper(uniqid());
            
            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'type' => 'urgent',
                'status' => 'pending',
                'ordered_by_user_id' => auth()->id() ?? 1,
                'ordered_at' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'po_id' => $po->id,
                'message' => 'Created new urgent PO'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Create Urgent PO failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Display a listing of the resource.
    public function index()
    {
        $this->authorize('po:view');

        try {
            // Helper to exclude resubmitted items (which belong in tracking)
            $excludeResubmit = function($q) {
                $q->where(function($sub) {
                    $sub->whereNull('pu_data->is_resubmit')
                        ->orWhere('pu_data->is_resubmit', '!=', true);
                });
            };

            $scheduledOrder = PurchaseOrder::with([
                'items.equipment.category',
                'items.equipment.unit',
                'items.equipment.images',
                'requester'
            ])
                ->where('type', 'scheduled')->where('status', 'pending')
                ->where($excludeResubmit)
                ->first();

            $urgentOrders = PurchaseOrder::with([
                'items.equipment.category',
                'items.equipment.unit',
                'items.equipment.images',
                'requester'
            ])
                ->where('type', 'urgent')->where('status', 'pending')
                ->where($excludeResubmit)
                ->orderBy('created_at', 'desc')->get();

            $glpiOrders = PurchaseOrder::with([
                'items.equipment.category',
                'items.equipment.unit',
                'items.equipment.images',
                'requester'
            ])
                ->where('type', 'job_order_glpi')->where('status', 'pending')
                ->where($excludeResubmit)
                ->orderBy('created_at', 'desc')->get();

            $jobOrders = PurchaseOrder::with([
                'items.equipment.unit',
                'items.equipment.images',
                'requester'
            ])
                ->where('type', 'job_order')
                ->where('status', 'pending')
                ->where($excludeResubmit)
                ->orderBy('created_at', 'desc')
                ->get();
            // --- ✅ END: Filtered Out Resubmitted Items ---

            $defaultDeptKey = config('department_stocks.default_key', 'mm');

        // ✅ Get Auto PO Schedule Settings
        $autoPoScheduleDay = \App\Models\Setting::where('key', 'auto_po_schedule_day')->value('value') ?? 24;
        $autoPoScheduleTime = \App\Models\Setting::where('key', 'auto_po_schedule_time')->value('value') ?? '23:50';

        // ✅ Get PU Deadline Settings (from PU Hub)
        $puDeadlineDay = \App\Models\Setting::where('key', 'pu_deadline_day')->value('value');
        $puDeadlineTime = \App\Models\Setting::where('key', 'pu_deadline_time')->value('value');

        return view('purchase-orders.index', compact(
            'scheduledOrder',
            'urgentOrders',
            'jobOrders',
            'glpiOrders',
            'defaultDeptKey',
            'autoPoScheduleDay',
            'autoPoScheduleTime',
            'puDeadlineDay',
            'puDeadlineTime'
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
                // อัปเดต: ใช้ Soft Delete สำหรับรายการย่อยก่อน (จริงๆ item ลบจริงเพราะไม่มี SoftDeletes)
                $purchaseOrder->items()->delete(); 
                // ✅ Fix: Use forceDelete to permanently remove the record (User Request)
                $purchaseOrder->forceDelete();
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
            // ✅ Use new command with --draft-only to just populate the list (no auto-submit)
            Artisan::call('stock:monthly-check', ['--draft-only' => true]);
            return redirect()->route('purchase-orders.index')->with('success', 'รันคำสั่งตรวจสอบสต็อกต่ำสำเร็จแล้ว!');
        } catch (\Exception $e) {
            Log::error('Manual stock check failed: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการรันคำสั่ง: ' . $e->getMessage());
        }
    }

    public function runGlpiSync(Request $request)
    {
        $this->authorize('po:create');

        // Validation: Check if Default Requester is set
        $jobRequesterId = \App\Models\Setting::where('key', 'automation_job_requester_id')->value('value');
        if (!$jobRequesterId) {
             return back()->with('error', 'กรุณาตั้งชื่อผู้ทำรายการก่อนในปุ่มตั้งค่าผู้สั่ง');
        }

        try {
            Artisan::call('app:sync-glpi-tickets');
            return redirect()->route('purchase-orders.index')->with('success', 'รันคำสั่งตรวจสอบใบงาน GLPI สำเร็จ!');
        } catch (\Exception $e) {
            Log::error('Manual GLPI sync failed: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการรันคำสั่ง: ' . $e->getMessage());
        }
    }

    // --- Helper Function to Send PO Data ---
    public function sendPurchaseOrderToApi(PurchaseOrder $order, Request $request, bool $suppressNotification = false)
    {
        // 1. ตรวจสอบว่าเปิดใช้งาน API หรือไม่ (Bypass Check)
        $apiEnabled = config('services.pu_hub.enabled', true);
        
        // แปลงค่า string '0' จาก DB ให้เป็น boolean false
        if ($apiEnabled === '0' || $apiEnabled === 0 || $apiEnabled === false || $apiEnabled === 'false') {
            Log::warning("PU Hub API is DISABLED. Bypassing API call for PO #{$order->id}. Order will be marked as ordered locally.");
            
            // Bypass: ทำเหมือนส่งสำเร็จ แต่ไม่ได้ส่งจริง
            $order->status = 'ordered';
            $order->ordered_at = now();
            $order->save();

            return ['success' => true, 'message' => 'API is disabled. Order marked as ordered locally (Bypassed).'];
        }

        // --- ถ้าเปิดใช้งาน API ก็ทำตาม Logic เดิม ---
        $puApiBaseUrl = config('services.pu_hub.base_url');
        $puApiToken = config('services.pu_hub.token');
        $puApiIntakePath = config('services.pu_hub.intake_path');

        if (empty($puApiBaseUrl) || empty($puApiToken) || empty($puApiIntakePath)) {
            Log::error('PU Hub API configuration missing in config/services.php or .env.');
            throw new \Exception('ตั้งค่า API สำหรับ PU Hub ไม่ถูกต้อง (กรุณาตรวจสอบ .env และ config/services.php)');
        }

        // Logic การหา Department ID (เหมือนเดิม)
        $originDeptId = null;

        // 1. Setting "ผู้สั่งอัตโนมัติ" (เฉพาะ Scheduled เท่านั้น)
        if ($order->type === 'scheduled') {
            $autoRequesterId = Setting::where('key', 'automation_requester_id')->value('value');
            if ($autoRequesterId) {
                $autoUser = \App\Models\User::find($autoRequesterId);
                if ($autoUser && !empty($autoUser->department_id)) {
                    $originDeptId = $autoUser->department_id;
                }
            }
        }

        // 2. Setting "ผู้สั่งตาม Job"
        if (empty($originDeptId)) {
            $jobRequesterId = Setting::where('key', 'automation_job_requester_id')->value('value');
            if ($jobRequesterId) {
                $jobUser = \App\Models\User::find($jobRequesterId);
                if ($jobUser && !empty($jobUser->department_id)) {
                    $originDeptId = $jobUser->department_id;
                }
            }
        }

        // 3. User ที่ทำรายการ
        if (empty($originDeptId)) {
            $order->loadMissing('requester');
            if ($order->requester && !empty($order->requester->department_id)) {
                $originDeptId = $order->requester->department_id;
            }
        }

        // 4. User Login
        if (empty($originDeptId) && Auth::check()) {
            if (!empty(Auth::user()->department_id)) {
                $originDeptId = Auth::user()->department_id;
            }
        }

        // 5. Fallback จาก Config
        if (empty($originDeptId)) {
            $originDeptId = config('services.pu_hub.origin_department_id');
        }

        // ถ้าหาไม่เจอจริงๆ
        if (empty($originDeptId)) {
            $relatedUserId = $order->ordered_by_user_id ?? 'Unknown';
            throw new \Exception("ไม่สามารถระบุรหัสแผนกต้นทาง (Origin Department ID) ได้เลย! \n(User ID ที่เกี่ยวข้อง: {$relatedUserId}) \nสาเหตุ: ผู้ใช้รายนี้ไม่มีข้อมูลแผนก และยังไม่ได้กำหนดค่า 'Default Origin Department ID' ในหน้าตั้งค่า API (Management > Tokens)");
        }

        $fullApiUrl = rtrim($puApiBaseUrl, '/') . '/' . ltrim($puApiIntakePath, '/');

        // อัปเดต: โหลด 'items.equipment.unit' และ 'items.purchaseOrder' เพิ่มเติม
        $poData = new PurchaseOrderResource($order->loadMissing('items.equipment.unit', 'requester', 'items.purchaseOrder'));

        // แปลงข้อมูลเป็น Array
        $payload = $poData->toArray($request);
        
        // ✅ Fix: Force 'items' to be a plain array (Resolve ResourceCollection)
        if (isset($payload['items']) && !is_array($payload['items'])) {
            $payload['items'] = json_decode(json_encode($payload['items']), true);
        }

        $payload['origin_department_id'] = $originDeptId;
        $payload['requestor_user_id'] = $order->ordered_by_user_id ?? Auth::id(); // ✅ Phase 1 Requirement

        // ✅✅✅ Resubmit Logic: Update Existing PR ✅✅✅
        if (isset($order->pu_data['is_resubmit']) && $order->pu_data['is_resubmit'] == true) {
            $payload['is_resubmit'] = true;
            
            // กรณีเป็นการแก้ไขใบเดิม (Status cancel -> pending -> ordered)
            // ให้ส่ง pr_code เดิมกลับไปด้วย เพื่อให้ PU อัปเดตใบเดิมแทนการสร้างใหม่
            if (!empty($order->pr_number)) {
                $payload['pr_code'] = $order->pr_number;
                Log::info("Sending Resubmit/Update for Existing PR: " . $order->pr_number);
            }

            // ✅ FILTER REJECTED ITEMS (Code 3)
            // User Req: If Code 3 (Substitution) or Rejected, send ONLY specific items that were rejected.
            // Assumption: 'rejection_code' persists on the item even after status reset to 'pending'.
            $itemsPayload = collect($payload['items']);
            $originalItems = $order->items; // Should match order of resource collection

            // Find items that have a rejection code
            $rejectedIndices = [];
            foreach ($originalItems as $index => $item) {
                // ✅ FILTER: Only include items with Rejection Code 3 (Unclear/Fixable) or similar.
                // Exclude Fatal Codes: 1 (Not Needed), 2 (No Budget), 4 (Substitute - handled elsewhere?)
                // User explicitly requested NOT to send Code 1 & 2 again.
                // We allow Code 3 (Unclear) or 0/Null if it was somehow rejected without specific code but is being resubmitted.
                if (!empty($item->rejection_code) && !in_array((int)$item->rejection_code, [1, 2, 4])) {
                    $rejectedIndices[] = $index;
                }
            }

            if (!empty($rejectedIndices)) {
                $filteredItems = $itemsPayload->only($rejectedIndices)->values()->all();
                
                // ✅ Inject Note into Items (for Item-level visibility)
                if ($request->filled('resubmit_note')) {
                    $noteContent = "(" . $request->input('resubmit_note') . ")"; // Format: (User Message)
                    foreach ($filteredItems as &$fItem) {
                        $fItem['note'] = $noteContent;   // ✅ Spec: "Send as note" (Singular)
                        $fItem['notes'] = $noteContent;  // Legacy/Backup
                        // Also add legacy field if needed
                        $fItem['resubmit_reason'] = $noteContent;
                    }
                }

                $payload['items'] = $filteredItems;
                Log::info("Resubmit: Filtered payload to " . count($filteredItems) . " rejected items.");
            }

            // ✅ ATTACH USER NOTE
            // Map 'resubmit_note' from request to 'note' field for PU visibility
            if ($request->filled('resubmit_note')) {
                $formattedNote = "(" . $request->input('resubmit_note') . ")";
                $payload['note'] = $formattedNote;
                $payload['resubmit_note'] = $formattedNote; // Keep both just in case
            }
        }

        // ✅✅✅ Priority Mapping: แปลงค่า Priority ให้ตรงกับที่ API ต้องการ ✅✅✅
        // ดึงค่า Mapping จาก DB (Setting) เป็นหลัก ถ้าไม่มีให้ใช้ Config
        $priorityConfig = [
            'scheduled'      => Setting::where('key', 'pu_api_priority_scheduled')->value('value') ?? config('services.pu_hub.priorities.scheduled', 'Scheduled'),
            'urgent'         => Setting::where('key', 'pu_api_priority_urgent')->value('value') ?? config('services.pu_hub.priorities.urgent', 'Urgent'),
            'job_order'      => Setting::where('key', 'pu_api_priority_job')->value('value') ?? config('services.pu_hub.priorities.job', 'Job'),
            'job_order_glpi' => Setting::where('key', 'pu_api_priority_job')->value('value') ?? config('services.pu_hub.priorities.job', 'Job'),
        ];

        // ตรวจสอบว่า order type ปัจจุบันตรงกับ Key ไหนใน Mapping หรือไม่
        if (array_key_exists($order->type, $priorityConfig)) {
            // ทับค่า priority ใน payload ด้วยค่าที่ถูกต้อง
            $payload['priority'] = $priorityConfig[$order->type];
        } else {
            // กรณีไม่เจอใน Map ให้ใช้ค่าเดิมแต่ปรับตัวแรกเป็นพิมพ์ใหญ่ (Fallback)
            $payload['priority'] = ucfirst($order->type);
        }

        // ✅ Log Payload เพื่อการ Debug (จะแสดงใน storage/logs/laravel.log)
        Log::info("Sending PO #{$order->id} to PU API.", [
            'payload_priority_sent' => $payload['priority'],
            'payload_origin_dept' => $payload['origin_department_id'],
            'note' => $payload['note'] ?? null, // ✅ Show Note in Log
            'items_count' => count($payload['items'] ?? []), // ✅ Show Items Count
            'items_preview' => array_map(function($i) { return ['id' => $i['id'] ?? '?', 'name' => $i['item_name'] ?? '?', 'note' => $i['note'] ?? '']; }, $payload['items'] ?? []), // ✅ Preview Items (Updated key)
            'mapped_config' => $priorityConfig
        ]);

        $response = Http::withToken($puApiToken)
            ->acceptJson()
            ->timeout(15)
            ->post($fullApiUrl, $payload); // ส่ง Payload ที่แก้ไขแล้ว

        if (!$response->successful()) {
            $status = $response->status();
            $errorBody = $response->json() ? json_encode($response->json()) : $response->body();
            // เพิ่มข้อมูล Payload ใน Error Message เพื่อให้ User เห็นว่าส่งอะไรไป
            $errorMessage = "ID {$order->id} ({$order->type}) ล้มเหลว (Status: {$status}) - ส่งค่า Priority: '{$payload['priority']}' - Response: " . $errorBody;
            Log::error("Failed to send PO to PU API. " . $errorMessage);
            throw new \Exception($errorMessage);
        }

        // ✅ Prevent Status Rewind: Only set to 'ordered' if currently pending or starting fresh.
        // If already shipped/completed, keep the current status (just a data sync).
        $advancedStatuses = ['shipped_from_supplier', 'partial_receive', 'completed', 'inspection_failed', 'returned'];
        if (!in_array($order->status, $advancedStatuses)) {
            $order->status = 'ordered';
            $order->ordered_at = now();
        }
        // ❗️ FIXED: Removed the line that was incorrectly overwriting the requester ID.
        // $order->ordered_by_user_id = Auth::id(); // This was the bug.

        // ✅ Capture PO Number/Code from API Response
        $responseData = $response->json();
        
        // DEBUG: Log the full response to see why we are missing po_code
        Log::info("PU API Response for PO #{$order->id}: ", $responseData);

        // Store full response data (MERGE to keep is_resubmit/history)
        $order->pu_data = array_merge($order->pu_data ?? [], $responseData ?? []);

        // Determine PR Number
        if (isset($responseData['pr_code'])) {
            $order->pr_number = $responseData['pr_code'];
        }

        // Determine PO Number
        if (isset($responseData['po_code'])) {
            $order->po_number = $responseData['po_code'];
        } elseif (isset($responseData['po_number'])) {
            $order->po_number = $responseData['po_number'];
        }
        
        // If we only got a PR code and no PO code yet, we ensure PO number is NULL (or keep existing if partial update)
        // However, standard flow is: Request -> PR -> PO.
        // So initially we might only get PR.
        // If the user previously had a PO number (unlikely in this flow), we don't want to wipe it unless we are sure.
        
        // If we only got a PR code and no PO code yet, we ensure PO number is NULL (or keep existing if partial update)
        // No explicit wipe of po_number here to be safe.

        $order->save();
        
        // ✅ บันทึก History Log: PR Sent to PU
        $prCode = $order->pr_number ?? 'N/A';
        $itemCount = $order->items()->count();
        $isResubmit = ($order->pu_data['is_resubmit'] ?? false) ? 'ส่งใหม่' : 'ส่งครั้งแรก';
        $this->addPuHistoryLog($order, 'PR Sent', "{$isResubmit} → PU (PR: {$prCode}, {$itemCount} รายการ)");

        // ✅ MAP PR ITEM IDs: Update pr_item_id from API Response
        if (isset($responseData['items']) && is_array($responseData['items'])) {
            $localItems = $order->items()->orderBy('id')->get(); // Matches sent order (assuming ID order)
            
            foreach ($responseData['items'] as $index => $remoteItem) {
                // Try to find local item by 'external_id' (if PU returns it)
                // We sent 'id' => $this->id in Resource, so PU *might* return it as 'external_id' or 'reference_id' or just 'id'? 
                // Wait, 'id' in response is likely PU's ID.
                
                $matchedItem = null;
                
                // Method A: Match by explicit ID ref (if available)
                if (isset($remoteItem['external_id'])) {
                    $matchedItem = $localItems->where('id', $remoteItem['external_id'])->first();
                }
                
                // Method B: Match by Index Order (Fallback)
                if (!$matchedItem && isset($localItems[$index])) {
                    $matchedItem = $localItems[$index];
                }

                // ✅ FIX: Use 'pr_item_id' from response (based on logs)
                $remotePrItemId = $remoteItem['pr_item_id'] ?? $remoteItem['id'] ?? null;

                if ($matchedItem && $remotePrItemId) {
                    $matchedItem->pr_item_id = $remotePrItemId;
                    
                    // ✅ Sync Status from PU Response (Immediate Update)
                    if (isset($remoteItem['status'])) {
                        $remoteStatus = strtolower($remoteItem['status']);
                        
                        // Map PU Status to Local Status
                        if (in_array($remoteStatus, ['rejected', 'cancelled'])) {
                            $matchedItem->status = 'cancelled';
                        } elseif ($remoteStatus === 'approved') {
                            $matchedItem->status = 'ordered'; 
                        } elseif ($remoteStatus === 'pending') {
                             // Keep as ordered if parent is ordered, or pending. 
                             // Usually sendPurchaseOrderToApi sets parent to 'ordered'.
                             $matchedItem->status = 'ordered';
                        }
                    }

                    $matchedItem->save();
                    // Log::info("Mapped Local Item #{$matchedItem->id} to PR Item ID: {$remotePrItemId} (Status: {$matchedItem->status})");
                }
            }
        }

        // 🔔 Notification: PU Received & PR/PO Assigned (Sync)
        if (!$suppressNotification) {
            try {
                (new \App\Services\SynologyService())->notify(
                    new \App\Notifications\PurchaseOrderUpdatedNotification($order, 'ordered')
                );
            } catch (\Exception $e) { Log::error("Notify PU Sync Error: " . $e->getMessage()); }
        }

        return [
            'success' => true,
            'message' => 'ส่งข้อมูลไปยัง PU Hub เรียบร้อยแล้ว',
            'data' => $response->json()
        ];
    }


    public function submitScheduled(Request $request)
    {
        $this->authorize('po:manage');
        $scheduledOrder = PurchaseOrder::with(['items.equipment', 'requester'])
            ->where('type', 'scheduled')->where('status', 'pending')->whereHas('items')->first();

        if (!$scheduledOrder) {
            return back()->with('warning', 'ไม่มีใบสั่งซื้อตามรอบที่รอดำเนินการ');
        }

        // ✅ ENFORCE FOR SCHEDULED POs: Always set requester to the Auto/System User
        $defaultRequesterId = Setting::where('key', 'automation_requester_id')->value('value');
        if (!$defaultRequesterId) {
            return back()->with('error', 'ยังไม่ได้ตั้งค่าผู้สั่งอัตโนมัติ! กรุณาตั้งค่าก่อนส่งใบสั่งซื้อ');
        }

        // Force update the requester to the system user (even if a human added items)
        $scheduledOrder->ordered_by_user_id = $defaultRequesterId;
        $scheduledOrder->save();
        $scheduledOrder->load('requester'); // Reload the relationship

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

    public function submitSingleJobOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('po:manage');

        if ($purchaseOrder->status !== 'pending') {
            return back()->with('error', 'ใบสั่งซื้อนี้ไม่ได้อยู่ในสถานะรอดำเนินการ');
        }

        // ✅ FIX FOR SINGLE PO: If no requester, assign one before sending.
        $defaultJobRequesterId = Setting::where('key', 'automation_job_requester_id')->value('value');
        
        if (is_null($purchaseOrder->ordered_by_user_id)) {
            if (!$defaultJobRequesterId) {
                return back()->with('error', 'ยังไม่ได้ตั้งค่าผู้สั่งตาม Job! กรุณาตั้งค่าก่อนส่งใบสั่งซื้อ');
            }
            $purchaseOrder->ordered_by_user_id = $defaultJobRequesterId;
            $purchaseOrder->save();
            $purchaseOrder->load('requester');
        }

        try {
            $this->sendPurchaseOrderToApi($purchaseOrder, $request);
            return back()->with('success', "ส่งใบสั่งซื้อ ID: {$purchaseOrder->id} (Job) สำเร็จ");
        } catch (ConnectionException $e) {
            $errorMessage = "ID {$purchaseOrder->id} ล้มเหลว (Connection Error): ไม่สามารถเชื่อมต่อกับ PU Hub API ได้ - " . $e->getMessage();
            Log::error($errorMessage);
            return back()->with('error', $errorMessage);
        } catch (\Exception $e) {
            return back()->with('error', "เกิดข้อผิดพลาด: " . $e->getMessage());
        }
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
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'เพิ่ม "' . $equipment->name . '" ในใบสั่งซื้อด่วนสำเร็จ',
                    'po_id' => $urgentPo->id
                ]);
            }
            return back()->with('success', 'เพิ่ม "' . $equipment->name . '" ในใบสั่งซื้อด่วนสำเร็จ');
        } catch (\Exception $e) {
            Log::error("Error adding item to urgent PO: " . $e->getMessage());
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
            }
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
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'เพิ่ม "' . $equipment->name . '" จำนวน ' . $quantityToAdd . ' ชิ้น ในใบสั่งซื้อตามรอบสำเร็จ',
                    'po_id' => $scheduledPo->id
                ]);
            }
            return back()->with('success', 'เพิ่ม "' . $equipment->name . '" จำนวน ' . $quantityToAdd . ' ชิ้น ในใบสั่งซื้อตามรอบสำเร็จ');
        } catch (\Exception $e) {
            Log::error("Error adding item to scheduled PO: " . $e->getMessage());
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
            }
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

        $defaultDeptKey = config('department_stocks.default_key', 'mm');
        return view('purchase-orders.partials._po_items_table_glpi', compact('order', 'defaultDeptKey'));
    }


    public function ajaxRemoveItem(PurchaseOrderItem $item)
    {
        $this->authorize('po:manage');

        if ($item->purchaseOrder->status !== 'pending') {
             return response()->json(['success' => false, 'message' => 'ไม่สามารถลบรายการจากใบสั่งซื้อที่ดำเนินการไปแล้วได้'], 403);
        }

        try {
            // อัปเดต: ใช้ Soft Delete แทน Hard Delete (แต่ PurchaseOrderItem ไม่มี SoftDeletes Trait ดังนั้นจะเป็น Delete จริง)
            $item->delete(); 

            // ✅ Fix: Auto-delete Purchase Order if it becomes empty
            $remainingItems = $item->purchaseOrder->items()->count(); 
            
            $poDeleted = false;
            if ($remainingItems === 0) {
                // User Request: "Adjustment to not exist in database"
                // PurchaseOrder uses SoftDeletes, so delete() just hides it.
                // We use forceDelete() to physically remove it as requested.
                $item->purchaseOrder->forceDelete(); 
                $poDeleted = true;
                Log::info("Purchase Order #{$item->purchase_order_id} force-deleted because it became empty.");
            }

            return response()->json([
                'success' => true, 
                'message' => 'ลบรายการสำเร็จแล้ว' . ($poDeleted ? ' (และลบใบสั่งซื้อว่างเปล่า)' : ''),
                'po_deleted' => $poDeleted
            ]);
        } catch (\Exception $e) {
            Log::error("Error AJAX removing PO Item #{$item->id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }

    // =============================================
    // Resubmit Logic
    // =============================================
    public function resubmit(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('po:create'); 

        $hasRejectedItems = $purchaseOrder->items()->where('status', 'cancelled')->exists();

        if ($purchaseOrder->status !== 'cancelled' && !$hasRejectedItems) {
            return back()->with('error', 'ทำรายการได้เฉพาะใบสั่งซื้อที่ถูกปฏิเสธ (Rejected) หรือมีรายการย่อยถูกปฏิเสธเท่านั้น');
        }

        try {
            DB::transaction(function () use ($purchaseOrder, $request) {
                // 1. Update Existing PO (No Clone)
                $purchaseOrder->status = 'pending';
                // Don't clear pr_number or po_number if we want to reuse them
                // $purchaseOrder->ordered_at = null; // Optional: Keep original order date or reset? Let's keep it to show age, or reset if process restarts. Resetting might be safer for logic.
                // Actually, if we reset ordered_at, the 'sendPurchaseOrderToApi' will treat it as new? 
                // sendToApi sets ordered_at = now(). So it's fine.

                // 2. Update Notes & Data
                $replyNote = $request->input('resubmit_note'); 
                if ($replyNote) {
                    $purchaseOrder->notes .= "\n\n📝 [Resubmit Info]: " . $replyNote;
                }

                $puData = $purchaseOrder->pu_data ?? [];
                // Backup rejection info just in case
                $puData['history'] = $puData['history'] ?? [];
                $puData['history'][] = [
                    'event' => 'rejected',
                    'reason' => $puData['rejection_reason'] ?? '-',
                    'at' => now()->toDateTimeString()
                ];
                
                // Clear active rejection flags so it doesn't show as rejected anymore
                unset($puData['rejection_reason']);
                unset($puData['rejection_code']); 
                
                // Mark as Resubmit for API Handler
                $puData['is_resubmit'] = true; 
                
                $purchaseOrder->pu_data = $puData;
                $purchaseOrder->save();

                // 3. Reset ONLY Rejected Code 3 Items (Not all items)
                // ✅ FIX: รีเซ็ตเฉพาะรายการที่ถูกปฏิเสธและสามารถแก้ไขได้ (Code 3)
                foreach ($purchaseOrder->items as $item) {
                    // รีเซ็ตเฉพาะรายการที่ถูกปฏิเสธและเป็น Code 3 (หรือไม่ระบุ Code ชัดเจน)
                    $isRejected = $item->status === 'cancelled';
                    $isFixable = !in_array((int)$item->rejection_code, [1, 2, 4]);
                    
                    if ($isRejected && $isFixable) {
                        $item->status = 'pending';
                        $item->inspection_status = 'pending';
                        $item->inspection_notes = null;
                        $item->quantity_received = 0;
                        $item->save();
                    }
                }
            });
            
            // 🚀 Trigger API to PU Hub
            // Fixed: Pass $request to sendPurchaseOrderToApi
            $apiResult = $this->sendPurchaseOrderToApi($purchaseOrder, $request);
            if (!$apiResult['success']) {
                // Warning only - because local status is already safe.
                return redirect()->route('purchase-track.index')
                    ->with('warning', 'บันทึกแก้ไขแล้ว แต่ส่งข้อมูลไป PU ไม่สำเร็จ: ' . $apiResult['message']);
            }

            return redirect()->route('purchase-track.index')
                ->with('success', 'ส่งข้อมูลแก้ไขไปรายการติดตามพัสดุเรียบร้อยแล้ว');

        } catch (\Exception $e) {
            Log::error("Resubmit Error: " . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    // =============================================
    // API Retry Feature
    // =============================================
    public function retrySendApi(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('po:create');
        
        // Allow retry for Pending (Stuck), Ordered (Update), or Cancelled (if re-opening logic exists)
        // Checks logic handled inside sendPurchaseOrderToApi mostly, but here we gatekeep basic status.
        // For Resubmit flow, status is 'pending'.
        
        try {
             // Fixed: Pass $request
             $apiResult = $this->sendPurchaseOrderToApi($purchaseOrder, $request);
             
             if ($apiResult['success']) {
                 return back()->with('success', 'ส่งข้อมูลไป PU Hub เรียบร้อยแล้ว 🚀');
             } else {
                 return back()->with('error', 'ส่งไม่สำเร็จ: ' . $apiResult['message']);
             }
        } catch (\Exception $e) {
            // ✅ Translate 422 Error for User
            if (str_contains($e->getMessage(), 'Status: 422') && str_contains($e->getMessage(), 'pr_item_id is invalid')) {
                return back()->with('error', 'ข้อผิดพลาด: ข้อมูลสินค้าไม่ตรงกับระบบ PU (รายการนี้อาจถูกลบหรือปฏิเสธไปแล้ว) โปรดลองสร้างใบใหม่หรือติดต่อ Admin');
            }

            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    // =============================================
    // Item-level Resubmit (ตอบกลับแยกรายอุปกรณ์)
    // =============================================
    
    /**
     * ✅ Resubmit เฉพาะรายการเดียว (Item-level)
     * POST /po-items/{item}/resubmit
     */
    public function resubmitItem(Request $request, PurchaseOrderItem $item)
    {
        $this->authorize('po:create');
        
        $isAjax = $request->expectsJson() || $request->ajax();
        
        // 1. ตรวจสอบว่ารายการนี้ถูกปฏิเสธ
        if ($item->status !== 'cancelled') {
            $message = 'รายการนี้ไม่ได้อยู่ในสถานะถูกปฏิเสธ';
            return $isAjax 
                ? response()->json(['success' => false, 'message' => $message], 400)
                : back()->with('error', $message);
        }
        
        // 2. ตรวจสอบว่าเป็น Code ที่แก้ไขได้ (ไม่ใช่ 1, 2, 4)
        if (in_array((int)$item->rejection_code, [1, 2, 4])) {
            $message = 'รายการนี้ไม่สามารถแก้ไขและส่งใหม่ได้ (ต้องสร้างใบใหม่)';
            return $isAjax 
                ? response()->json(['success' => false, 'message' => $message], 400)
                : back()->with('error', $message);
        }
        
        try {
            DB::transaction(function () use ($item, $request) {
                // 3. รีเซ็ตสถานะรายการนี้
                $item->status = 'pending';
                $item->inspection_status = 'pending';
                $item->inspection_notes = null;
                $item->quantity_received = 0;
                
                // ✅ เพิ่ม counter การตอบกลับ
                $item->resubmit_count = ($item->resubmit_count ?? 0) + 1;
                $item->last_resubmit_at = now();
                
                $item->save();
            });
            
            // 4. ส่งเฉพาะรายการนี้ไป API
            $this->sendSingleItemToApi($item, $request);
            
            $message = 'ส่งคำตอบกลับสำหรับ "' . ($item->item_description ?? 'รายการ') . '" เรียบร้อยแล้ว';
            
            return $isAjax 
                ? response()->json(['success' => true, 'message' => $message])
                : back()->with('success', $message);
            
        } catch (\Exception $e) {
            Log::error("Resubmit Item Error: " . $e->getMessage());
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            
            return $isAjax 
                ? response()->json(['success' => false, 'message' => $message], 500)
                : back()->with('error', $message);
        }
    }
    
    /**
     * ✅ ส่งเฉพาะรายการเดียวไป API
     */
    private function sendSingleItemToApi(PurchaseOrderItem $item, Request $request)
    {
        // 1. ตรวจสอบว่าเปิดใช้งาน API หรือไม่
        $apiEnabled = config('services.pu_hub.enabled', true);
        if ($apiEnabled === '0' || $apiEnabled === 0 || $apiEnabled === false || $apiEnabled === 'false') {
            Log::warning("PU Hub API is DISABLED. Bypassing API call for Item #{$item->id}.");
            $item->status = 'ordered';
            $item->save();
            return ['success' => true, 'message' => 'API is disabled. Item marked as ordered locally.'];
        }
        
        // 2. Load PO และ Config
        $order = $item->purchaseOrder;
        $puApiBaseUrl = config('services.pu_hub.base_url');
        $puApiToken = config('services.pu_hub.token');
        $puApiIntakePath = config('services.pu_hub.intake_path');
        
        if (empty($puApiBaseUrl) || empty($puApiToken) || empty($puApiIntakePath)) {
            throw new \Exception('ตั้งค่า API สำหรับ PU Hub ไม่ถูกต้อง');
        }
        
        // 3. สร้าง Payload สำหรับ Item เดียว
        $itemName = $item->item_description ?? $item->equipment?->name ?? 'N/A';
        $unitName = $item->equipment?->unit?->name ?? 'ชิ้น';
        $replyNote = $request->input('reply_note') ? '(' . $request->input('reply_note') . ')' : '';
        
        $itemPayload = [
            'id' => $item->id,
            'pr_item_id' => $item->pr_item_id,
            
            // ✅ Required by PU API
            'item_name' => $itemName,
            'item_name_custom' => $itemName,  // REQUIRED field
            'quantity' => $item->quantity_ordered,
            'unit' => $unitName,
            'unit_name' => $unitName,  // Legacy field
            
            // ✅ ID References
            'origin_item_id' => $item->equipment_id,
            'equipment_id' => $item->equipment_id,
            
            // ✅ Notes
            'note' => $replyNote,
            'notes' => $replyNote,
            'resubmit_reason' => $replyNote,
        ];
        
        $payload = [
            'is_resubmit' => true,
            'is_item_level' => true, // ✅ Flag for PU to know this is per-item resubmit
            'pr_code' => $order->pr_number,
            'origin_department_id' => config('services.pu_hub.origin_department_id'),
            'requestor_user_id' => $order->ordered_by_user_id ?? Auth::id(),
            'priority' => ucfirst($order->type),
            'note' => $request->input('reply_note') ? '(' . $request->input('reply_note') . ')' : null,
            'items' => [$itemPayload],
        ];
        
        // 4. ส่ง API
        $fullApiUrl = rtrim($puApiBaseUrl, '/') . '/' . ltrim($puApiIntakePath, '/');
        
        Log::info("Sending Single Item #{$item->id} to PU API.", [
            'pr_code' => $order->pr_number,
            'item_name' => $itemPayload['item_name'],
            'note' => $itemPayload['note'],
        ]);
        
        $response = Http::withToken($puApiToken)
            ->acceptJson()
            ->timeout(15)
            ->post($fullApiUrl, $payload);
        
        if (!$response->successful()) {
            $status = $response->status();
            $errorBody = $response->json() ? json_encode($response->json()) : $response->body();
            throw new \Exception("ส่งข้อมูลไม่สำเร็จ (Status: {$status}): {$errorBody}");
        }
        
        // 5. อัปเดตสถานะ Item
        $item->status = 'ordered';
        $item->save();

        // ✅ NEW: Update PO Status if it was Rejected (To remove from Rejected List)
        if (in_array($order->status, ['rejected', 'cancelled'])) {
            $order->status = 'ordered';
            $order->ordered_at = now();
            $order->save();
        }
        
        // 6. อัปเดต PO pu_data
        $puData = $order->pu_data ?? [];
        $puData['is_resubmit'] = true;
        $puData['last_item_resubmit'] = [
            'item_id' => $item->id,
            'at' => now()->toDateTimeString(),
            'note' => $request->input('reply_note'),
        ];
        $order->pu_data = $puData;
        $order->save();
        
        // ✅ บันทึก History Log
        $itemName = $item->equipment ? $item->equipment->name : $item->item_description;
        $this->addPuHistoryLog($order, 'Resubmit Sent', "ส่งตอบกลับ: {$itemName} - " . ($request->input('reply_note') ?? ''));
        
        return ['success' => true, 'data' => $response->json()];
    }
    
    /**
     * ✅ Helper: บันทึก History Log การสื่อสารกับ PU
     * @param PurchaseOrder $order
     * @param string $event ชื่อ Event (เช่น "PR Sent", "Resubmit Sent", "Item Rejected")
     * @param string $details รายละเอียด
     */
    private function addPuHistoryLog(PurchaseOrder $order, string $event, string $details = ''): void
    {
        $puData = $order->pu_data ?? [];
        $history = $puData['history'] ?? [];
        
        $history[] = [
            'event' => $event,
            'reason' => $details,
            'at' => now()->toIso8601String(),
        ];
        
        $puData['history'] = $history;
        $order->pu_data = $puData;
        $order->saveQuietly();
    }
}
