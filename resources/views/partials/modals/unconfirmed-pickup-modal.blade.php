<div class="fixed inset-0 z-[100] items-center justify-center hidden bg-black bg-opacity-50" id="unconfirmed-pickup-modal">
    <div class="w-full max-w-lg p-5 mx-auto bg-white rounded-2xl soft-card animate-slide-up-soft">
        <div class="flex items-start justify-between pb-3 border-b">
            <div class="flex items-center">
                <div class="flex items-center justify-center w-10 h-10 mr-3 text-yellow-600 bg-yellow-100 rounded-full">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="text-xl font-bold">แจ้งเตือนรายการค้างรับ</h3>
            </div>
            <button type="button" class="text-black" onclick="closeModal('unconfirmed-pickup-modal')">&times;</button>
        </div>

        <div class="py-4">
            <p class="mb-4 text-gray-700">คุณมีรายการอุปกรณ์ที่เบิก/ยืมไป แต่ยังไม่ได้กดยืนยันว่าได้รับของแล้ว กรุณายืนยันเพื่อป้องกันข้อผิดพลาดและเพื่อให้สามารถทำรายการใหม่ได้</p>
            <div id="unconfirmed-items-list" class="space-y-2 overflow-y-auto max-h-60">
                </div>
        </div>

        <div class="flex justify-end pt-3 border-t">
            <button type="button" class="px-4 py-2 mr-2 text-white bg-gray-500 rounded-lg hover:bg-gray-700" onclick="closeModal('unconfirmed-pickup-modal')">ปิดไปก่อน</button>
            <button type="button" id="confirm-all-btn" onclick="confirmAllPickups()" class="px-4 py-2 text-white bg-blue-500 rounded-lg hover:bg-blue-700">
                <i class="mr-2 fas fa-check-double"></i>ยืนยันทั้งหมด
            </button>
        </div>
    </div>
</div>
