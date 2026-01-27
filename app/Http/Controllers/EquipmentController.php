<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\EquipmentImage;
use App\Models\Location;
use App\Models\Unit;
use App\Models\Transaction;
use App\Models\GlpiTicket;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\SmbStorageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\PurchaseOrderItem;
use Carbon\Carbon; // ✅ ใช้ Carbon สำหรับคำนวณเวลา

class EquipmentController extends Controller
{
    use AuthorizesRequests;

    private SmbStorageService $smbService;
    private string $defaultDbName;
    private string $defaultConnection = 'mysql';
    private string $defaultDeptKey;

    public function __construct(SmbStorageService $smbService)
    {
        $this->smbService = $smbService;
        $this->defaultDbName = Config::get('database.connections.' . $this->defaultConnection . '.database');
        $this->defaultDeptKey = Config::get('department_stocks.default_key', 'mm');
    }

    // --- Database Switching Functions ---
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


    // --- index (Admin view) ---
    public function index(Request $request): View
    {
        $this->authorize('equipment:view');
        $this->switchToDefaultDb();

        if ($request->has('sort') && $request->has('direction')) {
            $sort = $request->get('sort');
            $direction = $request->get('direction');
            session(['equipment_sort_col' => $sort, 'equipment_sort_dir' => $direction]);
        } else {
            $sort = session('equipment_sort_col', 'name');
            $direction = session('equipment_sort_dir', 'asc');
        }
        $sortableColumns = ['name', 'serial_number', 'part_no', 'created_at', 'quantity', 'status'];
        if (!in_array($sort, $sortableColumns)) {
            $sort = 'name';
        }

        $query = Equipment::with(['category', 'location', 'unit', 'images'])
                            ->whereNotIn('status', ['sold', 'disposed']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('serial_number', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('part_no', 'LIKE', "%{$searchTerm}%");
            });
        }
        if ($request->filled('category')) $query->where('category_id', $request->category);
        if ($request->filled('location')) $query->where('location_id', $request->location);
        if ($request->filled('status')) $query->where('status', 'LIKE', "%{$request->status}%");


        $query->orderBy($sort, $direction);
        $equipments = $query->paginate(15)->withQueryString();

        $categories = Category::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();
        $equipment = new Equipment();

        $defaultDeptKey = $this->defaultDeptKey;

