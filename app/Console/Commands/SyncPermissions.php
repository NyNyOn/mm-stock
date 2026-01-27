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
