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
{{-- ✅ กำหนดแผนกของคุณที่นี่ --}}
@php
    $myDepartment = 'mm'; 
    $defaultDeptKey = $myDepartment;
@endphp

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
                                        <div class="relative flex items-center justify-center w-full h-32 overflow-hidden bg-gray-100 dark:bg-gray-700 group">
                                            @php 
                                                $imageFileName = $item->primary_image_file_name_manual ?? null;
                                                $imageUrl = $imageFileName ? url("nas-images/{$result['dept_key']}/{$imageFileName}") : asset('images/placeholder.webp');
                                            @endphp
                                            <img src="{{ $imageUrl }}" alt="{{ $item->name }}" 
                                                 class="object-contain max-w-full max-h-full cursor-pointer hover:scale-105 transition-transform duration-300" 
                                                 onclick="openImageViewer('{{ $imageUrl }}')">
                                            <div class="absolute bottom-1 right-1 bg-black/50 text-white text-xs px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                                <i class="fas fa-search-plus"></i>
                                            </div>
                                        </div>
                                        <div class="p-3 flex flex-col flex-grow">
                                            <h3 class="text-sm font-semibold text-gray-800 truncate dark:text-gray-100" title="{{ $item->name }}">{{ $item->name }}</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->serial_number ?? 'N/A' }}</p>
                                            <span class="block mt-1 text-xs font-medium text-blue-600 dark:text-blue-400">คงเหลือ: {{ $item->stock_sum_quantity }} {{ optional($item->unit)->name }}</span>
                                            
                                            <div class="mt-auto pt-2 flex gap-1">
                                                @php
                                                    $isSameDept = ($result['dept_key'] == $defaultDeptKey);
                                                    $isDisabled = ($item->stock_sum_quantity <= 0) || !$isSameDept;
                                                    
                                                    // ✅ แก้ไข 1: เพิ่มตัวแปรเช็ค class เพื่อไม่ให้ JS ทำงานซ้อนกับ onclick
                                                    $btnTriggerClass = $isSameDept ? 'live-search-withdraw-btn' : ''; 

                                                    if (!$isSameDept) {
                                                        $btnClass = 'bg-gray-400 text-gray-600 cursor-not-allowed';
                                                        $btnText = 'เบิกไม่ได้';
                                                        $btnIcon = 'fas fa-ban';
                                                        $btnTitle = 'เป็นของแผนก ' . $result['dept_name'];
                                                        $btnOnClick = "handleOtherDeptClick('{$result['dept_name']}')";
                                                    } else {
                                                        $btnStates = [ 
                                                            'consumable' => [ 'text' => 'เบิกด่วน', 'icon' => 'fas fa-bolt', 'class' => 'bg-red-600 hover:bg-red-700' ], 
                                                            'returnable' => [ 'text' => 'ยืมใช้', 'icon' => 'fas fa-hand-holding', 'class' => 'bg-blue-600 hover:bg-blue-700' ], 
                                                            'partial_return' => [ 'text' => 'เบิกเหลือคืนได้', 'icon' => 'fas fa-box-open', 'class' => 'bg-orange-500 hover:bg-orange-600' ], 
                                                            'unset' => [ 'text' => 'รอระบุ', 'icon' => 'fas fa-clock', 'class' => 'bg-gray-400 cursor-not-allowed' ] 
                                                        ];
                                                        $typeKey = $item->withdrawal_type ?? 'unset';
                                                        $state = $btnStates[$typeKey] ?? $btnStates['unset'];
                                                        $btnClass = $state['class'] . ' text-white';
                                                        $btnText = $state['text'];
                                                        $btnIcon = $state['icon'];
                                                        $btnTitle = 'คลิกเพื่อเบิก';
                                                        // ✅ แก้ไข 2: ถ้าเป็นของแผนกตัวเอง ให้ onclick ว่างไว้ (เพราะ class 'live-search-withdraw-btn' จะทำงานแทน)
                                                        $btnOnClick = ""; 
                                                    }
                                                @endphp
                                                
                                                <button onclick="{!! $btnOnClick !!}"
                                                        class="flex-1 text-xs py-1.5 rounded transition {{ $btnClass }} {{ $btnTriggerClass }}" 
                                                        data-equipment-id="{{ $item->id }}" 
                                                        data-type="{{ $item->withdrawal_type ?? 'unset' }}" 
                                                        data-name="{{ addslashes($item->name) }}" 
                                                        data-quantity="{{ $item->stock_sum_quantity }}" 
                                                        data-unit="{{ optional($item->unit)->name }}" 
                                                        data-dept-key="{{ $result['dept_key'] }}"
                                                        @if($isDisabled && $isSameDept) disabled @endif 
                                                        title="{{ $btnTitle }}">
                                                    <i class="mr-1 {{ $btnIcon }}"></i> {{ $btnText }}
                                                </button>
                                                
                                                <button onclick="@if($isSameDept) addToCart({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ $imageUrl }}', {{ $item->stock_sum_quantity }}) @else handleOtherDeptClick('{{ $result['dept_name'] }}') @endif" 
                                                        class="px-2.5 py-1.5 rounded text-xs transition {{ $isSameDept ? 'bg-emerald-500 hover:bg-emerald-600 text-white' : 'bg-gray-400 text-white cursor-not-allowed' }}" 
                                                        title="{{ $isSameDept ? 'เพิ่มลงตะกร้า' : 'เบิกข้ามแผนกไม่ได้' }}">
                                                    <i class="fas {{ $isSameDept ? 'fa-cart-plus' : 'fa-ban' }}"></i>
                                                </button>
                                            </div>
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
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 mt-6">
                @forelse ($equipments as $item)
                    <div class="flex flex-col overflow-hidden soft-card rounded-2xl gentle-shadow equipment-card bg-white dark:bg-gray-800">
                        <div class="relative flex items-center justify-center w-full h-48 overflow-hidden bg-gray-100 rounded-t-2xl dark:bg-gray-700 group">
                            @php 
                                $imageFileName = $item->primary_image_file_name_manual ?? null;
                                $imageUrl = $imageFileName ? url("nas-images/{$currentDeptKey}/{$imageFileName}") : asset('images/placeholder.webp');
                            @endphp
                            <img src="{{ $imageUrl }}" alt="{{ $item->name }}" 
                                 class="object-contain max-w-full max-h-full cursor-pointer hover:scale-105 transition-transform duration-300" 
                                 onclick="openImageViewer('{{ $imageUrl }}')">
                            <div class="absolute bottom-2 right-2 bg-black/50 text-white text-xs px-2 py-1 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                <i class="fas fa-search-plus"></i> ดูรูป
                            </div>
                        </div>
                        <div class="flex flex-col flex-grow p-4">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 line-clamp-2 h-14" title="{{ $item->name }}">{{ $item->name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item->serial_number ?? 'N/A' }}</p>
                            
                            @php
                                $avgRating = $item->ratings_avg_rating_score ?? $item->ratings->avg('rating_score') ?? 0;
                                $ratingCount = $item->ratings_count ?? $item->ratings->count() ?? 0;
                            @endphp
                            <div class="flex items-center mt-2 space-x-0.5" title="คะแนนเฉลี่ย: {{ number_format($avgRating, 1) }}">
                                @for ($i = 1; $i <= 5; $i++)
                                    @if ($i <= $avgRating) <i class="fas fa-star text-yellow-400 text-xs"></i>
                                    @elseif ($i - 0.5 <= $avgRating) <i class="fas fa-star-half-alt text-yellow-400 text-xs"></i>
                                    @else <i class="fas fa-star text-gray-300 dark:text-gray-600 text-xs"></i> @endif
                                @endfor
                                <span class="text-xs text-gray-400 ml-1">({{ $ratingCount }})</span>
                            </div>

                            <div class="flex-grow mt-2"><span class="text-sm font-semibold text-blue-600 dark:text-blue-400">คงเหลือ: {{ $item->quantity }} {{ optional($item->unit)->name }}</span></div>
                            
                            <div class="pt-4 mt-auto space-y-2">
                                @php
                                    $isSameDept = ($currentDeptKey == $defaultDeptKey);
                                    $viewingDeptName = $departments[$currentDeptKey]['name'] ?? 'แผนกอื่น';
                                    $isStockEmpty = $item->quantity <= 0;
                                    $hasUnconfirmed = ($unconfirmedCount ?? 0) > 0;
                                    
                                    // ✅ สำหรับปุ่มตะกร้า (ยังต้องใช้ disabled)
                                    $isDisabled = $isStockEmpty || $hasUnconfirmed || !$isSameDept;

                                    // ✅ ไม่ใช้ disabled แล้ว ให้ปุ่มคลิกได้เสมอ แต่เช็คที่ JavaScript
                                    $btnTriggerClass = $isSameDept ? 'live-search-withdraw-btn' : '';

                                    if (!$isSameDept) {
                                        $btnClass = 'bg-gray-400 text-gray-600 cursor-not-allowed';
                                        $btnIcon = 'fas fa-ban';
                                        $btnText = 'เบิกไม่ได้';
                                        $btnTitle = "รายการนี้เป็นของ {$viewingDeptName} คุณไม่สามารถเบิกได้";
                                        $btnOnClick = "handleOtherDeptClick('{$viewingDeptName}')";
                                    } elseif ($isStockEmpty) {
                                        $btnClass = 'bg-gray-400 text-gray-600 cursor-not-allowed';
                                        $btnIcon = 'fas fa-times';
                                        $btnText = 'เบิกไม่ได้';
                                        $btnTitle = 'สินค้าหมดสต็อก';
                                        $btnOnClick = "Swal.fire('หมดสต็อก', 'สินค้าหมดแล้วครับ', 'warning')";
                                    } elseif ($hasUnconfirmed) {
                                        // ✅ กรณีมีรายการค้างรับ: ให้ปุ่มคลิกได้ แต่ไม่ใส่ onclick (ให้ class จัดการ)
                                        $btnStates = [ 
                                            'consumable' => [ 'text' => 'เบิกด่วน', 'icon' => 'fas fa-bolt', 'class' => 'bg-red-600 hover:bg-red-700' ], 
                                            'returnable' => [ 'text' => 'ยืมแล้วต้องคืน', 'icon' => 'fas fa-hand-holding', 'class' => 'bg-blue-600 hover:bg-blue-700' ], 
                                            'partial_return' => [ 'text' => 'เบิกเหลือคืนได้', 'icon' => 'fas fa-box-open', 'class' => 'bg-orange-500 hover:bg-orange-600' ], 
                                            'unset' => [ 'text' => 'รอระบุ', 'icon' => 'fas fa-clock', 'class' => 'bg-gray-400 cursor-not-allowed' ] 
                                        ];
                                        $typeKey = $item->withdrawal_type ?? 'unset';
                                        $state = $btnStates[$typeKey] ?? $btnStates['unset'];
                                        $btnClass = $state['class'] . ' text-white';
                                        $btnIcon = $state['icon'];
                                        $btnText = $state['text'];
                                        $btnTitle = 'คลิกเพื่อดูรายละเอียด';
                                        $isUnset = ($typeKey === 'unset');
                                        $btnOnClick = $isUnset ? "handleUnsetTypeClick()" : "";
                                    } else {
                                        $btnStates = [ 
                                            'consumable' => [ 'text' => 'เบิกด่วน', 'icon' => 'fas fa-bolt', 'class' => 'bg-red-600 hover:bg-red-700' ], 
                                            'returnable' => [ 'text' => 'ยืมแล้วต้องคืน', 'icon' => 'fas fa-hand-holding', 'class' => 'bg-blue-600 hover:bg-blue-700' ], 
                                            'partial_return' => [ 'text' => 'เบิกเหลือคืนได้', 'icon' => 'fas fa-box-open', 'class' => 'bg-orange-500 hover:bg-orange-600' ], 
                                            'unset' => [ 'text' => 'รอระบุ', 'icon' => 'fas fa-clock', 'class' => 'bg-gray-400 cursor-not-allowed' ] 
                                        ];
                                        $typeKey = $item->withdrawal_type ?? 'unset';
                                        $state = $btnStates[$typeKey] ?? $btnStates['unset'];
                                        $btnClass = $state['class'] . ' text-white';
                                        $btnIcon = $state['icon'];
                                        $btnText = $state['text'];
                                        $btnTitle = 'คลิกเพื่อทำรายการ';
                                        
                                        $isUnset = ($typeKey === 'unset');
                                        $btnOnClick = $isUnset ? "handleUnsetTypeClick()" : "";
                                    }
                                @endphp

                                <div class="flex gap-2">
                                    <button class="flex-1 inline-flex items-center justify-center px-3 py-2 text-xs font-bold text-white transition duration-150 ease-in-out border border-transparent rounded-md {{ $btnClass }} {{ $btnTriggerClass }}"
                                            onclick="{!! $btnOnClick !!}" 
                                            data-equipment-id="{{ $item->id }}"
                                            data-type="{{ $item->withdrawal_type ?? 'unset' }}"
                                            data-name="{{ addslashes($item->name) }}"
                                            data-quantity="{{ $item->quantity }}"
                                            data-unit="{{ optional($item->unit)->name }}"
                                            data-dept-key="{{ $currentDeptKey }}"
                                            title="{{ $btnTitle }}">
                                        <i class="mr-1 {{ $btnIcon }}"></i> {{ $btnText }}
                                    </button>

                                    <button type="button" 
                                            class="flex-none inline-flex items-center justify-center px-3 py-2 text-xs font-bold text-white transition duration-150 ease-in-out bg-emerald-500 border border-transparent rounded-md hover:bg-emerald-600 {{ $isDisabled?'disabled:opacity-50 disabled:cursor-not-allowed':'' }}"
                                            onclick="@if($isDisabled) return; @else addToCart({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ $imageUrl }}', {{ $item->quantity }}) @endif"
                                            @if($isDisabled) disabled @endif
                                            title="เพิ่มลงตะกร้า">
                                        <i class="fas fa-cart-plus fa-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty <div class="p-8 text-center text-gray-500 dark:text-gray-400 sm:col-span-2 lg:col-span-3 xl:col-span-4"><p>ไม่พบอุปกรณ์</p></div> @endforelse
            </div>
            @if ($equipments && $equipments->hasPages()) <div class="mt-6 pagination-links">{{ $equipments->links() }}</div> @endif
        @else <div class="p-8 text-center text-gray-500 dark:text-gray-400 soft-card rounded-2xl gentle-shadow"><p>กรุณาเลือกแผนก หรือทำการค้นหา</p></div> @endif
    </div> 
