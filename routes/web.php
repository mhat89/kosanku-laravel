<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/**
 * ===========================
 *  AUTH (WEB)
 * ===========================
 */

// Login (Web)
Route::get('/login',  [AuthController::class, 'showLoginForm'])->name('login.show');
Route::post('/login', [AuthController::class, 'loginWeb'])->name('login.post');

// Logout (Web)
Route::post('/logout', [AuthController::class, 'logoutWeb'])->name('logout.web');

// Forgot Password (Web 3-step: email -> otp -> reset)
Route::get('/forgot',        [AuthController::class, 'showForgotForm'])->name('forgot.show');
Route::post('/forgot',       [AuthController::class, 'forgotPasswordWeb'])->name('forgot.post');

Route::get('/forgot/otp',    [AuthController::class, 'showOtpForm'])->name('forgot.otp.show');
Route::post('/forgot/otp',   [AuthController::class, 'otpNextWeb'])->name('forgot.otp.post');

Route::get('/forgot/reset',  [AuthController::class, 'showResetForm'])->name('forgot.reset.show');
Route::post('/forgot/reset', [AuthController::class, 'verifyForgotOtpWeb'])->name('forgot.reset.post');

// Resend OTP (Web)
Route::post('/forgot/resend-otp', [AuthController::class, 'resendOtpWeb'])->name('forgot.resend');


/**
 * ===========================
 *  APP PAGES
 * ===========================
 */

// Dashboard (contoh: butuh token di session)
Route::get('/dashboard', function () {
    if (!session('api_token')) {
        return redirect()->route('login.show')->with('error', 'Silakan login dulu');
    }
    return view('authweb.dashboard');
})->name('dashboard.show');

// Root → arahkan ke login
Route::get('/', fn () => redirect()->route('login.show'));

// Debug route untuk melihat data kosan
Route::get('/debug/kosan', function () {
    $kosan = \App\Models\BoardingHouse::select('id', 'name', 'latitude', 'longitude', 'address')->get();
    return response()->json($kosan);
});
