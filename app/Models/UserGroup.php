<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
    use HasFactory;

    // ✅✅✅ 3. Models สิทธิ์ ต้องใช้ Default Connection เสมอ
    protected $connection = 'mysql';

    // ✅✅✅ 4. Models สิทธิ์ ต้อง "ไม่มี" ชื่อ DB ใน $table
    protected $table = 'user_groups';
    // ✅✅✅ (สิ้นสุด)

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_deleted',
        'hierarchy_level'
    ];

    /**
     * (Logic นี้ถูกต้องแล้ว เพราะมันอยู่บน Connection เดียวกัน)
     */
    public function serviceUserRoles()
    {
        return $this->hasMany(ServiceUserRole::class, 'group_id');
    }

    /**
     * (Logic นี้ถูกต้องแล้ว เพราะมันอยู่บน Connection เดียวกัน)
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'group_permissions', 'user_group_id', 'permission_id');
    }
}

