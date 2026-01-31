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
        'equipment_id',
        'user_id',
        'feedback_type', // good=ถูกใจ, neutral=พอใช้, bad=แย่
        'comment',
        'rated_at',
    ];

    protected $casts = [
        'feedback_type' => 'string',
        'rated_at' => 'datetime',
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
     * Get the user who created the rating.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}