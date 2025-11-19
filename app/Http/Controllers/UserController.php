<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * แสดงหน้าตารางรายชื่อผู้ใช้ทั้งหมด
     */
    public function index()
    {
        // ดึงข้อมูลผู้ใช้ทั้งหมด พร้อมกับข้อมูลกลุ่ม (Role) มาด้วย
        // การใช้ with('group') จะช่วยลดจำนวน query ทำให้เว็บเร็วขึ้น
        //ini_set('display_errors', 1);
        //error_reporting(E_ALL);
        $users = User::with('group')->latest()->paginate(20);

        // ✅✅✅ ผมเพิ่ม dd() ให้ตรงนี้แล้วครับ ✅✅✅
        // โค้ดจะหยุดทำงานที่บรรทัดนี้ และแสดงข้อมูลในตัวแปร $users ออกมา
        //dd($users);

        // บรรทัดด้านล่างนี้จะยังไม่ถูกรัน จนกว่าเราจะลบ dd() ออก
        return view('users.index', compact('users'));
    }

    /**
     * แสดงหน้าฟอร์มสำหรับแก้ไขผู้ใช้
     */
    public function edit(User $user)
    {
        // ดึงข้อมูลกลุ่มทั้งหมด เพื่อเอาไปสร้าง dropdown
        $groups = UserGroup::all();

        return view('users.edit', compact('user', 'groups'));
    }

    /**
     * อัปเดตข้อมูลผู้ใช้ (เปลี่ยนกลุ่ม/Role)
     */
    public function update(Request $request, User $user)
    {
        // ตรวจสอบข้อมูลที่ส่งมาว่า user_group_id ต้องมี และต้องเป็นตัวเลข
        $request->validate([
            'user_group_id' => 'required|integer|exists:user_groups,id'
        ]);

        // อัปเดต user_group_id ของผู้ใช้คนนี้
        $user->update([
            'user_group_id' => $request->user_group_id
        ]);

        // ส่งกลับไปหน้า index พร้อมข้อความแจ้งเตือน
        return redirect()->route('users.index')->with('success', 'อัปเดตสิทธิ์ผู้ใช้สำเร็จ');
    }
}
