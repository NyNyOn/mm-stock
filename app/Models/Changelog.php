<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Changelog extends Model
{
    use HasFactory;

    protected $fillable = [
        // ฟิลด์เดิมที่จำเป็นสำหรับ Audit Logs
        'user_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
        
        // ฟิลด์ใหม่สำหรับการจัดการ Version
        'change_date',
        'version',
        'type',
        'title',
        'description',
        'files_modified',
    ];

    protected $casts = [
        'change_date' => 'date',
        'files_modified' => 'array', 
    ];

    /**
     * Get the user that performed the action.
     * ความสัมพันธ์นี้จำเป็นสำหรับ Audit Logs
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}