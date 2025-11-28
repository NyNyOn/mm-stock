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
        // 'user_id', // เอาออกแล้วตามที่แจ้ง (ใช้การดึงจาก Transaction แทน)
        'equipment_id',
        'q1_answer',    // เก็บคำตอบข้อ 1 (1=แย่, 2=ไม่ได้ใช้, 3=ดี)
        'q2_answer',    // เก็บคำตอบข้อ 2
        'q3_answer',    // เก็บคำตอบข้อ 3
        'rating_score', // เก็บค่าคะแนนเฉลี่ยเป็นทศนิยม (แทน rating เดิม)
        'comment',
        'rated_at'      // วันที่ประเมิน
    ];

    protected $casts = [
        'q1_answer' => 'integer',
        'q2_answer' => 'integer',
        'q3_answer' => 'integer',
        'rating_score' => 'float', // แปลงเป็น float อัตโนมัติเมื่อดึงมาใช้
        'rated_at' => 'datetime',
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
     * ไม่ต้องเก็บ user_id ในตารางนี้ แต่ดึงจาก transaction แทน
     */
    public function user()
    {
        // เช็คว่า transaction มีอยู่จริงไหมก่อนเรียก user
        return $this->transaction ? $this->transaction->user() : null;
    }

    /**
     * ฟังก์ชันคำนวณคะแนนเป็นทศนิยม 2 ตำแหน่ง
     * @param int $q1 คำตอบข้อ 1 (1, 2, 3)
     * @param int $q2 คำตอบข้อ 2 (1, 2, 3)
     * @param int $q3 คำตอบข้อ 3 (1, 2, 3)
     * @return float|null คืนค่าคะแนน 1.00 - 5.00 หรือ null ถ้าไม่ได้ใช้งาน
     */
    public static function calculateScore($q1, $q2, $q3)
    {
        // 1. ถ้ามีข้อใดข้อหนึ่งเป็น "ยังไม่เคยใช้งาน" (ค่า 2) ถือว่าไม่มีคะแนน
        if ($q1 == 2 || $q2 == 2 || $q3 == 2) {
            return null;
        }

        // 2. แปลงค่าจาก Choice (1,3) เป็นคะแนนเต็ม (1, 5)
        // Choice 1 (Negative) => 1 คะแนน
        // Choice 3 (Positive) => 5 คะแนน
        $s1 = ($q1 == 3) ? 5.0 : 1.0;
        $s2 = ($q2 == 3) ? 5.0 : 1.0;
        $s3 = ($q3 == 3) ? 5.0 : 1.0;

        // 3. หาค่าเฉลี่ย
        $average = ($s1 + $s2 + $s3) / 3;

        // 4. ปัดเศษทศนิยม 2 ตำแหน่ง
        return round($average, 2);
    }
}