</div>

{{-- Cart Button & Modals --}}
<div class="fixed bottom-8 right-8 z-40">
    <button onclick="openCartModal()" class="relative group bg-indigo-600 hover:bg-indigo-700 text-white p-4 rounded-full shadow-2xl transition-all transform hover:scale-110 focus:outline-none focus:ring-4 focus:ring-indigo-300">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span id="cart-count" class="absolute -top-2 -right-2 inline-flex items-center justify-center h-7 w-7 text-xs font-bold leading-none text-white bg-red-600 rounded-full border-2 border-white hidden animate-bounce">
            0
        </span>
    </button>
</div>

@include('partials.modals.cart-modal')
@include('partials.modals.rating-modal')

<div id="image-viewer-modal" class="fixed inset-0 z-[300] hidden bg-black bg-opacity-95 flex items-center justify-center p-0 sm:p-4 backdrop-blur-sm transition-opacity duration-300" onclick="closeImageViewer()">
    <button class="absolute top-4 right-4 text-white/80 hover:text-white z-50 focus:outline-none bg-black/20 rounded-full p-2 backdrop-blur-md transition-colors" onclick="closeImageViewer()">
        <svg class="w-8 h-8 drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    </button>
    <img id="image-viewer-img" src="" alt="Full Size" class="max-w-full max-h-[100vh] sm:max-h-[90vh] object-contain shadow-2xl transform transition-transform duration-300 scale-100" onclick="event.stopPropagation()">
