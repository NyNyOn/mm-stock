{{-- 
    File: resources/views/partials/modals/equipment-details.blade.php 
    Status: COMPLETE - Button 'แก้ไขข้อมูล' is now controlled by JavaScript (equipment.js) 
    based on Frozen status and User's Bypass permission.
--}}

{{-- ========================================== --}}
{{-- 🟢 PART 1: MAIN MODAL (หน้ารายละเอียด) --}}
{{-- ========================================== --}}
<div id="equipment-details-modal" class="hidden fixed inset-0 z-[150] items-center justify-center" role="dialog" aria-modal="true">
    
    {{-- Backdrop: ใช้ฟังก์ชัน forceCloseDetails() เพื่อความชัวร์ --}}
    <div class="absolute inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity opacity-100" onclick="forceCloseDetails()"></div>

    {{-- Modal Content --}}
    <div class="relative w-full max-w-5xl max-h-[90vh] bg-white rounded-3xl shadow-2xl flex flex-col overflow-hidden transform transition-all scale-100 mx-4 dark:bg-gray-800 animate-slide-up-soft border border-gray-200 dark:border-gray-700">

        {{-- Header --}}
        <div class="flex items-center justify-between px-8 py-5 bg-gradient-to-r from-indigo-600 via-purple-600 to-violet-600 text-white flex-shrink-0 z-10 shadow-lg">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-md border border-white/10 shadow-inner">
                    <i class="fas fa-cube text-2xl text-white drop-shadow-md"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-extrabold tracking-wide leading-none text-white drop-shadow-sm">รายละเอียดอุปกรณ์</h3>
                    <p class="text-xs text-indigo-100 font-light mt-1 opacity-90 tracking-wider uppercase">Equipment Details & Information</p>
                </div>
            </div>
            {{-- Close Button --}}
            <button onclick="forceCloseDetails()" class="group bg-white/10 hover:bg-white/20 p-2.5 rounded-full transition-all duration-200 focus:outline-none border border-transparent hover:border-white/30">
                <i class="fas fa-times text-lg text-white/80 group-hover:text-white group-hover:rotate-90 transition-transform duration-300"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-grow overflow-y-auto custom-scrollbar bg-gray-50/80 dark:bg-gray-900">
            
            {{-- Loading --}}
            <div id="details-loading" class="flex flex-col items-center justify-center py-24">
                <div class="relative">
                    <div class="w-20 h-20 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin"></div>
                    <div class="absolute top-0 left-0 w-20 h-20 border-4 border-transparent border-b-purple-500 rounded-full animate-spin-reverse"></div>
                </div>
                <p class="text-indigo-500 dark:text-indigo-400 mt-6 font-bold animate-pulse tracking-wide">กำลังโหลดข้อมูล...</p>
            </div>
            
            {{-- Error --}}
            <div id="details-error-message" class="hidden flex flex-col items-center justify-center py-20 text-center">
                <div class="w-24 h-24 bg-red-50 rounded-full flex items-center justify-center mb-6 shadow-inner">
                    <i class="fas fa-heart-broken text-5xl text-red-400"></i>
                </div>
                <h4 class="text-xl font-bold text-gray-800 dark:text-gray-200">ขออภัย ไม่สามารถโหลดข้อมูลได้</h4>
                <p class="text-gray-500 dark:text-gray-400 mt-2">กรุณาลองใหม่อีกครั้ง หรือติดต่อผู้ดูแลระบบ</p>
            </div>

            {{-- Content --}}
            <div id="details-body" class="hidden h-full">
                <div class="flex flex-col md:flex-row h-full">
                    
                    {{-- Left Column: Images --}}
                    <div class="w-full md:w-5/12 bg-white dark:bg-gray-800 p-6 border-b md:border-b-0 md:border-r border-gray-100 dark:border-gray-700 flex flex-col shadow-sm z-10 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-bl-full -z-0 opacity-50 pointer-events-none"></div>

                        {{-- Main Image (Trigger Lightbox) --}}
                        <div class="relative w-full aspect-[4/3] bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden group shadow-md mb-4 z-10 cursor-pointer hover:shadow-xl transition-all duration-300"
                             onclick="openLocalLightbox()">
                            
                            <div class="absolute top-3 left-3 bg-black/70 backdrop-blur-md text-white text-xs font-mono font-bold px-3 py-1 rounded-lg shadow-lg border border-white/10 z-20">
                                #<span id="img-badge-id">...</span>
                            </div>

                            <img id="details-primary-image" src="" alt="Equipment" class="w-full h-full object-contain p-4 transition-transform duration-500 group-hover:scale-105">
                            
                            {{-- Hover Hint --}}
                            <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                <span class="px-4 py-2 bg-white/20 backdrop-blur-md border border-white/40 rounded-full text-white text-sm font-bold shadow-xl transform translate-y-4 group-hover:translate-y-0 transition-transform duration-300 flex items-center gap-2">
                                    <i class="fas fa-search-plus"></i> ขยายรูปภาพ
                                </span>
                            </div>
                        </div>

                        {{-- Thumbnails --}}
                        <div id="details-gallery-thumbnails" class="grid grid-cols-5 gap-2 w-full mb-auto z-10"></div>

                        {{-- Status --}}
                        <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-700 w-full text-center z-10">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">CURRENT STATUS</p>
                            <div id="details-status-container" class="flex justify-center transform scale-110"></div>
                        </div>
                    </div>
                    
                    {{-- Right Column: Details --}}
                    <div class="w-full md:w-7/12 flex flex-col h-full bg-gray-50/30 dark:bg-gray-900/50">
                        <div class="px-8 py-6 bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 shadow-sm">
                            <h2 id="details-name" class="text-2xl font-bold text-gray-800 dark:text-gray-100 leading-tight mb-3">...</h2>
                            <div class="flex flex-wrap gap-3">
                                <div class="flex items-center gap-2 pl-2 pr-3 py-1 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-700">
                                    <div class="w-6 h-6 rounded bg-white flex items-center justify-center shadow-sm text-indigo-600 text-xs"><i class="fas fa-barcode"></i></div>
                                    <div class="flex flex-col leading-none"><span class="text-[9px] font-bold opacity-60 uppercase">Serial Number</span><span id="details-serial" class="font-mono font-bold text-sm">...</span></div>
                                </div>
                                <div class="flex items-center gap-2 pl-2 pr-3 py-1 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700">
                                    <div class="w-6 h-6 rounded bg-white flex items-center justify-center shadow-sm text-emerald-600 text-xs"><i class="fas fa-cubes"></i></div>
                                    <div class="flex flex-col leading-none"><span class="text-[9px] font-bold opacity-60 uppercase">Quantity</span><span id="details-quantity" class="font-bold text-sm">...</span></div>
                                </div>
                            </div>
                        </div>

                        {{-- Tabs --}}
                        <div class="px-8 mt-5 mb-2">
                            <div class="flex p-1.5 space-x-2 bg-gray-200/60 dark:bg-gray-700 rounded-xl border border-gray-200 dark:border-gray-600">
                                <button onclick="switchDetailsTab(this, 'details-tab-main')" class="details-tab-btn flex-1 py-2 px-4 text-sm font-bold rounded-lg shadow-sm bg-white dark:bg-gray-600 text-indigo-600 dark:text-white transition-all duration-200 ring-1 ring-black/5 flex items-center justify-center gap-2" aria-current="page"><i class="fas fa-info-circle"></i> ข้อมูลทั่วไป</button>
                                <button onclick="switchDetailsTab(this, 'details-tab-history')" class="details-tab-btn flex-1 py-2 px-4 text-sm font-medium rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-white/60 transition-all duration-200 flex items-center justify-center gap-2"><i class="fas fa-history"></i> ประวัติ</button>
                                <button id="details-msds-tab" onclick="switchDetailsTab(this, 'details-tab-msds')" class="details-tab-btn hidden flex-1 py-2 px-4 text-sm font-medium rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-white/60 transition-all duration-200 flex items-center justify-center gap-2"><i class="fas fa-shield-alt"></i> MSDS</button>
                            </div>
                        </div>

                        {{-- Tab Content --}}
                        <div class="flex-grow px-8 py-4 overflow-y-auto custom-scrollbar">
                            <div id="details-tab-main" class="details-tab-panel animate-fade-in space-y-6">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 flex items-center mb-4"><span class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center mr-3 shadow-sm"><i class="fas fa-sliders-h"></i></span> ข้อมูลจำเพาะ (Specifications)</h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-start gap-3">
                                            <div class="mt-0.5 text-indigo-500 bg-indigo-50 w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-tag text-xs"></i></div>
                                            <div><p class="text-[10px] text-gray-400 uppercase font-bold">ประเภทการเบิก</p><p id="details-withdrawal-type" class="text-sm font-bold text-gray-700 dark:text-gray-200">...</p></div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-start gap-3">
                                            <div class="mt-0.5 text-pink-500 bg-pink-50 w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-layer-group text-xs"></i></div>
                                            <div><p class="text-[10px] text-gray-400 uppercase font-bold">หมวดหมู่</p><p id="details-category" class="text-sm font-bold text-gray-700 dark:text-gray-200">...</p></div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-start gap-3 col-span-2 sm:col-span-1">
                                            <div class="mt-0.5 text-orange-500 bg-orange-50 w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-map-marker-alt text-xs"></i></div>
                                            <div><p class="text-[10px] text-gray-400 uppercase font-bold">สถานที่จัดเก็บ</p><p id="details-location" class="text-sm font-bold text-gray-700 dark:text-gray-200">...</p></div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-start gap-3 col-span-2 sm:col-span-1">
                                            <div class="mt-0.5 text-cyan-500 bg-cyan-50 w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-truck text-xs"></i></div>
                                            <div class="overflow-hidden"><p class="text-[10px] text-gray-400 uppercase font-bold">Supplier</p><p id="details-supplier" class="text-sm font-bold text-gray-700 dark:text-gray-200 truncate">...</p></div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-start gap-3">
                                            <div class="mt-0.5 text-gray-500 bg-gray-100 w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-cogs text-xs"></i></div>
                                            <div><p class="text-[10px] text-gray-400 uppercase font-bold">Model</p><p id="details-model" class="text-sm font-bold text-gray-700 dark:text-gray-200">...</p></div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-start gap-3">
                                            <div class="mt-0.5 text-gray-500 bg-gray-100 w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-barcode text-xs"></i></div>
                                            <div><p class="text-[10px] text-gray-400 uppercase font-bold">Part No.</p><p id="details-part-no" class="text-sm font-bold text-gray-700 dark:text-gray-200 font-mono">...</p></div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 flex items-center mb-3"><span class="w-8 h-8 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center mr-3 shadow-sm"><i class="fas fa-chart-pie"></i></span> การกำหนดสต็อก (Inventory Limits)</h4>
                                    <div class="flex gap-4">
                                        <div class="flex-1 bg-gradient-to-br from-red-50 to-white border border-red-100 rounded-xl p-3 flex items-center gap-3 shadow-sm hover:shadow-md transition-shadow">
                                            <div class="w-12 h-12 rounded-full bg-red-100 text-red-500 flex items-center justify-center shadow-inner"><i class="fas fa-arrow-down text-lg"></i></div>
                                            <div><p class="text-[10px] font-bold text-red-400 uppercase tracking-wide">Min Stock</p><p id="details-min-stock" class="text-2xl font-black text-red-600 leading-none">...</p></div>
                                        </div>
                                        <div class="flex-1 bg-gradient-to-br from-green-50 to-white border border-green-100 rounded-xl p-3 flex items-center gap-3 shadow-sm hover:shadow-md transition-shadow">
                                            <div class="w-12 h-12 rounded-full bg-green-100 text-green-500 flex items-center justify-center shadow-inner"><i class="fas fa-arrow-up text-lg"></i></div>
                                            <div><p class="text-[10px] font-bold text-green-500 uppercase tracking-wide">Max Stock</p><p id="details-max-stock" class="text-2xl font-black text-green-600 leading-none">...</p></div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 flex items-center mb-3"><span class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center mr-3 shadow-sm"><i class="far fa-calendar-alt"></i></span> ไทม์ไลน์ (Timeline)</h4>
                                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-xl p-4 shadow-sm">
                                        <div class="flex justify-between items-center text-center divide-x divide-gray-100">
                                            <div class="flex-1 px-2"><p class="text-[10px] text-gray-400 mb-1 font-bold">วันที่ซื้อ</p><p id="details-purchase-date" class="text-xs font-bold text-gray-700 dark:text-gray-300">...</p></div>
                                            <div class="flex-1 px-2"><p class="text-[10px] text-gray-400 mb-1 font-bold">ประกันหมด</p><p id="details-warranty-date" class="text-xs font-bold text-gray-700 dark:text-gray-300">...</p></div>
                                            <div class="flex-1 px-2"><p class="text-[10px] text-gray-400 mb-1 font-bold">นำเข้าเมื่อ</p><p id="details-created-at" class="text-xs font-bold text-gray-700 dark:text-gray-300">...</p></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="details-tab-history" class="details-tab-panel hidden animate-fade-in">
                                <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">รายการเคลื่อนไหวล่าสุด</h4>
                                <div id="details-transactions" class="space-y-3"></div>
                            </div>
                            <div id="details-tab-msds" class="details-tab-panel hidden animate-fade-in">
                                <div class="bg-amber-50 border border-amber-100 rounded-xl p-6 shadow-sm relative overflow-hidden">
                                    <i class="fas fa-exclamation-triangle absolute -top-4 -right-4 text-9xl text-amber-100/50 rotate-12 pointer-events-none"></i>
                                    <h4 class="flex items-center text-amber-800 font-bold mb-4 text-lg relative z-10"><span class="bg-amber-200 text-amber-700 w-8 h-8 rounded-full flex items-center justify-center mr-3 shadow-sm"><i class="fas fa-file-medical-alt"></i></span> ข้อมูลความปลอดภัย (Safety Data)</h4>
                                    <div class="bg-white/80 p-4 rounded-xl border border-amber-100/50 text-sm text-gray-700 leading-relaxed mb-6 shadow-sm min-h-[100px] relative z-10 backdrop-blur-sm"><p id="details-msds-details" class="whitespace-pre-wrap">...</p></div>
                                    <div class="text-center relative z-10"><a href="#" id="details-msds-file" target="_blank" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-xl text-sm font-bold shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-200 group"><i class="fas fa-file-pdf mr-2 group-hover:animate-bounce"></i> ดาวน์โหลดเอกสาร MSDS</a></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div id="details-footer" class="px-8 py-5 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex items-center justify-between flex-shrink-0 z-10">
            <div class="flex items-center gap-3 text-gray-400">
                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500"><i class="fas fa-fingerprint"></i></div>
                <div class="flex flex-col"><span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">System ID</span><span id="footer-equipment-id" class="font-mono text-sm text-gray-600 dark:text-gray-300 font-bold">...</span></div>
            </div>
            <div class="flex gap-3">
                <button id="details-print-btn" type="button" class="group flex items-center px-5 py-2.5 bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-700 shadow-sm hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-all"><i class="fas fa-qrcode mr-2 text-gray-400 group-hover:text-indigo-500 transition-colors"></i> Print QR</button>
                @can('equipment:manage')
                <button 
                    id="details-edit-btn" 
                    type="button" 
                    style="display: none;" {{-- ซ่อนปุ่มโดยค่าเริ่มต้น, JS (equipment.js) จะควบคุมการแสดงผลตามสิทธิ์และสถานะ Frozen --}}
                    class="flex items-center px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 border border-transparent rounded-xl text-sm font-bold text-white shadow-md hover:shadow-lg hover:scale-105 hover:from-indigo-500 hover:to-purple-500 transition-all duration-200"
                >
                    <i class="fas fa-edit mr-2"></i> แก้ไขข้อมูล
                </button>
                @endcan
            </div>
        </div>
    </div>
