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
            // ✅ 1. Standard Fields required by PU-HUB Spec (PR Intake)
            'item_name' => $this->equipment ? $this->equipment->name : $this->item_description,
            'quantity' => $this->quantity_ordered,
            'unit' => ($this->equipment && $this->equipment->unit) 
                            ? $this->equipment->unit->name 
                            : ($this->unit_name ?? 'N/A'),
            
            // ✅ 1.1 Legacy Fields (REQUIRED by Current PU API Validator)
            'item_name_custom' => $this->equipment ? $this->equipment->name : $this->item_description,
            'unit_name' => ($this->equipment && $this->equipment->unit) 
                            ? $this->equipment->unit->name 
                            : ($this->unit_name ?? 'N/A'),

            // ✅ 2. ID References
            'origin_item_id' => $this->equipment_id, // New Spec: ID in Origin System
            'master_item_id' => null, // Legacy ID (Nullable)

            // ✅ 3. Extra Fields (Keep for backward compatibility or internal tracking)
            'id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'pr_item_id' => $this->pr_item_id, // Reference from PU if update
            
            'notes' => '', 
            'unit_price' => $this->unit_price,
            
            'job_ticket_id' => $this->when($this->purchaseOrder?->type === 'job_order_glpi' || $this->purchaseOrder?->type === 'job_order', 
                $this->whenLoaded('purchaseOrder', function() {
                    return $this->purchaseOrder->glpi_ticket_id;
                })
            ),
        ];
    }
}