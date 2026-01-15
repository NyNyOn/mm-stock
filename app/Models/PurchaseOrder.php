<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;


class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes; // Added SoftDeletes just in case

    /**
     * Use the DEFAULT connection defined in .env for this specific app clone.
     * This ensures the model reads/writes to the department's specific database (e.g., it_stock_db, pe_stock_db).
     */
    protected $connection = 'mysql'; // Default connection

    /**
     * Table name (optional, Laravel infers 'purchase_orders').
     */
    // protected $table = 'purchase_orders';

    /**
     * Attributes that are mass assignable.
     * Kept glpi fields as they might be relevant even without central PO DB.
     */
    protected $fillable = [
        'po_number',
        'pr_number', // Added
        'pu_data',   // Added
        'ordered_by_user_id', // Refers to user in the CENTRAL 'depart_it_db'
        'ordered_at',
        'status',
        'type',
        'notes',
        'requester_name', // May still be useful locally
        'glpi_ticket_id',
        'glpi_requester_name',
        'supplier_id',
        'total_amount',
    ];

    /**
     * Attributes that should be cast to native types.
     */
    protected $casts = [
        'ordered_at' => 'datetime',
        'pu_data' => 'array', // Added
    ];

    /**
     * Get the items for the purchase order.
     * Relationship within the SAME (local department) database.
     */
    public function items(): HasMany
    {
        // This relationship uses the default 'mysql' connection
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get the user who ordered this purchase order.
     * Relationship to the CENTRAL user database 'depart_it_db'.
     * Assumes User model (V3) has `protected $connection = 'depart_it_db';`
     */
    public function orderedBy(): BelongsTo
    {
        // Assumes User model correctly points to 'depart_it_db'
        return $this->belongsTo(User::class, 'ordered_by_user_id', 'id');
    }

    /**
     * Get the user who requested the order (alias for orderedBy).
     * Used by API Resource and potentially other parts.
     * Relationship to the CENTRAL user database 'depart_it_db'.
     */
    public function requester(): BelongsTo
    {
        // Assumes User model correctly points to 'depart_it_db'
        return $this->belongsTo(User::class, 'ordered_by_user_id', 'id');
    }


    /**
     * Get the GLPI ticket associated with this purchase order (if applicable).
     * Assumes GlpiTicket model handles its own connection ('glpi_it' or 'glpi_en').
     */
    public function glpiTicket(): BelongsTo
    {
        // Ensure GlpiTicket model has its $connection property set correctly
        // This relationship does NOT use the default 'mysql' connection
        // It uses whatever connection GlpiTicket model specifies
        return $this->belongsTo(GlpiTicket::class, 'glpi_ticket_id', 'id');
    }

     /**
     * Relationship to the Supplier (if you have a Supplier model).
     * Assume Supplier table is ALSO in the SAME department database.
     */
     // public function supplier(): BelongsTo
     // {
     //     // Assumes Supplier model uses the default 'mysql' connection
     //     return $this->belongsTo(Supplier::class);
     // }
     /**
     * Get the Thai label for status.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'รอการยืนยัน',
            'ordered' => 'สั่งซื้อแล้ว',
            'approved' => 'อนุมัติแล้ว',
            'shipped_from_supplier' => 'อยู่ระหว่างจัดส่ง',
            'partial_receive' => 'รับของบางส่วน',
            'completed' => 'รับของครบแล้ว',
            'cancelled' => 'ยกเลิก',
            'contact_vendor' => 'ติดต่อผู้ขาย',
            default => $this->status,
        };
    }
}

