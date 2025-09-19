<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\AdminEmailOtp;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Mail\PasswordChangedMail;

class AdminController extends Controller
{
    /** TTL OTP dalam menit */
    private int $otpTtl = 15; // 15 menit

    /** ===== Utility dasar ===== */

    private function hashOtp(string $code): string
    {
        // Hash stabil; di production bisa ganti ke password_hash + password_verify jika mau
        return hash('sha256', $code . config('app.key'));
    }

    /** Bersihkan OTP lama (expired / belum dipakai) milik admin */
    private function clearOldOtps(Admin $admin): void
    {
        AdminEmailOtp::where('admin_id', $admin->id)
            ->where(function ($q) {
                $q->whereNull('consumed_at')
                  ->orWhere('expires_at', '<', now());
            })
            ->delete();
    }

    /** Terbitkan OTP baru (simpan hash + kirim email). $plainOut opsional untuk testing */
    private function issueOtp(Admin $admin, ?string &$plainOut = null): void
    {
        $plain = (string) random_int(100000, 999999);
        $plainOut = $plain;

        // invalidasi OTP lama yang belum dipakai
        AdminEmailOtp::where('admin_id', $admin->id)->whereNull('consumed_at')->delete();

        // simpan hash + expiry
        AdminEmailOtp::create([
            'id'         => (string) Str::uuid(),
            'admin_id'   => $admin->id,
            'code_hash'  => $this->hashOtp($plain),
            'expires_at' => now()->addMinutes($this->otpTtl),
        ]);

        // kirim email (MailHog di dev)
        try {
            Mail::to($admin->email)->send(new OtpMail($plain, $this->otpTtl));
        } catch (\Throwable $e) {
            Log::error('ADMIN MAIL SEND FAILED: ' . $e->getMessage());
        }

        // Dev helper: log ke file agar mudah tes tanpa buka email
        if (app()->environment('local')) {
            Log::info("[DEV] ADMIN OTP {$admin->email}: {$plain}");
        }
    }

    /** ===== Anti-bruteforce (suspend via Cache) ===== */

    private function isSuspended(string $key): ?Carbon
    {
        $until = Cache::get("suspend:admin:$key");
        return $until ? Carbon::parse($until) : null;
    }

    private function suspend(string $key, int $minutes): void
    {
        $until = now()->addMinutes($minutes);
        Cache::put("suspend:admin:$key", $until->toIso8601String(), $minutes * 60);
        Cache::forget("fail:admin:$key"); // reset counter
    }

    /** Tambah fail counter; kalau >= max → suspend */
    private function recordFail(string $key, int $maxAttempts, int $suspendMinutes): int
    {
        $fails = (int) Cache::increment("fail:admin:$key");
        // pastikan TTL counter gagal (mis. 15 menit), biar tidak akumulasi selamanya
        if ($fails === 1) {
            Cache::put("fail:admin:$key", 1, 15 * 60);
        }
        if ($fails >= $maxAttempts) {
            $this->suspend($key, $suspendMinutes);
        }
        return $fails;
    }

    private function clearFail(string $key): void
    {
        Cache::forget("fail:admin:$key");
    }

    /** ====== Endpoints ====== */

    /** Register admin → status PENDING → kirim OTP */
    public function register(Request $r)
    {
        $data = $r->validate([
            'email'     => 'required|email',
            'password'  => 'required|min:6',
            'full_name' => 'nullable|string',
        ]);

        $email = strtolower($data['email']);
        if (Admin::where('email', $email)->exists()) {
            return response()->json(['message' => 'Email admin sudah terdaftar'], 409);
        }

        $admin = Admin::create([
            'id'        => (string) Str::uuid(),
            'email'     => $email,
            'password'  => Hash::make($data['password']),
            'full_name' => $data['full_name'] ?? null,
            'status'    => 'PENDING',
        ]);

        // kirim OTP pertama
        $this->clearOldOtps($admin);
        $this->issueOtp($admin);

        return response()->json(['message' => 'OTP admin telah dikirim ke email.']);
    }

