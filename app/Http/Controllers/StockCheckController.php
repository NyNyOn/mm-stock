<?php

namespace App\Http\Controllers;

use App\Models\StockCheck;
use App\Models\StockCheckItem;
use App\Models\Equipment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class StockCheckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $stockChecks = StockCheck::with('checker')
            ->orderBy('scheduled_date', 'desc')
            ->paginate(15);

        return view('stock-check.index', compact('stockChecks'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stock-check.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'scheduled_date' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            $stockCheck = StockCheck::create([
                'name' => $request->name,
                'scheduled_date' => $request->scheduled_date,
                'status' => 'scheduled',
            ]);

            $equipmentsToCount = Equipment::where('status', '!=', 'sold')->get();

            foreach ($equipmentsToCount as $equipment) {
                StockCheckItem::create([
                    'stock_check_id' => $stockCheck->id,
                    'equipment_id' => $equipment->id,
                    'expected_quantity' => $equipment->quantity,
                ]);
            }

            DB::commit();
            return redirect()->route('stock-checks.index')->with('success', 'สร้างงานตรวจนับสต็อกสำเร็จแล้ว!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(StockCheck $stockCheck)
    {
        $stockCheck->load(['items.equipment.unit', 'checker']);
        return view('stock-check.show', compact('stockCheck'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function perform(StockCheck $stockCheck)
    {
        if ($stockCheck->status === 'completed') {
            return redirect()->route('stock-checks.show', $stockCheck)->with('info', 'งานตรวจนับนี้เสร็จสิ้นแล้ว');
        }

        if ($stockCheck->status === 'scheduled') {
            $stockCheck->status = 'in_progress';
            $stockCheck->save();
        }

        // --- จุดแก้ไขที่ 1 ---
        // เปลี่ยนจาก paginate(50) เป็น get() เพื่อโหลดทุกรายการในหน้าเดียว
        $items = $stockCheck->items()->with('equipment.unit')->get();

        return view('stock-check.perform', compact('stockCheck', 'items'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StockCheck $stockCheck)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.counted_quantity' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // --- จุดแก้ไขที่ 2 ---
            // แก้ไข Logic การบันทึกข้อมูล
            foreach ($request->items as $itemId => $data) {
                $item = StockCheckItem::find($itemId);
                if ($item && $item->stock_check_id === $stockCheck->id) {
                    
                    // ถ้าค่าที่ส่งมาเป็น null (เช่น ผู้ใช้ลบเลขทิ้ง) ให้ถือว่าเป็น 0
                    $counted = $data['counted_quantity'] ?? 0;

                    // บันทึกค่าที่นับได้ (ไม่ว่าจะเป็น 0, 5, 10)
                    $item->counted_quantity = $counted;
                    $item->discrepancy = $counted - $item->expected_quantity;
                    $item->save();
                }
            }
            // --- จบจุดแก้ไขที่ 2 ---

            $isComplete = $stockCheck->items()->whereNull('counted_quantity')->count() === 0;

            if ($request->has('complete_check') && $isComplete) {
                $stockCheck->status = 'completed';
                $stockCheck->completed_at = now();
                $stockCheck->checked_by_user_id = Auth::id();
                $stockCheck->save();

                $itemsToAdjust = $stockCheck->items()->where('discrepancy', '!=', 0)->get();

                foreach ($itemsToAdjust as $item) {
                    $equipment = $item->equipment;
                    if ($equipment) {
                        $equipment->quantity = $item->counted_quantity;
                        $equipment->save();

                        Transaction::create([
                            'equipment_id'    => $equipment->id,
                            'user_id'         => Auth::id(),
                            'type'            => 'adjust',
                            'quantity_change' => $item->discrepancy,
                            'notes'           => 'ปรับสต็อกจากงานตรวจนับ: ' . $stockCheck->name,
                            'transaction_date'=> now(),
                            'status'          => 'completed',
                        ]);
                    }
                }

                DB::commit();
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
