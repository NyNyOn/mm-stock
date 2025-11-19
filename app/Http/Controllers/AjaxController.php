<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\Location;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use App\Models\LdapUser;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
// --- 🐞 BUG FIX: START ---
// (เพิ่ม use Config เพื่อดึงค่าจากไฟล์ .env หรือ config)
use Illuminate\Support\Facades\Config;
// --- 🐞 BUG FIX: END ---

class AjaxController extends Controller
{
    /**
     * Handle all incoming AJAX requests from the frontend.
     */
    public function handleRequest(Request $request)
    {
        $action = $request->input('action');

        switch ($action) {
            // --- (โค้ดส่วนอื่น ๆ เหมือนเดิม) ---
            case 'get_dashboard_data':
                return $this->getDashboardData();
            case 'get_equipment_details':
                return $this->getEquipmentDetails($request);
            case 'get_next_serial_number':
                return $this->getNextSerialNumber($request);
            case 'add_equipment':
                return $this->addEquipment($request);
            case 'update_equipment':
                return $this->updateEquipment($request);
            case 'delete_equipment':
                return $this->deleteEquipment($request);
            case 'search_items':
                return $this->searchItems($request); // <-- เมธอดที่เราแก้ไข
            case 'store_withdrawal':
                return $this->storeWithdrawal($request);
            case 'get_category_details':
                return $this->getSettingDetailsType($request, 'category');
            case 'create_category':
                return $this->createSettingType($request, 'category');
            case 'update_category':
                return $this->updateSettingType($request, 'category');
            case 'delete_category':
                return $this->deleteSettingType($request, 'category');
            case 'get_location_details':
                return $this->getSettingDetailsType($request, 'location');
            case 'create_location':
                return $this->createSettingType($request, 'location');
            case 'update_location':
                return $this->updateSettingType($request, 'location');
            case 'delete_location':
                return $this->deleteSettingType($request, 'location');
            case 'get_unit_details':
                return $this->getSettingDetailsType($request, 'unit');
            case 'create_unit':
                return $this->createSettingType($request, 'unit');
            case 'update_unit':
                return $this->updateSettingType($request, 'unit');
            case 'delete_unit':
                return $this->deleteSettingType($request, 'unit');
            case 'check_low_stock':
                return $this->checkLowStock();
            
            // 
            // 📍 (แก้ไขแล้ว) 📍
            // นี่คือฟังก์ชันที่ Select2 เรียกใช้
            // 
            case 'get_ldap_users':
                $searchTerm = $request->input('q', '');
                try {

                    if ($searchTerm) {
                        // 
                        // 1. ถ้าผู้ใช้ "กำลังพิมพ์" (Search Mode)
                        // 
                        $query = DB::connection('depart_it_db')->table('sync_ldap')
                            ->select('id', 'username', 'fullname', 'employeecode') 
                            ->whereNotNull('fullname')
                            ->where('fullname', '!=', '');

                        $query->where(function ($q) use ($searchTerm) {
                            $q->where('fullname', 'like', '%' . $searchTerm . '%')
                              ->orWhere('username', 'like', '%' . $searchTerm . '%')
                              ->orWhere('employeecode', 'like', '%' . $searchTerm . '%');
                        });

                        $users = $query->orderBy('fullname', 'asc')->limit(20)->get(); // (แสดง 20 รายการเมื่อค้นหา)

                        // Format ผลลัพธ์
                        $formattedUsers = $users->map(fn($user) => $this->formatLdapUserForSelect2($user));
                        
                        // ส่งกลับแบบปกติ (flat array)
                        return response()->json(['items' => $formattedUsers]);

                    } else {
                        // 
                        // 2. ถ้าผู้ใช้ "ยังไม่พิมพ์" (Default - เบิกบ่อย)
                        // 
                        
                        // 2a. ดึง Top 10 User IDs จาก 'transactions' (DB หลัก 'mysql')
                        $topUserIds = DB::connection('mysql') // <-- 🌟 ใช้ DB หลัก (it_stock)
                            ->table('transactions')
                            ->select('user_id', DB::raw('count(*) as transaction_count'))
                            ->where('type', '!=', 'return')
                            ->where('transaction_date', '>=', now()->subMonths(3))
                            ->groupBy('user_id')
                            ->orderBy('transaction_count', 'desc')
                            ->limit(10) // 🌟 (10 รายการ ตามที่คุณต้องการ)
                            ->pluck('user_id');

                        if ($topUserIds->isEmpty()) {
                            return response()->json(['items' => []]); // ไม่มีคนเบิกบ่อย
                        }

                        // 2b. ดึง User Details จาก 'depart_it_db'
                        $users = DB::connection('depart_it_db')->table('sync_ldap')
                            ->whereIn('id', $topUserIds)
                            ->select('id', 'username', 'fullname', 'employeecode')
                            ->get()
                            ->keyBy('id'); // Key by ID เพื่อเรียงลำดับ

                        // 2c. เรียงลำดับ User ตาม $topUserIds
                        $sortedUsers = $topUserIds->map(fn($id) => $users->get($id))->filter();
                        
                        // 2d. Format ผลลัพธ์
                        $formattedFrequentUsers = $sortedUsers->map(fn($user) => $this->formatLdapUserForSelect2($user));

                        // 2e. 🌟 (สำคัญ) 🌟 สร้าง Group "เบิกบ่อย"
                        $responseItems = [
                            [
                                'text' => 'คนที่เบิกบ่อย (10 รายการล่าสุด)',
                                'children' => $formattedFrequentUsers
                            ]
                        ];
                        
                        // ส่งกลับแบบมี Optgroup
                        return response()->json(['items' => $responseItems]);
                    }

                } catch (\Exception $e) {
                    Log::error('Error in getLdapUsers (Select2): ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
                    return response()->json(['items' => [], 'error' => $e->getMessage()], 500);
                }
                break; // (จบ case)
            // ✅✅✅ END: โค้ดที่แก้ไข ✅✅✅

             // --- ✅ เพิ่มเคส getUserDetails กลับเข้ามา ---
            case 'get_user_details':
                return $this->getUserDetails($request); // Assuming getUserDetails exists
             // --- ✅ เพิ่มเคส updateUserGroup กลับเข้ามา ---
            case 'update_user_group':
                return $this->updateUserGroup($request); // Assuming updateUserGroup exists
             // --- ✅ เพิ่มเคส searchEquipmentForChart กลับเข้ามา ---
            case 'search_equipment_for_chart':
                return $this->searchEquipmentForChart($request); // Assuming searchEquipmentForChart exists

            default:
                return response()->json(['success' => false, 'message' => 'Invalid action specified.']);
        }
    }

    /**
     * 🌟 (เพิ่ม) 🌟
     * Helper function สำหรับจัดรูปแบบ User
     */
    private function formatLdapUserForSelect2($user)
    {
        return [
            'id'   => $user->id,
            'text' => $user->fullname . ' (' . ($user->employeecode ?? 'N/A') . ')'
        ];
    }

    /**
     * ตรวจสอบอุปกรณ์ที่สต๊อกต่ำและยังไม่มีการสั่งซื้อ (พร้อมระบบดีบัค)
     */
    private function checkLowStock()
    {
        // --- DEBUG: เริ่มการทำงาน ---
        Log::channel('daily')->debug('=============== AJAX: checkLowStock START ===============');

        try {
            // สร้าง Query Builder
            $lowStockItemsQuery = Equipment::whereColumn('quantity', '<=', 'minimum_stock')
                ->where('minimum_stock', '>', 0)
                ->whereDoesntHave('purchaseOrderItems.purchaseOrder', function ($query) {
                    $query->whereIn('status', ['pending', 'ordered']);
                });

            // --- DEBUG: ดึง SQL query ที่จะรันออกมาดู ---
            // เราจะแปลง Query Builder เป็น SQL string เพื่อดูหน้าตาของมันก่อนที่จะรันจริง
            $sqlQuery = $lowStockItemsQuery->toSql();
            $bindings = $lowStockItemsQuery->getBindings();
            Log::channel('daily')->debug('Generated SQL Query:', ['sql' => $sqlQuery, 'bindings' => $bindings]);

            // รัน Query จริง
            $lowStockItems = $lowStockItemsQuery->get();

            Log::channel('daily')->debug("Found {$lowStockItems->count()} low stock items.");

            if ($lowStockItems->isEmpty()) {
                Log::channel('daily')->debug('Result: No items found or already ordered.');
                Log::channel('daily')->debug('=============== AJAX: checkLowStock END ===============');
                return response()->json([
                    'success' => true,
                    'message' => 'ไม่พบอุปกรณ์ที่สต็อกต่ำ หรือรายการที่สต็อกต่ำได้ถูกสั่งซื้อไปแล้ว'
                ]);
            }

            Log::channel('daily')->debug('Result: Found items, rendering HTML.');
            $html = view('partials.modals._low_stock_list', compact('lowStockItems'))->render();
            Log::channel('daily')->debug('=============== AJAX: checkLowStock END ===============');
            return response()->json(['success' => true, 'html' => $html]);

        } catch (\Exception $e) {
            // --- DEBUG: บันทึก Error ที่เกิดขึ้นอย่างละเอียด ---
            Log::channel('daily')->error('!!! EXCEPTION in checkLowStock !!!');
            Log::channel('daily')->error('Error Message: ' . $e->getMessage());
            // บันทึก Stack Trace เพื่อให้รู้ว่า Error มาจากไฟล์ไหน บรรทัดไหน
            Log::channel('daily')->error('Stack Trace: ' . $e->getTraceAsString());
            Log::channel('daily')->debug('=============== AJAX: checkLowStock END (WITH ERROR) ===============');

            // ส่งข้อความ Error กลับไปให้ละเอียดขึ้น
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดรุนแรง: ' . $e->getMessage(),
                'debug_info' => 'โปรดตรวจสอบไฟล์ log ล่าสุดใน storage/logs/'
            ], 500);
        }
    }


    // ==================================================================
    // ========== โค้ดส่วนที่เหลือของไฟล์ (จากไฟล์ที่คุณอัปโหลด) ==============
    // ==================================================================

    private function addEquipment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'quantity'    => 'required|integer|min:0',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            $data = $request->except(['action', 'image', '_token']);

            $data['price'] = $request->input('price');
            if (empty($data['price'])) {
                $data['price'] = 0.00;
            }

            // status calc
            $quantity  = (int)$request->input('quantity', 0);
            $min_stock = (int)$request->input('min_stock', 1);

            if ($quantity <= 0) {
                $data['status'] = 'out-of-stock';
            } elseif ($quantity <= $min_stock) {
                $data['status'] = 'low_stock';
            } else {
                $data['status'] = 'available';
            }

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads'), $fileName);
                $data['image'] = $fileName;
            }

            DB::table('equipments')->insert($data);

            return response()->json(['success' => true, 'message' => 'เพิ่มอุปกรณ์เรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        }
    }

    private function updateEquipment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'equipment_id' => 'required|exists:equipments,id',
            'name'         => 'required|string|max:255',
            'image'        => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            $id = $request->input('equipment_id');
            $data = $request->except(['action', 'image', '_token', 'equipment_id']);

            $data['price'] = $request->input('price');
            if (empty($data['price'])) {
                $data['price'] = 0.00;
            }

            // status re-calc (preserve special)
            $oldEquipment = DB::table('equipments')->where('id', $id)->first();
            $quantity  = (int)$request->input('quantity', 0);
            $min_stock = (int)$request->input('min_stock', 1);

            if ($oldEquipment && in_array($oldEquipment->status, ['on-order', 'maintenance'])) {
                $data['status'] = $oldEquipment->status;
            } else {
                if ($quantity <= 0) {
                    $data['status'] = 'out-of-stock';
                } elseif ($quantity <= $min_stock) {
                    $data['status'] = 'low_stock';
                } else {
                    $data['status'] = 'available';
                }
            }

            if ($request->hasFile('image')) {
                $oldImage = DB::table('equipments')->where('id', $id)->value('image');
                if ($oldImage && File::exists(public_path('uploads/' . $oldImage))) {
                    File::delete(public_path('uploads/' . $oldImage));
                }

                $file = $request->file('image');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads'), $fileName);
                $data['image'] = $fileName;
            }

            DB::table('equipments')->where('id', $id)->update($data);

            return response()->json(['success' => true, 'message' => 'อัปเดตข้อมูลเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        }
    }

    private function deleteEquipment(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['success' => false, 'message' => 'จำเป็นต้องมี ID']);
        }

        try {
            $image = DB::table('equipments')->where('id', $id)->value('image');
            if ($image && File::exists(public_path('uploads/' . $image))) {
                File::delete(public_path('uploads/' . $image));
            }

            DB::table('equipments')->where('id', $id)->delete();

            return response()->json(['success' => true, 'message' => 'ลบอุปกรณ์เรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        }
    }

    private function storeWithdrawal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'requestor_name' => 'required|string|max:255',
            'purpose'        => 'required|string',
            'items'          => 'required|json',
            'type'           => ['required', Rule::in(['withdraw', 'borrow'])]
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $items = json_decode($request->input('items'), true);
        if (empty($items)) {
            return response()->json(['success' => false, 'message' => 'ไม่มีรายการอุปกรณ์'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $equipment = Equipment::lockForUpdate()->find($item['id']);

                if (!$equipment || $equipment->quantity < $item['quantity']) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => "สต็อกของ {$equipment->name} ไม่เพียงพอ"], 422);
                }

                $equipment->decrement('quantity', $item['quantity']);

                Transaction::create([
                    'equipment_id'    => $item['id'],
                    'user_id'         => Auth::id() ?? 1,
                    'type'            => $request->input('type'),
                    'quantity_change' => -$item['quantity'],
                    'notes'           => "ผู้ขอ: {$request->input('requestor_name')}\nวัตถุประสงค์: {$request->input('purpose')}",
                    'transaction_date'  => now(),
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'บันทึกรายการเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }

    private function mapSetting(string $type): array
    {
        $map = [
            'category' => [
                'table'      => 'categories',
                'fields'     => ['name', 'prefix'],
                'unique'     => 'name',
                'fk_column'  => 'category_id',
                'label'      => 'ประเภท',
            ],
            'location' => [
                'table'      => 'locations',
                'fields'     => ['name'],
                'unique'     => 'name',
                'fk_column'  => 'location_id',
                'label'      => 'สถานที่',
            ],
            'unit' => [
                'table'      => 'units',
                'fields'     => ['name'],
                'unique'     => 'name',
                'fk_column'  => 'unit_id',
                'label'      => 'หน่วยนับ',
            ],
        ];

        if (!isset($map[$type])) {
            throw new \InvalidArgumentException('Invalid setting type');
        }
        return $map[$type];
    }

    private function getSettingDetailsType(Request $request, string $type)
    {
        $meta = $this->mapSetting($type);
        $id = (int) $request->input('id');
        if (!$id) {
            return response()->json(['success' => false, 'message' => 'จำเป็นต้องมี ID']);
        }

        $item = DB::table($meta['table'])->where('id', $id)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูล']);
        }
        return response()->json(['success' => true, 'data' => $item]);
    }

    private function createSettingType(Request $request, string $type)
    {
        $meta = $this->mapSetting($type);

        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique($meta['table'], 'name')],
        ];
        if ($type === 'category') {
            $rules['prefix'] = ['nullable', 'string', 'max:20'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $insert = [
            'name'       => $request->input('name'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($type === 'category') {
            $insert['prefix'] = $request->input('prefix');
        }

        DB::table($meta['table'])->insert($insert);

        return response()->json(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ']);
    }

    private function updateSettingType(Request $request, string $type)
    {
        $meta = $this->mapSetting($type);
        $id = (int) $request->input('id');
        if (!$id) {
            return response()->json(['success' => false, 'message' => 'จำเป็นต้องมี ID']);
        }

        $exists = DB::table($meta['table'])->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูลที่จะอัปเดต']);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique($meta['table'], 'name')->ignore($id)],
        ];
        if ($type === 'category') {
            $rules['prefix'] = ['nullable', 'string', 'max:20'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $update = [
            'name'       => $request->input('name'),
            'updated_at' => now(),
        ];
        if ($type === 'category') {
            $update['prefix'] = $request->input('prefix');
        }

        DB::table($meta['table'])->where('id', $id)->update($update);

        return response()->json(['success' => true, 'message' => 'อัปเดตข้อมูลสำเร็จ']);
    }

    private function deleteSettingType(Request $request, string $type)
    {
        $meta = $this->mapSetting($type);
        $id = (int) $request->input('id');
        if (!$id) {
            return response()->json(['success' => false, 'message' => 'จำเป็นต้องมี ID']);
        }

        $item = DB::table($meta['table'])->where('id', $id)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูลที่จะลบ']);
        }

        // ป้องกันลบเมื่อถูกใช้งานในอุปกรณ์
        $inUse = DB::table('equipments')->where($meta['fk_column'], $id)->exists();
        if ($inUse) {
            return response()->json(['success' => false, 'message' => 'ไม่สามารถลบได้ เนื่องจากมีข้อมูลอุปกรณ์ผูกอยู่']);
        }

        DB::table($meta['table'])->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    }

    private function getNextSerialNumber(Request $request)
    {
        $categoryId = $request->input('category_id');
        if (!$categoryId) {
            return response()->json(['success' => false, 'message' => 'จำเป็นต้องมี Category ID']);
        }

        try {
            $category = DB::table('categories')->where('id', $categoryId)->first();
            if (!$category || !$category->prefix) {
                return response()->json(['success' => false, 'message' => 'ไม่พบคำนำหน้า (Prefix) สำหรับประเภทนี้ในฐานข้อมูล']);
            }

            $prefix = $category->prefix . '-';

            $latestSerial = DB::table('equipments')
                ->where('serial_number', 'like', $prefix . '%')
                ->select(DB::raw('MAX(CAST(SUBSTRING(serial_number, ' . (strlen($prefix) + 1) . ') AS UNSIGNED)) as max_num'))
                ->first();

            $nextNumber = 1;
            if ($latestSerial && $latestSerial->max_num) {
                $nextNumber = $latestSerial->max_num + 1;
            }

            $newSerialNumber = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            return response()->json(['success' => true, 'serial_number' => $newSerialNumber]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getEquipmentDetails(Request $request)
    {
        $equipmentId = $request->input('id');
        if (!$equipmentId) {
            return response()->json(['success' => false, 'message' => 'Equipment ID is required.']);
        }

        try {
            $equipment = DB::table('equipments as e')
                ->leftJoin('categories as c', 'e.category_id', '=', 'c.id')
                ->leftJoin('locations as l', 'e.location_id', '=', 'l.id')
                ->leftJoin('units as u', 'e.unit_id', '=', 'u.id')
                ->where('e.id', $equipmentId)
                ->select('e.*', 'c.name as category_name', 'l.name as location_name', 'u.name as unit_name')
                ->first();

            if ($equipment) {
                if ($equipment->image) {
                    $equipment->image = asset('uploads/'. $equipment->image);
                }
                return response()->json(['success' => true, 'equipment' => $equipment]);
            } else {
                return response()->json(['success' => false, 'message' => 'ไม่พบอุปกรณ์']);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getDashboardData()
    {
        try {
            $equipmentByCategory = DB::table('equipments')
                ->join('categories', 'equipments.category_id', '=', 'categories.id')
                ->select('categories.name as category_name', DB::raw('COUNT(equipments.id) as total'))
                ->groupBy('categories.name')
                ->get();

            $equipmentByStatus = [
                'available' => DB::table('equipments')->where('status', 'available')->count(),
                'low_stock' => DB::table('equipments')->where('status', 'low_stock')->count(),
                'on_order'  => DB::table('equipments')->where('status', 'on_order')->count(),
            ];

            return response()->json([
                'success' => true,
                'equipmentByCategory' => $equipmentByCategory,
                'equipmentByStatus'   => $equipmentByStatus,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // --- ✅✅✅ START: โค้ดที่แก้ไข (สำหรับหน้า Settings) ✅✅✅
    public function getLdapUsers(Request $request)
    {
        try {
            // ดึงค่า settingKey จาก request
            $settingKey = $request->query('settingKey');
            $currentSetting = null;

            // ถ้ามี settingKey, ดึงค่าปัจจุบัน
            if ($settingKey) {
                $currentSetting = Setting::where('key', $settingKey)->first();
            }

            // ดึงรายชื่อผู้ใช้ทั้งหมด
            // ✅✅✅ 1. แก้ไข: เปลี่ยน 'email' เป็น 'employeecode'
            $users = LdapUser::select('id', 'username', 'fullname', 'employeecode')
                            ->whereNotNull('fullname')
                            ->where('fullname', '!=', '')
                            ->orderBy('fullname', 'asc')
                            ->get();

            return response()->json([
                'users' => $users,
                'current_requester_id' => $currentSetting ? $currentSetting->value : null
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getLdapUsers (Settings): ' . $e->getMessage());
            return response()->json(['message' => 'เกิดข้อผิดพลาดในการโหลดรายชื่อผู้ใช้: ' . $e->getMessage()], 500);
        }
    }
    // --- ✅✅✅ END: โค้ดที่แก้ไข (สำหรับหน้า Settings) ✅✅✅

    // --- ✅ Method searchItems จากไฟล์ที่แก้แล้ว ---
    private function searchItems(Request $request)
    {
        $queryTerm = $request->input('q');
        $stockStatus = $request->input('stock_status', 'in_stock');

        $query = Equipment::query();

        if ($stockStatus === 'in_stock') {
            $query->where('quantity', '>', 0);
        } else {
            $query->where('quantity', '<=', 0);
        }

        if (!empty($queryTerm)) {
            $query->where(function ($q) use ($queryTerm) {
                $q->where('name', 'LIKE', "%{$queryTerm}%")
                  ->orWhere('serial_number', 'LIKE', "%{$queryTerm}%")
                  ->orWhere('part_no', 'LIKE', "%{$queryTerm}%");
            });
        }

        $items = $query->with(['unit', 'images']) // 'images' ถูกโหลดมาแล้ว
                        ->orderBy('name', 'asc')
                        ->paginate(10);

        // --- 🐞 BUG FIX: START ---
        // 1. ดึง Default Dept Key จาก Config (เพราะ View Partial '..._reorganized_item_list' ต้องการ)
        // เราใช้ Config::get() ซึ่งต้อง 'use Illuminate\Support\Facades\Config;' ด้านบน
        $defaultDeptKey = Config::get('department_stocks.default_nas_dept_key', 'it');

        // 2. ส่ง 'items' และ 'defaultDeptKey' ไปยัง View
        $itemsHtml = view('partials.modals._reorganized_item_list', [
            'items' => $items,
            'defaultDeptKey' => $defaultDeptKey
        ])->render();
        // --- 🐞 BUG FIX: END ---
        
        // --- โค้ดเดิมที่ทำให้เกิดบัค (KO) ---
        // $itemsHtml = view('partials.modals._reorganized_item_list', ['items' => $items])->render();
        
        $paginationHtml = $items->appends($request->except('page'))->links()->toHtml();

        return response()->json([
            'success'         => true,
            'items_html'      => $itemsHtml,
            'pagination_html' => $paginationHtml,
        ]);
    }

    // --- ✅ เพิ่ม getUserDetails กลับเข้ามา ---
    private function getUserDetails(Request $request) {
        // Implement logic if needed, otherwise return placeholder
        return response()->json(['success' => false, 'message' => 'getUserDetails not implemented yet.'], 501);
    }
     // --- ✅ เพิ่ม updateUserGroup กลับเข้ามา ---
    private function updateUserGroup(Request $request) {
        // Implement logic if needed, otherwise return placeholder
         return response()->json(['success' => false, 'message' => 'updateUserGroup not implemented yet.'], 501);
    }
     // --- ✅ เพิ่ม searchEquipmentForChart กลับเข้ามา ---
     private function searchEquipmentForChart(Request $request) {
         // Implement logic if needed, otherwise return placeholder
         return response()->json(['success' => false, 'message' => 'searchEquipmentForChart not implemented yet.'], 501);
    }

} // <-- ปิด Class AjaxController