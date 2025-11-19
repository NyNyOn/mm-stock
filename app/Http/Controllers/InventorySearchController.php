<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Equipment; // (คงเดิม)
use App\Models\EquipmentImage; // (คงเดิม)

class InventorySearchController extends Controller
{
    private string $defaultDbName;
    private string $defaultConnection = 'mysql';

    /**
     * ตั้งค่าพื้นฐาน (Database, Connection)
     */
    public function __construct()
    {
        $this->defaultConnection = Config::get('database.default', 'mysql');
        $this->defaultDbName = Config::get('database.connections.' . $this->defaultConnection . '.database');
    }

    /**
     * (Helper 1) ฟังก์ชันสำหรับสลับการเชื่อมต่อ Database
     */
    private function switchToDb(string $dbName)
    {
        if (empty($dbName)) {
            $dbName = $this->defaultDbName;
        }
        if (Config::get('database.connections.' . $this->defaultConnection . '.database') === $dbName) {
            return;
        }
        DB::purge($this->defaultConnection);
        Config::set('database.connections.' . $this->defaultConnection . '.database', $dbName);
    }

    /**
     * (Helper 2) ฟังก์ชันสำหรับสลับกลับไป Database หลัก
     */
    private function switchToDefaultDb()
    {
        $this->switchToDb($this->defaultDbName);
    }

    /**
     * Method หลัก: รับคำค้นหาจาก AJAX และส่งผลลัพธ์กลับไปเป็น JSON
     */
    public function ajaxSearch(Request $request)
    {
        $searchTerm = $request->query('query');
        $myStock = [];
        $otherStock = [];

        if (strlen($searchTerm) < 2) {
            return response()->json(['myStock' => [], 'otherStock' => []]);
        }

        $departments = Config::get('department_stocks.departments', []);

        // 
        // 📍 (แก้ไขแล้ว) 📍
        // ลบโค้ด 2 บรรทัดนี้ (Auth::user()->load('department');)
        // ที่ทำให้เกิด Lỗi "Call to undefined relationship [department]"
        // และเปลี่ยนมาใช้ 'default_key' ตาม EquipmentController
        // 
        $userDeptKey = Config::get('department_stocks.default_key', 'wh');

        try {
            foreach ($departments as $key => $dept) {
                
                $this->switchToDb($dept['db_name']);

                // (คงเดิม) ค้นหา Equipment พร้อม unit
                $query = Equipment::with(['unit']) 
                    ->where(function ($q) use ($searchTerm) {
                        $q->where('name', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('serial_number', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('part_no', 'LIKE', "%{$searchTerm}%");
                    })
                    ->where('quantity', '>', 0)
                    ->whereIn('status', ['available', 'low_stock']); 

                $results = $query->get();

                // (คงเดิม) ค้นหารูปภาพ
                if ($results->isNotEmpty()) {
                    
                    $equipmentIds = $results->pluck('id')->toArray();
                    
                    $images = EquipmentImage::whereIn('equipment_id', $equipmentIds)
                                        ->select('equipment_id', 'file_name', 'is_primary')
                                        ->get()
                                        ->groupBy('equipment_id');

                    $results->each(function ($item) use ($images) {
                        $itemImages = $images->get($item->id);
                        $primaryImage = null;
                        if ($itemImages) {
                            $primaryImage = $itemImages->firstWhere('is_primary', true) ?? $itemImages->first();
                        }
                        $item->primary_image_file_name_manual = $primaryImage ? $primaryImage->file_name : null;
                    });
                }
                
                // (คงเดิม) แยกสต็อก
                foreach ($results as $equipment) {
                    $equipment->dept_key = $key; 
                    $equipment->dept_name = $dept['name']; 

                    if ($key === $userDeptKey) {
                        $myStock[] = $equipment;
                    } else {
                        $otherStock[] = $equipment;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('AJAX Search Failed: ' . $e->getMessage());
            $this->switchToDefaultDb();
            return response()->json(['error' => 'เกิดข้อผิดพลาดในการค้นหา: ' . $e->getMessage()], 500);
        }

        $this->switchToDefaultDb();

        return response()->json([
            'myStock'    => $myStock,
            'otherStock' => $otherStock,
        ]);
    }
}