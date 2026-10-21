<?php

use App\Http\Controllers\Api\CargoApiController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\WilayahController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Jepara Cargo Mobile & Integrasi
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Sinkronisasi Driver & Inbound Offline
Route::post('/driver/sync', [SyncController::class, 'driverSync']);
Route::post('/inbound/sync', [SyncController::class, 'inboundSync']);
Route::post('/pickup/sync', [SyncController::class, 'pickupSync']);

// API Wilayah Indonesia (Smart Search & Hierarki Dropdown)
Route::prefix('wilayah')->group(function () {
    Route::get('/search', [WilayahController::class, 'search'])->name('api.wilayah.search');
    Route::get('/provinces', [WilayahController::class, 'provinces'])->name('api.wilayah.provinces');
    Route::get('/regencies/{provinceId}', [WilayahController::class, 'regencies'])->name('api.wilayah.regencies');
    Route::get('/districts/{regencyId}', [WilayahController::class, 'districts'])->name('api.wilayah.districts');
    Route::get('/villages/{districtId}', [WilayahController::class, 'villages'])->name('api.wilayah.villages');
});

// API Inti Jepara Cargo Mobile
Route::prefix('cargo')->group(function () {
    Route::get('/dashboard', [CargoApiController::class, 'dashboard']);
    Route::get('/shipments', [CargoApiController::class, 'getShipments']);
    Route::post('/shipments', [CargoApiController::class, 'storeShipment']);
    Route::get('/senders', [CargoApiController::class, 'getSenders']);
    Route::get('/recipients', [CargoApiController::class, 'getRecipients']);
    Route::get('/track/{resi}', [CargoApiController::class, 'trackResi']);
});
