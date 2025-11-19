<div id="order-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg flex flex-col gentle-shadow animate-slide-up-soft">
        <form id="order-form" method="POST" onsubmit="handleFormSubmit(event)">
            @csrf
            
            <div class="p-5 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-800">ยืนยันการสั่งซื้อ</h3>
                <p id="order-item-name" class="text-sm text-gray-600"></p>
            </div>

            <div class="p-6">
                <p class="text-gray-700">
                    คุณต้องการเปลี่ยนสถานะของอุปกรณ์นี้เป็น <strong class="text-blue-600">"กำลังสั่งซื้อ"</strong> ใช่หรือไม่?
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    การดำเนินการนี้จะช่วยให้ทีมจัดซื้อทราบว่าต้องดำเนินการสั่งซื้ออุปกรณ์ชิ้นนี้
                </p>
            </div>

            <div class="p-5 border-t border-gray-200 bg-gray-50 rounded-b-2xl flex justify-end space-x-3">
                <button type="button" onclick="closeModal('order-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 font-medium">ยกเลิก</button>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">
                    <i class="fas fa-check-circle mr-2"></i>ยืนยัน
                </button>
            </div>
        </form>
    </div>
</div>