</div>

{{-- Transaction Modal --}}
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
                        <option value="">-- กรุณาเลือก --</option>
                        
                        @if(isset($customObjectives) && $customObjectives->isNotEmpty())
                            <optgroup label="วัตถุประสงค์อื่นๆ (เบิกใช้งานทั่วไป)">
                                @foreach($customObjectives as $obj)
                                    <option value="{{ $obj->name }}">{{ $obj->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif

                        @if(isset($allOpenTickets) && $allOpenTickets->isNotEmpty()) 
                            <optgroup label="อ้างอิงใบแจ้งซ่อม (GLPI - IT)" class="dark:bg-gray-600"> 
                                @forelse ($allOpenTickets->where('source', 'it') as $ticket) 
                                    <option value="glpi-it-{{ $ticket->id }}">[IT] #{{ $ticket->id }}: {{ Str::limit($ticket->name, 50) }}</option> 
                                @empty @endforelse 
                            </optgroup> 
                            <optgroup label="อ้างอิงใบแจ้งซ่อม (GLPI - EN)" class="dark:bg-gray-600"> 
                                @forelse ($allOpenTickets->where('source', 'en') as $ticket) 
                                    <option value="glpi-en-{{ $ticket->id }}">[EN] #{{ $ticket->id }}: {{ Str::limit($ticket->name, 50) }}</option> 
                                @empty @endforelse 
                            </optgroup> 
                        @else 
                            <optgroup label="อ้างอิงใบแจ้งซ่อม (GLPI)" class="dark:bg-gray-600"><option disabled>ไม่พบใบงาน</option></optgroup> 
                        @endif
                    </select>
                </div>
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

{{-- ✅ ประกาศ URL ให้ไฟล์ JS ใช้ --}}
<script>
    window.laravelRoutes = {
        ajaxHandler: "{{ route('ajax.handler') }}",
        checkStatus: "{{ route('transactions.check_status') }}",
        bulkWithdraw: "{{ route('transactions.bulkWithdraw') }}"
    };
</script>

@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script src="{{ asset('js/cart.js') }}"></script>

<script>
    const userDefaultDeptKey = "{{ $defaultDeptKey }}";

    function openImageViewer(url) {
        const modal = document.getElementById('image-viewer-modal');
        const img = document.getElementById('image-viewer-img');
        img.src = url;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeImageViewer() {
        const modal = document.getElementById('image-viewer-modal');
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        setTimeout(() => { document.getElementById('image-viewer-img').src = ''; }, 200);
    }

    function handleOtherDeptClick(deptName) { Swal.fire({ icon: 'error', title: 'ไม่สามารถเบิกจากแผนกนี้ได้', text: `รายการนี้เป็นของแผนก ${deptName} คุณสามารถเบิกได้เฉพาะของแผนกที่คุณกำลังเลือกเท่านั้น`, confirmButtonText: 'ตกลง' }); }
    function handleUnsetTypeClick() { Swal.fire({ icon: 'warning', title: 'ยังไม่ได้กำหนดประเภท', text: 'กรุณาติดต่อ Admin เพื่อระบุประเภท', confirmButtonText: 'ตกลง' }); }

    async function handleTransaction(equipmentId, type, equipmentName, maxQuantity, unitName, deptKey) {
        try {
            const response = await fetch("{{ route('transactions.check_status') }}");
            if (!response.ok) throw new Error("Network Error");
            const data = await response.json();
            if (data.blocked) {
                // ✅ Handle pending confirmations with detailed message
                if (data.reason === 'pending_confirmations') {
                    Swal.fire({ 
                        icon: 'warning', 
                        title: 'ไม่สามารถทำรายการได้', 
                        html: data.message.replace(/\n/g, '<br>'),
                        confirmButtonText: 'ตกลง',
                        customClass: {
                            htmlContainer: 'text-left'
                        }
                    });
                    return;
                }
                
                // Handle unrated transactions
                if (data.reason === 'unrated_transactions') {
                    if (typeof openRatingModal === 'function') {
                        openRatingModal(data.unrated_items);
                        Swal.fire({ icon: 'warning', title: 'กรุณาประเมินความพึงพอใจ', text: 'คุณมีรายการอุปกรณ์ที่ใช้งานเสร็จสิ้นแล้ว กรุณาให้คะแนนก่อนทำรายการใหม่', confirmButtonText: 'ไปให้คะแนน' });
                    } else {
                        Swal.fire('Error', 'ไม่พบหน้าต่างประเมิน', 'error');
                    }
                    return;
                }
            }
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ตรวจสอบสถานะไม่สำเร็จ' });
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
        
        showModal('transaction-details-modal');
    }

    async function submitTransaction() {
        const requestorType = $('input[name="requestor_type"]:checked').val();
        const requestorId = (requestorType === 'other') ? $('#modal_requestor_id').val() : null;
        
        const equipmentId = document.getElementById('modal_equipment_id').value;
        const type = document.getElementById('modal_transaction_type').value;
        const deptKey = document.getElementById('modal_dept_key').value; 
        const purpose = document.getElementById('modal_purpose').value;
        const quantityInput = document.getElementById('modal_quantity');
        const quantity = parseInt(quantityInput.value);
        const maxQuantity = parseInt(quantityInput.max);
        const unitName = document.getElementById('modal_unit_name').textContent || 'ชิ้น';

        // ✅ แก้ไข: อนุญาตให้ค่า 'general_use' ผ่านการตรวจสอบ
        if (!purpose || !purpose.trim()) return Swal.fire('ข้อมูลไม่ครบ!', 'กรุณาเลือกวัตถุประสงค์', 'warning');
        if (!quantity || quantity <= 0) return Swal.fire('ข้อมูลไม่ถูกต้อง!', 'กรุณาระบุจำนวนที่ต้องการอย่างน้อย 1', 'warning');
        if (quantity > maxQuantity) return Swal.fire('จำนวนเกินสต็อก!', `คุณสามารถเบิก/ยืมได้สูงสุด ${maxQuantity} ${unitName}`, 'warning');
        
        if (requestorType === 'other' && (!requestorId || requestorId === '')) {
            return Swal.fire('ข้อมูลไม่ครบ!', 'กรุณาเลือกชื่อผู้ใช้ที่ต้องการเบิกให้', 'warning');
        }

        const equipmentName = document.getElementById('modal_equipment_name').textContent;

        Swal.fire({ title: 'กำลังดำเนินการ...', text: 'กรุณารอสักครู่', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });

        try {
            const response = await fetch("{{ route('ajax.user.transact') }}", {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ 
                    equipment_id: equipmentId, 
                    type: type, 
                    purpose: purpose, 
                    notes: '', 
                    quantity: quantity, 
                    requestor_type: requestorType, 
                    requestor_id: requestorId, 
                    dept_key: deptKey 
                })
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
                
                let errorMsg = data.message || `ไม่สามารถทำรายการได้ (${response.status})`;
                if (data.errors) {
                    errorMsg = Object.values(data.errors).flat()[0];
                }
                
                await Swal.fire('เกิดข้อผิดพลาด!', errorMsg, 'error');
            }
        } catch (error) { 
            console.error(error); 
            await Swal.fire('การเชื่อมต่อล้มเหลว!', 'กรุณาลองใหม่', 'error'); 
        }
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
                        
                        if (data.myStock && data.myStock.length > 0) {
                            let myHtml = `<div class="p-5 soft-card rounded-2xl gentle-shadow"><h2 class="mb-4 text-xl font-bold text-gray-800 dark:text-gray-100"><i class="fas fa-store text-green-500"></i> สต็อกของคุณ (เบิกได้)</h2><div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">`;
                            data.myStock.forEach(item => {
                                const unit = item.unit?.name || 'ชิ้น';
                                let imgUrl = item.live_search_image_url ? item.live_search_image_url : 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';
                                
                                let avgRating = parseFloat(item.avg_rating) || 0;
                                let ratingCount = item.rating_count || 0;
                                let starsHtml = '';
                                if (avgRating === 0 && ratingCount > 0) {
                                    starsHtml = '<div class="flex items-center mt-2 space-x-1" title="ประเมินแล้ว: ยังไม่เคยใช้งาน"><span class="text-[10px] text-gray-500 font-bold bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200">📦 ยังไม่ใช้</span><span class="text-xs text-gray-400">('+ratingCount+')</span></div>';
                                } else {
                                    starsHtml = '<div class="flex items-center mt-2 space-x-0.5" title="คะแนนเฉลี่ย: '+avgRating.toFixed(1)+'">';
                                    for (let i = 1; i <= 5; i++) {
                                        if (i <= avgRating) starsHtml += '<i class="fas fa-star text-yellow-400 text-xs"></i>';
                                        else if (i - 0.5 <= avgRating) starsHtml += '<i class="fas fa-star-half-alt text-yellow-400 text-xs"></i>';
                                        else starsHtml += '<i class="fas fa-star text-gray-300 dark:text-gray-600 text-xs"></i>';
                                    }
                                    starsHtml += `<span class="text-xs text-gray-400 ml-1">(${ratingCount})</span></div>`;
                                }

                                const btnStates = { 'consumable': { 'text': 'เบิก', 'icon': 'fas fa-box-open', 'class': 'bg-indigo-600 hover:bg-indigo-700', 'type': 'consumable', }, 'returnable': { 'text': 'ยืม', 'icon': 'fas fa-hand-holding', 'class': 'bg-purple-600 hover:bg-purple-700', 'type': 'returnable', }, 'partial_return': { 'text': 'เบิก', 'icon': 'fas fa-box-open', 'class': 'bg-blue-600 hover:bg-blue-700', 'type': 'partial_return', }, 'unset': { 'text': 'รอระบุ', 'icon': 'fas fa-clock', 'class': 'bg-gray-400 cursor-not-allowed', 'type': null, } };
                                const itemType = item.withdrawal_type; const btnData = btnStates[itemType] || btnStates['unset'];
                                let btnDis = false, btnTit = '', btnCls = btnData.class;
                                const unconfirmed = {{ $unconfirmedCount ?? 0 }};
                                if (unconfirmed > 0) { btnDis = true; btnTit = 'เคลียร์ของเก่าก่อน'; } else if (item.quantity <= 0) { btnDis = true; btnTit = 'หมด'; } else if (!btnData.type) { btnDis = true; btnTit = 'ยังไม่กำหนดประเภท'; btnCls = btnStates['unset'].class; }
                                
                                // ✅ 3. เช็คว่าแผนกของของชิ้นนี้ ตรงกับแผนกของผู้ใช้ (userDefaultDeptKey) หรือไม่
                                const isSameDept = item.dept_key === userDefaultDeptKey; 
                                
                                const buttonDisabled = btnDis || !isSameDept;
                                const buttonTitle = !isSameDept ? `ของแผนก ${item.dept_name || 'อื่น'} เบิกไม่ได้` : btnTit;
                                
                                // ✅ 4. ตัวแปรสำหรับ Class ปุ่มเปิด Modal (ป้องกัน JS ทำงานซ้อนกับ onclick)
                                const btnTriggerClass = isSameDept ? 'live-search-withdraw-btn' : '';
                                
                                if (!isSameDept) { btnCls = 'bg-gray-400 cursor-not-allowed opacity-50'; }

                                myHtml += `<div class="flex flex-col overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700 equipment-card bg-white dark:bg-gray-800">
                                    <div class="relative flex items-center justify-center w-full h-32 bg-gray-100 dark:bg-gray-700 group">
                                        <img src="${imgUrl}" class="object-contain max-w-full max-h-full cursor-pointer hover:scale-105 transition-transform duration-300" onclick="openImageViewer('${imgUrl}')">
                                        <div class="absolute bottom-1 right-1 bg-black/50 text-white text-xs px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"><i class="fas fa-search-plus"></i></div>
                                    </div>
                                    <div class="p-3"><h3 class="text-sm font-semibold text-gray-800 truncate dark:text-gray-100">${item.name}</h3><p class="text-xs text-gray-500">${item.serial_number||'N/A'}</p>${starsHtml}<span class="block mt-1 text-xs font-medium text-blue-600 dark:text-blue-400">คงเหลือ: ${item.quantity} ${unit}</span></div>
                                    <div class="p-3 pt-0 mt-auto flex gap-2">
                                        <button type="button" class="flex-1 inline-flex items-center justify-center px-3 py-2 text-xs font-bold text-white transition duration-150 ease-in-out border border-transparent rounded-md ${buttonDisabled?'disabled:opacity-50 disabled:cursor-not-allowed':''} ${btnCls} ${btnTriggerClass}" 
                                            onclick="${!isSameDept ? `handleOtherDeptClick('${item.dept_name}')` : ''}"
                                            data-equipment-id="${item.id}" data-type="${btnData.type}" data-name="${item.name.replace(/"/g,'&quot;')}" data-quantity="${item.quantity}" data-unit="${unit.replace(/"/g,'&quot;')}" data-dept-key="${item.dept_key}" ${buttonDisabled?'disabled':''} title="${buttonTitle}"><i class="mr-1 ${!isSameDept ? 'fas fa-ban' : btnData.icon}"></i> ${!isSameDept ? 'เบิกไม่ได้' : btnData.text}</button>
                                        
                                        <button type="button" onclick="addToCart(${item.id}, '${item.name.replace(/'/g, "\\'")}', '${imgUrl}', ${item.quantity})" class="flex-none inline-flex items-center justify-center px-3 py-2 text-xs font-bold text-white transition duration-150 ease-in-out bg-emerald-500 border border-transparent rounded-md hover:bg-emerald-600 ${buttonDisabled?'disabled:opacity-50 disabled:cursor-not-allowed':''}" ${buttonDisabled?'disabled':''} title="เพิ่มลงตะกร้า"><i class="fas ${!isSameDept ? 'fas fa-ban' : 'fa-cart-plus'}"></i></button>
                                    </div></div>`;
                            });
                            myHtml += '</div></div>'; myResultsDiv.innerHTML = myHtml;
                        } else { myResultsDiv.innerHTML = '<p class="p-8 text-center text-gray-500 dark:text-gray-400">ไม่พบอุปกรณ์ในสต็อกของคุณ</p>'; }

                        // 2. Other Stock
                        if (data.otherStock && data.otherStock.length > 0) {
                             let otherHtml = `<div class="p-5 soft-card rounded-2xl gentle-shadow"><h2 class="mb-4 text-xl font-bold text-gray-800 dark:text-gray-100">พบในแผนกอื่น</h2><div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">`;
                             data.otherStock.forEach(item => {
                                 const unit = item.unit?.name || 'ชิ้น'; const imgUrl = item.live_search_image_url ? item.live_search_image_url : 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';
                                 otherHtml += `<div class="flex flex-col overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700 equipment-card bg-white dark:bg-gray-800 opacity-70">
                                 <div class="relative flex items-center justify-center w-full h-32 bg-gray-100 dark:bg-gray-700 group">
                                     <img src="${imgUrl}" class="object-contain max-w-full max-h-full cursor-pointer hover:scale-105 transition-transform duration-300" onclick="openImageViewer('${imgUrl}')">
                                     <div class="absolute bottom-1 right-1 bg-black/50 text-white text-xs px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"><i class="fas fa-search-plus"></i></div>
                                 </div>
                                 <div class="p-3"><h3 class="text-sm font-semibold text-gray-800 truncate dark:text-gray-100">${item.name}</h3><p class="text-xs text-gray-500">${item.dept_name}</p><span class="block mt-1 text-xs font-medium text-gray-600 dark:text-gray-400">มี: ${item.quantity} ${unit}</span></div><div class="p-3 pt-0 mt-auto">
                                 <button onclick="handleOtherDeptClick('${item.dept_name}')" class="inline-flex items-center justify-center w-full px-3 py-2 text-xs font-bold text-white border border-transparent rounded-md bg-gray-400 opacity-90 cursor-not-allowed"><i class="mr-1 fas fa-ban"></i> เบิกไม่ได้</button>
                                 </div></div>`;
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