        return view('equipment.index', compact(
            'equipments',
            'categories',
            'locations',
            'units',
            'equipment',
            'sort',
            'direction',
            'defaultDeptKey'
        ));
    }


    // --- userIndex (User view) ---
    public function userIndex(Request $request)
    {
        $this->authorize('equipment:borrow');

        $departments = Config::get('department_stocks.departments', []);
        $currentDeptKey = $request->query('dept', $this->defaultDeptKey);
        $defaultDeptKey = $this->defaultDeptKey;
        $currentView = $request->input('view', 'catalog');

        $this->switchToDefaultDb();
        $categories = Category::orderBy('name')->get();
        $appName = config('app.name');

        $equipments = null;
        $aggregatedResults = null;
        $myEquipment = null;

        try {
            if ($currentView === 'catalog') {
                if ($request->filled('search')) {
                    $searchTerm = $request->search;
                    $aggregatedResults = [];

                    foreach ($departments as $key => $dept) {
                        $this->switchToDb($dept['db_name']);

                        $query = Equipment::with(['category', 'unit'])
                                            ->whereIn('status', ['available', 'low_stock'])
                                            ->where('quantity', '>', 0)
                                            ->where(function ($q) use ($searchTerm) {
                                                $q->where('name', 'LIKE', "%{$searchTerm}%")
                                                    ->orWhere('serial_number', 'LIKE', "%{$searchTerm}%");
                                            });
                        if ($request->filled('category')) {
                            $query->where('category_id', $request->category);
                        }
                        $results = $query->latest()->get();

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
                                $item->stock_sum_quantity; 
                            });

                            $aggregatedResults[] = [
                                'dept_key' => $key,
                                'dept_name' => $dept['name'],
                                'items' => $results,
                            ];
                        }
                    }

                } else {
                    $targetDbName = $departments[$currentDeptKey]['db_name'] ?? $this->defaultDbName;
                    $this->switchToDb($targetDbName);

                    $query = Equipment::with(['category', 'location', 'unit'])
                                            ->whereIn('status', ['available', 'low_stock'])
                                            ->where('quantity', '>', 0);
                    if ($request->filled('category')) {
                        $query->where('category_id', $request->category);
                    }
                    
                    $equipments = $query->orderBy('name', 'asc')->paginate(12)->withQueryString();

                    if ($equipments->isNotEmpty()) {
                        $equipmentIds = $equipments->pluck('id')->toArray();
                        $images = EquipmentImage::whereIn('equipment_id', $equipmentIds)
                                                ->select('equipment_id', 'file_name', 'is_primary')
                                                ->get()
                                                ->groupBy('equipment_id');

                        $equipments->each(function ($item) use ($images) {
                            $itemImages = $images->get($item->id);
                            $primaryImage = null;
                            if ($itemImages) {
                                $primaryImage = $itemImages->firstWhere('is_primary', true) ?? $itemImages->first();
                            }
                            $item->primary_image_file_name_manual = $primaryImage ? $primaryImage->file_name : null;
                        });
                    }
                }
            
            } elseif ($currentView === 'my_equipment') {
                $this->switchToDefaultDb();
                
                $myEquipmentQuery = Transaction::with([
                                                    'equipment' => function ($query) {
                                                        $query->select('id', 'name', 'unit_id'); 
                                                    }, 
                                                    'equipment.unit' => function ($query) {
                                                        $query->select('id', 'name');
                                                    }
                                                ])
                    ->where('user_id', Auth::id())
                    ->where(function ($query) {
                        $query->whereIn('type', ['borrow', 'returnable', 'partial_return', 'borrow_temporary'])
                              ->whereIn('status', ['completed', 'shipped']); 
                    })
                    ->orWhere(function ($query) {
                        $query->where('user_id', Auth::id())
                              ->where('status', 'shipped');
                    })
                    ->orderBy('transaction_date', 'desc');

                $myEquipment = $myEquipmentQuery->paginate(10, ['*'], 'page')->withQueryString();
            }

            $this->switchToDefaultDb();

            $allOpenTickets = collect(); 
            $showGlpiSection = false;

            if (config('database.connections.glpi_it') && class_exists(GlpiTicket::class)) {
                $showGlpiSection = true; 
                try {
                    $itTickets = GlpiTicket::on('glpi_it') 
                                            ->open()
                                            ->orderBy('id', 'desc')
                                            ->get(['id', 'name'])
                                            ->map(function ($ticket) {
                                                $ticket->source = 'it'; 
                                                return $ticket;
                                            });
                    $allOpenTickets = $allOpenTickets->merge($itTickets);
                } catch (\Exception $e) {
                    Log::error('Error fetching GLPI IT tickets: ' . $e->getMessage());
                }
            }

            if (config('database.connections.glpi_en') && class_exists(GlpiTicket::class)) {
                $showGlpiSection = true; 
                try {
                    $enTickets = GlpiTicket::on('glpi_en') 
                                            ->open()
                                            ->orderBy('id', 'desc')
                                            ->get(['id', 'name'])
                                            ->map(function ($ticket) {
                                                $ticket->source = 'en';
                                                return $ticket;
                                            });
                    $allOpenTickets = $allOpenTickets->merge($enTickets);
                } catch (\Exception $e) {
                    Log::error('Error fetching GLPI EN tickets: ' . $e->getMessage());
                }
            }
            $allOpenTickets = $allOpenTickets->sortByDesc('id');

            $unconfirmedCount = Transaction::where('user_id', Auth::id())->where('status', 'shipped')->count();
            
            // ✅ Fetch Custom Objectives
            $customObjectives = \App\Models\CustomObjective::where('is_active', true)->orderBy('created_at', 'desc')->get();


            return view('user.equipment.index', compact(
                'equipments', 'aggregatedResults', 'categories', 'unconfirmedCount',
                'allOpenTickets', 'showGlpiSection', 'departments', 'currentDeptKey',
                'defaultDeptKey', 'currentView', 'myEquipment', 'customObjectives'
            ));

        } catch (\Exception $e) {
            Log::error('Error in userIndex (EquipmentController): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->switchToDefaultDb();
            return redirect()->route('dashboard')->with('error', 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage());
        }
    }


    // --- create ---
    public function create()
    {
        $this->authorize('equipment:manage');
        $this->switchToDefaultDb();
        $categories = Category::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();
        $equipment = new Equipment();
        if (request()->ajax()) {
            return view('equipment.partials._form', compact('equipment', 'categories', 'locations', 'units'));
        }
        return view('equipment.create', compact('equipment', 'categories', 'locations', 'units'));
    }

    // --- show (AJAX for Details Modal) ---
    public function show(Equipment $equipment)
    {
        $this->authorize('equipment:view');
        
        $equipment->load(['category', 'location', 'unit', 'images', 'transactions' => function ($query) {
            $query->with('user')->latest('transaction_date')->limit(5);
        }]);

        $equipmentData = [
            'id' => $equipment->id,
            'name' => $equipment->name,
            'part_no' => $equipment->part_no,
            'model' => $equipment->model,
            'serial_number' => $equipment->serial_number,
            'quantity' => $equipment->quantity,
            'min_stock' => $equipment->min_stock,
            'max_stock' => $equipment->max_stock,
            'price' => $equipment->price,
            'supplier' => $equipment->supplier,
            'purchase_date' => $equipment->purchase_date ? $equipment->purchase_date->format('Y-m-d') : null,
            'warranty_date' => $equipment->warranty_date ? $equipment->warranty_date->format('Y-m-d') : null,
            'withdrawal_type' => $equipment->withdrawal_type,
            'notes' => $equipment->notes,
            'has_msds' => $equipment->has_msds,
            'msds_details' => $equipment->msds_details,
            'msds_file_url' => $equipment->msds_file_url,
            'status' => $equipment->status,
            'created_at' => $equipment->created_at ? $equipment->created_at->toDateTimeString() : null,
            'updated_at' => $equipment->updated_at ? $equipment->updated_at->toDateTimeString() : null,
            'category' => $equipment->category ? ['name' => $equipment->category->name] : null,
            'location' => $equipment->location ? ['name' => $equipment->location->name] : null,
            'unit' => $equipment->unit ? ['name' => $equipment->unit->name] : null,
            'transactions' => $equipment->transactions->map(function ($t) {
                return [
                    'id' => $t->id,
                    'type' => $t->type,
                    'quantity_change' => $t->quantity_change,
                    'transaction_date' => $t->transaction_date->format('Y-m-d H:i'),
                    'user' => $t->user ? ['fullname' => $t->user->fullname] : null,
                    'status' => $t->status,
                    'notes' => $t->notes,
                ];
            }),
            'image_urls' => [],
            'primary_image_url' => 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image',
        ];


        $primaryImageFound = false;
        foreach ($equipment->images as $image) {
            if (!empty($image->file_name)) {
                try {
                    $imageUrl = route('nas.image', [
                        'deptKey' => $this->defaultDeptKey, 
                        'filename' => $image->file_name
                    ]);
                    $equipmentData['image_urls'][] = $imageUrl; 

                    if ($image->is_primary || !$primaryImageFound) {
                        $equipmentData['primary_image_url'] = $imageUrl;
                        if ($image->is_primary) {
                            $primaryImageFound = true; 
                        }
                    }

                } catch (\Exception $e) {
                    Log::error("Error generating image URL for file '{$image->file_name}' in show(): " . $e->getMessage());
                    $equipmentData['image_urls'][] = 'https://placehold.co/100x100/e2e8f0/64748b?text=URL+Error';
                }
            }
        }
        return response()->json(['success' => true, 'data' => $equipmentData]);
    }


    // --- edit ---
    public function edit(Equipment $equipment)
    {
        $this->authorize('equipment:manage');
        $this->switchToDefaultDb();
        $categories = Category::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();
        $equipment->load('images');
        $defaultDeptKey = $this->defaultDeptKey;

        return view('equipment.partials._edit-form', compact(
            'equipment',
            'categories',
            'locations',
            'units',
            'defaultDeptKey' 
        ));
    }

    public function getEditForm(Equipment $equipment)
    {
        return $this->edit($equipment);
    }


    // --- getValidationRules ---
    private function getValidationRules($equipmentId = null)
    {
        // กำหนดค่า Placeholder SNs ที่อนุญาตให้ซ้ำได้ (เพราะไม่ใช่ Serial Number จริง)
        $placeholderSNs = ['N/A', 'NONE']; 

        return [
            'name'          => 'required|string|max:255',
            'part_no'       => 'nullable|string|max:100',
            'model_name'    => 'nullable|string|max:100',
            'model_number'  => 'nullable|string|max:100',
            'category_id'   => 'required|exists:categories,id', 
            // FIX: ปรับ Rule::unique ให้ไม่ตรวจสอบความซ้ำซ้อนสำหรับค่า Placeholder
            'serial_number' => [
                'nullable', 
                'string', 
                'max:100', 
                Rule::unique('equipments', 'serial_number')
                    ->ignore($equipmentId)
                    ->where(function ($query) use ($placeholderSNs) { 
                        // ตรวจสอบกับเรคคอร์ดที่ยังไม่ถูกลบ
                        $query->whereNull('deleted_at')
                              ->whereNotNull('serial_number');

                        // EXCLUDE: ละเว้นเรคคอร์ดที่มีค่า Serial Number เป็น Placeholder 
                        // เพื่ออนุญาตให้มี SN ซ้ำกันได้หลายรายการสำหรับค่าเหล่านี้ (เช่น N/A ที่มาจาก maintenance)
                        foreach ($placeholderSNs as $placeholder) {
                            $query->where('serial_number', '!=', $placeholder);
                        }
                        
                        return $query;
                    })
            ], 
            'location_id'   => 'required|exists:locations,id', 
            'unit_id'       => 'required|exists:units,id', 
            'quantity'      => 'required|integer|min:0',
            'min_stock'     => 'required|integer|min:0',
            'max_stock'     => 'required|integer|min:0|gte:min_stock',
            'notes'         => 'nullable|string',
            'supplier'      => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'warranty_date' => 'nullable|date|after_or_equal:purchase_date',
            'price'         => 'nullable|numeric|min:0',
            'images'        => 'nullable|array',
            'images.*'      => 'image|mimes:jpeg,png,jpg,gif,webp,heic,heif|max:10240',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'integer|exists:equipment_images,id', 
            'primary_image' => 'nullable|integer|exists:equipment_images,id', 
            'withdrawal_type' => ['required', Rule::in(['consumable', 'returnable', 'partial_return'])],
            'has_msds'      => 'nullable|boolean',
            'msds_file'       => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,png,txt|max:10240',
            'msds_details'    => 'nullable|string',
        ];
    }


    // --- store ---
    public function store(Request $request)
    {
        $this->authorize('equipment:manage');
        $this->switchToDefaultDb();
        $rules = $this->getValidationRules();
        if (Gate::denies('edit-equipment-quantity')) {
            $rules['quantity'] = 'sometimes|required|integer|min:0';
        }
        $requestData = $request->all();
        $requestData['has_msds'] = $request->has('has_msds');
        $purchaseOrderItemId = $request->input('purchase_order_item_id');
        $validator = Validator::make($requestData, $rules);

        if ($validator->fails()) {
            Log::error('[STORE] Validation Failed:', $validator->errors()->toArray());
            if ($request->wantsJson()) {
                return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validatedData = $validator->validated();
        $equipment = null; 

        // ✅ [FIX] คำนวณวันที่นับสต็อกล่าสุด (last_stock_check_at) เพื่อป้องกันไม่ให้ถูกแช่แข็ง (Frozen) ทันที
        // หลักการ: พยายาม "เกาะกลุ่ม" กับรอบการนับของหมวดหมู่นั้นๆ (Sync)
        $categoryId = $validatedData['category_id'] ?? null;
        $initialCheckDate = now(); // ค่า Default คือวันนี้ (เพราะเพิ่งรับของใหม่เข้ามา ถือว่านับแล้ว)
        
        if ($categoryId) {
            $lastCategoryCheck = \App\Models\StockCheck::where('status', 'completed')
                ->where(function($q) use ($categoryId) {
                     $q->where('category_id', $categoryId)
                       ->orWhereNull('category_id');
                })
                ->latest('completed_at')
                ->first();

            if ($lastCategoryCheck && $lastCategoryCheck->completed_at) {
                $daysDiff = $lastCategoryCheck->completed_at->diffInDays(now());
                if ($daysDiff < 105) {
                    $initialCheckDate = $lastCategoryCheck->completed_at;
                }
            }
        }
        $validatedData['last_stock_check_at'] = $initialCheckDate;

        // ✅ HANDLE RECEIVE PROCESS CREATION (prevent double stock)
        $isReceiveProcess = $request->input('is_receive_process') == '1';
        if ($isReceiveProcess) {
            $validatedData['quantity'] = 0; // Force 0 Initial Stock (Receive Process will add it)
        }

        DB::transaction(function () use ($validatedData, $request, &$equipment, $purchaseOrderItemId, $isReceiveProcess) { 
            $validatedData['model'] = trim(($validatedData['model_name'] ?? '') . ' ' . ($validatedData['model_number'] ?? ''));
            $validatedData['has_msds'] = $request->has('has_msds');
            $msdsData = $this->handleMsdsUpload($request);
            $validatedData = array_merge($validatedData, $msdsData); 
            $equipment = Equipment::create($validatedData);
            $this->handleImageUploads($request, $equipment);

            // Only create transaction if NOT from receive process (because Receive Process will create its own)
            if ($equipment->quantity > 0 && !$isReceiveProcess) {
                Transaction::create([
                    'equipment_id'    => $equipment->id,
                    'user_id'         => Auth::id(), 
                    'type'            => 'receive',
                    'quantity_change' => $equipment->quantity, 
                    'notes'           => 'เพิ่มอุปกรณ์ใหม่เข้าระบบ',
                    'transaction_date' => now(),
                    'status'          => 'completed', 
                    ]);
            }


            if ($purchaseOrderItemId) {
                $poItem = PurchaseOrderItem::find($purchaseOrderItemId);
                if ($poItem) {
                    $poItem->update([
                        'status' => 'received',
                        'quantity_received' => $equipment->quantity 
                    ]);
                }
            }
        });

        $message = 'เพิ่มอุปกรณ์ "' . ($equipment ? $equipment->name : 'N/A') . '" เรียบร้อยแล้ว';
        if ($purchaseOrderItemId) {
            $message .= ' และอัปเดตสถานะใบสั่งซื้อแล้ว';
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'equipment' => $equipment]);
        }
        return redirect()->route('equipment.index')->with('success', $message);
    }

    // ✅✅✅ UPDATE: Logic ย้ายหมวดหมู่แบบฉลาด (Smart Category Move) ✅✅✅
    public function update(Request $request, Equipment $equipment)
    {
        $this->authorize('equipment:manage');
        $this->switchToDefaultDb();

        // เก็บ Category เดิมไว้ตรวจสอบ
        $oldCategoryId = $equipment->category_id;
        
        $rules = $this->getValidationRules($equipment->id);
        if (Gate::denies('edit-equipment-quantity')) {
            $rules['quantity'] = 'sometimes|required|integer|min:0';
        }
        $requestData = $request->all();
        $hasMsdsFromRequest = $request->has('has_msds');
        $requestData['has_msds'] = $hasMsdsFromRequest;
        $validator = Validator::make($requestData, $rules);
        if ($validator->fails()) {
            Log::error('[UPDATE] Validation Failed for ID ' . $equipment->id . ':', $validator->errors()->toArray());
            if ($request->wantsJson()) {
                return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $validatedData = $validator->validated();
        $validatedData['has_msds'] = $hasMsdsFromRequest;
        $oldQuantity = $equipment->quantity;

        DB::transaction(function () use ($equipment, $validatedData, $request, $oldQuantity, $oldCategoryId) {
            $validatedData['model'] = trim(($validatedData['model_name'] ?? '') . ' ' . ($validatedData['model_number'] ?? ''));
            $msdsData = $this->handleMsdsUpload($request, $equipment);
            $validatedData = array_merge($validatedData, $msdsData);
            if (!$validatedData['has_msds']) {
                $validatedData['msds_details'] = null;
                $validatedData['msds_file_path'] = null;
            }
            
            // เตรียมข้อมูลอัปเดต
            $equipment->fill($validatedData);

            // 🔥 CHECK CHANGE: ถ้าย้ายหมวดหมู่
            if (isset($validatedData['category_id']) && $validatedData['category_id'] != $oldCategoryId) {
                
                // 1. ค้นหา "เพื่อนร่วมหมวดใหม่" ที่มีการนับสต็อกล่าสุด (และสถานะปกติดี)
                // เราจะเรียงจากวันที่นับล่าสุด เพื่อเอาวันที่ "สดใหม่" ที่สุด
                $referenceItem = Equipment::where('category_id', $validatedData['category_id'])
                    ->whereNotNull('last_stock_check_at') // ต้องเคยนับแล้ว
                    ->whereNotIn('status', ['frozen', 'sold', 'disposed']) // สถานะต้องปกติ
                    ->orderBy('last_stock_check_at', 'desc')
                    ->first();

                $shouldFreeze = true; // ตั้งสมมติฐานว่า "โดนแช่แข็ง" ไว้ก่อน

                if ($referenceItem) {
                    $limitDays = 105;
                    $daysDiff = Carbon::parse($referenceItem->last_stock_check_at)->diffInDays(now());

                    // 2. ถ้าเพื่อนเพิ่งนับไปไม่นาน (ไม่เกิน 105 วัน) -> เรา "รอด" ด้วย
                    if ($daysDiff < $limitDays) {
                        $shouldFreeze = false;
                        
                        // ✅ สวมรอยใช้วันที่เดียวกับเพื่อน
                        $equipment->last_stock_check_at = $referenceItem->last_stock_check_at;
                        
                        // คำนวณสถานะตามจำนวน (ไม่ Frozen)
                        if ($equipment->quantity <= 0) {
                            $equipment->status = 'out_of_stock';
                        } elseif ($equipment->min_stock > 0 && $equipment->quantity <= $equipment->min_stock) {
                            $equipment->status = 'low_stock';
                        } else {
                            $equipment->status = 'available';
                        }

                        Log::info("Equipment ID {$equipment->id} moved to Cat {$validatedData['category_id']}. Inherited valid check date: {$referenceItem->last_stock_check_at}");
                    }
                }

                if ($shouldFreeze) {
                    // ❌ ไม่มีเพื่อน หรือเพื่อนหมดอายุ -> ต้องโดนแช่แข็ง (เพื่อบังคับให้นับ)
                    $equipment->last_stock_check_at = null; 
                    $equipment->status = 'frozen'; 
                    Log::info("Equipment ID {$equipment->id} moved to Cat {$validatedData['category_id']}. No valid reference found. Forced FROZEN.");
                }
            }
            
            $equipment->save();
            $this->handleImageUpdates($request, $equipment);
            
            $newQuantity = $validatedData['quantity'] ?? $oldQuantity;
            $quantityChange = $newQuantity - $oldQuantity;
            if ($quantityChange != 0 && Gate::allows('edit-equipment-quantity')) {
                Transaction::create([
                    'equipment_id'    => $equipment->id,
                    'user_id'         => Auth::id(),
                    'type'            => 'adjust',
                    'quantity_change' => $quantityChange,
                    'notes'           => 'ปรับสต็อกจากการแก้ไขข้อมูล (จาก ' . $oldQuantity . ' เป็น ' . $newQuantity . ')',
                    'transaction_date'=> now(),
                    'status'          => 'completed',
                ]);
            }
        });
        
        $message = 'แก้ไขข้อมูล "' . $equipment->name . '" เรียบร้อยแล้ว (หากมีการย้ายหมวดหมู่ อุปกรณ์จะถูกระงับการใช้งานจนกว่าจะมีการนับสต็อกใหม่)';
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }
        return redirect()->route('equipment.index')->with('success', $message);
    }

    public function destroy(Equipment $equipment)
    {
        $this->authorize('equipment:manage');
        $this->switchToDefaultDb();
        $superAdminId = (int)config('app.super_admin_id');
        
        $userGroupSlug = Auth::user()->serviceUserRole?->userGroup?->slug;
        $slugLower = $userGroupSlug ? strtolower(str_replace(' ', '', $userGroupSlug)) : '';
        $isIT = in_array($slugLower, ['it', 'admin', 'administrator', 'itsupport', 'it-support']);
        $isSuperAdmin = (Auth::id() === $superAdminId);

        // ✅ POLICY:
        // 1. Transactions Exist -> Only SuperAdmin/IT can delete (Force Delete). Others are BLOCKED.
        // 2. No Transactions -> Anyone with 'equipment:manage' can delete.
        if ($equipment->transactions()->exists() && !$isSuperAdmin && !$isIT) {
             $message = 'ไม่สามารถลบได้ เนื่องจากมีประวัติการทำธุรกรรม (สงวนสิทธิ์เฉพาะ ID 9 และกลุ่ม IT เท่านั้น)';
             if (request()->wantsJson()) { return response()->json(['success' => false, 'message' => $message], 403); }
             return back()->with('error', $message);
        }

        // Note: Transactions check is no longer needed to block deletion IF we allow IT/ID9 to force delete anyway.
        // But if there is any other constraint, we might keep it.
        // User said: "ID9 / IT will be able to delete... by not caring if it's attached".
        // So we proceed to allow deletion for these users.
        
        // However, if we wanted to prevent ACCIDENTAL deletion of populated items by IT, we might want a confirmation?
        // But the user said "Can delete immediately by not caring". So we allow it.

        DB::transaction(function () use ($equipment) {
            $equipment->loadMissing('images');
            foreach ($equipment->images as $image) {
                try {
                    $this->smbService->resetShare()->delete($image->file_name);
                }
                catch (\Exception $e) { Log::warning('SMB Delete failed during destroy(): ' . $e->getMessage(), ['filename' => $image->file_name]); }
            }
            if ($equipment->msds_file_path) {
                try { Storage::disk('public')->delete($equipment->msds_file_path); }
                catch (\Exception $e) { Log::warning('MSDS file deletion failed during destroy(): ' . $e->getMessage(), ['path' => $equipment->msds_file_path]); }
            }
            $equipment->delete();
        });
        $message = 'ลบข้อมูลอุปกรณ์และไฟล์ที่เกี่ยวข้องสำเร็จแล้ว!';
        if (request()->wantsJson()) { return response()->json(['success' => true, 'message' => $message, 'redirect' => route('equipment.index')]); }
        return redirect()->route('equipment.index')->with('success', $message);
    }


    // --- handleImageUploads, handleImageUpdates ---
    private function handleImageUploads(Request $request, Equipment $equipment)
    {
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $key => $file) {
                $imageName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                try {
                    $this->smbService->resetShare()->put($file, $imageName);
                    $isPrimary = ($key == 0 && $equipment->images()->where('is_primary', true)->doesntExist());
                    $equipment->images()->create(['file_name' => $imageName, 'is_primary' => $isPrimary]);
                } catch (\Exception $e) { Log::error('SMB Upload failed in handleImageUploads(): ' . $e->getMessage()); }
            }
            if ($equipment->images()->exists() && $equipment->images()->where('is_primary', true)->doesntExist()) {
                $equipment->images()->first()?->update(['is_primary' => true]);
            }
        }
    }
    private function handleImageUpdates(Request $request, Equipment $equipment)
    {
        if ($request->has('delete_images')) {
            $equipment->loadMissing('images');
            $imagesToDelete = EquipmentImage::whereIn('id', $request->delete_images)->where('equipment_id', $equipment->id)->get();
            foreach ($imagesToDelete as $image) {
                try {
                    $this->smbService->resetShare()->delete($image->file_name);
                    $image->delete();
                }
                catch (\Exception $e) { Log::warning('SMB Delete failed during handleImageUpdates(): ' . $e->getMessage(), ['filename' => $image->file_name]); }
            }
        }
        $this->handleImageUploads($request, $equipment);
        if ($request->filled('primary_image')) {
            $equipment->images()->update(['is_primary' => false]);
            EquipmentImage::where('id', $request->primary_image)->where('equipment_id', $equipment->id)->update(['is_primary' => true]);
        }
        elseif ($equipment->images()->exists() && $equipment->images()->where('is_primary', true)->doesntExist()) {
            $equipment->images()->first()?->update(['is_primary' => true]);
        }
    }


    // --- handleMsdsUpload, getNextSerialNumber, getMsdsFormContent ---
    private function handleMsdsUpload(Request $request, ?Equipment $existingEquipment = null): array
    {
        $msdsData = [];
        $isChecked = $request->has('has_msds');
        $msdsData['has_msds'] = $isChecked;
        if ($isChecked) {
            $msdsData['msds_details'] = $request->input('msds_details');
            if ($request->hasFile('msds_file')) {
                if ($existingEquipment && $existingEquipment->msds_file_path) {
                    try { Storage::disk('public')->delete($existingEquipment->msds_file_path); }
                    catch (\Exception $e) { Log::warning('Failed to delete old MSDS file: ' . $e->getMessage(), ['path' => $existingEquipment->msds_file_path]); }
                }
                try {
                    $path = $request->file('msds_file')->store('msds_files', 'public');
                    $msdsData['msds_file_path'] = $path;
                } catch (\Exception $e) { Log::error('MSDS File Upload Error: ' . $e->getMessage()); throw new \Exception('ไม่สามารถอัปโหลดไฟล์ MSDS ได้: ' . $e->getMessage()); }
            } else {
                $msdsData['msds_file_path'] = $existingEquipment?->msds_file_path;
            }
        } else {
            $msdsData['msds_details'] = null;
            $msdsData['msds_file_path'] = null;
            if ($existingEquipment && $existingEquipment->msds_file_path) {
                try { Storage::disk('public')->delete($existingEquipment->msds_file_path); }
                catch (\Exception $e) { Log::warning('Failed to delete old MSDS file when unchecked: ' . $e->getMessage(), ['path' => $existingEquipment->msds_file_path]); }
            }
        }
        return $msdsData;
    }
    public function getNextSerialNumber(Request $request)
    {
        $this->authorize('equipment:manage');
        $this->switchToDefaultDb();
        $request->validate(['category_id' => 'required|exists:categories,id']);
        $category = Category::find($request->category_id);
        if (!$category || !$category->prefix) { return response()->json(['success' => true, 'serial_number' => '']); }
        $prefix = $category->prefix;
        $lastEquipment = Equipment::withTrashed()
                            ->where('serial_number', 'LIKE', $prefix . '-%')
                            ->orderByRaw('CAST(SUBSTRING_INDEX(serial_number, "-", -1) AS UNSIGNED) DESC')
                            ->first();
        $nextNumber = 1;
        if ($lastEquipment) {
            $parts = explode('-', $lastEquipment->serial_number);
            if (is_numeric(end($parts))) {
                $lastNumber = (int)end($parts);
                $nextNumber = $lastNumber + 1;
            }
        }
        $newSerial = $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        return response()->json(['success' => true, 'serial_number' => $newSerial]);
    }
    public function getMsdsFormContent(Request $request)
    {
        $this->authorize('equipment:manage');
        $details = $request->query('details', '');
        $fileStatus = $request->query('fileStatus', 'ยังไม่มีการอัปโหลดไฟล์ MSDS');
        try {
            return view('partials.modals.msds-modal', [
                'details' => $details,
                'fileStatus' => html_entity_decode($fileStatus)
            ]);
        } catch (\Exception $e) {
            Log::error('Error rendering MSDS form content (partials.modals.msds-modal): ' . $e->getMessage());
            return response('<p class="text-red-500">Error loading form content. Please check logs.</p>', 500);
        }
    }

}