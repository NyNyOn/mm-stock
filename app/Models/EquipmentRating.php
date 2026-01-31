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
        'q1_answer',    // เก็บคำตอบข้อ 1 (1=แย่, 2=ไม่ได้ใช้, 3=ดี) - Legacy
        'q2_answer',    // เก็บคำตอบข้อ 2 - Legacy
        'q3_answer',    // เก็บคำตอบข้อ 3 - Legacy
        'rating_score', // เก็บค่าคะแนนเฉลี่ยเป็นทศนิยม - Legacy
        'feedback_type', // ✅ ระบบใหม่: good=ถูกใจ, neutral=พอใช้, bad=แย่
        'comment',
        'rated_at',     // วันที่ประเมิน
        'answers',      // เก็บคำตอบ Dynamic JSON - Legacy
    ];

    protected $casts = [
        'q1_answer' => 'integer',
        'q2_answer' => 'integer',
        'q3_answer' => 'integer',
        'rating_score' => 'float',
        'feedback_type' => 'string', // ✅ ระบบใหม่
        'rated_at' => 'datetime',
        'answers' => 'array',
    ];

    /**
     * ✅ แปลง feedback_type เป็นข้อความไทย
     */
    public function getFeedbackLabel(): string
    {
        return match ($this->feedback_type) {
            'good' => 'ถูกใจ',
            'neutral' => 'พอใช้',
            'bad' => 'แย่',
            default => 'ยังไม่ประเมิน',
        };
    }

    /**
     * ✅ แปลง feedback_type เป็น Emoji
     */
    public function getFeedbackEmoji(): string
    {
        return match ($this->feedback_type) {
            'good' => '👍',
            'neutral' => '👌',
            'bad' => '👎',
            default => '❓',
        };
    }

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
        // Legacy Support
        return self::calculateDynamicScore([$q1, $q2, $q3]);
    }

    /**
     * คำนวณคะแนนแบบ Dynamic (รับ Array คำตอบ)
     * Rule:
     * - Choice 1 (แย่) = 1 คะแนน
     * - Choice 3 (ดี) = 5 คะแนน
     * - Choice 2 (ไม่ได้ใช้) = ทำให้เป็น Null ทันที (Unrated)
     */
    public static function calculateDynamicScore(array $answers)
    {
        if (empty($answers)) return null;

        $totalScore = 0;
        $count = count($answers);

        foreach ($answers as $val) {
            $val = (int)$val;
            // ถ้ามีข้อใดข้อหนึ่งเป็น "ยังไม่เคยใช้งาน" (2) -> ถือว่าไม่ได้ประเมิน
            if ($val === 2) {
                return null;
            }
            
            // 1 -> 1.0, 3 -> 5.0
            $totalScore += ($val === 3) ? 5.0 : 1.0;
        }

        if ($count === 0) return 0;

        return round($totalScore / $count, 2);
    }
}