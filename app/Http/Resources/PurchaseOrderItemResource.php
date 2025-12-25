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
        return [
            // ✅ AUTO-FIX: Send Local ID so we can match it later
            'id' => $this->id,
            
            // 1. แก้ไขชื่อสินค้า: เช็คก่อนว่ามี equipment ไหม
            // ถ้ามี: ใช้ชื่อจาก Equipment
            // ถ้าไม่มี (null): ใช้ item_description ที่เราบันทึกตอน create
            'item_name_custom' => $this->equipment ? $this->equipment->name : $this->item_description,

            // ปริมาณ
            'quantity' => $this->quantity_ordered,

            // 2. แก้ไขหน่วยนับ: เช็คก่อนว่ามี equipment และ unit ไหม
            // ถ้ามี: ใช้หน่วยของ Equipment
            // ถ้าไม่มี: ใช้ unit_name ที่เราบันทึกในตาราง (หรือ default N/A)
            'unit_name' => ($this->equipment && $this->equipment->unit) 
                            ? $this->equipment->unit->name 
                            : ($this->unit_name ?? 'N/A'),
            
            // Job Ticket ID (เหมือนเดิม)
            'job_ticket_id' => $this->when($this->purchaseOrder?->type === 'job_order_glpi' || $this->purchaseOrder?->type === 'job_order', 
                $this->whenLoaded('purchaseOrder', function() {
                    return $this->purchaseOrder->glpi_ticket_id;
                })
            ),

            // หมายเหตุ
            'notes' => $this->item_description,
            
            // ราคาต่อหน่วย (แถมให้: ถ้าอยากส่งราคากลับไปด้วย)
            'unit_price' => $this->unit_price,
        ];
    }
}