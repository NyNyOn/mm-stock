<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GlpiTicket;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // ✅ เพิ่ม use statement นี้

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'equipment_id',
        'user_id',
        'handler_id', // ✅ เพิ่ม handler_id เข้าไปใน fillable
        'type',
        'quantity_change',
        'notes',
        'purpose',
        'transaction_date',
        'status',
        'return_condition',
        'admin_confirmed_at',
        'user_confirmed_at',
        'confirmed_at', // Added confirmed_at
        'returned_quantity', // Added returned_quantity
        'glpi_ticket_id' // Added glpi_ticket_id
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'transaction_date' => 'datetime',
        'admin_confirmed_at' => 'datetime',
        'user_confirmed_at' => 'datetime',
        'confirmed_at' => 'datetime', // ✅ Cast confirmed_at
        'quantity_change' => 'integer',
        'returned_quantity' => 'integer', // ✅ Cast returned_quantity
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['glpi_ticket'];

    /**
     * Dynamically creates an attribute to retrieve GLPI ticket information.
     * It first checks the 'purpose' column, and if it's empty or doesn't match,
     * it falls back to checking the 'notes' column for backward compatibility.
     *
     * @return GlpiTicket|null
     */
    public function getGlpiTicketAttribute()
    {
        $purposeString = $this->purpose;
        $ticketId = null;

        // 1. Check the dedicated 'purpose' column first (for new data)
        if ($purposeString && str_starts_with($purposeString, 'glpi-')) {
            $ticketId = substr($purposeString, 5);
        }
        // 2. If not found in 'purpose', search within the 'notes' column (for old data)
        else if ($this->notes && preg_match('/วัตถุประสงค์: (glpi-\d+)/', $this->notes, $matches)) {
            if (isset($matches[1])) {
                $ticketId = substr($matches[1], 5);
            }
        }

        // 3. If a ticket ID was found, retrieve the ticket model
        if ($ticketId) {
            // Ensure GlpiTicket model exists and is correctly namespaced
            if (class_exists(GlpiTicket::class)) {
                return GlpiTicket::find($ticketId);
            }
        }

        return null;
    }

    /**
     * Get the equipment associated with the transaction.
     */
    public function equipment(): BelongsTo // ✅ Type hint
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Get the user associated with the transaction.
     */
    public function user(): BelongsTo // ✅ Type hint
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ✅✅✅ START: เพิ่มฟังก์ชันนี้ ✅✅✅
     * Get the handler (admin/staff) who processed the transaction (confirmed shipment/return).
     */
    public function handler(): BelongsTo // ✅ Type hint
    {
        // Assuming your User model handles the mapping correctly
        return $this->belongsTo(User::class, 'handler_id');
    }
    // ✅✅✅ END: เพิ่มฟังก์ชันนี้ ✅✅✅

    /**
     * Get the associated GLPI ticket relation (Optional but good practice)
     */
    public function glpiTicketRelation(): BelongsTo // ✅ Type hint
    {
         // Ensure GlpiTicket model exists and is correctly namespaced
         if (class_exists(GlpiTicket::class)) {
            return $this->belongsTo(GlpiTicket::class, 'glpi_ticket_id');
         }
         // Return a dummy relation if GlpiTicket doesn't exist to avoid errors
         return $this->belongsTo(Model::class, 'glpi_ticket_id'); // Fallback
    }

}
