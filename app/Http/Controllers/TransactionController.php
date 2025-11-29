<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\EquipmentRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Notifications\EquipmentRequested;
use App\Notifications\RequestApproved;
use App\Notifications\UserConfirmedReceipt;
use App\Services\SynologyService;
use Carbon\Carbon;
use App\Models\GlpiTicket;

use App\Notifications\RequestCancelledByUser;
use App\Notifications\RequestCancelledByAdmin;
use App\Notifications\TransactionReversedByAdmin;

class TransactionController extends Controller
{
    use AuthorizesRequests;

    // =========================================================================
    // 🛡️ HELPER: ระบบบังคับแช่แข็ง (Self-Healing Frozen State)
    // =========================================================================
    /**
     * ตรวจสอบและบังคับแช่แข็งทันทีถ้าหมดอายุ (ป้องกันการเบิกของที่หมดอายุแต่สถานะยังไม่เปลี่ยน)
     */
    private function checkAndEnforceFrozenState(Equipment $equipment)
    {
        // ถ้าสถานะเป็น frozen, sold, disposed อยู่แล้ว ไม่ต้องเช็คซ้ำ
        if (in_array($equipment->status, ['frozen', 'sold', 'disposed'])) {
            return;
        }

        $limitDays = 105;
        $isExpired = false;

        if (is_null($equipment->last_stock_check_at)) {
            // ถ้าไม่เคยนับเลย -> หมดอายุทันที
            $isExpired = true;
        } else {
            // ถ้านับล่าสุด นานกว่า 105 วัน -> หมดอายุ
            $daysSinceCheck = Carbon::parse($equipment->last_stock_check_at)->diffInDays(now());
            if ($daysSinceCheck >= $limitDays) {
                $isExpired = true;
            }
        }

        // 🔥 ถ้าหมดอายุจริง แต่สถานะยังไม่ Frozen -> สั่งแช่แข็งเดี๋ยวนี้!
        if ($isExpired) {
            $equipment->status = 'frozen';
            $equipment->save();
            $equipment->refresh(); // โหลดค่าใหม่มาใช้
            Log::info("Force Frozen Triggered: Equipment ID {$equipment->id} ({$equipment->name})");
        }
    }

    // =========================================================================
    // 1. LIST & SHOW
    // =========================================================================

