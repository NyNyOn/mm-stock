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
    // 1. LIST & SHOW
    // =========================================================================

    public function index(Request $request)
    {
        try {
            $statusFilter = $request->query('status', 'my_history');
            $query = Transaction::with(['equipment.latestImage', 'user', 'handler'])
                                ->orderBy('transaction_date', 'desc');

            if ($statusFilter == 'pending_confirmation') {
                $query->where('user_id', Auth::id())
                        ->whereIn('status', ['shipped', 'user_confirm_pending']);
            } elseif ($statusFilter == 'my_history') {
                $query->where('user_id', Auth::id());
            } elseif ($statusFilter == 'all_history') {
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
                if ($type = $request->get('type')) { $query->where('type', $type); }
                if ($userId = $request->get('user_id')) { $query->where('user_id', $userId); }
                if ($startDate = $request->get('start_date')) { $query->whereDate('transaction_date', '>=', $startDate); }
                if ($endDate = $request->get('end_date')) { $query->whereDate('transaction_date', '<=', $endDate); }
            }

            $transactions = $query->paginate(15)->appends($request->query());

            if ($request->ajax() && $statusFilter == 'all_history') {
                $latestTimestamp = $transactions->isNotEmpty() ? Carbon::parse($transactions->first()->transaction_date)->timestamp : now()->timestamp;
                return response()->json([
                    'view' => view('transactions.partials._table_rows', compact('transactions'))->render(),
                    'pagination' => $transactions->links()->toHtml(),
                    'latest_timestamp' => $latestTimestamp
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

        } catch (\Throwable $e) {
            Log::error('Transaction Index Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            if ($request->ajax()) { return response()->json(['error' => 'เกิดข้อผิดพลาดในการโหลดข้อมูล'], 500); }
            $transactions = collect(); $users = collect(); $types = []; $statusFilter = 'my_history';
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการโหลดข้อมูล โปรดตรวจสอบ Log');
        }
        return view('transactions.index', compact('transactions', 'users', 'types', 'statusFilter'));
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['user', 'equipment.latestImage', 'handler', 'glpiTicketRelation']);
        return response()->json(['success' => true, 'data' => $transaction]);
    }

    public function searchItems(Request $request)
    {
        $term = $request->input('q', '');
        $query = Equipment::whereIn('status', ['available', 'low_stock'])
                            ->where('quantity', '>', 0);
        
        try { 
            if (method_exists(Equipment::class, 'ratings')) {
                $query->withAvg('ratings', 'rating');
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
            $primaryImage = $item->images->firstWhere('is_primary', true) ?? $item->images->first();
            $imageFileName = $primaryImage->file_name ?? null;
            try {
                $item->image_url = $imageFileName ? route('nas.image', ['deptKey' => $defaultDeptKey, 'filename' => $imageFileName]) : asset('images/placeholder.webp');
            } catch (\Exception $e) {
                $item->image_url = asset('images/placeholder.webp');
            }
            $item->unit_name = $item->unit->name ?? 'N/A';
            
            $item->avg_rating = $item->ratings_avg_rating ? (float)$item->ratings_avg_rating : 0;
            $item->rating_count = $item->ratings_count ?? 0;

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
        $this->authorize('equipment:manage');

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

                $purpose = $request->input('purpose');
                $notes = $request->input('notes');
                $combinedNotes = $notes ?? '';
                $glpiTicketId = null;
                $purposeForDb = $purpose;

                if (str_starts_with($purpose, 'glpi-')) {
                    $parts = explode('-', $purpose);
                    if (count($parts) === 3) {
                        $glpiTicketId = (int) $parts[2];
                        $purposeForDb = 'glpi_ticket';
                        $combinedNotes = "อ้างอิงใบงาน GLPI #{$glpiTicketId}\n" . $combinedNotes;
                    }
                } else {
                    $combinedNotes = "วัตถุประสงค์: " . $purpose . "\n" . $combinedNotes;
                }

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
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด'], 500);
        }
    }

    public function handleUserTransaction(Request $request)
    {
        Log::debug('===== handleUserTransaction Start =====');
        $this->authorize('equipment:borrow');

        $loggedInUser = Auth::user();
        $canAutoConfirm = $loggedInUser->can('transaction:auto_confirm');

        $requestorType = $request->input('requestor_type');
        $targetUserId = ($requestorType === 'other' && $request->filled('requestor_id')) 
                        ? (int)$request->input('requestor_id') : $loggedInUser->id;

        // ✅ เช็ค Block เฉพาะกรณีเบิกให้ตัวเอง
        if ($targetUserId === $loggedInUser->id) {
            $unratedTransactions = $this->getUnratedTransactions($targetUserId);
            if ($unratedTransactions->count() > 0) {
                // ✅ เพิ่ม ->values() เพื่อให้มั่นใจว่าเป็น Array JSON ไม่ใช่ Object
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

            $purpose = $request->input('purpose');
            $combinedNotes = $request->input('notes') ?? '';
            $glpiTicketId = null;

             if (str_starts_with($purpose, 'glpi-')) {
                $parts = explode('-', $purpose);
                if (count($parts) === 3) {
                    $glpiTicketId = (int) $parts[2];
                    $combinedNotes = "อ้างอิง GLPI #{$glpiTicketId}\n" . $combinedNotes;
                }
            } else {
                $combinedNotes = "วัตถุประสงค์: " . $purpose . "\n" . $combinedNotes;
            }

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

            return response()->json(['success' => true, 'message' => $successMessage]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("handleUserTransaction Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด'], 500);
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
        if (Auth::id() !== $transaction->user_id && !Auth::user()->can('permission:manage')) return back()->with('error', 'ไม่มีสิทธิ์');

        if (in_array($transaction->status, ['shipped', 'user_confirm_pending'])) {
            DB::beginTransaction();
            try {
                $transaction->update([
                    'user_confirmed_at' => now(), 
                    'confirmed_at' => now(), 
                    'status' => 'completed',
                    'handler_id' => $transaction->handler_id ?? Auth::id()
                ]);

                if ($transaction->type === 'return') {
                    Equipment::where('id', $transaction->equipment_id)->increment('quantity', $transaction->quantity_change);
                }

                try { (new SynologyService())->notify(new UserConfirmedReceipt($transaction->load('equipment', 'user', 'handler'))); } catch (\Exception $e) {}

                DB::commit();
                return back()->with('success', 'ยืนยันเรียบร้อย');
            } catch (\Exception $e) { DB::rollBack(); return back()->with('error', 'Error: ' . $e->getMessage()); }
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
    // 4. RATING SYSTEM (ใช้ตาราง equipment_ratings)
    // =========================================================================

    // ✅ API Check Block Status
    public function checkBlockStatus(Request $request)
    {
        try {
            $userId = Auth::id();
            $unratedTransactions = $this->getUnratedTransactions($userId);

            // 🔍 DEBUG LOG: เช็คว่าเจอเท่าไหร่
            Log::info("[CheckBlockStatus] User ID: {$userId}, Count: " . $unratedTransactions->count());

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
                    // ✅ เพิ่ม ->values() เพื่อบังคับให้เป็น JSON Array [{},{},{}] 
                    // แก้ปัญหา JS อ่านค่าได้แค่ตัวเดียว
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
            ->where('status', 'completed') // ✅ ต้อง Completed เท่านั้น
            ->whereIn('type', ['consumable', 'returnable', 'partial_return'])
            ->whereDoesntHave('rating') // เช็คว่าไม่มีในตาราง equipment_ratings
            ->orderBy('transaction_date', 'desc')
            ->with(['equipment.latestImage'])
            ->get();
    }

    // ✅ Rate Transaction
    public function rateTransaction(Request $request, Transaction $transaction)
    {
        if (Auth::id() !== $transaction->user_id) return response()->json(['success' => false, 'message' => 'ไม่มีสิทธิ์'], 403);
        
        if ($transaction->rating()->exists()) {
            return response()->json(['success' => false, 'message' => 'รายการนี้ประเมินไปแล้ว'], 400);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'rating_comment' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);

        DB::beginTransaction();
        try {
            // ✅ บันทึกลง equipment_ratings
            EquipmentRating::create([
                'transaction_id' => $transaction->id,
                'equipment_id' => $transaction->equipment_id,
                'rating' => (int) $request->input('rating'),
                'comment' => $request->input('rating_comment'),
            ]);
            
            DB::commit();
            
            $remainingCount = $this->getUnratedTransactions(Auth::id())->count();
            return response()->json(['success' => true, 'message' => 'บันทึกคะแนนเรียบร้อย', 'remaining_count' => $remainingCount]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Rate Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'บันทึกไม่สำเร็จ'], 500);
        }
    }
}