    /** Kirim ulang OTP (cooldown 60 detik), hanya untuk admin PENDING */
    public function resendOtp(Request $r)
    {
        $data = $r->validate(['email' => 'required|email']);
        $email = strtolower($data['email']);

        $admin = Admin::where('email', $email)->first();
        if (!$admin) return response()->json(['message' => 'Email admin tidak terdaftar'], 404);
        if ($admin->status === 'ACTIVE') return response()->json(['message' => 'Akun admin sudah aktif'], 409);

        // cooldown 60s
        $last = AdminEmailOtp::where('admin_id', $admin->id)->orderByDesc('created_at')->first();
        if ($last && $last->created_at->gt(now()->subSeconds(60))) {
            return response()->json(['message' => 'Terlalu sering. Coba lagi sebentar.'], 429);
        }

        $this->clearOldOtps($admin);
        $this->issueOtp($admin);

        return response()->json(['message' => 'OTP admin baru dikirim.']);
    }

    /** Verifikasi OTP → aktifkan admin; kalau expired → kirim OTP baru */
    public function verifyOtp(Request $r)
    {
        $data = $r->validate([
            'email' => 'required|email',
            'code'  => 'required|string',
        ]);

        $email = strtolower($data['email']);
        $admin = Admin::where('email', $email)->first();
        if (!$admin) return response()->json(['message' => 'Email admin tidak terdaftar'], 401);

        $otp = AdminEmailOtp::where('admin_id', $admin->id)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$otp) {
            // tidak ada OTP aktif → kirim baru agar UX lancar
            $this->clearOldOtps($admin);
            $this->issueOtp($admin);
            return response()->json(['message' => 'OTP tidak ditemukan. OTP admin baru telah dikirim.'], 401);
        }

        if (now()->greaterThan($otp->expires_at)) {
            // expired → kirim OTP baru
            $this->clearOldOtps($admin);
            $this->issueOtp($admin);
            return response()->json(['message' => 'OTP expired. OTP admin baru telah dikirim ke email.'], 401);
        }

        if (!hash_equals($otp->code_hash, $this->hashOtp($data['code']))) {
            return response()->json(['message' => 'OTP salah'], 401);
        }

        // OTP valid
        $otp->update(['consumed_at' => now()]);
        if ($admin->status !== 'ACTIVE') {
            $admin->update(['status' => 'ACTIVE']);
        }

