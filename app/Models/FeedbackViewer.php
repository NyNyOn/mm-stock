<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ✅ Helper class สำหรับตรวจสอบสิทธิ์ดู feedback
 * ใช้ระบบ Permission ที่มีอยู่แล้ว (feedback:view)
 */
class FeedbackViewer extends Model
{
    /**
     * ✅ Static: ตรวจสอบว่า user มีสิทธิ์ดู feedback หรือไม่
     */
    public static function canView($user): bool
    {
        if (!$user) return false;
        
        // 1. มีสิทธิ์ feedback:view → ดูได้เลย
        if ($user->can('feedback:view')) {
            return true;
        }
        
        // 2. ID9 ดูได้เลย (username มี id9)
        $username = strtolower($user->username ?? '');
        if (str_contains($username, 'id9')) {
            return true;
        }
        
        return false;
    }
}
