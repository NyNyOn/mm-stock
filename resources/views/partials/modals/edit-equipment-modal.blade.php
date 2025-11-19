<div id="edit-equipment-modal" class="fixed inset-0 modal-backdrop-soft hidden items-center justify-center z-50 p-4">
    <div class="soft-card rounded-3xl w-full max-w-4xl modal-content-wrapper flex flex-col animate-slide-up-soft gentle-shadow">

        {{-- Modal Header --}}
        {{-- ✅ EDIT from previous step: Padding is now px-4 py-3 sm:px-6 sm:py-4 --}}
        <div class="px-4 py-3 border-b sm:px-6 sm:py-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xl sm:text-2xl font-bold gradient-text-soft">✏️ แก้ไขข้อมูลอุปกรณ์</h3>
                <button onclick="closeModal('edit-equipment-modal')" class="p-2 rounded-full hover:bg-gray-100 text-gray-500 text-2xl">&times;</button>
            </div>
        </div>

        {{-- Modal Content Wrapper --}}
        {{-- ✅✅✅ EDIT: Removed top padding (pt-4/sm:pt-6) by changing p-4 sm:p-6 to px-4 sm:px-6 pb-4 sm:pb-6 ✅✅✅ --}}
        <div id="edit-form-content-wrapper" class="px-4 pb-4 sm:px-6 sm:pb-6 flex-grow overflow-y-auto scrollbar-soft">
             {{-- Edit form will be injected here --}}
             <div class="flex justify-center items-center h-48"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i></div> {{-- Added loading state --}}
        </div>
    </div>
</div>

