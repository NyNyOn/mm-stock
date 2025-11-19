{{-- resources/views/partials/modals/select-item-modal.blade.php --}}
<div id="select-item-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 modal-backdrop-soft">
    <div class="w-full max-w-2xl soft-card rounded-2xl modal-content-wrapper animate-slide-up-soft">
        <div class="relative p-6">
            <button type="button" class="absolute text-2xl text-gray-500 top-4 right-4 hover:text-gray-700"
                onclick="closeModal('select-item-modal')">&times;</button>
            <h3 class="text-xl font-bold text-gray-800">เลือกรายการเพื่อเพิ่มในใบสั่งซื้อ</h3>

            <div class="mt-4">
                <input type="text" id="select-item-search" placeholder="🔍 ค้นหาด้วยชื่อ, Part No., Serial..."
                    class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-gray-100 border-0 rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
            </div>

            {{-- ✅✅✅ START: โค้ดส่วนที่เพิ่มเข้ามาใหม่ (แท็บ) ✅✅✅ --}}
            <div class="flex mt-4 border-b border-gray-200">
                <button id="tab-in-stock"
                        class="px-4 py-2 text-sm font-semibold text-center border-b-2 tab-button border-blue-500 text-blue-600">
                    <i class="mr-2 fas fa-check-circle"></i> มีในสต็อก
                </button>
                <button id="tab-out-of-stock"
                        class="px-4 py-2 text-sm font-semibold text-center text-gray-500 border-b-2 border-transparent tab-button hover:text-gray-700">
                    <i class="mr-2 fas fa-ban"></i> สต็อกหมด
                </button>
            </div>
            {{-- ✅✅✅ END: โค้ดส่วนที่เพิ่มเข้ามาใหม่ (แท็บ) ✅✅✅ --}}

            <div id="select-item-list" class="mt-4 space-y-2 overflow-y-auto max-h-96 scrollbar-soft">
                {{-- Item list will be loaded here via AJAX --}}
            </div>

            <div id="select-item-pagination" class="flex items-center justify-center pt-4 mt-4 border-t">
                {{-- Pagination links will be loaded here --}}
            </div>
        </div>
    </div>
</div>