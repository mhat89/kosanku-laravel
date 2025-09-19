<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/ping', fn() => response()->json(['pong' => true])); // tes sederhana

Route::post('/auth/register',   [AuthController::class, 'register']);
Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/auth/login',      [AuthController::class, 'login']);
Route::post('/auth/forgot-password',      [AuthController::class,'forgotPassword']);
Route::post('/auth/verify-forgot-otp',    [AuthController::class,'verifyForgotOtp']);
// routes/api.php
//Route::middleware('auth:sanctum')->get('/users/me', fn() => auth()->user());
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',     [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
     Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
});

use App\Http\Controllers\BoardingHouseController;

// public read
Route::prefix('kosan')->group(function () {
    Route::get('nearby', [BoardingHouseController::class, 'nearby']);
    Route::get('search', [BoardingHouseController::class, 'search']);
    Route::get('{id}',   [BoardingHouseController::class, 'show']); // <-- detail
});;

// protected write (butuh token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/kosan', [BoardingHouseController::class, 'store']);
    Route::put('/kosan/{kosan}', [BoardingHouseController::class, 'update']);
    Route::delete('/kosan/{kosan}', [BoardingHouseController::class, 'destroy']);
});
