<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\SupervisorController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryAuditController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\Supervisor\ItemController;
use App\Http\Controllers\Supervisor\ReportController;
use App\Http\Controllers\Supervisor\WorkerController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseTransferController;
use App\Http\Controllers\Worker\LabelController;
use Illuminate\Support\Facades\Route;

// Language switcher route (Locked to Arabic)
Route::middleware('web')->get('/lang/{locale}', function () {
    session(['locale' => 'ar']);
    app()->setLocale('ar');

    return redirect()->back(fallback: route('dashboard'));
})->name('lang.switch');

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Redirect root
Route::get('/', fn () => redirect()->route('dashboard'));

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard (accessible to all authenticated users)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Mobile / Handheld Scanner
    Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
    Route::post('/scanner/lookup', [ScannerController::class, 'lookup'])->name('scanner.lookup');

    // Transactions (Inbound, Outbound, Returns, Damages)
    Route::middleware('role:admin,super_admin,warehouse_manager,inventory_manager,supervisor,storekeeper,stock_counter,worker,auditor,viewer')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    });

    // Multi-Warehouse Management
    Route::resource('warehouses', WarehouseController::class);

    // Inter-Warehouse Transfers
    Route::resource('transfers', WarehouseTransferController::class)->except(['edit', 'update', 'destroy']);
    Route::post('/transfers/{transfer}/approve', [WarehouseTransferController::class, 'approveTransfer'])->name('transfers.approve');
    Route::post('/transfers/{transfer}/dispatch', [WarehouseTransferController::class, 'dispatchTransfer'])->name('transfers.dispatch');
    Route::post('/transfers/{transfer}/receive', [WarehouseTransferController::class, 'receiveTransfer'])->name('transfers.receive');
    Route::post('/transfers/{transfer}/resolve', [WarehouseTransferController::class, 'resolveDiscrepancy'])->name('transfers.resolve');

    // Inventory Audits & Cycle Counts
    Route::resource('audits', InventoryAuditController::class)->except(['edit', 'update', 'destroy']);
    Route::post('/audits/{audit}/counts', [InventoryAuditController::class, 'updateCounts'])->name('audits.counts');
    Route::post('/audits/{audit}/reconcile', [InventoryAuditController::class, 'reconcile'])->name('audits.reconcile');

    // Item Batches & Expiry Management
    Route::resource('batches', BatchController::class)->only(['index', 'create', 'store']);
    Route::post('/batches/{batch}/quarantine', [BatchController::class, 'toggleQuarantine'])->name('batches.quarantine');

    // Suppliers & Customers
    Route::resource('suppliers', SupplierController::class);
    Route::resource('customers', CustomerController::class);

    // Units of Measurement & Categories
    Route::resource('units', UnitController::class);
    Route::resource('categories', ItemCategoryController::class);

    // Admin routes
    Route::middleware('role:admin,super_admin,warehouse_manager,auditor')->prefix('admin')->name('admin.')->group(function () {
        Route::middleware('role:admin,super_admin,warehouse_manager')->group(function () {
            Route::resource('departments', DepartmentController::class)->except(['show']);
            Route::resource('supervisors', SupervisorController::class)->except(['show']);
        });
        Route::get('/logs', [SystemLogController::class, 'index'])->name('logs.index');
    });

    // Supervisor & Inventory Manager routes
    Route::middleware('role:admin,super_admin,warehouse_manager,inventory_manager,supervisor,auditor')->prefix('supervisor')->name('supervisor.')->group(function () {
        Route::middleware('role:admin,super_admin,warehouse_manager,inventory_manager,supervisor')->group(function () {
            Route::resource('items', ItemController::class)->except(['show']);
            Route::resource('workers', WorkerController::class)->except(['show']);
        });
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'exportExcel'])->name('reports.export');
    });

    // Worker & Label Printing routes
    Route::middleware('role:admin,super_admin,warehouse_manager,inventory_manager,supervisor,storekeeper,stock_counter,worker')->prefix('worker')->name('worker.')->group(function () {
        Route::get('/labels/search', [LabelController::class, 'search'])->name('labels.search');
        Route::get('/labels/{item}/print', [LabelController::class, 'print'])->name('labels.print');
    });
});
