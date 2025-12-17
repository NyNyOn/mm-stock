@extends('layouts.app')
@section('header', '🏠 Dashboard')
@section('subtitle', 'ภาพรวมระบบจัดการสต๊อกอุปกรณ์ IT')

@section('content')
<div id="dashboard-page" class="page animate-slide-up-soft">

    {{-- ========================================================= --}}
    {{-- 1. ส่วนการ์ดต้อนรับ (Welcome Cards) --}}
    {{-- ========================================================= --}}
    @auth
        @php
            $superAdminId = (int)config('app.super_admin_id', 9); 
            $userGroupSlug = Auth::user()->serviceUserRole?->userGroup?->slug;
        @endphp

        {{-- 1.1 Super Admin --}}
        @if(Auth::user()->id === $superAdminId)
            <div class="flex items-center p-6 mb-6 space-x-6 bg-gradient-to-r from-blue-50 to-cyan-50 soft-card rounded-2xl gentle-shadow soft-hover animate-slide-up-soft">
                <div class="text-5xl text-yellow-400">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="flex-grow">
                    <h2 class="text-2xl font-bold gradient-text-soft">
                        ยินดีต้อนรับกลับมา, {{ Auth::user()->fullname }}! (Super Admin)
                    </h2>
                    <p class="mt-1 text-gray-600">
                        ท่านผู้สร้างระบบ! คุณมีสิทธิ์เข้าถึงทุกฟังก์ชัน
                    </p>
                </div>
            </div>

        {{-- 1.2 IT & Admin --}}
        @elseif($userGroupSlug && in_array(strtolower($userGroupSlug), ['it', 'admin', 'administrator', 'administartor']))
            <div class="flex items-center p-6 mb-6 space-x-6 bg-gradient-to-r from-green-50 to-emerald-50 soft-card rounded-2xl gentle-shadow soft-hover animate-slide-up-soft">
                <div class="text-5xl text-green-400">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="flex-grow">
                    <h2 class="text-2xl font-bold gradient-text-soft">
                        ยินดีต้อนรับ, {{ Auth::user()->fullname }}!
                    </h2>
                    <p class="mt-1 text-gray-600">
                        คุณอยู่ในกลุ่มผู้ดูแลระบบ ขอให้เป็นวันที่ดีกับการทำงาน!
                    </p>
                </div>
                <div>
                    <a href="{{ route('management.users.index') }}"
                       class="px-5 py-3 text-sm font-medium text-blue-700 whitespace-nowrap bg-gradient-to-br from-blue-100 to-blue-200 transition-all rounded-xl hover:shadow-lg button-soft gentle-shadow">
                        <i class="mr-2 fas fa-users-cog"></i>
                        <span>จัดการผู้ใช้</span>
                    </a>
                </div>
            </div>

        {{-- 1.3 General User --}}
        @else
            <div class="p-6 mb-6 soft-card rounded-2xl stat-card gentle-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="mb-3 text-2xl font-bold gradient-text-soft">🌸 ยินดีต้อนรับสู่ IT Stock Pro</h1>
                        <p class="mb-4 text-sm leading-relaxed text-gray-600">ระบบจัดการสต๊อกอุปกรณ์ IT แบบ Smart & Modern</p>
                        <div class="flex items-center space-x-5">
                            <div class="flex items-center space-x-2"><div class="w-3 h-3 bg-green-400 rounded-full animate-bounce-gentle"></div><span class="text-sm font-bold text-green-600">🟢 ระบบพร้อมใช้งาน</span></div>
                            <div class="flex items-center space-x-2"><i class="text-sm text-gray-400 fas fa-clock"></i><span class="text-sm text-gray-500">อัปเดตล่าสุด: {{ now()->format('d/m/Y H:i') }}</span></div>
                        </div>
                    </div>
                    <div class="hidden lg:block"><div class="flex items-center justify-center w-24 h-24 bg-gradient-to-br from-blue-100 to-purple-100 rounded-2xl animate-float-soft gentle-shadow"><i class="text-2xl text-blue-500 fas fa-chart-line animate-bounce-gentle"></i></div></div>
                </div>
            </div>
        @endif
    @endauth

    {{-- ========================================================= --}}
    {{-- 2. Stat Cards Grid --}}
    {{-- ========================================================= --}}
    <div class="grid grid-cols-1 gap-6 mb-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="flex items-start justify-between p-5 bg-white shadow-sm rounded-2xl">
            <div><p class="flex items-center text-sm font-medium text-gray-500"><i class="mr-2 text-gray-400 fas fa-box-open"></i>อุปกรณ์ทั้งหมด</p><p class="mt-2 text-3xl font-bold text-indigo-600">{{ number_format($total_equipment ?? 0, 0) }}</p></div>
            <div class="p-3 bg-blue-100 rounded-xl animate-float-soft"><i class="text-lg text-blue-500 fas fa-cubes"></i></div>
        </div>
        <a href="{{ route('reports.index', ['report_type' => 'low_stock']) }}" class="block p-5 bg-white transition-colors shadow-sm rounded-2xl hover:bg-orange-50">
            <div class="flex items-start justify-between">
                <div><p class="flex items-center text-sm font-medium text-gray-500"><i class="mr-2 text-gray-400 fas fa-exclamation-triangle"></i>สต็อกต่ำ</p><p class="mt-2 text-3xl font-bold text-orange-500">{{ number_format($low_stock_count ?? 0, 0) }}</p></div>
                <div class="p-3 bg-orange-100 rounded-xl animate-float-soft"><i class="text-lg text-orange-500 fas fa-exclamation-triangle"></i></div>
            </div>
        </a>
        <a href="{{ route('purchase-track.index') }}" class="block p-5 bg-white transition-colors shadow-sm rounded-2xl hover:bg-sky-50">
            <div class="flex items-start justify-between">
                <div><p class="flex items-center text-sm font-medium text-gray-500"><i class="mr-2 text-gray-400 fas fa-truck-loading"></i>กำลังสั่งซื้อ</p><p class="mt-2 text-3xl font-bold text-sky-500">{{ number_format($on_order_count ?? 0, 0) }}</p></div>
                <div class="p-3 bg-sky-100 rounded-xl animate-float-soft"><i class="text-lg fas fa-shipping-fast text-sky-500"></i></div>
            </div>
        </a>
        <a href="{{ route('reports.index', ['report_type' => 'warranty']) }}" class="block p-5 bg-white transition-colors shadow-sm rounded-2xl hover:bg-purple-50">
            <div class="flex items-start justify-between">
                <div><p class="flex items-center text-sm font-medium text-gray-500"><i class="mr-2 text-gray-400 fas fa-calendar-times"></i>ใกล้หมดประกัน</p><p class="mt-2 text-3xl font-bold text-purple-500">{{ number_format($warranty_count ?? 0, 0) }}</p></div>
                <div class="p-3 bg-purple-100 rounded-xl animate-float-soft"><i class="text-lg text-purple-500 fas fa-calendar-times"></i></div>
            </div>
        </a>
        
        <a href="{{ route('purchase-orders.index') }}" class="block p-5 bg-white transition-colors shadow-sm rounded-2xl hover:bg-red-50">
            <div class="flex items-start justify-between">
                <div><p class="flex items-center text-sm font-medium text-gray-500"><i class="mr-2 text-gray-400 fas fa-bolt"></i>สั่งซื้อด่วน</p><p class="mt-2 text-3xl font-bold text-red-500">{{ number_format($urgent_order_count ?? 0, 0) }}</p></div>
                <div class="p-3 bg-red-100 rounded-xl animate-float-soft"><i class="text-lg text-red-500 fas fa-bolt"></i></div>
            </div>
        </a>
        <a href="{{ route('purchase-orders.index') }}" class="block p-5 bg-white transition-colors shadow-sm rounded-2xl hover:bg-cyan-50">
            <div class="flex items-start justify-between">
                <div><p class="flex items-center text-sm font-medium text-gray-500"><i class="mr-2 text-gray-400 fas fa-calendar-alt"></i>สั่งซื้อตามรอบ</p><p class="mt-2 text-3xl font-bold text-cyan-500">{{ number_format($scheduled_order_count ?? 0, 0) }}</p></div>
                <div class="p-3 bg-cyan-100 rounded-xl animate-float-soft"><i class="text-lg fas fa-calendar-alt text-cyan-500"></i></div>
            </div>
        </a>
        <a href="{{ route('receive.index') }}" class="block p-5 bg-white transition-colors shadow-sm rounded-2xl hover:bg-teal-50">
            <div class="flex items-start justify-between">
                <div><p class="flex items-center text-sm font-medium text-gray-500"><i class="mr-2 text-gray-400 fas fa-hourglass-half"></i>รอตรวจสอบ</p><p class="mt-2 text-3xl font-bold text-teal-500">{{ number_format($pending_transactions_count ?? 0, 0) }}</p></div>
                <div class="p-3 bg-teal-100 rounded-xl animate-float-soft"><i class="text-lg text-teal-500 fas fa-hourglass-half"></i></div>
            </div>
        </a>
        <a href="{{ route('purchase-orders.index') }}" class="block p-5 bg-white transition-colors shadow-sm rounded-2xl hover:bg-gray-50">
             <div class="flex items-start justify-between">
                <div><p class="flex items-center text-sm font-medium text-gray-500"><i class="mr-2 text-gray-400 fas fa-tools"></i>สั่งซื้อตาม Job</p><p class="mt-2 text-3xl font-bold text-gray-500">{{ number_format($job_order_count ?? 0, 0) }}</p></div>
                <div class="p-3 bg-gray-100 rounded-xl animate-float-soft"><i class="text-lg text-gray-500 fas fa-tools"></i></div>
            </div>
        </a>
    </div>

    {{-- ========================================================= --}}
    {{-- 3. Chart Area (Modern Redesign - Fixed Grouping) --}}
    {{-- ========================================================= --}}
    <div class="p-6 mb-6 bg-white soft-card rounded-2xl gentle-shadow">
        {{-- Header & Controls --}}
        <div class="flex flex-col justify-between mb-6 md:flex-row md:items-center gap-y-4">
            <div>
                <h3 class="text-xl font-bold text-gray-800">📊 สถิติการเบิก-จ่ายพัสดุ</h3>
                <p class="text-sm text-gray-500">แยกตามประเภทการทำรายการรายเดือน</p>
            </div>
            
            {{-- Custom Legend / Series Toggles --}}
            <div id="chartSeriesToggle" class="flex flex-wrap items-center gap-2">
                <label class="px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg cursor-pointer hover:bg-green-100 transition-all select-none">
                    <input type="checkbox" value="received" class="hidden chart-series-checkbox" checked> 
                    <span class="flex items-center"><span class="w-2 h-2 mr-2 bg-green-500 rounded-full"></span>รับเข้า</span>
                </label>
                <label class="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg cursor-pointer hover:bg-red-100 transition-all select-none">
                    <input type="checkbox" value="withdrawn" class="hidden chart-series-checkbox" checked> 
                    <span class="flex items-center"><span class="w-2 h-2 mr-2 bg-red-500 rounded-full"></span>เบิกออก</span>
                </label>
                <label class="px-3 py-1.5 text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg cursor-pointer hover:bg-yellow-100 transition-all select-none">
                    <input type="checkbox" value="borrowed" class="hidden chart-series-checkbox" checked> 
                    <span class="flex items-center"><span class="w-2 h-2 mr-2 bg-yellow-500 rounded-full"></span>ยืม</span>
                </label>
                <label class="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg cursor-pointer hover:bg-blue-100 transition-all select-none">
                    <input type="checkbox" value="returned" class="hidden chart-series-checkbox"> 
                    <span class="flex items-center"><span class="w-2 h-2 mr-2 bg-blue-500 rounded-full"></span>คืน</span>
                </label>

                {{-- 🔥 ปุ่มสำหรับเรียก Modal ตั้งค่าสี --}}
                <button 
                    onclick="openColorSettingsModal()" 
                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-200 transition-all select-none whitespace-nowrap"
                    title="กำหนดค่าสีของกราฟด้วยตัวเอง">
                    <i class="fas fa-palette mr-1"></i> ตั้งค่าสี
                </button>
            </div>
        </div>

        {{-- Filters --}}
        <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-12">
            <div class="sm:col-span-2">
                <select id="chartYearSelect" class="w-full text-sm border-gray-200 rounded-xl bg-gray-50">
                    @forelse($available_years as $year)
                        <option value="{{ $year }}" @if($year == now()->year) selected @endif>ปี {{ $year + 543 }}</option>
                    @empty
                        <option value="{{ now()->year }}">ปี {{ now()->year + 543 }}</option>
                    @endforelse
                </select>
            </div>
            <div class="sm:col-span-4">
                <select id="chartCategorySelect" class="w-full text-sm border-gray-200 rounded-xl bg-gray-50">
                    <option value="">📂 ทุกหมวดหมู่</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-6">
                <select id="chartEquipmentSelect" class="w-full"></select>
            </div>
        </div>

        {{-- Chart Canvas --}}
        <div class="relative w-full h-80">
            <canvas id="mainDashboardChart"></canvas>
        </div>
    </div>

    {{-- Lists: Activities & Alerts --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        {{-- ========================================================= --}}
        {{-- 4. Left Column: Activities --}}
        {{-- ========================================================= --}}
        <div class="p-5 lg:col-span-2 soft-card rounded-2xl stat-card gentle-shadow flex flex-col h-full">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800">⚡ กิจกรรมล่าสุด</h3>
                <a href="{{ route('transactions.index') }}" class="text-sm font-medium text-blue-600 hover:underline">ดูทั้งหมด →</a>
            </div>
            <div class="space-y-3 flex-grow">
                @forelse ($recent_activities as $tx)
                    @php
                        $details = match($tx->type) {
                            'receive'   => ['icon' => 'fa-plus', 'color' => 'green', 'title' => 'เพิ่มสต็อกใหม่'],
                            'withdraw'  => ['icon' => 'fa-minus', 'color' => 'red', 'title' => 'เบิกอุปกรณ์'],
                            'borrow'    => ['icon' => 'fa-tag', 'color' => 'yellow', 'title' => 'ยืมอุปกรณ์'],
                            'return'    => ['icon' => 'fa-undo-alt', 'color' => 'blue', 'title' => 'รับคืนอุปกรณ์'],
                            'adjust'    => ['icon' => 'fa-sliders-h', 'color' => 'gray', 'title' => 'ปรับสต็อก'],
                            'consumable' => ['icon' => 'fa-box-open', 'color' => 'red', 'title' => 'เบิก (ไม่ต้องคืน)'],
                            'returnable' => ['icon' => 'fa-hand-holding-heart', 'color' => 'yellow', 'title' => 'ยืม (ต้องคืน)'],
                            'partial_return' => ['icon' => 'fa-recycle', 'color' => 'red', 'title' => 'เบิก (เหลือคืนได้)'],
                            default     => ['icon' => 'fa-info-circle', 'color' => 'gray', 'title' => ucfirst($tx->type)]
                        };
                        $colorClasses = ['green' => 'bg-green-100 text-green-600', 'red' => 'bg-red-100 text-red-600', 'yellow' => 'bg-yellow-100 text-yellow-600', 'blue' => 'bg-blue-100 text-blue-600', 'gray' => 'bg-gray-100 text-gray-600'][$details['color']];
                        $qtyColor = $tx->quantity_change > 0 ? 'text-green-600' : 'text-red-600';
                        $qtySign = $tx->quantity_change > 0 ? '+' : '';
                    @endphp
                    <div class="flex items-center p-3 space-x-4 transition-colors duration-200 rounded-xl hover:bg-gray-50">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-full {{ $colorClasses }}"><i class="fas {{ $details['icon'] }}"></i></div>
                        <div class="flex-grow min-w-0">
                            <p class="text-sm font-bold text-gray-800">{{ $details['title'] }}</p>
                            <p class="text-xs text-gray-600 truncate">{{ optional($tx->equipment)->name }} (<strong class="{{ $qtyColor }}">{{ $qtySign }}{{ $tx->quantity_change }}</strong> ชิ้น)</p>
                            <p class="text-gray-400" style="font-size: 10px;">โดย {{ optional($tx->user)->fullname ?? 'System' }}</p>
                        </div>
                        <div class="flex-shrink-0 font-medium text-gray-400 whitespace-nowrap" style="font-size: 10px;">{{ optional($tx->transaction_date)->diffForHumans() }}</div>
                    </div>
                @empty
                    <p class="py-8 text-sm text-center text-gray-500">ยังไม่มีกิจกรรมล่าสุด</p>
                @endforelse
            </div>
            @if ($recent_activities && $recent_activities->hasPages())
                 <div class="pt-4 mt-auto border-t border-gray-100">{{ $recent_activities->links() }}</div>
            @endif
        </div>

        {{-- ========================================================= --}}
        {{-- 5. Right Column --}}
        {{-- ========================================================= --}}
        <div class="space-y-6">

            {{-- 5.1 รอบการนับสต๊อก (REDESIGNED: Clean White + Red Gradient + 3D) --}}
            <div class="p-5 bg-white soft-card rounded-2xl gentle-shadow border border-gray-100 relative overflow-hidden">
                {{-- Decorative Header Accent --}}
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-gray-200 via-red-300 to-red-500"></div>
                
                <h3 class="mb-4 text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-clipboard-list mr-2 text-indigo-500"></i> รอบการนับสต๊อก (105 วัน)
                </h3>
                
                <div class="space-y-4 overflow-y-auto max-h-72 scrollbar-soft pr-2">
                    @forelse($stockCycles as $cycle)
                        @php
                            $totalCycleDays = 105;
                            $daysElapsed = $totalCycleDays - $cycle->days_left;
                            $progressPercent = min(100, max(0, ($daysElapsed / $totalCycleDays) * 100));
                            $daysLeftInt = (int)$cycle->days_left;
                            
                            // 🔥 DESIGN LOGIC: Remove Green -> White -> Red Gradient
                            $statusConfig = match($cycle->status) {
                                'locked' => [
                                    'icon' => 'fas fa-lock',
                                    'icon_bg' => 'bg-red-100',
                                    'icon_text' => 'text-red-600',
                                    'border_left' => 'border-l-4 border-red-500',
                                    'progress_gradient' => 'bg-gradient-to-r from-red-500 to-red-700', // แดงเข้มสุด
                                    'status_text' => 'ระงับการเบิก',
                                    'badge_class' => 'bg-red-100 text-red-700'
                                ],
                                'warning' => [
                                    'icon' => 'fas fa-exclamation-triangle',
                                    'icon_bg' => 'bg-orange-100',
                                    'icon_text' => 'text-orange-600',
                                    'border_left' => 'border-l-4 border-orange-400',
                                    'progress_gradient' => 'bg-gradient-to-r from-orange-400 via-red-400 to-red-500', // ส้มไปแดง
                                    'status_text' => 'ใกล้ครบกำหนด',
                                    'badge_class' => 'bg-orange-100 text-orange-700'
                                ],
                                default => [ // Safe (Formerly Green -> Now Clean White/Blue)
                                    'icon' => 'fas fa-shield-alt',
                                    'icon_bg' => 'bg-gray-100', // พื้นหลังไอคอนสีขาว/เทา
                                    'icon_text' => 'text-blue-500', // ไอคอนสีฟ้า (สบายตา)
                                    'border_left' => 'border-l-4 border-blue-400',
                                    'progress_gradient' => 'bg-gradient-to-r from-blue-300 to-indigo-400', // ฟ้าไปม่วงอ่อน (ไม่ใช้เขียว)
                                    'status_text' => 'ปกติ',
                                    'badge_class' => 'bg-gray-100 text-gray-600'
                                ]
                            };

                            $progressText = $daysLeftInt > 0 ? "เหลืออีก {$daysLeftInt} วัน" : ($daysLeftInt == 0 ? "ครบกำหนดวันนี้" : "เลยมา " . abs($daysLeftInt) . " วัน");
                        @endphp

                        {{-- Card Item --}}
                        <div class="bg-white rounded-xl shadow-[0_3px_10px_rgba(0,0,0,0.08)] border border-gray-100 p-4 transition-all hover:-translate-y-1 hover:shadow-lg relative overflow-hidden group {{ $statusConfig['border_left'] }}">
                            
                            {{-- Header: Icon + Name --}}
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg {{ $statusConfig['icon_bg'] }} flex items-center justify-center mr-3 shadow-inner">
                                        <i class="{{ $statusConfig['icon'] }} {{ $statusConfig['icon_text'] }} text-lg"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-gray-800 line-clamp-1 group-hover:text-indigo-600 transition-colors" title="{{ $cycle->name }}">
                                            {{ $cycle->name }}
                                        </h4>
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $statusConfig['badge_class'] }}">
                                            {{ $statusConfig['status_text'] }}
                                        </span>
                                    </div>
                                </div>
                                <a href="{{ route('stock-checks.create') }}" class="text-gray-400 hover:text-indigo-600 transition-colors">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>

                            {{-- Progress Bar Container --}}
                            <div class="space-y-1 mb-3">
                                <div class="flex justify-between text-xs font-medium text-gray-500">
                                    <span>ความคืบหน้า</span>
                                    <span class="{{ $statusConfig['icon_text'] }}">{{ number_format($progressPercent, 0) }}%</span>
                                </div>
                                <div class="h-2.5 w-full bg-gray-100 rounded-full overflow-hidden shadow-inner">
                                    <div class="h-full rounded-full {{ $statusConfig['progress_gradient'] }} shadow-sm transition-all duration-1000 ease-out" 
                                         style="width: {{ $progressPercent }}%"></div>
                                </div>
                            </div>

                            {{-- Footer Info --}}
                            <div class="flex justify-between items-center text-xs text-gray-500 border-t border-gray-50 pt-2 mt-2">
                                <div class="flex items-center">
                                    <i class="fas fa-cubes mr-1.5 opacity-50"></i> {{ $cycle->item_count }} รายการ
                                </div>
                                {{-- Countdown (Using JS Logic) --}}
                                <div class="stock-countdown-display font-mono font-semibold {{ $statusConfig['icon_text'] }}" 
                                     data-target="{{ isset($cycle->next_check_date) ? \Carbon\Carbon::parse($cycle->next_check_date)->timestamp * 1000 : 0 }}">
                                     <i class="fas fa-clock mr-1"></i> {{ $progressText }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-gray-400 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                            <i class="fas fa-clipboard-check text-3xl mb-2 opacity-50"></i>
                            <span class="text-sm">ข้อมูลครบถ้วน ไม่มีรอบตรวจนับคงค้าง</span>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- 5.2 รายการที่กำลังสั่งซื้อ --}}
            <div class="p-5 soft-card rounded-2xl stat-card gentle-shadow">
                <h3 class="mb-4 text-lg font-bold text-gray-800 gradient-text-soft">⏳ รายการที่กำลังสั่งซื้อ</h3>
                <div class="space-y-3 overflow-y-auto max-h-72 scrollbar-soft">
                    @forelse($on_order_items as $item)
                        <div class="p-3 border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl">
                            <p class="text-sm font-bold text-blue-600 truncate">{{ $item->name }}</p>
                        </div>
                    @empty
                        <div class="p-4 text-sm text-center text-gray-500"><i class="mr-2 text-green-500 fas fa-check-circle"></i>ไม่มีรายการที่กำลังสั่งซื้อ</div>
                    @endforelse
                </div>
            </div>
            
            {{-- 5.3 การแจ้งเตือนสำคัญ --}}
            <div class="p-5 soft-card rounded-2xl stat-card gentle-shadow">
                <h3 class="mb-4 text-lg font-bold text-gray-800 gradient-text-soft">🚨 การแจ้งเตือนสำคัญ</h3>
                <div class="space-y-3 overflow-y-auto max-h-72 scrollbar-soft">
                    @if($out_of_stock_items->isEmpty() && $low_stock_items->isEmpty())
                        <div class="p-4 text-sm text-center text-gray-500"><i class="mr-2 text-green-500 fas fa-check-circle"></i>ไม่มีการแจ้งเตือน</div>
                    @endif
                    @foreach($out_of_stock_items as $item)
                        <div class="p-3 border border-red-200 bg-gradient-to-r from-red-50 to-pink-50 rounded-2xl">
                            <p class="text-sm font-bold text-red-600">🚫 สต๊อกหมด: <span class="font-normal text-red-500">{{ $item->name }}</span></p>
                        </div>
                    @endforeach
                    @foreach($low_stock_items as $item)
                        <div class="p-3 border border-orange-200 bg-gradient-to-r from-orange-50 to-yellow-50 rounded-2xl">
                            <p class="text-sm font-bold text-orange-600">⚠️ สต็อกต่ำ: <span class="font-normal text-orange-500">{{ $item->name }} (เหลือ {{ $item->quantity }})</span></p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================= --}}
{{-- 🔥 MODAL สำหรับตั้งค่าสีกราฟ --}}
{{-- ========================================================= --}}
<div id="color-settings-modal" class="fixed inset-0 z-50 items-center justify-center hidden bg-black bg-opacity-50">
    <div class="w-full max-w-lg p-6 bg-white rounded-xl shadow-2xl relative animate-slide-up-soft">
        <h3 class="text-xl font-bold text-gray-800 mb-4 flex justify-between items-center">
            ตั้งค่าสีของกราฟ
            <button onclick="closeColorSettingsModal()" class="text-gray-400 hover:text-gray-700 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            กำหนดโค้ดสี HEX (เช่น #FF0000) สำหรับแต่ละประเภทการทำรายการ
        </p>

        <form id="colorSettingsForm" onsubmit="saveCustomColors(event)">
            @php
                // กำหนดโครงสร้างข้อมูลสีสำหรับ Modal
                $colorFields = [
                    'received' => ['label' => 'รับเข้า', 'badge_color' => 'bg-green-500'],
                    'withdrawn' => ['label' => 'เบิกออก', 'badge_color' => 'bg-red-500'],
                    'borrowed' => ['label' => 'ยืม', 'badge_color' => 'bg-yellow-500'],
                    'returned' => ['label' => 'คืน', 'badge_color' => 'bg-blue-500'],
                ];
            @endphp
            
            @foreach($colorFields as $key => $field)
                <div class="mb-4 p-3 border border-gray-100 rounded-lg bg-gray-50">
                    <div class="flex items-center mb-2">
                        <span class="w-2 h-2 mr-2 rounded-full {{ $field['badge_color'] }}"></span>
                        <label class="text-sm font-bold text-gray-700">{{ $field['label'] }}</label>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label for="{{ $key }}-start" class="block text-xs font-medium text-gray-500">สีอ่อน (Start)</label>
                            <input type="color" id="{{ $key }}-start" name="{{ $key }}-start" class="w-full h-10 p-1 border border-gray-300 rounded-lg cursor-pointer transition-shadow hover:shadow-md" value="">
                        </div>
                        <div>
                            <label for="{{ $key }}-end" class="block text-xs font-medium text-gray-500">สีเข้ม (End)</label>
                            <input type="color" id="{{ $key }}-end" name="{{ $key }}-end" class="w-full h-10 p-1 border border-gray-300 rounded-lg cursor-pointer transition-shadow hover:shadow-md" value="">
                        </div>
                         <div>
                            <label for="{{ $key }}-border" class="block text-xs font-medium text-gray-500">สีขอบ/ตัวเลข (Border)</label>
                            <input type="color" id="{{ $key }}-border" name="{{ $key }}-border" class="w-full h-10 p-1 border border-gray-300 rounded-lg cursor-pointer transition-shadow hover:shadow-md" value="">
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end space-x-3 pt-3 border-t border-gray-100 mt-4">
                <button type="button" onclick="resetCustomColors()" class="text-sm text-red-600 hover:underline">รีเซ็ตเป็นค่าเริ่มต้น</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-md">
                    บันทึกและอัปเดตกราฟ
                </button>
            </div>
        </form>
    </div>
