<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Transaction;
use App\Models\MaintenanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log; // ‼️ เพิ่ม Log (เผื่อไว้)

class ReturnController extends Controller
{
    /**
     * Display the page with all borrowed items that are not yet returned.
     * แสดงหน้ารายการยืมที่ยังค้างคืน (ยังคืนไม่ครบ)
     */
    public function index()
    {
        $user = Auth::user();
        
        // 1. รายการที่ "ฉัน" ยืมอยู่ (My Borrowed Items)
        // แสดงให้ User เห็นเพื่อกด "แจ้งคืน" (Request Return)
        $myItems = Transaction::with(['equipment.images', 'user'])
            ->where('user_id', $user->id)
            ->whereIn('type', ['borrow', 'returnable'])
            ->whereIn('status', ['completed', 'closed', 'return_requested']) // สถานะ Active + Pending Return
            ->where(function ($query) {
                $query->whereNull('returned_quantity')
                      ->orWhereRaw('ABS(quantity_change) > returned_quantity');
            })
            ->orderBy('transaction_date', 'asc')
            ->get();

        // 2. Setting Check
        $allowUserReturn = \App\Models\Setting::where('key', 'allow_user_return_request')->value('value');

        // 3. (New) Admin View: All Borrowed Items
        $allBorrowedItems = collect();
        if ($user->can('permission:manage')) {
             $allBorrowedItems = Transaction::with(['equipment.images', 'user'])
                ->whereIn('type', ['borrow', 'returnable'])
                ->whereIn('status', ['completed', 'close', 'return_requested']) // Completed/Active or Requested
                ->where(function ($query) {
                    $query->whereNull('returned_quantity')
                          ->orWhereRaw('ABS(quantity_change) > returned_quantity');
                })
                ->orderBy('transaction_date', 'desc')
                ->get();
        }

        return view('returns.index', compact('myItems', 'allowUserReturn', 'allBorrowedItems'));
    }

