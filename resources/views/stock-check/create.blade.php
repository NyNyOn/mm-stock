@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#F3F4F6] py-10 font-sans selection:bg-indigo-500 selection:text-white">
    <div class="container px-4 mx-auto max-w-7xl">

        {{-- 🌈 HEADER --}}
        <div class="mb-12 relative">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 relative z-10">
                <div>
                    <h1 class="text-4xl font-black text-slate-800 tracking-tight mb-2 flex items-center gap-3">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-violet-600 drop-shadow-sm">
                            Stock Management
                        </span>
                    </h1>
                    <p class="text-slate-500 text-lg font-medium">จัดการรอบการตรวจนับและตรวจสอบสถานะอุปกรณ์</p>
                </div>
                <div class="bg-white px-4 py-2 rounded-full shadow-sm border border-slate-200 flex items-center gap-2 text-sm font-medium text-slate-600">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    Now: {{ now()->format('d M Y H:i:s') }}
                </div>
            </div>
            <div class="absolute top-0 right-0 -mt-10 -mr-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl -z-0 pointer-events-none"></div>
        </div>

        {{-- 📦 MAIN LAYOUT --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start relative">
            
            {{-- 👈 LEFT COLUMN: Category List --}}
            <div class="lg:col-span-7 xl:col-span-8 space-y-5">
                
                {{-- List Header --}}
                <div class="flex items-center justify-between px-1 mb-2">
                    <h3 class="font-bold text-slate-700 text-lg">หมวดหมู่ทั้งหมด</h3>
                    <span class="text-xs font-bold px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg">
                        {{ $categories->count() + 1 }} Items
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    
                    {{-- 1. All Items Card --}}
                    @php
                        $totalAllItems = App\Models\Equipment::where('status', '!=', 'sold')->count();
                    @endphp
                    <div onclick="selectCategory(this)" 
                         class="category-card group relative bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:border-indigo-300 transition-all duration-300 cursor-pointer overflow-hidden transform hover:-translate-y-1"
                         data-id="" data-name="ตรวจนับทั้งหมด" data-last="{{ $lastGlobalCheckDate }}" data-total="{{ $totalAllItems }}" data-status="all">
                        
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-bl-[100px] -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
                        <div class="relative z-10">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/30 mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-cubes text-2xl"></i>
                            </div>
                            <h4 class="text-xl font-bold text-slate-800 group-hover:text-indigo-600 transition-colors">ตรวจนับทั้งหมด</h4>
                            <p class="text-slate-500 text-sm mt-1">รายการรวม {{ $totalAllItems }} ชิ้น</p>
                        </div>
                        <div class="active-ring absolute inset-0 border-[3px] border-indigo-500 rounded-2xl opacity-0 scale-95 transition-all duration-200 pointer-events-none"></div>
                    </div>

                    {{-- 2. Loop Categories --}}
                    @foreach($categories as $category)
                        @php
                            $stockStatus = $category->stock_status ?? [];
                            $status = $stockStatus['status'] ?? 'normal';
                            $daysLeft = isset($stockStatus['days_left']) ? intval($stockStatus['days_left']) : 105;
                            $lastCheck = $stockStatus['last_check'] ?? '-';
                            $totalItems = $stockStatus['total_items'] ?? 0;
                            
                            $theme = match($status) {
                                'critical' => ['icon_bg' => 'bg-gradient-to-br from-rose-500 to-red-600', 'shadow' => 'shadow-rose-500/30', 'text' => 'text-rose-600', 'bg_soft' => 'bg-rose-50', 'icon' => 'fa-exclamation-triangle', 'border' => 'group-hover:border-rose-300'],
                                'warning' => ['icon_bg' => 'bg-gradient-to-br from-amber-400 to-orange-500', 'shadow' => 'shadow-orange-500/30', 'text' => 'text-amber-600', 'bg_soft' => 'bg-amber-50', 'icon' => 'fa-stopwatch', 'border' => 'group-hover:border-amber-300'],
                                'normal' => ['icon_bg' => 'bg-gradient-to-br from-emerald-400 to-teal-500', 'shadow' => 'shadow-emerald-500/30', 'text' => 'text-emerald-600', 'bg_soft' => 'bg-emerald-50', 'icon' => 'fa-check', 'border' => 'group-hover:border-emerald-300'],
                                'empty' => ['icon_bg' => 'bg-slate-300', 'shadow' => 'shadow-none', 'text' => 'text-slate-400', 'bg_soft' => 'bg-slate-50', 'icon' => 'fa-box-open', 'border' => 'border-slate-100'],
                                default => ['icon_bg' => 'bg-slate-400', 'shadow' => 'shadow-slate-400/30', 'text' => 'text-slate-600', 'bg_soft' => 'bg-slate-50', 'icon' => 'fa-question', 'border' => 'group-hover:border-slate-300']
                            };

                            // กำหนด Label เริ่มต้น
                            if ($status === 'empty') {
                                $statusLabel = "ไม่มีอุปกรณ์";
                            } elseif ($lastCheck === '-') {
                                $statusLabel = "ยังไม่เคยตรวจนับ"; 
                            } else {
                                $statusLabel = match($status) {
                                    'critical' => "เกินกำหนด",
                                    'warning' => "เหลือเวลา",
                                    'normal' => "ปกติ",
                                    default => "-"
                                };
                            }
                        @endphp

                        <div onclick="selectCategory(this)"
                             class="category-card group relative bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-[0_8px_30px_rgb(0,0,0,0.12)] {{ $theme['border'] }} transition-all duration-300 cursor-pointer overflow-hidden transform hover:-translate-y-1 {{ $status === 'empty' ? 'opacity-60 cursor-not-allowed pointer-events-none grayscale' : '' }}"
                             data-id="{{ $category->id }}" 
                             data-name="{{ $category->name }}" 
                             data-last="{{ $lastCheck }}" 
                             data-total="{{ $totalItems }}"
                             data-status="{{ $status }}"
                             data-days="{{ $daysLeft }}">

                            <div class="flex justify-between items-start mb-4">
                                <div class="w-12 h-12 rounded-xl {{ $theme['icon_bg'] }} {{ $theme['shadow'] }} flex items-center justify-center text-white text-lg transition-transform group-hover:scale-110 duration-300">
                                    <i class="fas {{ $theme['icon'] }}"></i>
                                </div>
                                <div class="text-right">
                                    <span class="block text-2xl font-black text-slate-800">{{ $totalItems }}</span>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">ITEMS</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <h4 class="text-lg font-bold text-slate-700 truncate pr-2 group-hover:text-slate-900">{{ $category->name }}</h4>
                            </div>

                            {{-- 🔥 LIVE TIMER ON CARD --}}
                            <div class="flex items-center justify-between p-2 rounded-lg {{ $theme['bg_soft'] }}">
                                <div class="flex flex-col">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 leading-none mb-0.5">{{ $statusLabel }}</span>
                                    
                                    <span class="text-sm font-bold {{ $theme['text'] }} card-live-timer font-mono tracking-tight" 
                                          data-last-check="{{ $lastCheck }}">
                                          {{-- JS จะมายิงเวลาใส่ตรงนี้ --}}
                                          @if($lastCheck === '-')
                                              <span class="font-sans">New Item</span>
                                          @else
                                              Loading...
                                          @endif
                                    </span>
                                </div>
                                <span class="text-[10px] text-slate-500 font-mono self-end">
                                    {{ $lastCheck === '-' ? '' : \Carbon\Carbon::parse($lastCheck)->format('d/m/y') }}
                                </span>
                            </div>

                            <div class="active-ring absolute inset-0 border-[3px] border-indigo-500 rounded-2xl opacity-0 scale-95 transition-all duration-200 pointer-events-none"></div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- 👉 RIGHT COLUMN: Sticky Dashboard --}}
            <div class="lg:col-span-5 xl:col-span-4 relative">
                <div class="sticky top-6 space-y-6" id="action-panel">
                    
                    {{-- EMPTY STATE --}}
                    <div id="empty-state-panel" class="bg-white/80 backdrop-blur-md rounded-3xl p-10 text-center border border-slate-200 shadow-sm flex flex-col items-center justify-center min-h-[400px]">
                        <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mb-6 animate-bounce-slow">
                            <i class="fas fa-hand-pointer text-3xl text-indigo-400"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">เลือกหมวดหมู่</h3>
                        <p class="text-slate-500">คลิกที่การ์ดทางซ้ายมือ<br>เพื่อดูรายละเอียดและเริ่มงานตรวจนับ</p>
                    </div>

                    {{-- ACTIVE DASHBOARD --}}
                    <div id="active-content-panel" class="hidden space-y-5 animate-fade-in-up">
                        
                        {{-- Dashboard Card --}}
                        <div class="bg-slate-900 rounded-3xl shadow-xl shadow-slate-900/20 overflow-hidden text-white relative border border-slate-800">
                            {{-- Glow Effect --}}
                            <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-600/30 rounded-full blur-3xl -mt-10 -mr-10 pointer-events-none" id="dashboard-glow"></div>

                            <div class="p-6 relative z-10">
                                <div class="flex justify-between items-start mb-6">
                                    <div>
                                        <p class="text-indigo-300 text-xs font-bold uppercase tracking-wider mb-1">Selected Category</p>
                                        <h2 class="text-2xl font-bold truncate pr-4" id="panel-title">Loading...</h2>
                                    </div>
                                    <div class="bg-white/10 backdrop-blur-md px-3 py-1 rounded-lg border border-white/10">
                                        <i class="fas fa-box text-xs mr-1"></i> <span id="panel-total" class="font-bold">0</span>
                                    </div>
                                </div>

                                {{-- TIMER SECTION: 4 BLOCKS --}}
                                <div class="py-2">
                                    <div class="text-xs font-medium text-slate-400 mb-3 text-center uppercase tracking-widest" id="timer-label">TIME REMAINING</div>
                                    
                                    <div class="grid grid-cols-4 gap-2 text-center">
                                        {{-- Days --}}
                                        <div class="bg-white/10 rounded-xl p-2 backdrop-blur-sm border border-white/5">
                                            <span id="t-d" class="text-2xl md:text-3xl font-black block leading-none mb-1">--</span>
                                            <span class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">Days</span>
                                        </div>
                                        {{-- Hours --}}
                                        <div class="bg-white/10 rounded-xl p-2 backdrop-blur-sm border border-white/5">
                                            <span id="t-h" class="text-xl md:text-2xl font-bold block leading-none mb-1 text-indigo-200">00</span>
                                            <span class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">Hrs</span>
                                        </div>
                                        {{-- Minutes --}}
                                        <div class="bg-white/10 rounded-xl p-2 backdrop-blur-sm border border-white/5">
                                            <span id="t-m" class="text-xl md:text-2xl font-bold block leading-none mb-1 text-indigo-200">00</span>
                                            <span class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">Mins</span>
                                        </div>
                                        {{-- Seconds --}}
                                        <div class="bg-white/10 rounded-xl p-2 backdrop-blur-sm border border-white/5">
                                            <span id="t-s" class="text-xl md:text-2xl font-bold block leading-none mb-1 text-indigo-200 w-full">00</span>
                                            <span class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">Secs</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Status Message Box --}}
                                <div id="timer-message-box" class="mt-6 bg-white/5 border border-white/10 rounded-xl p-3 flex items-center justify-center gap-3">
                                    <span id="timer-icon" class="text-2xl"></span>
                                    <span id="timer-text" class="text-sm font-medium">Loading status...</span>
                                </div>
                            </div>
                        </div>

                        {{-- Action Form --}}
                        <div class="bg-white rounded-3xl shadow-lg border border-slate-100 p-6 relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-500 to-violet-500"></div>
                            <h3 class="font-bold text-slate-800 mb-5 flex items-center gap-2 text-lg">
                                <i class="fas fa-calendar-plus text-indigo-500"></i> เปิดงานตรวจนับใหม่
                            </h3>
                            <form action="{{ route('stock-checks.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="category_id" id="form-category-id">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1 ml-1">ชื่องาน</label>
                                        <div class="relative">
                                            <input type="text" name="name" id="form-name" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 pl-10 text-slate-700 font-medium focus:ring-2 focus:ring-indigo-500 transition-all outline-none" required>
                                            <i class="fas fa-pen absolute left-3.5 top-3.5 text-slate-400"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1 ml-1">วันที่ตรวจนับ</label>
                                        <div class="relative">
                                            <input type="date" name="scheduled_date" id="form-date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 pl-10 text-slate-700 font-medium focus:ring-2 focus:ring-indigo-500 transition-all outline-none" required>
                                            <i class="fas fa-calendar absolute left-3.5 top-3.5 text-slate-400"></i>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="w-full mt-6 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-indigo-500/30 transition-all transform hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2 group">
                                    <span>ยืนยันสร้างงาน</span>
                                    <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .animate-bounce-slow { animation: bounce 3s infinite; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    input[type="date"]::-webkit-calendar-picker-indicator { background: transparent; bottom: 0; color: transparent; cursor: pointer; height: auto; left: 0; position: absolute; right: 0; top: 0; width: auto; }
</style>
@endsection

@push('scripts')
<script>
    const TOTAL_DAYS = 105;
    let selectedPanelInterval;

    function parseDateSafe(dateStr) {
        if (!dateStr || dateStr === '-' || dateStr.includes('ไม่เคย')) return null;
        const isoStr = dateStr.replace(' ', 'T'); 
        const date = new Date(isoStr);
        return isNaN(date.getTime()) ? null : date;
    }

    // 🔥 1. GLOBAL TIMER LOOP FOR CARDS
    function updateAllCardTimers() {
        const now = new Date();
        document.querySelectorAll('.card-live-timer').forEach(el => {
            const lastCheckStr = el.dataset.lastCheck;
            
            if (!lastCheckStr || lastCheckStr === '-') {
                // el.innerHTML = '<span class="font-sans">New Item</span>';
                return; // Skip new items
            }

            const lastDate = parseDateSafe(lastCheckStr);
            if(!lastDate) return;

            const deadline = new Date(lastDate);
            deadline.setDate(deadline.getDate() + TOTAL_DAYS);
            
            const distance = deadline - now;
            const absDist = Math.abs(distance);
            
            const days = Math.floor(absDist / (1000 * 60 * 60 * 24));
            const hours = Math.floor((absDist % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((absDist % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((absDist % (1000 * 60)) / 1000);
            
            const timeStr = `${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`;

            if(distance < 0) {
                // Overdue: Show negative or count up style
                el.innerHTML = `-${days}d ${timeStr}`;
            } else {
                // Normal
                el.innerHTML = `${days}d ${timeStr}`;
            }
        });
    }

    // Run card timers every second
    setInterval(updateAllCardTimers, 1000);
    updateAllCardTimers(); // Run once immediately

    // 🔥 2. SELECTION LOGIC
    function selectCategory(el) {
        // UI Selection
        document.querySelectorAll('.category-card').forEach(c => {
            c.classList.remove('ring-2', 'ring-indigo-500', 'border-transparent');
            c.querySelector('.active-ring').classList.remove('opacity-100', 'scale-100');
            c.querySelector('.active-ring').classList.add('scale-95');
        });
        el.querySelector('.active-ring').classList.add('opacity-100', 'scale-100');
        el.querySelector('.active-ring').classList.remove('scale-95');

        // Data & Panels
        const data = el.dataset;
        const isAll = data.id === '';
        
        document.getElementById('empty-state-panel').classList.add('hidden');
        document.getElementById('active-content-panel').classList.remove('hidden');
        
        document.getElementById('panel-title').innerText = data.name;
        document.getElementById('panel-total').innerText = data.total;
        
        // Form Setup
        document.getElementById('form-category-id').value = data.id;
        document.getElementById('form-name').value = isAll 
            ? `ตรวจนับรวม ประจำเดือน ${new Date().toLocaleDateString('th-TH', { month: 'long' })}`
            : `ตรวจนับ ${data.name} (รอบใหม่)`;
        const today = new Date();
        document.getElementById('form-date').value = today.toISOString().split('T')[0];

        // Panel Timer Logic
        if (selectedPanelInterval) clearInterval(selectedPanelInterval);

        // ✅ [FIX] ถ้ามีวันที่ให้ใช้ logic เดียวกันหมด ไม่ว่าจะเป็น All หรือ Category
        const lastDate = parseDateSafe(data.last);
        if (lastDate) {
            startPanelCountdown(lastDate);
        } else {
            // ถ้าไม่มีวันที่จริงๆ ค่อยใช้ Generic
             if (isAll) renderGenericStatus();
             else startPanelCountdown(null);
        }
        
        if(window.innerWidth < 1024) document.getElementById('active-content-panel').scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    function renderGenericStatus() {
        setDashboardTheme('neutral');
        updateTimeBlocks('∞', '--', '--', '--');
        document.getElementById('timer-label').innerText = 'GLOBAL STATUS';
        document.getElementById('timer-icon').innerText = '📦';
        document.getElementById('timer-text').innerText = 'ยังไม่เคยมีการตรวจนับแบบรวมทั้งหมด';
    }

    function startPanelCountdown(lastCheckDate) {
        if (!lastCheckDate) {
            setDashboardTheme('neutral');
            updateTimeBlocks('-', '-', '-', '-');
            document.getElementById('timer-label').innerText = 'NEW ITEM';
            document.getElementById('timer-icon').innerText = '🆕';
            document.getElementById('timer-text').innerText = 'ยังไม่เคยตรวจนับ กรุณาเริ่มรอบแรก';
            return;
        }

        const deadline = new Date(lastCheckDate);
        deadline.setDate(deadline.getDate() + TOTAL_DAYS);

        const update = () => {
            const now = new Date();
            const distance = deadline - now;
            
            const absDist = Math.abs(distance);
            const days = Math.floor(absDist / (1000 * 60 * 60 * 24));
            const hours = Math.floor((absDist % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((absDist % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((absDist % (1000 * 60)) / 1000);
            
            updateTimeBlocks(
                days, 
                String(hours).padStart(2,'0'), 
                String(minutes).padStart(2,'0'), 
                String(seconds).padStart(2,'0')
            );

            if (distance < 0) {
                setDashboardTheme('critical');
                document.getElementById('timer-label').innerText = 'OVERDUE (เกินกำหนดมาแล้ว)';
                document.getElementById('timer-icon').innerText = '🚨';
                document.getElementById('timer-text').innerText = `เกินกำหนดมาแล้ว ${days} วัน (นับเวลาเกิน)`;
            } else if (distance < 1296000000) {
                setDashboardTheme('warning');
                document.getElementById('timer-label').innerText = 'TIME REMAINING (เหลือเวลา)';
                document.getElementById('timer-icon').innerText = '⚠️';
                document.getElementById('timer-text').innerText = 'ใกล้ครบกำหนด ควรวางแผนนับเร็วๆ นี้';
            } else {
                setDashboardTheme('normal');
                document.getElementById('timer-label').innerText = 'TIME REMAINING (เหลือเวลา)';
                document.getElementById('timer-icon').innerText = '✅';
                document.getElementById('timer-text').innerText = 'ยังอยู่ในระยะเวลาที่กำหนด';
            }
        };

        update();
        selectedPanelInterval = setInterval(update, 1000);
    }

    function updateTimeBlocks(d, h, m, s) {
        document.getElementById('t-d').innerText = d;
        document.getElementById('t-h').innerText = h;
        document.getElementById('t-m').innerText = m;
        document.getElementById('t-s').innerText = s;
    }

    function setDashboardTheme(type) {
        const glow = document.getElementById('dashboard-glow');
        const box = document.getElementById('timer-message-box');
        
        box.className = 'mt-6 border rounded-xl p-3 flex items-center justify-center gap-3 transition-colors duration-500';
        
        const daysText = document.getElementById('t-d');
        const timeTexts = [document.getElementById('t-h'), document.getElementById('t-m'), document.getElementById('t-s')];

        if (type === 'critical') {
            glow.className = 'absolute top-0 right-0 w-64 h-64 bg-rose-600/40 rounded-full blur-3xl -mt-10 -mr-10 pointer-events-none transition-all duration-500';
            box.classList.add('bg-rose-500/10', 'border-rose-500/20', 'text-rose-200');
            daysText.className = 'text-2xl md:text-3xl font-black block leading-none mb-1 text-rose-400';
            timeTexts.forEach(el => el.classList.remove('text-indigo-200', 'text-amber-200', 'text-emerald-200'));
            timeTexts.forEach(el => el.classList.add('text-rose-200'));
        } else if (type === 'warning') {
            glow.className = 'absolute top-0 right-0 w-64 h-64 bg-amber-500/40 rounded-full blur-3xl -mt-10 -mr-10 pointer-events-none transition-all duration-500';
            box.classList.add('bg-amber-500/10', 'border-amber-500/20', 'text-amber-200');
            daysText.className = 'text-2xl md:text-3xl font-black block leading-none mb-1 text-amber-400';
            timeTexts.forEach(el => el.classList.remove('text-indigo-200', 'text-rose-200', 'text-emerald-200'));
            timeTexts.forEach(el => el.classList.add('text-amber-200'));
        } else if (type === 'normal') {
            glow.className = 'absolute top-0 right-0 w-64 h-64 bg-emerald-500/30 rounded-full blur-3xl -mt-10 -mr-10 pointer-events-none transition-all duration-500';
            box.classList.add('bg-emerald-500/10', 'border-emerald-500/20', 'text-emerald-200');
            daysText.className = 'text-2xl md:text-3xl font-black block leading-none mb-1 text-emerald-400';
            timeTexts.forEach(el => el.classList.remove('text-indigo-200', 'text-rose-200', 'text-amber-200'));
            timeTexts.forEach(el => el.classList.add('text-emerald-200'));
        } else {
            glow.className = 'absolute top-0 right-0 w-64 h-64 bg-indigo-600/30 rounded-full blur-3xl -mt-10 -mr-10 pointer-events-none transition-all duration-500';
            box.classList.add('bg-white/10', 'border-white/10', 'text-slate-300');
            daysText.className = 'text-2xl md:text-3xl font-black block leading-none mb-1 text-white';
            timeTexts.forEach(el => el.classList.remove('text-rose-200', 'text-amber-200', 'text-emerald-200'));
            timeTexts.forEach(el => el.classList.add('text-indigo-200'));
        }
    }
</script>
@endpush