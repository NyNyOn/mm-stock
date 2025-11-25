<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Category;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DeadstockController extends Controller
{
    public function index(Request $request)
    {
        // รับค่าจำนวนวันที่ต้องการเช็ค (Default คือ 90 วัน)
        $daysInactive = $request->input('days', 90);
        $categoryId = $request->input('category_id');

        // วันที่ตัดรอบ (ย้อนหลังไป X วัน)
        $thresholdDate = Carbon::now()->subDays($daysInactive);

        // Query หา Deadstock
        $query = Equipment::with(['category', 'transactions' => function($q) {
                $q->latest('transaction_date');
            }])
            ->where('quantity', '>', 0) // ต้องมีของเหลือ
            ->where('status', '!=', 'sold') // ไม่ใช่ของที่ขายไปแล้ว
            ->whereDoesntHave('transactions', function ($q) use ($thresholdDate) {
                // เงื่อนไข: ต้องไม่มี Transaction ใดๆ เกิดขึ้นหลังจากวันที่กำหนด
                $q->where('transaction_date', '>=', $thresholdDate)
                  ->whereIn('type', ['withdraw', 'borrow', 'return', 'partial_return']); // นับเฉพาะรายการเคลื่อนไหวจริง
            });

        // Filter ตามหมวดหมู่
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $deadstockItems = $query->orderBy('name')->paginate(20);

        // คำนวณข้อมูลเพิ่มเติมสำหรับแต่ละรายการ (เพื่อโชว์ใน View)
        $deadstockItems->getCollection()->transform(function ($item) {
            $lastTx = $item->transactions->first(); // Transaction ล่าสุด
            $item->last_movement_date = $lastTx ? $lastTx->transaction_date : $item->created_at;
            
            // คำนวณว่านิ่งมากี่วันแล้ว
            $item->days_silent = Carbon::parse($item->last_movement_date)->diffInDays(now());
            
            return $item;
        });

        $categories = Category::orderBy('name')->get();

        return view('deadstock.index', compact('deadstockItems', 'categories', 'daysInactive'));
    }
}