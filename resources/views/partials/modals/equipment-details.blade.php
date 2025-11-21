<div id="equipment-details-modal" class="hidden fixed inset-0 z-[150] items-center justify-center" role="dialog" aria-modal="true">
    
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-md transition-opacity opacity-100" onclick="closeModal('equipment-details-modal')"></div>

    {{-- Modal Content --}}
    <div class="relative w-full max-w-5xl max-h-[90vh] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden transform transition-all scale-100 mx-4 dark:bg-gray-800 animate-slide-up-soft border border-gray-200 dark:border-gray-700">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white flex-shrink-0 z-10 shadow-md">
            <div class="flex items-center gap-4">
                <div class="p-2.5 bg-white/20 rounded-xl backdrop-blur-md shadow-inner">
                    <i class="fas fa-box-open text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold leading-tight tracking-wide">รายละเอียดอุปกรณ์</h3>
                    <p class="text-xs text-indigo-100 mt-0.5 font-light opacity-90">Equipment Details & History</p>
                </div>
            </div>
            <button onclick="closeModal('equipment-details-modal')" class="group bg-white/10 hover:bg-white/20 p-2 rounded-full transition-all duration-200 focus:outline-none">
                <i class="fas fa-times text-lg text-white/80 group-hover:text-white"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-grow overflow-y-auto custom-scrollbar bg-gray-50/50 dark:bg-gray-900">
            
            {{-- Loading --}}
            <div id="details-loading" class="flex flex-col items-center justify-center py-20">
                <div class="relative">
                    <div class="w-16 h-16 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                    <div class="absolute top-0 left-0 w-16 h-16 border-4 border-transparent border-b-purple-500 rounded-full animate-spin-reverse"></div>
                </div>
                <p class="text-gray-500 dark:text-gray-400 mt-5 font-medium animate-pulse">กำลังโหลดข้อมูล...</p>
            </div>
            
            {{-- Error --}}
            <div id="details-error-message" class="hidden flex flex-col items-center justify-center py-20 text-center">
                <div class="w-24 h-24 bg-red-50 rounded-full flex items-center justify-center mb-4 shadow-sm">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-500"></i>
                </div>
                <h4 class="text-xl font-bold text-gray-800 dark:text-gray-200">ไม่สามารถโหลดข้อมูลได้</h4>
                <p class="text-gray-500 dark:text-gray-400 mt-2">กรุณาลองใหม่อีกครั้ง หรือติดต่อเจ้าหน้าที่</p>
            </div>

            {{-- Content --}}
            <div id="details-body" class="hidden h-full">
                <div class="flex flex-col md:flex-row h-full">
                    
                    {{-- Left: Image Gallery (40%) --}}
                    <div class="w-full md:w-5/12 bg-white dark:bg-gray-800 p-6 border-b md:border-b-0 md:border-r border-gray-100 dark:border-gray-700 flex flex-col shadow-sm z-10">
                        
                        {{-- Main Image --}}
                        <div class="relative w-full aspect-[4/3] bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden group shadow-inner mb-4">
                            {{-- ID Badge --}}
                            <div class="absolute top-3 left-3 bg-black/60 backdrop-blur-md text-white text-xs font-mono px-2.5 py-1 rounded-lg shadow-sm z-10 border border-white/10">
                                ID: <span id="img-badge-id">...</span>
                            </div>

                            <img id="details-primary-image" 
                                 src="" 
                                 alt="Equipment" 
                                 class="w-full h-full object-contain p-4 cursor-zoom-in transition-transform duration-500 group-hover:scale-105"
                                 onclick="triggerDetailImageSlider()">
                            
                            <button onclick="triggerDetailImageSlider()" class="absolute bottom-3 right-3 bg-white/90 dark:bg-gray-800/90 hover:bg-white text-gray-700 dark:text-gray-200 text-xs font-bold px-3 py-1.5 rounded-full shadow-sm opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-200 flex items-center gap-2 backdrop-blur-sm">
                                <i class="fas fa-expand-alt"></i> ขยายรูป
                            </button>
                        </div>

                        {{-- Thumbnails --}}
                        <div id="details-gallery-thumbnails" class="grid grid-cols-5 gap-2 w-full mb-auto"></div>

                        {{-- Status Section --}}
                        <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-700 w-full text-center">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">สถานะปัจจุบัน (Status)</p>
                            <div id="details-status-container" class="flex justify-center transform scale-110">
                                <span class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider shadow-sm border bg-gray-100 text-gray-400">Loading...</span>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Right: Details (60%) --}}
                    <div class="w-full md:w-7/12 flex flex-col h-full bg-gray-50/30 dark:bg-gray-900/50">
                        
                        {{-- Title --}}
                        <div class="p-6 pb-2 bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 shadow-sm">
                            <h2 id="details-name" class="text-2xl font-bold text-gray-800 dark:text-gray-100 leading-tight mb-2">...</h2>
                            <div class="flex flex-wrap gap-2">
                                <div class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded-full border border-gray-200 dark:border-gray-600">
                                    <i class="fas fa-barcode text-gray-400"></i> S/N: <span id="details-serial" class="font-mono text-indigo-600 dark:text-indigo-400">...</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded-full border border-gray-200 dark:border-gray-600">
                                    <i class="fas fa-cube text-gray-400"></i> คงเหลือ: <span id="details-quantity" class="font-bold text-green-600">...</span>
                                </div>
                            </div>
                        </div>

                        {{-- Tabs (Pills) --}}
                        <div class="px-6 mt-5 mb-1">
                            <div class="flex p-1.5 space-x-1 bg-gray-200/50 dark:bg-gray-700 rounded-xl border border-gray-200 dark:border-gray-600">
                                <button onclick="switchDetailsTab(this, 'details-tab-main')" 
                                        class="details-tab-btn flex-1 py-2 px-4 text-sm font-bold rounded-lg shadow-sm bg-white dark:bg-gray-600 text-indigo-600 dark:text-white transition-all duration-200 ring-1 ring-black/5" 
                                        aria-current="page">
                                    <i class="fas fa-list-ul mr-1.5"></i> ข้อมูล
                                </button>
                                <button onclick="switchDetailsTab(this, 'details-tab-history')" 
                                        class="details-tab-btn flex-1 py-2 px-4 text-sm font-medium rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-white/60 transition-all duration-200">
                                    <i class="fas fa-history mr-1.5"></i> ประวัติ
                                </button>
                                <button id="details-msds-tab" onclick="switchDetailsTab(this, 'details-tab-msds')" 
                                        class="details-tab-btn hidden flex-1 py-2 px-4 text-sm font-medium rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-white/60 transition-all duration-200">
                                    <i class="fas fa-shield-alt mr-1.5"></i> MSDS
                                </button>
                            </div>
                        </div>

                        {{-- Content Area --}}
                        <div class="flex-grow p-6 overflow-y-auto custom-scrollbar">
                            
                            {{-- Tab 1: Main Info --}}
                            <div id="details-tab-main" class="details-tab-panel animate-fade-in">
                                
                                {{-- 🛠️ Specifications --}}
                                <div class="mb-6">
                                    <h4 class="text-sm font-bold text-indigo-900 dark:text-indigo-200 flex items-center mb-3">
                                        <span class="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center mr-2 text-indigo-600">
                                            <i class="fas fa-tools text-xs"></i>
                                        </span>
                                        ข้อมูลจำเพาะ
                                    </h4>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm p-4 grid grid-cols-2 gap-y-4 gap-x-6">
                                        
                                        <div class="col-span-2 sm:col-span-1">
                                            <p class="text-[10px] text-gray-400 uppercase font-bold mb-0.5">ประเภทการเบิก</p>
                                            <p id="details-withdrawal-type" class="text-sm font-medium text-gray-800 dark:text-gray-200">...</p>
                                        </div>
                                        <div class="col-span-2 sm:col-span-1">
                                            <p class="text-[10px] text-gray-400 uppercase font-bold mb-0.5">หมวดหมู่</p>
                                            <p id="details-category" class="text-sm font-medium text-gray-800 dark:text-gray-200">...</p>
                                        </div>
                                        <div class="col-span-2 sm:col-span-1">
                                            <p class="text-[10px] text-gray-400 uppercase font-bold mb-0.5">สถานที่จัดเก็บ</p>
                                            <div class="flex items-center gap-1.5 text-sm font-medium text-gray-800 dark:text-gray-200">
                                                <i class="fas fa-map-marker-alt text-red-400"></i> <span id="details-location">...</span>
                                            </div>
                                        </div>
                                        <div class="col-span-2 sm:col-span-1">
                                            <p class="text-[10px] text-gray-400 uppercase font-bold mb-0.5">Part No.</p>
                                            <p id="details-part-no" class="text-sm font-medium text-gray-800 dark:text-gray-200">...</p>
                                        </div>
                                         <div class="col-span-2 sm:col-span-1">
                                            <p class="text-[10px] text-gray-400 uppercase font-bold mb-0.5">Model</p>
                                            <p id="details-model" class="text-sm font-medium text-gray-800 dark:text-gray-200">...</p>
                                        </div>
                                        <div class="col-span-2 sm:col-span-1">
                                            <p class="text-[10px] text-gray-400 uppercase font-bold mb-0.5">Supplier</p>
                                            <p id="details-supplier" class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">...</p>
                                        </div>
                                        
                                        {{-- Stock Limits --}}
                                        <div class="col-span-2 border-t border-dashed border-gray-200 pt-3 mt-1 flex justify-between items-center">
                                            <div>
                                                <p class="text-[10px] text-gray-400 uppercase font-bold">Min Stock</p>
                                                <p id="details-min-stock" class="text-sm font-bold text-gray-600">...</p>
                                            </div>
                                            <div class="h-8 w-px bg-gray-200"></div>
                                            <div class="text-right">
                                                <p class="text-[10px] text-gray-400 uppercase font-bold">Max Stock</p>
                                                <p id="details-max-stock" class="text-sm font-bold text-gray-600">...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- 📅 Timeline --}}
                                <div>
                                    <h4 class="text-sm font-bold text-orange-900 dark:text-orange-200 flex items-center mb-3">
                                        <span class="w-7 h-7 rounded-lg bg-orange-100 dark:bg-orange-900/50 flex items-center justify-center mr-2 text-orange-600">
                                            <i class="fas fa-calendar-alt text-xs"></i>
                                        </span>
                                        ข้อมูลวันที่
                                    </h4>
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm text-center">
                                            <p class="text-[10px] text-gray-400 mb-1">วันที่ซื้อ</p>
                                            <p id="details-purchase-date" class="text-xs font-bold text-gray-700 dark:text-gray-300">...</p>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm text-center">
                                            <p class="text-[10px] text-gray-400 mb-1">ประกันหมด</p>
                                            <p id="details-warranty-date" class="text-xs font-bold text-gray-700 dark:text-gray-300">...</p>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm text-center">
                                            <p class="text-[10px] text-gray-400 mb-1">เพิ่มเมื่อ</p>
                                            <p id="details-created-at" class="text-xs font-bold text-gray-700 dark:text-gray-300">...</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="details-notes" class="hidden"></div>
                            </div>

                            {{-- Tab 2: History --}}
                            <div id="details-tab-history" class="details-tab-panel hidden animate-fade-in">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">รายการล่าสุด</h4>
                                    <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-1 rounded-full font-bold">5 รายการ</span>
                                </div>
                                <div id="details-transactions" class="space-y-3 pr-1">
                                    {{-- JS Injected --}}
                                </div>
                            </div>
                            
                            {{-- Tab 3: MSDS --}}
                            <div id="details-tab-msds" class="details-tab-panel hidden animate-fade-in">
                                <div class="bg-orange-50 border border-orange-100 rounded-xl p-5 shadow-sm">
                                    <h4 class="flex items-center text-orange-800 font-bold mb-3">
                                        <i class="fas fa-file-medical-alt mr-2 text-xl"></i> ข้อมูลความปลอดภัย
                                    </h4>
                                    <div class="bg-white/60 p-3 rounded-lg border border-orange-100/50 mb-4">
                                        <p id="details-msds-details" class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">...</p>
                                    </div>
                                    
                                    <div class="text-center">
                                        <a href="#" id="details-msds-file" target="_blank" class="hidden inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-lg text-sm font-bold shadow-md hover:shadow-lg hover:scale-[1.02] transition-all duration-200">
                                            <i class="fas fa-file-pdf mr-2"></i> ดาวน์โหลดเอกสาร MSDS
                                        </a>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div id="details-footer" class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 flex items-center justify-between flex-shrink-0 z-10">
            <div class="flex items-center gap-2 text-gray-400">
                <i class="fas fa-fingerprint"></i> 
                <span class="text-xs font-bold uppercase tracking-wider">System ID:</span>
                <span id="footer-equipment-id" class="font-mono text-sm text-gray-600 dark:text-gray-300 font-bold">...</span>
            </div>
            
            <div class="flex gap-3">
                {{-- ปุ่ม QR Code: เพิ่ม Logic การทำงานที่ชัดเจน --}}
                <button id="details-print-btn" type="button" class="group flex items-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50 hover:border-indigo-300 hover:text-indigo-600 transition-all duration-200">
                    <i class="fas fa-qrcode mr-2 text-gray-400 group-hover:text-indigo-500 transition-colors"></i> QR Code
                </button>

                @can('equipment:manage')
                <button id="details-edit-btn" type="button" class="flex items-center px-5 py-2 bg-indigo-600 border border-transparent rounded-lg text-sm font-bold text-white shadow-md hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
                    <i class="fas fa-edit mr-2"></i> แก้ไข
                </button>
                @endcan
            </div>
        </div>

    </div>
