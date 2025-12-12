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
     * กฎสำหรับ PO มาตรฐาน (Standard PO) ที่ส่งมาจากระบบกลาง
     */
    public function rules(): array
    {
        return [
            // ข้อมูลระดับ Header ของ PO
            'po_number' => 'required|string|max:50|unique:purchase_orders,po_number',
            'ordered_by_user_id' => 'required|integer',
            'supplier_name' => 'nullable|string|max:255',
            'order_date' => 'required|date',
            'status' => 'nullable|string',
            
            // ข้อมูลรายการสินค้า (Items)
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_name' => 'nullable|string|max:50',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            
            // ✅✅✅ ต้องเพิ่มบรรทัดนี้ ไม่งั้น ID ที่ส่งมาจะหายหมด! ✅✅✅
            'items.*.equipment_id' => 'nullable|integer|exists:equipments,id', 
        ];
    }
}