<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    /**
     * Use the DEFAULT connection defined in .env for this specific app clone.
     * This ensures the model reads/writes to the department's specific database.
     */
    protected $connection = 'mysql'; // Default connection

    /**
     * Table name (optional, Laravel infers 'purchase_order_items').
     */
    // protected $table = 'purchase_order_items';

    /**
     * The attributes that are mass assignable.
     * REMOVED dept_key as POs are now department-specific within their own DB.
     * Kept requester_id as it points to the central User DB.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purchase_order_id',
        'pr_item_id', // ID from PU Hub
        'equipment_id', // Refers to equipment in the SAME database
        'item_description',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'unit_name',
        'status',
        'requester_id', // Refers to user in the CENTRAL 'depart_it_db'
        'specifications',
        'reference_link',
        'image',
        'inspection_status',
        'inspection_notes',
        'rejection_code', // New
        'rejection_reason', // New
        // 'dept_key', // Removed
    ];

    /**
     * Get the purchase order that owns the item.
     * Relationship within the SAME (local department) database.
     */
    public function purchaseOrder(): BelongsTo
    {
        // This relationship uses the default 'mysql' connection
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * Get the equipment associated with the purchase order item.
     * Relationship within the SAME (local department) database.
     * Uses withTrashed() to allow viewing details even if equipment was soft-deleted.
     */
    public function equipment(): BelongsTo
    {
        // This relationship uses the default 'mysql' connection
        return $this->belongsTo(Equipment::class, 'equipment_id')->withTrashed();
    }

    /**
     * Get the user who requested the item.
     * Relationship to the CENTRAL user database 'depart_it_db'.
     * Assumes User model (V3) has `protected $connection = 'depart_it_db';`
     */
    public function requester(): BelongsTo
    {
        // Assumes User model correctly points to 'depart_it_db'
        return $this->belongsTo(User::class, 'requester_id', 'id');
    }
}
