<?php

namespace App\Http\Controllers;

use App\Models\StockCheck;
use App\Models\StockCheckItem;
use App\Models\Equipment;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan; 
use Illuminate\Support\Facades\Log;

class StockCheckController extends Controller
{
    public function index()
    {
        $stockChecks = StockCheck::with(['checker', 'category'])
            ->orderBy('scheduled_date', 'desc')
            ->paginate(15);

        return view('stock-check.index', compact('stockChecks'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get()->map(function($category) {
            
            $lastCheckDate = null;

            // ตรวจสอบวันที่นับล่าสุดของหมวดหมู่นั้นๆ
            $lastStockCheck = StockCheck::where('status', 'completed')
                ->where('category_id', $category->id) 
                ->latest('completed_at')
                ->first();

            if ($lastStockCheck && $lastStockCheck->completed_at) {
                $lastCheckDate = $lastStockCheck->completed_at;
            } 
            
            // คำนวณสถานะ
            $totalItems = Equipment::where('category_id', $category->id)->where('status', '!=', 'sold')->count();
            $status = 'normal';
            $daysLeft = 105; 
            
            $lastCheckStr = ($lastCheckDate) ? $lastCheckDate->format('Y-m-d H:i:s') : '-';

            if ($totalItems == 0) {
                $status = 'empty';
            } elseif ($lastCheckDate) {
                $daysLeft = 105 - now()->diffInDays($lastCheckDate);
                
                if ($daysLeft <= 0) $status = 'critical';
                elseif ($daysLeft <= 15) $status = 'warning';
            } else {
                $status = 'critical';
            }

            $category->stock_status = [
                'status' => $status,
                'days_left' => $daysLeft,
                'last_check' => $lastCheckStr,
                'total_items' => $totalItems
            ];

            return $category;
        });

        return view('stock-check.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'scheduled_date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        DB::beginTransaction();
        try {
            $stockCheck = StockCheck::create([
                'name' => $request->name,
                'scheduled_date' => $request->scheduled_date,
                'status' => 'scheduled',
                'category_id' => $request->category_id, 
            ]);

            $query = Equipment::where('status', '!=', 'sold');

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            $equipmentsToCount = $query->get();

            if ($equipmentsToCount->isEmpty()) {
                DB::rollBack();
                return back()->with('error', 'ไม่พบอุปกรณ์ในหมวดหมู่ที่เลือก')->withInput();
            }

            foreach ($equipmentsToCount as $equipment) {
                StockCheckItem::create([
                    'stock_check_id' => $stockCheck->id,
                    'equipment_id' => $equipment->id,
                    'expected_quantity' => $equipment->quantity,
                ]);
            }

            DB::commit();
            return redirect()->route('stock-checks.index')->with('success', 'สร้างงานตรวจนับสำเร็จ');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage())->withInput();
        }
    }

    public function show(StockCheck $stockCheck)
    {
        $stockCheck->load(['items.equipment.unit', 'checker']);
        return view('stock-check.show', compact('stockCheck'));
    }

    public function perform(StockCheck $stockCheck)
    {
        if ($stockCheck->status === 'completed') {
            return redirect()->route('stock-checks.show', $stockCheck)->with('info', 'งานตรวจนับนี้เสร็จสิ้นแล้ว');
        }
        if ($stockCheck->status === 'scheduled') {
            $stockCheck->status = 'in_progress';
            $stockCheck->save();
        }
        $items = $stockCheck->items()->with('equipment.unit')->get();
        return view('stock-check.perform', compact('stockCheck', 'items'));
    }

    public function update(Request $request, StockCheck $stockCheck)
    {
        // ✅ [NEW] ปุ่มล้างค่า: รีเซ็ตข้อมูลทั้งหมดกลับเป็น NULL เพื่อเริ่มนับใหม่
        if ($request->has('reset_progress')) {
            $stockCheck->items()->update([
                'counted_quantity' => null, 
                'discrepancy' => null
            ]);
            return back()->with('success', 'ล้างข้อมูลการนับทั้งหมด ให้เริ่มใหม่เรียบร้อยแล้ว');
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.counted_quantity' => 'nullable', // อนุญาตให้ส่งค่าว่าง/null ได้
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->items as $itemId => $data) {
                $item = StockCheckItem::find($itemId);
                if ($item && $item->stock_check_id === $stockCheck->id) {
                    
                    // ✅ [FIX] ตรวจสอบค่าว่าง: ถ้าว่างให้เป็น NULL (ยังไม่นับ) ถ้ามีค่าให้เป็นตัวเลข
                    $inputVal = $data['counted_quantity'];

                    if ($inputVal === null || $inputVal === '') {
                        $item->counted_quantity = null;
                        $item->discrepancy = null;
                    } else {
                        $counted = (int) $inputVal;
                        $item->counted_quantity = $counted;
                        $item->discrepancy = $counted - $item->expected_quantity;
                    }
                    
                    $item->save();
                }
            }

            // ตรวจสอบว่านับครบทุกรายการหรือยัง (นับเฉพาะที่ไม่ใช่ NULL)
            $isComplete = $stockCheck->items()->whereNull('counted_quantity')->count() === 0;

            if ($request->has('complete_check') && $isComplete) {
                $stockCheck->status = 'completed';
                $stockCheck->completed_at = now();
                $stockCheck->checked_by_user_id = Auth::id();
                $stockCheck->save();

                $allItemsInCheck = $stockCheck->items()->with('equipment')->get();
                foreach ($allItemsInCheck as $checkItem) {
                    $equipment = $checkItem->equipment;
                    if ($equipment) {
                        $equipment->last_stock_check_at = $stockCheck->completed_at;
                        
                        // ปรับสถานะ Low Stock / Out of Stock
                        if ($equipment->status === 'frozen') {
                            if ($checkItem->counted_quantity <= 0) $equipment->status = 'out_of_stock';
                            elseif ($equipment->min_stock > 0 && $checkItem->counted_quantity <= $equipment->min_stock) $equipment->status = 'low_stock';
                            else $equipment->status = 'available';
                        }
                        
                        // สร้าง Transaction ปรับยอดถ้ามีผลต่าง
                        if ($checkItem->discrepancy != 0) {
                            $equipment->quantity = $checkItem->counted_quantity;
                            Transaction::create([
                                'equipment_id' => $equipment->id,
                                'user_id' => Auth::id(),
                                'type' => 'adjust',
                                'quantity_change' => $checkItem->discrepancy,
                                'notes' => 'ปรับสต็อกจากงานตรวจนับ: ' . $stockCheck->name,
                                'transaction_date' => now(),
                                'status' => 'completed',
                            ]);
                        }
                        $equipment->save();
                    }
                }
                DB::commit(); 
                try { Artisan::call('stock:check-expiration'); } catch (\Throwable $e) {}
                return redirect()->route('stock-checks.show', $stockCheck)->with('success', 'บันทึกและปิดงานตรวจนับเรียบร้อยแล้ว');
            } elseif ($request->has('complete_check') && !$isComplete) {
                DB::rollBack();
                return back()->with('error', 'กรุณากรอกข้อมูลให้ครบทุกรายการก่อนปิดงาน');
            }

            DB::commit();
            return back()->with('success', 'บันทึกความคืบหน้าเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}