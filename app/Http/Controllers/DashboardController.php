<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipment;
use App\Models\Transaction;
use App\Models\PurchaseOrder;
use App\Models\Category;
use App\Models\StockCheck;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        // กำหนดเวลา Cache (วินาที)
        $statCacheTime = 600; 
        $listCacheTime = 300; 
        $filterCacheTime = 3600; 

        // --- 1. คำนวณรอบการนับสต๊อก (105 วัน) ---
        $stockCycles = Category::with(['equipments' => function($q) {
                $q->where('status', '!=', 'sold')->select('id', 'category_id', 'last_stock_check_at');
            }])
            ->get()
            ->map(function($category) {
                // หาอุปกรณ์ที่ไม่ได้นับมานานที่สุด
                $oldestItem = $category->equipments->sortBy('last_stock_check_at')->first();
                
                $cycleDays = 105; 
                $nextCheckDate = null;
                $status = 'safe';
                $daysLeft = 105;

                if ($oldestItem && $oldestItem->last_stock_check_at) {
                    $lastCheck = Carbon::parse($oldestItem->last_stock_check_at);
                    $nextCheckDate = $lastCheck->copy()->addDays($cycleDays);
                    $daysLeft = now()->diffInDays($nextCheckDate, false); 
                } elseif ($oldestItem) {
                    // มีของแต่ไม่เคยนับ = เลยกำหนดทันที
                    $nextCheckDate = Carbon::now()->subDay(); 
                    $daysLeft = -1;
                } else {
                    return null; // ไม่มีของ
                }

                // Logic สถานะ
                if ($daysLeft < 0) {
                    $status = 'locked';
                } elseif ($daysLeft <= 15) {
                    $status = 'warning';
                }

                return (object) [
                    'id' => $category->id,
                    'name' => $category->name,
                    // ✅✅✅ สำคัญ: ต้องมีบรรทัดนี้เพื่อให้ View เรียกใช้ได้
                    'next_check_date' => $nextCheckDate ? $nextCheckDate->toIso8601String() : null,
                    'formatted_date' => (isset($lastCheck) && $lastCheck) ? $lastCheck->format('d/m/Y') : '-', 
                    'item_count' => $category->equipments->count(),
                    'status' => $status,
                    'days_left' => $daysLeft
                ];
            })
            ->filter()
            ->sortBy('days_left')
            ->values();

        // --- 1.2 Summary for Notifications (Count ALL, no limit) ---
        $lockedStockCount = $stockCycles->where('status', 'locked')->count();
        $warningStockCount = $stockCycles->where('status', 'warning')->count();

        // Limit for Display List
        $stockCycles = $stockCycles->take(20);

        // --- 2. รายการนัดหมาย (Scheduled) ---
        $scheduledStockChecks = StockCheck::with(['items.equipment.category'])
                                    ->where('status', 'scheduled')
                                    ->orderBy('scheduled_date', 'asc')
                                    ->get();

        // --- Stat Cards Data ---
        $total_equipment = Cache::remember('dashboard_total_equipment', $statCacheTime, function () { return Equipment::sum('quantity'); });
        $low_stock_count = Cache::remember('dashboard_low_stock_count', $statCacheTime, function () { return Equipment::where('min_stock', '>', 0)->whereColumn('quantity', '<=', 'min_stock')->where('quantity', '>', 0)->count(); });
        $on_order_count = Cache::remember('dashboard_on_order_count', $statCacheTime, function () { return Equipment::where('status', 'on-order')->count(); });
        $warranty_count = Cache::remember('dashboard_warranty_count', $statCacheTime, function () { return Equipment::whereNotNull('warranty_date')->whereBetween('warranty_date', [now(), now()->addDays(30)])->count(); });
        $urgent_order_count = Cache::remember('dashboard_urgent_order_count', $statCacheTime, function () { return PurchaseOrder::where('type', 'urgent')->count(); });
        $scheduled_order_count = Cache::remember('dashboard_scheduled_order_count', $statCacheTime, function () { return PurchaseOrder::where('type', 'scheduled')->count(); });
        $pending_transactions_count = Cache::remember('dashboard_pending_tx_count', $statCacheTime, function () { return Transaction::where('status', 'pending')->count(); });
        $job_order_count = Cache::remember('dashboard_job_order_count', $statCacheTime, function () { return PurchaseOrder::where('type', 'job_order')->count(); });

        // --- List Data ---
        $on_order_items = Cache::remember('dashboard_on_order_items', $listCacheTime, function () { return Equipment::where('status', 'on-order')->orderBy('name')->limit(5)->get(); });
        $out_of_stock_items = Cache::remember('dashboard_out_of_stock_items', $listCacheTime, function () { return Equipment::where('quantity', '<=', 0)->orderBy('name')->limit(5)->get(); });
        $low_stock_items = Cache::remember('dashboard_low_stock_items', $listCacheTime, function () { return Equipment::where('min_stock', '>', 0)->whereColumn('quantity', '<=', 'min_stock')->where('quantity', '>', 0)->orderBy('quantity')->limit(5)->get(); });

        $recent_activities = Transaction::with(['equipment', 'user'])->latest('transaction_date')->paginate(10);

        // --- Filters ---
        $available_years = Cache::remember('dashboard_available_years', $filterCacheTime, function () { return Transaction::select(DB::raw('YEAR(transaction_date) as year'))->whereNotNull('transaction_date')->distinct()->orderBy('year', 'desc')->pluck('year'); });
        $categories = Cache::remember('dashboard_categories', $filterCacheTime, function () { return Category::orderBy('name')->get(); });

        return view('dashboard.index', compact(
            'total_equipment', 'low_stock_count', 'on_order_count', 'warranty_count',
            'urgent_order_count', 'scheduled_order_count', 'pending_transactions_count',
            'job_order_count',
            'available_years', 'categories',
            'on_order_items', 'out_of_stock_items', 'low_stock_items', 'recent_activities',
            'scheduledStockChecks', 'stockCycles',
            'lockedStockCount', 'warningStockCount'
        ));
    }

    public function getChartData(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $categoryId = $request->input('category_id');
        $equipmentId = $request->input('equipment_id');

        $query = Transaction::select(
                DB::raw('MONTH(transaction_date) as month'),
                // 1. Received (รับเข้า): ใช้ 'receive' เท่านั้น
                DB::raw("SUM(CASE WHEN type = 'receive' THEN quantity_change ELSE 0 END) as total_received"),
                
                // 2. Withdrawn (เบิกออก): รวม 'withdraw', 'consumable', 'partial_return'
                // ใช้ ABS() เพราะ quantity_change เป็นค่าติดลบเมื่อเบิกออก
                DB::raw("SUM(CASE WHEN type IN ('withdraw', 'consumable', 'partial_return') THEN ABS(quantity_change) ELSE 0 END) as total_withdrawn"),
                
                // 3. Borrowed (ยืม): รวม 'borrow' และ 'returnable'
                // ใช้ ABS() เพราะ quantity_change เป็นค่าติดลบเมื่อยืมออก
                DB::raw("SUM(CASE WHEN type IN ('borrow', 'returnable') THEN ABS(quantity_change) ELSE 0 END) as total_borrowed"),
                
                // 4. Returned (คืน): ใช้ 'return' เท่านั้น
                DB::raw("SUM(CASE WHEN type = 'return' THEN quantity_change ELSE 0 END) as total_returned")
            )
            ->whereYear('transaction_date', $year)
            ->groupBy('month');

        if ($categoryId) {
            $query->whereHas('equipment', function($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }
        if ($equipmentId) {
            $query->where('equipment_id', $equipmentId);
        }

        $transactionsByMonth = $query->get()->keyBy('month');
        $labels = [];
        $data = ['received' => array_fill(1, 12, 0), 'withdrawn' => array_fill(1, 12, 0), 'borrowed' => array_fill(1, 12, 0), 'returned' => array_fill(1, 12, 0)];
        for ($m = 1; $m <= 12; $m++) {
            // ดึงชื่อเดือนภาษาไทย (ขึ้นอยู่กับการตั้งค่า Locale ใน Laravel)
            $labels[] = Carbon::create()->month($m)->locale('th_TH')->monthName; 
            if ($transactionsByMonth->has($m)) {
                $monthData = $transactionsByMonth->get($m);
                // บังคับแปลงเป็น Integer เพื่อความเสถียรของ Chart.js
                $data['received'][$m] = (int)$monthData->total_received;
                $data['withdrawn'][$m] = (int)$monthData->total_withdrawn;
                $data['borrowed'][$m] = (int)$monthData->total_borrowed;
                $data['returned'][$m] = (int)$monthData->total_returned;
            }
        }
        
        $datasets = [
            'received' => ['label' => 'รับเข้า', 'data' => array_values($data['received'])],
            'withdrawn' => ['label' => 'เบิก', 'data' => array_values($data['withdrawn'])],
            'borrowed' => ['label' => 'ยืม', 'data' => array_values($data['borrowed'])],
            'returned' => ['label' => 'คืน', 'data' => array_values($data['returned'])],
        ];

        return response()->json(['labels' => $labels, 'datasets' => $datasets]);
    }
    
    public function searchEquipmentForChart(Request $request)
    {
        $term = $request->input('term', '');
        $items = Equipment::where('name', 'LIKE', "%{$term}%")
            ->select('id', 'name as text')
            ->limit(20)
            ->get();
        return response()->json(['results' => $items]);
    }
}