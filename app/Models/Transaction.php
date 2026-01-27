<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GlpiTicket;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    public $timestamps = false;

    protected $fillable = [
        'equipment_id',
        'user_id',
        'handler_id',
        'type',
        'quantity_change',
        'notes',
        'purpose',
        'transaction_date',
        'status',
        'return_condition',
        'admin_confirmed_at',
        'user_confirmed_at',
        'confirmed_at',
        'returned_quantity',
        'glpi_ticket_id',
        // ❌ ลบ rating, rating_comment, rated_at ออกแล้ว
    ];

    protected $casts = [
        'transaction_date'   => 'datetime',
        'admin_confirmed_at' => 'datetime',
        'user_confirmed_at'  => 'datetime',
        'confirmed_at'       => 'datetime',
        'quantity_change'    => 'integer',
        'returned_quantity'  => 'integer',
        'glpi_ticket_id'     => 'integer',
    ];

    protected $appends = ['glpi_ticket'];

    public function getGlpiTicketAttribute()
    {
        $purposeString = $this->purpose;
        $ticketId = null;

        if ($purposeString && str_starts_with($purposeString, 'glpi-')) {
            $ticketId = substr($purposeString, 5);
        }
        else if ($this->notes && preg_match('/วัตถุประสงค์: (glpi-\d+)/', $this->notes, $matches)) {
            if (isset($matches[1])) {
                $ticketId = substr($matches[1], 5);
            }
        }

        if ($ticketId) {
            if (class_exists(GlpiTicket::class)) {
                return GlpiTicket::find($ticketId);
            }
        }

        return null;
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    public function glpiTicketRelation(): BelongsTo
    {
        if (class_exists(\App\Models\GlpiTicket::class)) {
            return $this->belongsTo(\App\Models\GlpiTicket::class, 'glpi_ticket_id');
        }
        return $this->belongsTo(self::class, 'glpi_ticket_id')->whereNull('id');
    }

    /**
     * ✅ Relation ใหม่ไปยังตาราง EquipmentRating
     */
    public function rating(): HasOne
    {
        return $this->hasOne(EquipmentRating::class);
    }
}