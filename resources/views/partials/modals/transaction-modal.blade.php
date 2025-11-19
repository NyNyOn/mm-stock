{{-- resources/views/partials/modals/transaction-modal.blade.php --}}
{{-- This is the CORRECT modal for displaying transaction details --}}
<div id="transaction-details-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 bg-black bg-opacity-60 animate-fade-in"> {{-- Use correct ID --}}
    <div class="w-full max-w-2xl max-h-[90vh] bg-white rounded-2xl shadow-xl soft-card animate-slide-up-soft flex flex-col">

        {{-- Header --}}
        <div class="flex items-center justify-between p-5 border-b flex-shrink-0">
            <h3 class="text-xl font-semibold text-gray-800">
                รายละเอียดธุรกรรม #<span id="modal-tx-id">...</span>
            </h3>
            <button onclick="closeModal('transaction-details-modal')" class="text-gray-400 hover:text-gray-600">
                <span class="text-2xl">&times;</span>
            </button>
        </div>

        {{-- Body: Scrollable Area --}}
        <div class="flex-grow p-6 overflow-y-auto scrollbar-soft space-y-5">

            {{-- Equipment Info --}}
            <div class="flex items-start space-x-4">
                <img id="modal-tx-image" src="{{ asset('images/placeholder.webp') }}" alt="Equipment Image" class="flex-shrink-0 object-cover w-24 h-24 border rounded-lg gentle-shadow bg-gray-50">
                <div class="flex-grow">
                    <p class="text-xs font-semibold text-gray-500 uppercase">อุปกรณ์</p>
                    <p id="modal-tx-equipment-name" class="text-lg font-bold text-gray-800 break-words">...</p>
                    <p class="mt-1 text-sm text-gray-600 font-mono">Serial: <span id="modal-tx-equipment-serial">...</span></p>
                </div>
            </div>

            {{-- Transaction Details Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                <div>
                    <p class="font-semibold text-gray-500">ประเภท:</p>
                    <p id="modal-tx-type" class="font-medium text-gray-800">...</p>
                </div>
                <div>
                    <p class="font-semibold text-gray-500">จำนวน:</p>
                    <p id="modal-tx-quantity" class="text-lg font-bold text-gray-800">...</p>
                </div>
                <div>
                    <p class="font-semibold text-gray-500">สถานะ:</p>
                    <div id="modal-tx-status" class="mt-1">...</div> {{-- Badge goes here --}}
                </div>
                <div>
                    <p class="font-semibold text-gray-500">ผู้ทำรายการ:</p>
                    <p id="modal-tx-user" class="font-medium text-gray-800">...</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="font-semibold text-gray-500">วันที่ทำรายการ:</p>
                    <p id="modal-tx-date" class="font-medium text-gray-800">...</p>
                </div>
            </div>

            {{-- ✅✅✅ START: แก้ไขส่วน Purpose & Notes ให้แยก GLPI ออก ✅✅✅ --}}
            {{-- Purpose (If not GLPI ticket) --}}
            <div id="modal-tx-purpose-section" class="pt-3 border-t"> {{-- Added ID --}}
                <p class="text-sm font-semibold text-gray-500">วัตถุประสงค์:</p>
                <p id="modal-tx-purpose" class="text-sm font-medium text-gray-800 whitespace-pre-wrap">...</p>
            </div>

            {{-- GLPI Ticket Info (Hidden by default, now separate) --}}
            <div id="modal-tx-glpi-section" class="hidden pt-3 border-t"> {{-- Keep ID --}}
                 <p class="text-sm font-semibold text-gray-500">ใบงาน GLPI ที่เกี่ยวข้อง:</p>
                 <a id="modal-tx-glpi-link" href="#" target="_blank" class="text-sm font-medium text-blue-600 hover:underline">...</a>
            </div>

            {{-- Notes (Always shown, may be empty) --}}
            <div class="pt-3 border-t"> {{-- Changed from space-y-3 to simpler structure --}}
                <p class="text-sm font-semibold text-gray-500">หมายเหตุ:</p>
                <p id="modal-tx-notes" class="text-sm font-medium text-gray-800 whitespace-pre-wrap">...</p>
            </div>
            {{-- ✅✅✅ END: แก้ไขส่วน Purpose & Notes ✅✅✅ --}}


             {{-- Confirmation Info --}}
             <div class="space-y-3 pt-3 border-t">
                 <h4 class="text-sm font-bold text-gray-600">ข้อมูลการยืนยัน</h4>
                 <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-2 text-xs">
                     <div><p class="font-semibold text-gray-500">ผู้จัดส่ง/คืน:</p> <p id="modal-tx-handler" class="text-gray-700">...</p></div>
                     <div><p class="font-semibold text-gray-500">Admin ยืนยัน:</p> <p id="modal-tx-admin-confirm-date" class="text-gray-700">...</p></div>
                     <div><p class="font-semibold text-gray-500">ผู้ใช้ยืนยัน:</p> <p id="modal-tx-user-confirm-date" class="text-gray-700">...</p></div>
                 </div>
             </div>

        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end p-5 bg-gray-50 border-t rounded-b-2xl flex-shrink-0">
            <button type="button" onclick="closeModal('transaction-details-modal')" class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                ปิด
            </button>
        </div>
    </div>
</div>
