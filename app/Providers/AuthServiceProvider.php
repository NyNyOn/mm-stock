<?php

namespace App\Providers;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        try {
            if (Schema::hasTable('permissions')) {

                Gate::before(function (User $user, $ability) {
                    // ✅✅✅ แก้ไขจุดนี้: เปลี่ยนจาก env() มาเป็น config() ✅✅✅
                    if ($user->id === (int)config('app.super_admin_id')) {
                        return true;
                    }
                    return null;
                });

                Gate::define('manage-user-role', function (User $user, User $targetUser) {
                    return $user->getRoleLevel() > $targetUser->getRoleLevel();
                });

                Gate::define('assign-to-group', function (User $user, UserGroup $group) {
                    return $user->getRoleLevel() > $group->hierarchy_level;
                });

                Gate::define('manage-groups', function (User $user) {
                    return $user->hasPermissionTo('permission:manage');
                });

                Gate::define('edit-equipment-quantity', function(User $user) {
                    return $user->getRoleLevel() >= 90;
                });

                // Register all other permissions from the database.
                $permissions = Permission::pluck('name')->all();
                foreach ($permissions as $permission) {
                    Gate::define($permission, function (User $user) use ($permission) {
                        return $user->hasPermissionTo($permission);
                    });
                }
            }
        } catch (Exception $e) {
            Log::error('Could not register authorization gates: ' . $e->getMessage());
        }
    }
}