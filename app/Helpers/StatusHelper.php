<?php

namespace App\Helpers;

class StatusHelper
{
    /**
     * ดึงรายละเอียดสถานะสำหรับ Equipment
     */
    public static function getStatusDetails(string $status): array
    {
        $statuses = [
            'available' => ['name' => '✅ พร้อมใช้งาน', 'class' => 'bg-green-100 text-green-800'],
            'low_stock' => ['name' => '⚠️ สต็อกต่ำ', 'class' => 'bg-orange-100 text-orange-800'],
            'out-of-stock' => ['name' => '🚫 สต๊อกหมด', 'class' => 'bg-red-100 text-red-800'],
            'maintenance' => ['name' => '🔧 ซ่อมบำรุง', 'class' => 'bg-purple-100 text-purple-800'],
            'on-order' => ['name' => '⏳ กำลังสั่งซื้อ', 'class' => 'bg-blue-100 text-blue-800'],
            
            // เพิ่มสถานะอื่นๆ ที่อาจมีในระบบ
            'on_loan'       => ['name' => '👥 ถูกยืม/ใช้งานอยู่', 'class' => 'bg-teal-100 text-teal-700'],
            'repairing'     => ['name' => '🛠️ ซ่อม', 'class' => 'bg-indigo-100 text-indigo-800'],
            'inactive'      => ['name' => '⭕ ไม่ใช้', 'class' => 'bg-gray-200 text-gray-600'],
            'disposed'      => ['name' => '❌ จำหน่าย', 'class' => 'bg-pink-200 text-pink-800'],
            'sold'          => ['name' => '❌ ขายแล้ว', 'class' => 'bg-pink-200 text-pink-800'],
            'written_off'   => ['name' => '❌ ตัดจำหน่าย', 'class' => 'bg-pink-200 text-pink-800'],
        ];

        return $statuses[$status] ?? ['name' => '❓ ไม่ทราบ (' . $status . ')', 'class' => 'bg-gray-100 text-gray-800'];
    }

    // ✅✅✅ START: เพิ่ม 2 ฟังก์ชันที่ขาดไป (ต้นเหตุของ Error) ✅✅✅

    /**
     * ดึงข้อความสถานะ (Text) สำหรับ Purchase Order
     * (ฟังก์ชันนี้ถูกเรียกใช้โดย receive.index.blade.php)
     */
    public static function getPurchaseOrderStatusText(string $status): string
    {
        return match ($status) {
            'pending' => 'รอดำเนินการ',
            'ordered' => 'ส่งแล้ว',
            'shipped_from_supplier' => 'จัดส่งแล้ว',
            'partial_receive' => 'รับแล้วบางส่วน',
            'received' => 'รับครบแล้ว', // (สถานะของ Item)
            'completed' => 'เสร็จสมบูรณ์',
            'job_order' => 'Job Order',
            'job_order_glpi' => 'Job (GLPI)',
            default => ucfirst($status),
        };
    }

    /**
     * ดึงคลาสสี (Tailwind Class) สำหรับ Purchase Order
     * (ฟังก์ชันนี้คือตัวที่ Error ในรูปภาพของคุณ)
     */
    public static function getPurchaseOrderStatusClass(string $status): string
    {
        return match ($status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'ordered' => 'bg-blue-100 text-blue-800',
            'shipped_from_supplier' => 'bg-cyan-100 text-cyan-800',
            'partial_receive' => 'bg-indigo-100 text-indigo-800',
            'received' => 'bg-green-100 text-green-800',
            'completed' => 'bg-green-100 text-green-800',
            'job_order' => 'bg-gray-100 text-gray-800',
            'job_order_glpi' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    // ✅✅✅ END: สิ้นสุดส่วนที่เพิ่ม ✅✅✅
}

