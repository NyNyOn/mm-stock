<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\UserGroup;
use App\Models\Permission;
use App\Models\ServiceUserRole;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // 1. Create User Groups (Roles)
        $adminGroup = UserGroup::firstOrCreate(
            ['name' => 'Admin'],
            ['hierarchy_level' => 99, 'description' => 'System Administrator']
        );

        $userGroup = UserGroup::firstOrCreate(
            ['name' => 'User'],
            ['hierarchy_level' => 1, 'description' => 'General User']
        );

        // 🧹 CLEANUP: Remove old 'equipment:manage' assignment
        $oldPerm = Permission::where('name', 'equipment:manage')->first();
        if ($oldPerm) {
            DB::table('group_permissions')->where('permission_id', $oldPerm->id)->delete();
        }

        // 2. Create Permissions
        $permissions = [
            'dashboard:view',
            'equipment:view',
            // 'equipment:manage', // ❌ REMOVED
            'equipment:borrow', // ✅ Critical for /user/equipment
            'transaction:view',
            'transaction:create',
            'transaction:cancel', // ✅ ADDED
            'transaction:confirm', // ✅ ADDED
            'transaction:approve',
            'report:view',
            'setting:view',
            'user:manage',
            'role:manage'
        ];

        foreach ($permissions as $permName) {
            $perm = Permission::firstOrCreate(['name' => $permName]);
            
            // 3. Assign Permissions to Groups
            // Admin gets everything
            DB::table('group_permissions')->updateOrInsert(
                ['user_group_id' => $adminGroup->id, 'permission_id' => $perm->id],
                []
            );

            // User gets basic stuff
            if (in_array($permName, ['dashboard:view', 'equipment:view', 'equipment:borrow', 'transaction:view', 'transaction:create'])) {
                DB::table('group_permissions')->updateOrInsert(
                    ['user_group_id' => $userGroup->id, 'permission_id' => $perm->id],
                    []
                );
            }
        }

        // 4. Assign Admin User to Admin Group
        // Find admin user (adjust email/username as needed)
        // Since we don't know the exact user ID, let's assign to ID 1 (or prompt user)
        // Assuming ID 1 is the user currently logged in or main admin
        
        $adminUserId = 1; // Default assumption
        
        // Check if user exists in sync_ldap first? No, foreign key is looser here or user exists
        // Just insert directly
        ServiceUserRole::updateOrCreate(
            ['user_id' => $adminUserId],
            ['group_id' => $adminGroup->id]
        );
        
        // Also assign to a typical test user "Chj" if exists
        $testUser = User::where('username', 'Chj')->first();
        if ($testUser) {
             ServiceUserRole::updateOrCreate(
                ['user_id' => $testUser->id],
                ['group_id' => $adminGroup->id]
            );
        }
    }
}
