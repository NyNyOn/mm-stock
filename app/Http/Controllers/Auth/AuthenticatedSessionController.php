<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

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
        // 1. ตรวจสอบข้อมูลจากฟอร์ม
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 2. ค้นหาผู้ใช้ในฐานข้อมูล sync_ldap
        $user = User::where('username', $request->username)->first();

        // 3. ตรวจสอบ Username และสร้างรหัสผ่านตามสูตรเพื่อเปรียบเทียบ
        if (!$user) {
            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        $firstChar = mb_substr($user->username, 0, 1);
        $thirdChar = mb_substr($user->username, 2, 1);
        $expectedPassword = $firstChar . $thirdChar . $user->employeecode;

        if ($request->password !== $expectedPassword) {
            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        // 4. สั่งให้ Laravel ล็อกอินผู้ใช้คนนี้เข้าระบบ
        Auth::login($user, $request->boolean('remember'));

        // 5. สร้าง Session ใหม่
        $request->session()->regenerate();

        // 6. ตรวจสอบสิทธิ์และ Redirect ไปยังหน้าที่เหมาะสม
        if (Auth::user()->can('dashboard:view')) {
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
