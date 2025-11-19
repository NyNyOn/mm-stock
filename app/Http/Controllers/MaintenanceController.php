<?php

namespace App\Http\Controllers;

// --- 1. Use Statements (จากไฟล์ "ก่อนแก้" ของคุณ) ---
use App\Models\MaintenanceLog;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// --- 2. Use Statements (จากส่วน "ปิดปรับปรุง" ที่เราเพิ่ม) ---
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
// (เราไม่ต้อง 'use Illuminate\Routing\Controller;' แล้ว เพราะเราจะ extends 'Controller' ที่อยู่ใน namespace นี้)


// คลาสนี้จะ extends Controller ที่ถูกต้อง (app/Http/Controllers/Controller.php)
class MaintenanceController extends Controller
{
    /**
     * Display the maintenance list page.
     * (โค้ดจากไฟล์ "ก่อนแก้" ที่ถูกต้องของคุณ)
     */
    public function index()
    {
        // 1. โหลด Log พร้อม "อุปกรณ์ชั่วคราว" (ที่เก็บ notes) และ "ผู้แจ้งซ่อม"
        $maintenanceLogs = MaintenanceLog::with(['equipment', 'reportedBy'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. ดึง ID ของ "อุปกรณ์หลัก" (Main Stock ID) ที่ซ่อนอยู่ใน notes
        $mainEquipmentIds = [];
        foreach ($maintenanceLogs as $log) {
            // เราคาดหวัง notes รูปแบบ "แยกซ่อมจาก ID: 123"
            if ($log->equipment && preg_match('/ID: (\d+)/', $log->equipment->notes, $matches)) {
                $mainEquipmentIds[] = (int)$matches[1];
            }
        }

        // 3. โหลด "อุปกรณ์หลัก" ทั้งหมด (ที่มีรูปภาพ) ใน query เดียว
        // (ผมเพิ่ม 'unit' และ 'images' กลับเข้าไป, เผื่อคุณยังใช้ใน view)
        $mainEquipmentItems = Equipment::with(['primaryImage', 'latestImage', 'unit', 'images'])
            ->findMany(array_unique($mainEquipmentIds))
            ->keyBy('id'); // Key by ID เพื่อให้ค้นหาได้เร็ว

        // 4. ผูก "อุปกรณ์หลัก" (ที่มีรูป) กลับเข้าไปใน $log แต่ละตัว
        foreach ($maintenanceLogs as $log) {
            $mainStockId = null;
            if ($log->equipment && preg_match('/ID: (\d+)/', $log->equipment->notes, $matches)) {
                $mainStockId = (int)$matches[1];
            }
            
            // สร้าง property ใหม่ชื่อ mainStockItem เพื่อให้ View ใช้งาน
            $log->mainStockItem = $mainEquipmentItems->get($mainStockId);
        }
        
        // (ผมเพิ่ม defaultDeptKey กลับมา, เผื่อคุณยังใช้)
        $defaultDeptKey = config('department_stocks.default_key', 'it');
        return view('maintenance.index', compact('maintenanceLogs', 'defaultDeptKey'));
    }

    /**
     * Update the status of a maintenance item.
     * (โค้ดจากไฟล์ "ก่อนแก้" ที่ถูกต้องของคุณ)
     */
    public function update(Request $request, MaintenanceLog $maintenanceLog)
    {
        $action = $request->input('action');

        DB::beginTransaction();
        try {
            // $maintenanceEquipment คืออุปกรณ์ชิ้นที่สถานะเป็น 'maintenance'
            $maintenanceEquipment = $maintenanceLog->loadMissing('equipment')->equipment;

            if (!$maintenanceEquipment || $maintenanceEquipment->status !== 'maintenance') {
                DB::rollBack();
                return redirect()->route('maintenance.index')->with('error', 'ไม่พบอุปกรณ์ที่รอซ่อม!');
            }

            if ($action === 'complete_repair') {
                // --- Logic การ "รวมสต็อก" ---

                // 1. อ่าน 'notes' เพื่อหา ID ของสต็อกหลัก
                $originalStockId = null;
                if (preg_match('/ID: (\d+)/', $maintenanceEquipment->notes, $matches)) {
                    $originalStockId = (int)$matches[1];
                }

                $mainStock = $originalStockId ? Equipment::lockForUpdate()->find($originalStockId) : null;

                if ($mainStock) {
                    // 2. เพิ่มจำนวนกลับเข้าสต็อกหลัก
                    $mainStock->increment('quantity', $maintenanceEquipment->quantity);
                    // 4. ลบรายการอุปกรณ์ที่แยกไว้สำหรับซ่อมทิ้ง
                    $maintenanceEquipment->forceDelete(); 
                } else {
                    // กรณีไม่เจอสต็อกหลัก ให้เปลี่ยนสถานะกลับเป็น available
                    $maintenanceEquipment->status = 'available';
                    $maintenanceEquipment->save();
                }

                // 3. ปิด Log การซ่อม
                $maintenanceLog->status = 'completed';
                $maintenanceLog->save();

                $message = 'อุปกรณ์ ' . ($mainStock->name ?? $maintenanceEquipment->name) . ' ซ่อมเสร็จและกลับเข้าสต็อกแล้ว';

            } elseif ($action === 'write_off') {
                // เปลี่ยนสถานะของชิ้นที่แยกมาเป็น 'disposed'
                $maintenanceEquipment->status = 'disposed';
                $maintenanceEquipment->save();

                $maintenanceLog->status = 'completed';
                $maintenanceLog->save();

                $message = "อุปกรณ์ {$maintenanceEquipment->name} ถูกส่งไปรอตัดจำหน่ายเรียบร้อยแล้ว";

            } else {
                DB::rollBack();
                return redirect()->route('maintenance.index')->with('error', 'การกระทำไม่ถูกต้อง!');
            }

            DB::commit();
            return redirect()->route('maintenance.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Maintenance Update Error: ' . $e->getMessage());
            return redirect()->route('maintenance.index')->with('error', 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล!');
        }
    }


    // --- ✅✅✅ START: ฟังก์ชัน Maintenance Mode (ฉบับ 2-ขั้นตอน) ---

    /**
     * [New] Generate a secret key for the 2-step process.
     */
    public function prepareKey()
    {
        $secret = Str::random(32);
        // ส่ง Key กลับไปเป็น JSON
        return response()->json(['secret' => $secret]);
    }

    /**
     * [MODIFIED] Enable system maintenance mode.
     */
    public function enable(Request $request)
    {
        $secret = $request->input('secret');

        if (empty($secret)) {
            Log::warning('Maintenance enable failed: No secret provided.');
            return back()->with('error', 'ไม่สามารถเปิดโหมดปรับปรุงได้: ไม่พบ Secret Key');
        }

        try {
            Artisan::call('down', [
                '--secret' => $secret,
            ]);

            $request->session()->flash('maintenance_secret', $secret);

            Log::info('Maintenance mode enabled by user: ' . (auth()->user()?->username ?? 'Unknown'));

            // สร้างคุกกี้ Bypass ทันที
            $cookie = Cookie::make(
                'laravel_maintenance',
                hash('sha256', $secret), // ค่าที่ hash แล้ว
                60 * 24 // 1 วัน
            );

            // ส่ง Redirect กลับไปหน้า Settings "พร้อมกับ" คุกกี้ Bypass
            return redirect()->route('settings.index')
                             ->with('success', 'เปิดโหมดปิดปรับปรุงระบบเรียบร้อยแล้ว')
                             ->withCookie($cookie);

        } catch (\Exception $e) {
            Log::error('Failed to enable maintenance mode: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการเปิดโหมดปิดปรับปรุง: ' . $e->getMessage());
        }
    }

    /**
     * Disable system maintenance mode.
     */
    public function disable()
    {
        try {
            if (!File::exists(storage_path('framework/down'))) {
                return redirect()->route('settings.index')->with('info', 'ระบบไม่ได้อยู่ในโหมดปิดปรับปรุง');
            }

            Artisan::call('up');
            Log::info('Maintenance mode disabled by user: ' . (auth()->user()?->username ?? 'Unknown'));
            $cookie = Cookie::forget('laravel_maintenance');

            return redirect()->route('settings.index')
                             ->with('success', 'ปิดโหมดปิดปรับปรุงระบบเรียบร้อยแล้ว')
                             ->withCookie($cookie);

        } catch (\Exception $e) {
            Log::error('Failed to disable maintenance mode: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการปิดโหมดปิดปรับปรุง: ' . $e->getMessage());
        }
    }

    /**
     * Helper function to check current status
     * @return bool
     */
    public static function isDownForMaintenance(): bool
    {
        return File::exists(storage_path('framework/down'));
    }
    // --- ✅✅✅ END: ฟังก์ชัน Maintenance Mode ---
}

