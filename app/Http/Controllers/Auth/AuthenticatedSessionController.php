<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        Log::info("🔍 [LOGIN PROCESS] User attempting login: " . $request->input('username'));

        // 1. ตรวจสอบข้อมูลจากฟอร์ม (ตามโค้ดของคุณ)
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 2. ค้นหาผู้ใช้ในฐานข้อมูล sync_ldap
        $user = User::where('username', $request->username)->first();

        // 3. ตรวจสอบ Username และสร้างรหัสผ่านตามสูตรเพื่อเปรียบเทียบ (Logic ของคุณ)
        if (!$user) {
            Log::warning("❌ [LOGIN FAILED] User not found: " . $request->username);
            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        // สูตรสร้างรหัสผ่าน: ตัวแรก + ตัวที่สาม + รหัสพนักงาน
        $firstChar = mb_substr($user->username, 0, 1);
        $thirdChar = mb_substr($user->username, 2, 1);
        $expectedPassword = $firstChar . $thirdChar . $user->employeecode;

        // เปรียบเทียบรหัสผ่าน (Plain text comparison)
        if ($request->password !== $expectedPassword) {
            Log::warning("❌ [LOGIN FAILED] Password mismatch for user: " . $request->username);
            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        // 4. สั่งให้ Laravel ล็อกอินผู้ใช้คนนี้เข้าระบบ
        Auth::login($user, $request->boolean('remember'));
        Log::info("✅ [LOGIN SUCCESS] User logged in: " . $user->username);

        // 5. สร้าง Session ใหม่
        $request->session()->regenerate();

        // ----------------------------------------------------------------------
        // 🛡️ SAFETY ZONE: ระบบ "ตำรวจเวลา" (ทำงานเงียบๆ ไม่ขัดจังหวะการล็อกอิน)
        // ----------------------------------------------------------------------
        try {
            // เช็คสิทธิ์ก่อนรัน (Admin / IT / ID 9)
            if ($user->id === 9 || $user->can('permission:manage') || $user->can('equipment:manage')) {
                
                Log::info("⏳ [TRIGGER] Starting stock check expiration...");
                
                // ใช้ callSilently เพื่อไม่ให้ output รบกวน
                Artisan::call('stock:check-expiration');
                
                Log::info("✅ [TRIGGER] Stock check triggered successfully.");
            }
        } catch (\Throwable $e) {
            // ถ้าพัง ให้แค่จด Log แล้วปล่อยผ่าน (User จะยังเข้าหน้าเว็บได้ปกติ)
            Log::error("⚠️ [TRIGGER ERROR] Failed to run stock check: " . $e->getMessage());
        }
        // ----------------------------------------------------------------------

        // 6. ตรวจสอบสิทธิ์และ Redirect ไปยังหน้าที่เหมาะสม
        if ($user->can('dashboard:view')) {
            return redirect()->intended(route('dashboard'));
        }

        return redirect()->route('user.equipment.index');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}