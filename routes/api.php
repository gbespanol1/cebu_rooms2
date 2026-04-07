<?php

use App\Http\Controllers\EquipmentController;
use Illuminate\Support\Facades\Route;

// Equipment API routes - NO AUTH FOR NOW (for testing)
Route::prefix('equipment')->group(function () {
    Route::get('/', [EquipmentController::class, 'getAll']);
    Route::get('/stats', [EquipmentController::class, 'getEquipmentStats']);
    Route::get('/usage', [EquipmentController::class, 'getEquipmentUsage']);
    Route::post('/', [EquipmentController::class, 'store']);
    Route::put('/{id}', [EquipmentController::class, 'update']);
    Route::delete('/{id}', [EquipmentController::class, 'destroy']);
    Route::post('/{id}/transfer', [EquipmentController::class, 'transfer']);
});
