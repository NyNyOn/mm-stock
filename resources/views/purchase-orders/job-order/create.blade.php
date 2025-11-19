@extends('layouts.app')

@section('header', 'สร้างใบสั่งซื้อตาม Job')
@section('subtitle', 'สำหรับสั่งซื้ออะไหล่หรืออุปกรณ์สำหรับงานซ่อมโดยเฉพาะ')

@section('content')
<div class="container p-4 mx-auto">
    <div class="p-6 soft-card gentle-shadow rounded-2xl">
        {{-- ... ส่วนแสดง Error Messages (เหมือนเดิม) ... --}}

        {{-- ✅ FIX: เพิ่ม enctype="multipart/form-data" สำหรับการอัปโหลดไฟล์ --}}
        <form action="{{ route('job-orders.store') }}" method="POST" id="job-order-form" enctype="multipart/form-data">
            @csrf
            <div class="space-y-6">
                {{-- เลือกใบแจ้งซ่อม --}}
                <div>
                    <label for="maintenance_log_id" class="block text-sm font-bold text-gray-700">เลือกใบแจ้งซ่อมที่เกี่ยวข้อง*</label>
                    <select name="maintenance_log_id" id="maintenance_log_id" class="w-full mt-1 input-form" required>
                       {{-- ... options เหมือนเดิม ... --}}
                    </select>
                </div>

                {{-- ✅ FIX: ปรับปรุงส่วนรายการอุปกรณ์ทั้งหมด --}}
                <div>
                    <h3 class="text-lg font-bold text-gray-800">รายการที่ต้องการสั่งซื้อ</h3>
                    <div id="items-container" class="mt-2 space-y-4">
                        {{-- Item Row Template --}}
                        <div class="relative p-4 border rounded-lg item-row bg-gray-50">
                             <button type="button" class="absolute top-2 right-2 btn-icon-danger remove-item-btn">&times;</button>
                             <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                 {{-- Main Details --}}
                                 <div class="space-y-4">
                                     <input type="text" name="items[0][name]" placeholder="ชื่ออะไหล่/อุปกรณ์*" class="w-full input-form" required>
                                     <div class="flex gap-4">
                                         <input type="number" name="items[0][quantity]" placeholder="จำนวน*" class="w-1/2 input-form" min="1" required>
                                         <input type="text" name="items[0][link]" placeholder="ลิงก์อ้างอิง (ถ้ามี)" class="w-1/2 input-form">
                                     </div>
                                     <textarea name="items[0][specs]" placeholder="รายละเอียด / สเปค (ถ้ามี)" rows="3" class="w-full input-form"></textarea>
                                 </div>
                                 {{-- Image Upload --}}
                                 <div>
                                     <label class="block mb-1 text-sm font-medium text-gray-700">รูปภาพอ้างอิง</label>
                                     <input type="file" name="items[0][image]" class="w-full text-sm border rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                 </div>
                             </div>
                        </div>
                    </div>
                    <button type="button" id="add-item-btn" class="mt-4 text-sm btn-secondary"><i class="mr-1 fas fa-plus"></i> เพิ่มรายการ</button>
                </div>
            </div>

            <div class="flex justify-end pt-6 mt-6 border-t">
                <a href="{{ route('purchase-orders.index') }}" class="btn-secondary">ยกเลิก</a>
                <button type="submit" class="ml-2 btn-primary"><i class="mr-2 fas fa-save"></i> สร้างใบสั่งซื้อ</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const addItemBtn = document.getElementById('add-item-btn');
    const itemsContainer = document.getElementById('items-container');
    let itemIndex = itemsContainer.querySelectorAll('.item-row').length;

    const itemRowTemplate = (index) => `
        <div class="relative p-4 border rounded-lg item-row bg-gray-50">
             <button type="button" class="absolute top-2 right-2 btn-icon-danger remove-item-btn">&times;</button>
             <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                 <div class="space-y-4">
                     <input type="text" name="items[${index}][name]" placeholder="ชื่ออะไหล่/อุปกรณ์*" class="w-full input-form" required>
                     <div class="flex gap-4">
                         <input type="number" name="items[${index}][quantity]" placeholder="จำนวน*" class="w-1/2 input-form" min="1" required>
                         <input type="text" name="items[${index}][link]" placeholder="ลิงก์อ้างอิง (ถ้ามี)" class="w-1/2 input-form">
                     </div>
                     <textarea name="items[${index}][specs]" placeholder="รายละเอียด / สเปค (ถ้ามี)" rows="3" class="w-full input-form"></textarea>
                 </div>
                 <div>
                     <label class="block mb-1 text-sm font-medium text-gray-700">รูปภาพอ้างอิง</label>
                     <input type="file" name="items[${index}][image]" class="w-full text-sm border rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                 </div>
             </div>
        </div>
    `;

    addItemBtn.addEventListener('click', function() {
        const newItemDiv = document.createElement('div');
        newItemDiv.innerHTML = itemRowTemplate(itemIndex);
        itemsContainer.appendChild(newItemDiv.firstElementChild);
        itemIndex++;
    });

    itemsContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item-btn')) {
            if (itemsContainer.querySelectorAll('.item-row').length > 1) {
                e.target.closest('.item-row').remove();
            } else {
                alert('ต้องมีรายการสั่งซื้ออย่างน้อย 1 รายการ');
            }
        }
    });
});
</script>
@endpush
