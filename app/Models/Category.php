<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'categories';

    /**
     * ✅ FIX: The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'prefix',
        'custom_questions',
    ];

    protected $casts = [
        'custom_questions' => 'array',
    ];

    /**
     * ✅✅✅ START: เพิ่มฟังก์ชันนี้เข้าไปครับ ✅✅✅
     * สร้างความสัมพันธ์ว่า Category หนึ่งอัน สามารถมี Equipment ได้หลายชิ้น
     */
    public function equipments()
    {
        return $this->hasMany(Equipment::class);
    }
    /**
     * ✅✅✅ END: สิ้นสุดส่วนที่เพิ่ม ✅✅✅
     */
}