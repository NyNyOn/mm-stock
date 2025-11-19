<div id="add-equipment-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 modal-backdrop-soft">
    <div
        class="flex flex-col w-full max-w-4xl soft-card rounded-3xl modal-content-wrapper animate-slide-up-soft gentle-shadow">

        {{-- Modal Header --}}
        {{-- ✅ EDIT from previous step: Padding is now px-4 py-3 sm:px-6 sm:py-4 --}}
        <div class="flex items-center justify-between px-4 py-3 border-b sm:px-6 sm:py-4">
            <h3 class="text-xl font-bold sm:text-2xl gradient-text-soft">➕ เพิ่มอุปกรณ์ใหม่</h3>
            <button onclick="closeModal('add-equipment-modal')"
                class="p-2 text-2xl text-gray-500 rounded-full hover:bg-gray-100">&times;</button>
        </div>

        {{-- Modal Content Wrapper --}}
        {{-- ✅✅✅ EDIT: Removed top padding (pt-4/sm:pt-6) by changing p-4 sm:p-6 to px-4 sm:px-6 pb-4 sm:pb-6 ✅✅✅ --}}
        <div id="add-form-content-wrapper" class="flex flex-col flex-grow px-4 pb-4 overflow-y-auto sm:px-6 sm:pb-6 scrollbar-soft">
            {{-- The form HTML will be injected here by JavaScript --}}
            <div class="p-8 text-center">
                <i class="text-3xl text-blue-500 fas fa-spinner fa-spin"></i>
                <p class="mt-2 text-gray-500">กำลังโหลดฟอร์ม...</p> {{-- Corrected loading text --}}
            </div>
        </div>

    </div>
</div>

