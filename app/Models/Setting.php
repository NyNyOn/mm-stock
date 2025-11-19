<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    // ตั้งค่าตาราง
    protected $table = 'settings';
    protected $primaryKey = 'key'; // ใช้ 'key' เป็น Primary Key
    public $incrementing = false; // บอกว่า Primary Key ไม่ใช่ Auto-increment
    protected $keyType = 'string'; // บอกว่า Key เป็น String

    // field ที่อนุญาตให้กรอก
    protected $fillable = [
        'key',
        'value',
    ];
}