        // langsung berikan token untuk sesi pertama
        $token = $admin->createToken('admin-mobile')->plainTextToken;
        return response()->json([
            'message' => 'Verifikasi admin berhasil. Akun aktif.',
            'data' => [
                'accessToken' => $token
            ]
        ]);
    }

    /** Login admin:
     * - Jika admin PENDING → selalu kirim OTP baru, blokir login (409)
     * - Jika salah password 5× → suspend 10 menit (423)
     */
    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required']);
        $email = strtolower($data['email']);

        $admin = Admin::where('email', $email)->first();

        $maxLogin = (int) (env('MAX_LOGIN_ATTEMPTS', 5));
        $suspendMin = (int) (env('SUSPEND_MINUTES', 10));
        $key = "login:$email";

        if ($until = $this->isSuspended($key)) {
            $mins = now()->diffInMinutes($until, false);
            return response()->json([
                'message' => 'Akun admin sementara dikunci karena terlalu banyak percobaan login gagal.',
                'retry_after_minutes' => max($mins, 1),
            ], 423);
        }

        if (!$admin || !Hash::check($data['password'], $admin->password)) {
            $fails = $this->recordFail($key, $maxLogin, $suspendMin);
            if ($fails >= $maxLogin) {
                return response()->json([
                    'message' => "Terlalu banyak percobaan gagal. Akun admin dikunci $suspendMin menit.",
                    'retry_after_minutes' => $suspendMin,
                ], 423);
            }
            return response()->json([
                'message' => 'Email atau password admin salah',
                'remaining_attempts' => max($maxLogin - $fails, 0),
            ], 401);
        }

        // password benar → bersihkan counter gagal
        $this->clearFail($key);

        if ($admin->status !== 'ACTIVE') {
            // selalu kirim OTP baru agar admin tidak kebingungan
            $this->clearOldOtps($admin);
            $this->issueOtp($admin);
            return response()->json(['message' => 'Akun admin belum aktif. OTP baru telah dikirim ke email.'], 409);
        }

        $token = $admin->createToken('admin-mobile')->plainTextToken;
        return response()->json([
            'data' => [
                'accessToken' => $token,
                'admin' => [
                    'id' => $admin->id,
                    'email' => $admin->email,
                    'full_name' => $admin->full_name,
                    'birth_date' => $admin->birth_date,
                    'image' => $admin->image,
                    'gender' => $admin->gender,
                    'status' => $admin->status
                ]
            ]
        ]);
    }

    /** Lupa password admin: kirim OTP reset */
    public function forgotPassword(Request $r)
    {
        $data = $r->validate(['email' => 'required|email']);
        $email = strtolower($data['email']);

        $admin = Admin::where('email', $email)->first();
        if (!$admin) return response()->json(['message' => 'Email admin tidak terdaftar'], 404);

        // jika jalur OTP reset disuspend, blokir
        $suspendKey = "fp:otp:$email";
        if ($until = $this->isSuspended($suspendKey)) {
            $mins = now()->diffInMinutes($until, false);
            return response()->json([
                'message' => 'Percobaan OTP reset admin sedang dikunci sementara.',
                'retry_after_minutes' => max($mins, 1),
            ], 423);
        }

        // cooldown resend 60 detik (opsional)
        $last = AdminEmailOtp::where('admin_id', $admin->id)->orderByDesc('created_at')->first();
        if ($last && $last->created_at->gt(now()->subSeconds(60))) {
            return response()->json(['message' => 'Terlalu sering. Coba lagi sebentar.'], 429);
        }

        // set admin jadi PENDING selama proses reset
        if ($admin->status !== 'PENDING') {
            $admin->update(['status' => 'PENDING']);
        }

        $this->clearOldOtps($admin);
        $this->issueOtp($admin);

        return response()->json(['message' => 'OTP reset password admin telah dikirim ke email.']);
    }

    /** Verifikasi OTP reset + set password baru admin
     *  Gagal OTP 5× → suspend 10 menit (khusus kanal forgot)
     */
    public function verifyForgotOtp(Request $r)
    {
        $data = $r->validate([
            'email'                 => 'required|email',
            'code'                  => 'required|string',
            'password'              => 'required|min:6|confirmed', // kirim juga password_confirmation
        ]);

        $email = strtolower($data['email']);
        $admin = Admin::where('email', $email)->first();
        if (!$admin) return response()->json(['message' => 'Email admin tidak terdaftar'], 404);

        $maxOtp = (int) (env('MAX_OTP_ATTEMPTS', 5));
        $suspendMin = (int) (env('SUSPEND_MINUTES', 10));
        $key = "fp:otp:$email";

        if ($until = $this->isSuspended($key)) {
            $mins = now()->diffInRealSeconds($until, false);
            return response()->json([
                'message' => 'Percobaan OTP admin untuk reset sedang dikunci sementara.',
                'retry_after_seconds' => max($mins, 1)
            ], 423);
        }

        $otp = AdminEmailOtp::where('admin_id', $admin->id)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$otp || now()->greaterThan($otp->expires_at)) {
            $fails = $this->recordFail($key, $maxOtp, $suspendMin);
            if ($fails >= $maxOtp) {
                return response()->json([
                    'message' => "Terlalu banyak percobaan OTP admin gagal. Dikunci $suspendMin menit."
                ], 423);
            }
            return response()->json(['message' => 'OTP admin tidak ditemukan / expired'], 401);
        }

        if (!hash_equals($otp->code_hash, $this->hashOtp($data['code']))) {
            $fails = $this->recordFail($key, $maxOtp, $suspendMin);
            if ($fails >= $maxOtp) {
                return response()->json([
                    'message' => "Terlalu banyak percobaan OTP admin gagal. Dikunci $suspendMin menit."
                ], 423);
            }
            return response()->json([
                'message' => 'OTP admin salah',
                'remaining_attempts' => max($maxOtp - $fails, 0),
            ], 401);
        }

        // OTP valid
        $this->clearFail($key);
        $otp->update(['consumed_at' => now()]);

        // ganti password
        $admin->update(['password' => Hash::make($data['password'])]);

        // aktifkan kembali akun admin
        if ($admin->status !== 'ACTIVE') {
            $admin->update(['status' => 'ACTIVE']);
        }

        return response()->json([
            'message' => 'Password admin berhasil direset. Silakan login.'
        ], 200);
    }

    /**
     * Logout admin (hapus token yang sedang dipakai)
     */
    public function logout(Request $request)
    {
        // ambil admin yang sedang login via Sanctum
        $admin = $request->user();

        // hapus token yang sedang dipakai
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout admin berhasil'], 200);
    }

    /**
     * Ganti password admin (untuk admin yang sudah login)
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $admin = $request->user();

        // Verifikasi password lama
        if (!Hash::check($data['current_password'], $admin->password)) {
            return response()->json(['message' => 'Password lama tidak sesuai'], 401);
        }

        // Update password baru
        $admin->update([
            'password' => Hash::make($data['password'])
        ]);

        // Kirim email notifikasi (opsional)
        try {
            Mail::to($admin->email)->send(new PasswordChangedMail($admin));
        } catch (\Throwable $e) {
            Log::error('ADMIN PASSWORD CHANGED MAIL FAILED: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Password admin berhasil diubah']);
    }

    /**
     * Check if admin email exists
     */
    public function check($email)
    {
        $email = strtolower($email);
        $exists = Admin::where('email', $email)->exists();
        
        return response()->json([
            'data' => $exists,
            'message' => $exists ? 'Email admin sudah terdaftar' : 'Email admin belum terdaftar'
        ]);
    }

    /**
     * Get profile admin yang sedang login
     */
    public function profile(Request $request)
    {
        $admin = $request->user();
        
        return response()->json([
            'data' => [
                'id' => $admin->id,
                'email' => $admin->email,
                'full_name' => $admin->full_name,
                'birth_date' => $admin->birth_date,
                'image' => $admin->image,
                'gender' => $admin->gender,
                'status' => $admin->status,
                'created_at' => $admin->created_at,
                'updated_at' => $admin->updated_at
            ]
        ]);
    }

    /**
     * Update profile admin yang sedang login
     */
    public function updateProfile(Request $request)
    {
        $admin = $request->user();
        
        // Debug request data
        \Log::info('Update Profile Request Data:', [
            'all' => $request->all(),
            'files' => $request->allFiles(),
            'content_type' => $request->header('Content-Type')
        ]);
        
        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'birth_date' => 'sometimes|date|before:today',
            'gender' => 'sometimes|in:male,female|nullable',
            'image' => 'sometimes|file|mimes:jpeg,png,jpg,gif|max:2048', // Removed image validation to allow any file type for testing
        ]);

        // Handle image upload if provided
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $extension = $image->getClientOriginalExtension();
            $filename = $admin->id . '.' . $extension;
            
            // Create assets/profile directory if it doesn't exist
            $profilePath = public_path('assets/profile');
            if (!file_exists($profilePath)) {
                mkdir($profilePath, 0755, true);
            }
            
            // Delete old profile image if exists
            $oldImagePath = $profilePath . '/' . $admin->id . '.*';
            foreach (glob($oldImagePath) as $oldFile) {
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            
            // Move uploaded file to assets/profile with user ID as filename
            $image->move($profilePath, $filename);
            
            // Update image path in validated data
            $validated['image'] = 'assets/profile/' . $filename;
        }

        // Update hanya field yang dikirim
        $admin->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $admin->id,
                'email' => $admin->email,
                'full_name' => $admin->full_name,
                'birth_date' => $admin->birth_date,
                'image' => $admin->image,
                'gender' => $admin->gender,
                'status' => $admin->status,
                'created_at' => $admin->created_at,
                'updated_at' => $admin->updated_at
            ]
        ]);
    }
}