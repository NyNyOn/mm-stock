<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * กฎการตรวจสอบข้อมูลที่ส่งเข้ามา (สำหรับ JSON ที่ 2)
     */
    public function rules(): array
    {
        return [
            'inspections' => 'required|array|min:1',
            
            // กฎสำหรับแต่ละรายการ Inspection
            // ตรวจสอบว่า ID มีอยู่จริงในตาราง purchase_order_items
            'inspections.*.pr_item_id' => 'required|integer|exists:purchase_order_items,id', 
            'inspections.*.status' => [
                'required',
                'string',
                Rule::in(['accepted', 'rejected', 'partial']), // สถานะที่อนุญาต
            ],
            // ตรวจสอบ field 'received_quantity' ที่มีอยู่เดิม
            'inspections.*.received_quantity' => 'required|integer|min:0', 
            'inspections.*.notes' => 'nullable|string',
        ];
    }
}