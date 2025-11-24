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
        $stockChecks = StockCheck::with('checker')
            ->orderBy('scheduled_date', 'desc')
            ->paginate(15);

        return view('stock-check.index', compact('stockChecks'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get()->map(function($category) {
            $oldestItem = Equipment::where('category_id', $category->id)
                ->where('status', '!=', 'sold')
                ->orderBy('last_stock_check_at', 'asc')
                ->first();

            $totalItems = Equipment::where('category_id', $category->id)->where('status', '!=', 'sold')->count();
            
            $status = 'normal';
            $daysLeft = 105;
            $lastCheck = 'ยังไม่เคยนับ';

            if ($totalItems > 0 && $oldestItem) {
                if ($oldestItem->last_stock_check_at) {
                    $daysLeft = 105 - now()->diffInDays($oldestItem->last_stock_check_at);
                    $lastCheck = $oldestItem->last_stock_check_at->format('d/m/Y');
                } else {
                    $daysLeft = -1; 
                    $lastCheck = 'ไม่เคยนับ';
                }

                if ($daysLeft <= 0) $status = 'critical';
                elseif ($daysLeft <= 15) $status = 'warning';
                else $status = 'normal';
            } elseif ($totalItems == 0) {
                $status = 'empty';
            }

            $category->stock_status = [
                'status' => $status,
                'days_left' => $daysLeft,
                'last_check' => $lastCheck,
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
            ]);

            $query = Equipment::where('status', '!=', 'sold');

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            $equipmentsToCount = $query->get();

            if ($equipmentsToCount->isEmpty()) {
                DB::rollBack();
                return back()->with('error', 'ไม่พบอุปกรณ์ในหมวดหมู่ที่เลือก กรุณาเลือกใหม่')->withInput();
            }

            foreach ($equipmentsToCount as $equipment) {
                StockCheckItem::create([
                    'stock_check_id' => $stockCheck->id,
                    'equipment_id' => $equipment->id,
                    'expected_quantity' => $equipment->quantity,
                ]);
            }

            DB::commit();
            return redirect()->route('stock-checks.index')->with('success', 'สร้างงานตรวจนับสต็อกสำเร็จแล้ว! (จำนวน ' . $equipmentsToCount->count() . ' รายการ)');

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
        $request->validate([
            'items' => 'required|array',
            'items.*.counted_quantity' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // 1. บันทึกค่าที่กรอกเข้ามาก่อน (Updated_at ของ Item จะเปลี่ยนตรงนี้)
            foreach ($request->items as $itemId => $data) {
                $item = StockCheckItem::find($itemId);
                if ($item && $item->stock_check_id === $stockCheck->id) {
                    $counted = $data['counted_quantity'] ?? 0;
                    
                    // เช็คว่ามีการเปลี่ยนแปลงค่าหรือไม่ เพื่อให้ updated_at ทำงานแน่นอน
                    if ($item->counted_quantity !== $counted) {
                        $item->counted_quantity = $counted;
                        $item->discrepancy = $counted - $item->expected_quantity;
                        $item->save(); // timestamp 'updated_at' จะอัปเดตเป็นเวลาปัจจุบัน
                    }
                }
            }

            // เช็คว่านับครบทุกรายการหรือยัง
            $isComplete = $stockCheck->items()->whereNull('counted_quantity')->count() === 0;

            // 2. ถ้ากดปุ่ม "ปิดงาน" (Complete)
            if ($request->has('complete_check') && $isComplete) {
                
                $stockCheck->status = 'completed';
                $stockCheck->completed_at = now();
                $stockCheck->checked_by_user_id = Auth::id();
                $stockCheck->save();

                $allItemsInCheck = $stockCheck->items()->with('equipment')->get();

                foreach ($allItemsInCheck as $checkItem) {
                    $equipment = $checkItem->equipment;
                    
                    if ($equipment) {
                        // ✅✅✅ FIX: ใช้เวลาที่กรอกข้อมูลจริง ($checkItem->updated_at) แทนเวลาปิดงาน (now())
                        // ถ้าไม่มี updated_at ให้ใช้ now() สำรอง
                        $equipment->last_stock_check_at = $checkItem->updated_at ?? now(); 

                        // ถ้าเคย Frozen -> คำนวณสถานะใหม่ตามจำนวน
                        if ($equipment->status === 'frozen') {
                            if ($checkItem->counted_quantity <= 0) {
                                $equipment->status = 'out_of_stock';
                            } elseif ($equipment->min_stock > 0 && $checkItem->counted_quantity <= $equipment->min_stock) {
                                $equipment->status = 'low_stock';
                            } else {
                                $equipment->status = 'available';
                            }
                        }

                        if ($checkItem->discrepancy != 0) {
                            $equipment->quantity = $checkItem->counted_quantity;
                            
                            Transaction::create([
                                'equipment_id'    => $equipment->id,
                                'user_id'         => Auth::id(),
                                'type'            => 'adjust',
                                'quantity_change' => $checkItem->discrepancy,
                                'notes'           => 'ปรับสต็อกจากงานตรวจนับ: ' . $stockCheck->name,
                                'transaction_date'=> now(),
                                'status'          => 'completed',
                            ]);
                        }

                        $equipment->save();
                    }
                }

                DB::commit(); 

                try {
                    Artisan::call('stock:check-expiration');
                    Log::info("Triggered stock expiration check after completing StockCheck #{$stockCheck->id}");
                } catch (\Throwable $e) {
                    Log::warning("Failed to trigger stock expiration check: " . $e->getMessage());
                }

                return redirect()->route('stock-checks.show', $stockCheck)->with('success', 'บันทึกและปิดงานตรวจนับเรียบร้อยแล้ว');

            } elseif ($request->has('complete_check') && !$isComplete) {
                DB::rollBack();
                return back()->with('error', 'กรุณากรอกข้อมูล "จำนวนที่นับได้" ให้ครบทุกรายการก่อนปิดงาน');
            }

            DB::commit();
            return back()->with('success', 'บันทึกความคืบหน้าเรียบร้อยแล้ว');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
}