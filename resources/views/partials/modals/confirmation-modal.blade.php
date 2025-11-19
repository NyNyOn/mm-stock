<div id="confirmation-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm">
    <div class="w-full max-w-md p-6 text-center bg-white shadow-xl soft-card rounded-2xl animate-slide-up-soft">
        <div class="flex justify-center mb-4">
             <div id="confirmation-icon-wrapper" class="flex items-center justify-center w-16 h-16 rounded-full">
                <i id="confirmation-icon" class="text-4xl text-white"></i>
            </div>
        </div>
        <h3 id="confirmation-title" class="mb-2 text-lg font-bold text-gray-800"></h3>
        <div id="confirmation-message" class="mb-6 text-sm text-gray-600"></div>
        <div class="flex justify-center space-x-4">
            <button id="confirmation-cancel-btn" class="px-6 py-2 font-medium text-gray-800 bg-gray-200 rounded-lg hover:bg-gray-300">ยกเลิก</button>
            <button id="confirmation-confirm-btn" class="px-6 py-2 font-bold text-white rounded-lg"></button>
        </div>
    </div>
</div>
