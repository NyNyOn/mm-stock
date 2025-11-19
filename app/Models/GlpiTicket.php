<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlpiTicket extends Model
{
    use HasFactory;

    /**
     * ระบุว่า Model นี้ให้ไปเชื่อมต่อกับ 'glpi' connection
     *
     * @var string
     */
    protected $connection = 'glpi_it';

    /**
     * ระบุชื่อตารางของ Model นี้
     *
     * @var string
     */
    protected $table = 'glpi_tickets';

    /**
     * กำหนดว่าตารางนี้ไม่มี timestamp (created_at, updated_at)
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Scope a query to only include active and non-deleted tickets.
     * (New, Assigned, Planned, Pending) and not in trash.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [1, 2, 3, 4]) // กรองสถานะ: ใหม่, กำลังดำเนินการ, วางแผนแล้ว, รอดำเนินการ
                     ->where('is_deleted', 0);        // กรองใบงานที่ยังไม่ถูกลบ (ไม่ได้อยู่ในถังขยะ)
    }
}