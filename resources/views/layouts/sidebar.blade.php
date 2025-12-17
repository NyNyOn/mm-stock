@php
    $menu = [
        // --- 1. Main Menu ---
        'main' => [
            'dashboard' => [
                'icon' => 'fa-tachometer-alt', 
                'color' => 'blue', 
                'title' => 'Dashboard', 
                'subtitle' => 'ภาพรวมระบบ', 
                'permission' => 'dashboard:view'
            ],
            'user.equipment.index' => [
                'icon' => 'fa-shopping-basket', 
                'color' => 'orange', 
                'title' => 'เบิก/ยืม อุปกรณ์', 
                'subtitle' => 'Withdraw / Borrow', 
                'permission' => 'equipment:borrow'
            ],
            'transactions.index' => [
                'icon' => 'fa-clock-rotate-left', 
                'color' => 'purple', 
                'title' => 'ประวัติธุรกรรม', 
                'subtitle' => 'Transaction Logs', 
                'permission' => 'transaction:view'
            ],
        ],

        // --- 2. Accordion Menu ---
        'accordions' => [
            'inventory' => [
                'title' => 'คลังและอุปกรณ์',
                'icon' => 'fa-boxes-stacked', 
                'color' => 'emerald',    
                'items' => [
                    'equipment.index' => ['icon' => 'fa-laptop', 'title' => 'จัดการอุปกรณ์', 'permission' => 'equipment:view'],
                    'receive.index' => ['icon' => 'fa-download', 'title' => 'รับเข้าอุปกรณ์', 'permission' => 'receive:view'],
                    'stock-checks.index' => ['icon' => 'fa-clipboard-list', 'title' => 'ตรวจนับสต็อก', 'permission' => 'stock-check:manage'],
                    'disposal.index' => ['icon' => 'fa-trash-can', 'title' => 'ตัดจำหน่าย', 'permission' => 'disposal:view'],
                ]
            ],
            'purchasing' => [
                'title' => 'จัดซื้อและติดตาม',
                'icon' => 'fa-cart-shopping',
                'color' => 'cyan',       
                'items' => [
                    'purchase-orders.index' => ['icon' => 'fa-file-invoice-dollar', 'title' => 'ใบสั่งซื้อ (PO)', 'permission' => 'po:view'],
                    'purchase-track.index' => ['icon' => 'fa-truck-fast', 'title' => 'ติดตามพัสดุ', 'permission' => 'po:view'],
                ]
            ],
            'services' => [
                'title' => 'บริการและซ่อมบำรุง',
                'icon' => 'fa-screwdriver-wrench',
                'color' => 'amber',     
                'items' => [
                    'returns.index' => ['icon' => 'fa-rotate-left', 'title' => 'รับคืน / แจ้งเสีย', 'permission' => 'return:view'],
                    'consumable-returns.index' => ['icon' => 'fa-box-open', 'title' => 'คืนวัสดุสิ้นเปลือง', 'permission' => 'consumable:return'],
                    'maintenance.index' => ['icon' => 'fa-hammer', 'title' => 'รายการซ่อมบำรุง', 'permission' => 'maintenance:view'],
                ]
            ],
            'analytics' => [
                'title' => 'ข้อมูลและรายงาน',
                'icon' => 'fa-chart-line',
                'color' => 'pink',       
                'items' => [
                    'deadstock.index' => ['icon' => 'fa-box-archive', 'title' => 'Deadstock', 'permission' => 'report:view'],
                    'reports.index' => ['icon' => 'fa-file-csv', 'title' => 'รายงานสรุป', 'permission' => 'report:view'],
                ]
            ],
            'settings' => [
                'title' => 'การตั้งค่าระบบ',
                'icon' => 'fa-gears',
                'color' => 'indigo', // 🔵 เปลี่ยนจาก slate เป็น indigo ให้ดูมีสีสันขึ้นตามคำขอ
                'items' => [
                    'management.users.index' => ['icon' => 'fa-users', 'title' => 'ผู้ใช้งาน', 'permission' => 'user:manage'],
                    'management.groups.index' => ['icon' => 'fa-user-shield', 'title' => 'กลุ่มและสิทธิ์', 'permission' => 'permission:manage'],
                    'categories.index' => ['icon' => 'fa-tags', 'title' => 'ประเภทอุปกรณ์', 'permission' => 'master-data:manage'],
                    'locations.index' => ['icon' => 'fa-map-location-dot', 'title' => 'สถานที่จัดเก็บ', 'permission' => 'master-data:manage'],
                    'units.index' => ['icon' => 'fa-scale-balanced', 'title' => 'หน่วยนับ', 'permission' => 'master-data:manage'],
                    'management.tokens.index' => ['icon' => 'fa-key', 'title' => 'API Tokens', 'permission' => 'token:manage'],
                ]
            ]
        ]
    ];
