@extends('layouts.app')
@section('header', '🏠 Dashboard')
@section('subtitle', 'ภาพรวมระบบจัดการสต๊อกอุปกรณ์ IT')

@section('content')
<div id="dashboard-page" class="page animate-slide-up-soft">

    {{-- การ์ดต้อนรับ --}}
    @auth
        @php
            $superAdminId = (int)config('app.super_admin_id', 9); 
            $userGroupSlug = Auth::user()->serviceUserRole?->userGroup?->slug;
        @endphp

        {{-- 1. Super Admin --}}
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

        {{-- 2. การ์ดต้อนรับสำหรับ IT และ Admin ทั่วไป --}}
        {{-- ✅ รองรับทั้ง administrator (ถูก) และ administartor (เผื่อพิมพ์ผิดใน DB) --}}
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

        {{-- 3. User ทั่วไป --}}
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

    {{-- Stat Cards Grid --}}
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

    {{-- Chart Area --}}
    <div class="p-5 mb-6 soft-card rounded-2xl stat-card gentle-shadow">
        <div class="flex flex-col items-start justify-between gap-4 mb-4 md:flex-row">
            <h3 class="text-lg font-bold text-gray-800">📊 ภาพรวมการเคลื่อนไหวสต๊อกรายเดือน</h3>
            <div class="flex flex-col items-center w-full gap-2 md:w-auto md:flex-row">
                <div id="chartSeriesToggle" class="flex flex-wrap justify-start gap-x-4 gap-y-2">
                    <label class="flex items-center space-x-2 text-sm cursor-pointer"><input type="checkbox" value="received" class="chart-series-checkbox" checked> <span class="px-2 py-1 text-xs text-white bg-green-500 rounded">รับเข้า</span></label>
                    <label class="flex items-center space-x-2 text-sm cursor-pointer"><input type="checkbox" value="withdrawn" class="chart-series-checkbox" checked> <span class="px-2 py-1 text-xs text-white bg-red-500 rounded">เบิก</span></label>
                    <label class="flex items-center space-x-2 text-sm cursor-pointer"><input type="checkbox" value="borrowed" class="chart-series-checkbox" checked> <span class="px-2 py-1 text-xs text-white bg-yellow-500 rounded">ยืม</span></label>
                    <label class="flex items-center space-x-2 text-sm cursor-pointer"><input type="checkbox" value="returned" class="chart-series-checkbox"> <span class="px-2 py-1 text-xs text-white bg-blue-500 rounded">คืน</span></label>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-4 mb-4 sm:grid-cols-3">
            <select id="chartYearSelect" class="w-full px-2 py-1 border rounded-md border-gray-200 focus:ring-blue-500 focus:border-blue-500">
                @forelse($available_years as $year)
                    <option value="{{ $year }}" @if($year == now()->year) selected @endif>ปี {{ $year + 543 }}</option>
                @empty
                    <option value="{{ now()->year }}">ปี {{ now()->year + 543 }}</option>
                @endforelse
            </select>
            <select id="chartCategorySelect" class="w-full px-2 py-1 border rounded-md border-gray-200 focus:ring-blue-500 focus:border-blue-500">
                <option value="">ทุกหมวดหมู่</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <select id="chartEquipmentSelect" class="w-full"></select>
        </div>
        <div class="relative h-80"><canvas id="mainDashboardChart"></canvas></div>
    </div>

    {{-- Lists: Activities & Alerts --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        {{-- Left Column: Activities --}}
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

        {{-- Right Column --}}
        <div class="space-y-6">

            {{-- รอบการนับสต๊อก (105 วัน) --}}
            <div class="p-5 soft-card rounded-2xl stat-card gentle-shadow">
                <h3 class="mb-4 text-lg font-bold text-gray-800 gradient-text-soft">📋 รอบการนับสต๊อก (105 วัน)</h3>
                <div class="space-y-3 overflow-y-auto max-h-72 scrollbar-soft">
                    @forelse($stockCycles as $cycle)
                        @php
                            $theme = match($cycle->status) {
                                'locked' => [
                                    'border' => 'border-red-300',
                                    'bg' => 'bg-gradient-to-r from-red-50 to-rose-100',
                                    'text_head' => 'text-red-700',
                                    'text_sub' => 'text-red-500',
                                    'icon' => 'text-red-500',
                                    'msg' => '⚠️ ระงับการใช้งาน (เลยกำหนด)'
                                ],
                                'warning' => [
                                    'border' => 'border-orange-300',
                                    'bg' => 'bg-gradient-to-r from-amber-50 to-orange-100',
                                    'text_head' => 'text-orange-700',
                                    'text_sub' => 'text-orange-500',
                                    'icon' => 'text-orange-500',
                                    'msg' => '⚠️ ใกล้ถึงกำหนดนับสต๊อก'
                                ],
                                default => [ // safe
                                    'border' => 'border-emerald-200',
                                    'bg' => 'bg-gradient-to-r from-emerald-50 to-teal-50',
                                    'text_head' => 'text-emerald-700',
                                    'text_sub' => 'text-emerald-600',
                                    'icon' => 'text-emerald-500',
                                    'msg' => '✅ สถานะปกติ'
                                ]
                            };
                        @endphp

                        <div class="p-3 border {{ $theme['border'] }} {{ $theme['bg'] }} rounded-2xl relative overflow-hidden shadow-sm transition-all hover:shadow-md">
                            <div class="flex justify-between items-start mb-1">
                                <h4 class="text-sm font-bold {{ $theme['text_head'] }} truncate pr-2">
                                    @if($cycle->status === 'locked') <i class="fas fa-lock mr-1"></i> @endif
                                    {{ $cycle->name }}
                                </h4>
                                <a href="{{ route('stock-checks.create') }}" class="text-xs {{ $theme['text_sub'] }} hover:underline transition-colors">
                                    <i class="fas fa-edit"></i> สร้างนัดหมาย
                                </a>
                            </div>
                            
                            <div class="text-xs text-gray-600 mb-1">
                                <span class="font-semibold"><i class="fas fa-cubes mr-1 {{ $theme['icon'] }}"></i>จำนวน:</span> {{ $cycle->item_count }} รายการ
                            </div>
                            <div class="text-xs font-bold {{ $theme['text_sub'] }} mb-2">
                                {{ $theme['msg'] }}
                            </div>

                            {{-- ✅ แสดง Countdown เสมอ --}}
                            <p class="text-xs {{ $theme['text_sub'] }} mb-2 border-t border-black/5 pt-1 mt-1">
                                <i class="fas fa-clock mr-1"></i>เป้าหมาย: {{ $cycle->formatted_date }}
                            </p>
                            <div class="flex space-x-2 text-xs font-mono {{ $theme['text_head'] }} bg-white/60 p-2 rounded-lg justify-center stock-countdown-display border border-black/5" 
                                 data-target="{{ $cycle->next_check_date }}">
                                 <span><i class="fas fa-spinner fa-spin"></i> กำลังคำนวณ...</span>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-sm text-center text-gray-500"><i class="mr-2 text-green-500 fas fa-check-circle"></i>ข้อมูลครบถ้วน</div>
                    @endforelse
                </div>
            </div>

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
@endsection

@push('scripts')
    {{-- SweetAlert2 for Popups --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        window.lockedStockCount = {{ $stockCycles->where('status', 'locked')->count() }};
        window.warningStockCount = {{ $stockCycles->where('status', 'warning')->count() }};
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
    <script src="{{ asset('js/dashboard.js') }}"></script>
@endpush