<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission; 

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        // ✅✅✅ START: อัปเดตรายการสิทธิ์ (จัดหมวดหมู่แล้ว) ✅✅✅
        //
        $permissions = [
            // === Dashboard ===
            ['name' => 'dashboard:view', 'description' => 'เข้าถึงหน้าแดชบอร์ดหลัก'],

            // === Equipment (อุปกรณ์/พัสดุ) ===
            ['name' => 'equipment:view', 'description' => 'ดูรายการอุปกรณ์ในคลัง (หน้า Admin)'],
            ['name' => 'equipment:create', 'description' => 'สร้างอุปกรณ์ใหม่ (ปุ่มเพิ่มอุปกรณ์)'],
            ['name' => 'equipment:edit', 'description' => 'แก้ไขจำนวนคงคลังได้'],
            ['name' => 'equipment:update', 'description' => 'อัปเดตข้อมูลอุปกรณ์ (ยกเว้นจำนวนคงคลัง)'],
            ['name' => 'equipment:delete', 'description' => 'ลบอุปกรณ์ออกจากระบบ'],
            ['name' => 'equipment:borrow', 'description' => 'เบิก-ยืมอุปกรณ์ (หน้า User)'],

            // === Receive (รับเข้า) ===
            ['name' => 'receive:view', 'description' => 'ดูหน้ารับเข้าอุปกรณ์'],
            ['name' => 'receive:manage', 'description' => 'ดำเนินการรับเข้าอุปกรณ์ (กดปุ่มรับ)'],

            // === Purchase Orders (ใบสั่งซื้อ) ===
            ['name' => 'po:view', 'description' => 'ดูรายการใบสั่งซื้อ'],
            ['name' => 'po:create', 'description' => 'สร้างใบสั่งซื้อใหม่ / เพิ่มสินค้าลงตะกร้า'],
            ['name' => 'po:manage', 'description' => 'แก้ไข/ลบ/ยืนยัน ใบสั่งซื้อ'],
            ['name' => 'po-status:view', 'description' => 'ดูหน้าติดตามสถานะ PO'],
            ['name' => 'po:update-status', 'description' => '(API) อัปเดตสถานะ PO จากระบบภายนอก'],

            // === Transactions (เบิก-ยืม) ===
            ['name' => 'transaction:view', 'description' => 'ดูประวัติการเบิก-ยืม'],
            ['name' => 'transaction:create', 'description' => 'สร้างรายการเบิก-ยืมใหม่'],
            ['name' => 'transaction:auto_confirm', 'description' => 'ข้ามขั้นตอนรอยืนยัน (อนุมัติอัตโนมัติ)'],

            // === Returns (คืน) ===
            ['name' => 'return:view', 'description' => 'ดูประวัติการคืนอุปกรณ์'],
            ['name' => 'return:create', 'description' => 'สร้างรายการคืนอุปกรณ์'],
            ['name' => 'consumable:return', 'description' => 'รับคืนพัสดุสิ้นเปลือง'],

            // === Reports (รายงาน) ===
            ['name' => 'report:view', 'description' => 'เข้าถึงหน้ารายงานและ Deadstock'],
            ['name' => 'report:export', 'description' => 'Export รายงานเป็น PDF/Excel'],

            // === System Management (จัดการระบบ) ===
            ['name' => 'user:manage', 'description' => 'จัดการผู้ใช้และกำหนดกลุ่ม'],
            ['name' => 'permission:manage', 'description' => 'จัดการสิทธิ์ของแต่ละกลุ่ม (Super Admin เท่านั้น)'],
            ['name' => 'manage-groups', 'description' => 'สร้าง/แก้ไข/ลบ กลุ่มผู้ใช้ (Roles)'],
            ['name' => 'token:manage', 'description' => 'จัดการ API Tokens'],
            ['name' => 'master-data:manage', 'description' => 'จัดการข้อมูลหลัก (ประเภท, สถานที่, หน่วยนับ)'],

            // === System Maintenance ===
            ['name' => 'maintenance:mode', 'description' => 'เปิด/ปิด Maintenance Mode'],
        ];
        //
        // ✅✅✅ END: อัปเดตรายการสิทธิ์ ✅✅✅
        //


        // วนลูปสร้างหรืออัปเดต Permission
        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                ['description' => $permission['description']]
            );
        }
    }
}