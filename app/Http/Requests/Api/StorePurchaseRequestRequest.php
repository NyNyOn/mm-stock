<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequestRequest extends FormRequest
{
    /**
     * ตรวจสอบว่าผู้ใช้มีสิทธิ์หรือไม่
     * (เราใช้ auth:sanctum จัดการแล้ว ให้เป็น true ไป)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * กฎการตรวจสอบข้อมูลที่ส่งเข้ามา (สำหรับ JSON ที่ 1)
     */
    public function rules(): array
    {
        return [
            // ตรวจสอบ User ID กับ DB กลาง
            'requestor_user_id' => 'required|integer|exists:depart_it_db.sync_ldap,id', 
            'origin_department_id' => 'nullable|integer',
            'priority' => [
                'required',
                'string',
                Rule::in(['Urgent', 'Normal', 'Low']), // อนุญาตเฉพาะค่าเหล่านี้
            ],

            // Items ต้องมีอย่างน้อย 1 รายการ
            'items' => 'required|array|min:1',
            
            // กฎสำหรับแต่ละ Item
            'items.*.item_name_custom' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_name' => 'required|string|max:50',
            'items.*.notes' => 'nullable|string',
        ];
    }
}