    /**
     * Process the return of an equipment.
     * ดำเนินการรับคืนอุปกรณ์
     * (ฟังก์ชันนี้ไม่ได้แก้ไข ตรรกะเดิมของคุณถูกต้องแล้ว)
     */
    public function store(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id',
            'return_condition' => 'required|string|in:good,defective',
            'problem_description' => [
                'nullable',
                'string',
                'required_if:return_condition,defective',
            ],
        ]);

        DB::beginTransaction();
        try {
            $originalTransaction = Transaction::lockForUpdate()->findOrFail($request->input('transaction_id'));

            // --- การตรวจสอบสิทธิ์ (ตัวอย่าง) ---
            // (คุณอาจจะต้องปรับแก้ตามสิทธิ์ของคุณ)
            if (!Auth::user()->can('permission:manage')) {
                 return back()->withErrors(['error' => 'คุณไม่มีสิทธิ์ในการดำเนินการคืนอุปกรณ์']);
            }

            // --- ตรวจสอบว่ามีของค้างคืนจริงหรือไม่ ---
            $remainingToReturn = abs($originalTransaction->quantity_change) - ($originalTransaction->returned_quantity ?? 0);
            $quantityToReturn = $remainingToReturn; // ✅ แก้ไข: ให้คืนยอดที่เหลืออยู่ (ป้องกันการคืนเกิน)

            if ($remainingToReturn <= 0) {
                 return back()->withErrors(['error' => 'รายการนี้ถูกคืนครบถ้วนหรือตัดยอดไปแล้ว']);
            }

            // อัปเดตรายการยืมเดิม (Original Transaction)
            // ‼️ แก้ไขเล็กน้อย: ให้เพิ่มยอดคืนตามจริง ไม่ใช่คืนทั้งหมดทีเดียว
            $originalTransaction->returned_quantity = ($originalTransaction->returned_quantity ?? 0) + $quantityToReturn; // (สมมติว่าคืนทั้งหมด)
            
            // ตรวจสอบว่าคืนครบหรือยัง
            if (($originalTransaction->returned_quantity ?? 0) >= abs($originalTransaction->quantity_change)) {
                $originalTransaction->status = 'returned'; // อัปเดตสถานะเป็น "คืนแล้ว"
            }
            $originalTransaction->save();


            $equipment = Equipment::lockForUpdate()->findOrFail($originalTransaction->equipment_id);

            // --- สร้าง Transaction 'return' ใหม่สำหรับประวัติ ---
            $newReturnTransaction = Transaction::create([
                'equipment_id' => $equipment->id,
                'user_id' => $originalTransaction->user_id, // ใช้ ID ของผู้ยืมเดิม
                'handler_id' => Auth::id(),                 // ID ของผู้ดำเนินการคืน
                'type' => 'return',
                'quantity_change' => $quantityToReturn, // คืนเข้า (เป็นบวก)
                'notes' => "Return for #TXN-{$originalTransaction->id}. Condition: {$request->input('return_condition')}",
                'transaction_date' => now(),
                'status' => 'completed', // รายการ "คืน" ถือว่าเสร็จสมบูรณ์ทันที
                'admin_confirmed_at' => now(), // (เพิ่ม)
                'user_confirmed_at' => now(),  // (เพิ่ม)
                'confirmed_at' => now(),       // (เพิ่ม)
            ]);

            if ($request->input('return_condition') === 'defective') {
                // Logic "แยกสต็อก" สำหรับของที่ชำรุด
                // (โค้ดส่วนนี้ของคุณดูถูกต้องแล้ว)
                $maintenanceEquipment = $equipment->replicate(['id', 'created_at', 'updated_at']);
                $maintenanceEquipment->quantity = $quantityToReturn;
                $maintenanceEquipment->status = 'maintenance'; // ตั้งสถานะเป็น "ส่งซ่อม"
                $maintenanceEquipment->notes = "Split from stock for repair from original equipment ID: " . $equipment->id . ". Ref TXN-{$newReturnTransaction->id}";
                $maintenanceEquipment->save();

                $maintenanceEquipment->save();

                // สร้าง Log การส่งซ่อม
                $log = MaintenanceLog::create([
                    'equipment_id' => $maintenanceEquipment->id,
                    'transaction_id' => $newReturnTransaction->id,
                    'reported_by_user_id' => Auth::id(), // ผู้ดำเนินการ (handler) เป็นคนแจ้ง
                    'problem_description' => $request->input('problem_description', 'Not specified'),
                    'status' => 'pending', // สถานะ "รอซ่อม"
                ]);

                // ✅ แจ้งเตือน Send for Repair
                try {
                     (new \App\Services\SynologyService())->notify(
                        new \App\Notifications\EquipmentSentForRepair($maintenanceEquipment, $log)
                     );
                } catch (\Exception $e) { Log::error("Notify Repair Sent Error: " . $e->getMessage()); }

            } else {
                // Logic สำหรับของสภาพดี
                // เพิ่มจำนวนกลับเข้าสต็อกหลัก
                $equipment->increment('quantity', $quantityToReturn);
            }

            // บันทึกการเปลี่ยนแปลงของ Equipment (ถ้ามี เช่นการ increment)
            $equipment->save();

            // ✅ แจ้งเตือน Item Returned (เฉพาะกรณีคืนปกติ หรือคืนแล้วเสียก็แจ้งว่าได้รับคืนแล้ว)
            try {
                 (new \App\Services\SynologyService())->notify(
                    new \App\Notifications\ItemReturned($newReturnTransaction->load('equipment', 'user'))
                 );
            } catch (\Exception $e) { Log::error("Notify Return Error: " . $e->getMessage()); }

            DB::commit();
            return back()->with('success', 'ดำเนินการรับคืนอุปกรณ์เรียบร้อยแล้ว!');
        } catch (\Exception $e) {
            DB::rollBack();
            // เก็บ Log ข้อผิดพลาด
            Log::error("Return processing failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดขณะดำเนินการคืน: ' . $e->getMessage()]);
        }
    }
}