<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Services\SmbStorageService;
use Illuminate\Support\Facades\Config; // <-- 1. เพิ่ม use Config

class EquipmentImage extends Model
{
    use HasFactory;

    protected $fillable = ['equipment_id', 'file_name', 'is_primary'];
    protected $appends = ['image_url'];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->file_name) {
            // <-- 2. ดึง defaultDeptKey จาก config -->
            $defaultDeptKey = Config::get('department_stocks.default_key', 'mm');

            try {
                // <-- 3. ส่ง deptKey ไปพร้อมกับ filename -->
                return route('nas.image', ['deptKey' => $defaultDeptKey, 'filename' => $this->file_name]);
            } catch (\Exception $e) {
                 // <-- 4. เพิ่มการดักจับ Error กรณี Route ผิดพลาด -->
                 \Log::error("Error generating nas.image route in EquipmentImage accessor: " . $e->getMessage());
                 return asset('images/placeholder.webp'); // Fallback on error
            }
        }
        return asset('images/placeholder.webp');
    }
}
