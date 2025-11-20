<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Equipment;
use App\Models\EquipmentImage;

class InventorySearchController extends Controller
{
    private string $defaultDbName;
    private string $defaultConnection = 'mysql';

    public function __construct()
    {
        $this->defaultConnection = Config::get('database.default', 'mysql');
        $this->defaultDbName = Config::get('database.connections.' . $this->defaultConnection . '.database');
    }

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
        DB::reconnect($this->defaultConnection);
    }

    private function switchToDefaultDb()
    {
        $this->switchToDb($this->defaultDbName);
    }

    public function ajaxSearch(Request $request)
    {
        $searchTerm = $request->query('query');
        $myStock = [];
        $otherStock = [];

        if (strlen($searchTerm) < 2) {
            return response()->json(['myStock' => [], 'otherStock' => []]);
        }

        $departments = Config::get('department_stocks.departments', []);
        $user = Auth::user();
        // ดึงแผนกของผู้ใช้ (ปรับ field ตามจริง เช่น department_code)
        $userDeptKey = $user->department_code ?? Config::get('department_stocks.default_key', 'mm');
        $defaultNasDeptKey = Config::get('department_stocks.default_nas_dept_key', 'mm');

        try {
            foreach ($departments as $key => $dept) {
                
                // 1. สลับ Database
                try {
                    $this->switchToDb($dept['db_name']);
                } catch (\Exception $e) {
                    Log::error("Cannot switch to DB {$dept['db_name']}: " . $e->getMessage());
                    continue; // ข้ามแผนกนี้ถ้าต่อ Database ไม่ได้
                }

                // 2. สร้าง Query
                $query = Equipment::with(['unit']) 
                    ->where(function ($q) use ($searchTerm) {
                        $q->where('name', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('serial_number', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('part_no', 'LIKE', "%{$searchTerm}%");
                    })
                    ->where('quantity', '>', 0)
                    ->whereIn('status', ['available', 'low_stock']); 

                // ✅ 3. เพิ่ม Rating (ใส่ try-catch ย่อย เพื่อกัน Error 500 ถ้าตารางไม่มี)
                try {
                    if (method_exists(Equipment::class, 'ratings')) {
                        $query->withAvg('ratings', 'rating');
                        $query->withCount('ratings');
                    }
                } catch (\Exception $e) {
                    // ถ้า Error เรื่อง Rating ให้ปล่อยผ่าน (ดึงแค่ข้อมูลของ)
                }
                
                $results = $query->limit(20)->get();

                if ($results->isNotEmpty()) {
                    $equipmentIds = $results->pluck('id')->toArray();
                    // ดึงรูปภาพ
                    $images = EquipmentImage::whereIn('equipment_id', $equipmentIds)
                                            ->select('equipment_id', 'file_name', 'is_primary')
                                            ->get()
                                            ->groupBy('equipment_id');

                    $results->each(function ($item) use ($images, $key, $defaultNasDeptKey) {
                        // รูปภาพ
                        $itemImages = $images->get($item->id);
                        $primaryImage = null;
                        if ($itemImages) {
                            $primaryImage = $itemImages->firstWhere('is_primary', true) ?? $itemImages->first();
                        }
                        $imageFileName = $primaryImage ? $primaryImage->file_name : null;

                        if ($imageFileName) {
                            $item->image_url = route('nas.image', ['deptKey' => $key, 'filename' => $imageFileName]);
                        } else {
                            $item->image_url = asset('images/placeholder.webp');
                        }

                        // ✅ คะแนน (แปลงเป็น float เพื่อความชัวร์)
                        $item->avg_rating = isset($item->ratings_avg_rating) ? (float)$item->ratings_avg_rating : 0;
                        $item->rating_count = $item->ratings_count ?? 0;
                        
                        $item->dept_key = $key;
                    });
                }
                
                // 4. แยกผลลัพธ์
                foreach ($results as $equipment) {
                    $equipment->dept_name = $dept['name']; 

                    // ถ้าเป็นแผนก User หรือ แผนก Default (MM) ถือเป็น My Stock
                    if ($key === $userDeptKey || $key === $defaultNasDeptKey) {
                        $myStock[] = $equipment;
                    } else {
                        $otherStock[] = $equipment;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('AJAX Search Failed: ' . $e->getMessage());
            $this->switchToDefaultDb();
            return response()->json(['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }

        $this->switchToDefaultDb();

        return response()->json([
            'myStock'    => $myStock,
            'otherStock' => $otherStock,
        ]);
    }
}