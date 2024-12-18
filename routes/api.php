<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SavingController;


// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('login', [AuthController::class, 'userLogin']);
Route::post('/user/register', [AuthController::class, 'userRegister']);


Route::middleware('auth:sanctum')->group(function () {
    // Logout membutuhkan autentikasi
    Route::post('logout', [AuthController::class, 'logout']);

    // Get user data
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('savings/{id}/add', [SavingController::class, 'addSaving']);
    Route::get('savings/status/{status}', [SavingController::class, 'getSavingsByStatus']);
    Route::apiResource('savings', SavingController::class);
});
