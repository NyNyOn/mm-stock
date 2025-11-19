<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // โครงสร้างนี้ตรงตามที่ PU Hub ต้องการสำหรับแต่ละ Item
        return [
            // จากคู่มือ Postman: "item_name_custom"
            'item_name_custom' => $this->whenLoaded('equipment', $this->equipment->name, $this->item_description),

            // จากคู่มือ Postman: "quantity"
            'quantity' => $this->quantity_ordered,

            // จากคู่มือ Postman: "unit_name"
            'unit_name' => $this->whenLoaded('equipment', function() {
                return $this->equipment->unit->name ?? 'N/A';
            }),
            
            // เพิ่ม job_ticket_id สำหรับ PO ประเภท Job
            'job_ticket_id' => $this->when($this->purchaseOrder?->type === 'job_order_glpi' || $this->purchaseOrder?->type === 'job_order', 
                $this->whenLoaded('purchaseOrder', $this->purchaseOrder->glpi_ticket_id)
            ),

            // จากคู่มือ Postman: "notes"
            'notes' => $this->item_description,
        ];
    }
}
