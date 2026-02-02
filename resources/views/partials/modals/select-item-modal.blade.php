{{-- resources/views/partials/modals/select-item-modal.blade.php --}}
{{-- Visual Catalog Quick Add Modal --}}
<div id="select-item-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-0 md:p-4 modal-backdrop-soft">
    {{-- Main Container: Full Screen on Mobile, Rounded on Desktop --}}
    <div class="w-full max-w-7xl h-full md:h-[90vh] bg-white md:soft-card md:rounded-2xl modal-content-wrapper animate-slide-up-soft flex flex-col overflow-hidden relative">
        
        {{-- Header Section - Premium Design --}}
        <div class="p-4 md:p-6 bg-gradient-to-r from-slate-50 to-blue-50 border-b border-blue-100/50 flex-none z-10">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    {{-- Premium Icon --}}
                    <div class="relative">
                        <span class="bg-gradient-to-br from-blue-500 to-indigo-600 text-white p-3 rounded-xl shadow-lg shadow-blue-200/50 inline-flex items-center justify-center">
                            <i class="fas fa-warehouse text-lg"></i>
                        </span>
                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-green-400 rounded-full border-2 border-white animate-pulse"></span>
                    </div>
                    {{-- Title --}}
                    <div>
                        <h3 class="text-xl md:text-2xl font-extrabold bg-gradient-to-r from-gray-800 via-blue-800 to-indigo-700 bg-clip-text text-transparent tracking-tight">
                            คลังสินค้า
                        </h3>
                        <p class="text-xs text-gray-500 font-medium hidden md:block">เลือกอุปกรณ์เพื่อเพิ่มในรายการ</p>
                    </div>
                    {{-- Count Badge - Premium --}}
                    <span id="catalog-item-count" class="ml-2 px-4 py-1.5 bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-sm font-bold rounded-full shadow-md shadow-emerald-200/50 hidden items-center gap-1.5">
                        <i class="fas fa-cubes text-xs"></i>
                        <span id="catalog-count-number" class="tabular-nums">0</span>
                        <span class="text-emerald-100 text-xs">รายการ</span>
                    </span>
                </div>
                {{-- Close Button --}}
                <button type="button" class="text-gray-400 hover:text-red-500 transition-all p-2 rounded-full hover:bg-red-50 hover:rotate-90 duration-200"
                    onclick="closeQuickAddModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="flex flex-col md:flex-row gap-4">
                {{-- Search Bar --}}
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="catalog-search" placeholder="ค้นหาชื่อ, รหัส..." 
                        class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-100 focus:border-blue-300 transition-all text-gray-700 font-medium text-sm md:text-base"
                        oninput="debounceSearch()">
                    <button id="clear-search-btn" class="hidden absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600" onclick="clearCatalogSearch()">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>

                {{-- Category Filter Dropdown --}}
                <div class="relative flex-shrink-0 min-w-[200px]">
                    <i class="fas fa-layer-group absolute left-3 top-1/2 transform -translate-y-1/2 text-blue-500 pointer-events-none z-10"></i>
                    <select id="catalog-category-select" onchange="filterCatalogCategory(this.value)"
                        class="w-full pl-10 pr-10 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-100 focus:border-blue-400 transition-all text-gray-700 font-bold text-sm appearance-none cursor-pointer shadow-sm hover:border-blue-300">
                        <option value="all" selected>📦 ทั้งหมด</option>
                        {{-- Categories injected via JS --}}
                    </select>
                    <i class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                </div>
            </div>
        </div>

        {{-- Content Area: Split View --}}
        <div class="flex flex-1 overflow-hidden bg-gray-50 relative">
            
            {{-- Left: Item Grid (Scrollable) --}}
            <div class="flex-1 overflow-y-auto p-3 md:p-6 scrollbar-soft pb-32 md:pb-6" id="catalog-grid-container">
                <div id="catalog-grid" class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-2 md:gap-4">
                    {{-- Items injected via JS --}}
                </div>
                
                {{-- Loading State --}}
                <div id="catalog-loader" class="hidden py-10 text-center">
                    <i class="fas fa-circle-notch fa-spin text-3xl text-blue-500"></i>
                    <p class="mt-2 text-gray-500 text-sm">กำลังโหลดรายการ...</p>
                </div>

                {{-- Empty State --}}
                <div id="catalog-empty" class="hidden flex flex-col items-center justify-center py-20 text-center">
                    <div class="bg-gray-100 p-6 rounded-full mb-4">
                        <i class="fas fa-box-open text-4xl text-gray-400"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-600">ไม่พบสินค้า</h4>
                    <p class="text-gray-500 text-sm">ลองค้นหาด้วยคำใหม่</p>
                </div>
            </div>

            {{-- Right: Selection Panel (Desktop Only) --}}
            <div class="w-80 bg-white border-l border-gray-100 flex-col shadow-xl z-20 hidden md:flex" id="selection-panel">
                <div class="p-6 flex-1 flex flex-col">
                    <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">รายการที่เลือก</h4>
                    
                    {{-- Selected Item Detail --}}
                    <div id="selected-item-detail" class="flex-1 flex flex-col items-center justify-center text-center">
                        <div class="bg-blue-50 p-6 rounded-full mb-4">
                            <i class="fas fa-mouse-pointer text-3xl text-blue-300"></i>
                        </div>
                        <p class="text-gray-400 text-sm">คลิกเลือกสินค้าจากรายการ<br>เพื่อกำหนดจำนวน</p>
                    </div>

                    {{-- Active Selection Form --}}
                    <div id="active-selection-form" class="hidden w-full">
                        <div class="relative w-full aspect-video rounded-xl overflow-hidden bg-gray-100 mb-4 border border-gray-100">
                            <img id="sel-image" src="" class="w-full h-full object-contain p-2">
                        </div>
                        <h5 id="sel-name" class="font-bold text-gray-800 text-lg leading-tight mb-1"></h5>
                        <p id="sel-serial" class="text-xs text-gray-500 mb-4 font-mono"></p>
                        
                        <div class="flex items-center justify-between mb-4 p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-600">คงเหลือ</span>
                            <span id="sel-stock" class="font-bold text-gray-800"></span>
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">จำนวนที่ต้องการ</label>
                            <div class="flex items-center border border-gray-300 rounded-xl overflow-hidden">
                                <button type="button" onclick="adjustQty(-1)" class="px-4 py-3 bg-gray-50 hover:bg-gray-100 text-gray-600 border-r">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" id="sel-quantity" value="1" min="1" class="flex-1 text-center py-3 border-none focus:ring-0 text-gray-800 font-bold">
                                <button type="button" onclick="adjustQty(1)" class="px-4 py-3 bg-gray-50 hover:bg-gray-100 text-gray-600 border-l">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <button type="button" id="btn-confirm-add" onclick="confirmAddCatalogItem()" 
                            class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-200 transform hover:-translate-y-0.5 transition-all">
                            เพิ่มรายการ
                        </button>
                    </div>
                </div>
            </div>

            {{-- Mobile Selection Sheet (Slide Up Overlay) --}}
            <div id="mobile-selection-sheet" class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.1)] z-50 transform translate-y-full transition-transform duration-300 flex flex-col md:hidden max-h-[85%]">
                
                <!-- Drag Handle & Close Button -->
                <div class="w-full flex items-center justify-center pt-3 pb-1 relative" onclick="closeMobileSheet()">
                    <div class="w-12 h-1.5 bg-gray-300 rounded-full"></div>
                    
                    {{-- Close Button X --}}
                    <button type="button" class="absolute right-4 top-3 text-gray-400 p-2" onclick="setTimeout(closeMobileSheet, 50)">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-5 flex-1 overflow-y-auto">
                     <div class="flex gap-4 mb-4">
                        <div class="w-24 h-24 bg-gray-100 rounded-xl overflow-hidden flex-shrink-0 border border-gray-200">
                             <img id="mob-sel-image" src="" class="w-full h-full object-contain p-1">
                        </div>
                        <div class="flex-1 min-w-0">
                             <h4 id="mob-sel-name" class="font-bold text-gray-800 text-lg leading-tight mb-1 line-clamp-2">Name</h4>
                             <p id="mob-sel-serial" class="text-sm text-gray-500 font-mono mb-2">Serial</p>
                             <div class="inline-flex items-center px-2 py-1 bg-green-50 text-green-700 rounded text-xs font-bold" id="mob-sel-stock">
                                 Stock
                             </div>
                        </div>
                     </div>

                     <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">จำนวน</label>
                        <div class="flex items-center border border-gray-300 rounded-xl overflow-hidden h-12">
                            <button type="button" onclick="adjustQty(-1)" class="px-5 h-full bg-gray-50 hover:bg-gray-100 text-gray-600 border-r text-lg">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" id="mob-sel-quantity" value="1" min="1" class="flex-1 h-full text-center border-none focus:ring-0 text-gray-800 font-bold text-lg">
                            <button type="button" onclick="adjustQty(1)" class="px-5 h-full bg-gray-50 hover:bg-gray-100 text-gray-600 border-l text-lg">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <button type="button" id="mob-btn-confirm-add" onclick="confirmAddCatalogItem()" 
                        class="w-full py-4 bg-blue-600 active:bg-blue-700 text-white font-bold rounded-xl shadow-lg text-lg flex items-center justify-center gap-2">
                        <span>เพิ่มรายการ</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom Scrollbar for Grid */
.scrollbar-soft::-webkit-scrollbar { width: 6px; }
.scrollbar-soft::-webkit-scrollbar-track { background: transparent; }
.scrollbar-soft::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.scrollbar-soft::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* Hide Scrollbar but keep functionality */
.scrollbar-hide {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;  /* Chrome, Safari and Opera */
}

/* Item Card Hover Effect */
.item-card {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    border-color: #93c5fd;
}
.item-card.selected {
    ring: 2px solid #3b82f6;
    border-color: #3b82f6;
    background-color: #eff6ff;
}
</style>