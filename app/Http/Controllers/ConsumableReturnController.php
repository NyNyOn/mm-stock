<?php

namespace App\Http\Controllers;

use App\Models\ConsumableReturn;
use App\Models\Transaction;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Notifications\ConsumableReturnApproved;
use App\Notifications\ConsumableReturnRejected;

class ConsumableReturnController extends Controller
{
    use AuthorizesRequests;

    /**
     * แสดงรายการที่สามารถคืนได้ และประวัติการคืน
     */
    public function index()
    {
        $pendingReturns = collect();
        
        // Admin: ดูรายการรออนุมัติทั้งหมด
        if (Auth::user()->can('permission:manage')) {
            $pendingReturns = ConsumableReturn::with(['originalTransaction.equipment.latestImage', 'requester'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // User: ประวัติการขอคืนของตัวเอง
        $userReturnHistory = ConsumableReturn::with(['originalTransaction.equipment.latestImage', 'approver'])
            ->where('requested_by_user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'history_page');

        // User: รายการที่ "เบิกเหลือคืนได้" (Partial Return) ที่ยังคืนไม่ครบ
        $returnableItems = Transaction::with(['equipment.latestImage'])
            ->where('user_id', Auth::id())
            ->where('return_condition', 'allowed')
            // ->where('type', 'partial_return') // ❌ Previously restricted to partial_return only
            ->whereIn('type', ['partial_return', 'returnable']) // ✅ Allow both Partial Return and Borrow (Returnable)
            ->where('status', 'completed')
            // ตรวจสอบว่ายังมียอดคงเหลือให้คืน (Abs(จำนวนเบิก) > จำนวนที่คืนแล้ว)
            ->where(DB::raw('ABS(quantity_change)'), '>', DB::raw('COALESCE(returned_quantity, 0)'))
            ->orderBy('transaction_date', 'desc')
            ->paginate(10, ['*'], 'items_page');

        // รายการที่ User เคยกดขอคืนไปแล้ว แต่สถานะเป็น Pending (เพื่อนำไปแสดง Badge 'รออนุมัติ')
        $pendingReturnTxnIds = ConsumableReturn::where('requested_by_user_id', Auth::id())
                                ->where('status', 'pending')
                                ->pluck('original_transaction_id')
                                ->toArray();

        return view('consumable-returns.index', compact('pendingReturns', 'userReturnHistory', 'returnableItems', 'pendingReturnTxnIds'));
    }

    /**
     * บันทึกคำขอคืน (หรือแจ้งใช้หมด)
     */
    public function store(Request $request)
    {
        $this->authorize('consumable:return');
        
        $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id',
            'action_type' => 'required|in:return,write_off', // return = คืนของ, write_off = ใช้หมด
            'return_quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        $originalTransaction = Transaction::findOrFail($request->transaction_id);

        // เช็คว่ามีรายการรออนุมัติค้างอยู่หรือไม่
        $existingPendingReturn = ConsumableReturn::where('original_transaction_id', $originalTransaction->id)
            ->where('status', 'pending')
            ->exists();

        if ($existingPendingReturn) {
            return back()->with('error', 'รายการนี้มีคำขอที่รอการอนุมัติอยู่แล้ว');
        }

        // คำนวณยอดคงเหลือจริง
        $remainingQty = abs($originalTransaction->quantity_change) - ($originalTransaction->returned_quantity ?? 0);

        if ($remainingQty <= 0) {
            return back()->with('error', 'รายการนี้ถูกเคลียร์ยอดหมดแล้ว');
        }

        $quantityToProcess = 0;
        $notePrefix = "";

        // กรณี: แจ้งใช้หมด (Write-off) -> บังคับยอดเท่ากับจำนวนคงเหลือทั้งหมด
        if ($request->action_type === 'write_off') {
            $quantityToProcess = $remainingQty;
            $notePrefix = "[แจ้งใช้หมด] ";
        } 
        // กรณี: คืนของ (Return) -> ใช้ยอดตามที่กรอก
        else {
            $quantityToProcess = $request->return_quantity;
            if (!$quantityToProcess || $quantityToProcess > $remainingQty) {
                return back()->with('error', "ไม่สามารถขอคืนเกินจำนวนที่เหลือได้ (เหลือ {$remainingQty} ชิ้น)");
            }
            $notePrefix = "[ขอคืนของ] ";
        }

        if ($request->action_type === 'write_off') {
            DB::beginTransaction();
            try {
                // 1. Update Original Transaction
                $originalTransaction->increment('returned_quantity', $quantityToProcess);

                // 2. Create 'Adjust' Transaction Log
                Transaction::create([
                    'equipment_id' => $originalTransaction->equipment_id,
                    'user_id' => Auth::id(),
                    'handler_id' => null, // No handler needed for auto-action
                    'type' => 'adjust',
                    'quantity_change' => 0, 
                    'notes' => "[แจ้งใช้หมด] อ้างอิง #TXN-{$originalTransaction->id} (Auto-Approved)",
                    'transaction_date' => now(),
                    'status' => 'completed',
                    'confirmed_at' => now(),
                    'admin_confirmed_at' => now(),
                ]);

                // 3. Create Return Record (Approved)
                $return = ConsumableReturn::create([
                    'original_transaction_id' => $originalTransaction->id,
                    'requested_by_user_id' => Auth::id(),
                    'quantity_returned' => $quantityToProcess,
                    'action_type' => 'write_off',
                    'notes' => $notePrefix . $request->notes,
                    'status' => 'approved', // ✅ Auto-Approve directly
                    'approved_by_user_id' => null, // System auto-approve
                ]);

                DB::commit();
                return back()->with('success', 'แจ้งใช้งานหมดแล้วสำเร็จ (ระบบบันทึกเรียบร้อย)');

            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
        }

        // === Standard Return Flow (Pending Admin Approval) ===
        $return = ConsumableReturn::create([
            'original_transaction_id' => $originalTransaction->id,
            'requested_by_user_id' => Auth::id(),
            'quantity_returned' => $quantityToProcess,
            'action_type' => $request->action_type,
            'notes' => $notePrefix . $request->notes,
            'status' => 'pending',
        ]);

        // ✅✅✅ Notification Trigger (Only for Returns) ✅✅✅
        try {
            // Re-query to get relationships
            $newReturn = ConsumableReturn::with(['originalTransaction.equipment', 'requester'])->find($return->id);

            // 1. Notify Admins (Database Bell)
            // User model does not use Spatie trait, so we filter manually using custom hasPermissionTo
            $admins = User::all()->filter(function($user) {
                return $user->hasPermissionTo('permission:manage');
            });
            
            if ($admins->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\ConsumableReturnRequested($newReturn));
            }

            // 2. Notify Synology Chat
            $synology = new \App\Services\SynologyService();
            $synology->notify(new \App\Notifications\ConsumableReturnRequested($newReturn));
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Consumable Return Notification Error: " . $e->getMessage());
        }

        $msg = $request->action_type === 'write_off' ? 'แจ้งใช้งานหมดแล้วสำเร็จ กรุณารอ Admin ตรวจสอบ' : 'ส่งคำขอคืนพัสดุสำเร็จ กรุณารอ Admin อนุมัติ';
        return back()->with('success', $msg);
    }

    /**
     * Admin อนุมัติคำขอ
     */
    public function approve(ConsumableReturn $return)
    {
        $this->authorize('permission:manage');
        if ($return->status !== 'pending') {
            return back()->with('error', 'รายการนี้ไม่ได้อยู่ในสถานะรออนุมัติ');
        }

        DB::beginTransaction();
        try {
            $originalTransaction = Transaction::lockForUpdate()->findOrFail($return->original_transaction_id);
            $equipment = Equipment::lockForUpdate()->findOrFail($originalTransaction->equipment_id);
            
            // 1. อัปเดต Transaction เดิมให้ยอด Returned เพิ่มขึ้น (เพื่อให้รายการหายไปจากหน้า User เพราะถือว่าเคลียร์แล้ว)
            $originalTransaction->increment('returned_quantity', $return->quantity_returned);

            Log::info("Approving Return ID: {$return->id}, Type: {$return->action_type}, Qty: {$return->quantity_returned}, Current Eq Qty: {$equipment->quantity}");

            // 2. จัดการ Stock และสร้าง Transaction ใหม่ตามประเภท
            if ($return->action_type === 'return') {
                // === กรณีคืนของ: เพิ่ม Stock กลับ ===
                $equipment->increment('quantity', $return->quantity_returned);
                
                $equipment->refresh(); // Check new value
                Log::info("Incremented! New Eq Qty: {$equipment->quantity}");

                // สร้าง Transaction 'return'
                Transaction::create([
                    'equipment_id' => $equipment->id,
                    'user_id' => $return->requested_by_user_id, // เจ้าของรายการเดิม
                    'handler_id' => Auth::id(), // Admin ผู้อนุมัติ
                    'type' => 'return',
                    'quantity_change' => $return->quantity_returned, // คืนเข้า (+)
                    'notes' => "รับคืนพัสดุจาก #TXN-{$originalTransaction->id} (อนุมัติคำขอ #{$return->id})",
                    'transaction_date' => now(),
                    'status' => 'completed',
                    'confirmed_at' => now(),
                    'admin_confirmed_at' => now(),
                    'return_condition' => 'allowed', // Ensure this field is present if required
                ]);

            } else {
                Log::info("Action is NOT return (write_off). No stock increment.");
                // === กรณีใช้หมด (Write-off): ไม่เพิ่ม Stock ===
                // สร้าง Transaction 'adjust' เพื่อเก็บ Log ว่าของชิ้นนี้ถูกใช้หมดแล้ว
                Transaction::create([
                    'equipment_id' => $equipment->id,
                    'user_id' => $return->requested_by_user_id,
                    'handler_id' => Auth::id(),
                    'type' => 'adjust', // ใช้ adjust เพื่อไม่กระทบ Stock รวม (เพราะ quantity_change เป็น 0 หรือ log แยก)
                    'quantity_change' => 0, // ไม่เพิ่ม Stock กลับ (เพราะใช้หมดแล้ว)
                    'notes' => "บันทึกการใช้งานจนหมด (Write-off) จาก #TXN-{$originalTransaction->id} (อนุมัติคำขอ #{$return->id})",
                    'transaction_date' => now(),
                    'status' => 'completed',
                    'confirmed_at' => now(),
                    'admin_confirmed_at' => now(),
                ]);
            }

            // 3. อัปเดตสถานะคำขอ
            $return->status = 'approved';
            $return->approved_by_user_id = Auth::id();
            $return->save();

            // 4. แจ้งเตือนผู้ขอ
            $return->requester->notify(new ConsumableReturnApproved($return));

            DB::commit();
            Log::info("Approval Committed Successfully.");
            return back()->with('success', 'อนุมัติรายการเรียบร้อยแล้ว');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    /**
     * Admin ปฏิเสธคำขอ
     */
    public function reject(ConsumableReturn $return)
    {
        $this->authorize('permission:manage');
        if ($return->status !== 'pending') {
            return back()->with('error', 'รายการนี้ไม่ได้อยู่ในสถานะรออนุมัติ');
        }

        $return->status = 'rejected';
        $return->approved_by_user_id = Auth::id();
        $return->save();

        $return->requester->notify(new ConsumableReturnRejected($return));

        return back()->with('success', 'ปฏิเสธคำขอเรียบร้อยแล้ว');
    }
}