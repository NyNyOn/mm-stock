<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers สำหรับ AJAX ภายใน
// ✅ Use statements for internal AJAX controllers
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ReceiveController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AjaxController;
// ✅ 1. เพิ่ม ImageController เพื่อใช้งานกับ Route รูปภาพ
use App\Http\Controllers\ImageController;

// Controllers สำหรับ API v1
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\InspectionController;
// ✅ เรียกใช้ Controller ผ่านชื่อเล่น (Alias) ที่เราตั้งไว้
use App\Http\Controllers\Api\V1\EquipmentController as V1EquipmentController;
use App\Http\Controllers\Api\ScheduleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- API สำหรับ AJAX ภายในเว็บ (ใช้ Session) ---
Route::middleware('auth')->group(function () {
    // Standard user info endpoint
    Route::get('/user', fn(Request $request) => $request->user());

    // (Internal AJAX routes omitted/commented out as per your file)
});

// ✅ Public Routes for External Systems (PU, etc.)
Route::prefix('v1')->group(function () {
    // ✅ เรียกใช้ Controller ผ่านชื่อเล่น (Alias)
    Route::get('/equipments', [V1EquipmentController::class, 'index'])->name('api.v1.equipments.index');

    // ✅ เพิ่ม: ดึงรายละเอียด 1 ชิ้น (โดยระบุ ID)
    Route::get('/equipments/{id}', [V1EquipmentController::class, 'show'])->name('api.v1.equipments.show');


});


// --- API v1 สำหรับระบบภายนอก (ใช้ Sanctum Token) ---
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // ✅ 2. เพิ่ม Route รูปภาพไว้ในนี้ (คนที่จะดูต้องมี Token เท่านั้น)
    Route::get('/nas-images/{deptKey}/{filename}', [ImageController::class, 'show'])
        ->where('filename', '.*')
        ->name('api.v1.nas.image');



    // --- Routes สำหรับระบบ PU (Inbound to this app) ---

    // Get list of POs
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('api.v1.purchase-orders.index');

    // Get details of a single PO
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('api.v1.purchase-orders.show');

    // Standard PO Intake
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('api.v1.purchase-orders.store');

    // Custom PR Intake
    Route::post('/purchase-requests', [PurchaseOrderController::class, 'storeRequest'])->name('api.v1.purchase-requests.store');

    // Inspection Result Submission
    Route::post('/inspections/submit', [InspectionController::class, 'submit'])->name('api.v1.inspections.submit');

    // Delivery Notification
     Route::post('/po-delivery-notification/{purchaseOrder}', [PurchaseOrderController::class, 'notifyDelivery'])
          ->name('api.v1.po-delivery-notification');

    // ✅ Webhook from PU (Now Secured with Token to track usage)
    Route::post('/notify-hub-arrival', [PurchaseOrderController::class, 'receiveHubNotification'])->name('api.v1.notify-hub-arrival');

    // ✅ Schedule Sync API (รับกำหนดการจาก PU Hub)
    Route::post('/schedule/sync', [ScheduleController::class, 'sync'])->name('api.v1.schedule.sync');
    Route::get('/schedule', [ScheduleController::class, 'show'])->name('api.v1.schedule.show');

});

// Fallback route for unauthenticated API access or invalid API routes
Route::fallback(function(){
    return response()->json(['message' => 'API endpoint not found or authentication required.'], 404);
});