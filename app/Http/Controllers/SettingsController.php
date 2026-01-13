<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('settings.index');
    }

    /**
     * (Method เดิม) บันทึกค่าผู้สั่งอัตโนมัติสำหรับใบสั่งซื้อตามรอบ
     */
    public function updateAutomationRequester(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:depart_it_db.sync_ldap,id',
        ]);

        try {
            Setting::updateOrCreate(
                ['key' => 'automation_requester_id'],
                ['value' => $request->user_id]
            );

            return response()->json(['message' => 'บันทึกผู้สั่งอัตโนมัติเรียบร้อยแล้ว']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }

    /**
     * (Method ใหม่) บันทึกค่าผู้สั่งอัตโนมัติสำหรับใบสั่งซื้อตาม Job/GLPI
     */
    public function updateAutomationJobRequester(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:depart_it_db.sync_ldap,id']);

        try {
            Setting::updateOrCreate(
                ['key' => 'automation_job_requester_id'],
                ['value' => $request->user_id]
            );

            return response()->json(['message' => 'บันทึกผู้สั่งสำหรับ PO ตาม Job สำเร็จแล้ว']);
        } catch (\Exception $e) {
            Log::error('Error saving automation job requester: ' . $e->getMessage());
            return response()->json(['message' => 'เกิดข้อผิดพลาดในการบันทึก'], 500);
        }
    }

    /**
     * (Method ใหม่) บันทึกตารางเวลาตรวจสอบสต็อกอัตโนมัติ
     */
    public function updateAutoPoSchedule(Request $request)
    {
        $request->validate([
            'day' => 'required|integer|min:1|max:31',
            'time' => 'required|date_format:H:i',
        ]);

        try {
            Setting::updateOrCreate(['key' => 'auto_po_schedule_day'], ['value' => $request->day]);
            Setting::updateOrCreate(['key' => 'auto_po_schedule_time'], ['value' => $request->time]);

            return response()->json(['message' => 'บันทึกตารางเวลาตรวจสอบสต็อกเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            Log::error('Error saving auto po schedule: ' . $e->getMessage());
            return response()->json(['message' => 'เกิดข้อผิดพลาดในการบันทึก'], 500);
        }
    }

    /**
     * (Method ใหม่สำหรับ AJAX) ดึงรายชื่อ User พร้อมกับค่าที่ตั้งไว้ปัจจุบัน
     */
    public function getLdapUsersWithSetting(Request $request, $settingKey)
    {
        try {
            $users = User::where('status', '1')
                           ->orderBy('fullname', 'asc')
                           ->get(['id', 'fullname', 'username']);

            $currentSetting = Setting::find($settingKey);

            return response()->json([
                'users' => $users,
                'current_requester_id' => $currentSetting ? $currentSetting->value : null
            ]);
        } catch (\Exception $e) {
            Log::error("Error in getLdapUsersWithSetting for key {$settingKey}: " . $e->getMessage());
            return response()->json(['message' => 'เกิดข้อผิดพลาดในการโหลดรายชื่อผู้ใช้'], 500);
        }
    }
    /**
     * (Method ใหม่) บันทึกการตั้งค่าอนุญาตให้ User กดขอคืนของ
     */
    public function updateReturnRequestSetting(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);

        try {
            Setting::updateOrCreate(
                ['key' => 'allow_user_return_request'],
                ['value' => $request->enabled ? '1' : '0']
            );

            return response()->json(['message' => 'บันทึกการตั้งค่าเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            Log::error('Error saving return request setting: ' . $e->getMessage());
            return response()->json(['message' => 'เกิดข้อผิดพลาดในการบันทึก'], 500);
        }
    }
}
