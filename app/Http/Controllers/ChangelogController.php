<?php

namespace App\Http\Controllers;

use App\Models\Changelog;
use Illuminate\Http\Request; // ✅ 1. เพิ่ม use Request
use Illuminate\Validation\Rule; // ✅ 2. เพิ่ม use Rule (สำหรับ Validation)

class ChangelogController extends Controller
{
    /**
     * แสดงหน้าประวัติการอัปเดต
     */
    public function index()
    {
        $changelogs = Changelog::orderBy('change_date', 'desc')->get();

        $groupedLogs = $changelogs->groupBy(function($log) {
            $date = $log->change_date ?? now(); 
            return $date->format('F Y');
        });

        // ‼️ 3. ส่ง $changelogs (ตัวเต็ม) ไปด้วย เผื่อกรณีข้อมูลว่าง
        return view('changelog.index', compact('groupedLogs', 'changelogs'));
    }

    /**
     * ✅✅✅ 4. เพิ่มฟังก์ชัน store() ใหม่ทั้งหมด ✅✅✅
     * บันทึกข้อมูล Changelog ใหม่จาก Modal
     */
    public function store(Request $request)
    {
        // 1. ตรวจสอบสิทธิ์ (เฉพาะ Admin)
        $this->authorize('permission:manage');

        // 2. ตรวจสอบข้อมูล
        $request->validate([
            'type' => ['required', Rule::in(['feature', 'bugfix', 'improvement'])],
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'version' => 'nullable|string|max:50',
            'files_modified_text' => 'nullable|string', // รับค่าจาก Textarea
        ]);

        // 3. ประมวลผล 'files_modified_text' (จาก Textarea)
        $files_modified = null;
        if ($request->filled('files_modified_text')) {
            $files_modified = array_filter( // ลบค่าว่าง (ถ้ากรอกบรรทัดเปล่า)
                array_map('trim', // ลบช่องว่างหน้า/หลัง
                    explode("\n", $request->input('files_modified_text')) // แยกด้วยการขึ้นบรรทัดใหม่
                )
            );
        }

        // 4. บันทึกข้อมูล
        Changelog::create([
            'change_date' => now(), // ใช้วันที่ปัจจุบัน
            'version' => $request->input('version'),
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'files_modified' => $files_modified,
        ]);

        // 5. กลับไปหน้าเดิม พร้อมข้อความ "สำเร็จ"
        return back()->with('success', 'เพิ่มประวัติการอัปเดตเรียบร้อยแล้ว!');
    }
}