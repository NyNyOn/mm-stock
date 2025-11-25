<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumableReturn extends Model
{
    use HasFactory;

    protected $table = 'consumable_returns';

    protected $fillable = [
        'original_transaction_id',
        'requested_by_user_id',
        'approved_by_user_id',
        'quantity_returned',
        'action_type',
        'status',
        'notes',
    ];

    /**
     * ความสัมพันธ์ไปยัง Transaction เดิมที่ถูกเบิกไป
     */
    public function originalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'original_transaction_id');
    }

    /**
     * ความสัมพันธ์ไปยังผู้ใช้ที่ส่งคำขอคืน
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * ความสัมพันธ์ไปยังผู้ที่อนุมัติ
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
