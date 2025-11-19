<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'units';

    /**
     * ✅ FIX: The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * ✅✅✅ START: เพิ่มฟังก์ชันนี้เข้าไปครับ ✅✅✅
     * สร้างความสัมพันธ์ว่า Unit หนึ่งอัน สามารถมี Equipment ได้หลายชิ้น
     */
    public function equipments()
    {
        return $this->hasMany(Equipment::class);
    }
    /**
     * ✅✅✅ END: สิ้นสุดส่วนที่เพิ่ม ✅✅✅
     */
}