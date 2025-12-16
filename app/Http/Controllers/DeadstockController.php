<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class DeadstockController extends Controller
{
    public function index(Request $request)
    {
        // 1. รับค่าจาก Filter (ใช้ 'days' ตามใน Form)
        $daysInactive = (int) $request->input('days', 90); // Default 90 วัน
        $categoryId = $request->input('category_id');
        $locationId = $request->input('location_id');

        // 2. ดึงข้อมูลตัวเลือก
        $categories = Category::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();

        // 3. Query สินค้าที่มีของอยู่และสถานะปกติ
        $query = Equipment::with(['category', 'location', 'primaryImage', 'latestImage', 'transactions'])
            ->where('quantity', '>', 0)
            ->whereNotIn('status', ['sold', 'disposed']);

        if ($categoryId) $query->where('category_id', $categoryId);
        if ($locationId) $query->where('location_id', $locationId);

        // 4. คำนวณวันหยุดนิ่ง (Logic ที่ถูกต้อง)
        $allItems = $query->get()->map(function ($item) {
            // หาวันที่เคลื่อนไหวล่าสุด
            $lastTx = $item->transactions->sortByDesc('transaction_date')->first();

            if ($lastTx) {
                $lastMovement = Carbon::parse($lastTx->transaction_date);
            } else {
                // ถ้าไม่มี Transaction ให้ใช้วันที่แก้ไขล่าสุด หรือวันที่สร้าง
                $lastMovement = $item->updated_at ? Carbon::parse($item->updated_at) : Carbon::parse($item->created_at);
            }

            // เตรียมข้อมูลสำหรับ View
            $item->last_movement_date = $lastMovement; // ส่งเป็น Object Carbon
            
            // คำนวณวัน (ใช้ startOfDay เพื่อตัดเรื่องเวลาออก นับเต็มวัน)
            $item->days_silent = (int) abs(now()->startOfDay()->diffInDays($lastMovement->copy()->startOfDay()));

            return $item;
        });

        // 5. กรองเฉพาะที่นิ่งเกินกำหนด
        $filteredItems = $allItems->filter(function ($item) use ($daysInactive) {
            return $item->days_silent >= $daysInactive;
        })->sortByDesc('days_silent')->values();

        // 6. ทำ Pagination เอง (Manual Pagination) เพราะเรา Filter บน Collection
        $page = Paginator::resolveCurrentPage() ?: 1;
        $perPage = 10;
        $currentPageItems = $filteredItems->slice(($page - 1) * $perPage, $perPage)->values();

        $deadstockItems = new LengthAwarePaginator(
            $currentPageItems,
            $filteredItems->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // 7. ส่งข้อมูลไป View (ใช้ชื่อตัวแปรตามที่คุณต้องการใน Blade)
        return view('deadstock.index', compact('deadstockItems', 'categories', 'locations', 'daysInactive'));
    }
}