</div>

<script>
    if (typeof window.initDetailsModal === 'undefined') {
        window.initDetailsModal = true;
        let currentDetailImages = [];
        let currentDetailName = '';

        // ... (Slider & Tab Switcher logic เหมือนเดิม) ...
        window.triggerDetailImageSlider = function() {
            if (typeof openImageSlider === 'function') { openImageSlider(currentDetailImages, currentDetailName); } 
            else { const primaryImg = document.getElementById('details-primary-image'); if (primaryImg) window.open(primaryImg.src, '_blank'); }
        }

        function switchDetailsTab(selectedBtn, targetPanelId) {
            document.querySelectorAll('.details-tab-btn').forEach(btn => {
                btn.className = 'details-tab-btn flex-1 py-2 px-4 text-sm font-medium rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-white/60 transition-all duration-200';
                btn.removeAttribute('aria-current');
            });
            document.querySelectorAll('.details-tab-panel').forEach(panel => panel.classList.add('hidden'));

            selectedBtn.className = 'details-tab-btn flex-1 py-2 px-4 text-sm font-bold rounded-lg shadow-sm bg-white dark:bg-gray-600 text-indigo-600 dark:text-white transition-all duration-200 ring-1 ring-black/5';
            selectedBtn.setAttribute('aria-current', 'page');

            const targetPanel = document.getElementById(targetPanelId);
            if (targetPanel) targetPanel.classList.remove('hidden');
        }

        window.updatePrimaryImage = function(url) {
            const primaryImageDisplay = document.getElementById('details-primary-image');
            if (primaryImageDisplay) { primaryImageDisplay.style.opacity = '0.5'; setTimeout(() => { primaryImageDisplay.src = url; primaryImageDisplay.style.opacity = '1'; }, 150); }
        }

        // ✅ Helper: Status Badge
        function createInternalStatusBadge(status) {
            const badge = document.createElement('span');
            badge.className = 'px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider shadow-sm border';
            if (!status) status = 'unknown';
            switch (status) {
                case 'available': badge.classList.add('bg-green-50', 'text-green-700', 'border-green-200'); badge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> พร้อมใช้งาน'; break;
                case 'low_stock': badge.classList.add('bg-yellow-50', 'text-yellow-700', 'border-yellow-200'); badge.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> ใกล้หมด'; break;
                case 'out_of_stock': badge.classList.add('bg-red-50', 'text-red-700', 'border-red-200'); badge.innerHTML = '<i class="fas fa-times-circle mr-1"></i> สินค้าหมด'; break;
                case 'maintenance': badge.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-200'); badge.innerHTML = '<i class="fas fa-tools mr-1"></i> ซ่อมบำรุง'; break;
                default: badge.classList.add('bg-gray-100', 'text-gray-600', 'border-gray-200'); badge.textContent = status;
            }
            return badge;
        }

        // ✅ MAIN FUNCTION
        window.populateDetailsModal = function(data) {
            // ✅ FIX: Handle Data Wrapping (แก้ ID/Status ไม่ขึ้น)
            const item = data.data || data;
            
            console.log("[DEBUG_DETAILS] Item Data:", item);
            
            const setText = (id, value) => { 
                try {
                    const el = document.getElementById(id); 
                    if (el) el.textContent = (value === null || value === undefined || value === '') ? '-' : value; 
                } catch(e) { console.warn(`Error setting text for ${id}`, e); }
            };
            
            currentDetailName = item.name;
            currentDetailImages = [];

            // 1. IDs & Header
            setText('footer-equipment-id', item.id);
            setText('img-badge-id', item.id); // Update Image Badge
            setText('details-name', item.name);
            setText('details-quantity', `${item.quantity ?? 0} ${item.unit?.name || 'ชิ้น'}`);
            setText('details-serial', item.serial_number);
            
            // 2. Specs
            setText('details-min-stock', item.min_stock);
            setText('details-max-stock', item.max_stock);
            setText('details-withdrawal-type', window.getWithdrawalTypeText ? window.getWithdrawalTypeText(item.withdrawal_type) : item.withdrawal_type);
            setText('details-category', item.category?.name);
            setText('details-location', item.location?.name);
            setText('details-model', item.model);
            setText('details-part-no', item.part_no);
            setText('details-supplier', item.supplier);
            
            // 3. Dates
            setText('details-purchase-date', window.formatDate ? window.formatDate(item.purchase_date) : item.purchase_date);
            setText('details-warranty-date', window.formatDate ? window.formatDate(item.warranty_date) : item.warranty_date);
            setText('details-created-at', window.formatDateTime ? window.formatDateTime(item.created_at) : item.created_at);

            // ✅ 4. Status Badge (รับประกันว่าขึ้นแน่นอน)
            try {
                const statusEl = document.getElementById('details-status-container');
                if (statusEl) { 
                    statusEl.innerHTML = ''; 
                    statusEl.appendChild(createInternalStatusBadge(item.status)); 
                }
            } catch(e) { console.error("Status badge error", e); }

            // ... (MSDS, History, Images Logic - เหมือนเดิม) ...
            // ... (ข้ามส่วนที่ยาวๆ เพื่อความกระชับ แต่ในไฟล์จริงต้องมีครบนะครับ) ...
             // MSDS
            const msdsTab = document.getElementById('details-msds-tab');
            const msdsDetailsEl = document.getElementById('details-msds-details');
            const msdsLinkEl = document.getElementById('details-msds-file');
            if (item.has_msds) {
                if(msdsTab) msdsTab.classList.remove('hidden');
                if(msdsDetailsEl) msdsDetailsEl.textContent = item.msds_details || 'ไม่มีรายละเอียดเพิ่มเติม';
                if (msdsLinkEl && item.msds_file_url) {
                    msdsLinkEl.href = item.msds_file_url;
                    msdsLinkEl.classList.remove('hidden');
                    msdsLinkEl.classList.add('inline-flex');
                }
            } else {
                if(msdsTab) msdsTab.classList.add('hidden');
            }
            const firstTabBtn = document.querySelector('.details-tab-btn');
            if(firstTabBtn) switchDetailsTab(firstTabBtn, 'details-tab-main');

            // History
            const transactionContainer = document.getElementById('details-transactions');
            if (transactionContainer) {
                transactionContainer.innerHTML = '';
                if (item.transactions && item.transactions.length > 0) {
                    item.transactions.forEach(t => {
                        const isPlus = t.quantity_change >= 0;
                        const div = document.createElement('div');
                        div.className = `p-3 rounded-lg border ${isPlus ? 'bg-green-50 border-green-100' : 'bg-red-50 border-red-100'} shadow-sm flex items-start gap-3 transition-transform hover:scale-[1.01]`;
                        div.innerHTML = `
                            <div class="mt-1 bg-white rounded-full p-1.5 shadow-sm border border-gray-100">
                                <i class="fas ${isPlus ? 'fa-arrow-down text-green-500' : 'fa-arrow-up text-red-500'} text-xs"></i>
                            </div>
                            <div class="flex-grow">
                                <div class="flex justify-between items-start">
                                    <span class="font-bold text-gray-700 text-sm">
                                        ${window.getTransactionTypeText ? window.getTransactionTypeText(t.type) : t.type}
                                    </span>
                                    <span class="text-xs text-gray-400 font-mono">${window.formatDateTime ? window.formatDateTime(t.transaction_date) : t.transaction_date}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                                    <span><i class="fas fa-user text-gray-400"></i> ${t.user?.fullname || 'System'}</span>
                                    <span class="font-mono font-bold ${isPlus ? 'text-green-600' : 'text-red-600'}">
                                        (${isPlus ? '+' : ''}${t.quantity_change})
                                    </span>
                                </div>
                            </div>
                        `;
                        transactionContainer.appendChild(div);
                    });
                } else {
                    transactionContainer.innerHTML = '<div class="py-8 text-center text-gray-400"><i class="fas fa-history text-3xl mb-2 opacity-30"></i><span class="text-sm">ยังไม่มีประวัติการใช้งาน</span></div>';
                }
            }

            // Images
            const primaryImageDisplay = document.getElementById('details-primary-image');
            const thumbnailContainer = document.getElementById('details-gallery-thumbnails');
            thumbnailContainer.innerHTML = '';
            let finalPrimaryUrl = 'https://placehold.co/600x400/e2e8f0/64748b?text=No+Image';

            if (item.images_list && item.images_list.length > 0) {
                currentDetailImages = item.images_list;
                finalPrimaryUrl = currentDetailImages[0];
            } else if (item.image_urls && item.image_urls.length > 0) {
                currentDetailImages = item.image_urls;
                finalPrimaryUrl = item.primary_image_url || item.image_urls[0];
            } else if (item.image_url) {
                currentDetailImages = [item.image_url];
                finalPrimaryUrl = item.image_url;
            } else {
                currentDetailImages = [finalPrimaryUrl];
            }
            primaryImageDisplay.src = finalPrimaryUrl;

            if (currentDetailImages.length > 1) {
                currentDetailImages.forEach((url, index) => {
                    const container = document.createElement('div');
                    container.className = 'relative aspect-square cursor-pointer group';
                    const imgThumb = document.createElement('img');
                    imgThumb.src = url;
                    imgThumb.className = 'w-full h-full object-cover rounded-lg border-2 border-transparent group-hover:border-indigo-500 transition-all duration-200 shadow-sm';
                    container.onclick = () => {
                        window.updatePrimaryImage(url);
                        thumbnailContainer.querySelectorAll('img').forEach(i => i.classList.remove('border-indigo-500', 'ring-2', 'ring-indigo-200'));
                        imgThumb.classList.add('border-indigo-500', 'ring-2', 'ring-indigo-200');
                    };
                    container.appendChild(imgThumb);
                    thumbnailContainer.appendChild(container);
                });
            }

            // Buttons (Edit)
            const editBtn = document.getElementById('details-edit-btn');
            if(editBtn) {
                const newEdit = editBtn.cloneNode(true);
                editBtn.parentNode.replaceChild(newEdit, editBtn);
                newEdit.setAttribute('data-equipment-id', item.id);
                newEdit.addEventListener('click', () => {
                    closeModal('equipment-details-modal');
                    if (typeof showEditModal === 'function') showEditModal(item.id);
                });
            }

            // ✅ FIX: QR Code Button Logic (แก้ปัญหาถูกบัง)
            const printBtn = document.getElementById('details-print-btn');
            if(printBtn) {
                const newPrint = printBtn.cloneNode(true);
                printBtn.parentNode.replaceChild(newPrint, printBtn);
                newPrint.setAttribute('data-equipment-id', item.id);
                
                newPrint.addEventListener('click', () => {
                     const sn = item.serial_number && item.serial_number !== '-' ? item.serial_number : String(item.id);
                     
                     // 1. ปิดหน้าต่างปัจจุบัน
                     closeModal('equipment-details-modal'); 
                     
                     // 2. รอ 200ms ให้ Animation จบ แล้วเปิด QR Code
                     setTimeout(() => {
                        if (typeof openQrCodeModal === 'function') {
                            openQrCodeModal(sn, item.name);
                            // Hack: บังคับ Z-Index ของ QR Modal ให้สูงที่สุด
                            const qrModal = document.getElementById('qr-code-modal');
                            if(qrModal) qrModal.style.zIndex = '9999';
                        } else {
                            console.error("openQrCodeModal not found!");
                        }
                     }, 200);
                });
            }
        }
    }
</script>

<style>
    @keyframes spin-reverse { to { transform: rotate(-360deg); } }
    .animate-spin-reverse { animation: spin-reverse 1s linear infinite; }
    .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
</style>