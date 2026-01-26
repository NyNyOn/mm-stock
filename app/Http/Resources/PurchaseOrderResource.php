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

        $autoRequesterId = \App\Models\Setting::where('key', 'automation_requester_id')->value('value');
        $jobRequesterId = \App\Models\Setting::where('key', 'automation_job_requester_id')->value('value');

        return [
            // ✅ Spec Field: requestor_id (Employee Code)
            'requestor_id'         => optional($this->requester)->employeecode ?? (string)($this->ordered_by_user_id ?? 1),
            
            // ✅ Spec Field: requestor_fullname
            'requestor_fullname'   => optional($this->requester)->fullname ?? 'System Admin',

            // ✅ Spec Field: department_code
            'department_code'      => optional(optional($this->requester)->department)->code ?? 'MM', // Default to MM

            // ✅ Spec Field: urgency
            'urgency'              => $priority, 

            // ✅ Spec Field: origin_pr_number (Reference)
            'origin_pr_number'     => $this->po_number,

            // ✅ Spec Field: notes
            'notes'                => $this->notes, 

            // ✅ Spec Field: items (Array)
            'items'                => PurchaseOrderItemResource::collection($this->whenLoaded('items')),

            // --- Backward Compatibility / Legacy Fields ---
            'requestor_user_id'    => $this->ordered_by_user_id,
            'origin_department_id' => optional($this->requester)->department_id,
            'priority'             => $priority,
            'resubmit_note'        => $request->input('resubmit_note'),
            'id'                   => $this->id,
            'po_number'            => $this->po_number,
            'status'               => $this->status,
            'created_at'           => $this->created_at->toIso8601String(),
            'updated_at'           => $this->updated_at->toIso8601String(),
        ];
    }
}