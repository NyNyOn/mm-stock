<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'name',
        'part_no',
        'model',
        'model_name',
        'model_number',
        'category_id',
        'serial_number',
        'location_id',
        'unit_id',
        'status',
        'quantity',
        'min_stock',
        'max_stock',
        'price',
        'supplier',
        'purchase_date',
        'warranty_date',
        'withdrawal_type',
        'notes',
        'has_msds',
        'msds_file_path',
        'msds_details',
    ];

    protected $casts = [
        'price' => 'float',
        'purchase_date' => 'date:Y-m-d',
        'warranty_date' => 'date:Y-m-d',
        'quantity' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'has_msds' => 'boolean',
    ];

    protected $appends = ['msds_file_url'];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($equipment) {
            $manualStatuses = [
                'maintenance', 'disposed', 'sold',
                'on_loan', 'repairing', 'inactive', 'written_off'
            ];

            if (!in_array($equipment->status, $manualStatuses)) {
                if ($equipment->quantity <= 0) {
                    $equipment->status = 'out_of_stock';
                } elseif ($equipment->min_stock > 0 && $equipment->quantity <= $equipment->min_stock) {
                    $equipment->status = 'low_stock';
                } else {
                    $equipment->status = 'available';
                }
            }
        });
    }

    public function getMsdsFileUrlAttribute()
    {
        if ($this->msds_file_path && Storage::disk('public')->exists($this->msds_file_path)) {
            return Storage::disk('public')->url($this->msds_file_path);
        }
        return null;
    }

    // --- RELATIONSHIPS ---

    public function images(): HasMany
    {
        return $this->hasMany(EquipmentImage::class);
    }

    public function latestImage(): HasOne
    {
        return $this->hasOne(EquipmentImage::class)->latest('id')->withDefault();
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(EquipmentImage::class)->where('is_primary', true)->withDefault();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
    
    /**
     * ✅ Relation สำหรับดึงคะแนนจากตารางใหม่
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(EquipmentRating::class);
    }

    // --- ACCESSORS ---

    public function getModelNameAttribute($value)
    {
        if ($value) return $value;
        if ($this->model) {
            $parts = explode(' ', $this->model, 2);
            return $parts[0] ?? '';
        }
        return null;
    }

    public function getModelNumberAttribute($value)
    {
        if ($value) return $value;
        if ($this->model) {
            $parts = explode(' ', $this->model, 2);
            return $parts[1] ?? null;
        }
        return null;
    }
}