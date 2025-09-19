<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;

Route::get('/ping', fn() => response()->json(['pong' => true])); // tes sederhana

Route::post('/auth/register',   [AuthController::class, 'register']);
Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/auth/login',      [AuthController::class, 'login']);
Route::post('/auth/forgot-password',      [AuthController::class,'forgotPassword']);
Route::post('/auth/verify-forgot-otp',    [AuthController::class,'verifyForgotOtp']);

// Admin Auth Routes
Route::prefix('admin')->group(function () {
    Route::get('/check/{email}', [AdminController::class, 'check']);
    Route::post('/register',   [AdminController::class, 'register']);
    Route::post('/resend-otp', [AdminController::class, 'resendOtp']);
    Route::post('/verify-otp', [AdminController::class, 'verifyOtp']);
    Route::post('/login',      [AdminController::class, 'login']);
    Route::post('/forgot-password',      [AdminController::class,'forgotPassword']);
    Route::post('/verify-forgot-otp',    [AdminController::class,'verifyForgotOtp']);
});
// routes/api.php
//Route::middleware('auth:sanctum')->get('/users/me', fn() => auth()->user());
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',     [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
     Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
});

// Admin Protected Routes
Route::middleware(['auth:sanctum', 'admin.auth'])->prefix('admin')->group(function () {
    Route::post('/logout',     [AdminController::class, 'logout']);
    Route::post('/change-password', [AdminController::class, 'changePassword']);
    Route::get('/profile', [AdminController::class, 'profile']);
    Route::post('/profile', [AdminController::class, 'updateProfile']); // Changed from PUT to POST for multipart
});

use App\Http\Controllers\BoardingHouseController;
use App\Http\Controllers\BoardingHouseImageController;

// public read
Route::prefix('kosan')->group(function () {
    Route::get('nearby', [BoardingHouseController::class, 'nearby']);
    Route::get('search', [BoardingHouseController::class, 'search']);

    Route::get('{id}',   [BoardingHouseController::class, 'show']); // <-- detail
    Route::get('{id}/images', [BoardingHouseImageController::class, 'index']); // get images
});

// protected write (butuh token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/kosan', [BoardingHouseController::class, 'store']);
    Route::put('/kosan/{kosan}', [BoardingHouseController::class, 'update']);
    Route::delete('/kosan/{kosan}', [BoardingHouseController::class, 'destroy']);
    
    // Image management routes
    Route::post('/kosan/{id}/images', [BoardingHouseImageController::class, 'upload']);
    Route::put('/kosan/{id}/images/{imageId}/primary', [BoardingHouseImageController::class, 'setPrimary']);
    Route::delete('/kosan/{id}/images/{imageId}', [BoardingHouseImageController::class, 'destroy']);
    Route::put('/kosan/{id}/images/order', [BoardingHouseImageController::class, 'updateOrder']);
});
