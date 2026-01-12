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
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class AjaxController extends Controller
{
    /**
     * Handle all incoming AJAX requests from the frontend.
     */
    public function handleRequest(Request $request)
    {
        $action = $request->input('action');

        switch ($action) {
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
                return $this->searchItems($request);
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
            
            // ✅ ส่วนที่แก้ไข: ใช้ฟังก์ชันใหม่สำหรับค้นหาชื่อ (Select2)
            case 'get_ldap_users':
                return $this->getLdapUsersForSelect2($request);

            // ✅ คืนค่าฟังก์ชันที่เคยหายไป
            case 'get_user_details':
                return $this->getUserDetails($request);
            case 'update_user_group':
                return $this->updateUserGroup($request);
            case 'search_equipment_for_chart':
                return $this->searchEquipmentForChart($request);

            case 'get_notifications':
                return $this->getNotifications();
            case 'mark_notifications_read':
                return $this->markNotificationsRead();
            case 'clear_notifications':
                return $this->clearNotifications();
            case 'get_popular_items':
                return $this->getPopularItems();

            default:
                return response()->json(['success' => false, 'message' => 'Invalid action specified.']);
        }
    }

    // =========================================================================
    // 🌟 [NEW] ค้นหาพนักงาน (LDAP) สำหรับหน้าเบิก/ตะกร้า
    // =========================================================================
    private function getLdapUsersForSelect2(Request $request)
    {
        $term = $request->input('q');
        
        try {
            // ใช้ Model LdapUser (ปลอดภัยและถูกต้องกว่า DB::connection)
            $query = LdapUser::select('id', 'fullname', 'username', 'employeecode')
                ->whereNotNull('fullname')
                ->where('fullname', '!=', '');

            if ($term) {
                // 🔍 กรณีพิมพ์ค้นหา: ค้นจาก ชื่อ, รหัสพนักงาน, หรือ Username
                $query->where(function($q) use ($term) {
                    $q->where('fullname', 'like', "%{$term}%")
                      ->orWhere('employeecode', 'like', "%{$term}%")
                      ->orWhere('username', 'like', "%{$term}%");
                });
                $query->limit(30); // จำกัดผลลัพธ์
                
                $users = $query->orderBy('fullname', 'asc')->get();
                
                $results = $users->map(function($user) {
                    return $this->formatUserForResponse($user);
                });

                return response()->json(['items' => $results]);

            } else {
                // ⭐ กรณีไม่ได้พิมพ์: ดึงคนที่เบิกบ่อยที่สุด (Top 10)
                // ใช้ Transaction Model เพื่อดึงข้อมูลประวัติการเบิก
                $topUserIds = Transaction::select('user_id', DB::raw('count(*) as total'))
                    ->whereNotNull('user_id')
                    ->groupBy('user_id')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->pluck('user_id')
                    ->toArray();

                if (!empty($topUserIds)) {
                    // ดึงรายละเอียด User จาก LdapUser Model
                    $users = LdapUser::whereIn('id', $topUserIds)
                        ->select('id', 'fullname', 'username', 'employeecode')
                        ->get();
                        
                    // เรียงลำดับตามความถี่
                    $users = $users->sortBy(function($user) use ($topUserIds) {
                        return array_search($user->id, $topUserIds);
                    });

                    $formattedUsers = $users->map(function($user) {
                        return $this->formatUserForResponse($user);
                    })->values();

                    return response()->json(['items' => [
                        [
                            'text' => '🔥 คนที่เบิกบ่อย',
                            'children' => $formattedUsers
                        ]
                    ]]);
                } else {
                    return response()->json(['items' => []]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Error in getLdapUsersForSelect2: ' . $e->getMessage());
            return response()->json(['items' => [], 'error' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    private function formatUserForResponse($user)
    {
        $text = $user->fullname;
        if (!empty($user->employeecode)) {
            $text .= " ({$user->employeecode})";
        }
        return [
            'id' => $user->id,
            'text' => $text
        ];
    }

    // =========================================================================
    // 🔄 [RESTORED] ฟังก์ชันเดิมที่ถูกกู้คืนกลับมา
    // =========================================================================

    private function getUserDetails(Request $request)
    {
        $id = $request->input('id');
        if (!$id) return response()->json(['success' => false, 'message' => 'User ID is required']);

        // ใช้ Model User
        $user = User::with('userGroup')->find($id);
        if (!$user) return response()->json(['success' => false, 'message' => 'User not found']);

        return response()->json(['success' => true, 'user' => $user]);
    }

    private function updateUserGroup(Request $request)
    {
        $userId = $request->input('user_id');
        $groupId = $request->input('group_id');

        $user = User::find($userId);
        if (!$user) return response()->json(['success' => false, 'message' => 'User not found']);

        $user->user_group_id = $groupId;
        $user->save();

        return response()->json(['success' => true, 'message' => 'User group updated successfully']);
    }

    private function searchEquipmentForChart(Request $request)
    {
        $term = $request->input('term');
        $equipments = Equipment::where('name', 'like', "%{$term}%")
            ->select('id', 'name')
            ->limit(10)
            ->get();

        $results = $equipments->map(function ($item) {
            return ['id' => $item->id, 'text' => $item->name];
        });

        return response()->json(['results' => $results]);
    }

    // =========================================================================
    // 🔽 ฟังก์ชันเดิมที่ไม่มีการแก้ไข (Equipment, Settings, etc.) 🔽
    // =========================================================================

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
            $data['price'] = $request->input('price') ?? 0.00;

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
            $data['price'] = $request->input('price') ?? 0.00;

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
        if (!$id) return response()->json(['success' => false, 'message' => 'จำเป็นต้องมี ID']);

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
        if (empty($items)) return response()->json(['success' => false, 'message' => 'ไม่มีรายการอุปกรณ์'], 422);

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
            'category' => ['table' => 'categories', 'fields' => ['name', 'prefix'], 'unique' => 'name', 'fk_column' => 'category_id', 'label' => 'ประเภท'],
            'location' => ['table' => 'locations', 'fields' => ['name'], 'unique' => 'name', 'fk_column' => 'location_id', 'label' => 'สถานที่'],
            'unit' => ['table' => 'units', 'fields' => ['name'], 'unique' => 'name', 'fk_column' => 'unit_id', 'label' => 'หน่วยนับ'],
        ];
        if (!isset($map[$type])) throw new \InvalidArgumentException('Invalid setting type');
        return $map[$type];
    }

    private function getSettingDetailsType(Request $request, string $type)
    {
        $meta = $this->mapSetting($type);
        $id = (int) $request->input('id');
        if (!$id) return response()->json(['success' => false, 'message' => 'จำเป็นต้องมี ID']);

        $item = DB::table($meta['table'])->where('id', $id)->first();
        if (!$item) return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูล']);
        return response()->json(['success' => true, 'data' => $item]);
    }

    private function createSettingType(Request $request, string $type)
    {
        $meta = $this->mapSetting($type);
        $rules = ['name' => ['required', 'string', 'max:255', Rule::unique($meta['table'], 'name')]];
        if ($type === 'category') $rules['prefix'] = ['nullable', 'string', 'max:20'];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) return response()->json(['success' => false, 'message' => $validator->errors()->first()]);

        $insert = ['name' => $request->input('name'), 'created_at' => now(), 'updated_at' => now()];
        if ($type === 'category') $insert['prefix'] = $request->input('prefix');

        DB::table($meta['table'])->insert($insert);
        return response()->json(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ']);
    }

    private function updateSettingType(Request $request, string $type)
    {
        $meta = $this->mapSetting($type);
        $id = (int) $request->input('id');
        if (!$id) return response()->json(['success' => false, 'message' => 'จำเป็นต้องมี ID']);

        $exists = DB::table($meta['table'])->where('id', $id)->exists();
        if (!$exists) return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูลที่จะอัปเดต']);

        $rules = ['name' => ['required', 'string', 'max:255', Rule::unique($meta['table'], 'name')->ignore($id)]];
        if ($type === 'category') $rules['prefix'] = ['nullable', 'string', 'max:20'];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) return response()->json(['success' => false, 'message' => $validator->errors()->first()]);

        $update = ['name' => $request->input('name'), 'updated_at' => now()];
        if ($type === 'category') $update['prefix'] = $request->input('prefix');

        DB::table($meta['table'])->where('id', $id)->update($update);
        return response()->json(['success' => true, 'message' => 'อัปเดตข้อมูลสำเร็จ']);
    }

    private function deleteSettingType(Request $request, string $type)
    {
        $meta = $this->mapSetting($type);
        $id = (int) $request->input('id');
        if (!$id) return response()->json(['success' => false, 'message' => 'จำเป็นต้องมี ID']);

        $item = DB::table($meta['table'])->where('id', $id)->first();
        if (!$item) return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูลที่จะลบ']);

        $inUse = DB::table('equipments')->where($meta['fk_column'], $id)->exists();
        if ($inUse) return response()->json(['success' => false, 'message' => 'ไม่สามารถลบได้ เนื่องจากมีข้อมูลอุปกรณ์ผูกอยู่']);

        DB::table($meta['table'])->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    }

    private function getNextSerialNumber(Request $request)
    {
        $categoryId = $request->input('category_id');
        if (!$categoryId) return response()->json(['success' => false, 'message' => 'จำเป็นต้องมี Category ID']);

        try {
            $category = DB::table('categories')->where('id', $categoryId)->first();
            if (!$category || !$category->prefix) return response()->json(['success' => false, 'message' => 'ไม่พบคำนำหน้า (Prefix) สำหรับประเภทนี้ในฐานข้อมูล']);

            $prefix = $category->prefix . '-';
            $latestSerial = DB::table('equipments')
                ->where('serial_number', 'like', $prefix . '%')
                ->select(DB::raw('MAX(CAST(SUBSTRING(serial_number, ' . (strlen($prefix) + 1) . ') AS UNSIGNED)) as max_num'))
                ->first();

            $nextNumber = ($latestSerial && $latestSerial->max_num) ? $latestSerial->max_num + 1 : 1;
            $newSerialNumber = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            return response()->json(['success' => true, 'serial_number' => $newSerialNumber]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getEquipmentDetails(Request $request)
    {
        $equipmentId = $request->input('id');
        if (!$equipmentId) return response()->json(['success' => false, 'message' => 'Equipment ID is required.']);

        try {
            $equipment = DB::table('equipments as e')
                ->leftJoin('categories as c', 'e.category_id', '=', 'c.id')
                ->leftJoin('locations as l', 'e.location_id', '=', 'l.id')
                ->leftJoin('units as u', 'e.unit_id', '=', 'u.id')
                ->where('e.id', $equipmentId)
                ->select('e.*', 'c.name as category_name', 'l.name as location_name', 'u.name as unit_name')
                ->first();

            if ($equipment) {
                if ($equipment->image) $equipment->image = asset('uploads/'. $equipment->image);
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

    private function checkLowStock()
    {
        try {
            $lowStockItemsQuery = Equipment::whereColumn('quantity', '<=', 'minimum_stock')
                ->where('minimum_stock', '>', 0)
                ->whereDoesntHave('purchaseOrderItems.purchaseOrder', function ($query) {
                    $query->whereIn('status', ['pending', 'ordered']);
                });

            $lowStockItems = $lowStockItemsQuery->get();

            if ($lowStockItems->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'ไม่พบอุปกรณ์ที่สต็อกต่ำ หรือรายการที่สต็อกต่ำได้ถูกสั่งซื้อไปแล้ว'
                ]);
            }

            $html = view('partials.modals._low_stock_list', compact('lowStockItems'))->render();
            return response()->json(['success' => true, 'html' => $html]);

        } catch (\Exception $e) {
            Log::error('Exception in checkLowStock: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }

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

        $items = $query->with(['unit', 'images'])
                       ->orderBy('name', 'asc')
                       ->paginate(10);

        // ใช้ Config เพื่อดึงค่า default dept key
        $defaultDeptKey = Config::get('department_stocks.default_nas_dept_key', 'mm');

        $itemsHtml = view('partials.modals._reorganized_item_list', [
            'items' => $items,
            'defaultDeptKey' => $defaultDeptKey
        ])->render();
        
        $paginationHtml = $items->appends($request->except('page'))->links()->toHtml();

        return response()->json([
            'success'         => true,
            'items_html'      => $itemsHtml,
            'pagination_html' => $paginationHtml,
        ]);
    }

    // --- ฟังก์ชันอื่นๆ ที่ใช้โดยหน้า Setting (คงเดิม) ---
    public function getLdapUsers(Request $request)
    {
        try {
            $settingKey = $request->query('settingKey');
            $currentSetting = null;
            if ($settingKey) {
                $currentSetting = Setting::where('key', $settingKey)->first();
            }
            // ใช้ Model LdapUser
            $users = LdapUser::select('id', 'username', 'fullname', 'employeecode')
                ->whereNotNull('fullname')->where('fullname', '!=', '')
                ->orderBy('fullname', 'asc')->get();

            return response()->json([
                'users' => $users,
                'current_requester_id' => $currentSetting ? $currentSetting->value : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }



    private function getNotifications()
    {
        try {
            $user = Auth::user();
            // ✅ Use local user_meta table instead of touching sync_ldap
            $meta = DB::table('user_meta')->where('user_id', $user->id)->first();
            $lastCheck = $meta ? $meta->last_notification_check : null;
            $lastCleared = $meta ? $meta->last_cleared_at : null;
            
            $notifications = [];
            $unreadCount = 0;
            
            // 1. Low Stock Notifications
            $lowStockQuery = Equipment::where(function($q) {
                    $q->where('status', 'low_stock')
                      ->orWhere(function($sub) {
                          $sub->whereColumn('quantity', '<=', 'min_stock')->where('min_stock', '>', 0);
                      });
                })
                ->limit(10); // Check more items since we might filter some out
            
            // Filter by cleared time if exists
            if ($lastCleared) {
                // Only show items updated AFTER the clear time
                $lowStockQuery->where('updated_at', '>', $lastCleared);
            }
            
            $lowStockItems = $lowStockQuery->get();

            foreach ($lowStockItems as $item) {
                // If item updated AFTER last check, it's unread.
                $isUnread = !$lastCheck || ($item->updated_at && $item->updated_at->gt($lastCheck));
                if ($isUnread) $unreadCount++;

                if ($item->quantity <= 0) {
                    $type = 'out_of_stock';
                    $message = "สินค้าหมด: {$item->name}";
                } else {
                    $type = 'low_stock';
                    $message = "สินค้าใกล้หมด: {$item->name} (เหลือ {$item->quantity})";
                }

                $notifications[] = [
                    'id' => $item->id,
                    'type' => $type,
                    'message' => $message,
                    'url' => route('equipment.index', ['search' => $item->name]),
                    'is_read' => !$isUnread
                ];
            }

            // 2. Pending Approval Notifications (For Approvers)
            if ($user && $user->can('transaction:approve')) {
                $pendingQuery = Transaction::where('status', 'pending_approval');
                
                if ($lastCleared) {
                    $pendingQuery->where('created_at', '>', $lastCleared);
                }
                
                $pendingTxs = $pendingQuery->get();
                $pendingCount = $pendingTxs->count();
                
                if ($pendingCount > 0) {
                     // Check if ANY pending tx is newer than last check
                     $latestPending = $pendingTxs->max('created_at');
                     $isUnread = !$lastCheck || ($latestPending && $latestPending->gt($lastCheck));
                     if ($isUnread) $unreadCount++;

                     $notifications[] = [
                        'id' => 'pending-tx',
                        'type' => 'pending_approval',
                        'message' => "มีรายการรออนุมัติ {$pendingCount} รายการ",
                        'url' => route('transactions.index', ['status' => 'pending_approval']),
                        'is_read' => !$isUnread
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'count' => $unreadCount, // Badge shows unread only
                'total' => count($notifications),
                'notifications' => $notifications
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function markNotificationsRead()
    {
        try {
            $user = Auth::user();
            if ($user) {
                // ✅ Update local user_meta table
                DB::table('user_meta')->updateOrInsert(
                    ['user_id' => $user->id],
                    ['last_notification_check' => now(), 'updated_at' => now()]
                );
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }


    private function clearNotifications()
    {
        try {
            $user = Auth::user();
            if ($user) {
                 DB::table('user_meta')->updateOrInsert(
                    ['user_id' => $user->id],
                    ['last_cleared_at' => now(), 'last_notification_check' => now(), 'updated_at' => now()]
                );
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    private function getPopularItems()
    {
        try {
            // Get top 5 most frequently withdrawn items
            $popular = Transaction::whereIn('type', ['withdraw', 'borrow', 'consumable']) // Include all usage types
                ->select('equipment_id', DB::raw('count(*) as total_usage'))
                ->groupBy('equipment_id')
                ->orderByDesc('total_usage')
                ->limit(5)
                ->with('equipment:id,name,unit_id') // Eager load equipment
                ->get();
            
            $results = $popular->map(function($tx) {
                 if(!$tx->equipment) return null;
                 return [
                     'name' => $tx->equipment->name,
                     'count' => $tx->total_usage
                 ];
            })->filter()->values();

            return response()->json(['success' => true, 'items' => $results]);
        } catch (\Exception $e) {
             return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}