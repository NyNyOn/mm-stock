<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder; // Uses default connection
use App\Models\LdapUser; // Uses depart_it_db connection (assuming this is User model)
use App\Models\User; // Also uses depart_it_db connection
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Use default connection DB facade
use Exception;
use Illuminate\Support\Facades\Log;

use App\Http\Resources\PurchaseOrderResource;
use App\Http\Requests\Api\StorePurchaseOrderRequest;
use App\Http\Requests\Api\StorePurchaseRequestRequest;

class PurchaseOrderController extends Controller
{
    /**
     * (Outbound) Display a listing of purchase orders FROM THIS DEPARTMENT'S DB.
     */
    public function index()
    {
        // Queries the LOCAL database defined in .env
        $purchaseOrders = PurchaseOrder::with(['items', 'orderedBy'])
                                      ->orderBy('created_at', 'desc')
                                      ->paginate(20);
        return PurchaseOrderResource::collection($purchaseOrders);
    }

    /**
     * (Outbound) Display the specified purchase order FROM THIS DEPARTMENT'S DB.
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        // Model binding uses the LOCAL connection
        return new PurchaseOrderResource($purchaseOrder->load(['items', 'orderedBy']));
    }

    /**
     * (Inbound - Standard PO) Store a newly created purchase order IN THIS DEPARTMENT'S DB.
     */
    public function store(StorePurchaseOrderRequest $request)
    {
        // Writes to the LOCAL database
        // PU API needs to know which app instance (URL) to call based on the department
        Log::info("Received standard PO store request.", $request->validated());
        // TODO: Implement logic based on expected JSON structure
        // Need to ensure ordered_by_user_id refers to a valid ID (potentially from central User DB)
        // Ensure PU sends data for THIS department only.

        DB::beginTransaction(); // Use default connection transaction
        try {
            // Example: Create PO in local DB
            // $poData = $request->validated();
            // $po = PurchaseOrder::create($poData);
            // ... create items ...
            DB::commit();
            // return new PurchaseOrderResource($po->load('items')); // Return created PO
            return response()->json(['message' => 'Standard PO intake endpoint needs implementation.'], 501);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to create LOCAL Standard PO via API: " . $e->getMessage(), ['request_data' => $request->validated()]);
            return response()->json(['message' => 'Failed to create Purchase Order.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * (Inbound - Custom PR) Store a newly created purchase request IN THIS DEPARTMENT'S DB.
     */
    public function storeRequest(StorePurchaseRequestRequest $request)
    {
        // Writes PO and PO Items to the LOCAL database
        $validated = $request->validated();
        Log::info("Received custom PR store request for local processing.", $validated);

        // Find the requester user in the central user database ('depart_it_db')
        $requester = LdapUser::find($validated['requestor_user_id']); // Assumes LdapUser points to depart_it_db
        if (!$requester) {
             Log::error("Requester user ID {$validated['requestor_user_id']} not found in central user DB (depart_it_db).");
             return response()->json(['message' => 'Requester user ID not found.'], 404);
        }

        // Use transaction on the LOCAL database connection
        DB::beginTransaction(); // Uses default connection
        try {
            $po = PurchaseOrder::create([ // Writes to local DB
                'po_number'      => 'PR-' . uniqid(),
                'ordered_by_user_id' => $requester->id, // Store ID from central User DB
                // 'requester_name' => $requester->fullname, // Optionally store name locally if needed
                'status'         => 'pending',
                'type'           => $validated['priority'],
            ]);
            Log::info("Created LOCAL PO #{$po->id} for PR.");

            foreach ($validated['items'] as $item) {
                $description = $item['item_name_custom'] . " (" . $item['unit_name'] . ")";
                if (!empty($item['notes'])) { $description .= " - " . $item['notes']; }

                // No dept_key needed as PO is local to the department
                $po->items()->create([ // Writes to local DB
                    'equipment_id' => null,
                    'item_description'  => $description,
                    'quantity_ordered'     => $item['quantity'],
                    'status' => 'pending',
                    'requester_id' => $requester->id, // Store requester ID per item
                ]);
            }
            DB::commit(); // Commit local transaction
            Log::info("Successfully created LOCAL items for PO #{$po->id}.");

            return (new PurchaseOrderResource($po->load(['items', 'orderedBy'])))
                   ->response()
                   ->setStatusCode(201);

        } catch (Exception $e) {
            DB::rollBack(); // Rollback local transaction
            Log::error("Failed to create LOCAL Purchase Request via API for Requester ID {$validated['requestor_user_id']}: " . $e->getMessage(), ['request_data' => $validated]);
            return response()->json(['message' => 'Failed to create Purchase Request.', 'error' => $e->getMessage()], 500);
        }
    }

    // Method to handle PU Delivery Notification (For Cloned Apps)
    /**
     * (Inbound) Receives notification from PU system that items for a PO have shipped.
     * Updates the PurchaseOrder status in the LOCAL database.
     * This method must exist in EACH CLONED APPLICATION.
     * PU system needs to call the correct API endpoint (e.g., pe-stock.domain/api/...)
     *
     * @param Request $request
     * @param PurchaseOrder $purchaseOrder (Route Model Binding using LOCAL connection)
     * @return \Illuminate\Http\JsonResponse
     */
    public function notifyDelivery(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Input validation (optional, could just check if PO exists)
        // $request->validate([ // Example validation
        //     'delivery_date' => 'nullable|date',
        //     'tracking_number' => 'nullable|string|max:255',
        // ]);

        Log::info("[API notifyDelivery] Received delivery notification for LOCAL PO #{$purchaseOrder->id} (Current Status: {$purchaseOrder->status}).");

        // Define valid statuses from which a PO can transition to 'shipped_from_supplier'
        $validPreviousStatuses = ['ordered', 'pending', 'partial_receive']; // Include partial_receive if PU might send multiple ship notifications

        // Check if the PO exists and is in a valid state (Model Binding handles existence check)
        if (!in_array($purchaseOrder->status, $validPreviousStatuses)) {
             Log::warning("[API notifyDelivery] Skipping delivery notification for LOCAL PO #{$purchaseOrder->id}: Status '{$purchaseOrder->status}' invalid.");
             // Return success, indicating the notification was received but no action needed for this status
             return response()->json([
                 'success' => true,
                 'message' => "LOCAL PO #{$purchaseOrder->id} status '{$purchaseOrder->status}' is not eligible for shipment notification. No action taken."
             ], 200); // 200 OK is appropriate here
        }

        // Use transaction on the LOCAL database connection
        DB::beginTransaction(); // Uses default connection
        try {
            // Update PO status to 'shipped_from_supplier' in LOCAL DB
            $purchaseOrder->update([
                'status' => 'shipped_from_supplier'
                // Optionally update other fields if PU sends them, e.g., 'shipped_at' => $request->input('delivery_date')
            ]);

            // Optional: Update status of individual items to 'shipped' if that makes sense for your workflow
            // $purchaseOrder->items()->where('status', 'ordered')->update(['status' => 'shipped']); // Example

            DB::commit(); // Commit local transaction
            Log::info("[API notifyDelivery] Successfully updated LOCAL PO #{$purchaseOrder->id} status to shipped_from_supplier.");
            return response()->json([
                'success' => true,
                'message' => "LOCAL PO #{$purchaseOrder->id} status updated to shipped_from_supplier."
            ]);

        } catch (\Exception $e) {
            DB::rollBack(); // Rollback local transaction
            Log::error("[API notifyDelivery] Failed to update LOCAL PO status for PO #{$purchaseOrder->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating PO status.'
            ], 500); // Internal Server Error
        }
    }
}

