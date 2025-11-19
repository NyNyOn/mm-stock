<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\MaintenanceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str; // ✅ Add Str facade

class JobOrderController extends Controller
{
    public function create()
    {
        $maintenanceLogs = MaintenanceLog::whereIn('status', ['pending', 'in_progress'])
            ->with('equipment')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('purchase-orders.job-order.create', compact('maintenanceLogs'));
    }

    public function store(Request $request)
    {
        // ✅ FIX: เพิ่ม Validation Rules ใหม่
        $request->validate([
            'maintenance_log_id' => 'required|exists:maintenance_logs,id',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.specs' => 'nullable|string',
            'items.*.link' => 'nullable|url|max:255',
            'items.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $maintenanceLog = MaintenanceLog::findOrFail($request->maintenance_log_id);
        $equipmentName = $maintenanceLog->equipment->name ?? 'N/A';
        $notes = "สั่งซื้อสำหรับใบแจ้งซ่อม #{$maintenanceLog->id} ({$equipmentName})";

        DB::beginTransaction();
        try {
            $jobPo = PurchaseOrder::create([
                'type'   => 'job_order', 'status' => 'pending',
                'notes'  => $notes, 'ordered_by_user_id' => Auth::id(),
            ]);

            foreach ($request->items as $index => $itemData) {
                $imagePath = null;
                // ✅ NEW: Logic การอัปโหลดรูปภาพ
                if ($request->hasFile("items.{$index}.image")) {
                    $file = $request->file("items.{$index}.image");
                    $imagePath = Str::uuid() . '.' . $file->extension();
                    $file->move(public_path('uploads/po_items'), $imagePath);
                }

                PurchaseOrderItem::create([
                    'purchase_order_id' => $jobPo->id,
                    'item_description'  => $itemData['name'],
                    'quantity_ordered'  => $itemData['quantity'],
                    'specifications'    => $itemData['specs'],    // ✅ NEW
                    'reference_link'    => $itemData['link'],     // ✅ NEW
                    'image'             => $imagePath,            // ✅ NEW
                    'status' => 'pending_for_new_item',
                ]);
            }

            DB::commit();
            return redirect()->route('purchase-orders.index')->with('success', 'สร้างใบสั่งซื้อตาม Job สำเร็จแล้ว');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage())->withInput();
        }
    }
}
