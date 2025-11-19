<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ServiceUserRole; // ✅ 1. เพิ่ม Use Statement

class LdapUser extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    // ✅✅✅ 1. USER Model ต้องชี้ไปที่ DB กลาง เสมอ
    protected $connection = 'depart_it_db';
    protected $table = 'sync_ldap';
    // ✅✅✅ (สิ้นสุด)

    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'fullname',
        'employeecode',
        'photo_path',
        'access_token',
        'status',
        'department_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getAuthIdentifierName()
    {
        return 'username';
    }

    /**
     * ✅✅✅ 2. สร้างความสัมพันธ์ข้ามฐานข้อมูล (Cross-Database Relationship) ✅✅✅
     * (Logic เดียวกันกับ User.php)
     */
    public function serviceUserRole()
    {
        $serviceRoleModel = new ServiceUserRole();
        $connectionName = $serviceRoleModel->getConnectionName(); // 'mysql'
        $databaseName = config("database.connections.{$connectionName}.database"); // 'it_stock_db' หรือ 'pe_stock_db'
        $table = $serviceRoleModel->getTable(); // 'service_user_roles'
        $fullTableName = $databaseName . '.' . $table;

        return $this->hasOne(ServiceUserRole::class, 'user_id', 'id')
                    ->from($fullTableName);
    }
    // ✅✅✅ (สิ้นสุด)

    // (Logic นี้ถูกต้องแล้ว)
    public function getRoleLevel(): int
    {
        if ($this->id === (int)config('app.super_admin_id', 9)) {
            return PHP_INT_MAX;
        }
         try {
            $this->loadMissing('serviceUserRole.userGroup');
            return $this->serviceUserRole?->userGroup?->hierarchy_level ?? 0;
        } catch (\Exception $e) {
            Log::error("Error accessing role level for LdapUser ID {$this->id}: " . $e->getMessage());
            return 0;
        }
    }

    // (Logic นี้ถูกต้องแล้ว)
     public function hasPermissionTo($permissionName)
     {
         if (!$this->serviceUserRole || !$this->serviceUserRole->userGroup) {
             return false;
         }
         $this->serviceUserRole->userGroup->loadMissing('permissions');
         return $this->serviceUserRole->userGroup->permissions->contains('name', $permissionName);
     }
}

