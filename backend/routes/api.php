<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\SyncController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Auth Route
Route::post('/login', [ApiController::class, 'login'])->name('login');
Route::get('/test-log', function() {
    Log::info('LOG TEST REACHED');
    return response()->json(['message' => 'Log test reached']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Sync Routes
    Route::get('/sync/download', [SyncController::class, 'download']);
    Route::post('/sync/upload', [SyncController::class, 'upload']);
    Route::get('/sync/files', [SyncController::class, 'listFiles']);
    Route::post('/sync/save-transaction', [SyncController::class, 'storeTransactionJson']);
    Route::post('/sync/save-inventory-count', [SyncController::class, 'storeInventoryCountJson']);
    Route::get('/sync/json/{filename}', [SyncController::class, 'getFile']);

    Route::get('/sites', [ApiController::class, 'getSites']);
    Route::get('/locations', [ApiController::class, 'getLocations']);
    Route::post('/transactions', [ApiController::class, 'storeTransaction']);
    Route::get('/transactions', [ApiController::class, 'getTransactions']);
    Route::get('/assets/lookup', [ApiController::class, 'lookupAsset']);
    Route::get('/assets/by-location', [ApiController::class, 'getAssetsByLocation']);
    Route::post('/inventory/count', [ApiController::class, 'storeInventoryCount']);
    Route::get('/inventory/counts', [ApiController::class, 'getInventoryCounts']);
    Route::get('/inventory/counts-by-location', [ApiController::class, 'getInventoryCountsByLocation']);
    Route::delete('/inventory/count/{id}', [ApiController::class, 'deleteInventoryCount']);
    Route::delete('/transactions/{id}', [ApiController::class, 'deleteTransaction']);
    Route::post('/inventory/scan-session', [ApiController::class, 'submitScan']);
});
