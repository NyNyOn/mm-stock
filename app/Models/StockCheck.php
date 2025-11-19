<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCheck extends Model
{
    use HasFactory;

    protected $table = 'stock_checks';

    protected $fillable = [
        'name',
        'scheduled_date',
        'completed_at',
        'status',
        'checked_by_user_id',
    ];

    /**
     * Get the items associated with this stock check.
     */
    public function items()
    {
        return $this->hasMany(StockCheckItem::class);
    }

    /**
     * Get the user who performed this stock check.
     */
    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by_user_id');
    }
}
