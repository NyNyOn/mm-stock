<div id="add-item-to-po-modal" class="fixed inset-0 bg-black bg-opacity-60 z-[60] hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col gentle-shadow animate-slide-up-soft">
        <div class="p-5 border-b border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">ค้นหาและเลือกอุปกรณ์จากคลัง</h3>
                <button onclick="closeModal('add-item-to-po-modal')" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <input type="text" id="po-item-search-input" placeholder="พิมพ์ชื่อ, S/N, หรือ Part No. เพื่อค้นหา..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div id="po-item-search-results" class="p-2 space-y-2 overflow-y-auto scrollbar-soft flex-grow">
            <div class="text-center p-8 text-gray-500">
                <p>เริ่มต้นค้นหาโดยการพิมพ์ในช่องด้านบน</p>
            </div>
        </div>
    </div>
</div>