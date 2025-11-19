<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PurchaseOrderItemResource;
use Illuminate\Support\Facades\Log; // ✅ 1. เพิ่ม Log

class PurchaseOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // ✅ 2. START: แก้ไข Logic การแปลค่าทั้งหมด
        
        // ใช้ match statement เพื่อเลือกค่า Priority ที่ถูกต้องจาก config โดยตรง
        $priority = match ($this->type) {
            'scheduled'                => config('services.pu_hub.priorities.scheduled'),
            'urgent'                   => config('services.pu_hub.priorities.urgent'),
            'job_order', 'job_order_glpi' => config('services.pu_hub.priorities.job'),
            default                    => config('services.pu_hub.priorities.scheduled'),
        };

        // ✅ 3. เพิ่มระบบป้องกัน: ตรวจสอบว่าค่า config ไม่ใช่ null
        // นี่คือส่วนที่สำคัญที่สุดในการแก้ปัญหา
        if (is_null($priority)) {
            $errorMessage = "Priority mapping for type '{$this->type}' is NULL. Please run 'php artisan optimize:clear' to refresh the configuration cache.";
            // บันทึก Error ลง Log เพื่อให้เราเห็น
            Log::critical($errorMessage);
            // โยน Exception เพื่อหยุดการทำงานทันที และป้องกันการส่งข้อมูลที่ผิดพลาด
            throw new \Exception($errorMessage);
        }

        // ✅ END: สิ้นสุดการแก้ไข
        
        return [
            'requestor_user_id'    => $this->ordered_by_user_id,
            'origin_department_id' => $this->whenLoaded('requester', $this->requester->department_id ?? 1),
            'priority'             => $priority, // ใช้ค่าที่ตรวจสอบแล้ว
            'items'                => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}