@endphp

<div id="sidebar" class="fixed top-0 left-0 z-50 w-64 h-screen transition-transform duration-500 transform -translate-x-full lg:translate-x-0 flex flex-col 
     bg-gradient-to-b from-slate-50 via-white to-blue-50 border-r border-blue-100 shadow-[4px_0_24px_rgba(0,0,0,0.02)]">

    {{-- ปุ่มปิดเมนู (Mobile) --}}
    <div class="absolute top-4 right-4 lg:hidden z-50">
        <button id="close-sidebar-btn" class="p-3 text-gray-500 rounded-xl hover:bg-white hover:text-red-500 transition-all shadow-lg border border-gray-100 bg-white/80 backdrop-blur-sm">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>

    {{-- Header Section --}}
    <div class="p-6"> 
        {{-- Logo Area --}}
        <div class="flex items-center mb-8 space-x-3 animate-fade-in group cursor-default">
            <div class="relative transition-transform duration-500 group-hover:rotate-12">
                <div class="absolute inset-0 bg-blue-500 blur-xl opacity-20 rounded-full"></div>
                <div class="relative flex items-center justify-center w-12 h-12 bg-white rounded-2xl shadow-[0_4px_15px_rgba(0,0,0,0.05)] border border-blue-50">
                    <i class="text-xl text-transparent bg-clip-text bg-gradient-to-br from-blue-500 to-indigo-600 fas fa-cube"></i>
                </div>
            </div>
            <div>
                <h1 class="text-xl font-black text-slate-800 tracking-tight">{{ config('app.name', 'MM Stock') }}</h1>
                <div class="flex items-center space-x-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">System</span>
                </div>
            </div>
        </div>

        {{-- User Profile Card --}}
        <div class="p-3 mb-2 bg-white rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.03)] border border-blue-50/50 transition-all duration-300 hover:shadow-md hover:border-blue-100 group">
            <div class="flex items-center space-x-3">
                @auth
                    <div class="relative">
                         <x-user-profile-picture :user="Auth::user()" size="md" class="ring-2 ring-white shadow-sm" />
                         <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></div>
                    </div>
                @endauth
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-700 truncate group-hover:text-blue-600 transition-colors">{{ Auth::user()->fullname ?? 'Guest User' }}</p>
                    <p class="text-[11px] text-slate-400 truncate font-medium">
                        @auth
                            {{ optional(optional(Auth::user()->serviceUserRole)->userGroup)->name ?? 'User' }}
                        @endauth
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 px-4 pb-5 space-y-3 overflow-y-auto scrollbar-hide min-h-0">
    
        {{-- Main Menu --}}
        <div class="space-y-2">
            @foreach ($menu['main'] as $route => $item)
                @can($item['permission'])
                    @php
                        $isActive = request()->routeIs($route) || request()->routeIs(str_replace('.index', '', $route) . '.*');
                    @endphp
                    
                    {{-- 
                        ✨ Main Menu Logic:
                        - Active: bg-white (ขาวลอยเด่น) + Shadow
                        - Inactive (ปกติ): bg-[color]-50 (สีจางๆ แสดงตลอดเวลาตามที่ขอ)
                        - Hover: bg-[color]-100 (เข้มขึ้นเมื่อชี้)
                    --}}
                    <a href="{{ route($route) }}" 
                       class="nav-item group flex items-center space-x-3 p-3.5 rounded-2xl transition-all duration-200 ease-out relative overflow-hidden
                              {{ $isActive 
                                 ? 'bg-white shadow-[0_8px_30px_rgba(0,0,0,0.06)] border border-blue-50 ring-1 ring-blue-50' 
                                 : 'bg-'.$item['color'].'-50 border border-transparent hover:bg-'.$item['color'].'-100 hover:shadow-sm' }}">
                        
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-300 group-hover:scale-110 group-hover:rotate-3
                                    {{ $isActive 
                                       ? 'bg-gradient-to-br from-'.$item['color'].'-500 to-'.$item['color'].'-600 text-white shadow-lg shadow-'.$item['color'].'-200' 
                                       : 'bg-white text-'.$item['color'].'-400 shadow-[0_2px_8px_rgba(0,0,0,0.05)] border border-gray-50' }}">
                            <i class="fas {{ $item['icon'] }} text-sm"></i>
                        </div>
                        
                        <div>
                            <span class="text-sm font-bold block leading-tight {{ $isActive ? 'text-slate-800' : 'text-slate-600 group-hover:text-'.$item['color'].'-700' }}">
                                {{ $item['title'] }}
                            </span>
                            <p class="text-[10px] font-medium mt-0.5 transition-colors {{ $isActive ? 'text-'.$item['color'].'-500' : 'text-slate-400 group-hover:text-'.$item['color'].'-500' }}">
                                {{ $item['subtitle'] }}
                            </p>
                        </div>

                        @if($isActive)
                            <div class="absolute right-0 top-1/2 -translate-y-1/2 h-full w-1 bg-{{ $item['color'] }}-500 rounded-l-md opacity-20"></div>
                        @endif
                    </a>
                @endcan
            @endforeach
        </div>

        <div class="px-2">
            <div class="h-px bg-gradient-to-r from-transparent via-blue-100 to-transparent"></div>
        </div>

        {{-- Accordion Menus --}}
        <div class="space-y-2">
            @foreach ($menu['accordions'] as $key => $category)
                @php
                    $hasPermission = false;
                    foreach ($category['items'] as $route => $item) {
                        if (isset($item['permission']) && Auth::user()->can($item['permission'])) {
                            $hasPermission = true;
                            break;
                        }
                    }
                @endphp

                @if ($hasPermission)
                    <div class="mb-2">
                        {{-- 
                            ✨ Group Header:
                            - ใช้สีพื้นหลังจางๆ (bg-[color]-50) ตลอดเวลา เพื่อให้เห็นขอบเขตชัดเจน
                        --}}
                        <button class="flex items-center justify-between w-full p-3.5 transition-all duration-200 accordion-toggle rounded-2xl group border border-transparent 
                                       bg-{{ $category['color'] }}-50/50 hover:bg-{{ $category['color'] }}-50 hover:border-{{ $category['color'] }}-100">
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-white shadow-[0_2px_5px_rgba(0,0,0,0.03)] text-{{ $category['color'] }}-500 transition-transform duration-300 group-hover:scale-110 group-hover:bg-gradient-to-br group-hover:from-{{ $category['color'] }}-400 group-hover:to-{{ $category['color'] }}-600 group-hover:text-white">
                                    <i class="fas {{ $category['icon'] }} text-sm"></i>
                                </div>
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 group-hover:text-{{ $category['color'] }}-700">{{ $category['title'] }}</span>
                            </div>
                            <i class="text-slate-300 transition-transform duration-300 fas fa-chevron-down accordion-chevron text-xs group-hover:text-{{ $category['color'] }}-400"></i>
                        </button>
                        
                        <div class="grid grid-rows-[0fr] transition-all duration-300 ease-out accordion-content">
                            <div class="overflow-hidden min-h-0"> 
                                <div class="mt-2 bg-slate-50/50 rounded-2xl border border-blue-50/30 p-2 mx-1 space-y-1"> 
                                    @foreach ($category['items'] as $route => $item)
                                        @can($item['permission'])
                                            @php
                                                $baseRoute = str_replace('.index', '', $route);
                                                $isActive = request()->routeIs($route) || request()->routeIs($baseRoute.'.*');
                                                $itemColor = $category['color']; 
                                            @endphp

                                            {{-- 
                                                ✨ Submenu Item:
                                                - Inactive: bg-[color]-50 (แสดงสีจางๆ ทันที ไม่ต้องรอชี้)
                                                - Hover: bg-[color]-100 (เข้มขึ้น)
                                            --}}
                                            <a href="{{ route($route) }}" 
                                               class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all duration-200 group relative
                                                      {{ $isActive 
                                                         ? 'active-nav bg-white shadow-sm ring-1 ring-blue-50' 
                                                         : 'bg-'.$itemColor.'-50/50 hover:bg-'.$itemColor.'-100 text-slate-500 hover:text-slate-900' }}">
                                                
                                                <div class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors shadow-sm border border-transparent
                                                            {{ $isActive 
                                                               ? 'text-'.$itemColor.'-600 bg-'.$itemColor.'-50 border-'.$itemColor.'-100' 
                                                               : 'bg-white text-slate-400 group-hover:text-'.$itemColor.'-500 group-hover:border-blue-50' }}">
                                                    <i class="fas {{ $item['icon'] }} text-xs"></i>
                                                </div>
                                                
                                                <div class="flex-1">
                                                    <span class="text-sm block leading-tight {{ $isActive ? 'font-bold text-slate-800' : 'font-medium' }}">
                                                        {{ $item['title'] }}
                                                    </span>
                                                </div>

                                                @if($isActive)
                                                    <div class="w-2 h-2 rounded-full bg-{{ $itemColor }}-400 shadow-[0_0_8px] shadow-{{ $itemColor }}-400 animate-pulse"></div>
                                                @endif
                                            </a>
                                        @endcan
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
        
        <div class="h-24"></div>
    </nav>
