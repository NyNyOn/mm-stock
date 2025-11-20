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
        $userDeptKey = Config::get('department_stocks.default_key', 'mm');

        try {
            foreach ($departments as $key => $dept) {
                
                $this->switchToDb($dept['db_name']);

                $query = Equipment::with(['unit']) 
                    ->where(function ($q) use ($searchTerm) {
                        $q->where('name', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('serial_number', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('part_no', 'LIKE', "%{$searchTerm}%");
                    })
                    ->where('quantity', '>', 0)
                    ->whereIn('status', ['available', 'low_stock']); 

                // ✅✅✅ เพิ่ม: ดึงค่าคะแนนเฉลี่ย (Rating) ✅✅✅
                try {
                    if (method_exists(Equipment::class, 'transactions')) {
                        $query->withAvg('transactions', 'rating');
                    }
                } catch (\Exception $e) { }
                
                $results = $query->get();

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

                        // ✅✅✅ เพิ่ม: Format ค่า Rating ✅✅✅
                        // (ค่าจาก DB จะชื่อ transactions_avg_rating)
                        if (isset($item->transactions_avg_rating) && $item->transactions_avg_rating) {
                            $item->avg_rating = number_format($item->transactions_avg_rating, 2);
                        } else {
                            $item->avg_rating = null; 
                        }
                    });
                }
                
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