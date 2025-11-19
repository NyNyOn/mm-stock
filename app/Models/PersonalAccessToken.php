<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * การเชื่อมต่อฐานข้อมูลที่ตารางนี้ควรใช้
     *
     * @var string
     */
    // บังคับให้ใช้ 'mysql' (Connection เริ่มต้นที่ชี้ไป it_stock_db)
    protected $connection = 'mysql'; 
}