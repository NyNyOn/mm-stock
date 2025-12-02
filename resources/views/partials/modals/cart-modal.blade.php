<div id="cartModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        
        <!-- Overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 backdrop-blur-sm" aria-hidden="true" onclick="closeCartModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
            
            <!-- Header -->
            <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    ตะกร้าเบิกสินค้า (My Cart)
                </h3>
                <button onclick="closeCartModal()" class="text-white hover:text-gray-200 focus:outline-none transition transform hover:rotate-90">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="p-6 bg-white">
                
                <!-- 1. ส่วนเลือกผู้รับของ (Global Receiver) -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-5 mb-6">
                    <h4 class="font-bold text-blue-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        ผู้รับของ (Receiver)
                    </h4>
                    <div class="flex flex-col sm:flex-row gap-6 mb-3">
                        <label class="inline-flex items-center cursor-pointer group">
                            <input type="radio" name="global_receiver_type" value="self" class="form-radio text-indigo-600 h-5 w-5 border-gray-300 focus:ring-indigo-500" checked onchange="toggleGlobalReceiverInput()">
                            <span class="ml-2 text-gray-700 group-hover:text-indigo-700 transition font-medium">เบิกให้ตัวเอง</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer group">
                            <input type="radio" name="global_receiver_type" value="other" class="form-radio text-indigo-600 h-5 w-5 border-gray-300 focus:ring-indigo-500" onchange="toggleGlobalReceiverInput()">
                            <span class="ml-2 text-gray-700 group-hover:text-indigo-700 transition font-medium">เบิกให้ผู้อื่น</span>
                        </label>
                    </div>
                    
                    <div id="global_receiver_input_container" class="hidden animate-fade-in-down">
                        <label class="block text-xs font-medium text-gray-500 mb-1">ค้นหาชื่อผู้รับ หรือ รหัสพนักงาน</label>
                        {{-- ✅ Select2 ค้นหาชื่อ --}}
                        <select id="global_receiver_id" class="w-full" style="width: 100%;">
                            <option value="" selected>พิมพ์ชื่อ หรือรหัสพนักงาน...</option>
                        </select>
                        <button onclick="applyReceiverToAll()" class="mt-2 text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-200 transition">
                            <i class="fas fa-check-double mr-1"></i> ใช้ผู้รับคนนี้กับทุกรายการ
                        </button>
                    </div>
                </div>

                <!-- 2. ตารางรายการสินค้า -->
                <div class="overflow-hidden border border-gray-200 rounded-lg shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-1/4">สินค้า</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-24">จำนวน</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    วัตถุประสงค์
                                    <button onclick="applyPurposeToAll()" class="ml-1 text-[10px] text-indigo-600 hover:underline">(ใช้เหมือนกัน)</button>
                                </th>
                                {{-- ✅ คอลัมน์เลือกผู้รับรายคน (ซ่อนไว้ก่อน ถ้าเลือกเบิกให้คนอื่นค่อยโชว์) --}}
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-1/4 receiver-col hidden">
                                    ผู้รับ (รายคน)
                                </th>
                                <th class="px-4 py-3 text-center w-12 text-xs font-semibold text-gray-600 uppercase">ลบ</th>
                            </tr>
                        </thead>
                        <tbody id="cart-items-container" class="bg-white divide-y divide-gray-200">
                            <!-- JS จะสร้างรายการตรงนี้ -->
                        </tbody>
                    </table>
                </div>
                
                <div id="empty-cart-msg" class="text-center py-12 hidden flex flex-col items-center justify-center">
                    <div class="bg-gray-100 p-4 rounded-full mb-3">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <span class="text-gray-500 font-medium">ยังไม่มีสินค้าในตะกร้า</span>
                    <button onclick="closeCartModal()" class="mt-2 text-indigo-600 hover:underline text-sm">ไปเลือกสินค้า</button>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="bg-gray-50 px-6 py-4 sm:flex sm:flex-row-reverse gap-3">
                <button type="button" onclick="submitCart()" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-6 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:text-sm transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    ยืนยันการเบิก
                </button>
                <button type="button" onclick="closeCartModal()" class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm transition">
                    ปิดหน้าต่าง
                </button>
                <button type="button" onclick="clearCart()" class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-red-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:text-sm sm:mr-auto transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    ล้างตะกร้า
                </button>
            </div>
        </div>
    </div>
    <style>
        .animate-fade-in-down { animation: fadeInDown 0.3s ease-out; }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        /* Style Select2 ให้ดูดีขึ้นใน Modal */
        .select2-container { z-index: 9999; } 
        .select2-dropdown { z-index: 10000; }
    </style>
</div>