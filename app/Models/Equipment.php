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
        // --- NEW FIELDS ---
        'withdrawal_type',
        'notes', // Moved notes here for better organization with MSDS
        'has_msds',
        'msds_file_path',
        'msds_details',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price' => 'float',
        'purchase_date' => 'date:Y-m-d',
        'warranty_date' => 'date:Y-m-d',
        'quantity' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'has_msds' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['msds_file_url'];


    /**
     * The "booted" method of the model.
     * ✅✅✅ จุดที่เปลี่ยนสถานะอัตโนมัติ ✅✅✅
     */
    protected static function boot()
    {
        parent::boot();

        // ทำงาน *ก่อน* ที่ข้อมูลจะถูกบันทึกลงฐานข้อมูล
        static::saving(function ($equipment) {
            // สถานะที่ User กำหนดเอง จะไม่ถูกเปลี่ยนอัตโนมัติ
            $manualStatuses = [
                'maintenance', 'disposed', 'sold',
                'on_loan', 'repairing', 'inactive', 'written_off'
            ];

            // ถ้า status ปัจจุบัน ไม่ใช่สถานะที่กำหนดเอง
            if (!in_array($equipment->status, $manualStatuses)) {
                // ตรวจสอบ quantity *หลังจาก* ที่มีการเปลี่ยนแปลงแล้ว (แต่ยังไม่ save)
                if ($equipment->quantity <= 0) {
                    // ถ้าจำนวนน้อยกว่าหรือเท่ากับ 0 -> 'out_of_stock'
                    $equipment->status = 'out_of_stock';
                } elseif ($equipment->min_stock > 0 && $equipment->quantity <= $equipment->min_stock) {
                    // ถ้ามี min_stock กำหนดไว้ และจำนวนน้อยกว่าหรือเท่ากับ min_stock -> 'low_stock'
                    $equipment->status = 'low_stock';
                } else {
                    // กรณีอื่นๆ -> 'available'
                    $equipment->status = 'available';
                }
            }
        });
    }

    /**
     * Get the URL for the MSDS file.
     */
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

    /**
     * Get the latest image for the equipment.
     * (ใช้สำหรับแสดงเป็นรูปปกหลัก หรือรูปในตารางที่ไม่ใช่ primary)
     */
    public function latestImage(): HasOne
    {
        // ใช้ hasOne เพื่อดึงแค่รูปเดียว
        // ใช้ latest('id') เพื่อดึงรูปที่เพิ่มล่าสุด (ID มากสุด)
        // ใช้ withDefault() เพื่อป้องกัน Error ถ้าไม่มีรูปเลย
        return $this->hasOne(EquipmentImage::class)->latest('id')->withDefault();
    }

    public function primaryImage(): HasOne
    {
        // ใช้ hasOne และ where เพื่อดึงรูปที่เป็น primary (is_primary = true)
        // ใช้ withDefault() เพื่อป้องกัน Error ถ้าไม่มีรูปที่เป็น primary
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

