<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission; // ใช้ Model Path ตามไฟล์ที่คุณอัปโหลดมา

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        // ✅✅✅ START: อัปเดตรายการสิทธิ์ 29 รายการ (จัดหมวดหมู่แล้ว) ✅✅✅
        //
        $permissions = [
            // === Dashboard ===
            ['name' => 'dashboard:view', 'description' => 'สิทธิ์ในการมองเห็นและเข้าถึงหน้า Dashboard'],

            // === Equipment & Stock (คลังและอุปกรณ์) ===
            ['name' => 'equipment:view', 'description' => 'สิทธิ์ในการมองเห็นและจัดการอุปกรณ์'],
            ['name' => 'equipment:create', 'description' => 'สร้างอุปกรณ์ใหม่'],
            ['name' => 'equipment:edit', 'description' => 'แก้ไขอุปกรณ์ที่มีอยู่'],
            ['name' => 'equipment:delete', 'description' => 'ลบบันทึกอุปกรณ์'],
            ['name' => 'equipment:borrow', 'description' => 'เบิก/ยืมอุปกรณ์ (สำหรับผู้ใช้ทั่วไป)'],
            ['name' => 'equipment:manage', 'description' => 'สิทธิ์ในการใช้งานปุ่มเพิ่มและแก้ไขข้อมูลอุปกรณ์'],
            ['name' => 'receive:view', 'description' => 'สิทธิ์ในการมองเห็นและเข้าถึงหน้า รับเข้าอุปกรณ์'],
            ['name' => 'stock-check:manage', 'description' => 'จัดการระบบตรวจนับสต็อก'],
            ['name' => 'transaction:auto_confirm', 'description' => 'สิทธ์ข้ามการยืนยัน'],

            // === Purchase Orders (PO) (จัดซื้อ) ===
            ['name' => 'po:view', 'description' => 'สิทธิ์ในการมองเห็นใบสั่งซื้อ'],
            ['name' => 'po:create', 'description' => 'สิทธิ์ในการสร้างใบสั่งซื้อ'],
            ['name' => 'po:manage', 'description' => 'สิทธิ์ในการจัดการใบสั่งซื้อ'],
            ['name' => 'po-status:view', 'description' => 'ดูหน้าติดตามสถานะใบสั่งซื้อ'],
            ['name' => 'po:update-status', 'description' => '(API) สิทธิ์สำหรับอัปเดตสถานะ PO จากระบบภายนอก'],

            // === Transactions & Returns (ธุรกรรมและการคืน) ===
            ['name' => 'transaction:view', 'description' => 'สิทธิ์ในการมองเห็นประวัติการเบิก-ยืม'],
            ['name' => 'transaction:create', 'description' => 'สิทธิ์ในการสร้างรายการเบิก-ยืมอุปกรณ์'],
            ['name' => 'return:view', 'description' => 'สิทธิ์ในการมองเห็นประวัติการคืน'],
            ['name' => 'return:create', 'description' => 'สิทธิ์ในการสร้างรายการคืนอุปกรณ์'],
            ['name' => 'consumable:return', 'description' => 'รับคืนพัสดุสิ้นเปลือง'],

            // === Maintenance & Disposal (ซ่อมบำรุงและตัดจำหน่าย) ===
            ['name' => 'maintenance:view', 'description' => 'สิทธิ์ในการมองเห็นรายการซ่อมบำรุง'],
            ['name' => 'maintenance:manage', 'description' => 'สิทธิ์ในการจัดการสถานะการซ่อมบำรุง'],
            ['name' => 'disposal:view', 'description' => 'สิทธิ์ในการมองเห็นรายการรอตัดจำหน่าย'],
            ['name' => 'disposal:manage', 'description' => 'สิทธิ์ในการจัดการรายการรอตัดจำหน่าย'],

            // === Reports (รายงาน) ===
            ['name' => 'report:view', 'description' => 'สิทธิ์ในการเข้าถึงและสร้างรายงาน'],

            // === System Management (การจัดการระบบ) ===
            ['name' => 'user:manage', 'description' => 'สิทธิ์ในการจัดการผู้ใช้และกำหนดกลุ่ม'],
            ['name' => 'permission:manage', 'description' => 'สิทธิ์ในการจัดการสิทธิ์ของแต่ละกลุ่ม (เฉพาะ Super Admin)'],
            ['name' => 'manage-groups', 'description' => 'จัดการกลุ่ม (Roles)'],
            ['name' => 'token:manage', 'description' => 'จัดการ API Tokens (สร้าง/ลบ)'],
            ['name' => 'master-data:manage', 'description' => 'สิทธิ์ในการจัดการข้อมูลหลัก (ประเภท, สถานที่, หน่วยนับ)'],

            // ✅✅✅ START: เพิ่มสิทธิ์สำหรับ Maintenance Mode ✅✅✅
            ['name' => 'maintenance:mode', 'description' => 'Enable or disable maintenance mode'],
            // ✅✅✅ END: เพิ่มสิทธิ์สำหรับ Maintenance Mode ✅✅✅
        ];
        //
        // ✅✅✅ END: อัปเดตรายการสิทธิ์ ✅✅✅
        //


        // วนลูปสร้าง Permission โดยใช้ firstOrCreate (ตามโค้ดเดิมของคุณ)
        // วิธีนี้จะค้นหาสิทธิ์จาก 'name' ก่อน ถ้าไม่เจอก็จะสร้างใหม่พร้อม 'description'
        // ทำให้สามารถรันซ้ำได้โดยไม่เกิดข้อผิดพลาด
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['description' => $permission['description']]
            );
        }
    }
}