</div>

{{-- ========================================== --}}
{{-- 🟢 PART 2: LIGHTBOX MODAL (แยกอิสระ ปลอดภัย 100%) --}}
{{-- ========================================== --}}
<div id="local-lightbox-modal" 
     class="hidden fixed inset-0 z-[9999] bg-black/95 backdrop-blur-md items-center justify-center transition-all duration-300 opacity-0" 
     onclick="if(event.target === this) closeLocalLightbox()"
     style="display: none;">
    
    {{-- Close Button --}}
    <button onclick="closeLocalLightbox()" class="absolute top-6 right-6 w-12 h-12 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 hover:scale-110 transition-all focus:outline-none z-50 shadow-lg border border-white/10 cursor-pointer"><i class="fas fa-times text-2xl"></i></button>
    
    {{-- Controls --}}
    <button id="lb-prev-btn" onclick="changeLbImage(-1)" class="absolute left-4 md:left-10 text-white/50 hover:text-white p-4 focus:outline-none transition-all hover:scale-110 z-50 hidden cursor-pointer"><div class="w-14 h-14 flex items-center justify-center rounded-full bg-black/50 hover:bg-indigo-600/80 backdrop-blur-sm border border-white/10 shadow-xl transition-colors"><i class="fas fa-chevron-left text-3xl pr-1"></i></div></button>
    <button id="lb-next-btn" onclick="changeLbImage(1)" class="absolute right-4 md:right-10 text-white/50 hover:text-white p-4 focus:outline-none transition-all hover:scale-110 z-50 hidden cursor-pointer"><div class="w-14 h-14 flex items-center justify-center rounded-full bg-black/50 hover:bg-indigo-600/80 backdrop-blur-sm border border-white/10 shadow-xl transition-colors"><i class="fas fa-chevron-right text-3xl pl-1"></i></div></button>

    {{-- Image --}}
    <div class="relative w-full h-full flex flex-col items-center justify-center p-4 md:p-10 pointer-events-none">
        <img id="local-lightbox-image" src="" alt="Fullscreen" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl transform scale-95 transition-transform duration-300 select-none pointer-events-auto">
        <div class="absolute bottom-8 left-0 right-0 text-center flex flex-col items-center gap-2 pointer-events-auto">
             <div id="lb-counter" class="text-white/60 font-mono text-sm tracking-widest hidden">1 / 5</div>
             <span class="inline-block bg-black/60 backdrop-blur-md px-5 py-2 rounded-full text-white/90 text-sm font-medium border border-white/10 shadow-lg"><i class="fas fa-image mr-2 text-indigo-400"></i> รูปภาพขยาย</span>
        </div>
    </div>
