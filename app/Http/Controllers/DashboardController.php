<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipment;
use App\Models\Transaction;
use App\Models\PurchaseOrder;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
// ✅ 1. เพิ่ม Use Statement สำหรับ Cache
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        // --- ✅ 2. กำหนดเวลา Cache (วินาที) ---
        $statCacheTime = 600; // 10 นาที สำหรับการ์ดตัวเลข
        $listCacheTime = 300; // 5 นาที สำหรับรายการ (Lists)
        $filterCacheTime = 3600; // 1 ชั่วโมง สำหรับฟิลเตอร์ (ปี, หมวดหมู่)

        // --- Stat Cards Data (With Cache) ---
        $total_equipment = Cache::remember('dashboard_total_equipment', $statCacheTime, function () {
            return Equipment::sum('quantity');
        });

        $low_stock_count = Cache::remember('dashboard_low_stock_count', $statCacheTime, function () {
            return Equipment::where('min_stock', '>', 0)->whereColumn('quantity', '<=', 'min_stock')->count();
        });

        $on_order_count = Cache::remember('dashboard_on_order_count', $statCacheTime, function () {
            return Equipment::where('status', 'on-order')->count();
        });

        $warranty_count = Cache::remember('dashboard_warranty_count', $statCacheTime, function () {
            return Equipment::whereNotNull('warranty_date')->whereBetween('warranty_date', [now(), now()->addDays(30)])->count();
        });

        $urgent_order_count = Cache::remember('dashboard_urgent_order_count', $statCacheTime, function () {
            return PurchaseOrder::where('type', 'urgent')->count();
        });

        $scheduled_order_count = Cache::remember('dashboard_scheduled_order_count', $statCacheTime, function () {
            return PurchaseOrder::where('type', 'scheduled')->count();
        });

        $pending_transactions_count = Cache::remember('dashboard_pending_tx_count', $statCacheTime, function () {
            return Transaction::where('status', 'pending')->count();
        });

        $job_order_count = Cache::remember('dashboard_job_order_count', $statCacheTime, function () {
            return PurchaseOrder::where('type', 'job_order')->count();
        });

        // --- List Data (With Cache) ---
        $on_order_items = Cache::remember('dashboard_on_order_items', $listCacheTime, function () {
            return Equipment::where('status', 'on-order')->orderBy('name')->limit(5)->get();
        });

        $out_of_stock_items = Cache::remember('dashboard_out_of_stock_items', $listCacheTime, function () {
            return Equipment::where('quantity', '<=', 0)->orderBy('name')->limit(5)->get();
        });

        $low_stock_items = Cache::remember('dashboard_low_stock_items', $listCacheTime, function () {
            return Equipment::where('min_stock', '>', 0)->whereColumn('quantity', '<=', 'min_stock')->orderBy('quantity')->limit(5)->get();
        });

        // --- ⚡️ NON-CACHED DATA ---
        // (Query นี้เร็วอยู่แล้ว และควรจะ Real-time เพราะมี Pagination)
        $recent_activities = Transaction::with(['equipment', 'user'])->latest('transaction_date')->paginate(5);

        // --- Data for Chart Filters (With Cache) ---
        $available_years = Cache::remember('dashboard_available_years', $filterCacheTime, function () {
            return Transaction::select(DB::raw('YEAR(transaction_date) as year'))
                                ->whereNotNull('transaction_date')
                                ->distinct()
                                ->orderBy('year', 'desc')
                                ->pluck('year');
        });

        $categories = Cache::remember('dashboard_categories', $filterCacheTime, function () {
            return Category::orderBy('name')->get();
        });

        return view('dashboard.index', compact(
            'total_equipment', 'low_stock_count', 'on_order_count', 'warranty_count',
            'urgent_order_count', 'scheduled_order_count', 'pending_transactions_count',
            'job_order_count',
            'available_years', 'categories',
            'on_order_items', 'out_of_stock_items', 'low_stock_items', 'recent_activities'
        ));
    }

    // ... (ส่วน getChartData และ searchEquipmentForChart เหมือนเดิม ไม่ต้องแก้ไข) ...
    // (ฟังก์ชันเหล่านี้ถูกเรียกด้วย AJAX และทำงานเร็วอยู่แล้ว)
    public function getChartData(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $categoryId = $request->input('category_id');
        $equipmentId = $request->input('equipment_id');

        $query = Transaction::select(
                DB::raw('MONTH(transaction_date) as month'),
                DB::raw("SUM(CASE WHEN type = 'receive' THEN quantity_change ELSE 0 END) as total_received"),
                DB::raw("SUM(CASE WHEN type = 'withdraw' THEN ABS(quantity_change) ELSE 0 END) as total_withdrawn"),
                DB::raw("SUM(CASE WHEN type = 'borrow' THEN ABS(quantity_change) ELSE 0 END) as total_borrowed"),
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
            $labels[] = Carbon::create()->month($m)->locale('th_TH')->monthName;
            if ($transactionsByMonth->has($m)) {
                $monthData = $transactionsByMonth->get($m);
                $data['received'][$m] = $monthData->total_received;
                $data['withdrawn'][$m] = $monthData->total_withdrawn;
                $data['borrowed'][$m] = $monthData->total_borrowed;
                $data['returned'][$m] = $monthData->total_returned;
            }
        }
        $datasets = [
            'received' => ['label' => 'รับเข้า', 'data' => array_values($data['received']), 'backgroundColor' => '#4ade80'],
            'withdrawn' => ['label' => 'เบิก', 'data' => array_values($data['withdrawn']), 'backgroundColor' => '#f87171'],
            'borrowed' => ['label' => 'ยืม', 'data' => array_values($data['borrowed']), 'backgroundColor' => '#facc15'],
            'returned' => ['label' => 'คืน', 'data' => array_values($data['returned']), 'backgroundColor' => '#60a5fa'],
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
