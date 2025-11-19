<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCheckItem extends Model
{
    use HasFactory;

    protected $table = 'stock_check_items';

    protected $fillable = [
        'stock_check_id',
        'equipment_id',
        'expected_quantity',
        'counted_quantity',
        'discrepancy',
        'notes',
    ];

    /**
     * Get the parent stock check.
     */
    public function stockCheck()
    {
        return $this->belongsTo(StockCheck::class);
    }

    /**
     * Get the associated equipment.
     */
    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}
