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
        'last_stock_check_at', // ✅ ต้องมีฟิลด์นี้
    ];

    protected $casts = [
        'price' => 'float',
        'purchase_date' => 'date:Y-m-d',
        'warranty_date' => 'date:Y-m-d',
        'last_stock_check_at' => 'datetime', // ✅ สำคัญ: ต้องแปลงเป็น datetime ไม่งั้นหน้า Dashboard จะพัง
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
                'on_loan', 'repairing', 'inactive', 'written_off',
                'frozen' // ✅ เพิ่มสถานะ frozen
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
    
    // ✅ Added Relation for Ratings
    // ratings relation already exists or handled above


    // transactions relation already exists or handled above


    // ✅ Smart Rating Calculation Logic
    public function calculateSmartRating()
    {
        // 1. Inventory Efficiency Score (50%)
        // Concept: Frequent = 5, Deadstock (Expensive) = 1, Deadstock (Cheap) = 3
        
        $price = $this->price ?? 0;
        
        // Count withdrawals in last 90 days (Quarterly Activity)
        $withdrawCount = $this->transactions()
            ->whereIn('type', ['withdraw', 'consumable', 'returnable', 'partial_return'])
            ->where('transaction_date', '>=', now()->subDays(90))
            ->count();
            
        // Calculate Efficiency Score
        $efficiencyScore = 3.0; // Default Neutral

        if ($withdrawCount >= 3) {
            // Frequent (Hot Item)
            $efficiencyScore = 5.0;
        } elseif ($withdrawCount > 0) {
            // Normal Movement
            $efficiencyScore = 4.0;
        } else {
            // No movement in 90 days (Slow/Deadstock)
            if ($price <= 0) {
                // No Price: Neutral / Safe
                $efficiencyScore = 3.0;
            } elseif ($price < 500) { 
                // Cheap (< 500 THB): Neutral
                $efficiencyScore = 3.0;
            } else {
                // Expensive (>= 500 THB) & Dead: Penalty!
                $efficiencyScore = 1.0; 
            }
        }

        // 2. User Satisfaction Score (50%)
        // Average of user ratings (if any)
        
        $avgUserRating = $this->ratings()->avg('rating_score');
        $userRatingCount = $this->ratings()->count();

        if ($userRatingCount > 0) {
            // Weighted Average: (Efficiency + User) / 2
            $finalScore = ($efficiencyScore + $avgUserRating) / 2;
        } else {
            // Only efficiency available
            $finalScore = $efficiencyScore;
        }

        // 3. Save to DB
        $this->smart_rating = round($finalScore, 1);
        $this->last_rating_update = now();
        $this->saveQuietly(); // Don't trigger updated_at
        
        return $this->smart_rating;
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
    
    public function ratings(): HasMany
    {
        return $this->hasMany(EquipmentRating::class);
    }

    /**
     * ✅ นับจำนวนประเมินแต่ละประเภท (ถูกใจ/พอใช้/แย่)
     * ใช้ฐานข้อมูลที่กำลังเชื่อมต่ออยู่ (แต่ละแผนกมีตาราง equipment_ratings ของตัวเอง)
     */
    public function feedbackCounts(): array
    {
        try {
            return [
                'good' => $this->ratings()->where('feedback_type', 'good')->count(),
                'neutral' => $this->ratings()->where('feedback_type', 'neutral')->count(),
                'bad' => $this->ratings()->where('feedback_type', 'bad')->count(),
            ];
        } catch (\Exception $e) {
            // ถ้าตารางไม่มี หรือ error อื่นๆ ให้ return 0 ทั้งหมด
            return ['good' => 0, 'neutral' => 0, 'bad' => 0];
        }
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

    // ✅ ฟังก์ชันเช็คสถานะ Frozen
    public function isFrozen()
    {
        return $this->status === 'frozen';
    }
    
    // Helper to get image URL properly (เพิ่มเพื่อให้ ReportController ทำงานได้สมบูรณ์กับ JS ที่แก้ไป)
    public function getImageUrlAttribute()
    {
        // ✅ Fix: Check ->exists because withDefault() returns an empty model, not null
        $image = ($this->primaryImage && $this->primaryImage->exists) 
                    ? $this->primaryImage 
                    : $this->latestImage;
        
        if (!$image || !$image->exists) {
            return asset('images/placeholder.webp');
        }

        if (filter_var($image->file_path, FILTER_VALIDATE_URL)) {
            return $image->file_path;
        }

        // ✅ Check if file_path is empty to prevent returning directory URL (images/)
        if (empty($image->file_path) && empty($image->file_name)) {
             return asset('images/placeholder.webp');
        }

        // ✅ Use NAS Route if available (Preferred)
        if (!empty($image->file_name)) {
            $deptKey = config('department_stocks.default_nas_dept_key', 'mm'); // Default to MM if not set
            try {
                return route('nas.image', ['deptKey' => $deptKey, 'filename' => $image->file_name]);
            } catch (\Exception $e) {
                // Fallback if route not defined
            }
        }

        // Fallback to local path logic
        return url('images/' . $image->file_path);
    }
}