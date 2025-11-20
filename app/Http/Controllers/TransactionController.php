<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request; // ✅ เพิ่ม Request
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // ✅ เพิ่ม Auth
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Notifications\EquipmentRequested;
use App\Notifications\RequestApproved;
use App\Notifications\UserConfirmedReceipt;
use App\Services\SynologyService;
// ✅ เพิ่ม use Carbon
use Carbon\Carbon;
// ✅✅✅ เพิ่ม use GlpiTicket Model ✅✅✅
use App\Models\GlpiTicket;

// ✅✅✅ START: 1. เพิ่ม use statements สำหรับ Notifications ใหม่ ✅✅✅
use App\Notifications\RequestCancelledByUser;
use App\Notifications\RequestCancelledByAdmin;
use App\Notifications\TransactionReversedByAdmin;
// ✅✅✅ END: 1. เพิ่ม use statements ✅✅✅


class TransactionController extends Controller
{
    use AuthorizesRequests;

    // --- (index, show, searchItems - โค้ดส่วนนี้เหมือนเดิม) ---
    public function index(Request $request) // ✅ เพิ่ม Request $request
    {
        try {
            $statusFilter = $request->query('status', 'my_history'); // ✅ เปลี่ยนค่าเริ่มต้นเป็น 'my_history' แทน 'pending_confirmation'
            $query = Transaction::with(['equipment.latestImage', 'user', 'handler']) // Eager load handler
                                ->orderBy('transaction_date', 'desc');

            if ($statusFilter == 'pending_confirmation') {
                // ดึงรายการที่รอผู้ใช้คนนี้ยืนยัน
                $query->where('user_id', Auth::id())
                        ->whereIn('status', ['shipped', 'user_confirm_pending']);
            } elseif ($statusFilter == 'my_history') {
                // ดึงประวัติทั้งหมดของผู้ใช้คนนี้
                $query->where('user_id', Auth::id());
            } elseif ($statusFilter == 'all_history') {
                 // ดึงประวัติทั้งหมด (ต้องมีสิทธิ์ 'report:view')
                $this->authorize('report:view');

                // Apply filters only for 'all_history' tab
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

            $transactions = $query->paginate(15)->appends($request->query()); // Keep query string on pagination

            // Handle AJAX polling for 'all_history' tab
            if ($request->ajax() && $statusFilter == 'all_history') {
                $latestTimestamp = $transactions->isNotEmpty() ? Carbon::parse($transactions->first()->transaction_date)->timestamp : now()->timestamp;
                return response()->json([
                    'view' => view('transactions.partials._table_rows', compact('transactions'))->render(),
                    'pagination' => $transactions->links()->toHtml(),
                    'latest_timestamp' => $latestTimestamp
                ]);
            }

            // Data for filters (only needed if showing 'all_history', but load anyway for simplicity)
            $users = User::orderBy('fullname', 'asc')->get();
            
            // ✅✅✅ START: อัปเดต $types ✅✅✅
            // อัปเดตอาร์เรย์นี้เพื่อรองรับประเภทใหม่ (จาก user) และประเภทเก่า (จาก admin)
            $types = [
                'consumable' => 'เบิก (ไม่ต้องคืน)',
                'returnable' => 'ยืม (ต้องคืน)',
                'partial_return' => 'เบิก (เหลือคืนได้)',
                'withdraw' => 'เบิก (Admin)', // คงไว้สำหรับรายการที่ Admin สร้าง
                'borrow' => 'ยืม (Admin)',   // คงไว้สำหรับรายการที่ Admin สร้าง
                'return' => 'คืน',
                'add' => 'เพิ่ม',
                'adjust' => 'ปรับสต็อก'
            ];
            // ✅✅✅ END: อัปเดต $types ✅✅✅


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
        $transaction->load(['user', 'equipment.latestImage', 'handler', 'glpiTicketRelation']); // Load GLPI ticket relation if exists
        return response()->json(['success' => true, 'data' => $transaction]);
    }

    // ✅ แก้ไข: searchItems (เพิ่ม Rating + สร้าง Image URL จาก Server)
    public function searchItems(Request $request)
    {
        $term = $request->input('q', '');
        $query = Equipment::whereIn('status', ['available', 'low_stock'])
                            ->where('quantity', '>', 0); // Only show items with quantity > 0
        
        // ✅ เพิ่ม: ดึงคะแนนเฉลี่ย (Average Rating)
        try { 
            if (method_exists(Equipment::class, 'transactions')) {
                $query->withAvg('transactions', 'rating');
            }
        } catch (\Throwable $e) { }

        if ($term) {
            $query->where(function($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('serial_number', 'like', "%{$term}%")
                    ->orWhere('part_no', 'like', "%{$term}%"); // Also search part_no
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
                Log::error("Failed NAS route gen: Item {$item->id}: " . $e->getMessage());
                $item->image_url = asset('images/placeholder.webp'); // Fallback
            }
            $item->unit_name = $item->unit->name ?? 'N/A';

            // ✅ เพิ่ม: ส่งค่า Rating กลับไปหน้าบ้าน (ทศนิยม 2 ตำแหน่ง)
            $item->avg_rating = $item->transactions_avg_rating ? number_format($item->transactions_avg_rating, 2) : null;

            return $item;
        });
        return response()->json($items);
    }

    // --- (ฟังก์ชัน storeWithdrawal คงเดิม - ใช้สำหรับ Admin Panel) ---
    public function storeWithdrawal(Request $request)
    {
        Log::debug('===== storeWithdrawal Start =====');
        $this->authorize('equipment:manage');
        Log::debug('[storeWithdrawal] Authorization check passed.');

        $validator = Validator::make($request->all(), [
            'type'             => ['required', Rule::in(['withdraw', 'borrow'])], // ‼️ นี่คือของ Admin (ถูกต้องแล้ว)
            'requestor_name'   => 'required|string|max:255',
            'purpose'          => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer|exists:equipments,id',
            'items.*.quantity' => 'required|integer|min:1',
        ],[
            'items.*.quantity.min' => 'จำนวนต้องไม่น้อยกว่า 1',
            'items.required' => 'กรุณาเลือกรายการอุปกรณ์',
        ]);

        if ($validator->fails()) { /* ... Validation fail handling ... */
            Log::warning('[storeWithdrawal] Validation failed: ', $validator->errors()->toArray());
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }
        Log::debug('[storeWithdrawal] Validation passed.');

        $loggedInUser = Auth::user();
        $canAutoConfirm = $loggedInUser->can('transaction:auto_confirm');
        Log::debug("[storeWithdrawal] Checking 'transaction:auto_confirm' permission for Admin ID: {$loggedInUser->id}. Result: " . ($canAutoConfirm ? 'Yes' : 'No'));

        DB::beginTransaction();
        Log::debug('[storeWithdrawal] DB Transaction started.');
        try {
            $userIdToAssign = $loggedInUser->id;
            $requestorNameInput = $request->input('requestor_name');
            $userFromName = User::where('fullname', $requestorNameInput)->first();

            if ($userFromName) { /* ... Assign userIdToAssign ... */
                $userIdToAssign = $userFromName->id;
                Log::debug("[storeWithdrawal] Requestor '{$requestorNameInput}' found in DB. Assigning TXN to User ID: {$userIdToAssign}");
            } else { Log::warning("[storeWithdrawal] Requestor '{$requestorNameInput}' NOT found in DB. Assigning TXN to Admin ID: {$userIdToAssign}"); } // Modified warning
            $isSelfWithdrawal = ($userIdToAssign === $loggedInUser->id);
            Log::debug("[storeWithdrawal] Checking if it's a self-withdrawal for the admin. Result: " . ($isSelfWithdrawal ? 'Yes' : 'No'));

            $firstTransactionData = null; // For notification

            foreach ($request->items as $itemData) {
                $equipment = Equipment::lockForUpdate()->find($itemData['id']);
                $quantityToWithdraw = (int)$itemData['quantity']; // Cast to int

                if (!$equipment || $equipment->quantity < $quantityToWithdraw) { /* ... Stock check fail ... */
                    DB::rollBack();
                    Log::error("[storeWithdrawal] Insufficient stock or item not found for ID: {$itemData['id']}. Needed: {$quantityToWithdraw}, Available: " . ($equipment->quantity ?? 'N/A'));
                    return response()->json(['success' => false, 'message' => "สต็อกของ " . ($equipment->name ?? "ID: {$itemData['id']}") . " (คงเหลือ: " . ($equipment->quantity ?? 'N/A') . ") ไม่เพียงพอ"], 400);
                }

                // --- ⚠️ START: Process Purpose and GLPI ID (for storeWithdrawal) ---
                $purpose = $request->input('purpose');
                $notes = $request->input('notes');
                $combinedNotes = $notes ?? ''; // Start with notes if provided
                $glpiTicketId = null;
                $glpiSource = null; // To store 'it' or 'en' if applicable
                $purposeForDb = $purpose; // Default to original purpose
                $glpiTicketName = null; // Store ticket name

                if (str_starts_with($purpose, 'glpi-')) {
                    $parts = explode('-', $purpose);
                    if (count($parts) === 3 && is_numeric($parts[2])) {
                        $glpiSource = $parts[1]; // 'it' or 'en'
                        $glpiTicketId = (int) $parts[2];
                        $purposeForDb = 'glpi_ticket'; // Standardize

                        // --- ✅✅✅ START: Query GLPI Ticket Name ✅✅✅ ---
                        try {
                            $glpiConnection = 'glpi_' . $glpiSource; // Determine connection name
                            if (config("database.connections.{$glpiConnection}")) {
                                $glpiTicket = GlpiTicket::on($glpiConnection)->find($glpiTicketId);
                                if ($glpiTicket) {
                                    $glpiTicketName = $glpiTicket->name;
                                }
                            } else {
                                Log::warning("[storeWithdrawal] GLPI connection '{$glpiConnection}' not configured.");
                            }
                        } catch (\Exception $glpiError) {
                            Log::error("[storeWithdrawal] Error fetching GLPI ticket name (ID: {$glpiTicketId}, Source: {$glpiSource}): " . $glpiError->getMessage());
                        }
                        // --- ✅✅✅ END: Query GLPI Ticket Name ✅✅✅ ---

                        // --- 📝 Modify how notes are combined ---
                        $glpiNote = "อ้างอิงใบงาน GLPI ({$glpiSource}): #{$glpiTicketId}";
                        if ($glpiTicketName) {
                            $glpiNote .= " - " . $glpiTicketName; // Append name if found
                        }
                        $combinedNotes = empty($combinedNotes) ? $glpiNote : $glpiNote . "\n" . $combinedNotes;
                        Log::debug("[storeWithdrawal] Extracted GLPI Ticket ID: {$glpiTicketId} (Source: {$glpiSource}, Name: {$glpiTicketName}) for Item ID: {$equipment->id}");
                        // --- 📝 End Modify ---

                    } else {
                        Log::warning("[storeWithdrawal] Invalid GLPI purpose format: '{$purpose}'. Treating as general purpose.");
                        $combinedNotes = empty($combinedNotes) ? "วัตถุประสงค์: " . $purpose : "วัตถุประสงค์: " . $purpose . "\n" . $combinedNotes;
                    }
                } else if (in_array($purpose, ['general_use', 'general_use_1', 'general_use_2', 'general_use_3'])) {
                    // ✅ Map ค่าจาก value เป็นชื่อภาษาไทย
                    $purposeNames = [
                        'general_use'   => 'เบิกใช้งานทั่วไป',
                        'general_use_1' => '1',
                        'general_use_2' => '2',
                        'general_use_3' => '3',
                    ];

                    $purposeForDb = $purpose; // เก็บ key ไว้ใน DB
                    $thaiName = $purposeNames[$purpose] ?? $purpose;

                    $combinedNotes = empty($combinedNotes)
                        ? "วัตถุประสงค์: {$thaiName}"
                        : "วัตถุประสงค์: {$thaiName}\n" . $combinedNotes;
                } else {
                    // Any other purpose string (fallback)
                    $purposeForDb = $purpose;
                    $combinedNotes = empty($combinedNotes)
                        ? "วัตถุประสงค์: " . $purpose
                        : "วัตถุประสงค์: " . $purpose . "\n" . $combinedNotes;
                }
                // --- ⚠️ END: Process Purpose and GLPI ID ---


                $returnCondition = match ($request->type) { /* ... Determine return condition ... */
                    'borrow' => 'allowed',
                    'withdraw' => match ($equipment->withdrawal_type) {
                        'consumable' => 'not_allowed',
                        'partial_return', 'returnable' => 'allowed',
                        default => 'not_allowed',
                    },
                    default => 'not_allowed',
                };
                Log::debug("[storeWithdrawal] Determined return condition for Item ID {$equipment->id}: {$returnCondition}");

                if ($canAutoConfirm && $isSelfWithdrawal) { /* ... Auto-Confirm Logic ... */
                    Log::info("[storeWithdrawal] Applying AUTO-CONFIRM logic for Item ID: {$equipment->id}");
                    $equipment->decrement('quantity', $quantityToWithdraw); // Use decrement
                    $transactionData = [
                        'equipment_id'    => $equipment->id, 'user_id' => $loggedInUser->id, 'handler_id' => $loggedInUser->id,
                        'type' => $request->type,
                        'quantity_change' => -$quantityToWithdraw,
                        'notes' => $combinedNotes, // Use combined notes
                        'purpose' => $purposeForDb, // Use processed purpose
                        'glpi_ticket_id' => $glpiTicketId, // Add GLPI ID (can be null)
                        'transaction_date'=> now(), 'status' => 'completed', 'admin_confirmed_at' => now(), 'user_confirmed_at' => now(),
                        'confirmed_at' => now(), 'return_condition'=> $returnCondition,
                    ];
                    Transaction::create($transactionData);
                    Log::debug("[storeWithdrawal] Created AUTO-CONFIRMED Transaction for Item ID: {$equipment->id}");
                } else { /* ... Normal Logic ... */
                    Log::info("[storeWithdrawal] Applying NORMAL logic (Pending) for Item ID: {$equipment->id}. Reason: " . (!$canAutoConfirm ? 'No AutoConfirm Perm' : 'Not Self Withdrawal'));
                    $transactionData = [
                        'equipment_id'    => $equipment->id, 'user_id' => $userIdToAssign, 'handler_id' => null,
                        'type' => $request->type,
                        'quantity_change' => -$quantityToWithdraw,
                        'notes' => $combinedNotes, // Use combined notes
                        'purpose' => $purposeForDb, // Use processed purpose
                        'glpi_ticket_id' => $glpiTicketId, // Add GLPI ID (can be null)
                        'transaction_date'=> now(), 'status' => 'pending', 'return_condition'=> $returnCondition,
                    ];
                    $transaction = Transaction::create($transactionData);
                    Log::debug("[storeWithdrawal] Created PENDING Transaction ID: {$transaction->id} for Item ID: {$equipment->id}");
                    if (!$firstTransactionData) { $firstTransactionData = $transaction; }
                }
            } // End foreach item

            DB::commit();
            Log::debug('[storeWithdrawal] DB Transaction committed.');

            // Send notification ONLY for normal flow
            if ((!$isSelfWithdrawal || !$canAutoConfirm) && $firstTransactionData) {
                $targetUser = $firstTransactionData->user; // Use the user from the transaction
                if ($targetUser) {
                    Log::info("[storeWithdrawal] Sending EquipmentRequested notification for User ID: {$targetUser->id}, TXN ID: {$firstTransactionData->id}");
                    try {
                        (new SynologyService())->notify(new EquipmentRequested($firstTransactionData->load('equipment', 'user')));
                    } catch (\Exception $e) { Log::error("[storeWithdrawal] Failed to send Synology notification: " . $e->getMessage()); } // Modified error log
                } else { Log::warning("[storeWithdrawal] Target user not found for notification (TXN ID: {$firstTransactionData->id})."); } // Modified warning
            } else { Log::debug("[storeWithdrawal] Skipping notification. isSelfWithdrawal=" . ($isSelfWithdrawal?'true':'false') . ", canAutoConfirm=" . ($canAutoConfirm?'true':'false')); } // Modified debug


            $successMessage = ($canAutoConfirm && $isSelfWithdrawal) ? 'บันทึกและยืนยันรายการเรียบร้อยแล้ว' : 'สร้างรายการเบิก/ยืม สำเร็จ!';
            Log::debug("===== storeWithdrawal End (Success: {$successMessage}) =====");
            return response()->json(['success' => true, 'message' => $successMessage]);

        } catch (\Exception $e) { /* ... Error handling ... */
            DB::rollBack();
            Log::error("[storeWithdrawal] EXCEPTION CAUGHT: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            Log::debug("===== storeWithdrawal End (Error) =====");
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'], 500);
        }
    }

    // ✅✅✅ Updated handleUserTransaction (FIXED + Add GLPI Name + Requestor ID + Rating Block) ✅✅✅
    // นี่คือฟังก์ชันที่รับการเบิก/ยืม/คืนได้ จากหน้า User
    public function handleUserTransaction(Request $request)
    {
        Log::debug('===== handleUserTransaction Start =====');
        $this->authorize('equipment:borrow');
        Log::debug('[handleUserTransaction] Authorization check passed.');

        $loggedInUser = Auth::user();
        $canAutoConfirm = $loggedInUser->can('transaction:auto_confirm');
        Log::debug("[handleUserTransaction] Checking 'transaction:auto_confirm' permission for User ID: {$loggedInUser->id}. Result: " . ($canAutoConfirm ? 'Yes' : 'No'));

        // 🌟 1. Logic บล็อกการเบิก ถ้ามีรายการค้างประเมิน (Server Side Block) 🌟
        $requestorType = $request->input('requestor_type');
        $targetUserId = ($requestorType === 'other' && $request->filled('requestor_id')) 
                        ? (int)$request->input('requestor_id') : $loggedInUser->id;

        $unratedTransactions = $this->getUnratedTransactions($targetUserId);

        if ($unratedTransactions->count() > 0) {
            // ส่ง 403 กลับไป พร้อมข้อมูลรายการที่ค้าง เพื่อให้ JS เปิด Modal
            return response()->json([
                'success' => false,
                'message' => 'คุณมีรายการอุปกรณ์ที่ยังไม่ได้ให้คะแนน กรุณาประเมินความพึงพอใจก่อนทำรายการใหม่',
                'error_code' => 'UNRATED_TRANSACTIONS',
                'unrated_items' => $unratedTransactions
            ], 403);
        }

        // ✅✅✅ FIX: อัปเดต Validation Rule (เพิ่ม requestor_type และ requestor_id) ✅✅✅
        $validator = Validator::make($request->all(), [
            'equipment_id'   => 'required|integer|exists:equipments,id',
            // ยอมรับประเภทใหม่ 3 ประเภทนี้
            'type'           => ['required', Rule::in(['consumable', 'returnable', 'partial_return'])],
            'purpose'        => 'required|string|max:255',
            'notes'          => 'nullable|string',
            'quantity'       => 'required|integer|min:1', // Validate quantity
            // --- เพิ่ม 2 บรรทัดนี้ ---
            'requestor_type' => ['required', Rule::in(['self', 'other'])],
            // ตรวจสอบว่า requestor_id ต้องมี ถ้าเลือก 'other' และต้องมีในตาราง sync_ldap จริง
            // (เราดึง connection 'depart_it_db' และ table 'sync_ldap' มาจาก User Model ที่คุณให้)
            'requestor_id'   => [
                                'nullable', 
                                'required_if:requestor_type,other', 
                                'integer', 
                                Rule::exists('depart_it_db.sync_ldap', 'id') 
                            ], 
        ],[
            'quantity.required' => 'กรุณาระบุจำนวน',
            'quantity.integer' => 'จำนวนต้องเป็นตัวเลข',
            'quantity.min' => 'จำนวนต้องมีค่าอย่างน้อย 1',
            // --- เพิ่ม 2 บรรทัดนี้ ---
            'requestor_id.required_if' => 'กรุณาเลือกชื่อผู้ใช้ที่ต้องการเบิกให้',
            'requestor_id.exists' => 'ผู้ใช้ที่เลือกไม่ถูกต้อง หรือไม่มีอยู่ในระบบ',
        ]);
        // ✅✅✅ END FIX ✅✅✅

        if ($validator->fails()) {
            Log::warning('[handleUserTransaction] Validation failed: ', $validator->errors()->toArray());
            // นี่คือส่วนที่ส่ง 422 Error กลับไป
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }
        Log::debug('[handleUserTransaction] Validation passed.');

        // --- ✅ START: ตรรกะกำหนด User ID ที่จะบันทึก ---
        $userIdToAssign = $targetUserId; // ใช้ค่าที่หาไว้ข้างบนแล้ว
        
        if ($requestorType === 'other') {
            Log::debug("[handleUserTransaction] Request is 'for_other'. Assigning TXN to User ID: {$userIdToAssign}");
        } else {
            Log::debug("[handleUserTransaction] Request is 'for_self'. Assigning TXN to User ID: {$userIdToAssign}");
        }
        // --- ✅ END: ตรรกะกำหนด User ID ---

        DB::beginTransaction();
        Log::debug('[handleUserTransaction] DB Transaction started.');
        try {
            $equipmentId = $request->input('equipment_id');
            $equipment = Equipment::lockForUpdate()->find($equipmentId);
            $transactionType = $request->input('type'); // ‼️ นี่จะได้ค่า 'consumable', 'returnable', 'partial_return'
            $quantityToTransact = (int)$request->input('quantity'); // Read quantity
            Log::debug("[handleUserTransaction] Requested Item ID: {$equipmentId}, Type: {$transactionType}, Qty: {$quantityToTransact}");


            if (!$equipment) {
                DB::rollBack();
                Log::error("[handleUserTransaction] Error: Equipment ID {$equipmentId} not found.");
                return response()->json(['success' => false, 'message' => "ไม่พบข้อมูลอุปกรณ์"], 404);
            }
            // Server-side quantity check
            if ($equipment->quantity < $quantityToTransact) {
                DB::rollBack();
                Log::warning("[handleUserTransaction] Error: Insufficient stock for {$equipment->name} (ID: {$equipment->id}). Needed: {$quantityToTransact}, Available: {$equipment->quantity}");
                // Provide unit name in error message if available
                $unitName = optional($equipment->unit)->name ?? 'ชิ้น';
                return response()->json(['success' => false, 'message' => "สต็อกของ {$equipment->name} (คงเหลือ: {$equipment->quantity}) ไม่เพียงพอสำหรับเบิก {$quantityToTransact} {$unitName}"], 400);
            }
            Log::debug('[handleUserTransaction] Stock Check Passed.');

            // --- ⚠️ START: Process Purpose and GLPI ID (handleUserTransaction) ---
            // (คงตรรกะเดิมจากไฟล์ของคุณไว้)
            $purpose = $request->input('purpose');
            $notes = $request->input('notes');
            $combinedNotes = $notes ?? ''; // Start with notes if provided
            $glpiTicketId = null;
            $glpiSource = null; // To store 'it' or 'en' if applicable
            $purposeForDb = $purpose; // Default to original purpose
            $glpiTicketName = null; // Store ticket name

            // Check if purpose indicates a GLPI ticket
            if (str_starts_with($purpose, 'glpi-')) {
                // Example: glpi-it-123 or glpi-en-456
                $parts = explode('-', $purpose);
                if (count($parts) === 3 && is_numeric($parts[2])) {
                    $glpiSource = $parts[1]; // 'it' or 'en'
                    $glpiTicketId = (int) $parts[2];
                    $purposeForDb = 'glpi_ticket'; // Standardize purpose in DB

                    // --- ✅✅✅ START: Query GLPI Ticket Name ✅✅✅ ---
                    try {
                        $glpiConnection = 'glpi_' . $glpiSource; // Determine connection name
                        if (config("database.connections.{$glpiConnection}")) {
                            $glpiTicket = GlpiTicket::on($glpiConnection)->find($glpiTicketId);
                            if ($glpiTicket) {
                                $glpiTicketName = $glpiTicket->name;
                            }
                        } else {
                            Log::warning("[handleUserTransaction] GLPI connection '{$glpiConnection}' not configured.");
                        }
                    } catch (\Exception $glpiError) {
                        Log::error("[handleUserTransaction] Error fetching GLPI ticket name (ID: {$glpiTicketId}, Source: {$glpiSource}): " . $glpiError->getMessage());
                    }
                    // --- ✅✅✅ END: Query GLPI Ticket Name ✅✅✅ ---

                    // --- 📝 Modify how notes are combined ---
                    $glpiNote = "อ้างอิงใบงาน GLPI ({$glpiSource}): #{$glpiTicketId}";
                    if ($glpiTicketName) {
                        $glpiNote .= " - " . $glpiTicketName; // Append name if found
                    }
                    $combinedNotes = empty($combinedNotes) ? $glpiNote : $glpiNote . "\n" . $combinedNotes;
                    Log::debug("[handleUserTransaction] Extracted GLPI Ticket ID: {$glpiTicketId} (Source: {$glpiSource}, Name: {$glpiTicketName}) for Item ID: {$equipment->id}");
                    // --- 📝 End Modify ---

                } else {
                    // Invalid GLPI format, treat as general purpose
                    $purposeForDb = $purpose; // Keep original purpose string
                    $combinedNotes = empty($combinedNotes) ? "วัตถุประสงค์: " . $purpose : "วัตถุประสงค์: " . $purpose . "\n" . $combinedNotes;
                    Log::warning("[handleUserTransaction] Invalid GLPI purpose format: '{$purpose}'. Treating as general purpose.");
                }
            } else if (in_array($purpose, ['general_use', 'general_use_1', 'general_use_2', 'general_use_3'])) {
                // ✅ Map ค่าจาก value เป็นชื่อภาษาไทย
                $purposeNames = [
                    'general_use'   => 'เบิกใช้งานทั่วไป',
                    'general_use_1' => '1',
                    'general_use_2' => '2',
                    'general_use_3' => '3',
                ];

                $purposeForDb = $purpose; // เก็บ key ไว้ใน DB
                $thaiName = $purposeNames[$purpose] ?? $purpose;

                $combinedNotes = empty($combinedNotes)
                    ? "วัตถุประสงค์: {$thaiName}"
                    : "วัตถุประสงค์: {$thaiName}\n" . $combinedNotes;
            } else {
                // Any other purpose string (fallback)
                $purposeForDb = $purpose;
                $combinedNotes = empty($combinedNotes)
                    ? "วัตถุประสงค์: " . $purpose
                    : "วัตถุประสงค์: " . $purpose . "\n" . $combinedNotes;
            }
            // --- ⚠️ END: Process Purpose and GLPI ID ---


            // ✅✅✅ START: Updated Return Condition Logic ✅✅✅
            // ตรรกะนี้ถูกปรับให้ง่ายขึ้นตาม type ใหม่
            $returnCondition = 'not_allowed';
            if ($transactionType === 'returnable' || $transactionType === 'partial_return') {
                // 'returnable' -> จะไปโผล่ที่ ReturnController (หน้าคืน/แจ้งเสีย)
                // 'partial_return' -> จะไปโผล่ที่ ConsumableReturnController (หน้ารับคืนพัสดุ)
                $returnCondition = 'allowed';
            }
            // ถ้า $transactionType === 'consumable', $returnCondition จะยังคงเป็น 'not_allowed'
            // ✅✅✅ END: Updated Return Condition Logic ✅✅✅
            Log::debug("[handleUserTransaction] Determined return condition for Item ID {$equipment->id} based on type '{$transactionType}': {$returnCondition}");

            //
            // 📍 (แก้ไข) 📍
            // ย้าย $transaction ออกมานอก if/else
            //
            $transaction = null; 

            if ($canAutoConfirm) {
                Log::info("[handleUserTransaction] Applying AUTO-CONFIRM logic for Item ID: {$equipment->id}");
                $equipment->decrement('quantity', $quantityToTransact); // Use requested quantity
                $transactionData = [
                    'equipment_id'    => $equipment->id, 
                    'user_id' => $userIdToAssign, // ✅✅✅ ใช้ User ID ที่กำหนดใหม่
                    'handler_id' => $loggedInUser->id, // Handler คือคนที่กดยืนยัน (ในเคสนี้คือคนทำ Auto-Confirm)
                    'type' => $transactionType, // ‼️ บันทึก type ใหม่
                    'quantity_change' => -$quantityToTransact, // Use requested quantity (negative)
                    'notes' => $combinedNotes, // Use combined notes
                    'purpose' => $purposeForDb, // Use processed purpose
                    'glpi_ticket_id' => $glpiTicketId, // Add GLPI ID (can be null)
                    'transaction_date'=> now(), 'status' => 'completed', 'admin_confirmed_at' => now(), 'user_confirmed_at' => now(),
                    'confirmed_at' => now(), 'return_condition'=> $returnCondition, // ‼️ บันทึก return_condition ใหม่
                ];
                
                // 📍 (แก้ไข) 📍
                $transaction = Transaction::create($transactionData); // สร้างและเก็บค่า
                
                Log::debug("[handleUserTransaction] Created AUTO-CONFIRMED Transaction ID: {$transaction->id} for Item ID: {$equipment->id}");
                $successMessage = 'บันทึกและยืนยันรายการเรียบร้อยแล้ว';
            } else {
                Log::info("[handleUserTransaction] Applying NORMAL logic (Pending) for Item ID: {$equipment->id}. Reason: No AutoConfirm Perm");
                $transactionData = [
                    'equipment_id'    => $equipment->id, 
                    'user_id' => $userIdToAssign, // ✅✅✅ ใช้ User ID ที่กำหนดใหม่
                    'handler_id' => null, // ‼️ รอ Admin มา Confirm
                    'type' => $transactionType, // ‼️ บันทึก type ใหม่
                    'quantity_change' => -$quantityToTransact, // Use requested quantity (negative)
                    'notes' => $combinedNotes, // Use combined notes
                    'purpose' => $purposeForDb, // Use processed purpose
                    'glpi_ticket_id' => $glpiTicketId, // Add GLPI ID (can be null)
                    'transaction_date'=> now(), 'status' => 'pending', // ‼️ สถานะเริ่มต้นที่ถูกต้อง
                    'return_condition'=> $returnCondition, // ‼️ บันทึก return_condition ใหม่
                ];
                
                // 📍 (แก้ไข) 📍
                $transaction = Transaction::create($transactionData); // สร้างและเก็บค่า
                
                Log::debug("[handleUserTransaction] Created PENDING Transaction ID: {$transaction->id} for Item ID: {$equipment->id}");
                $successMessage = 'ส่งคำขอสำเร็จ! กรุณารอ Admin ยืนยันการจัดส่ง';
            }

            DB::commit();
            Log::debug('[handleUserTransaction] DB Transaction committed.');

            // 
            // 📍 (แก้ไข) 📍
            // ย้าย Notification มาไว้ "หลัง" commit และ "นอก" if/else
            // เพื่อให้มันส่ง "ทุกครั้ง"
            //

            // ✅✅✅ START: (แก้ไข) ตรรกะการส่ง Notification ✅✅✅
            // เราจะส่ง Notification (แจ้งเตือน Admin คนอื่น) ก็ต่อเมื่อ:
            // 1. เป็นการเบิกให้คนอื่น ($requestorType === 'other')
            // 2. หรือ ผู้กด *ไม่มี* สิทธิ์ Auto-Confirm (ซึ่งรายการจะไปค้างที่ Pending)
            // 
            // ❌ เราจะไม่ส่ง Notification ❌
            // ถ้าผู้กดมีสิทธิ์ Auto-Confirm และ เบิกให้ตัวเอง ($requestorType === 'self')
            
            if ($requestorType === 'other' || !$canAutoConfirm) {
                try {
                    Log::info("[handleUserTransaction] Sending EquipmentRequested notification for TXN ID: {$transaction->id}. Reason: (requestor_type: {$requestorType}, canAutoConfirm: ".($canAutoConfirm ? 'true' : 'false').")");
                    // โหลด relationship ที่จำเป็น (user = ผู้รับ, equipment = ของ)
                    $transaction->load('equipment','user'); 
                    // $loggedInUser คือผู้กด (Admin)
                    (new SynologyService())->notify(new EquipmentRequested($transaction, $loggedInUser));
                } catch (\Exception $e) { 
                    Log::error("[handleUserTransaction] Failed to send Synology notification: " . $e->getMessage()); 
                }
            } else {
                // (นี่คือกรณี Admin กด Auto-Confirm ให้ตัวเอง -> ไม่ต้องแจ้งเตือน)
                Log::debug("[handleUserTransaction] Skipping notification for TXN ID: {$transaction->id}. Reason: Auto-Confirmed Self-Withdrawal.");
            }
            // ✅✅✅ END: (แก้ไข) ตรรกะการส่ง Notification ✅✅✅


            Log::debug("===== handleUserTransaction End (Success: {$successMessage}) =====");
            // Return JSON response for AJAX
            return response()->json(['success' => true, 'message' => $successMessage]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[handleUserTransaction] EXCEPTION CAUGHT: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            Log::debug("===== handleUserTransaction End (Error) =====");
            // Return JSON response for AJAX
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: '. $e->getMessage()], 500);
        }
    }
    // ✅✅✅ END: Updated handleUserTransaction ✅✅✅


    // --- (ฟังก์ชัน adminConfirmShipment คงเดิม) ---
    public function adminConfirmShipment(Request $request, Transaction $transaction)
    {
        $this->authorize('equipment:manage');
        DB::beginTransaction();
        try {
            // ✅✅✅ แก้ไข: เปลี่ยนสถานะ 'pending' เป็น 'pending_approval' ให้ตรงกับที่ handleUserTransaction สร้าง
            if ($transaction->status !== 'pending' && $transaction->status !== 'pending_approval') { 
                /* ... validation ... */ 
                return back()->with('error', 'รายการนี้ไม่ได้อยู่ในสถานะรอจัดส่ง (Status: ' . $transaction->status . ')'); 
            }
            
            $equipment = Equipment::lockForUpdate()->find($transaction->equipment_id);
            if (!$equipment) { /* ... validation ... */ DB::rollBack(); Log::error("[adminConfirmShipment] Equipment ID {$transaction->equipment_id} not found for TXN ID {$transaction->id}"); return back()->with('error', "ไม่พบข้อมูลอุปกรณ์..."); } // Modified error
            // Use abs() to ensure positive quantity for comparison
            if ($equipment->quantity < abs($transaction->quantity_change)) {
                DB::rollBack();
                // Add unit name to error message
                $unitName = optional($equipment->unit)->name ?? 'ชิ้น';
                return back()->with('error', "สต็อกของ {$equipment->name} (คงเหลือ: {$equipment->quantity}) ไม่เพียงพอสำหรับเบิก " . abs($transaction->quantity_change) . " {$unitName}");
            }

            // Decrement using the absolute value from the transaction
            $equipment->decrement('quantity', abs($transaction->quantity_change));
            $transaction->admin_confirmed_at = now();
            $transaction->handler_id = Auth::id();
            $transaction->status = 'shipped'; // ‼️ สถานะ: จัดส่งแล้ว (รอผู้ใช้กดยืนยัน)
            $transaction->save();

            try {
                Log::info("[adminConfirmShipment] Sending RequestApproved notification for User ID: {$transaction->user_id}, TXN ID: {$transaction->id}");
                $transaction->loadMissing(['user', 'equipment', 'handler']);
                if($transaction->user){
                    (new SynologyService())->notify(new RequestApproved($transaction));
                    Log::info("[adminConfirmShipment] RequestApproved Notification dispatched for TXN ID: {$transaction->id}");
                } else { Log::warning("[adminConfirmShipment] User relationship not loaded for TXN ID: {$transaction->id}. Cannot send notification."); }
            } catch (\Exception $e) { Log::error("[adminConfirmShipment] FATAL ERROR during RequestApproved notification: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine(), ['exception' => $e]); }

            DB::commit();
            return back()->with('success', 'ยืนยันการจัดส่งเรียบร้อยแล้ว');

        } catch (\Exception $e) { /* ... Error handling ... */
            DB::rollBack();
            Log::error("Admin Confirm Shipment Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return back()->with('error', 'เกิดข้อผิดพลาดในการยืนยัน: ' . $e->getMessage());
        }
    }

    // --- (ฟังก์ชัน userConfirmReceipt คงเดิม) ---
    public function userConfirmReceipt(Request $request, Transaction $transaction)
    {
        $user = Auth::user();
        if ($user->id !== $transaction->user_id && !$user->can('permission:manage')) { /* ... validation ... */ return back()->with('error', 'คุณไม่มีสิทธิ์ยืนยันรายการนี้'); }

        if ($transaction->status === 'shipped' || $transaction->status === 'user_confirm_pending') {
            DB::beginTransaction();
            try {
                if (is_null($transaction->handler_id)) {
                    Log::info("[userConfirmReceipt] TXN ID {$transaction->id} had NULL handler_id. Setting handler to confirmer ID: {$user->id}");
                    $transaction->handler_id = $user->id;
                }

                $transaction->user_confirmed_at = now();
                $transaction->confirmed_at = now(); // Also set the final confirmation time
                $transaction->status = 'completed'; // ‼️ สถานะ: เสร็จสมบูรณ์
                $transaction->save();

                if ($transaction->type === 'return') { /* ... stock return logic ... */
                    $equipment = Equipment::lockForUpdate()->find($transaction->equipment_id);
                    if ($equipment) {
                        // Use increment with the (positive) quantity_change from the return transaction
                        $equipment->increment('quantity', $transaction->quantity_change);
                    } else { Log::error("[userConfirmReceipt] Equipment ID {$transaction->equipment_id} not found for return TXN ID {$transaction->id}"); }
                }

                try {
                    $transaction->loadMissing('handler');
                    if($transaction->handler) {
                        Log::info("[userConfirmReceipt] Sending UserConfirmedReceipt notification for Handler ID: {$transaction->handler_id}, TXN ID: {$transaction->id}");
                        (new SynologyService())->notify(new UserConfirmedReceipt($transaction->load('equipment', 'user', 'handler')));
                        Log::info("[userConfirmReceipt] UserConfirmedReceipt Notification dispatched for TXN ID: {$transaction->id}");
                    } else { Log::warning("[userConfirmReceipt] Handler not loaded or NULL for TXN ID: {$transaction->id}. Cannot send notification."); }
                } catch (\Exception $e) { Log::error("[userConfirmReceipt] FATAL ERROR during UserConfirmedReceipt notification: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine(), ['exception' => $e]); }

                DB::commit();
                return back()->with('success', 'ยืนยันการรับ/คืน อุปกรณ์เรียบร้อยแล้ว');
            } catch (\Exception $e) { /* ... Error handling ... */
                DB::rollBack();
                Log::error('Error confirming receipt: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                return back()->with('error', 'เกิดข้อผิดพลาดในการยืนยัน: ' . $e->getMessage());
            }
        }

        return back()->with('error', 'ไม่สามารถยืนยันรายการนี้ได้ (สถานะปัจจุบัน: ' . $transaction->status . ')');
    }

    // --- (checkUnconfirmed, confirmAllMyPickups - Commented Out) ---
    /*
    public function checkUnconfirmed(Request $request) { ... }
    public function confirmAllMyPickups(Request $request) { ... }
    */

    // --- (ฟังก์ชัน getLatestTimestamp คงเดิม) ---
    public function getLatestTimestamp()
    {
        $latestTimestamp = Transaction::max('transaction_date');
        if (is_null($latestTimestamp)) { return response()->json(['latest_timestamp' => now()->timestamp]); }
        try {
            return response()->json(['latest_timestamp' => Carbon::parse($latestTimestamp)->timestamp]);
        } catch (\Exception $e) {
            Log::error("Error parsing latest transaction timestamp '{$latestTimestamp}': " . $e->getMessage());
            return response()->json(['latest_timestamp' => now()->timestamp]); // Fallback
        }
    }

    // --- (ฟังก์ชัน writeOff คงเดิม) ---
    public function writeOff(Request $request, Transaction $transaction)
    {
        $this->authorize('permission:manage');
        DB::beginTransaction();
        try {
            $remaining = 0;
            // Calculate remaining ONLY if it was allowed to be returned in the first place
            if (in_array($transaction->type, ['borrow', 'borrow_temporary', 'returnable', 'partial_return', 'withdraw']) && $transaction->return_condition === 'allowed') { // ✅ เพิ่ม type ใหม่
                $remaining = abs($transaction->quantity_change) - ($transaction->returned_quantity ?? 0);
            }

            if ($remaining <= 0) { /* ... Handle already closed/no remaining ... */
                if ($transaction->status !== 'closed') { $transaction->status = 'closed'; $transaction->save(); DB::commit(); return back()->with('warning', 'รายการนี้ไม่มียอดค้าง แต่สถานะถูกปรับเป็นปิดแล้ว'); }
                return back()->with('error', 'รายการนี้ไม่มียอดค้างให้ตัด หรือไม่สามารถตัดยอดได้');
            }

            // Mark the original transaction as fully returned/closed
            $transaction->returned_quantity = abs($transaction->quantity_change);
            $transaction->status = 'closed';
            $transaction->save();

            // Create a corresponding 'adjust' transaction for record keeping
            $userNameForNote = $transaction->user ? $transaction->user->fullname : 'N/A';
            Transaction::create([
                'equipment_id'    => $transaction->equipment_id,
                'user_id'         => Auth::id(), 'handler_id' => Auth::id(),
                'type'            => 'adjust', 'quantity_change' => 0, // No actual stock change here
                'notes'           => "Admin ตัดยอดค้างคืน/สูญหาย จำนวน {$remaining} ชิ้น จาก #TXN-{$transaction->id} (User: {$userNameForNote})",
                'transaction_date'=> now(), 'status' => 'completed',
                'admin_confirmed_at' => now(), 'user_confirmed_at' => now(), 'confirmed_at' => now(),
            ]);

            DB::commit();
            return back()->with('success', "ตัดยอดรายการค้าง {$remaining} ชิ้น สำเร็จ (TXN#{$transaction->id})");

        } catch (\Exception $e) { /* ... Error handling ... */
            DB::rollBack();
            Log::error("Write Off Error for TXN #{$transaction->id}: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return back()->with('error', 'เกิดข้อผิดพลาดร้ายแรงในการตัดยอด');
        }
    }

    // ✅✅✅ START: 2. อัปเดตฟังก์ชันนี้ (userCancel) ✅✅✅
    /**
     * อนุญาตให้ผู้ใช้ยกเลิกคำขอเบิกของตนเอง
     * (ทำได้เฉพาะเมื่อสถานะเป็น 'pending' เท่านั้น)
     * 🌟 (อัปเดต) ตอนนี้อนุญาตให้ Admin (permission:manage) ยกเลิกได้ด้วย 🌟
     */
    public function userCancel(Request $request, Transaction $transaction)
    {
        $user = Auth::user();

        // 1. ตรวจสอบสิทธิ์: 🌟 CHANGED 🌟
        // ต้องเป็นเจ้าของรายการ หรือ เป็น Admin (permission:manage)
        if ($user->id !== $transaction->user_id && !$user->can('permission:manage')) {
            Log::warning("[userCancel] FAILED: User ID {$user->id} พยายามยกเลิก TXN ID {$transaction->id} (Permission Denied)");
            return back()->with('error', 'คุณไม่มีสิทธิ์ยกเลิกรายการนี้');
        }

        // 2. ตรวจสอบสถานะ: ต้องเป็น 'pending' เท่านั้น
        if ($transaction->status !== 'pending') {
            Log::warning("[userCancel] FAILED: User ID {$user->id} พยายามยกเลิก TXN ID {$transaction->id} ที่มีสถานะ '{$transaction->status}'");
            return back()->with('error', 'ไม่สามารถยกเลิกรายการที่กำลังดำเนินการหรือเสร็จสมบูรณ์แล้วได้');
        }

        // (เพิ่มการตรวจสอบ)
        $isOwner = ($user->id === $transaction->user_id);

        // 3. อัปเดตสถานะ
        DB::beginTransaction();
        try {
            $transaction->status = 'cancelled'; // 🌟 เปลี่ยนสถานะเป็น 'cancelled'
            
            // 🌟 CHANGED 🌟
            // (Optional) เพิ่มโน้ตไว้เป็นหลักฐานว่าใครกดยกเลิก
            $cancellerName = $user->fullname;
            // ตรวจสอบว่าคนกดยกเลิกคือเจ้าของ หรือเป็น Admin ที่มากดยกเลิกแทน
            $cancellerRole = $isOwner ? "โดยผู้ใช้" : "โดย Admin";
            
            $transaction->notes = ($transaction->notes ?? '') . "\n--- ยกเลิก ({$cancellerRole}: {$cancellerName}) เมื่อ " . now()->format('Y-m-d H:i') . " ---";
            $transaction->save();
            
            DB::commit();
            Log::info("[userCancel] SUCCESS: User ID {$user->id} ({$cancellerRole}) ยกเลิก TXN ID {$transaction->id} สำเร็จ");

            // ✅✅✅ START: 3. (แก้ไขกลับ) ใช้ Notification Class ที่ถูกต้อง ✅✅✅
            try {
                $transaction->loadMissing(['user', 'equipment']);
                $canceller = $user; // คนที่กดยกเลิก

                if ($isOwner) {
                    // (User ยกเลิกเอง) -> แจ้งเตือน Admin (Service จะจัดการเอง)
                    Log::info("[userCancel] Notifying (via Service) using 'RequestCancelledByUser' for TXN ID {$transaction->id}");
                    
                    // 🌟🌟🌟 (แก้ไขกลับ) 🌟🌟🌟
                    (new SynologyService())->notify(new RequestCancelledByUser($transaction));
                    
                } else {
                    // (Admin ยกเลิก) -> แจ้งเตือน User เจ้าของ (Service จะจัดการเอง)
                    Log::info("[userCancel] Notifying (via Service) using 'RequestCancelledByAdmin' for TXN ID {$transaction->id}");
                    
                    // 🌟🌟🌟 (แก้ไขกลับ) 🌟🌟🌟
                    (new SynologyService())->notify(new RequestCancelledByAdmin($transaction, $canceller));
                }
            } catch (\Exception $e) {
                Log::error("[userCancel] Synology Notification FAILED for TXN ID {$transaction->id}: " . $e->getMessage());
                // (ไม่ต้อง Rollback แค่ Log error ไว้)
            }
            // ✅✅✅ END: 3. (แก้ไขกลับ) ✅✅✅

            return back()->with('success', 'ยกเลิกรายการเบิกเรียบร้อยแล้ว');

        } catch (\Throwable $e) { // เปลี่ยนเป็น Throwable ตามคำแนะนำก่อนหน้า
            DB::rollBack();
            Log::error("[userCancel] EXCEPTION CAUGHT for TXN ID {$transaction->id}: " . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการยกเลิก: ' . $e->getMessage());
        }
    }
    // ✅✅✅ END: 2. อัปเดตฟังก์ชันนี้ (userCancel) ✅✅✅

    // 🌟🌟🌟 START: 2. อัปเดตฟังก์ชันนี้ (adminCancelTransaction) 🌟🌟🌟
    /**
     * อนุญาตให้ Admin (permission:manage) ยกเลิกรายการที่ 'completed'
     * (เช่น รายการที่เกิดจาก Auto-Confirm)
     * นี่จะเป็นการ "คืนสต็อก" กลับเข้าคลัง
     */
    public function adminCancelTransaction(Request $request, Transaction $transaction)
    {
        // 1. ตรวจสอบสิทธิ์: ต้องเป็น Admin
        $this->authorize('permission:manage');
        $adminUser = Auth::user();

        // 2. ตรวจสอบสถานะ: ต้องเป็น 'completed'
        if ($transaction->status !== 'completed') {
            Log::warning("[adminCancel] FAILED: Admin ID {$adminUser->id} พยายามยกเลิก TXN ID {$transaction->id} ที่มีสถานะ '{$transaction->status}'");
            return back()->with('error', 'ไม่สามารถยกเลิกรายการที่ยังไม่เสร็จสมบูรณ์ (ต้องมีสถานะ Completed)');
        }

        // 🌟🌟🌟 START: 2.5 ตรวจสอบอายุรายการ (เพิ่มเข้ามาใหม่) 🌟🌟🌟
        // (ป้องกันการยกเลิกรายการที่เก่าเกิน 24 ชั่วโมง)
        // เราจะใช้ 'confirmed_at' ซึ่งเป็นเวลาที่รายการเสร็จสมบูรณ์
        if (empty($transaction->confirmed_at)) {
             Log::error("[adminCancel] FAILED: Admin ID {$adminUser->id} พยายามยกเลิก TXN ID {$transaction->id} แต่ไม่มีข้อมูล confirmed_at");
             return back()->with('error', 'ไม่สามารถยกเลิกรายการได้: ไม่พบข้อมูลเวลายืนยัน');
        }
        
        $transactionAgeHours = Carbon::parse($transaction->confirmed_at)->diffInHours(now());
        
        if ($transactionAgeHours > 24) {
            Log::warning("[adminCancel] FAILED: Admin ID {$adminUser->id} พยายามยกเลิก TXN ID {$transaction->id} (Age: {$transactionAgeHours} hours > 24)");
            return back()->with('error', "ไม่สามารถยกเลิกรายการที่เสร็จสมบูรณ์นานกว่า 24 ชั่วโมงได้ (ปัจจุบัน: {$transactionAgeHours} ชม.)");
        }
        // 🌟🌟🌟 END: 2.5 ตรวจสอบอายุรายการ 🌟🌟🌟

        // 3. ตรวจสอบว่าเป็นรายการเบิก/ยืม (มี quantity_change เป็นลบ)
        if ($transaction->quantity_change >= 0) {
             Log::warning("[adminCancel] FAILED: Admin ID {$adminUser->id} พยายามยกเลิก TXN ID {$transaction->id} (Type: {$transaction->type}, QtyChange: {$transaction->quantity_change})");
             return back()->with('error', 'ไม่สามารถยกเลิกรายการประเภทนี้ได้ (ไม่ใช่การเบิก/ยืม)');
        }
         
        // 4. ตรวจสอบว่ามีการคืนของมาบ้างหรือยัง (ป้องกันการคืนสต็อกซ้ำซ้อน)
        if (isset($transaction->returned_quantity) && $transaction->returned_quantity > 0) {
             Log::warning("[adminCancel] FAILED: Admin ID {$adminUser->id} พยายามยกเลิก TXN ID {$transaction->id} (Returned Qty: {$transaction->returned_quantity})");
             return back()->with('error', 'ไม่สามารถยกเลิกรายการนี้ได้ เนื่องจากมีการคืนของเข้ามาบางส่วนแล้ว');
        }

        DB::beginTransaction();
        try {
            // 5. 🌟 คืนสต็อก (สำคัญมาก) 🌟
            $equipment = Equipment::lockForUpdate()->find($transaction->equipment_id);
            if (!$equipment) {
                DB::rollBack();
                Log::error("[adminCancel] FAILED: Equipment ID {$transaction->equipment_id} not found for TXN ID {$transaction->id}");
                return back()->with('error', 'ไม่พบอุปกรณ์ที่เกี่ยวข้อง (ID: ' . $transaction->equipment_id . ')');
            }
            
            // คืนสต็อกกลับไป (quantity_change เป็นลบ, abs() จะได้ค่าบวก)
            $quantityToReturn = abs($transaction->quantity_change);
            $equipment->increment('quantity', $quantityToReturn);
            Log::info("[adminCancel] Stock returned for Equipment ID {$equipment->id}. Quantity increased by {$quantityToReturn}.");

            // 6. อัปเดตสถานะ Transaction
            $transaction->status = 'cancelled'; // ใช้สถานะเดิม
            
            // 7. เพิ่มโน้ต
            $transaction->notes = ($transaction->notes ?? '') . "\n--- ⚠️ ยกเลิกโดย Admin (Auto-Confirm Reversal) โดย: {$adminUser->fullname} เมื่อ " . now()->format('Y-m-d H:i') . " ---";
            $transaction->save();
            
            DB::commit();
            Log::info("[adminCancel] SUCCESS: Admin ID {$adminUser->id} ยกเลิก TXN ID {$transaction->id} (Completed) สำเร็จ");

            // ✅✅✅ START: 3. (แก้ไขกลับ) ใช้ Notification Class ที่ถูกต้อง ✅✅✅
            try {
                $transaction->loadMissing(['user', 'equipment']);
                $canceller = $adminUser; // Admin ที่กดยกเลิก

                // (Admin Reversal) -> ควรจะแจ้งเตือน User เจ้าของ
                Log::info("[adminCancel] Notifying (via Service) using 'TransactionReversedByAdmin' for TXN ID {$transaction->id}");
                
                // 🌟🌟🌟 (แก้ไขกลับ) 🌟🌟🌟
                (new SynologyService())->notify(new TransactionReversedByAdmin($transaction, $canceller));

            } catch (\Exception $e) {
                Log::error("[adminCancel] Synology Notification FAILED for TXN ID {$transaction->id}: " . $e->getMessage());
                // (ไม่ต้อง Rollback แค่ Log error ไว้)
            }
            // ✅✅✅ END: 3. (แก้ไขกลับ) ✅✅✅

            return back()->with('success', 'ยกเลิกรายการ (Completed) และคืนสต็อกเรียบร้อยแล้ว');

        } catch (\Throwable $e) { // เปลี่ยนเป็น Throwable
            DB::rollBack();
            Log::error("[adminCancel] EXCEPTION CAUGHT for TXN ID {$transaction->id}: " . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการยกเลิก: ' . $e->getMessage());
        }
    }
    // 🌟🌟🌟 END: 2. อัปเดตฟังก์ชันนี้ (adminCancelTransaction) 🌟🌟🌟

    // ✅✅✅ Helper & API for Rating (เพิ่มใหม่ท้ายไฟล์) ✅✅✅

    // (เพิ่มใหม่) API เช็คสถานะก่อนกดเบิก
    public function checkBlockStatus(Request $request)
    {
        $userId = Auth::id();
        $unratedTransactions = $this->getUnratedTransactions($userId);

        if ($unratedTransactions->count() > 0) {
            return response()->json([
                'blocked' => true,
                'message' => 'มีรายการค้างประเมิน',
                'unrated_items' => $unratedTransactions
            ]);
        }
        return response()->json(['blocked' => false]);
    }

    private function getUnratedTransactions($userId)
    {
        // ดึงรายการที่ค้าง
        $items = Transaction::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereIn('type', ['consumable', 'returnable', 'partial_return'])
            ->whereNull('rating')
            ->orderBy('transaction_date', 'desc')
            ->with(['equipment.latestImage'])
            ->get();

        // ✅ Fix Image URL (สร้าง Full URL จาก Backend เลย)
        $defaultDeptKey = config('department_stocks.default_nas_dept_key', 'mm');
        $items->transform(function ($tx) use ($defaultDeptKey) {
            if ($tx->equipment) {
                $imgName = $tx->equipment->latestImage ? $tx->equipment->latestImage->file_name : null;
                $tx->equipment->image_url = $imgName ? route('nas.image', ['deptKey' => $defaultDeptKey, 'filename' => $imgName]) : asset('images/placeholder.webp');
            }
            return $tx;
        });
        
        return $items;
    }

    public function rateTransaction(Request $request, Transaction $transaction)
    {
        // เช็คสิทธิ์
        if (Auth::id() !== $transaction->user_id) return response()->json(['success' => false, 'message' => 'No Permission'], 403);
        if ($transaction->status !== 'completed' || !is_null($transaction->rating)) return response()->json(['success' => false, 'message' => 'Cannot rate'], 400);

        // Validation (เช็คว่ามีค่าส่งมาจริงไหม)
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        DB::beginTransaction();
        try {
            // บันทึกข้อมูล
            $transaction->rating = $request->input('rating');
            $transaction->rating_comment = $request->input('rating_comment');
            $transaction->rated_at = now();
            $transaction->save();
            
            DB::commit();
            Log::info("[RateTransaction] Success - Rating saved: " . $transaction->rating);

            $remainingCount = $this->getUnratedTransactions(Auth::id())->count();
            return response()->json(['success' => true, 'message' => 'บันทึกคะแนนแล้ว', 'remaining_count' => $remainingCount]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[RateTransaction] Error saving: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Save failed'], 500);
        }
    }
} // <-- End Class