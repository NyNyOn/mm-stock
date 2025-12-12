<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Config;

class PurchaseTrackController extends Controller
{
    /**
     * แสดงหน้าติดตามสถานะพัสดุ (Tracking)
     * โชว์เฉพาะรายการที่มีการสั่งซื้อไปแล้ว หรือกำลังดำเนินการ
     */
    public function index()
    {
        // ดึงชื่อแผนกปัจจุบันมาแสดง
        $currentDeptKey = Config::get('app.dept_key', 'it');
        $departmentsConfig = Config::get('department_stocks.departments', []);
        $currentDeptName = $departmentsConfig[$currentDeptKey]['name'] ?? strtoupper($currentDeptKey);

        // ดึงรายการ PO ทั้งหมด (เรียงจากล่าสุดไปเก่าสุด)
        // เน้นดึงข้อมูล Items และ Equipment เพื่อเอารูปมาโชว์
        $purchaseOrders = PurchaseOrder::with(['items.equipment.latestImage', 'orderedBy'])
            ->whereIn('status', [
                'ordered',                // สั่งซื้อแล้ว (รอร้านส่ง)
                'shipped_from_supplier',  // ร้านส่งแล้ว (รอรับ)
                'partial_receive',        // รับแล้วบางส่วน
                'completed',              // เสร็จสิ้น
                'pending',                // รอดำเนินการ (เผื่อไว้)
                'approved'                // อนุมัติแล้ว (เผื่อไว้)
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(10); // แบ่งหน้าทีละ 10 รายการ

        return view('purchase-track.index', compact('purchaseOrders', 'currentDeptName'));
    }
}