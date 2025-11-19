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

    public function index()
    {
        $pendingReturns = collect();
        if (Auth::user()->can('permission:manage')) {
            $pendingReturns = ConsumableReturn::with(['originalTransaction.equipment', 'requester'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $userReturnHistory = ConsumableReturn::with(['originalTransaction.equipment', 'approver'])
            ->where('requested_by_user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'history_page');

        // ✅✅✅ START: แก้ไข Query ตรงนี้ ✅✅✅
        $returnableItems = Transaction::with('equipment')
            ->where('user_id', Auth::id())
            ->where('return_condition', 'allowed')
            ->where('type', 'partial_return')
            ->where('status', 'completed')
            // ‼️ แก้ไขบรรทัดนี้: เพิ่ม COALESCE เพื่อจัดการกับค่า NULL (ว่าง) ให้เป็น 0
            ->where(DB::raw('ABS(quantity_change)'), '>', DB::raw('COALESCE(returned_quantity, 0)'))
            ->orderBy('transaction_date', 'desc')
            ->paginate(10, ['*'], 'items_page');
        // ✅✅✅ END: แก้ไข Query ✅✅✅

        $pendingReturnTxnIds = ConsumableReturn::where('requested_by_user_id', Auth::id())
                                ->where('status', 'pending')
                                ->pluck('original_transaction_id')
                                ->toArray();

        return view('consumable-returns.index', compact('pendingReturns', 'userReturnHistory', 'returnableItems', 'pendingReturnTxnIds'));
    }

    // (ฟังก์ชัน store นี้ถูกต้องแล้ว จากที่เราแก้ครั้งก่อน)
    public function store(Request $request)
    {
        $this->authorize('consumable:return');
        
        $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id',
            'return_quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        $originalTransaction = Transaction::findOrFail($request->transaction_id);

        $existingPendingReturn = ConsumableReturn::where('original_transaction_id', $originalTransaction->id)
            ->where('status', 'pending')
            ->exists();
        if ($existingPendingReturn) {
            return back()->with('error', 'คุณได้ส่งคำขอคืนสำหรับรายการนี้ไปแล้ว และกำลังรอการอนุมัติ');
        }

        $remainingQty = abs($originalTransaction->quantity_change) - ($originalTransaction->returned_quantity ?? 0); // (ใช้ ?? 0 เพื่อความปลอดภัย)
        if ($request->return_quantity > $remainingQty) {
            return back()->with('error', "ไม่สามารถขอคืนเกินจำนวนที่เหลือได้ (เหลือให้คืน {$remainingQty} ชิ้น)");
        }

        ConsumableReturn::create([
            'original_transaction_id' => $originalTransaction->id,
            'requested_by_user_id' => Auth::id(),
            'quantity_returned' => $request->return_quantity,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        return back()->with('success', 'ส่งคำขอคืนพัสดุสำเร็จแล้ว กรุณารอ Admin อนุมัติ');
    }


    // (ฟังก์ชัน approve นี้ไม่ได้แก้ไข ตรรกะเดิมของคุณถูกต้องแล้ว)
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
            
            // เพิ่ม Stock คืน
            $equipment->increment('quantity', $return->quantity_returned);
            
            // อัปเดต Transaction เดิม (increment จะจัดการ NULL ให้เป็น 0 อัตโนมัติ)
            $originalTransaction->increment('returned_quantity', $return->quantity_returned);

            // สร้าง Transaction 'return' ใหม่เพื่อเก็บประวัติ
            Transaction::create([
                'equipment_id' => $equipment->id,
                'user_id' => Auth::id(), // คนที่กดยืนยัน (Admin)
                'type' => 'return',
                'quantity_change' => $return->quantity_returned, // คืนเข้า (ค่าบวก)
                'notes' => "รับคืนพัสดุจาก #TXN-{$originalTransaction->id} (อนุมัติคำขอ #{$return->id})",
                'transaction_date' => now(),
                'status' => 'completed',
            ]);

            $return->status = 'approved';
            $return->approved_by_user_id = Auth::id();
            $return->save();

            $return->requester->notify(new ConsumableReturnApproved($return));

            DB::commit();
            return back()->with('success', 'อนุมัติการรับคืนเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    // (ฟังก์ชัน reject นี้ไม่ได้แก้ไข ตรรกะเดิมของคุณถูกต้องแล้ว)
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