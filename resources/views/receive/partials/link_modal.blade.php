    <!-- Link Equipment Modal -->
    <div id="link-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="hideLinkModal()"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <form id="link-form" method="POST" action="">
                    @csrf
                    <div>
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100">
                            <i class="fas fa-link text-indigo-600 text-lg"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                เชื่อมโยงอุปกรณ์ (Link Equipment)
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    รายการ: <span id="link-item-name" class="font-bold text-gray-800"></span>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    ค้นหาอุปกรณ์ที่มีอยู่ในระบบเพื่อเชื่อมโยง หรือสร้างใหม่หากยังไม่มี
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 relative">
                            <label for="equipment-search" class="block text-sm font-medium text-gray-700">ค้นหาอุปกรณ์ (ชื่อ / รหัส)</label>
                            <input type="text" id="equipment-search" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="พิมพ์ชื่ออุปกรณ์เพื่อค้นหา...">
                            
                            <!-- Search Results Dropdown -->
                            <div id="search-results" class="hidden absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                <!-- Dynamic Results -->
                            </div>
                        </div>

                        <!-- Selected Equipment Display -->
                        <div id="selected-equipment-display" class="hidden mt-4 p-3 bg-indigo-50 rounded-lg border border-indigo-200 flex justify-between items-center">
                            <div>
                                <span class="text-xs text-indigo-500 font-bold uppercase">เลือกแล้ว:</span>
                                <div id="selected-name" class="text-sm font-bold text-indigo-900"></div>
                            </div>
                            <span class="text-indigo-500"><i class="fas fa-check-circle"></i></span>
                        </div>
                        <input type="hidden" id="selected-equipment-id" name="equipment_id">

                        <div class="mt-4 text-center">
                            <span class="text-xs text-gray-500">หรือ</span>
                            <a href="#" id="link-create-btn" onclick="event.preventDefault(); if(typeof closeModal === 'function'){ closeModal('link-modal'); } if(typeof showAddModal === 'function'){ showAddModal(this.dataset.initialName, this.dataset.initialQty, this.dataset.linkPoItemId); } else { console.error('showAddModal not found'); }" class="text-xs text-indigo-600 hover:text-indigo-500 font-bold ml-1">
                                + สร้างอุปกรณ์ใหม่ (New Equipment)
                            </a>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3">
                        <button type="submit" id="confirm-link-btn" disabled
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-2 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            ยืนยันการเชื่อมโยง
                        </button>
                        <button type="button" onclick="hideLinkModal()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                            ยกเลิก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
