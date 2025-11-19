<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;
// --- Use DB and Log ---
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Keep Log for potential errors
use App\Models\Permission; // Add this if Permission model exists in App\Models
use App\Models\ServiceUserRole; // ✅ 1. เพิ่ม Use Statement (จากไฟล์ตัวอย่างที่ถูกต้อง)


class User extends Authenticatable
{
    // ... (use Authorizable, HasFactory, Notifiable, HasApiTokens - as before) ...
    use Authorizable, HasFactory, Notifiable, HasApiTokens;

    // ✅✅✅ 1. USER Model ต้องชี้ไปที่ DB กลาง เสมอ (จากไฟล์ตัวอย่างที่ถูกต้อง)
    protected $connection = 'depart_it_db';
    protected $table = 'sync_ldap';
    // ✅✅✅ (สิ้นสุด)
    
    protected $primaryKey = 'id';
    public $timestamps = false;
    // ✅ 2. อัปเดต fillable ให้ตรงกับไฟล์ตัวอย่างที่ถูกต้อง
    protected $fillable = ['fullname', 'username', 'employeecode', 'photo_path', 'access_token', 'status', 'department_id'];
    protected $hidden = ['password', 'remember_token'];
    protected $appends = ['photo_url'];

    // ... (Casts - as before) ...
     protected function casts(): array
     {
         return [
             'email_verified_at' => 'datetime',
             'last_login_at' => 'datetime',
         ];
     }


    /**
      * Relationship to ServiceUserRole (it_stock_db)
      * (จากไฟล์ตัวอย่างที่ถูกต้อง)
      */
     public function serviceUserRole()
     {
         return $this->hasOne(ServiceUserRole::class, 'user_id', 'id');
     }

     /**
      * Relationship to Transactions (it_stock_db) where this user is the requestor
      * (จากไฟล์ตัวอย่างที่ถูกต้อง)
      */
     public function transactions()
     {
         return $this->hasMany(Transaction::class, 'user_id', 'id');
     }
      /**
       * Relationship to Transactions (it_stock_db) where this user is the handler
       * (จากไฟล์ตัวอย่างที่ถูกต้อง)
       */
      public function handledTransactions()
      {
           return $this->hasMany(Transaction::class, 'handler_id', 'id');
      }

      /**
       * Relationship to Returns (it_stock_db) where this user is the returnee
       * (จากไฟล์ตัวอย่างที่ถูกต้อง)
       */
      public function returns()
      {
           return $this->hasMany(Transaction::class, 'user_id', 'id')->where('type', 'return');
      }


    /**
     * Check permission by querying the 'mysql' (it_stock_db) connection directly.
     * (จากไฟล์ตัวอย่างที่ถูกต้อง)
     */
    public function hasPermissionTo($permissionName)
    {
        $userId = $this->id;

        try {
            // Step 1: Find the user's group_id from service_user_roles in it_stock_db
            $role = DB::connection('mysql')->table('service_user_roles')
                      ->where('user_id', $userId)
                      ->select('group_id')
                      ->first();

            if (!$role || !$role->group_id) {
                return false;
            }
            $groupId = $role->group_id;

            // Step 2: Find the permission_id from the permissions table in it_stock_db
            $permission = DB::connection('mysql')->table('permissions')
                            ->where('name', $permissionName)
                            ->select('id')
                            ->first();

            if (!$permission || !$permission->id) {
                return false;
            }
            $permissionId = $permission->id;

            // Step 3: Check if the group_id and permission_id exist in group_permissions table
            $hasPermission = DB::connection('mysql')->table('group_permissions')
                               ->where('user_group_id', $groupId)
                               ->where('permission_id', $permissionId)
                               ->exists();

            return $hasPermission;

        } catch (\Exception $e) {
            Log::error("Permission check failed for user {$userId}, permission '{$permissionName}': " . $e->getMessage());
            return false;
        }
    }


    /**
     * ดึงค่าระดับชั้นของผู้ใช้
     * (จากไฟล์ตัวอย่างที่ถูกต้อง)
     */
    public function getRoleLevel(): int
    {
        // ใช้ config() แทน env() เพื่อความปลอดภัยและประสิทธิภาพ
        if ($this->id === (int)config('app.super_admin_id', 9)) { // ใส่ 9 เป็นค่า default
            return PHP_INT_MAX;
        }
         try {
            $this->loadMissing('serviceUserRole.userGroup');
            return $this->serviceUserRole?->userGroup?->hierarchy_level ?? 0;

        } catch (\Exception $e) {
            Log::error("Error accessing role level for User ID {$this->id}: " . $e->getMessage());
            return 0; // Default level on error
        }
    }

    // ✅✅✅ START: โค้ดที่แก้ไข (ใช้ URL Hardcode เหมือนไฟล์ตัวอย่างที่ถูกต้อง) ✅✅✅
    /**
     * สร้าง URL สำหรับลิงก์ไปยังโปรไฟล์พนักงาน (ใช้ URL ตรง)
     */
    public function getProfileLink(): string
    {
        // ใช้ URL แบบ Hardcode เหมือนเวอร์ชันเก่า
        $baseUrl = 'http://183.88.219.75:14261/mobilelogin';
        $usernamePart = $this->username;
        // ดึงค่า access_token จากฐานข้อมูล (depart_it_db.sync_ldap)
        $token = $this->access_token ?? ''; // ใช้ '??' เพื่อให้เป็นค่าว่างถ้าไม่มี Token

        // ตรวจสอบว่ามี Token จริงหรือไม่ ก่อนนำไปต่อท้าย URL
        if (!empty($token)) {
            // ถ้ามี Token ให้สร้าง URL แบบเต็ม
            return "{$baseUrl}/{$usernamePart}/{$token}/";
        } else {
            // ถ้าไม่มี Token อาจจะ Log เตือนไว้ และคืนค่า #
            Log::warning("User {$this->id} ({$this->username}) does not have an access_token. Cannot generate profile link.");
            return '#';
        }
    }
    // ✅✅✅ END: โค้ดที่แก้ไข ✅✅✅

    /**
     * Accessor สำหรับสร้าง URL รูปภาพเต็ม
     * (จากไฟล์ตัวอย่างที่ถูกต้อง)
     */
    public function getPhotoUrlAttribute(): string
    {
        // ตรวจสอบว่ามี photo_path และไม่เป็นค่าว่าง
        if ($this->photo_path && !empty($this->photo_path)) {
            // ดึง Base URL และ Path รูปภาพจาก config
            // (หมายเหตุ: ไฟล์ที่ถูกต้องที่คุณส่งมาก็ยังใช้ config ที่นี่ ซึ่งอาจจะถูกหรือผิดก็ได้)
            // (ถ้าส่วนนี้ยังผิด ให้เปลี่ยน $baseUrl และ $photoPath เป็น Hardcode เหมือนกัน)
            $baseUrl = config('employee_portal.base_uri');
            $photoPath = config('employee_portal.photo_path');

             // ตัด / ท้าย baseUrl และ / หน้า photoPath ออก (ถ้ามี)
             $baseUrl = rtrim($baseUrl, '/');
             $photoPath = ltrim($photoPath, '/');

            // สร้าง URL เต็ม
            return "{$baseUrl}/{$photoPath}/{$this->photo_path}";
        }
        // ถ้าไม่มีรูปภาพ ให้ใช้ URL จาก ui-avatars.com
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->fullname ?? $this->username) . '&background=random&color=fff';
    }

}

