<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceUserRole extends Model
{
    use HasFactory;

    // ✅✅✅ 3. Models สิทธิ์ ต้องใช้ Default Connection เสมอ
    protected $connection = 'mysql';

    // ✅✅✅ 4. Models สิทธิ์ ต้อง "ไม่มี" ชื่อ DB ใน $table
    protected $table = 'service_user_roles';
    // ✅✅✅ (สิ้นสุด)

    public $timestamps = false;
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = ['user_id', 'group_id'];

    /**
     * (Logic นี้ถูกต้องแล้ว)
     */
    public function userGroup()
    {
        return $this->belongsTo(UserGroup::class, 'group_id');
    }

    /**
     * (Logic นี้ถูกต้องแล้ว เพราะ User Model (V3) จะจัดการเรื่อง Cross-DB เอง)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

