<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
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


class AuthController extends Controller
{
    /** TTL OTP dalam menit */
    private int $otpTtl = 15; // 15 menit

    /** ===== Utility dasar ===== */

    private function hashOtp(string $code): string
    {
        // Hash stabil; di production bisa ganti ke password_hash + password_verify jika mau
        return hash('sha256', $code . config('app.key'));
    }

    /** Bersihkan OTP lama (expired / belum dipakai) milik user */
    private function clearOldOtps(User $user): void
    {
        EmailOtp::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('consumed_at')
                  ->orWhere('expires_at', '<', now());
            })
            ->delete();
    }

    /** Terbitkan OTP baru (simpan hash + kirim email). $plainOut opsional untuk testing */
    private function issueOtp(User $user, ?string &$plainOut = null): void
    {
        $plain = (string) random_int(100000, 999999);
        $plainOut = $plain;

        // invalidasi OTP lama yang belum dipakai
        EmailOtp::where('user_id', $user->id)->whereNull('consumed_at')->delete();

        // simpan hash + expiry
        EmailOtp::create([
            'id'         => (string) Str::uuid(),
            'user_id'    => $user->id,
            'code_hash'  => $this->hashOtp($plain),
            'expires_at' => now()->addMinutes($this->otpTtl),
        ]);

        // kirim email (MailHog di dev)
        try {
            Mail::to($user->email)->send(new OtpMail($plain, $this->otpTtl));
        } catch (\Throwable $e) {
            Log::error('MAIL SEND FAILED: ' . $e->getMessage());
        }

        // Dev helper: log ke file agar mudah tes tanpa buka email
        if (app()->environment('local')) {
            Log::info("[DEV] OTP {$user->email}: {$plain}");
        }
    }

    /** ===== Anti-bruteforce (suspend via Cache) ===== */

    private function isSuspended(string $key): ?Carbon
    {
        $until = Cache::get("suspend:$key");
        return $until ? Carbon::parse($until) : null;
    }

    private function suspend(string $key, int $minutes): void
    {
        $until = now()->addMinutes($minutes);
        Cache::put("suspend:$key", $until->toIso8601String(), $minutes * 60);
        Cache::forget("fail:$key"); // reset counter
    }

    /** Tambah fail counter; kalau >= max → suspend */
    private function recordFail(string $key, int $maxAttempts, int $suspendMinutes): int
    {
        $fails = (int) Cache::increment("fail:$key");
        // pastikan TTL counter gagal (mis. 15 menit), biar tidak akumulasi selamanya
        if ($fails === 1) {
            Cache::put("fail:$key", 1, 15 * 60);
        }
        if ($fails >= $maxAttempts) {
            $this->suspend($key, $suspendMinutes);
        }
        return $fails;
    }

    private function clearFail(string $key): void
    {
        Cache::forget("fail:$key");
    }

    /** ====== Endpoints ====== */

    /** Register user → status PENDING → kirim OTP */
    public function register(Request $r)
    {
        $data = $r->validate([
            'email'     => 'required|email',
            'password'  => 'required|min:6',
            'full_name' => 'nullable|string',
        ]);

        $email = strtolower($data['email']);
        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'Email sudah terdaftar'], 409);
        }

        $user = User::create([
            'id'        => (string) Str::uuid(),
            'email'     => $email,
            'password'  => Hash::make($data['password']),
            'full_name' => $data['full_name'] ?? null,
            'status'    => 'PENDING',
        ]);

        // kirim OTP pertama
        $this->clearOldOtps($user);
        $this->issueOtp($user);

        return response()->json(['message' => 'OTP telah dikirim ke email.']);
    }

    /** Kirim ulang OTP (cooldown 60 detik), hanya untuk user PENDING */
    public function resendOtp(Request $r)
    {
        $data = $r->validate(['email' => 'required|email']);
        $email = strtolower($data['email']);

        $user = User::where('email', $email)->first();
        if (!$user) return response()->json(['message' => 'Email tidak terdaftar'], 404);
        if ($user->status === 'ACTIVE') return response()->json(['message' => 'Akun sudah aktif'], 409);

        // cooldown 60s
        $last = EmailOtp::where('user_id', $user->id)->orderByDesc('created_at')->first();
        if ($last && $last->created_at->gt(now()->subSeconds(60))) {
            return response()->json(['message' => 'Terlalu sering. Coba lagi sebentar.'], 429);
        }

        $this->clearOldOtps($user);
        $this->issueOtp($user);

        return response()->json(['message' => 'OTP baru dikirim.']);
    }

    /** Verifikasi OTP → aktifkan user; kalau expired → kirim OTP baru */
    public function verifyOtp(Request $r)
    {
        $data = $r->validate([
            'email' => 'required|email',
            'code'  => 'required|string',
        ]);

        $email = strtolower($data['email']);
        $user = User::where('email', $email)->first();
        if (!$user) return response()->json(['message' => 'Email tidak terdaftar'], 401);

        $otp = EmailOtp::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$otp) {
            // tidak ada OTP aktif → kirim baru agar UX lancar
            $this->clearOldOtps($user);
            $this->issueOtp($user);
            return response()->json(['message' => 'OTP tidak ditemukan. OTP baru telah dikirim.'], 401);
        }

        if (now()->greaterThan($otp->expires_at)) {
            // expired → kirim OTP baru
            $this->clearOldOtps($user);
            $this->issueOtp($user);
            return response()->json(['message' => 'OTP expired. OTP baru telah dikirim ke email.'], 401);
        }

        if (!hash_equals($otp->code_hash, $this->hashOtp($data['code']))) {
            return response()->json(['message' => 'OTP salah'], 401);
        }

        // OTP valid
        $otp->update(['consumed_at' => now()]);
        if ($user->status !== 'ACTIVE') {
            $user->update(['status' => 'ACTIVE']);
        }

        // langsung berikan token untuk sesi pertama
        $token = $user->createToken('mobile')->plainTextToken;
        return response()->json(['message' => 'Verifikasi berhasil. Akun aktif.', 'accessToken' => $token]);
    }

    /** Login:
     * - Jika user PENDING → selalu kirim OTP baru, blokir login (409)
     * - Jika salah password 5× → suspend 10 menit (423)
     */
    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required']);
        $email = strtolower($data['email']);

        $user = User::where('email', $email)->first();

        $maxLogin = (int) (env('MAX_LOGIN_ATTEMPTS', 5));
        $suspendMin = (int) (env('SUSPEND_MINUTES', 10));
        $key = "login:$email";

        if ($until = $this->isSuspended($key)) {
            $mins = now()->diffInMinutes($until, false);
            return response()->json([
                'message' => 'Akun sementara dikunci karena terlalu banyak percobaan login gagal.',
                'retry_after_minutes' => max($mins, 1),
            ], 423);
        }

        if (!$user || !Hash::check($data['password'], $user->password)) {
            $fails = $this->recordFail($key, $maxLogin, $suspendMin);
            if ($fails >= $maxLogin) {
                return response()->json([
                    'message' => "Terlalu banyak percobaan gagal. Akun dikunci $suspendMin menit.",
                    'retry_after_minutes' => $suspendMin,
                ], 423);
            }
            return response()->json([
                'message' => 'Email atau password salah',
                'remaining_attempts' => max($maxLogin - $fails, 0),
            ], 401);
        }

        // password benar → bersihkan counter gagal
        $this->clearFail($key);

        if ($user->status !== 'ACTIVE') {
            // selalu kirim OTP baru agar user tidak kebingungan
            $this->clearOldOtps($user);
            $this->issueOtp($user);
            return response()->json(['message' => 'Akun belum aktif. OTP baru telah dikirim ke email.'], 409);
        }

        $token = $user->createToken('mobile')->plainTextToken;
        return response()->json(['accessToken' => $token]);
    }

    /** Lupa password: kirim OTP reset */
    public function forgotPassword(Request $r)
    {
        $data = $r->validate(['email' => 'required|email']);
        $email = strtolower($data['email']);

        $user = User::where('email', $email)->first();
        if (!$user) return response()->json(['message' => 'Email tidak terdaftar'], 404);

        // jika jalur OTP reset disuspend, blokir
        $suspendKey = "fp:otp:$email";
        if ($until = $this->isSuspended($suspendKey)) {
            $mins = now()->diffInMinutes($until, false);
            return response()->json([
                'message' => 'Percobaan OTP reset sedang dikunci sementara.',
                'retry_after_minutes' => max($mins, 1),
            ], 423);
        }

        // cooldown resend 60 detik (opsional)
        $last = EmailOtp::where('user_id', $user->id)->orderByDesc('created_at')->first();
        if ($last && $last->created_at->gt(now()->subSeconds(60))) {
            return response()->json(['message' => 'Terlalu sering. Coba lagi sebentar.'], 429);
        }

        // set user jadi PENDING selama proses reset
        if ($user->status !== 'PENDING') {
            $user->update(['status' => 'PENDING']);
        }

        $this->clearOldOtps($user);
        $this->issueOtp($user);

        return response()->json(['message' => 'OTP reset password telah dikirim ke email.']);
    }

    /** Verifikasi OTP reset + set password baru
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
        $user = User::where('email', $email)->first();
        if (!$user) return response()->json(['message' => 'Email tidak terdaftar'], 404);

        $maxOtp = (int) (env('MAX_OTP_ATTEMPTS', 5));
        $suspendMin = (int) (env('SUSPEND_MINUTES', 10));
        $key = "fp:otp:$email";

        if ($until = $this->isSuspended($key)) {
            $mins = now()->diffInRealSeconds($until, false);
            return response()->json([
                'message' => 'Percobaan OTP untuk reset sedang dikunci sementara.',
                'retry_after_seconds' => max($mins, 1)
            ], 423);
        }

        $otp = EmailOtp::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$otp || now()->greaterThan($otp->expires_at)) {
            $fails = $this->recordFail($key, $maxOtp, $suspendMin);
            if ($fails >= $maxOtp) {
                return response()->json([
                    'message' => "Terlalu banyak percobaan OTP gagal. Dikunci $suspendMin menit."
                ], 423);
            }
            // OTP benar → reset password
            $this->clearFail($key);
            $otp->update(['consumed_at' => now()]);
            $user->update(['password' => Hash::make($data['password'])]);

            // >>> PATCH: aktifkan kembali akun setelah reset password berhasil
            if ($user->status !== 'ACTIVE') {
                $user->update(['status' => 'ACTIVE']);
            }
            return response()->json(['message' => 'OTP tidak ditemukan / expired'], 401);
        }

        if (!hash_equals($otp->code_hash, $this->hashOtp($data['code']))) {
            $fails = $this->recordFail($key, $maxOtp, $suspendMin);
            if ($fails >= $maxOtp) {
                return response()->json([
                    'message' => "Terlalu banyak percobaan OTP gagal. Dikunci $suspendMin menit."
                ], 423);
            }
            return response()->json([
                'message' => 'OTP salah',
                'remaining_attempts' => max($maxOtp - $fails, 0),
            ], 401);
        }

        // OTP valid .............................
        // (kode di atas ini: ambil $otp, cek expired, cek hash, dll)

        // === MULAI: blok sukses ===
        $this->clearFail($key);
        $otp->update(['consumed_at' => now()]);

        // ganti password
        $user->update(['password' => Hash::make($data['password'])]);

        // >>> PATCH PENTING: aktifkan kembali akun
        if ($user->status !== 'ACTIVE') {
            $user->update(['status' => 'ACTIVE']);
        }

        return response()->json([
            'message' => 'Password berhasil direset. Silakan login.'
        ], 200);
        // === SELESAI: blok sukses ===
    }

    /**
     * Logout user (hapus token yang sedang dipakai)
     */
    public function logout(Request $request)
    {
        // ambil user yang sedang login via Sanctum
        $user = $request->user();

        // hapus token yang sedang dipakai
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil'], 200);
    }

    /**
     * Logout dari semua device (hapus semua token user ini)
     */
    public function logoutAll(Request $request)
    {
        $user = $request->user();

        // hapus semua token yang dimiliki user ini
        $user->tokens()->delete();

        return response()->json(['message' => 'Logout dari semua perangkat berhasil'], 200);
    }

    /**
     * Logout dari web (Blade)
     */
    public function logoutWeb(Request $request)
    {
        if ($request->user()) {
            if ($request->user()->currentAccessToken()) {
                $request->user()->currentAccessToken()->delete();
            }
            Auth::logout();
        }

        session()->forget('api_token'); // hapus token di session

        return redirect('/login')->with('status', 'Anda berhasil logout');
    }


    // ===========================
    //     WEB FORGOT FLOW (FIX)
    // ===========================

    // STEP 1 — Tampilkan form email
    public function showForgotForm()
    {
        return view('authweb.forgot');
    }

    // STEP 1 — Submit email → kirim OTP + redirect ke halaman OTP
    public function forgotPasswordWeb(\Illuminate\Http\Request $r)
    {
        // panggil logic API yang sudah ada
        $apiRes = $this->forgotPassword($r);

        // jika berupa Response, cek status code
        if ($apiRes instanceof \Illuminate\Http\Response || $apiRes instanceof \Illuminate\Http\JsonResponse) {
            if ($apiRes->getStatusCode() >= 400) {
                $msg = json_decode($apiRes->getContent(), true)['message'] ?? 'Gagal mengirim OTP';
                return back()->withErrors(['email' => $msg])->withInput();
            }
        }

        $email = strtolower($r->input('email'));
        return redirect()->route('forgot.otp.show', ['email' => $email])
            ->with('status', 'OTP dikirim ke email.');
    }

    // STEP 2 — Tampilkan form OTP (bawa ?email=)
    public function showOtpForm(\Illuminate\Http\Request $r)
    {
        $email = $r->query('email', '');
        abort_unless($email, 404);
        return view('authweb.otp', compact('email'));
    }

    // STEP 2 — Submit OTP → teruskan ke halaman reset (OTP diverifikasi saat submit password)
    public function otpNextWeb(\Illuminate\Http\Request $r)
    {
        $data = $r->validate([
            'email' => 'required|email',
            'code'  => 'required|string',
        ]);

        // redirect dgn query email & code
        return redirect()->route('forgot.reset.show', $data);
    }

    // STEP 3 — Tampilkan form reset (bawa ?email=&code=)
    public function showResetForm(\Illuminate\Http\Request $r)
    {
        $email = $r->query('email', '');
        $code  = $r->query('code', '');
        abort_unless($email && $code, 404);
        return view('authweb.reset', compact('email','code'));
    }

    // STEP 3 — Submit password baru + verifikasi OTP (pakai logic API yang sudah ada)
    public function verifyForgotOtpWeb(\Illuminate\Http\Request $r)
    {
        $data = $r->validate([
            'email'                 => 'required|email',
            'code'                  => 'required|string',
            'password'              => 'required|min:6|confirmed',
        ]);

        $apiRes = $this->verifyForgotOtp($r);
        if ($apiRes instanceof \Illuminate\Http\Response || $apiRes instanceof \Illuminate\Http\JsonResponse) {
            $code = $apiRes->getStatusCode();
            $json = json_decode($apiRes->getContent(), true);
            if ($code >= 400) {
                return back()->withErrors(['code' => $json['message'] ?? 'Verifikasi gagal'])->withInput();
            }
        }

        // sukses
        return redirect()->route('forgot.show')
            ->with('status', 'Password berhasil direset. Akun sudah aktif, silakan login.');
    }

    // (opsional) RESEND dari halaman OTP
    public function resendOtpWeb(\Illuminate\Http\Request $r)
    {
        $r->validate(['email' => 'required|email']);
        $apiRes = $this->resendOtp($r);
        $msg = 'OTP dikirim.';
        $isErr = false;

        if ($apiRes instanceof \Illuminate\Http\Response || $apiRes instanceof \Illuminate\Http\JsonResponse) {
            $json = json_decode($apiRes->getContent(), true);
            $msg = $json['message'] ?? $msg;
            $isErr = $apiRes->getStatusCode() >= 400;
        }

        return back()->with($isErr ? 'error' : 'status', $msg);
    }

    /**
     * Tampilkan form login (web)
     */
    public function showLoginForm()
    {
        return view('authweb.login');
    }

    public function loginWeb(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // panggil login API logic
        $apiRes = $this->login($request);

        if ($apiRes instanceof \Illuminate\Http\JsonResponse || $apiRes instanceof \Illuminate\Http\Response) {
            $status = $apiRes->getStatusCode();
            $json   = json_decode($apiRes->getContent(), true);

            if ($status >= 400) {
                return back()->withErrors([
                    'email' => $json['message'] ?? 'Login gagal'
                ])->withInput();
            }

            // login berhasil
            session(['api_token' => $json['accessToken'] ?? null]);
            return redirect()->route('dashboard.show')->with('status', 'Login berhasil');
        }

        // fallback: kalau $apiRes bukan response
        return back()->withErrors(['email' => 'Login gagal (tidak dikenal)']);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'old_password' => ['required'],
            'password' => ['required', 'min:6', 'confirmed'], // expects password_confirmation
        ]);

        if (!Hash::check($data['old_password'], $user->password)) {
            throw ValidationException::withMessages([
                'old_password' => ['Password lama salah.'],
            ]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        // kirim email notifikasi
        try {
            Mail::to($user->email)->queue(new PasswordChangedMail($user));
        } catch (\Throwable $e) {
            // log jika perlu, tapi jangan gagalkan request user
            \Log::warning('Email password changed gagal: '.$e->getMessage());
        }

        return response()->json([
            'message' => 'Password berhasil diubah.',
        ]);
    }


}