</div>

{{-- Mobile Overlay --}}
<div id="mobile-overlay" class="fixed inset-0 z-40 hidden bg-slate-900/10 backdrop-blur-[2px] lg:hidden transition-opacity"></div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const accordions = document.querySelectorAll('.accordion-toggle');

    accordions.forEach(button => {
        const content = button.nextElementSibling; 
        const chevron = button.querySelector('.accordion-chevron');

        if (content) {
            const toggleAccordion = (shouldOpen) => {
                if (shouldOpen) {
                    content.classList.remove('grid-rows-[0fr]');
                    content.classList.add('grid-rows-[1fr]');
                    if(chevron) chevron.classList.add('rotate-180');
                    button.classList.add('bg-white/80', 'shadow-sm'); 
                } else {
                    content.classList.remove('grid-rows-[1fr]');
                    content.classList.add('grid-rows-[0fr]');
                    if(chevron) chevron.classList.remove('rotate-180');
                    button.classList.remove('bg-white/80', 'shadow-sm');
                }
            };

            button.addEventListener('click', () => {
                const isOpen = content.classList.contains('grid-rows-[1fr]');
                toggleAccordion(!isOpen);
            });

            if (content.querySelector('.active-nav')) {
                toggleAccordion(true);
            }
        }
    });

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    const openBtn = document.getElementById('open-sidebar-btn'); 
    const closeBtn = document.getElementById('close-sidebar-btn'); 

    function toggleSidebar() {
        if (sidebar && overlay) {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    }
    if (openBtn) openBtn.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', toggleSidebar);
    if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
});
</script>

<style>
.scrollbar-hide::-webkit-scrollbar { width: 0px; background: transparent; }
</style>
@endpush