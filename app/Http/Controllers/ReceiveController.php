<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder; // Uses default connection
use App\Models\PurchaseOrderItem; // Uses default connection
use App\Models\Equipment; // Uses default connection
use App\Models\Transaction; // Uses default connection
use Illuminate\Support\Facades\DB; // Uses default connection facade
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config; // Keep for reading dept name if needed

class ReceiveController extends Controller
{
    use AuthorizesRequests;

    // No DB switching logic is needed for the "clone" approach.
    // All operations happen on the default connection defined in the .env file of the specific app instance.

    /**
     * Display the receiving page for the current department.
     * Fetches POs from THIS DEPARTMENT'S DB (defined in .env).
     */
    public function index()
    {
        // Check if the user has permission to view the receive page
        $this->authorize('receive:view');

        try {
            // Fetch Purchase Orders from the LOCAL DB (default connection)
            $purchaseOrders = PurchaseOrder::with([ // Uses default connection
                'items' => function ($query) {
                    // Load only items that still need receiving
                    // (quantity_received is null or less than quantity_ordered)
                    $query->where(function ($q) {
                        $q->whereNull('quantity_received')
                          ->orWhereRaw('quantity_received < quantity_ordered');
                    })
                    ->with('equipment:id,name') // Eager load equipment details (from the same local DB)
                    ->orderBy('item_description'); // Order items alphabetically
                },
                'orderedBy' // Eager load the user who ordered (from the central user DB via User model V3)
            ])
            // Fetch POs that are potentially ready for receiving
            ->whereIn('status', ['shipped_from_supplier', 'partial_receive', 'pending']) // Include 'pending' if PU might not send notification
            ->whereHas('items', function ($query) { // Ensure the PO actually has items needing receiving
                $query->where(function ($q) {
                    $q->whereNull('quantity_received')
                      ->orWhereRaw('quantity_received < quantity_ordered');
                });
            })
            ->orderBy('created_at', 'desc') // Show newest POs first
            ->get();

             // Get current department name for display purposes (Optional)
             // Reads APP_DEPT_KEY from .env via config/app.php
             $currentDeptKey = Config::get('app.dept_key', 'it');
             // Reads department details from config/department_stocks.php
             $departmentsConfig = Config::get('department_stocks.departments', []);
             $currentDeptName = $departmentsConfig[$currentDeptKey]['name'] ?? strtoupper($currentDeptKey); // Fallback to key if name not found


            // Pass the data to the view
            return view('receive.index', compact('purchaseOrders', 'currentDeptName'));

        } catch (\Exception $e) {
            // Log any errors encountered during data fetching
            Log::error("[ReceiveController::index] Error loading Receive Index page: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // Redirect back or to dashboard with an error message
            return redirect()->route('dashboard')->with('error', 'ไม่สามารถโหลดหน้ารับเข้าได้: ' . $e->getMessage());
        }
    }

    /**
     * Process the receiving form submission for the current department.
     * All database operations happen on the LOCAL DB (default connection defined in .env).
     */
    public function process(Request $request)
    {
        // Check if the user has permission to manage receiving
        $this->authorize('receive:manage');

        // 1. Validate the incoming form data
        $validator = Validator::make($request->all(), [
            // Expect an array named 'items', with PO Item IDs as keys
            'items' => 'required|array|min:1',
            // For each item, expect the quantity being received now
            'items.*.receive_now_quantity' => 'nullable|integer|min:0', // Allow 0 or null
            // Include hidden fields for cross-checking against database values
            'items.*.ordered_quantity' => 'required|integer|min:1',
            'items.*.already_received' => 'required|integer|min:0',
        ],[
            // Custom validation messages
            'items.*.receive_now_quantity.integer' => 'จำนวนรับเข้าของรายการ #:key ต้องเป็นตัวเลข',
            'items.*.receive_now_quantity.min' => 'จำนวนรับเข้าของรายการ #:key ต้องไม่ติดลบ',
        ]);

        // If validation fails, redirect back with errors and input
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()
                             ->with('error', 'ข้อมูลไม่ถูกต้อง: ' . $validator->errors()->first());
        }

        // Get the validated item data from the request
        $receivedItemsInput = $request->input('items');
        $poIdsToUpdate = []; // Keep track of POs whose items were updated
        $receivedCount = 0; // Count successfully received items
        $skippedItems = []; // Store reasons for skipping items (e.g., over-receive, not linked)
        $warnings = []; // Store non-critical warnings (e.g., PO item not found)

        // Start a transaction on the LOCAL database connection
        DB::beginTransaction(); // Uses default connection
        Log::info("[ReceiveController::process] Starting LOCAL receive process. Input: ", $receivedItemsInput);

        try {
            // Loop through each item submitted in the form
            foreach ($receivedItemsInput as $poItemId => $data) {
                // Get the quantity entered by the user (default to 0 if null/empty)
                $receiveNowQty = (int)($data['receive_now_quantity'] ?? 0);

                // Skip this item if the user entered 0 or nothing
                if ($receiveNowQty <= 0) {
                    continue;
                }

                // Find the PurchaseOrderItem in the LOCAL database (lock for update during transaction)
                $poItem = PurchaseOrderItem::lockForUpdate()->find($poItemId); // Uses default connection

                // Handle case where PO Item ID is invalid or not found in this DB
                if (!$poItem) {
                    $warnings[] = "ไม่พบรายการสั่งซื้อ ID {$poItemId} ในฐานข้อมูลนี้";
                    Log::warning("[ReceiveController::process] LOCAL PO Item ID {$poItemId} not found.");
                    continue; // Skip to the next item in the loop
                }

                // Get necessary details from the PO Item
                $poId = $poItem->purchase_order_id;
                $poIdsToUpdate[$poId] = $poId; // Mark this PO for potential status update later
                $equipmentId = $poItem->equipment_id;
                $totalOrdered = (int)$poItem->quantity_ordered;
                $alreadyReceived = (int)$poItem->quantity_received ?? 0; // Default to 0

                // 2. Prevent Over-Receiving
                // Check if receiving this amount would exceed the total ordered quantity
                if (($alreadyReceived + $receiveNowQty) > $totalOrdered) {
                    $skippedItems[$poItemId] = "รับเกินจำนวนที่สั่ง (สั่ง {$totalOrdered}, รับแล้ว {$alreadyReceived}, กรอก {$receiveNowQty})";
                    Log::warning("[ReceiveController::process] Over-receiving attempt for LOCAL PO Item #{$poItemId}. Ordered: {$totalOrdered}, Already Received: {$alreadyReceived}, Attempting: {$receiveNowQty}");
                    continue; // Skip this item
                }

                // 3. Ensure Item is Linked to Equipment in this Local DB
                // Items must be linked to an Equipment record before they can be received into stock
                if (is_null($equipmentId)) {
                    $skippedItems[$poItemId] = "ยังไม่ได้ผูกกับอุปกรณ์ในสต็อก (กรุณาแก้ไข PO Item หรือสร้าง Equipment)";
                    Log::warning("[ReceiveController::process] LOCAL PO Item #{$poItemId} skipped: Not linked to Equipment ID.");
                    continue; // Skip this item
                }

                // Find the corresponding Equipment in the LOCAL database (lock for update)
                $equipment = Equipment::lockForUpdate()->find($equipmentId); // Uses default connection

                // Handle case where the linked Equipment ID doesn't exist in this DB
                if (!$equipment) {
                    $skippedItems[$poItemId] = "ไม่พบข้อมูล Equipment ID {$equipmentId} ในฐานข้อมูลสต็อกนี้";
                    Log::error("[ReceiveController::process] LOCAL Equipment ID {$equipmentId} (linked from PO Item #{$poItemId}) not found in this department's database.");
                    continue; // Skip this item
                }

                // --- If all checks pass, proceed with receiving ---

                // 4. Update Stock Quantity in LOCAL Equipment table
                $equipment->increment('quantity', $receiveNowQty);
                // Note: The Equipment model's boot method should handle updating the 'status' (e.g., to 'available' or 'low_stock') automatically based on thresholds.
                Log::info("[ReceiveController::process] LOCAL Stock updated for Equipment #{$equipment->id} ({$equipment->name}). Added {$receiveNowQty}. New Qty: {$equipment->quantity}. Status should auto-update.");

                // 5. Create a Transaction record in the LOCAL transactions table
                Transaction::create([ // Uses default connection
                    'equipment_id'    => $equipment->id,
                    'user_id'         => Auth::id(), // ID of the user performing the receive action (from central User DB)
                    'handler_id'      => Auth::id(), // Assume the receiver is also the handler
                    'type'            => 'receive', // Specific transaction type for receiving
                    'quantity_change' => $receiveNowQty, // Positive value for stock increase
                    'notes'           => "รับของจาก PO #{$poId} (Item: {$poItem->item_description})", // Descriptive note
                    'transaction_date'=> now(), // Timestamp of the receive action
                    'status'          => 'completed', // Receiving transactions are completed immediately
                    'admin_confirmed_at' => now(), // Auto-confirmed
                    'user_confirmed_at' => now(),  // Auto-confirmed
                    'confirmed_at' => now(),       // Final confirmation timestamp
                ]);
                Log::debug("[ReceiveController::process] Created LOCAL 'receive' transaction for Equipment #{$equipment->id}.");

                // 6. Update the PurchaseOrderItem in the LOCAL DB
                $newTotalReceived = $alreadyReceived + $receiveNowQty;
                $poItem->update([ // Uses default connection
                    'quantity_received' => $newTotalReceived,
                    // Determine the new status for the PO Item
                    'status' => ($newTotalReceived >= $totalOrdered) ? 'received' : 'partial_receive' // 'received' if fully done, 'partial_receive' otherwise
                ]);
                Log::info("[ReceiveController::process] Updated LOCAL PO Item #{$poItemId}. New Received Qty: {$newTotalReceived}, New Status: {$poItem->status}.");

                // Increment the counter for successfully processed items
                $receivedCount++;

            } // end foreach loop through submitted items

            // 7. Update the main PurchaseOrder Status (in LOCAL DB) after processing all items for that PO
            // Loop through the unique PO IDs that had items updated in this submission
            foreach (array_unique($poIdsToUpdate) as $poId) {
                $purchaseOrder = PurchaseOrder::find($poId); // Uses default connection
                if ($purchaseOrder) {
                    // Check if *all* items belonging to this PO (in this local DB) are now fully received
                    $remainingItemsCount = $purchaseOrder->items() // Query items of this PO (uses default connection)
                                            ->where(function ($q) {
                                                // Filter for items that are NOT fully received
                                                $q->whereNull('quantity_received')
                                                  ->orWhereRaw('quantity_received < quantity_ordered');
                                            })
                                            ->count(); // Count how many items are left

                    if ($remainingItemsCount == 0) {
                        // If no items are left, mark the entire PO as 'completed'
                        $purchaseOrder->update(['status' => 'completed']);
                        Log::info("[ReceiveController::process] Updated LOCAL PO #{$poId} status to 'completed' as all items are received.");
                    } else if ($purchaseOrder->status == 'shipped_from_supplier' || $purchaseOrder->status == 'pending') {
                         // If items remain, but the PO was previously marked as shipped (or still pending),
                         // update its status to 'partial_receive'.
                         // Avoid overwriting if it was already 'partial_receive' from a previous action.
                        $purchaseOrder->update(['status' => 'partial_receive']);
                        Log::info("[ReceiveController::process] Updated LOCAL PO #{$poId} status to 'partial_receive'.");
                    }
                    // If status was already 'partial_receive' and items still remain, no status change needed.
                } else {
                    Log::warning("[ReceiveController::process] Could not find LOCAL PO #{$poId} to update its final status.");
                }
            }

            // If all operations were successful, commit the LOCAL DB transaction
            DB::commit();
            Log::info("[ReceiveController::process] LOCAL DB transaction committed successfully.");

            // --- Prepare User Feedback Messages ---
            $finalMessage = '';
            $messageType = 'success'; // Assume success initially

             if ($receivedCount > 0) {
                 $finalMessage .= "รับของเข้าสต็อกจำนวน {$receivedCount} รายการเรียบร้อยแล้ว";
             } else {
                 $finalMessage .= "ไม่มีรายการใดถูกรับเข้าสต็อก";
                 // Add clarification only if there were no errors or skips
                 if (empty($skippedItems) && empty($warnings)) {
                     $finalMessage .= " (อาจเนื่องจากไม่ได้กรอกจำนวนรับเข้า หรือจำนวนเป็น 0)";
                     $messageType = 'warning'; // Change to warning if nothing was received but no errors occurred
                 }
             }

             // Append details about skipped items if any
             if (!empty($skippedItems)) {
                  $finalMessage .= ($receivedCount > 0 ? "<br>" : "") . "<b>มีข้อผิดพลาด/ข้ามบางรายการ:</b><ul class='list-disc list-inside text-xs mt-1'>";
                 foreach ($skippedItems as $id => $reason) { $finalMessage .= "<li>PO Item #{$id}: {$reason}</li>"; }
                 $finalMessage .= "</ul>";
                 // Adjust message type based on overall result
                 if ($receivedCount > 0) { $messageType = 'warning'; } // Mixed results (some success, some skips)
                 else { $messageType = 'error'; } // Only skips/errors, no success
             }

              // Append non-critical warnings if any
              if (!empty($warnings)) {
                  $finalMessage .= (!empty($skippedItems) || $receivedCount > 0 ? "<br>" : "") . "<b>คำเตือน:</b><ul class='list-disc list-inside text-xs mt-1'>";
                  foreach ($warnings as $warn) { $finalMessage .= "<li>{$warn}</li>"; }
                  $finalMessage .= "</ul>";
                  // Downgrade success to warning if there were only warnings and no errors/skips
                  if ($messageType == 'success') $messageType = 'warning';
              }

            // Redirect back to the receive index page with the feedback message
            return redirect()->route('receive.index')->with($messageType, $finalMessage);

        } catch (\Exception $e) {
            // If any exception occurs, rollback the LOCAL DB transaction
            DB::rollBack();
            // Log the critical error with details
            Log::error("CRITICAL Error processing LOCAL receiving: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_input' => $request->input('items') // Log the input data for debugging
            ]);
            // Redirect back with input and a generic error message for the user
            return redirect()->back()
                             ->withInput() // Keep user's input in the form fields
                             ->with('error', 'เกิดข้อผิดพลาดร้ายแรง โปรดติดต่อผู้ดูแลระบบ: ' . $e->getMessage());
        } finally {
            // Log that the process finished, regardless of success or failure
            Log::info("[ReceiveController::process] LOCAL Process finished.");
        }
    }

    // --- Old Methods (Can be removed or commented out) ---
    /*
    // Old search method, possibly replaced by client-side filtering or Equipment search
    public function search(Request $request) { ... }
    // Old store method, replaced by the more comprehensive process() method
    public function store(Request $request) { ... }
    // Old job order specific view, potentially integrated into the main index() view now
    public function showJobOrder(\App\Models\PurchaseOrder $purchaseOrder) { ... }
    */
}

