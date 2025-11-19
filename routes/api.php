<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers สำหรับ AJAX ภายใน
// ✅ Use statements for internal AJAX controllers
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ReceiveController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AjaxController;

// Controllers สำหรับ API v1
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\InspectionController;
// ✅ 1. แก้ไข use statement โดยการตั้งชื่อเล่น (Alias) ให้มัน
use App\Http\Controllers\Api\V1\EquipmentController as V1EquipmentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- API สำหรับ AJAX ภายในเว็บ (ใช้ Session) ---
// Note: Using 'auth' typically refers to session guard unless configured otherwise.
// Consider using 'auth:web' for clarity if you have multiple guards.
Route::middleware('auth')->group(function () {
    // Standard user info endpoint (often used by frontend frameworks)
    Route::get('/user', fn(Request $request) => $request->user());

    // --- Specific AJAX endpoints for internal use ---
    // ✅ These seem like internal helpers, often better placed in web.php under 'auth'
    // Route::post('/get-next-serial', [CategoryController::class, 'getNextSerialNumber'])->name('categories.getNextSerial'); // Consider moving to web.php ajax prefix
    // Route::post('/receive/search', [ReceiveController::class, 'search'])->name('receive.search'); // Consider moving to web.php ajax prefix
    // Route::post('/receive', [ReceiveController::class, 'store'])->name('receive.store'); // Consider moving to web.php ajax prefix
    // Route::get('/transactions/search-equipment', [TransactionController::class, 'searchEquipment'])->name('transactions.searchEquipment'); // Consider moving to web.php ajax prefix
    // Route::post('/transactions/by-serial', [TransactionController::class, 'getEquipmentBySerial'])->name('transactions.getEquipmentBySerial'); // Consider moving to web.php ajax prefix
    // Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store'); // Consider moving to web.php ajax prefix
    // Route::post('/ajax-handler', [AjaxController::class, 'handleRequest'])->name('ajax.handler'); // Keep if truly needed here, else move

    // Note: The routes commented out above might be better placed in routes/web.php
    // within the ajax prefix group for consistency with session-based authentication.
});


// --- API v1 สำหรับระบบภายนอก (ใช้ Sanctum Token) ---
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // ✅ 2. เรียกใช้ Controller ผ่านชื่อเล่น (Alias) ที่เราตั้งไว้
    Route::get('/equipments', [V1EquipmentController::class, 'index'])->name('api.v1.equipments.index'); // Added name

    // --- Routes สำหรับระบบ PU (Inbound to this app) ---
    // ✅ Assuming these are INBOUND endpoints called BY the PU system

    // Get list of POs (Read-only for PU?)
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('api.v1.purchase-orders.index'); // Added name

    // Get details of a single PO (Read-only for PU?)
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('api.v1.purchase-orders.show'); // Added name

    // Standard PO Intake (PU sends complete PO data to create/update)
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('api.v1.purchase-orders.store'); // Correct Controller

    // Custom PR Intake (PU sends PR data, this app creates a basic PO)
    Route::post('/purchase-requests', [PurchaseOrderController::class, 'storeRequest'])->name('api.v1.purchase-requests.store'); // Correct Controller

    // Inspection Result Submission (PU sends inspection results for PO items)
    Route::post('/inspections/submit', [InspectionController::class, 'submit'])->name('api.v1.inspections.submit'); // Correct Controller

    // Delivery Notification (PU notifies this app instance that items have shipped)
    // Needs PurchaseOrder model binding for {purchaseOrder}
     Route::post('/po-delivery-notification/{purchaseOrder}', [PurchaseOrderController::class, 'notifyDelivery'])
          ->name('api.v1.po-delivery-notification'); // Added based on previous context


});

// Fallback route for unauthenticated API access or invalid API routes
Route::fallback(function(){
    return response()->json(['message' => 'API endpoint not found or authentication required.'], 404);
});