</div>


@endsection

@push('scripts')
    {{-- SweetAlert2 for Popups --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        window.lockedStockCount = {{ $stockCycles->where('status', 'locked')->count() }};
        window.warningStockCount = {{ $stockCycles->where('status', 'warning')->count() }};

        // ========================================================
        // 🎨 CHART COLORS CONFIGURATION (ค่าเริ่มต้นที่ใช้ถ้าไม่มีใน localStorage)
        // แก้ไขโค้ดสี HEX 6 หลัก (เช่น #RRGGBB) หรือชื่อสี CSS 
        // start: สีอ่อนด้านบน, end: สีเข้มด้านล่าง, border: สีเส้นขอบ (และสีตัวเลข)
        // ========================================================
        const DEFAULT_CHART_COLORS = {
            // รับเข้า (Received)
            received:  { start: '#4ade80', end: '#14532d', border: '#15803d' }, 
            
            // เบิก (Withdrawn)
            withdrawn: { 
                start: '#fca5a5',   // สีแดงอ่อน (ด้านบน)
                end: '#991b1b',     // สีแดงเข้ม (ด้านล่าง)
                border: '#ef4444'   // สีขอบและตัวเลข (ทำให้สีแดงเห็นชัดเจน)
            },
            
            // ยืม (Borrowed)
            borrowed:  { start: '#fde047', end: '#713f12', border: '#a16207' },
            
            // คืน (Returned)
            returned:  { start: '#93c5fd', end: '#1e3a8a', border: '#3b82f6' }  
        };
        
        // ฟังก์ชันสำหรับเปิด Modal
        function openColorSettingsModal() {
            const modal = document.getElementById('color-settings-modal');
            const form = document.getElementById('colorSettingsForm');
            
            // 1. โหลดสีปัจจุบันจาก localStorage หรือใช้ค่าเริ่มต้น
            const currentColors = JSON.parse(localStorage.getItem('customChartColors')) || DEFAULT_CHART_COLORS;
            
            // 2. กำหนดค่าใน input fields
            for (const type in currentColors) {
                if (currentColors.hasOwnProperty(type)) {
                    document.getElementById(`${type}-start`).value = currentColors[type].start;
                    document.getElementById(`${type}-end`).value = currentColors[type].end;
                    document.getElementById(`${type}-border`).value = currentColors[type].border;
                }
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // ฟังก์ชันสำหรับปิด Modal
        function closeColorSettingsModal() {
            document.getElementById('color-settings-modal').classList.add('hidden');
            document.getElementById('color-settings-modal').classList.remove('flex');
        }

        // ฟังก์ชันสำหรับบันทึกสีที่กำหนดเอง
        function saveCustomColors(event) {
            event.preventDefault();
            const form = event.target;
            const newColors = {};
            const types = ['received', 'withdrawn', 'borrowed', 'returned'];

            types.forEach(type => {
                newColors[type] = {
                    start: form.elements[`${type}-start`].value,
                    end: form.elements[`${type}-end`].value,
                    border: form.elements[`${type}-border`].value
                };
            });

            localStorage.setItem('customChartColors', JSON.stringify(newColors));
            closeColorSettingsModal();
            
            // 🔥 อัปเดตกราฟทันที
            // ต้องเรียกใช้ fetchAndRenderChart() ซึ่งอยู่ใน dashboard.js
            if (typeof fetchAndRenderChart === 'function') {
                fetchAndRenderChart();
            } else {
                 console.warn("fetchAndRenderChart function is not available yet. Please reload the page.");
                 window.location.reload();
            }
        }

        // ฟังก์ชันสำหรับรีเซ็ตสีเป็นค่าเริ่มต้น
        function resetCustomColors() {
            localStorage.removeItem('customChartColors');
            closeColorSettingsModal();
            
            // 🔥 อัปเดตกราฟทันที
            if (typeof fetchAndRenderChart === 'function') {
                fetchAndRenderChart();
            } else {
                 window.location.reload();
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
    {{-- 🔥 โหลด dashboard.js ทีหลัง เพื่อให้ฟังก์ชันข้างบนเรียกใช้ได้ --}}
    <script src="{{ asset('js/dashboard.js') }}"></script>
@endpush