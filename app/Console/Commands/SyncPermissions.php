<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Permission;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all permissions to Admin group and default permissions to User group';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting permission sync...');

        // 1. Define all permissions (Master List)
        // MUST MATCH PermissionSeeder.php
        $permissions = [
            // === Dashboard ===
            ['name' => 'dashboard:view', 'description' => 'สิทธิ์ในการมองเห็นและเข้าถึงหน้า Dashboard'],

            // === Equipment & Stock ===
            ['name' => 'equipment:view', 'description' => 'สิทธิ์ในการมองเห็นและจัดการอุปกรณ์'],
            ['name' => 'equipment:create', 'description' => 'สร้างอุปกรณ์ใหม่'],
            ['name' => 'equipment:edit', 'description' => 'แก้ไขอุปกรณ์ที่มีอยู่'],
            ['name' => 'equipment:delete', 'description' => 'ลบบันทึกอุปกรณ์'],
            ['name' => 'equipment:borrow', 'description' => 'เบิก/ยืมอุปกรณ์ (สำหรับผู้ใช้ทั่วไป)'],
            ['name' => 'equipment:manage', 'description' => 'สิทธิ์ในการใช้งานปุ่มเพิ่มและแก้ไขข้อมูลอุปกรณ์'],
            ['name' => 'receive:view', 'description' => 'สิทธิ์ในการมองเห็นและเข้าถึงหน้า รับเข้าอุปกรณ์'],
            ['name' => 'receive:manage', 'description' => 'ดำเนินการรับเข้าอุปกรณ์'],
            ['name' => 'stock-check:manage', 'description' => 'จัดการระบบตรวจนับสต็อก'],
            ['name' => 'transaction:auto_confirm', 'description' => 'สิทธ์ข้ามการยืนยัน'],

            // === Purchase Orders (PO) ===
            ['name' => 'po:view', 'description' => 'สิทธิ์ในการมองเห็นใบสั่งซื้อ'],
            ['name' => 'po:create', 'description' => 'สิทธิ์ในการสร้างใบสั่งซื้อ'],
            ['name' => 'po:manage', 'description' => 'สิทธิ์ในการจัดการใบสั่งซื้อ'],
            ['name' => 'po-status:view', 'description' => 'ดูหน้าติดตามสถานะใบสั่งซื้อ'],
            ['name' => 'po:update-status', 'description' => '(API) สิทธิ์สำหรับอัปเดตสถานะ PO จากระบบภายนอก'],

            // === Transactions & Returns ===
            ['name' => 'transaction:view', 'description' => 'สิทธิ์ในการมองเห็นประวัติการเบิก-ยืม'],
            ['name' => 'transaction:create', 'description' => 'สิทธิ์ในการสร้างรายการเบิก-ยืมอุปกรณ์'],
            ['name' => 'return:view', 'description' => 'สิทธิ์ในการมองเห็นประวัติการคืน'],
            ['name' => 'return:create', 'description' => 'สิทธิ์ในการสร้างรายการคืนอุปกรณ์'],
            ['name' => 'consumable:return', 'description' => 'รับคืนพัสดุสิ้นเปลือง'],

            // === Maintenance & Disposal ===
            ['name' => 'maintenance:view', 'description' => 'สิทธิ์ในการมองเห็นรายการซ่อมบำรุง'],
            ['name' => 'maintenance:manage', 'description' => 'สิทธิ์ในการจัดการสถานะการซ่อมบำรุง'],
            ['name' => 'disposal:view', 'description' => 'สิทธิ์ในการมองเห็นรายการรอตัดจำหน่าย'],
            ['name' => 'disposal:manage', 'description' => 'สิทธิ์ในการจัดการรายการรอตัดจำหน่าย'],

            // === Reports ===
            ['name' => 'report:view', 'description' => 'สิทธิ์ในการเข้าถึงรายงานและ Deadstock'],
            ['name' => 'report:export', 'description' => 'สิทธิ์ในการ Export รายงานเป็น PDF'],

            // === System Management ===
            ['name' => 'user:manage', 'description' => 'สิทธิ์ในการจัดการผู้ใช้และกำหนดกลุ่ม'],
            ['name' => 'permission:manage', 'description' => 'สิทธิ์ในการจัดการสิทธิ์ของแต่ละกลุ่ม (เฉพาะ Super Admin)'],
            ['name' => 'manage-groups', 'description' => 'จัดการกลุ่ม (Roles)'],
            ['name' => 'token:manage', 'description' => 'จัดการ API Tokens (สร้าง/ลบ)'],
            ['name' => 'master-data:manage', 'description' => 'สิทธิ์ในการจัดการข้อมูลหลัก (ประเภท, สถานที่, หน่วยนับ)'],

            // === System Maintenance ===
            ['name' => 'maintenance:mode', 'description' => 'เปิด/ปิด Maintenance Mode ของระบบ'],
        ];

        // 2. Ensure Permissions Exist in DB
        foreach ($permissions as $p) {
            Permission::firstOrCreate(
                ['name' => $p['name']],
                ['description' => $p['description']]
            );
        }
        $this->info('Permissions checked/created.');

        // 3. Find Groups
        $adminGroup = UserGroup::where('name', 'Admin')->first();
        if (!$adminGroup) {
            $this->error('Admin group not found! Creating it...');
            $adminGroup = UserGroup::create([
                'name' => 'Admin',
                'hierarchy_level' => 99,
                'description' => 'System Administrator'
            ]);
        }

        $userGroup = UserGroup::where('name', 'User')->first();
         if (!$userGroup) {
            $this->error('User group not found! Creating it...');
            $userGroup = UserGroup::create([
                'name' => 'User',
                'hierarchy_level' => 1,
                'description' => 'General User'
            ]);
        }

        // 4. Sync Admin Permissions (GIVE ALL)
        // Get all permission IDs
        $allPermissionIds = Permission::pluck('id')->toArray();
        $adminGroup->permissions()->sync($allPermissionIds);
        $this->info("Synced " . count($allPermissionIds) . " permissions to Admin group.");

        // 5. Sync User Permissions (Subset)
        $userPermissionNames = [
            'dashboard:view',
            'equipment:borrow', 
            'transaction:view', 
            'transaction:create',
            'return:view', 
            'return:create',
            'po:view', // Users might need to see POs? Adjust as needed.
            // Add others as requested
        ];
        
        $userPermissionIds = Permission::whereIn('name', $userPermissionNames)->pluck('id')->toArray();
        $userGroup->permissions()->sync($userPermissionIds);
        $this->info("Synced " . count($userPermissionIds) . " permissions to User group.");

        $this->info('Permission sync completed successfully!');
    }
}
