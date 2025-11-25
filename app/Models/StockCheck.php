<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCheck extends Model
{
    use HasFactory;

    protected $table = 'stock_checks';

    protected $fillable = [
        'name',
        'scheduled_date',
        'completed_at',
        'status',
        'checked_by_user_id',
        'category_id', // ✅ 1. เพิ่มเพื่อบันทึก ID หมวดหมู่
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(StockCheckItem::class);
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by_user_id');
    }

    // ✅ 2. เพิ่มฟังก์ชันนี้ เพื่อแก้ Error "Undefined relationship"
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}