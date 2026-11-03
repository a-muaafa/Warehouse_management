<?php

use App\Http\Controllers\Api\v1\InventoryApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// REST API v1 Integration routes for ERP / Accounting / Third-party systems
Route::prefix('v1')->name('api.v1.')->middleware(['auth.api_token', 'throttle:api'])->group(function () {
    // Read-only endpoints
    Route::middleware('api.scope:read')->group(function () {
        Route::get('/stock-balances', [InventoryApiController::class, 'stockBalances'])->name('stock-balances');
        Route::get('/items/barcode/{code}', [InventoryApiController::class, 'findByBarcode'])->name('items.barcode');
        Route::get('/valuation-summary', [InventoryApiController::class, 'valuationSummary'])->name('valuation-summary');
    });

    // Write endpoints — requires explicit 'write' scope
    Route::middleware('api.scope:write')->group(function () {
        Route::post('/transactions/external-sync', [InventoryApiController::class, 'recordExternalTransaction'])->name('transactions.external-sync');
    });
});
