@extends('layouts.app')

@section('header', 'แคตตาล็อกอุปกรณ์')
@section('subtitle', 'เลือกดูและทำรายการเบิก/ยืมอุปกรณ์ที่คุณต้องการ')

@push('styles')
<style>
    .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
    .tab-content.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    .equipment-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
    .equipment-card { transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
    @keyframes pulse-shadow-key { 0% { box-shadow: 0 0 15px 0px rgba(99, 102, 241, 0.0); } 50% { box-shadow: 0 0 15px 8px rgba(99, 102, 241, 0.4); } 100% { box-shadow: 0 0 15px 0px rgba(99, 102, 241, 0.0); } }
    .btn-pulse-shadow { animation: pulse-shadow-key 2.5s infinite ease-in-out; }
    /* Select2 Custom Styles */
    .select2-container--default .select2-selection--single { background-color: #fff; border: 1px solid #d1d5db; border-radius: 0.5rem; height: 42px; padding: 0.5rem 0.75rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
    .dark .select2-container--default .select2-selection--single { background-color: #374151; border-color: #4b5563; color: #e5e7eb; }
    .dark .select2-dropdown { background-color: #374151; border-color: #4b5563; color: #e5e7eb; }
    .dark .select2-search__field { background-color: #4b5563; color: #e5e7eb; }
</style>
@endpush

@section('content')
<div class="space-y-6 page animate-slide-up-soft">

    {{-- Search Bar --}}
    <div class="p-5 soft-card rounded-2xl gentle-shadow mb-6">
        <form id="search-form">
            <label for="live-search-input" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">ค้นหาอุปกรณ์ (Live Search)</label>
            <div class="flex rounded-lg shadow-sm">
                <input type="text" id="live-search-input" name="search" value="{{ request('search') }}" class="flex-1 block w-full px-4 py-3 text-lg text-gray-700 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-lg dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" placeholder="พิมพ์ชื่ออุปกรณ์, S/N, หรือ Part No. ..." autofocus>
                <button type="button" id="scan-qr-button" title="สแกน QR Code" class="inline-flex items-center px-4 py-2 text-gray-500 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg hover:bg-gray-200 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"><i class="fas fa-qrcode fa-lg"></i></button>
            </div>
        </form>
    </div>

    {{-- Loading Spinner --}}
    <div id="loading-spinner" class="text-center my-10" style="display: none;">
        <svg class="animate-spin h-10 w-10 text-indigo-600 dark:text-indigo-400 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">กำลังค้นหาในสต็อกทั้งหมด...</p>
    </div>

    {{-- Results Container (AJAX) --}}
    <div id="search-results-container" class="space-y-8" style="display: none;">
        <div id="my-stock-results"></div>
        <div id="other-stock-results"></div>
    </div>

    {{-- Default Content (Tabs) --}}
    <div id="default-catalog-content">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex flex-wrap -mb-px space-x-1 sm:space-x-4" aria-label="Tabs">
                @php $deptIcons = [ 'it' => 'fa-solid fa-computer', 'en' => 'fa-solid fa-cogs', 'hr' => 'fa-solid fa-users', 'qa' => 'fa-solid fa-flask-vial', 'pd' => 'fa-solid fa-industry', 'mm' => 'fa-solid fa-screwdriver-wrench', 'wh' => 'fa-solid fa-warehouse', 'enmold' => 'fa-solid fa-cube' ]; @endphp
                @foreach ($departments as $key => $dept)
                    <a href="{{ route('user.equipment.index', ['dept' => $key] + request()->except('dept', 'page', 'search')) }}"
                       class="flex items-center px-3 py-3 text-sm font-medium whitespace-nowrap rounded-t-lg transition-colors duration-150 ease-in-out group {{ $currentDeptKey == $key && !request()->filled('search') ? 'border-b-2 border-indigo-500 text-indigo-600 bg-white dark:bg-gray-800 dark:text-indigo-400 tab-active' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600' }}">
                        @if(isset($deptIcons[$key])) <i class="{{ $deptIcons[$key] }} mr-2 opacity-75 group-hover:opacity-100 {{ $currentDeptKey == $key && !request()->filled('search') ? 'text-indigo-500 dark:text-indigo-400' : 'text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300' }}"></i> @endif
                        <span>{{ $dept['name'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

        @if (request()->filled('search') && isset($aggregatedResults))
            {{-- Search Results (PHP Rendered) --}}
            <div class="space-y-6">
                @forelse ($aggregatedResults as $result)
                    <div class="p-5 soft-card rounded-2xl gentle-shadow">
                        <h2 class="mb-4 text-xl font-bold text-gray-800 dark:text-gray-100">{{ $result['dept_name'] }} (พบ {{ count($result['items']) }} รายการ)</h2>
                        @if(count($result['items']) > 0)
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                                @foreach ($result['items'] as $item)
                                    <div class="flex flex-col overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700 equipment-card bg-white dark:bg-gray-800">
                                        <div class="relative flex items-center justify-center w-full h-32 overflow-hidden bg-gray-100 dark:bg-gray-700">
                                            @php 
                                                $imageFileName = $item->primary_image_file_name_manual ?? null;
                                                $imageUrl = $imageFileName ? route('nas.image', ['deptKey' => $result['dept_key'], 'filename' => $imageFileName]) : asset('images/placeholder.webp');
                                            @endphp
                                            <img src="{{ $imageUrl }}" alt="{{ $item->name }}" class="object-contain max-w-full max-h-full" onerror="this.src='{{ asset('images/placeholder.webp') }}'">
                                        </div>
                                        <div class="p-3">
                                            <h3 class="text-sm font-semibold text-gray-800 truncate dark:text-gray-100" title="{{ $item->name }}">{{ $item->name }}</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->serial_number ?? 'N/A' }}</p>
                                            <span class="block mt-1 text-xs font-medium text-blue-600 dark:text-blue-400">คงเหลือ: {{ $item->stock_sum_quantity }} {{ optional($item->unit)->name }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else <p class="text-sm text-center text-gray-500 dark:text-gray-400">ไม่พบอุปกรณ์ที่ตรงกันในแผนกนี้</p> @endif
                    </div>
                @empty <div class="p-8 text-center text-gray-500 dark:text-gray-400 soft-card rounded-2xl gentle-shadow"><p>ไม่พบผลลัพธ์</p></div> @endforelse
            </div>
        @elseif ($equipments)
            {{-- Normal List --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                @forelse ($equipments as $item)
                    <div class="flex flex-col overflow-hidden soft-card rounded-2xl gentle-shadow equipment-card bg-white dark:bg-gray-800">
                        <div class="relative flex items-center justify-center w-full h-48 overflow-hidden bg-gray-100 rounded-t-2xl dark:bg-gray-700">
                            @php 
                                $imageFileName = $item->primary_image_file_name_manual ?? null;
                                $imageUrl = $imageFileName ? route('nas.image', ['deptKey' => $currentDeptKey, 'filename' => $imageFileName]) : asset('images/placeholder.webp');
                            @endphp
                            <img src="{{ $imageUrl }}" alt="{{ $item->name }}" class="object-contain max-w-full max-h-full" onerror="this.src='{{ asset('images/placeholder.webp') }}'">
                        </div>
                        <div class="flex flex-col flex-grow p-4">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100" title="{{ $item->name }}">{{ Str::limit($item->name, 40) }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item->serial_number ?? 'N/A' }}</p>
                            
                            {{-- ⭐ PHP: แสดงดาวเหลือง 5 ดวง + จำนวนรีวิว ⭐ --}}
                            @php
                                $avgRating = $item->ratings_avg_rating ?? $item->ratings->avg('rating') ?? 0;
                                $ratingCount = $item->ratings->count() ?? 0;
                            @endphp
                            <div class="flex items-center mt-2 space-x-0.5" title="คะแนนเฉลี่ย: {{ number_format($avgRating, 1) }}">
                                @for ($i = 1; $i <= 5; $i++)
                                    @if ($i <= $avgRating)
                                        <i class="fas fa-star text-yellow-400 text-xs"></i>
                                    @elseif ($i - 0.5 <= $avgRating)
                                        <i class="fas fa-star-half-alt text-yellow-400 text-xs"></i>
                                    @else
                                        <i class="fas fa-star text-gray-300 dark:text-gray-600 text-xs"></i>
                                    @endif
                                @endfor
                                <span class="text-xs text-gray-400 ml-1">({{ $ratingCount }} รีวิว)</span>
                            </div>

                            <div class="flex-grow mt-2"><span class="text-sm font-semibold text-blue-600 dark:text-blue-400">คงเหลือ: {{ $item->quantity }} {{ optional($item->unit)->name }}</span></div>
                            <div class="pt-4 mt-auto space-y-2">
                                @php
                                    $btnStates = [ 
                                        'consumable' => [ 'text' => 'เบิก (ไม่ต้องคืน)', 'icon' => 'fas fa-box-open', 'class' => 'bg-orange-500 hover:bg-orange-600', 'type' => 'consumable' ], 
                                        'returnable' => [ 'text' => 'ยืม (ต้องคืน)', 'icon' => 'fas fa-hand-holding-heart', 'class' => 'bg-purple-500 hover:bg-purple-600', 'type' => 'returnable' ], 
                                        'partial_return' => [ 'text' => 'เบิก (เหลือคืนได้)', 'icon' => 'fas fa-recycle', 'class' => 'bg-blue-500 hover:bg-blue-600', 'type' => 'partial_return' ], 
                                        // ✅✅✅ แก้ตรงนี้: ใช้ => แทน : ✅✅✅
                                        'unset' => [ 'text' => 'ยังไม่กำหนดประเภท', 'icon' => 'fas fa-question-circle', 'class' => 'bg-green-100 hover:bg-green-300 opacity-90 cursor-not-allowed', 'type' => null ] 
                                    ];
                                    $itemType = $item->withdrawal_type; 
                                    $isUnsetType = is_null($itemType);
                                    if ($isUnsetType) { $btnData = $btnStates['unset']; } elseif (isset($btnStates[$itemType])) { $btnData = $btnStates[$itemType]; } else { $btnData = null; }
                                    
                                    if ($btnData) {
                                        $btn_disabled = false; $btn_title = ''; $btn_class = $btnData['class']; $add_animation_class = false;
                                        $isHardDisabled = ($unconfirmedCount ?? 0) > 0 || $item->quantity <= 0;
                                        $hardDisabledTitle = '';
                                        if ($item->quantity <= 0) $hardDisabledTitle = 'สินค้าหมดสต็อก';
                                        elseif (($unconfirmedCount ?? 0) > 0) $hardDisabledTitle = 'กรุณายืนยันการรับของที่ค้างอยู่ก่อนทำรายการใหม่';
                                        
                                        $isNotDefaultDept = ($currentDeptKey !== $defaultDeptKey);
                                        $currentDeptName = $departments[$currentDeptKey]['name'] ?? 'แผนกนี้';
                                        
                                        $btn_onclick_attr = "";
                                        $target_class = "";

                                        if ($isNotDefaultDept) {
                                            // ต่างแผนก: ใช้ onclick แจ้งเตือน (และไม่ใส่ class พิเศษ)
                                            $btn_onclick_attr = "onclick=\"handleOtherDeptClick('".e($currentDeptName)."')\""; 
                                            $btn_title = 'ไม่สามารถเบิกจากแผนกอื่นได้';
                                            $btn_class = str_replace('hover:bg-', 'bg-', $btnData['class']) . ' opacity-50 cursor-not-allowed';
                                        } elseif ($isUnsetType) {
                                            $btn_onclick_attr = "onclick=\"handleUnsetTypeClick()\"";
                                            $btn_title = 'ยังไม่ได้กำหนดประเภทการเบิก';
                                        } elseif ($isHardDisabled) {
                                            $btn_disabled = true; $btn_title = $hardDisabledTitle;
                                        } else {
                                            // ปกติ: ใส่ class พิเศษ ให้ JS ทำงาน (และไม่มี onclick)
                                            $target_class = "live-search-withdraw-btn"; 
                                            $add_animation_class = true;
                                        }
                                    }
                                @endphp
                                @if ($btnData)
                                    <button class="{{ $target_class }} inline-flex items-center justify-center w-full px-3 py-2 text-xs font-bold text-white transition duration-150 ease-in-out border border-transparent rounded-md disabled:opacity-50 disabled:cursor-not-allowed {{ $btn_class }} @if($add_animation_class) btn-pulse-shadow @endif"
                                        {!! $btn_onclick_attr !!} 
                                        @if($btn_disabled) disabled @endif 
                                        title="{{ $btn_title }}"
                                        data-equipment-id="{{ $item->id }}" 
                                        data-type="{{ $btnData['type'] }}" 
                                        data-name="{{ $item->name }}" 
                                        data-quantity="{{ $item->quantity }}" 
                                        data-unit="{{ optional($item->unit)->name }}" 
                                        data-dept-key="{{ $currentDeptKey }}">
                                        <i class="mr-1 {{ $btnData['icon'] }}"></i> {{ $btnData['text'] }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty <div class="p-8 text-center text-gray-500 dark:text-gray-400 sm:col-span-2 lg:col-span-3 xl:col-span-4"><p>ไม่พบอุปกรณ์</p></div> @endforelse
            </div>
            @if ($equipments && $equipments->hasPages()) <div class="mt-6 pagination-links">{{ $equipments->links() }}</div> @endif
        @else <div class="p-8 text-center text-gray-500 dark:text-gray-400 soft-card rounded-2xl gentle-shadow"><p>กรุณาเลือกแผนก หรือทำการค้นหา</p></div> @endif
    </div> 
</div>

{{-- Modals --}}
<div class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-black bg-opacity-75" id="transaction-details-modal">
    <div class="w-full max-w-lg p-6 mx-4 bg-white rounded-2xl soft-card animate-slide-up-soft dark:bg-gray-800">
        <form id="transaction-details-form" onsubmit="event.preventDefault(); submitTransaction();">
            <input type="hidden" id="modal_equipment_id"><input type="hidden" id="modal_transaction_type"><input type="hidden" id="modal_dept_key" name="modal_dept_key">
            <div class="flex items-start justify-between pb-4 border-b border-gray-200 dark:border-gray-700"><h3 class="text-xl font-bold dark:text-gray-100">รายละเอียด <span id="modal_action_title" class="text-indigo-600 dark:text-indigo-400"></span></h3><button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" onclick="closeModal('transaction-details-modal')"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button></div>
            <div class="py-5 space-y-4">
                <div><label class="block mb-1 font-medium text-gray-700 dark:text-gray-300">อุปกรณ์</label><p id="modal_equipment_name" class="px-3 py-2 bg-gray-100 rounded-lg dark:bg-gray-700 dark:text-gray-200"></p></div>
                <div>
                    <label class="block mb-2 font-medium text-gray-700 dark:text-gray-300">ผู้เบิก</label>
                    <div class="flex items-center space-x-6"><div class="flex items-center"><input type="radio" id="req_self" name="requestor_type" value="self" class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" checked><label for="req_self" class="ml-2 block text-sm text-gray-900 dark:text-gray-200">เบิกให้ตัวเอง</label></div><div class="flex items-center"><input type="radio" id="req_other" name="requestor_type" value="other" class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"><label for="req_other" class="ml-2 block text-sm text-gray-900 dark:text-gray-200">เบิกให้ผู้อื่น</label></div></div><p class="mt-2 text-xs text-gray-500 dark:text-gray-400">(ผู้เบิกปัจจุบัน: {{ Auth::user()->fullname }})</p>
                </div>
                <div id="other-requestor-container" class="hidden"><label for="modal_requestor_id" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">ค้นหาชื่อผู้ใช้</label><select id="modal_requestor_id" name="modal_requestor_id" class="w-full" style="width: 100%;"><option value="" selected></option><optgroup label="ค้นหาทั้งหมด..."></optgroup></select></div>
                <div><label for="modal_quantity" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">จำนวน</label><div class="flex items-center"><input type="number" id="modal_quantity" name="modal_quantity" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" min="1" value="1" required><span id="modal_unit_name" class="ml-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap"></span></div><p id="modal_max_quantity_text" class="mt-1 text-xs text-gray-500 dark:text-gray-400">คงเหลือ: 0</p></div>
                <div>
                    <label for="modal_purpose" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">วัตถุประสงค์</label>
                    <select id="modal_purpose" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                        <option value="">-- กรุณาเลือก --</option><option value="general_use">เบิกใช้งานทั่วไป</option>
                        @if(isset($allOpenTickets) && $allOpenTickets->isNotEmpty()) <optgroup label="อ้างอิงใบแจ้งซ่อม (GLPI - IT)" class="dark:bg-gray-600"> @forelse ($allOpenTickets->where('source', 'it') as $ticket) <option value="glpi-it-{{ $ticket->id }}">[IT] #{{ $ticket->id }}: {{ Str::limit($ticket->name, 50) }}</option> @empty <option disabled>ไม่พบใบงาน IT</option> @endforelse </optgroup> <optgroup label="อ้างอิงใบแจ้งซ่อม (GLPI - EN)" class="dark:bg-gray-600"> @forelse ($allOpenTickets->where('source', 'en') as $ticket) <option value="glpi-en-{{ $ticket->id }}">[EN] #{{ $ticket->id }}: {{ Str::limit($ticket->name, 50) }}</option> @empty <option disabled>ไม่พบใบงาน EN</option> @endforelse </optgroup> @else <optgroup label="อ้างอิงใบแจ้งซ่อม (GLPI)" class="dark:bg-gray-600"><option disabled>ไม่พบใบงาน</option></optgroup> @endif
                    </select>
                </div>
                <div><label for="modal_notes" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">หมายเหตุ</label><textarea id="modal_notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"></textarea></div>
            </div>
            <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700 space-x-3">
                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500" onclick="closeModal('transaction-details-modal')">ยกเลิก</button>
                <button type="submit" id="modal_submit_btn" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"><i class="mr-1 -ml-1 fas fa-check-circle"></i> ยืนยัน</button>
            </div>
        </form>
    </div>
</div>

{{-- Scanner Modal --}}
<div class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-black bg-opacity-75" id="scanner-modal"><div class="w-full max-w-md p-6 mx-4 bg-white rounded-2xl soft-card animate-slide-up-soft dark:bg-gray-800"><div class="flex items-start justify-between pb-4 border-b border-gray-200 dark:border-gray-700"><h3 class="text-xl font-bold dark:text-gray-100">ค้นหาด้วย QR Code</h3><button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" onclick="closeScannerModal()"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button></div><div class="py-5"><p class="mb-4 text-center text-gray-600 dark:text-gray-300">กรุณาหันกล้องไปที่ QR Code</p><div id="qr-reader" class="border rounded-lg overflow-hidden dark:border-gray-600" style="width: 100%;"></div></div><div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700"><button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500" onclick="closeScannerModal()">ยกเลิก</button></div></div></div>

{{-- ✅ Include Modal Rating --}}
@include('partials.modals.rating-modal')

@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
    function handleOtherDeptClick(deptName) { Swal.fire({ icon: 'error', title: 'ไม่สามารถเบิกจากแผนกนี้ได้', text: `รายการนี้เป็นของแผนก ${deptName}`, confirmButtonText: 'ตกลง' }); }
    function handleUnsetTypeClick() { Swal.fire({ icon: 'warning', title: 'ยังไม่ได้กำหนดประเภท', text: 'กรุณาติดต่อ Admin เพื่อระบุประเภท', confirmButtonText: 'ตกลง' }); }

    async function handleTransaction(equipmentId, type, equipmentName, maxQuantity, unitName, deptKey) {
        console.log('Click:', equipmentName);

        try {
            console.log('📡 Checking block status...');
            const response = await fetch("{{ route('transactions.check_status') }}");
            
            if (!response.ok) {
                throw new Error("Network/Server Error: " + response.status);
            }

            const data = await response.json();
            console.log('📥 Check status response:', data);

            if (data.blocked) {
                console.warn('⛔ Blocked by Rating Logic');
                if (typeof openRatingModal === 'function') {
                    openRatingModal(data.unrated_items);
                    Swal.fire({
                        icon: 'warning',
                        title: 'กรุณาประเมินความพึงพอใจ',
                        text: 'คุณมีรายการอุปกรณ์ที่ใช้งานเสร็จสิ้นแล้วและยังไม่ได้ให้คะแนน กรุณาให้คะแนนก่อนทำรายการใหม่',
                        confirmButtonText: 'ตกลง, ไปให้คะแนน'
                    });
                } else {
                    Swal.fire('Error', 'ไม่พบ Modal Rating กรุณารีเฟรชหน้าจอ', 'error');
                }
                return; 
            }

        } catch (e) {
            console.error("Check status failed", e);
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถตรวจสอบสถานะรายการค้างได้ กรุณาลองใหม่ หรือติดต่อ Admin' });
            return; 
        }

        let typeText = '';
        if (type === 'consumable') typeText = 'เบิก (ไม่ต้องคืน)';
        if (type === 'returnable') typeText = 'ยืม (ต้องคืน)';
        if (type === 'partial_return') typeText = 'เบิก (เหลือคืนได้)';

        document.getElementById('modal_equipment_id').value = equipmentId;
        document.getElementById('modal_transaction_type').value = type;
        document.getElementById('modal_dept_key').value = deptKey; 
        document.getElementById('modal_action_title').textContent = typeText;
        document.getElementById('modal_equipment_name').textContent = equipmentName;
        
        const quantityInput = document.getElementById('modal_quantity');
        quantityInput.value = 1; 
        quantityInput.max = maxQuantity;
        
        document.getElementById('modal_max_quantity_text').textContent = `คงเหลือ: ${maxQuantity}`;
        document.getElementById('modal_unit_name').textContent = unitName || '';
        
        $('#req_self').prop('checked', true); 
        $('#other-requestor-container').hide(); 
        $('#modal_requestor_id').val(null).trigger('change'); 
        
        const form = document.getElementById('transaction-details-form');
        form.querySelector('#modal_purpose').value = ''; 
        form.querySelector('#modal_notes').value = '';
        
        showModal('transaction-details-modal');
    }

    async function submitTransaction() {
        const requestorType = $('input[name="requestor_type"]:checked').val();
        const requestorId = $('#modal_requestor_id').val();
        const equipmentId = document.getElementById('modal_equipment_id').value;
        const type = document.getElementById('modal_transaction_type').value;
        const deptKey = document.getElementById('modal_dept_key').value; 
        const purpose = document.getElementById('modal_purpose').value;
        const notes = document.getElementById('modal_notes').value;
        const quantityInput = document.getElementById('modal_quantity');
        const quantity = parseInt(quantityInput.value);
        const maxQuantity = parseInt(quantityInput.max);
        const unitName = document.getElementById('modal_unit_name').textContent || 'ชิ้น';

        if (!purpose.trim()) return Swal.fire('ข้อมูลไม่ครบ!', 'กรุณาเลือกวัตถุประสงค์', 'error');
        if (!quantity || quantity <= 0) return Swal.fire('ข้อมูลไม่ถูกต้อง!', 'กรุณาระบุจำนวนที่ต้องการอย่างน้อย 1', 'error');
        if (quantity > maxQuantity) return Swal.fire('จำนวนไม่ถูกต้อง!', `คุณสามารถเบิก/ยืมได้สูงสุด ${maxQuantity} ${unitName}`, 'error');
        if (requestorType === 'other' && (!requestorId || requestorId === '')) return Swal.fire('ข้อมูลไม่ครบ!', 'กรุณาเลือกชื่อผู้ใช้ที่ต้องการเบิกให้', 'error');

        const equipmentName = document.getElementById('modal_equipment_name').textContent;

        Swal.fire({ title: 'กำลังดำเนินการ...', text: 'กรุณารอสักครู่', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });

        try {
            const response = await fetch("{{ route('ajax.user.transact') }}", {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ equipment_id: equipmentId, type: type, purpose: purpose, notes: notes, quantity: quantity, requestor_type: requestorType, requestor_id: requestorId, dept_key: deptKey })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                closeModal('transaction-details-modal');
                await Swal.fire({ title: 'สำเร็จ!', text: `เบิก ${equipmentName} จำนวน ${quantity} ${unitName}`, icon: 'success', timer: 3000, showConfirmButton: false });
                const searchInput = document.getElementById('live-search-input'); if(searchInput && searchInput.value.length > 0) searchInput.dispatchEvent(new Event('keyup')); else location.reload(); 
            } else {
                if (response.status === 403 && data.error_code === 'UNRATED_TRANSACTIONS') {
                     closeModal('transaction-details-modal');
                     if (typeof openRatingModal === 'function') openRatingModal(data.unrated_items);
                     return;
                }
                await Swal.fire('เกิดข้อผิดพลาด!', data.message || `ไม่สามารถทำรายการได้ (${response.status})`, 'error');
            }
        } catch (error) { console.error(error); await Swal.fire('การเชื่อมต่อล้มเหลว!', 'กรุณาลองใหม่', 'error'); }
    }

    function showModal(modalId) { const modal = document.getElementById(modalId); if(modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); } }
    function closeModal(modalId) { 
        const modal = document.getElementById(modalId); if(modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
        if (modalId === 'scanner-modal' && window.closeScannerModal) window.closeScannerModal();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const scanButton = document.getElementById('scan-qr-button');
        const searchInput = document.getElementById('live-search-input'); 
        let html5QrCode;
        function onScanSuccess(decodedText) { searchInput.value = decodedText; closeScannerModal(); searchInput.dispatchEvent(new Event('keyup')); }
        function openScannerModal() { showModal('scanner-modal'); const qrReaderElement = document.getElementById("qr-reader"); if (!qrReaderElement) return; html5QrCode = new Html5Qrcode("qr-reader"); html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } }, onScanSuccess, () => {}).catch(err => { }); }
        window.closeScannerModal = function() { if (html5QrCode && html5QrCode.isScanning) html5QrCode.stop().catch(err => { }); closeModal('scanner-modal'); }
        if(scanButton) scanButton.addEventListener('click', openScannerModal);
    });

    $(document).ready(function() {
        $('#modal_requestor_id').select2({ dropdownParent: $('#transaction-details-modal'), placeholder: 'พิมพ์ชื่อ...', allowClear: true, ajax: { url: "{{ route('ajax.handler') }}", method: 'POST', dataType: 'json', delay: 250, data: (params) => ({ _token: '{{ csrf_token() }}', action: 'get_ldap_users', q: params.term }), processResults: (data) => ({ results: data.items }), cache: true } });
        $('input[name="requestor_type"]').on('change', function() { if (this.value === 'other') { $('#other-requestor-container').slideDown(200, function() { $('#modal_requestor_id').select2('open'); }); } else { $('#other-requestor-container').slideUp(200); $('#modal_requestor_id').val(null).trigger('change'); } });

        const searchInput = document.getElementById('live-search-input');
        if (searchInput) {
            $('#search-form').on('submit', (e) => e.preventDefault());
            const myResultsDiv = document.getElementById('my-stock-results');
            const otherResultsDiv = document.getElementById('other-stock-results');
            const spinner = document.getElementById('loading-spinner');
            const defaultContent = document.getElementById('default-catalog-content');
            const searchResultsContainer = document.getElementById('search-results-container');
            let debounceTimer;

            searchInput.addEventListener('keyup', function () {
                const query = searchInput.value;
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    if (query.length < 2) {
                        if (defaultContent) defaultContent.style.display = 'block';
                        searchResultsContainer.style.display = 'none'; myResultsDiv.innerHTML = ''; otherResultsDiv.innerHTML = ''; spinner.style.display = 'none'; return;
                    }
                    if (defaultContent) defaultContent.style.display = 'none';
                    searchResultsContainer.style.display = 'block'; spinner.style.display = 'block'; myResultsDiv.innerHTML = ''; otherResultsDiv.innerHTML = '';

                    fetch(`{{ route('inventory.ajax_search') }}?query=${encodeURIComponent(query)}`, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                    .then(response => response.json())
                    .then(data => {
                        spinner.style.display = 'none';
                        
                        // 1. My Stock (ดาวเหลือง + JS)
                        if (data.myStock && data.myStock.length > 0) {
                            let myHtml = `<div class="p-5 soft-card rounded-2xl gentle-shadow"><h2 class="mb-4 text-xl font-bold text-gray-800 dark:text-gray-100"><i class="fas fa-store text-green-500"></i> สต็อกของคุณ (เบิกได้)</h2><div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">`;
                            data.myStock.forEach(item => {
                                const unit = item.unit?.name || 'ชิ้น';
                                let imgUrl = item.image_url ? item.image_url : 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';
                                
                                // ⭐ JS Logic: ดาว 5 ดวง สีเหลือง + ครึ่งดวง ⭐
                                let avgRating = parseFloat(item.avg_rating) || 0;
                                let ratingCount = item.rating_count || 0;
                                let starsHtml = '<div class="flex items-center mt-2 space-x-0.5" title="คะแนนเฉลี่ย: '+avgRating.toFixed(1)+'">';
                                for (let i = 1; i <= 5; i++) {
                                    if (i <= avgRating) {
                                        starsHtml += '<i class="fas fa-star text-yellow-400 text-xs"></i>';
                                    } else if (i - 0.5 <= avgRating) {
                                        starsHtml += '<i class="fas fa-star-half-alt text-yellow-400 text-xs"></i>';
                                    } else {
                                        starsHtml += '<i class="fas fa-star text-gray-300 dark:text-gray-600 text-xs"></i>';
                                    }
                                }
                                starsHtml += `<span class="text-xs text-gray-400 ml-1">(${ratingCount} รีวิว)</span></div>`;

                                const btnStates = { 'consumable': { 'text': 'เบิก', 'icon': 'fas fa-box-open', 'class': 'bg-orange-500 hover:bg-orange-600', 'type': 'consumable', }, 'returnable': { 'text': 'ยืม', 'icon': 'fas fa-hand-holding-heart', 'class': 'bg-purple-500 hover:bg-purple-600', 'type': 'returnable', }, 'partial_return': { 'text': 'เบิก (เหลือคืน)', 'icon': 'fas fa-recycle', 'class': 'bg-blue-500 hover:bg-blue-600', 'type': 'partial_return', }, 'unset': { 'text': 'ยังไม่กำหนด', 'icon': 'fas fa-question-circle', 'class': 'bg-green-100 hover:bg-green-300 opacity-90 cursor-not-allowed', 'type': null, } };
                                const itemType = item.withdrawal_type; const btnData = btnStates[itemType] || btnStates['unset'];
                                let btnDis = false, btnTit = '', btnCls = btnData.class, anim = false;
                                const unconfirmed = {{ $unconfirmedCount ?? 0 }};
                                if (unconfirmed > 0) { btnDis = true; btnTit = 'เคลียร์ของเก่าก่อน'; } else if (item.quantity <= 0) { btnDis = true; btnTit = 'หมด'; } else if (!btnData.type) { btnDis = true; btnTit = 'ยังไม่กำหนดประเภท'; btnCls = btnStates['unset'].class; } else { anim = true; }
                                
                                // My Stock: ใช้ class live-search-withdraw-btn
                                myHtml += `<div class="flex flex-col overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700 equipment-card bg-white dark:bg-gray-800">
                                    <div class="relative flex items-center justify-center w-full h-32 bg-gray-100 dark:bg-gray-700"><img src="${imgUrl}" class="object-contain max-w-full max-h-full" onerror="this.src='https://placehold.co/400x300/e2e8f0/64748b?text=No+Image'"></div>
                                    <div class="p-3"><h3 class="text-sm font-semibold text-gray-800 truncate dark:text-gray-100">${item.name}</h3><p class="text-xs text-gray-500">${item.serial_number||'N/A'}</p>${starsHtml}<span class="block mt-1 text-xs font-medium text-blue-600 dark:text-blue-400">คงเหลือ: ${item.quantity} ${unit}</span></div>
                                    <div class="p-3 pt-0 mt-auto"><button type="button" class="live-search-withdraw-btn inline-flex items-center justify-center w-full px-3 py-2 text-xs font-bold text-white transition duration-150 ease-in-out border border-transparent rounded-md ${btnDis?'disabled:opacity-50 disabled:cursor-not-allowed':''} ${btnCls} ${anim?'btn-pulse-shadow':''}" data-equipment-id="${item.id}" data-type="${btnData.type}" data-name="${item.name.replace(/"/g,'&quot;')}" data-quantity="${item.quantity}" data-unit="${unit.replace(/"/g,'&quot;')}" data-dept-key="${item.dept_key}" ${btnDis?'disabled':''} title="${btnTit}"><i class="mr-1 ${btnData.icon}"></i> ${btnData.text}</button></div></div>`;
                            });
                            myHtml += '</div></div>'; myResultsDiv.innerHTML = myHtml;
                        } else { myResultsDiv.innerHTML = '<p class="p-8 text-center text-gray-500 dark:text-gray-400">ไม่พบอุปกรณ์ในสต็อกของคุณ</p>'; }

                        // 2. Other Stock (ไม่มีดาว + ปุ่มกดไม่ได้)
                        if (data.otherStock && data.otherStock.length > 0) {
                             let otherHtml = `<div class="p-5 soft-card rounded-2xl gentle-shadow"><h2 class="mb-4 text-xl font-bold text-gray-800 dark:text-gray-100">พบในแผนกอื่น</h2><div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">`;
                             data.otherStock.forEach(item => {
                                 const unit = item.unit?.name || 'ชิ้น'; const imgUrl = item.image_url ? item.image_url : 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';
                                 // Other Stock: ไม่มี class live-search-withdraw-btn (กดแล้วไม่ trigger rating)
                                 otherHtml += `<div class="flex flex-col overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700 equipment-card bg-white dark:bg-gray-800 opacity-70"><div class="relative flex items-center justify-center w-full h-32 bg-gray-100 dark:bg-gray-700"><img src="${imgUrl}" class="object-contain max-w-full max-h-full" onerror="this.src='https://placehold.co/400x300/e2e8f0/64748b?text=No+Image'"></div><div class="p-3"><h3 class="text-sm font-semibold text-gray-800 truncate dark:text-gray-100">${item.name}</h3><p class="text-xs text-gray-500">${item.dept_name}</p><span class="block mt-1 text-xs font-medium text-gray-600 dark:text-gray-400">มี: ${item.quantity} ${unit}</span></div><div class="p-3 pt-0 mt-auto"><button type="button" onclick="handleOtherDeptClick('${item.dept_name}')" class="inline-flex items-center justify-center w-full px-3 py-2 text-xs font-bold text-white border border-transparent rounded-md bg-gray-400 opacity-50 cursor-not-allowed"><i class="mr-1 fas fa-ban"></i> เบิกไม่ได้</button></div></div>`;
                             });
                             otherHtml += '</div></div>'; otherResultsDiv.innerHTML = otherHtml;
                        }
                    })
                    .catch(err => { spinner.style.display = 'none'; console.error(err); });
                }, 300);
            });
            $(document).on('click', '.live-search-withdraw-btn', function() {
                const type = $(this).data('type');
                if(type === 'null' || type === null || type === 'unset') { handleUnsetTypeClick(); return; }
                handleTransaction($(this).data('equipment-id'), type, $(this).data('name'), $(this).data('quantity'), $(this).data('unit'), $(this).data('dept-key'));
            });
        }
    });
</script>
@endpush

