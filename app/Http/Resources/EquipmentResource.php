<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // ✅ 1. ดึง URL รูปภาพผ่านระบบ NAS ที่คุณมีอยู่แล้ว
        // เรียกใช้ relationship 'latestImage' และ accessor 'image_url' จาก EquipmentImage Model
        $imageUrl = $this->latestImage ? $this->latestImage->image_url : null;

        // กรณีที่ $imageUrl เป็น Relative Path (เช่น /nas-images/...) ให้เติม Domain เข้าไปให้ครบ
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $imageUrl = url($imageUrl);
        }

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'part_no'       => $this->part_no, // ตรวจสอบว่าใน DB ใช้ part_no หรือ part_number
            'model'         => $this->model,
            'serial_number' => $this->serial_number, // ตรวจสอบว่าใน DB ใช้ serial หรือ serial_number
            
            // ✅ ส่ง Link รูปภาพที่ถูกต้องไปให้ PU
            'image_url'     => $imageUrl, 
            
            'unit'          => $this->whenLoaded('unit', fn() => $this->unit->name),
            'stock'         => [
                'current' => $this->quantity, // แก้จาก $this->stock เป็น $this->quantity ตาม Model
                'min'     => $this->min_stock,
                'max'     => $this->max_stock,
            ],
            'supplier'      => $this->supplier,
            'updated_at'    => $this->updated_at->toIso8601String(),
        ];
    }
}