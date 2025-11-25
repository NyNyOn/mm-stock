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

                        {{-- 3. เลือกหมวดหมู่ --}}
                        <div>
                            <label for="category_id" class="block mb-2 text-sm font-bold text-gray-700">เลือกประเภทอุปกรณ์</label>
                            <div class="relative">
                                <select name="category_id" id="category_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-400 outline-none appearance-none bg-white pr-10 cursor-pointer" onchange="updateCategoryInfo(this)">
                                    <option value="">-- ตรวจนับทุกหมวดหมู่ --</option>
                                    @foreach($categories as $category)
                                        @php
                                            $statusKey = $category->stock_status['status'] ?? 'normal';
                                            $labelStatus = '';
                                            $classStatus = '';
                                            if ($statusKey === 'critical') {
                                                $labelStatus = ' (🚨 เลยกำหนด)';
                                                $classStatus = 'text-red-600 font-bold';
                                            } elseif ($statusKey === 'warning') {
                                                $labelStatus = ' (⚠️ ใกล้ถึง)';
                                                $classStatus = 'text-yellow-600 font-bold';
                                            }
                                        @endphp
                                        <option value="{{ $category->id }}" 
                                            data-last="{{ $category->stock_status['last_check'] ?? '-' }}" 
                                            data-total="{{ $category->stock_status['total_items'] ?? 0 }}"
                                            class="{{ $classStatus }}"
                                        >
                                            {{ $category->name . $labelStatus }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-500">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. การ์ดแสดงสถานะ --}}
                    <div id="category-info-card" class="hidden relative overflow-hidden rounded-2xl border-2 transition-all duration-300 shadow-sm group">
                        <div class="absolute inset-0 opacity-10 pattern-dots pointer-events-none"></div>
                        <div class="relative z-10 p-5">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h4 class="text-lg font-extrabold uppercase tracking-wide flex items-center gap-2" id="info-title"></h4>
                                    <p class="text-sm opacity-90 mt-1" id="info-subtitle">สถานะปัจจุบัน</p>
                                </div>
                                <div class="bg-white/80 p-2.5 rounded-full shadow-sm backdrop-blur-sm transform transition-transform duration-500 group-hover:scale-110">
                                    <span id="status-icon" class="text-3xl"></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="bg-white/60 p-3 rounded-xl border border-white/50 backdrop-blur-sm text-center">
                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-60">จำนวนอุปกรณ์</div>
                                    <div class="text-xl font-bold mt-1 text-gray-800" id="info-total">-</div>
                                </div>
                                <div class="bg-white/60 p-3 rounded-xl border border-white/50 backdrop-blur-sm text-center">
                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-60">นับล่าสุดเมื่อ</div>
                                    <div class="text-sm font-bold mt-2 text-gray-800" id="info-last">-</div>
                                </div>
                                <div class="bg-white/60 p-3 rounded-xl border border-white/50 backdrop-blur-sm text-center relative overflow-hidden">
                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-60" id="time-label">เวลาคงเหลือ</div>
                                    <div class="mt-1 flex flex-col items-center justify-center">
                                        <span class="text-xl font-black tracking-tight leading-none" id="info-days">0</span>
                                        <span class="text-[10px] font-mono opacity-80 mt-1" id="info-time-detail">00:00:00</span>
                                    </div>
                                    <div class="absolute bottom-0 left-0 h-1 bg-current opacity-30 w-full">
                                        <div id="timer-progress" class="h-full w-full origin-left transform scale-x-0 transition-transform duration-1000"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-black/5">
                                <p class="text-sm font-medium text-center leading-relaxed" id="info-message"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-100">
                    <a href="{{ route('stock-checks.index') }}" class="px-5 py-2.5 font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">ย้อนกลับ</a>
                    <button type="submit" class="px-6 py-2.5 font-bold text-white bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg shadow-md hover:shadow-lg hover:scale-105 transition-all transform"><i class="mr-2 fas fa-check-circle"></i> สร้างรายการนับสต็อก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let countdownInterval;

    function parseDateComplete(dateStr) {
        if (!dateStr || dateStr === '-' || dateStr.includes('ไม่เคย')) return null;

        const parts = dateStr.trim().split(' ');
        const datePart = parts[0];
        const timePart = parts[1] || '00:00:00';

        const dParts = datePart.split('-'); 
        const tParts = timePart.split(':');

        if (dParts.length === 3) {
            return new Date(
                parseInt(dParts[0]), parseInt(dParts[1]) - 1, parseInt(dParts[2]),
                parseInt(tParts[0]||0), parseInt(tParts[1]||0), parseInt(tParts[2]||0)
            );
        }
        return null;
    }

    function updateCategoryInfo(select) {
        const card = document.getElementById('category-info-card');
        const option = select.options[select.selectedIndex];
        
        if (countdownInterval) clearInterval(countdownInterval);

        if (!select.value) {
            card.classList.add('hidden');
            return;
        }

        const lastCheckStr = option.dataset.last; 
        const total = option.dataset.total;
        
        document.getElementById('info-total').innerText = total + ' ชิ้น';
        document.getElementById('info-last').innerText = lastCheckStr;
        
        card.classList.remove('hidden');
        card.classList.add('block', 'animate-fade-in-up');

        const lastCheckDate = parseDateComplete(lastCheckStr);

        // --- CASE 1: ไม่เคยนับ (CRITICAL) ---
        if (!lastCheckDate) {
            applyTheme('critical');
            document.getElementById('time-label').innerText = 'สถานะ';
            document.getElementById('info-days').innerText = 'ไม่เคยนับ';
            document.getElementById('info-time-detail').innerText = '--:--:--';
            document.getElementById('status-icon').innerText = '🚨';
            document.getElementById('info-title').innerText = 'วิกฤต (CRITICAL)';
            document.getElementById('info-subtitle').innerText = 'ข้อมูลขาดหาย';
            document.getElementById('info-message').innerHTML = 'หมวดหมู่นี้ยังไม่มีประวัติการนับสต็อก <span class="font-bold underline">ต้องนับทันที</span>';
            document.getElementById('timer-progress').style.transform = 'scaleX(1)';
            return;
        }

        const deadlineDate = new Date(lastCheckDate);
        deadlineDate.setDate(deadlineDate.getDate() + 105);
        const warningDate = new Date(lastCheckDate);
        warningDate.setDate(warningDate.getDate() + 90);

        const updateTimer = () => {
            const now = new Date().getTime();
            const distance = deadlineDate.getTime() - now;
            const absDist = Math.abs(distance);
            const d = Math.floor(absDist / (1000 * 60 * 60 * 24));
            const h = Math.floor((absDist % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((absDist % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((absDist % (1000 * 60)) / 1000);
            const hStr = h.toString().padStart(2, '0');
            const mStr = m.toString().padStart(2, '0');
            const sStr = s.toString().padStart(2, '0');

            if (distance < 0) { 
                applyTheme('critical');
                document.getElementById('time-label').innerText = 'เลยกำหนดมาแล้ว';
                document.getElementById('info-days').innerHTML = `${d} <span class="text-sm font-normal">วัน</span>`;
                document.getElementById('info-time-detail').innerText = `${hStr}:${mStr}:${sStr}`;
                document.getElementById('status-icon').innerText = '🚨';
                document.getElementById('info-title').innerText = 'เลยกำหนด (LOCKED)';
                document.getElementById('info-subtitle').innerText = 'ระบบถูกล็อก';
                document.getElementById('info-message').innerHTML = `เกินกำหนดมาแล้ว <span class="font-bold">${d} วัน</span> ต้องนับสต็อกด่วน`;
                document.getElementById('timer-progress').style.transform = 'scaleX(1)';
            } else if (now >= warningDate.getTime()) {
                applyTheme('warning');
                document.getElementById('time-label').innerText = 'เหลือเวลาอีก';
                document.getElementById('info-days').innerHTML = `${d} <span class="text-sm font-normal">วัน</span>`;
                document.getElementById('info-time-detail').innerText = `${hStr}:${mStr}:${sStr}`;
                document.getElementById('status-icon').innerText = '⚠️';
                document.getElementById('info-title').innerText = 'แจ้งเตือน (WARNING)';
                document.getElementById('info-subtitle').innerText = 'เกิน 90 วันแล้ว';
                document.getElementById('info-message').innerHTML = `ควรนับภายใน <span class="font-bold">${d} วัน</span> ก่อนล็อก`;
                document.getElementById('timer-progress').style.transform = 'scaleX(0.8)';
            } else {
                applyTheme('normal');
                document.getElementById('time-label').innerText = 'เหลือเวลาอีก';
                document.getElementById('info-days').innerHTML = `${d} <span class="text-sm font-normal">วัน</span>`;
                document.getElementById('info-time-detail').innerText = `${hStr}:${mStr}:${sStr}`;
                document.getElementById('status-icon').innerText = '✅';
                document.getElementById('info-title').innerText = 'ปกติ (NORMAL)';
                document.getElementById('info-subtitle').innerText = 'สถานะดีเยี่ยม';
                document.getElementById('info-message').innerHTML = 'ยังไม่ต้องดำเนินการใดๆ';
                document.getElementById('timer-progress').style.transform = 'scaleX(0)';
            }
        };

        function applyTheme(type) {
            card.classList.remove('bg-red-50', 'border-red-500', 'text-red-900', 'bg-yellow-50', 'border-yellow-500', 'text-yellow-900', 'bg-green-50', 'border-green-500', 'text-green-900');
            const iconWrapper = document.getElementById('status-icon').parentElement;
            iconWrapper.className = 'p-2.5 rounded-full shadow-sm backdrop-blur-sm transform transition-transform duration-500 group-hover:scale-110';
            const progressElem = document.getElementById('timer-progress');

            if (type === 'critical') {
                card.classList.add('bg-red-50', 'border-red-500', 'text-red-900');
                iconWrapper.classList.add('bg-red-200', 'animate-pulse');
                progressElem.className = 'h-full w-full bg-red-600 origin-left transition-transform duration-1000';
            } else if (type === 'warning') {
                card.classList.add('bg-yellow-50', 'border-yellow-500', 'text-yellow-900');
                iconWrapper.classList.add('bg-yellow-200');
                progressElem.className = 'h-full w-full bg-yellow-500 origin-left transition-transform duration-1000';
            } else {
                card.classList.add('bg-green-50', 'border-green-500', 'text-green-900');
                iconWrapper.classList.add('bg-green-200');
                progressElem.className = 'h-full w-full bg-green-500 origin-left transition-transform duration-1000';
            }
        }

        updateTimer();
        countdownInterval = setInterval(updateTimer, 1000);
    }
</script>
@endsection