@props(['status'])

@php
    $statusClass = match ($status) {
        'available' => 'bg-green-100 text-green-800 border-green-200',
        'low_stock', 'low-stock' => 'bg-orange-100 text-orange-800 border-orange-200',
        'out_of_stock', 'out-of-stock' => 'bg-red-100 text-red-800 border-red-200',
        'maintenance' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'repairing' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
        'disposed' => 'bg-gray-100 text-gray-800 border-gray-200',
        'sold' => 'bg-purple-100 text-purple-800 border-purple-200',
        'on_loan', 'on-loan', 'borrowed' => 'bg-blue-100 text-blue-800 border-blue-200',
        'on-order', 'on_order' => 'bg-sky-100 text-sky-800 border-sky-200',
        'frozen' => 'bg-cyan-100 text-cyan-800 border-cyan-200', // ✅ เพิ่ม Frozen ตรงนี้
        default => 'bg-gray-100 text-gray-600 border-gray-200',
    };

    $statusLabel = match ($status) {
        'available' => 'พร้อมใช้งาน',
        'low_stock', 'low-stock' => 'สต็อกต่ำ',
        'out_of_stock', 'out-of-stock' => 'สินค้าหมด',
        'maintenance' => 'ซ่อมบำรุง',
        'repairing' => 'กำลังซ่อม',
        'disposed' => 'ตัดจำหน่าย',
        'sold' => 'ขายแล้ว',
        'on_loan', 'on-loan', 'borrowed' => 'ถูกยืม',
        'on-order', 'on_order' => 'กำลังสั่งซื้อ',
        'frozen' => 'ระงับ (Frozen)', // ✅ เพิ่มข้อความ Frozen ตรงนี้
        default => ucfirst(str_replace('_', ' ', $status)),
    };

    $icon = match ($status) {
        'available' => 'fa-check-circle',
        'low_stock', 'low-stock' => 'fa-exclamation-triangle',
        'out_of_stock', 'out-of-stock' => 'fa-times-circle',
        'maintenance' => 'fa-tools',
        'repairing' => 'fa-wrench',
        'disposed' => 'fa-trash-alt',
        'sold' => 'fa-dollar-sign',
        'on_loan', 'on-loan', 'borrowed' => 'fa-hand-holding',
        'on-order', 'on_order' => 'fa-shipping-fast',
        'frozen' => 'fa-snowflake', // ✅ เพิ่มไอคอน Frozen ตรงนี้
        default => 'fa-circle',
    };
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $statusClass }}">
    <i class="fas {{ $icon }} mr-1.5"></i>
    {{ $statusLabel }}
</span>