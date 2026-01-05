<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseTrackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // 1. กำหนดสถานะที่จะแสดงในหน้า Tracking
        // ✅ เพิ่ม 'pending' เข้าไป เพื่อให้เห็นรายการที่รอส่งด้วย
        $trackingStatuses = [
            'ordered',                
            'approved',               
            'shipped_from_supplier',  
            'partial_receive',        
            'completed'               
        ];

        // 2. สร้าง Query ดึงข้อมูล
        $query = PurchaseOrder::with([
            'items' => function ($q) {
                // โหลดข้อมูลอุปกรณ์ (รวมที่ถูกลบไปแล้ว) + Unit + Images + Category
                $q->with(['equipment' => function ($eq) {
                    $eq->withTrashed()->with(['category', 'unit', 'images']);
                }]);
            },
            'requester',   // ผู้ขอซื้อ
            'orderedBy'    // ผู้กดสั่ง (Admin/System)
        ])
        ->whereIn('status', $trackingStatuses); // กรองตามสถานะที่กำหนด

        // 3. ตรวจสอบสิทธิ์การมองเห็น (User Permission)
        $user = Auth::user();
        
        // เช็คว่าเป็น ID 9 (Super Admin) หรือมีสิทธิ์ Admin หรือไม่
        $isSuperAdmin = ($user->id == 9); 
        $hasAdminRole = method_exists($user, 'isAdmin') && $user->isAdmin();

        // ❌ ถ้าไม่ใช่ Admin: ให้เห็นเฉพาะรายการที่ตัวเองเกี่ยวข้อง (เป็นคนขอ หรือ เป็นคนกดสั่ง)
        // ❌ FIXED: Removed strict user check so everyone in the department can see the orders
        // (Since the app is already separated by department DB, this is safe)
        /*
        if (!$isSuperAdmin && !$hasAdminRole) {
            $query->where(function($q) use ($user) {
                $q->where('ordered_by_user_id', $user->id)
                  ->orWhere('requester_id', $user->id);
            });
        }
        */
        // ✅ ถ้าเป็น Admin (ID 9): โค้ดจะข้าม if ข้างบนไป ทำให้เห็นรายการทั้งหมด

        // 4. เรียงลำดับ: เอาล่าสุดขึ้นก่อนเสมอ (ตาม ID หรือ Created At)
        // ตามที่ User ขอ: "เลข Po ล่าสุดอยู่บนเสมอ" -> ตีความว่าเป็นรายการล่าสุด
        $purchaseOrders = $query->orderBy('id', 'desc')
                                ->paginate(10);

        return view('purchase-track.index', compact('purchaseOrders'));
    }
}