<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PurchaseOrderItemResource;
use Illuminate\Support\Facades\Log;

class PurchaseOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // 1. Logic การแปลงค่า Priority (ใช้ Match เพื่อความปลอดภัย)
        $priority = match ($this->type) {
            'scheduled'                => config('services.pu_hub.priorities.scheduled'),
            'urgent'                   => config('services.pu_hub.priorities.urgent'),
            'job_order', 'job_order_glpi' => config('services.pu_hub.priorities.job'),
            default                    => config('services.pu_hub.priorities.scheduled'), // ค่า Default
        };

        // 2. ระบบป้องกัน: ถ้า Config หาย ให้แจ้งเตือนแต่ไม่ทำให้ระบบล่ม
        if (is_null($priority)) {
            Log::warning("PurchaseOrderResource: Priority config is missing for type '{$this->type}'. Using default.");
            $priority = 'Normal'; // Fallback value ป้องกัน Error 500
        }

        return [
            'id'                   => $this->id,          // ✅ เพิ่ม: เพื่อให้ PU อ้างอิงกลับมาได้
            'po_number'            => $this->po_number,   // ✅ เพิ่ม: เลขที่ใบสั่งซื้อ
            'status'               => $this->status,      // ✅ เพิ่ม: สถานะปัจจุบัน
            'requestor_user_id'    => $this->ordered_by_user_id,
            'origin_department_id' => $this->whenLoaded('requester', fn() => $this->requester->department_id ?? null),
            'priority'             => $priority,
            'items'                => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_at'           => $this->created_at->toIso8601String(),
            'updated_at'           => $this->updated_at->toIso8601String(),
        ];
    }
}