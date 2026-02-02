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
    // 🛡️ HELPER
    // =========================================================================
    private function checkAndEnforceFrozenState(Equipment $equipment)
    {
        if (in_array($equipment->status, ['frozen', 'sold', 'disposed'])) {
            return;
        }

        $limitDays = 105;
        $isExpired = false;

        if (is_null($equipment->last_stock_check_at)) {
            $isExpired = true;
        } else {
            $daysSinceCheck = Carbon::parse($equipment->last_stock_check_at)->diffInDays(now());
            if ($daysSinceCheck >= $limitDays) {
                $isExpired = true;
            }
        }

        if ($isExpired) {
            $equipment->status = 'frozen';
            $equipment->save();
            $equipment->refresh();
            Log::info("Force Frozen Triggered: Equipment ID {$equipment->id} ({$equipment->name})");
        }
    }

    private function checkPendingConfirmations($userId)
    {
        $pendingTransactions = Transaction::with('equipment')
            ->where('user_id', $userId)
            ->whereIn('status', ['shipped', 'user_confirm_pending'])
            ->get();

        if ($pendingTransactions->isNotEmpty()) {
            $user = User::select('fullname')->find($userId);
            $name = $user ? $user->fullname : "ผู้ใช้";
            
            // Build detailed list of pending items
            $itemsList = $pendingTransactions->map(function($txn) {
                $equipmentName = $txn->equipment ? $txn->equipment->name : 'N/A';
                $qty = abs($txn->quantity_change);
                $date = $txn->transaction_date->format('d/m/Y H:i');
                return "• {$equipmentName} (จำนวน: {$qty}) - เบิกเมื่อ: {$date}";
            })->join("\n");
            
            $message = "⚠️ ไม่สามารถทำรายการได้\n\n" .
                       "คุณ {$name} ยังมีรายการค้างรับของ ({$pendingTransactions->count()} รายการ):\n" .
                       "{$itemsList}\n\n" .
                       "📌 กรุณากดยืนยันรับของ (Confirm Receipt) ในหน้า 'รายการเบิก' ก่อนทำรายการใหม่";
            
            return $message;
        }
        return null;
    }

    // ✅ Helper Function for Stock Calculation (Returns Qty + Names)
    private function getPendingStockDetails($equipmentId)
    {
        $transactions = Transaction::where('equipment_id', $equipmentId)
            ->whereIn('status', ['pending', 'pending_approval'])
            ->with(['user']) // Eager load user
            ->get();

        $quantity = $transactions->sum(function ($tx) { 
            return abs($tx->quantity_change); 
        });

        $names = $transactions->pluck('user.fullname')->unique()->filter()->values()->toArray();
        
        $earliestTime = $transactions->min('transaction_date');
        $timeStr = $earliestTime ? \Carbon\Carbon::parse($earliestTime)->format('H:i') : null;

        return [
            'quantity' => $quantity,
            'names' => $names,
            'time' => $timeStr
        ];
    }

    // =========================================================================
    // 1. LIST & SHOW
    // =========================================================================

    public function index(Request $request)
    {
        try {
            $adminPendingCount = 0;
            $myPendingCount = 0;
            $user = Auth::user();

            // ✅ Allow Confirmers OR Cancelers to see pending list counts
            if ($user->can('transaction:confirm') || $user->can('transaction:cancel')) {
                $adminPendingCount = Transaction::where('status', 'pending')->count();
            }

            $myPendingCount = Transaction::where('user_id', $user->id)
                ->whereIn('status', ['shipped', 'user_confirm_pending'])
                ->count();

            // ✅ Default tab priorities
            $defaultTab = ($user->can('transaction:confirm') || $user->can('transaction:cancel')) ? 'admin_pending' : 'my_history';
            $statusFilter = $request->query('status', $defaultTab);

            $query = Transaction::with(['equipment.latestImage', 'user', 'handler', 'rating'])
                                ->whereHas('equipment', function ($q) {
                                    $q->whereNull('deleted_at');
                                })
                                ->orderBy('transaction_date', 'desc');

            if ($statusFilter == 'admin_pending') {
                // ✅ Authorize either Confirm OR Cancel permission
                if (!$user->can('transaction:confirm') && !$user->can('transaction:cancel')) {
                    abort(403);
                }
                $query->where('status', 'pending');

            } elseif ($statusFilter == 'my_pending') {
                $query->where('user_id', $user->id)
                        ->whereIn('status', ['shipped', 'user_confirm_pending']);

            } elseif ($statusFilter == 'my_history') {
                $query->where('user_id', $user->id);

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
        // ✅ เพิ่ม 'rating' เพื่อส่งข้อมูลประเมิน (feedback_type, comment) ไปแสดงใน Modal
        $transaction->load(['user', 'equipment.latestImage', 'handler', 'glpiTicketRelation', 'rating']);
        return response()->json(['success' => true, 'data' => $transaction]);
    }

    public function searchItems(Request $request)
    {
        $term = $request->input('q', '');
        $query = Equipment::where('quantity', '>', 0)
                          ->whereNotIn('status', ['sold', 'disposed']); 
        
        if ($term) {
            $query->where(function($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('serial_number', 'like', "%{$term}%")
                    ->orWhere('part_no', 'like', "%{$term}%");
            });
        }
        
        // ✅ Eager load ratings to ensure accurate calculation (avoiding withAvg null issues)
        $items = $query->with('images', 'unit', 'ratings')->orderBy('name')->paginate(10);
        $defaultDeptKey = config('department_stocks.default_nas_dept_key', 'mm');

        $items->getCollection()->transform(function ($item) use ($defaultDeptKey) {
            $this->checkAndEnforceFrozenState($item);

            $primaryImage = $item->images->firstWhere('is_primary', true) ?? $item->images->first();
            $imageFileName = $primaryImage->file_name ?? null;
            $deptKey = $item->dept_key ?? $defaultDeptKey;

            try {
                // Use manual URL to avoid route conflicts and replace placeholder with external service
                $item->image_url = ($imageFileName && trim($imageFileName) !== '') 
                    ? url("nas-images/{$deptKey}/{$imageFileName}") 
                    : 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';
            } catch (\Exception $e) {
                $item->image_url = 'https://placehold.co/400x300/e2e8f0/64748b?text=Error';
            }
            $item->unit_name = $item->unit->name ?? 'N/A';
            
            // ✅ Calculate Ratings Manually
            $ratedItems = $item->ratings->whereNotNull('rating_score');
            $item->avg_rating = $ratedItems->count() > 0 ? (float)round($ratedItems->avg('rating_score'), 2) : 0;
            $item->rating_count = $item->ratings->count(); // Count all ratings including 'Not Used'
            
            $item->is_frozen = $item->status === 'frozen';
            $item->dept_key = $deptKey;
            
            // Cleanup to reduce payload
            unset($item->ratings);

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

            // ✅✅✅ Pre-process: Merge duplicate items by ID ✅✅✅
            $mergedItems = [];
            foreach ($request->items as $item) {
                $id = $item['id'];
                if (isset($mergedItems[$id])) {
                    $mergedItems[$id]['quantity'] += (int)$item['quantity'];
                } else {
                    $mergedItems[$id] = [
                        'id' => $id,
                        'quantity' => (int)$item['quantity']
                    ];
                }
            }
            $itemsToProcess = array_values($mergedItems);

            foreach ($itemsToProcess as $itemData) {
                $equipment = Equipment::lockForUpdate()->find($itemData['id']);
                $quantityToWithdraw = (int)$itemData['quantity'];

                // ✅✅✅ Stock Check with Pending Calculation ✅✅✅
                $pendingInfo = $this->getPendingStockDetails($equipment->id);
                $pendingQty = $pendingInfo['quantity'];
                $availableQty = $equipment->quantity - $pendingQty;

                if ($equipment->quantity < $quantityToWithdraw) {
                     DB::rollBack();
                     return response()->json(['success' => false, 'message' => "❌ สต็อกไม่พอ: {$equipment->name} (มี: {$equipment->quantity}, ขอ: {$quantityToWithdraw})"], 400); 
                }

                if ($quantityToWithdraw > $availableQty) {
                    DB::rollBack();
                    
                    $namesHtml = collect($pendingInfo['names'])->map(fn($n) => "<span style='color:#4f46e5; font-weight:bold;'>$n</span>")->take(3)->implode(', ');
                    if (count($pendingInfo['names']) > 3) $namesHtml .= " และคนอื่นๆ";
                    
                    $time = $pendingInfo['time'] ?? now()->format('H:i');
                    $msg = "<div class='text-left text-sm'>" .
                           "<b>⚠️ แจ้งเตือน: สต็อกไม่เพียงพอ</b> <span class='text-gray-500 text-xs'>({$time} น.)</span><br><br>" .
                           "📦 <b>รายการ:</b> " . ($equipment->name ?? 'N/A') . "<br>" .
                           "🛑 <b>ถูกจองแล้ว:</b> {$pendingQty} ชิ้น<br>" .
                           "👤 <b>โดย:</b> {$namesHtml}<br>" .
                           "📉 <b>คงเหลือเบิกได้จริง:</b> <span class='text-red-600 font-bold'>{$availableQty} ชิ้น</span>" .
                           "</div>";

                    return response()->json([
                        'success' => false, 
                        'message' => $msg
                    ], 400);
                }

                $this->checkAndEnforceFrozenState($equipment);

                if ($equipment->status === 'frozen') {
                    $canBypass = method_exists($loggedInUser, 'canBypassFrozenState') ? $loggedInUser->canBypassFrozenState() : false;
                    if (!$canBypass) {
                        DB::rollBack();
                        return response()->json(['success' => false, 'message' => "อุปกรณ์ '{$equipment->name}' ถูกระงับ (Frozen) กรุณานับสต็อกก่อน"], 403);
                    }
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
                    $equipment->quantity -= $quantityToWithdraw;
                    $equipment->save(); // ✅ Trigger 'saving' event for status update
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

            // ✅ ส่งแจ้งเตือนเสมอ
            if ($firstTransactionData && $firstTransactionData->user) {
                try {
                    // Notify Synology (Admin Log)
                    (new SynologyService())->notify(new EquipmentRequested($firstTransactionData->load('equipment', 'user'), $loggedInUser));
                    
                    // ✅ Notify Receiver if Admin did it for them (Item Received)
                    if ($canAutoConfirm && $isSelfWithdrawal && $userIdToAssign !== $loggedInUser->id) {
                         // Note: $isSelfWithdrawal check in storeWithdrawal logic might be tricky. 
                         // Check line 369: if ($canAutoConfirm && $isSelfWithdrawal)
                         // If isSelfWithdrawal is true, then userIdToAssign IS loggedInUser.
                         // So this block might not needed here IF storeWithdrawal ONLY handles self?
                         // Line 345: $isSelfWithdrawal = ($request->input('requestor_type') !== 'other');
                         // If 'other', isSelfWithdrawal is false.
                         // Line 369: if ($canAutoConfirm && $isSelfWithdrawal) -> Only logic for SELF.
                         // Line 380: else -> Logic for OTHER (Pending).
                    }
                    
                    // Wait, storeWithdrawal logic:
                    // Line 369: if ($canAutoConfirm && $isSelfWithdrawal) { status=completed }
                    // Line 380: else { status=pending }
                    // So storeWithdrawal DOES NOT allow Admin to Auto-Confirm for Others?
                    // If so, it goes to Pending. Then Admin approves -> RequestApproved -> User notified.
                    // So storeWithdrawal might be fine (captured by adminConfirmShipment).
                    
                    // BUT handleUserTransaction (Line 522):
                    // if ($canAutoConfirm) { status=completed } (No check for isSelfWithdrawal!!!!)
                    // So handleUserTransaction IS the place where Admin acts for Other and it completes immediately.
                } catch (\Exception $e) { Log::error("Notification Error: " . $e->getMessage()); }
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
        $this->authorize('equipment:borrow'); 

        $loggedInUser = Auth::user();
        $canAutoConfirm = $loggedInUser->can('transaction:auto_confirm');

        $requestorType = $request->input('requestor_type');
        $requestorIdInput = $request->input('requestor_id');
        $targetUserId = ($requestorType === 'other' && !empty($requestorIdInput)) 
                        ? (int)$requestorIdInput : $loggedInUser->id;

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

        // ✅ CHECK: ตรวจสอบรายการค้างรับ (Shipped)
        if ($pendingError = $this->checkPendingConfirmations($targetUserId)) {
            return response()->json(['success' => false, 'message' => $pendingError], 403);
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

            // ✅✅✅ Stock Check with Pending Calculation ✅✅✅
            $pendingInfo = $this->getPendingStockDetails($equipment->id);
            $pendingQty = $pendingInfo['quantity'];
            $availableQty = $equipment->quantity - $pendingQty;

            if ($quantityToTransact > $availableQty) {
                DB::rollBack();

                $namesHtml = collect($pendingInfo['names'])->map(fn($n) => "<span style='color:#4f46e5; font-weight:bold;'>$n</span>")->take(3)->implode(', ');
                if (count($pendingInfo['names']) > 3) $namesHtml .= " และคนอื่นๆ";

                $time = $pendingInfo['time'] ?? now()->format('H:i');
                $msg = "<div class='text-left text-sm'>" .
                       "<b>⚠️ แจ้งเตือน: สต็อกไม่เพียงพอ</b> <span class='text-gray-500 text-xs'>({$time} น.)</span><br><br>" .
                       "📦 <b>รายการ:</b> " . ($equipment->name ?? 'N/A') . "<br>" .
                       "🛑 <b>ถูกจองแล้ว:</b> {$pendingQty} ชิ้น<br>" .
                       "👤 <b>โดย:</b> {$namesHtml}<br>" .
                       "📉 <b>คงเหลือเบิกได้จริง:</b> <span class='text-red-600 font-bold'>{$availableQty} ชิ้น</span>" .
                       "</div>";

                return response()->json([
                    'success' => false, 
                    'message' => $msg
                ], 400);
            }

            $this->checkAndEnforceFrozenState($equipment);

            $bypassed = false;
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
            $glpiTicketId = null;

             if (str_starts_with($purpose, 'glpi-')) {
                $parts = explode('-', $purpose);
                if (count($parts) === 3) {
                    $glpiTicketId = (int) $parts[2];
                    $combinedNotes = "อ้างอิง GLPI #{$glpiTicketId}\n" . $combinedNotes;
                }
            } 

            $returnCondition = ($transactionType === 'returnable' || $transactionType === 'partial_return') ? 'allowed' : 'not_allowed';
            $transaction = null;

            if ($canAutoConfirm) {
                $equipment->quantity -= $quantityToTransact;
                $equipment->save(); // ✅ Trigger 'saving' event for status update
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

            // ✅ ส่งแจ้งเตือนเสมอ (ไม่ว่าจะเบิกให้ตัวเองหรือคนอื่น)
            try {
                // Notify Synology (Admin Log)
                (new SynologyService())->notify(new EquipmentRequested($transaction->load('equipment', 'user'), $loggedInUser));

                // ✅ Notify Receiver if Admin did it for them (Item Received)
                if ($canAutoConfirm && $userIdToAssign !== $loggedInUser->id) {
                     $transaction->user->notify(new \App\Notifications\ItemReceived($transaction, $loggedInUser));
                }
            } catch (\Exception $e) { Log::error("Notify Error: " . $e->getMessage()); }

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

    // ✅✅✅ FIXED: แก้ไข Bulk ให้รองรับ Auto-Confirm และไม่บันทึก 'general_use' ซ้ำซ้อนใน Notes ✅✅✅
    public function bulkWithdraw(Request $request)
    {
        $this->authorize('equipment:borrow'); 
        $loggedInUser = Auth::user();
        $canAutoConfirm = $loggedInUser->can('transaction:auto_confirm'); 
        
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.equipment_id' => 'required|exists:equipments,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string', 
            'items.*.receiver_id' => 'nullable|integer|exists:depart_it_db.sync_ldap,id', 
            'dept_key' => 'nullable|string' 
        ]);

        // ✅ CHECK: ตรวจสอบการประเมินความพึงพอใจ
        // (ตรวจสอบเฉพาะกรณีเบิกให้ตัวเอง หรือ receiver_id ตรงกับตัวเอง)
        $receiverId = $request->items[0]['receiver_id'] ?? $loggedInUser->id; // ใช้ item แรกเป็นเกณฑ์ (Cart มักจะเป็นคนเดียวกัน)
        if ($receiverId == $loggedInUser->id) {
            $unratedTransactions = $this->getUnratedTransactions($loggedInUser->id);
            if ($unratedTransactions->count() > 0) {
                return response()->json([
                    'message' => "คุณมีรายการอุปกรณ์ที่ยังไม่ได้ให้คะแนน",
                    'unrated_items' => $unratedTransactions->values()
                ], 403);
            }
        }

        // ✅ CHECK: ตรวจสอบรายการค้างรับ (Shipped) สำหรับผู้รับทุกคนในรายการ
        $allReceivers = collect($request->items)->map(function($item) use ($loggedInUser) {
            return $item['receiver_id'] ?? $loggedInUser->id;
        })->unique();

        foreach ($allReceivers as $uid) {
            if ($pendingError = $this->checkPendingConfirmations($uid)) {
                return response()->json(['message' => $pendingError], 403);
            }
        }

        DB::beginTransaction();
        try {
            $results = [];
            foreach ($request->items as $itemData) {
                $equipment = Equipment::lockForUpdate()->find($itemData['equipment_id']);
                
                if ($equipment->quantity < $itemData['quantity']) {
                    throw new \Exception("สินค้า {$equipment->name} มีไม่พอ (คงเหลือ: {$equipment->quantity})");
                }

                // ✅✅✅ Stock Check with Pending Calculation ✅✅✅
                $pendingInfo = $this->getPendingStockDetails($equipment->id);
                $pendingQty = $pendingInfo['quantity'];
                $availableQty = $equipment->quantity - $pendingQty;

                if ($availableQty < $itemData['quantity']) {
                    $namesHtml = collect($pendingInfo['names'])->map(fn($n) => "<span style='color:#4f46e5; font-weight:bold;'>$n</span>")->take(3)->implode(', ');
                    if (count($pendingInfo['names']) > 3) $namesHtml .= " และคนอื่นๆ";

                    $time = $pendingInfo['time'] ?? now()->format('H:i');
                    $msg = "<div class='text-left text-sm'>" .
                           "<b>⚠️ แจ้งเตือน: สต็อกไม่เพียงพอ</b> <span class='text-gray-500 text-xs'>({$time} น.)</span><br><br>" .
                           "📦 <b>รายการ:</b> " . ($equipment->name ?? 'N/A') . "<br>" .
                           "🛑 <b>ถูกจองแล้ว:</b> {$pendingQty} ชิ้น<br>" .
                           "👤 <b>โดย:</b> {$namesHtml}<br>" .
                           "📉 <b>คงเหลือเบิกได้จริง:</b> <span class='text-red-600 font-bold'>{$availableQty} ชิ้น</span>" .
                           "</div>";

                    // Throw structured error for frontend to handle
                    throw new \Exception(json_encode([
                        'html' => $msg,
                        'failed_item' => [
                            'id' => $equipment->id,
                            'name' => $equipment->name,
                            'available_qty' => $availableQty,
                            'requested_qty' => $itemData['quantity']
                        ]
                    ]));
                }

                if ($request->filled('dept_key')) {
                    $currentDeptKey = $request->input('dept_key');
                    if ($equipment->dept_key && $equipment->dept_key !== $currentDeptKey) {
                        throw new \Exception("ไม่สามารถเบิก '{$equipment->name}' ได้ เนื่องจากอยู่คนละแผนก");
                    }
                }

                $this->checkAndEnforceFrozenState($equipment);
                if ($equipment->status === 'frozen') {
                     throw new \Exception("อุปกรณ์ '{$equipment->name}' ถูกระงับ (Frozen)");
                }

                $targetUserId = !empty($itemData['receiver_id']) ? $itemData['receiver_id'] : $loggedInUser->id;
                
                $isSelfWithdrawal = ($targetUserId === $loggedInUser->id);
                $isCompleted = ($canAutoConfirm && $isSelfWithdrawal);

                if ($isCompleted) {
                    $equipment->quantity -= $itemData['quantity'];
                    $equipment->save(); // ✅ Trigger 'saving' event for status update
                }

                // ✅ LOGIC แก้ไข: จัดการ Purpose และ Notes ไม่ให้ซ้ำกัน
                $rawNote = $itemData['notes'] ?? null;
                $purpose = $rawNote;
                $noteToSave = $rawNote;

                // ถ้าเลือก "เบิกใช้งานทั่วไป" (ค่าคือ general_use)
                // - ให้ Purpose = 'general_use'
                // - ให้ Notes = null (เพื่อไม่ให้แสดงคำว่า general_use ซ้ำ)
                if ($rawNote === 'general_use') {
                    $purpose = 'general_use';
                    $noteToSave = null; 
                } elseif (empty($rawNote)) {
                    $purpose = 'general_use'; // กรณีไม่ใส่อะไรมาเลย
                    $noteToSave = '-';
                }

                $transaction = Transaction::create([
                    'user_id' => $targetUserId,
                    'equipment_id' => $equipment->id,
                    'quantity' => $itemData['quantity'], 
                    'quantity_change' => -((int)$itemData['quantity']),
                    'action' => 'withdrawal', 
                    'type' => $equipment->withdrawal_type ?? 'consumable', 
                    'notes' => $noteToSave,   // ✅ บันทึก Notes ที่เคลียร์ค่าแล้ว
                    'purpose' => $purpose,    // ✅ บันทึก Purpose ปกติ
                    'status' => $isCompleted ? 'completed' : 'pending',
                    'transaction_date' => now(),
                    'admin_confirmed_at' => $isCompleted ? now() : null,
                    'user_confirmed_at' => $isCompleted ? now() : null,
                    'confirmed_at' => $isCompleted ? now() : null,
                    'handler_id' => $isCompleted ? $loggedInUser->id : null,
                    'handler_id' => $isCompleted ? $loggedInUser->id : null,
                    'return_condition' => in_array($equipment->withdrawal_type, ['returnable', 'partial_return']) ? 'allowed' : 'not_allowed'
                ]);
                
                $results[] = $transaction;
            }

            DB::commit();
            
            if (count($results) > 0) {
                // ✅ Group ALL Transactions for Notification (Pending + Completed)
                $transactionIds = collect($results)->pluck('id');

                if ($transactionIds->isNotEmpty()) {
                    try {
                        // Fix: Re-query to get Eloquent Collection (so .load() works and relations are fresh)
                        $transactionsToNotify = Transaction::with(['equipment.unit', 'user'])
                                                ->whereIn('id', $transactionIds)
                                                ->get();
                        
                        // 1. Send Single Bulk Notification to Synology
                        (new SynologyService())->notify(new \App\Notifications\BulkEquipmentRequested($transactionsToNotify, $loggedInUser));

                        // 2. Notify Admins (Bell Notification) if there are pending items
                        $hasPending = $transactionsToNotify->contains('status', 'pending');
                        if ($hasPending) {
                            $admins = User::all()->filter(function($user) {
                                return $user->hasPermissionTo('equipment:manage');
                            });

                            if ($admins->isNotEmpty()) {
                                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\BulkEquipmentRequested($transactionsToNotify, $loggedInUser));
                            }
                        }

                    } catch (\Exception $e) { 
                        Log::error("Bulk Notification Error: " . $e->getMessage());
                    }
                }
            }

            return response()->json(['message' => 'บันทึกรายการเบิกแบบกลุ่มเรียบร้อยแล้ว']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // =========================================================================
    // 3. ADMIN & USER ACTIONS
    // =========================================================================

    public function adminConfirmShipment(Request $request, Transaction $transaction)
    {
        $this->authorize('transaction:confirm'); // ✅ Changed from 'equipment:manage' to specific permission
        DB::beginTransaction();
        try {
            if (!in_array($transaction->status, ['pending', 'pending_approval'])) return back()->with('error', 'สถานะไม่ถูกต้อง');
            
            $equipment = Equipment::lockForUpdate()->find($transaction->equipment_id);
            if ($equipment->quantity < abs($transaction->quantity_change)) { DB::rollBack(); return back()->with('error', "สต็อกไม่พอ"); }

            $equipment->decrement('quantity', abs($transaction->quantity_change));
            $transaction->update(['admin_confirmed_at' => now(), 'handler_id' => Auth::id(), 'status' => 'shipped']);
            
            try { 
                // 1. Notify User (Database) -> Shows in Bell
                $transaction->user->notify(new RequestApproved($transaction->load('user', 'equipment')));
                
                // 2. Notify Synology (Chat) -> Shows in Team Channel
                (new SynologyService())->notify(new RequestApproved($transaction)); 
            } catch (\Exception $e) {
                Log::error("Notification Error in adminConfirmShipment: " . $e->getMessage());
            }

            DB::commit();
            return back()->with('success', 'ยืนยันแล้ว');
        } catch (\Exception $e) { DB::rollBack(); return back()->with('error', 'Error: ' . $e->getMessage()); }
    }

    public function userConfirmReceipt(Request $request, Transaction $transaction)
    {
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

                if (Auth::id() !== $transaction->user_id) {
                    $updateData['notes'] = $transaction->notes . "\n[System: Admin " . Auth::user()->fullname . " ยืนยันรับของแทน]";
                }

                $transaction->update($updateData);

                if ($transaction->type === 'return') {
                    Equipment::where('id', $transaction->equipment_id)->increment('quantity', $transaction->quantity_change);
                }

                // 1. Notify Admins (Database)
                $admins = User::all()->filter(function($user) {
                    return $user->hasPermissionTo('transaction:auto_confirm'); // Or 'permission:manage'
                });
                
                if ($admins->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($admins, new UserConfirmedReceipt($transaction));
                }

                // 2. Notify Synology (Chat)
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

            try { (new SynologyService())->notify(new \App\Notifications\ItemWriteOffNotification($transaction->load('equipment', 'user'), Auth::user())); } catch(\Exception $e) {}

            DB::commit();
            return back()->with('success', 'ตัดยอดสำเร็จ');
        } catch(\Exception $e) { DB::rollBack(); return back()->with('error', 'Error'); }
    }

    public function userCancel(Request $request, Transaction $transaction) 
    { 
        $user = Auth::user();
        $isOwner = $user->id === $transaction->user_id;
        // ✅ Use dedicated 'transaction:cancel' permission
        $isAdmin = $user->can('permission:manage') || $user->can('transaction:cancel');

        if (!$isOwner && !$isAdmin) return back()->with('error', 'ไม่มีสิทธิ์');
        if ($transaction->status !== 'pending') return back()->with('error', 'ยกเลิกไม่ได้');

        // ✅ ลบ rating ก่อนยกเลิก (ถ้ามี)
        if ($transaction->rating) {
            $transaction->rating->delete();
        }

        if ($isAdmin && !$isOwner) {
            // Admin Rejecting
            $transaction->update(['status' => 'cancelled', 'notes' => $transaction->notes . "\n[System: ปฏิเสธโดย Admin " . $user->fullname . "]"]);
            try { 
                // Notify User (DB)
                $transaction->user->notify(new \App\Notifications\RequestCancelledByAdmin($transaction, $user)); 
                
                // Notify Synology (Chat)
                (new SynologyService())->notify(new \App\Notifications\RequestCancelledByAdmin($transaction, $user)); 
            } catch(\Exception $e) { 
                Log::error("Notify Cancel Admin Error: " . $e->getMessage()); 
            }
        } else {
            // User Cancelling
            $transaction->update(['status' => 'cancelled', 'notes' => $transaction->notes . "\nยกเลิกโดยผู้ใช้"]);
            try { (new SynologyService())->notify(new RequestCancelledByUser($transaction)); } catch(\Exception $e) {}
        }
        
        return back()->with('success', 'ยกเลิกรายการสำเร็จ'); 
    }

    public function adminCancelTransaction(Request $request, Transaction $transaction) 
    { 
        $this->authorize('transaction:cancel');
        if ($transaction->status !== 'completed') return back()->with('error', 'ต้อง Completed เท่านั้น');
        
        DB::beginTransaction();
        try {
            // ✅ ลบ rating ก่อนยกเลิก (ถ้ามี)
            if ($transaction->rating) {
                $transaction->rating->delete();
            }
            
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
            
            // ✅ Check for pending confirmations FIRST
            if ($pendingError = $this->checkPendingConfirmations($userId)) {
                return response()->json([
                    'blocked' => true,
                    'reason' => 'pending_confirmations',
                    'message' => $pendingError
                ]);
            }
            
            // Then check for unrated transactions
            $unratedTransactions = $this->getUnratedTransactions($userId);

            if ($unratedTransactions->count() > 0) {
                $defaultDeptKey = config('department_stocks.default_nas_dept_key', 'mm');
                $unratedTransactions->transform(function ($tx) use ($defaultDeptKey) {
                    $imgUrl = 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';
                    if ($tx->equipment && $tx->equipment->latestImage) {
                        try {
                            $fname = $tx->equipment->latestImage->file_name;
                            if ($fname && trim($fname) !== '') {
                                $imgUrl = url("nas-images/{$defaultDeptKey}/{$fname}");
                            }
                        } catch (\Exception $e) {}
                    }
                    $tx->equipment_image_url = $imgUrl;
                    return $tx;
                });

                return response()->json([
                    'blocked' => true,
                    'reason' => 'unrated_transactions',
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

    public function rateTransaction(Request $request, Transaction $transaction)
    {
        if (Auth::id() !== $transaction->user_id && !Auth::user()->can('transaction:confirm')) {
            return response()->json(['success' => false, 'message' => 'ไม่มีสิทธิ์ทำรายการนี้'], 403);
        }

        // ✅ ระบบใหม่: รับ feedback_type (good/neutral/bad)
        // ✅ รองรับ Legacy: ถ้ามี q1, q2, q3 ก็ยังใช้ได้
        $validator = Validator::make($request->all(), [
            'feedback_type' => 'nullable|in:good,neutral,bad', // ✅ ระบบใหม่
            'q1' => 'nullable|integer|in:1,2,3', // Legacy
            'q2' => 'nullable|integer|in:1,2,3', // Legacy
            'q3' => 'nullable|integer|in:1,2,3', // Legacy
            'answers' => 'nullable|array', // Legacy Dynamic
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        // ✅ ถ้าไม่มี feedback_type และไม่มี q1 ด้วย = ไม่ได้ส่งอะไรมาเลย
        if (!$request->filled('feedback_type') && !$request->filled('q1')) {
            return response()->json(['success' => false, 'message' => 'กรุณาเลือกประเมิน'], 422);
        }

        DB::beginTransaction();
        try {
            $dataToSave = [
                'equipment_id' => $transaction->equipment_id,
                'user_id' => Auth::id(), // ✅ เพิ่ม user_id
                'feedback_type' => $request->feedback_type,
                'comment' => $request->comment,
                'rated_at' => now(),
            ];

            EquipmentRating::updateOrCreate(
                ['transaction_id' => $transaction->id],
                $dataToSave
            );

            DB::commit();
            return response()->json(['success' => true, 'message' => 'บันทึกการประเมินเรียบร้อยแล้ว']);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Rate Error: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'System Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // 5. USER RETURN REQUEST SYSTEM
    // =========================================================================

    // =========================================================================
    // 5. USER DIRECT RETURN SYSTEM
    // =========================================================================

    public function processDirectReturn(Request $request, Transaction $transaction)
    {
        // 1. Check if feature is enabled
        $isEnabled = \App\Models\Setting::where('key', 'allow_user_return_request')->value('value');
        if (!$isEnabled) {
            return back()->with('error', 'ระบบขอคืนอุปกรณ์ปิดใช้งานอยู่ (ต้องคืนผ่าน Admin เท่านั้น)');
        }

        // 2. Authorization (Must be owner)
        if (Auth::id() !== $transaction->user_id) {
            return back()->with('error', 'ไม่มีสิทธิ์ทำรายการนี้');
        }

        // 3. Validation
        if ($transaction->status !== 'completed') {
            return back()->with('error', 'สถานะไม่ถูกต้อง (ต้องเป็น Completed เท่านั้น)');
        }
        if ($transaction->return_condition !== 'allowed') {
            return back()->with('error', 'รายการนี้ไม่สามารถคืนได้');
        }
        
        $request->validate([
            'return_condition' => 'required|in:good,defective',
            'problem_description' => 'required_if:return_condition,defective|nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $condition = $request->input('return_condition', 'good');
            $problem = $request->input('problem_description', '-');

            // --- Logic from ReturnController@store (Simplified for User) ---
            
            // A. Update Original Transaction
            $quantityToReturn = abs($transaction->quantity_change) - ($transaction->returned_quantity ?? 0);
            if ($quantityToReturn <= 0) {
                return back()->with('error', 'รายการนี้ถูกคืนครบถ้วนหรือตัดยอดไปแล้ว');
            }
            
            $transaction->returned_quantity = ($transaction->returned_quantity ?? 0) + $quantityToReturn;
            if ($transaction->returned_quantity >= abs($transaction->quantity_change)) {
                $transaction->status = 'returned'; 
            }
            $transaction->save();
            
            $equipment = Equipment::lockForUpdate()->find($transaction->equipment_id);
            
            // B. Create Return Transaction History
            $newReturnTxn = Transaction::create([
                'equipment_id' => $equipment->id,
                'user_id' => $transaction->user_id,
                'handler_id' => null, // System / Self
                'type' => 'return',
                'quantity_change' => $quantityToReturn,
                'notes' => "User Returned (Direct). Condition: " . ucfirst($condition),
                'transaction_date' => now(),
                'status' => 'completed',
                'confirmed_at' => now(),
                'user_confirmed_at' => now(),
                'admin_confirmed_at' => now(), // Auto-confirm
            ]);

            // C. Handle Stock based on Condition
            if ($condition === 'defective') {
                // Case: Defective -> Split to Maintenance
                $maintenanceEquipment = $equipment->replicate(['id', 'created_at', 'updated_at']);
                $maintenanceEquipment->quantity = $quantityToReturn;
                $maintenanceEquipment->status = 'maintenance';
                $maintenanceEquipment->notes = "User reported defective. Split from ID: {$equipment->id}. Ref User TXN-{$newReturnTxn->id}";
                $maintenanceEquipment->save();

                // Log Maintenance
                \App\Models\MaintenanceLog::create([
                    'equipment_id' => $maintenanceEquipment->id,
                    'transaction_id' => $newReturnTxn->id,
                    'reported_by_user_id' => Auth::id(),
                    'problem_description' => $problem,
                    'status' => 'pending',
                ]);
                
                // Notify Admin about Repair? Maybe.
            } else {
                // Case: Good -> Restock
                $equipment->increment('quantity', $quantityToReturn);
            }
            $equipment->save();

            DB::commit();
            
            // Notify Admin (Informational)
            try {
                 (new SynologyService())->notify(new \App\Notifications\ItemReturned($newReturnTxn->load('equipment', 'user')));
            } catch (\Exception $e) {}

            return back()->with('success', 'คืนอุปกรณ์สำเร็จ! (ยอดเข้าสต็อก/บันทึกซ่อมเรียบร้อย)');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Direct Return Error: " . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
}