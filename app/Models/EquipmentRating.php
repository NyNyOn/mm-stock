<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentRating extends Model
{
    use HasFactory;

    protected $table = 'equipment_ratings';

    protected $fillable = [
        'transaction_id',
        // 'user_id',  <-- เอาออกแล้ว
        'equipment_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Get the transaction that owns the rating.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the equipment that was rated.
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * ดึงข้อมูล User ผ่าน Transaction (Virtual Relation)
     */
    public function user()
    {
        return $this->transaction->user(); 
    }
}