</div>

{{-- ========================================== --}}
{{-- 🟢 PART 3: SCRIPTS (แยกการทำงานกันอย่างชัดเจน) --}}
{{-- *ต้องผูกกับฟังก์ชัน controlDetailModalEditButton ใน equipment.js* --}}
{{-- ========================================== --}}
<script>
    // 1️⃣ ฟังก์ชันบังคับปิด Main Modal (แก้หน้าค้าง)
    function forceCloseDetails() {
        const modal = document.getElementById('equipment-details-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // 🔥 สำคัญ: คืนค่า Scrollbar ให้หน้าเว็บ
            document.body.style.overflow = ''; 
            document.body.classList.remove('overflow-y-hidden');
        }
    }

    // 2️⃣ ตัวแปรสำหรับ Lightbox
    let lbCurrentIndex = 0;
    let lbImages = [];

    // 3️⃣ ฟังก์ชันเปิด Lightbox (ปลอดภัย ไม่ยุ่งกับ Scrollbar ของ Body โดยตรง)
    function openLocalLightbox() {
        const primaryImg = document.getElementById('details-primary-image');
        const lightboxModal = document.getElementById('local-lightbox-modal');
        const lightboxImg = document.getElementById('local-lightbox-image');
        
        // โหลดรูปภาพ (จากตัวแปร Global หรือ Scraping)
        if (typeof window.currentDetailImages !== 'undefined' && Array.isArray(window.currentDetailImages) && window.currentDetailImages.length > 0) {
            lbImages = window.currentDetailImages;
        } else {
            const thumbImgs = document.querySelectorAll('#details-gallery-thumbnails img');
            if (thumbImgs.length > 0) {
                lbImages = Array.from(thumbImgs).map(img => img.src);
            } else if (primaryImg && primaryImg.src) {
                lbImages = [primaryImg.src];
            } else {
                return; // ไม่มีรูป
            }
        }

        // หา Index รูปปัจจุบัน
        lbCurrentIndex = 0;
        if(primaryImg) {
            const currentSrc = primaryImg.src;
            const foundIndex = lbImages.findIndex(img => img === currentSrc || img.endsWith(currentSrc) || currentSrc.endsWith(img));
            if(foundIndex !== -1) lbCurrentIndex = foundIndex;
        }

        updateLightboxContent();

        if (lightboxModal && lightboxImg) {
            // เปิด Lightbox
            lightboxModal.style.display = 'flex';
            lightboxModal.classList.remove('hidden');
            lightboxModal.style.pointerEvents = 'auto';
            lightboxModal.style.zIndex = '9999'; // อยู่บนสุด
            
            setTimeout(() => {
                lightboxModal.classList.remove('opacity-0');
                lightboxImg.classList.remove('scale-95');
                lightboxImg.classList.add('scale-100');
            }, 10);
        }
    }

    // 4️⃣ เปลี่ยนรูป
    function changeLbImage(direction) {
        lbCurrentIndex += direction;
        if (lbCurrentIndex >= lbImages.length) lbCurrentIndex = 0;
        if (lbCurrentIndex < 0) lbCurrentIndex = lbImages.length - 1;
        updateLightboxContent();
    }

    // 5️⃣ อัปเดต UI Lightbox
    function updateLightboxContent() {
        const lightboxImg = document.getElementById('local-lightbox-image');
        const prevBtn = document.getElementById('lb-prev-btn');
        const nextBtn = document.getElementById('lb-next-btn');
        const counter = document.getElementById('lb-counter');

        if(lightboxImg && lbImages.length > 0) {
            lightboxImg.style.opacity = '0.5';
            setTimeout(() => {
                lightboxImg.src = lbImages[lbCurrentIndex];
                lightboxImg.style.opacity = '1';
            }, 150);
        }

        if (lbImages.length > 1) {
            if(prevBtn) prevBtn.classList.remove('hidden');
            if(nextBtn) nextBtn.classList.remove('hidden');
            if(counter) {
                counter.classList.remove('hidden');
                counter.textContent = `${lbCurrentIndex + 1} / ${lbImages.length}`;
            }
        } else {
            if(prevBtn) prevBtn.classList.add('hidden');
            if(nextBtn) nextBtn.classList.add('hidden');
            if(counter) counter.classList.add('hidden');
        }
    }

    // 6️⃣ ฟังก์ชันปิด Lightbox (Safe Mode)
    function closeLocalLightbox() {
        const lightboxModal = document.getElementById('local-lightbox-modal');
        const lightboxImg = document.getElementById('local-lightbox-image');
        
        if (lightboxModal) {
            lightboxModal.classList.add('opacity-0');
            lightboxModal.style.pointerEvents = 'none'; // ปิดการคลิกทันที
            
            if(lightboxImg) {
                lightboxImg.classList.remove('scale-100');
                lightboxImg.classList.add('scale-95');
            }
            
            setTimeout(() => {
                lightboxModal.classList.add('hidden');
                lightboxModal.style.display = 'none'; // ซ่อนให้มิด
                lightboxModal.style.zIndex = '-1';    // หลบไปหลังสุด
            }, 300);
        }
    }

    // 7️⃣ Keyboard Shortcuts
    document.addEventListener('keydown', function(event) {
        const lightboxModal = document.getElementById('local-lightbox-modal');
        // เช็คว่า Lightbox เปิดอยู่จริงหรือไม่
        if (lightboxModal && window.getComputedStyle(lightboxModal).display !== 'none' && !lightboxModal.classList.contains('opacity-0')) {
            if (event.key === "Escape") closeLocalLightbox();
            if (event.key === "ArrowRight") changeLbImage(1);
            if (event.key === "ArrowLeft") changeLbImage(-1);
        }
    });
</script>

<style>
    @keyframes spin-reverse { to { transform: rotate(-360deg); } }
    .animate-spin-reverse { animation: spin-reverse 1.5s linear infinite; }
    .animate-fade-in { animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards; }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.02); }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(160, 174, 192, 0.5); border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: rgba(129, 140, 248, 0.8); }
</style>