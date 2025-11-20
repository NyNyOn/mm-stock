<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController; // Ensure EquipmentController is imported
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\AjaxController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReceiveController;
use App\Http\Controllers\MaintenanceController; // ✅ 1. (มีอยู่แล้วในไฟล์ที่คุณให้มา)
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\DisposalController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\GroupManagementController;
use App\Http\Controllers\StockCheckController; // ✅ (มีอยู่แล้วในไฟล์ที่คุณให้มา)
use App\Http\Controllers\ConsumableReturnController;
use App\Http\Controllers\JobOrderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\PurchaseTrackController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\InventorySearchController;

// --- Authentication Routes ---
require __DIR__ . '/auth.php';

// --- Main Application Routes (Must be logged in) ---
Route::middleware('auth')->group(function () {

    Route::get('/', fn() => redirect()->route('dashboard'));

    // --- User-facing Routes ---
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('can:dashboard:view');
    Route::get('/user/equipment', [EquipmentController::class, 'userIndex'])->name('user.equipment.index')->middleware('can:equipment:borrow');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index')->middleware('can:transaction:view');
    Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index')->middleware('can:return:view');
    Route::post('/returns', [ReturnController::class, 'store'])->name('returns.store')->middleware('can:return:create');

    Route::post('/transactions/{transaction}/user-confirm', [TransactionController::class, 'userConfirmReceipt'])->name('transactions.userConfirmReceipt');
    Route::post('/transactions/{transaction}/admin-confirm', [TransactionController::class, 'adminConfirmShipment'])->name('transactions.adminConfirmShipment')->middleware('can:permission:manage');
    Route::get('/transactions/check-status', [TransactionController::class, 'checkBlockStatus'])->name('transactions.check_status');

    // (สำหรับ User ยกเลิกรายการ 'Pending')
    Route::patch('/transactions/{transaction}/user-cancel', [TransactionController::class, 'userCancel'])->name('transactions.userCancel');
    
    // 🌟🌟🌟 START: เพิ่ม Route นี้ 🌟🌟🌟
    // (สำหรับ Admin ยกเลิกรายการที่ 'Completed' แล้ว - เช่น Auto-Confirm)
    Route::patch('/transactions/{transaction}/admin-cancel', [TransactionController::class, 'adminCancelTransaction'])
        ->name('transactions.adminCancel')
        ->middleware('can:permission:manage');
    // 🌟🌟🌟 END: เพิ่ม Route นี้ 🌟🌟🌟

    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/nas-images/{deptKey}/{filename}', [ImageController::class, 'show'])
        ->where('filename', '.*') // Allow special characters in filename
        ->name('nas.image'); // Route name remains the same


    // Settings Routes
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/automation-requester', [SettingsController::class, 'updateAutomationRequester'])->name('settings.update.automation-requester');
    Route::post('/settings/automation-job-requester', [SettingsController::class, 'updateAutomationJobRequester'])->name('settings.update.automation-job-requester');

    // ✅✅✅ START: Add Maintenance Mode Routes ✅✅✅
    // (ใช้ MaintenanceController ตัวเดิมที่ใช้ร่วมกัน)
    // Placed after Settings routes for logical grouping
    Route::prefix('maintenance-mode')->name('maintenance.')->middleware('can:maintenance:mode')->group(function () {
        Route::post('/enable', [MaintenanceController::class, 'enable'])->name('enable');
        Route::post('/disable', [MaintenanceController::class, 'disable'])->name('disable');
    });
    // ✅✅✅ END: Add Maintenance Mode Routes ✅✅✅

    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog.index');
    Route::post('/changelog', [ChangelogController::class, 'store'])->name('changelog.store')->middleware('can:permission:manage');
    
    // Route ค้นหาเก่า (ที่เราทำไว้)
    Route::get('/inventory/search', [InventorySearchController::class, 'search'])->name('inventory.search');

    Route::get('/ajax/inventory-live-search', [InventorySearchController::class, 'ajaxSearch'])
        ->name('inventory.ajax_search') // <-- 🌟 ชื่อนี้จะถูกต้องแล้ว
        ->middleware('can:equipment:borrow');


    // --- Admin & Staff Routes ---
    Route::resource('equipment', EquipmentController::class)->middleware('can:equipment:view');
    Route::post('/transactions/{transaction}/rate', [TransactionController::class, 'rateTransaction'])
        ->name('transactions.rate');

    // --- Receive Management (Updated for Cloning Approach) ---
    Route::get('/receive', [ReceiveController::class, 'index'])
        ->name('receive.index')
        ->middleware('can:receive:view');
    Route::post('/receive/process', [ReceiveController::class, 'process'])
        ->name('receive.process')
        ->middleware('can:receive:manage'); // Assuming receive:manage permission exists

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->middleware('can:report:view');
    Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate')->middleware('can:report:view');
    Route::post('/reports/export-pdf', [ReportController::class, 'exportPdf'])->name('reports.exportPdf');
    
    // (Original routes for Equipment Maintenance)
    Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index')->middleware('can:maintenance:view'); // Note: same controller, different route group
    Route::post('/maintenance/{maintenanceLog}', [MaintenanceController::class, 'update'])->name('maintenance.update')->middleware('can:maintenance:manage');
    Route::get('/maintenance/prepare', [MaintenanceController::class, 'prepareKey'])->name('maintenance.prepare')->middleware(['web', 'auth']);

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index')->middleware('can:po:view');

    // Purchase Order Tracking (New)
    Route::get('/purchase-tracking', [PurchaseTrackController::class, 'index'])->name('purchase-track.index')->middleware('can:po:view');

    // Stock Check System
    Route::prefix('stock-checks')->name('stock-checks.')->middleware('can:stock-check:manage')->group(function () {
        Route::get('/', [StockCheckController::class, 'index'])->name('index');
        Route::get('/create', [StockCheckController::class, 'create'])->name('create');
        Route::post('/', [StockCheckController::class, 'store'])->name('store');
        Route::get('/{stockCheck}', [StockCheckController::class, 'show'])->name('show');
        Route::get('/{stockCheck}/perform', [StockCheckController::class, 'perform'])->name('perform');
        Route::put('/{stockCheck}', [StockCheckController::class, 'update'])->name('update');
                
    });

    // Purchase Order by Job
    Route::middleware(['can:po:create'])->group(function () {
        Route::get('/job-orders/create', [JobOrderController::class, 'create'])->name('job-orders.create');
        Route::post('/job-orders', [JobOrderController::class, 'store'])->name('job-orders.store');
        Route::post('/purchase-orders/submit-job-orders', [PurchaseOrderController::class, 'submitJobOrders'])->name('purchase-orders.submitJobOrders');
        Route::get('/receive/job-order/{purchaseOrder}', [ReceiveController::class, 'showJobOrder'])->name('receive.job-order');
        Route::post('/purchase-orders/{purchaseOrder}/add-item', [PurchaseOrderController::class, 'addItem'])->name('purchase-orders.add-item');
        Route::post('/purchase-orders/run-glpi-sync', [PurchaseOrderController::class, 'runGlpiSync'])->name('purchase-orders.runGlpiSync');
        Route::get('/purchase-orders/{order}/items-view', [PurchaseOrderController::class, 'getItemsView'])->name('purchase-orders.items-view');
        Route::delete('/purchase-orders/item/{item}', [PurchaseOrderController::class, 'ajaxRemoveItem'])->name('purchase-orders.ajax-remove-item');
    });

    // Consumable Returns
    Route::prefix('consumable-returns')->name('consumable-returns.')->group(function () {
        Route::get('/', [ConsumableReturnController::class, 'index'])->name('index')->middleware('can:consumable:return');
        Route::post('/', [ConsumableReturnController::class, 'store'])->name('store')->middleware('can:consumable:return');
        Route::middleware('can:permission:manage')->group(function () {
            Route::post('/{return}/approve', [ConsumableReturnController::class, 'approve'])->name('approve');
            Route::post('/{return}/reject', [ConsumableReturnController::class, 'reject'])->name('reject');
        });
    });

    // --- Management Routes (Admins/IT Only) ---
    Route::prefix('management')->name('management.')->group(function () {
        // === USER MANAGEMENT ===
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index')->middleware('can:user:manage');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update')->middleware('can:user:manage');
        Route::delete('/users/{user}/remove-group', [UserManagementController::class, 'removeGroup'])->name('users.removeGroup')->middleware('can:user:manage');

        // === GROUP & PERMISSION MANAGEMENT (REVISED & SIMPLIFIED) ===
        Route::resource('groups', GroupManagementController::class)->middleware('can:permission:manage');
        Route::put('/groups/{group}/permissions', [GroupManagementController::class, 'updatePermissions'])
            ->name('groups.updatePermissions')
            ->middleware('can:permission:manage');

        // === API TOKEN MANAGEMENT ===
        Route::prefix('tokens')->name('tokens.')->middleware('can:token:manage')->group(function () {
            Route::get('/', [ApiTokenController::class, 'index'])->name('index');
            Route::post('/', [ApiTokenController::class, 'store'])->name('store');
            Route::get('/{tokenId}', [ApiTokenController::class, 'show'])->name('show');
            Route::delete('/{tokenId}', [ApiTokenController::class, 'destroy'])->name('destroy');
        });
    });

    // Specific Action Routes
    Route::middleware('can:master-data:manage')->group(function () {
        Route::resource('categories', CategoryController::class)->except(['create', 'edit']);
        Route::resource('locations', LocationController::class)->except(['create', 'edit']);
        Route::resource('units', UnitController::class)->except(['create', 'edit']);
    });

    Route::middleware('can:po:create')->group(function () {
        Route::post('/purchase-orders/submit-urgent', [PurchaseOrderController::class, 'submitUrgent'])->name('purchase-orders.submitUrgent');
        Route::post('/purchase-orders/run-stock-check', [PurchaseOrderController::class, 'runStockCheck'])->name('purchase-orders.runStockCheck');
        Route::post('/purchase-orders/scheduled/submit', [PurchaseOrderController::class, 'submitScheduled'])->name('purchase-orders.submitScheduled');
        Route::post('/purchase-orders/urgent/add-item/{equipment}', [PurchaseOrderController::class, 'addItemToUrgent'])->name('purchase-orders.addItemToUrgent');
        Route::post('/purchase-orders/scheduled/add-item/{equipment}', [PurchaseOrderController::class, 'addItemToScheduled'])->name('purchase-orders.addItemToScheduled');
    });

    Route::middleware('can:po:manage')->group(function() {
        Route::delete('/purchase-order-items/{item}', [PurchaseOrderController::class, 'removeItem'])->name('purchase-order-items.destroy');
        Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');
        Route::post('/purchase-orders/{purchaseOrder}/clone-and-forward', [PurchaseOrderController::class, 'cloneAndForward'])->name('purchase-orders.cloneAndForward');
    });

    Route::middleware('can:disposal:view')->group(function () {
        Route::get('/disposal', [DisposalController::class, 'index'])->name('disposal.index');
        Route::middleware('can:disposal:manage')->group(function() {
            Route::post('/disposal/{equipment}/restore', [DisposalController::class, 'restore'])->name('disposal.restore');
            Route::post('/disposal/{equipment}/sell', [DisposalController::class, 'markAsSold'])->name('disposal.sell');
        });
    });

    // === AJAX Routes ===
    Route::prefix('ajax')->name('ajax.')->group(function () {
        Route::get('/get-ldap-users', [AjaxController::class, 'getLdapUsers'])->name('get-ldap-users');
        Route::get('/get-ldap-users-with-setting/{settingKey}', [SettingsController::class, 'getLdapUsersWithSetting'])->name('get-ldap-users-with-setting');
        Route::get('/equipment/{equipment}/edit-form', [EquipmentController::class, 'getEditForm'])->name('equipment.editForm');
        Route::post('/next-serial', [EquipmentController::class, 'getNextSerialNumber'])->name('next-serial');
        Route::get('/items', [TransactionController::class, 'searchItems'])->name('items.search');
        Route::post('/withdrawal', [TransactionController::class, 'storeWithdrawal'])->name('withdrawal.store');
        Route::post('/user/transact', [TransactionController::class, 'handleUserTransaction'])->name('user.transact')->middleware('can:equipment:borrow');

        Route::post('/find-by-scan', [AjaxController::class, 'findEquipmentByScan'])->name('find-by-scan');
        Route::get('/dashboard-charts', [DashboardController::class, 'getChartData'])->name('dashboard.charts');
        Route::get('/search-equipment', [DashboardController::class, 'searchEquipmentForChart'])->name('search.equipment');
        Route::get('/transactions/latest-timestamp', [TransactionController::class, 'getLatestTimestamp'])->name('transactions.latestTimestamp');
        Route::post('/transactions/{transaction}/write-off', [TransactionController::class, 'writeOff'])->name('transactions.writeOff')->middleware('can:permission:manage');
        Route::get('/equipment/msds-form', [EquipmentController::class, 'getMsdsFormContent'])
            ->middleware('can:equipment:manage')
            ->name('equipment.msdsFormContent');
            
    });

});

// Public AJAX Routes
Route::post('/ajax-handler', [AjaxController::class, 'handleRequest'])->name('ajax.handler');

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

// Route สำหรับซ่อมฐานข้อมูลทุกแผนก
Route::get('/fix-databases-tables', function () {
    $departments = Config::get('department_stocks.departments', []);
    $results = [];

    foreach ($departments as $key => $dept) {
        try {
            $dbName = $dept['db_name'];
            
            // สลับ Connection ไปที่ฐานข้อมูลแผนกนั้น
            DB::purge('mysql');
            Config::set('database.connections.mysql.database', $dbName);
            DB::reconnect('mysql');

            // สั่ง Migrate (จะสร้างตารางที่ขาดอยู่ให้เอง โดยดูจากไฟล์ใน database/migrations)
            Artisan::call('migrate', ['--force' => true]);

            $results[] = "<span style='color:green'>✅ แผนก {$key} ($dbName): อัปเดตตารางสำเร็จ</span>";
        } catch (\Exception $e) {
            $results[] = "<span style='color:red'>❌ แผนก {$key}: " . $e->getMessage() . "</span>";
        }
    }

    return implode('<br>', $results);
});