    public function index(Request $request)
    {
        try {
            // 1. Badge Counters (นับจำนวนแจ้งเตือนจุดแดง)
            $adminPendingCount = 0;
            $myPendingCount = 0;
            $user = Auth::user();

            // ถ้าเป็น Admin: นับรายการที่รออนุมัติ (Pending)
            if ($user->can('equipment:manage')) {
                $adminPendingCount = Transaction::where('status', 'pending')->count();
            }

            // User: นับรายการที่ตนเองต้องกดรับของ (Shipped / User Confirm Pending)
            $myPendingCount = Transaction::where('user_id', $user->id)
                ->whereIn('status', ['shipped', 'user_confirm_pending'])
                ->count();

            // 2. ตั้งค่า Default Tab
            // ถ้าเป็น Admin ให้ไปหน้า admin_pending ก่อน ถ้าไม่ใช่ให้ไป my_history
            $defaultTab = ($user->can('equipment:manage')) ? 'admin_pending' : 'my_history';
            $statusFilter = $request->query('status', $defaultTab);

            // 3. Query Builder
            $query = Transaction::with(['equipment.latestImage', 'user', 'handler', 'rating']) // Eager load rating
                                ->orderBy('transaction_date', 'desc');

            // --- Logic การกรองข้อมูลตาม Tab ---
            if ($statusFilter == 'admin_pending') {
                // Tab 1: รอจัดส่ง (Admin)
                $this->authorize('equipment:manage');
                $query->where('status', 'pending');

            } elseif ($statusFilter == 'my_pending') {
                // Tab 2: รายการที่ต้องจัดการ (User)
                $query->where('user_id', $user->id)
                        ->whereIn('status', ['shipped', 'user_confirm_pending']);

            } elseif ($statusFilter == 'my_history') {
                // Tab 3: ประวัติของฉัน
                $query->where('user_id', $user->id);

            } elseif ($statusFilter == 'all_history') {
                // Tab 4: ประวัติทั้งหมด (Admin Report)
                $this->authorize('report:view');

                if ($search = $request->get('search')) {
                    $query->where(function ($q) use ($search) {
                        $q->where('notes', 'like', "%{$search}%")
                            ->orWhere('purpose', 'like', "%{$search}%")
                            ->orWhereHas('equipment', function ($eq) use ($search) {
                                $eq->where('name', 'like', "%{$search}%")
                                    ->orWhere('serial_number', 'like', "%{$search}%");
                            });
                    });
                }
                if ($type = $request->get('type')) {
                    $query->where('type', $type);
                }
                if ($userId = $request->get('user_id')) {
                    $query->where('user_id', $userId);
                }
                if ($startDate = $request->get('start_date')) {
                    $query->whereDate('transaction_date', '>=', $startDate);
                }
                if ($endDate = $request->get('end_date')) {
                    $query->whereDate('transaction_date', '<=', $endDate);
                }
            }

            $transactions = $query->paginate(15)->appends($request->query());

            // AJAX Response (กรณีใช้ Pagination แบบไม่รีโหลดหน้า)
            if ($request->ajax()) {
                return response()->json([
                    'html' => view('transactions.partials._table_rows', compact('transactions', 'statusFilter'))->render(),
                    'pagination' => $transactions->links()->toHtml()
                ]);
            }

            $users = User::orderBy('fullname', 'asc')->get();
            
            $types = [
                'consumable' => 'เบิก (ไม่ต้องคืน)',
                'returnable' => 'ยืม (ต้องคืน)',
                'partial_return' => 'เบิก (เหลือคืนได้)',
                'withdraw' => 'เบิก (Admin)',
                'borrow' => 'ยืม (Admin)',
                'return' => 'คืน',
                'add' => 'เพิ่ม',
                'adjust' => 'ปรับสต็อก'
            ];

            // ส่งตัวแปร Counts ไปที่ View
            return view('transactions.index', compact(
                'transactions', 'users', 'types', 'statusFilter', 
                'adminPendingCount', 'myPendingCount'
            ));

        } catch (\Throwable $e) {
            Log::error('Transaction Index Error: ' . $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['error' => 'เกิดข้อผิดพลาดในการโหลดข้อมูล'], 500);
            }
            $transactions = collect();
            $users = collect();
            $types = [];
            $statusFilter = 'my_history';
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการโหลดข้อมูล โปรดตรวจสอบ Log');
        }
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['user', 'equipment.latestImage', 'handler', 'glpiTicketRelation']);
        return response()->json(['success' => true, 'data' => $transaction]);
    }

    public function searchItems(Request $request)
    {
        $term = $request->input('q', '');
        // ไม่กรอง status frozen ออกที่นี่ เพื่อให้ user เห็นว่าของมีอยู่จริง แต่เบิกไม่ได้ (จะไปบล็อกตอนกดเลือก)
        $query = Equipment::where('quantity', '>', 0)
                          ->whereNotIn('status', ['sold', 'disposed']); 
        
        try { 
            if (method_exists(Equipment::class, 'ratings')) {
                // ✅ [Fixed] ใช้ rating_score แทน rating เดิม
                $query->withAvg('ratings', 'rating_score');
                $query->withCount('ratings');
            }
        } catch (\Throwable $e) { }

        if ($term) {
            $query->where(function($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('serial_number', 'like', "%{$term}%")
                    ->orWhere('part_no', 'like', "%{$term}%");
            });
        }
        $items = $query->with('images', 'unit')->orderBy('name')->paginate(10);
        $defaultDeptKey = config('department_stocks.default_nas_dept_key', 'mm');
        $items->getCollection()->transform(function ($item) use ($defaultDeptKey) {
            // 🟢 Force Check ทุกครั้งที่ค้นหา
            $this->checkAndEnforceFrozenState($item);

            $primaryImage = $item->images->firstWhere('is_primary', true) ?? $item->images->first();
            $imageFileName = $primaryImage->file_name ?? null;
            try {
                $item->image_url = $imageFileName ? route('nas.image', ['deptKey' => $defaultDeptKey, 'filename' => $imageFileName]) : asset('images/placeholder.webp');
            } catch (\Exception $e) {
                $item->image_url = asset('images/placeholder.webp');
            }
            $item->unit_name = $item->unit->name ?? 'N/A';
            
            // ✅ [Fixed] รับค่า rating_score จาก alias ที่ Eloquent สร้างให้
            $item->avg_rating = $item->ratings_avg_rating_score ? (float)$item->ratings_avg_rating_score : 0;
            $item->rating_count = $item->ratings_count ?? 0;
            
            // ส่ง Flag Frozen กลับไปให้ Frontend
            $item->is_frozen = $item->status === 'frozen';

            return $item;
        });
        return response()->json($items);
    }

    // =========================================================================
    // 2. TRANSACTIONS (Store / Update)
    // =========================================================================

    public function storeWithdrawal(Request $request)
    {
        Log::debug('===== storeWithdrawal Start =====');
        $this->authorize('equipment:manage'); // Admin withdraw

        $validator = Validator::make($request->all(), [
            'type'             => ['required', Rule::in(['withdraw', 'borrow'])],
            'requestor_name'   => 'required|string|max:255',
            'purpose'          => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer|exists:equipments,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $loggedInUser = Auth::user();
        $canAutoConfirm = $loggedInUser->can('transaction:auto_confirm');
        
        DB::beginTransaction();
        try {
            $userIdToAssign = $loggedInUser->id;
            $requestorNameInput = $request->input('requestor_name');
            $userFromName = User::where('fullname', $requestorNameInput)->first();

            if ($userFromName) {
                $userIdToAssign = $userFromName->id;
            }
            $isSelfWithdrawal = ($userIdToAssign === $loggedInUser->id);

            $firstTransactionData = null;

            foreach ($request->items as $itemData) {
                $equipment = Equipment::lockForUpdate()->find($itemData['id']);
                $quantityToWithdraw = (int)$itemData['quantity'];

                if (!$equipment || $equipment->quantity < $quantityToWithdraw) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => "สต็อกของ " . ($equipment->name ?? "ID: {$itemData['id']}") . " ไม่เพียงพอ"], 400);
                }

                // ✅ [Safety Check] ตรวจสอบและบังคับแช่แข็ง
                $this->checkAndEnforceFrozenState($equipment);

                // ✅ [Frozen Check] บล็อกถ้าระงับ (ยกเว้นมีสิทธิ์ Bypass)
                if ($equipment->status === 'frozen') {
                    $canBypass = method_exists($loggedInUser, 'canBypassFrozenState') ? $loggedInUser->canBypassFrozenState() : false;
                    if (!$canBypass) {
                        DB::rollBack();
                        return response()->json(['success' => false, 'message' => "อุปกรณ์ '{$equipment->name}' ถูกระงับ (Frozen) กรุณานับสต็อกก่อน"], 403);
                    }
                }

                $purpose = $request->input('purpose');
                $notes = $request->input('notes');
                
                // ⚠️ FIXED: ไม่เอาวัตถุประสงค์ไปต่อท้ายใน notes แล้ว เพื่อแก้ปัญหาซ้ำซ้อนใน View
                $combinedNotes = $notes ?? ''; 
                
                $glpiTicketId = null;
                $purposeForDb = $purpose;

                if (str_starts_with($purpose, 'glpi-')) {
                    $parts = explode('-', $purpose);
                    if (count($parts) === 3) {
                        $glpiTicketId = (int) $parts[2];
                        $purposeForDb = 'glpi_ticket';
                        // สำหรับ GLPI เราอาจจะยังเก็บอ้างอิงไว้ใน notes ได้ ถ้าต้องการ
                        $combinedNotes = "อ้างอิงใบงาน GLPI #{$glpiTicketId}\n" . $combinedNotes;
                    }
                } 
                // ถ้าไม่ใช่ GLPI เราจะไม่เอา purpose ไปต่อใน notes แล้ว เพราะมีฟิลด์ purpose เก็บแยกต่างหาก

                $returnCondition = match ($request->type) {
                    'borrow' => 'allowed',
                    'withdraw' => match ($equipment->withdrawal_type) {
                        'consumable' => 'not_allowed',
                        'partial_return', 'returnable' => 'allowed',
                        default => 'not_allowed',
                    },
                    default => 'not_allowed',
                };

                if ($canAutoConfirm && $isSelfWithdrawal) {
                    $equipment->decrement('quantity', $quantityToWithdraw);
                    $transactionData = [
                        'equipment_id'    => $equipment->id, 'user_id' => $loggedInUser->id, 'handler_id' => $loggedInUser->id,
                        'type' => $request->type, 'quantity_change' => -$quantityToWithdraw,
                        'notes' => $combinedNotes, 'purpose' => $purposeForDb, 'glpi_ticket_id' => $glpiTicketId,
                        'transaction_date'=> now(), 'status' => 'completed', 
                        'admin_confirmed_at' => now(), 'user_confirmed_at' => now(), 'confirmed_at' => now(), 
                        'return_condition'=> $returnCondition,
                    ];
                    Transaction::create($transactionData);
                } else {
                    $transactionData = [
                        'equipment_id'    => $equipment->id, 'user_id' => $userIdToAssign, 'handler_id' => null,
                        'type' => $request->type, 'quantity_change' => -$quantityToWithdraw,
                        'notes' => $combinedNotes, 'purpose' => $purposeForDb, 'glpi_ticket_id' => $glpiTicketId,
                        'transaction_date'=> now(), 'status' => 'pending', 'return_condition'=> $returnCondition,
                    ];
                    $transaction = Transaction::create($transactionData);
                    if (!$firstTransactionData) { $firstTransactionData = $transaction; }
                }
            }

            DB::commit();

            if ((!$isSelfWithdrawal || !$canAutoConfirm) && $firstTransactionData) {
                if ($firstTransactionData->user) {
                    try {
                        (new SynologyService())->notify(new EquipmentRequested($firstTransactionData->load('equipment', 'user')));
                    } catch (\Exception $e) { Log::error("Notification Error: " . $e->getMessage()); }
                }
            }

            return response()->json(['success' => true, 'message' => ($canAutoConfirm && $isSelfWithdrawal) ? 'บันทึกสำเร็จ' : 'สร้างรายการสำเร็จ']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("storeWithdrawal Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }

    public function handleUserTransaction(Request $request)
    {
        Log::debug('===== handleUserTransaction Start =====');
        $this->authorize('equipment:borrow'); // User withdraw

        $loggedInUser = Auth::user();
        $canAutoConfirm = $loggedInUser->can('transaction:auto_confirm');

        $requestorType = $request->input('requestor_type');
        $targetUserId = ($requestorType === 'other' && $request->filled('requestor_id')) 
                        ? (int)$request->input('requestor_id') : $loggedInUser->id;

        // Check if user is blocked (unrated transactions)
        if ($targetUserId === $loggedInUser->id) {
            $unratedTransactions = $this->getUnratedTransactions($targetUserId);
            if ($unratedTransactions->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'คุณมีรายการอุปกรณ์ที่ยังไม่ได้ให้คะแนน',
                    'error_code' => 'UNRATED_TRANSACTIONS',
                    'unrated_items' => $unratedTransactions->values()
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'equipment_id'   => 'required|integer|exists:equipments,id',
            'type'           => ['required', Rule::in(['consumable', 'returnable', 'partial_return'])],
            'purpose'        => 'required|string|max:255',
            'quantity'       => 'required|integer|min:1',
            'requestor_type' => ['required', Rule::in(['self', 'other'])],
            'requestor_id'   => ['nullable', 'required_if:requestor_type,other', 'integer', Rule::exists('depart_it_db.sync_ldap', 'id')], 
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $userIdToAssign = $targetUserId;

        DB::beginTransaction();
        try {
            $equipment = Equipment::lockForUpdate()->find($request->input('equipment_id'));
            $transactionType = $request->input('type');
            $quantityToTransact = (int)$request->input('quantity');

            if (!$equipment || $equipment->quantity < $quantityToTransact) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => "สต็อกไม่เพียงพอ"], 400);
            }

            // ✅✅✅ [STEP 1]: บังคับเช็คและแช่แข็ง ถ้าหมดอายุจริง ✅✅✅
            $this->checkAndEnforceFrozenState($equipment);

            $bypassed = false;

            // ✅✅✅ [STEP 2]: บล็อกการเบิก ถ้าถูกแช่แข็ง (และไม่มีสิทธิ์ Bypass) ✅✅✅
            if ($equipment->status === 'frozen') {
                $canBypass = method_exists($loggedInUser, 'canBypassFrozenState') ? $loggedInUser->canBypassFrozenState() : false;
                
                if (!$canBypass) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => "❌ ทำรายการไม่สำเร็จ: อุปกรณ์นี้ถูกระงับ (Frozen) เนื่องจากไม่ได้นับสต็อกเกิน 105 วัน กรุณาติดต่อ Admin"], 403);
                } else {
                    $bypassed = true;
                    Log::warning("User ID {$loggedInUser->id} bypassed frozen item ID {$equipment->id}");
                }
            }

            $purpose = $request->input('purpose');
            $combinedNotes = $request->input('notes') ?? '';
            // ⚠️ FIXED: ไม่เอา purpose ไปต่อใน combinedNotes เพื่อลดความซ้ำซ้อน
            
            $glpiTicketId = null;

             if (str_starts_with($purpose, 'glpi-')) {
                $parts = explode('-', $purpose);
                if (count($parts) === 3) {
                    $glpiTicketId = (int) $parts[2];
                    $combinedNotes = "อ้างอิง GLPI #{$glpiTicketId}\n" . $combinedNotes;
                }
            } 
            // else: purpose is stored separately, no need to append to notes

            $returnCondition = ($transactionType === 'returnable' || $transactionType === 'partial_return') ? 'allowed' : 'not_allowed';
            $transaction = null;

            if ($canAutoConfirm) {
                $equipment->decrement('quantity', $quantityToTransact);
                $transactionData = [
                    'equipment_id'    => $equipment->id, 'user_id' => $userIdToAssign, 'handler_id' => $loggedInUser->id,
                    'type' => $transactionType, 'quantity_change' => -$quantityToTransact,
                    'notes' => $combinedNotes, 'purpose' => $purpose, 'glpi_ticket_id' => $glpiTicketId,
                    'transaction_date'=> now(), 'status' => 'completed', 
                    'admin_confirmed_at' => now(), 'user_confirmed_at' => now(), 'confirmed_at' => now(), 
                    'return_condition'=> $returnCondition,
                ];
                $transaction = Transaction::create($transactionData);
                $successMessage = 'บันทึกและยืนยันรายการเรียบร้อยแล้ว';
            } else {
                $transactionData = [
                    'equipment_id'    => $equipment->id, 'user_id' => $userIdToAssign, 'handler_id' => null,
                    'type' => $transactionType, 'quantity_change' => -$quantityToTransact,
                    'notes' => $combinedNotes, 'purpose' => $purpose, 'glpi_ticket_id' => $glpiTicketId,
                    'transaction_date'=> now(), 'status' => 'pending', 'return_condition'=> $returnCondition,
                ];
                $transaction = Transaction::create($transactionData);
                $successMessage = 'ส่งคำขอสำเร็จ! กรุณารอ Admin ยืนยัน';
            }

            DB::commit();

            if ($requestorType === 'other' || !$canAutoConfirm) {
                try {
                    (new SynologyService())->notify(new EquipmentRequested($transaction->load('equipment', 'user'), $loggedInUser));
                } catch (\Exception $e) { Log::error("Notify Error: " . $e->getMessage()); }
            }

            if ($bypassed) {
                $successMessage .= " (⚠️ Warning: Frozen Item Bypassed)";
            }

            return response()->json(['success' => true, 'message' => $successMessage]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("handleUserTransaction Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 3. ADMIN & USER ACTIONS (Confirm, Cancel, WriteOff)
    // =========================================================================

    public function adminConfirmShipment(Request $request, Transaction $transaction)
    {
        $this->authorize('equipment:manage');
        DB::beginTransaction();
        try {
            if (!in_array($transaction->status, ['pending', 'pending_approval'])) return back()->with('error', 'สถานะไม่ถูกต้อง');
            
            $equipment = Equipment::lockForUpdate()->find($transaction->equipment_id);
            if ($equipment->quantity < abs($transaction->quantity_change)) { DB::rollBack(); return back()->with('error', "สต็อกไม่พอ"); }

            $equipment->decrement('quantity', abs($transaction->quantity_change));
            $transaction->update(['admin_confirmed_at' => now(), 'handler_id' => Auth::id(), 'status' => 'shipped']);
            
            try { (new SynologyService())->notify(new RequestApproved($transaction->load('user', 'equipment'))); } catch (\Exception $e) {}

            DB::commit();
            return back()->with('success', 'ยืนยันแล้ว');
        } catch (\Exception $e) { DB::rollBack(); return back()->with('error', 'Error: ' . $e->getMessage()); }
    }

    public function userConfirmReceipt(Request $request, Transaction $transaction)
    {
        // ✅ [FIXED] อนุญาตให้ Owner หรือ Admin (permission:manage) กดรับได้
        if (Auth::id() !== $transaction->user_id && !Auth::user()->can('permission:manage')) {
            return back()->with('error', 'ไม่มีสิทธิ์ทำรายการนี้');
        }

        if (in_array($transaction->status, ['shipped', 'user_confirm_pending'])) {
            DB::beginTransaction();
            try {
                $updateData = [
                    'user_confirmed_at' => now(), 
                    'confirmed_at' => now(), 
                    'status' => 'completed',
                    'handler_id' => $transaction->handler_id ?? Auth::id()
                ];

                // ถ้าเป็น Admin กดรับแทน ให้ใส่ Note
                if (Auth::id() !== $transaction->user_id) {
                    $updateData['notes'] = $transaction->notes . "\n[System: Admin " . Auth::user()->fullname . " ยืนยันรับของแทน]";
                }

                $transaction->update($updateData);

                if ($transaction->type === 'return') {
                    Equipment::where('id', $transaction->equipment_id)->increment('quantity', $transaction->quantity_change);
                }

                try { (new SynologyService())->notify(new UserConfirmedReceipt($transaction->load('equipment', 'user', 'handler'))); } catch (\Exception $e) {}

                DB::commit();
                return back()->with('success', 'ยืนยันรับของเรียบร้อย' . (Auth::id() !== $transaction->user_id ? ' (ดำเนินการแทนผู้ใช้)' : ''));
            } catch (\Exception $e) { 
                DB::rollBack(); 
                return back()->with('error', 'Error: ' . $e->getMessage()); 
            }
        }
        return back()->with('error', 'สถานะไม่ถูกต้อง');
    }

    public function getLatestTimestamp()
    {
        return response()->json(['latest_timestamp' => now()->timestamp]);
    }

    public function writeOff(Request $request, Transaction $transaction) 
    { 
        $this->authorize('permission:manage');
        DB::beginTransaction();
        try {
            $remaining = abs($transaction->quantity_change) - ($transaction->returned_quantity ?? 0);
            if ($remaining <= 0) return back()->with('error', 'ไม่มียอดค้าง');
            
            $transaction->update(['status' => 'closed', 'returned_quantity' => abs($transaction->quantity_change)]);
            Transaction::create([
                'equipment_id' => $transaction->equipment_id,
                'user_id' => Auth::id(), 'handler_id' => Auth::id(),
                'type' => 'adjust', 'quantity_change' => 0,
                'notes' => "ตัดยอดสูญหาย จาก #{$transaction->id}",
                'transaction_date' => now(), 'status' => 'completed', 'confirmed_at' => now()
            ]);
            DB::commit();
            return back()->with('success', 'ตัดยอดสำเร็จ');
        } catch(\Exception $e) { DB::rollBack(); return back()->with('error', 'Error'); }
    }

    public function userCancel(Request $request, Transaction $transaction) 
    { 
        if (Auth::id() !== $transaction->user_id && !Auth::user()->can('permission:manage')) return back()->with('error', 'ไม่มีสิทธิ์');
        if ($transaction->status !== 'pending') return back()->with('error', 'ยกเลิกไม่ได้');

        $transaction->update(['status' => 'cancelled', 'notes' => $transaction->notes . "\nยกเลิกโดยผู้ใช้"]);
        try { (new SynologyService())->notify(new RequestCancelledByUser($transaction)); } catch(\Exception $e) {}
        
        return back()->with('success', 'ยกเลิกสำเร็จ'); 
    }

    public function adminCancelTransaction(Request $request, Transaction $transaction) 
    { 
        $this->authorize('permission:manage');
        if ($transaction->status !== 'completed') return back()->with('error', 'ต้อง Completed เท่านั้น');
        
        DB::beginTransaction();
        try {
            Equipment::where('id', $transaction->equipment_id)->increment('quantity', abs($transaction->quantity_change));
            $transaction->update(['status' => 'cancelled', 'notes' => $transaction->notes . "\nยกเลิกโดย Admin (Reversed)"]);
            try { (new SynologyService())->notify(new TransactionReversedByAdmin($transaction, Auth::user())); } catch(\Exception $e) {}
            
            DB::commit();
            return back()->with('success', 'ยกเลิกและคืนสต็อกสำเร็จ'); 
        } catch(\Exception $e) { DB::rollBack(); return back()->with('error', 'Error'); }
    }

    // =========================================================================
    // 4. RATING SYSTEM
    // =========================================================================

    public function checkBlockStatus(Request $request)
    {
        try {
            $userId = Auth::id();
            $unratedTransactions = $this->getUnratedTransactions($userId);

            if ($unratedTransactions->count() > 0) {
                $defaultDeptKey = config('department_stocks.default_nas_dept_key', 'mm');
                $unratedTransactions->transform(function ($tx) use ($defaultDeptKey) {
                    $imgUrl = asset('images/placeholder.webp');
                    if ($tx->equipment && $tx->equipment->latestImage) {
                        try {
                            $imgUrl = route('nas.image', ['deptKey' => $defaultDeptKey, 'filename' => $tx->equipment->latestImage->file_name]);
                        } catch (\Exception $e) {}
                    }
                    $tx->equipment_image_url = $imgUrl;
                    return $tx;
                });

                return response()->json([
                    'blocked' => true,
                    'message' => 'มีรายการค้างประเมิน',
                    'unrated_items' => $unratedTransactions->values()
                ]);
            }
            return response()->json(['blocked' => false]);
        } catch (\Exception $e) {
            return response()->json(['blocked' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function getUnratedTransactions($userId)
    {
        return Transaction::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereIn('type', ['consumable', 'returnable', 'partial_return'])
            ->whereDoesntHave('rating')
            ->orderBy('transaction_date', 'desc')
            ->with(['equipment.latestImage'])
            ->get();
    }

    /**
     * Store rating for a transaction (New System)
     * ✅ NAME: rateTransaction (ตรงกับ Route)
     */
    public function rateTransaction(Request $request, Transaction $transaction)
    {
        // 1. ตรวจสอบสิทธิ์: ให้เจ้าของรายการ หรือ Admin (เผื่อในอนาคต)
        if (Auth::id() !== $transaction->user_id && !Auth::user()->can('equipment:manage')) {
            return response()->json(['success' => false, 'message' => 'ไม่มีสิทธิ์ทำรายการนี้'], 403);
        }

        // 2. ตรวจสอบข้อมูล
        $validator = Validator::make($request->all(), [
            'q1' => 'required|integer|in:1,2,3',
            'q2' => 'required|integer|in:1,2,3',
            'q3' => 'required|integer|in:1,2,3',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        // เพิ่มการตรวจสอบ Model Method เพื่อป้องกัน Error 500 กรณีลืมอัปเดต Model
        if (!method_exists(\App\Models\EquipmentRating::class, 'calculateScore')) {
             return response()->json(['success' => false, 'message' => 'System Error: Please update App\Models\EquipmentRating.php to include calculateScore method.'], 500);
        }

        // 3. คำนวณคะแนนด้วยสูตรใหม่ (Model Helper)
        $score = \App\Models\EquipmentRating::calculateScore($request->q1, $request->q2, $request->q3);

        DB::beginTransaction();
        try {
            // 4. บันทึกข้อมูลลงฐานข้อมูล
            EquipmentRating::updateOrCreate(
                ['transaction_id' => $transaction->id],
                [
                    'equipment_id' => $transaction->equipment_id,
                    'q1_answer' => $request->q1,
                    'q2_answer' => $request->q2,
                    'q3_answer' => $request->q3,
                    'rating_score' => $score, // บันทึกค่าทศนิยม เช่น 3.67 หรือ null
                    'comment' => $request->comment,
                    'rated_at' => now(), // ✅ มี column นี้ใน DB แล้ว
                ]
            );

            DB::commit();
            return response()->json(['success' => true, 'message' => 'บันทึกการประเมินเรียบร้อยแล้ว']);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Rate Error: " . $e->getMessage());
            
            // ส่ง Error จริงกลับไปแสดงที่หน้าจอ (เพื่อ Debug ถ้ามีปัญหาอีก)
            return response()->json([
                'success' => false, 
                'message' => 'System Error: ' . $e->getMessage()
            ], 500);
        }
    }
}