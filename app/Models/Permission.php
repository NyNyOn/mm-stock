<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    // ✅✅✅ 3. Models สิทธิ์ ต้องใช้ Default Connection เสมอ
    protected $connection = 'mysql'; // Or your default connection name

    // ✅✅✅ 4. Models สิทธิ์ ต้อง "ไม่มี" ชื่อ DB ใน $table
    protected $table = 'permissions';
    // ✅✅✅ (สิ้นสุด)

    public $timestamps = false;
    protected $fillable = ['name', 'description'];

    /**
     * (Logic นี้ถูกต้องแล้ว)
     */
    public function userGroups()
    {
        return $this->belongsToMany(UserGroup::class, 'group_permissions', 'permission_id', 'user_group_id');
    }
}

