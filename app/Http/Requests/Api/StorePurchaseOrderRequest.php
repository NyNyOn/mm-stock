<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * กฎสำหรับ PO มาตรฐาน
     * (เรายังไม่มีกฎตอนนี้ ใส่เป็น array ว่างไว้ก่อน)
     */
    public function rules(): array
    {
        return [
            // 'po_number' => 'required|string|unique:purchase_orders',
            // 'items.*.equipment_id' => 'required|exists:equipments,id',
            // 'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}