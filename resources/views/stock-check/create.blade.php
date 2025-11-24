@extends('layouts.app')

@section('header', 'สร้างงานตรวจนับสต็อก')
@section('subtitle', 'กำหนดรอบการตรวจนับสต็อกใหม่')

@section('content')
<div class="container p-4 mx-auto">
    <div class="max-w-3xl mx-auto">
        <div class="soft-card rounded-2xl gentle-shadow bg-white">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-clipboard-list mr-3 text-indigo-500"></i>
                    เริ่มการตรวจนับสต็อกใหม่
                </h3>
            </div>
            
            <form action="{{ route('stock-checks.store') }}" method="POST" class="p-6">
                @csrf
                <div class="space-y-6">
                    
                    {{-- 1. ชื่องาน --}}
                    <div>
                        <label for="name" class="block mb-2 text-sm font-bold text-gray-700">ชื่องานตรวจนับ</label>
                        <input type="text" name="name" id="name" value="ตรวจนับสต็อก ประจำเดือน {{ now()->format('F Y') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 outline-none transition-all" required>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- 2. วันที่ --}}
                        <div>
                            <label for="scheduled_date" class="block mb-2 text-sm font-bold text-gray-700">วันที่กำหนดตรวจนับ</label>
                            <input type="date" name="scheduled_date" id="scheduled_date" value="{{ now()->format('Y-m-d') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-400 outline-none transition-all" required>
                        </div>

                        {{-- 3. เลือกหมวดหมู่ (Dropdown) --}}
                        <div>
                            <label for="category_id" class="block mb-2 text-sm font-bold text-gray-700">เลือกประเภทอุปกรณ์</label>
                            <div class="relative">
                                <select name="category_id" id="category_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-400 outline-none appearance-none bg-white pr-10 cursor-pointer" onchange="updateCategoryInfo(this)">
                                    <option value="">-- ตรวจนับทุกหมวดหมู่ --</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" 
                                            data-status="{{ $category->stock_status['status'] }}"
                                            data-days="{{ $category->stock_status['days_left'] }}"
                                            data-last="{{ $category->stock_status['last_check'] }}"
                                            data-total="{{ $category->stock_status['total_items'] }}"
                                        >
                                            {{ $category->name }} 
                                            @if($category->stock_status['status'] == 'critical') (🚨 เลยกำหนด)
                                            @elseif($category->stock_status['status'] == 'warning') (⚠️ ครบกำหนด)
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-500">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. การ์ดแสดงสถานะ (Dynamic) --}}
                    <div id="category-info-card" class="hidden p-4 rounded-xl border transition-all duration-300">
                        <h4 class="text-sm font-bold uppercase tracking-wide mb-2 flex items-center gap-2" id="info-title">
                            <i class="fas fa-info-circle"></i> สรุปสถานะหมวดหมู่
                        </h4>
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div class="bg-white/50 p-2 rounded-lg">
                                <p class="text-xs text-gray-500">รายการทั้งหมด</p>
                                <p class="text-lg font-bold text-gray-800" id="info-total">-</p>
                            </div>
                            <div class="bg-white/50 p-2 rounded-lg">
                                <p class="text-xs text-gray-500">นับล่าสุดเมื่อ</p>
                                <p class="text-sm font-bold text-gray-800" id="info-last">-</p>
                            </div>
                            <div class="bg-white/50 p-2 rounded-lg">
                                <p class="text-xs text-gray-500">ต้องนับในอีก</p>
                                <p class="text-lg font-bold" id="info-days">-</p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-center" id="info-message"></p>
                    </div>

                </div>

                <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-100">
                    <a href="{{ route('stock-checks.index') }}" class="px-5 py-2.5 font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        ย้อนกลับ
                    </a>
                    <button type="submit" class="px-6 py-2.5 font-bold text-white bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg shadow-md hover:shadow-lg hover:scale-105 transition-all transform">
                        <i class="mr-2 fas fa-check-circle"></i> สร้างรายการนับสต็อก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function updateCategoryInfo(select) {
        const card = document.getElementById('category-info-card');
        const option = select.options[select.selectedIndex];
        
        if (!select.value) {
            card.classList.add('hidden');
            return;
        }

        const status = option.dataset.status;
        const days = parseInt(option.dataset.days);
        const last = option.dataset.last;
        const total = option.dataset.total;

        // Update Texts
        document.getElementById('info-total').innerText = total + ' ชิ้น';
        document.getElementById('info-last').innerText = last;
        document.getElementById('info-days').innerText = days < 0 ? 'เลยกำหนด' : days + ' วัน';

        // Styling based on Status
        card.className = 'p-4 rounded-xl border transition-all duration-300 block animate-fade-in-up';
        const msgElem = document.getElementById('info-message');
        const titleElem = document.getElementById('info-title');
        const daysElem = document.getElementById('info-days');

        if (status === 'critical') {
            card.classList.add('bg-red-50', 'border-red-200', 'text-red-800');
            titleElem.className = 'text-sm font-bold uppercase tracking-wide mb-2 flex items-center gap-2 text-red-600';
            daysElem.className = 'text-lg font-bold text-red-600';
            msgElem.innerHTML = '<span class="font-bold text-red-600"><i class="fas fa-exclamation-circle"></i> วิกฤต!</span> อุปกรณ์ในหมวดนี้ถูกแช่แข็ง (Frozen) หรือยังไม่เคยนับ กรุณานับด่วน';
        } else if (status === 'warning') {
            card.classList.add('bg-yellow-50', 'border-yellow-200', 'text-yellow-800');
            titleElem.className = 'text-sm font-bold uppercase tracking-wide mb-2 flex items-center gap-2 text-yellow-600';
            daysElem.className = 'text-lg font-bold text-yellow-600';
            msgElem.innerHTML = '<span class="font-bold text-yellow-600"><i class="fas fa-clock"></i> แจ้งเตือน</span> อุปกรณ์ใกล้ครบกำหนดนับสต็อกแล้ว';
        } else if (status === 'empty') {
            card.classList.add('bg-gray-50', 'border-gray-200', 'text-gray-500');
            titleElem.className = 'text-sm font-bold uppercase tracking-wide mb-2 flex items-center gap-2 text-gray-600';
            daysElem.className = 'text-lg font-bold text-gray-400';
            msgElem.innerHTML = 'หมวดหมู่นี้ไม่มีอุปกรณ์ในระบบ';
        } else {
            card.classList.add('bg-green-50', 'border-green-200', 'text-green-800');
            titleElem.className = 'text-sm font-bold uppercase tracking-wide mb-2 flex items-center gap-2 text-green-600';
            daysElem.className = 'text-lg font-bold text-green-600';
            msgElem.innerHTML = '<span class="font-bold text-green-600"><i class="fas fa-check-circle"></i> ปกติ</span> สถานะการนับสต็อกยังอยู่ในเกณฑ์ดี';
        }
    }
</script>
@endsection