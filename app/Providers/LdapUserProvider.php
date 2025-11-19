<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\LdapUser;

class LdapUserProvider implements UserProvider
{
    /**
     * ดึงข้อมูลผู้ใช้ด้วย ID (ฟังก์ชันมาตรฐานของ Laravel)
     */
    public function retrieveById($identifier)
    {
        return LdapUser::find($identifier);
    }

    /**
     * ดึงข้อมูลผู้ใช้ด้วย Token (สำหรับฟีเจอร์ "จดจำฉันไว้")
     */
    public function retrieveByToken($identifier, $token)
    {
        // หากต้องการใช้ "Remember Me" ตาราง sync_ldap ต้องมีคอลัมน์ remember_token
        return LdapUser::where('id', $identifier)
                       ->where('remember_token', $token)
                       ->first();
    }

    /**
     * อัปเดต Token สำหรับ "จดจำฉันไว้"
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->setRememberToken($token);
        $user->save();
    }

    /**
     * ดึงข้อมูลผู้ใช้จาก credentials ที่กรอกในฟอร์ม (ในที่นี้คือ username)
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials['username'])) {
            return null;
        }
        // ค้นหาผู้ใช้จาก username ที่กรอกเข้ามา
        return LdapUser::where('username', $credentials['username'])->first();
    }

    /**
     * ✅ หัวใจของ Logic: ตรวจสอบรหัสผ่านตามกฎพิเศษของคุณ
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $plainPassword = $credentials['password']; // รหัสผ่านที่ผู้ใช้พิมพ์
        $username = $user->username;
        $employeeCode = $user->employeecode; // ตรวจสอบว่าคอลัมน์นี้ชื่อ employeecode จริงๆ

        // ตรวจสอบว่ามีข้อมูลที่จำเป็นครบถ้วนหรือไม่
        if (!$username || !$employeeCode || strlen($username) < 3) {
            return false;
        }

        // สร้างรหัสผ่านที่ถูกต้องตามตรรกะของคุณ
        // (ตัวอักษรแรกของ username + ตัวอักษรที่ 3 ของ username + employeecode)
        $correctPassword = substr($username, 0, 1) . substr($username, 2, 1) . $employeeCode;

        // เปรียบเทียบรหัสผ่านที่ผู้ใช้พิมพ์กับรหัสผ่านที่เราสร้างขึ้น
        return $plainPassword === $correctPassword;
    }
}