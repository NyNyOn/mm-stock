<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'part_no' => $this->part_number,
            'model' => $this->model,
            'serial_number' => $this->serial,
            'image_url' => $this->image_path,
            'unit' => $this->whenLoaded('unit', fn() => $this->unit->name), // ใช้ whenLoaded เพื่อป้องกัน Error
            'warranty_expire_date' => $this->warranty_date,
            'stock' => [
                'current' => $this->stock,
                'min' => $this->min_stock,
                'max' => $this->max_stock,
            ],
            'supplier' => $this->supplier,
        ];
    }
}