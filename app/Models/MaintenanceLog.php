<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'equipment_id',
        'transaction_id',
        'reported_by_user_id',
        'problem_description',
        'status',
    ];

    /**
     * Get the equipment that owns the maintenance log.
     */
    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /**
     * Get the user who reported the problem.
     */
    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }
}