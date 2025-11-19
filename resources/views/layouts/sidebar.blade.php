@php
    $menu = [
        'main' => [
            'dashboard' => ['icon' => 'fa-tachometer-alt', 'color' => 'blue', 'title' => '🏠 Dashboard', 'subtitle' => 'ภาพรวมระบบ', 'permission' => 'dashboard:view'],
            'user.equipment.index' => ['icon' => 'fa-shopping-basket', 'color' => 'orange', 'title' => '🔄 เบิก/ยืม อุปกรณ์', 'subtitle' => 'Withdraw / Borrow', 'permission' => 'equipment:borrow'],
        ],
        'accordions' => [
            'inventory' => [
                'title' => 'คลังและอุปกรณ์',
                'icon' => 'fa-warehouse',
                'color' => 'green',
                'items' => [
                    'equipment.index' => ['icon' => 'fa-laptop', 'color' => 'green', 'title' => 'จัดการอุปกรณ์', 'subtitle' => 'เพิ่ม/แก้ไข สต๊อก', 'permission' => 'equipment:view'],
                    'receive.index' => ['icon' => 'fa-download', 'color' => 'cyan', 'title' => 'รับเข้าอุปกรณ์', 'subtitle' => 'Receive Equipment', 'permission' => 'receive:view'],
                    'stock-checks.index' => ['icon' => 'fa-clipboard-check', 'color' => 'teal', 'title' => 'ตรวจนับสต็อก', 'subtitle' => 'Stock Count', 'permission' => 'stock-check:manage'],
                    'disposal.index' => ['icon' => 'fa-trash-alt', 'color' => 'red', 'title' => 'รอตัดจำหน่าย', 'subtitle' => 'Disposal List', 'permission' => 'disposal:view'],
                ]
            ],
            'purchasing' => [
                'title' => 'จัดซื้อและติดตาม',
                'icon' => 'fa-file-invoice-dollar',
                'color' => 'teal',
                'items' => [
                    'purchase-orders.index' => ['icon' => 'fa-shopping-cart', 'color' => 'teal', 'title' => 'ใบสั่งซื้อ', 'subtitle' => 'Purchase Orders', 'permission' => 'po:view'],
                    'purchase-track.index' => ['icon' => 'fa-truck-fast', 'color' => 'blue', 'title' => 'ติดตามใบสั่งซื้อ', 'subtitle' => 'Order Tracking', 'permission' => 'po:view'],
                ]
            ],
            'transactions' => [
                'title' => 'ธุรกรรมและซ่อมบำรุง',
                'icon' => 'fa-exchange-alt',
                'color' => 'purple',
                'items' => [
                    'transactions.index' => ['icon' => 'fa-history', 'color' => 'gray', 'title' => 'ประวัติธุรกรรม', 'subtitle' => 'Transaction History', 'permission' => 'transaction:view'],
                    'returns.index' => ['icon' => 'fa-undo-alt', 'color' => 'purple', 'title' => 'คืน/แจ้งเสีย', 'subtitle' => 'Return/Report', 'permission' => 'return:view'],
                    'maintenance.index' => ['icon' => 'fa-wrench', 'color' => 'gray', 'title' => 'ซ่อมบำรุง', 'subtitle' => 'Maintenance', 'permission' => 'maintenance:view'],
                    'consumable-returns.index' => ['icon' => 'fa-box-tissue', 'color' => 'pink', 'title' => 'รับคืนพัสดุ', 'subtitle' => 'Consumable Return', 'permission' => 'consumable:return'],
                ]
            ],
            'analysis' => [
                'title' => 'วิเคราะห์และรายงาน',
                'icon' => 'fa-chart-pie',
                'color' => 'indigo',
                'items' => [
                    'reports.index' => ['icon' => 'fa-chart-bar', 'color' => 'indigo', 'title' => 'รายงาน', 'subtitle' => 'System Reports', 'permission' => 'report:view'],
                ]
            ],
            'settings' => [
                'title' => 'การตั้งค่าระบบ',
                'icon' => 'fa-cogs',
                'color' => 'pink',
                'items' => [
                    'management.users.index' => ['icon' => 'fa-users-cog', 'color' => 'pink', 'title' => 'จัดการสิทธิ์ผู้ใช้', 'subtitle' => 'User Permissions', 'permission' => 'user:manage'],
                    'management.groups.index' => ['icon' => 'fa-shield-alt', 'color' => 'indigo', 'title' => 'จัดการกลุ่มและสิทธิ์', 'subtitle' => 'Groups & Roles', 'permission' => 'permission:manage'],
                    'management.tokens.index' => ['icon' => 'fa-key', 'color' => 'purple', 'title' => 'จัดการ API Token', 'subtitle' => 'M2M Integration', 'permission' => 'token:manage'],
                    'categories.index' => ['icon' => 'fa-folder-open', 'color' => 'yellow', 'title' => 'จัดการประเภท', 'subtitle' => 'Master Data', 'permission' => 'master-data:manage'],
                    'locations.index' => ['icon' => 'fa-map-marker-alt', 'color' => 'teal', 'title' => 'จัดการสถานที่', 'subtitle' => 'Master Data', 'permission' => 'master-data:manage'],
                    'units.index' => ['icon' => 'fa-ruler-combined', 'color' => 'red', 'title' => 'จัดการหน่วยนับ', 'subtitle' => 'Master Data', 'permission' => 'master-data:manage'],
                ]
            ]
        ]
    ];
@endphp

{{-- 
  (1) โครงสร้างหลัก Sidebar
  - h-screen: สูงเต็มจอ
  - flex flex-col: จัดเรียง บน-ล่าง
--}}
<div id="sidebar" class="fixed top-0 left-0 z-50 w-64 h-screen transition-transform duration-500 transform -translate-x-full soft-card lg:translate-x-0 flex flex-col">

    {{-- (2) ส่วน Header (Logo + User) - ไม่ Scroll --}}
    <div class="p-5"> 
        <div class="flex items-center mb-8 space-x-3 animate-fade-in">
            <div class="relative">
                {{-- ✅ แก้ไข: เปลี่ยนสีไอคอนเป็นธีม WH (Teal/Emerald) --}}
                <div class="flex items-center justify-center w-12 h-12 bg-gradient-to-br from-teal-100 to-emerald-200 rounded-2xl gentle-shadow">
                    {{-- ✅ แก้ไข: เปลี่ยนไอคอนเป็น fa-boxes-stacked (กล่อง) --}}
                    <i class="text-xl text-teal-600 fas fa-boxes-stacked"></i>
                </div>
            </div>
            <div>
                {{-- ✅ แก้ไข: เปลี่ยนชื่อเป็น WH Stock --}}
                <h1 class="text-xl font-bold gradient-text-soft">{{ config('app.name', 'WH Stock') }}</h1>
                {{-- ✅ แก้ไข: เปลี่ยนข้อความเป็น WH Dept --}}
                <p class="text-sm font-medium text-gray-600">📦 V 1.0 By WH Dept</p>
            </div>
        </div>

        <div class="p-5 mb-6 soft-card rounded-2xl animate-slide-up-soft gentle-shadow">
            <div class="flex items-center space-x-3">
                @auth
                    <x-user-profile-picture :user="Auth::user()" size="md" />
                @endauth
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-gray-800 truncate">{{ Auth::user()->fullname ?? 'Guest User' }}</p>
                    <p class="text-xs text-gray-600">
                        @auth
                            @if(Auth::user()->id === (int)config('app.super_admin_id'))
                                Administrator
                            @else
                                {{ optional(optional(Auth::user()->serviceUserRole)->userGroup)->name ?? 'N/A' }}
                            @endif
                        @endauth
                    </p>
                </div>
            </div>
        </div>
    </div> {{-- (2) ปิด div หุ้มส่วน Header --}}

    {{-- 
      (3) ส่วน <nav> (เมนู) - "Scroll ได้"
          - flex-1: ยืดเต็มพื้นที่ที่เหลือ
          - overflow-y-auto: ให้มี Scrollbar เมื่อเมนูยาว
          - min-h-0: (สำคัญ) บังคับให้ nav หดตัวได้ เพื่อให้ overflow ทำงาน
    --}}
    <nav class="flex-1 px-5 pb-5 space-y-2 overflow-y-auto scrollbar-soft min-h-0">
    
        {{-- Main Menu Items --}}
        @foreach ($menu['main'] as $route => $item)
            @can($item['permission'])
                <a href="{{ route($route) }}" class="nav-item {{ request()->routeIs($route.'*') ? 'active-nav' : '' }} flex items-center space-x-3 p-4 rounded-2xl text-gray-700 transition-all group">
                    <div class="w-10 h-10 bg-gradient-to-br from-{{ $item['color'] }}-100 to-{{ $item['color'] }}-200 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform gentle-shadow">
                        <i class="fas {{ $item['icon'] }} text-{{ $item['color'] }}-500 text-sm"></i>
                    </div>
                    <div>
                        <span class="text-sm font-bold">{{ $item['title'] }}</span>
                        <p class="text-xs text-gray-500">{{ $item['subtitle'] }}</p>
                    </div>
                </a>
            @endcan
        @endforeach

        <div class="pt-2"></div>

        {{-- Accordion Menu Items --}}
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
                <div>
                    <button class="flex items-center justify-between w-full p-4 text-gray-700 transition-all accordion-toggle rounded-2xl hover:bg-gray-100">
                        <div class="flex items-center space-x-3">
                            <div class="flex items-center justify-center w-8 h-8 bg-gradient-to-br from-{{ $category['color'] }}-100 to-{{ $category['color'] }}-200 rounded-lg gentle-shadow">
                                <i class="fas {{ $category['icon'] }} text-{{ $category['color'] }}-500 text-xs"></i>
                            </div>
                            <span class="text-xs font-bold text-gray-500 uppercase">{{ $category['title'] }}</span>
                        </div>
                        <i class="text-gray-500 transition-transform duration-300 fas fa-chevron-down accordion-chevron"></i>
                    </button>
                    
                    {{-- 
                      (โค้ดส่วน Accordion Content - เหมือนเดิม)
                    --}}
                    <div class="grid grid-rows-[0fr] transition-all duration-500 ease-in-out accordion-content">
                        <div class="overflow-hidden min-h-0"> 
                            <div class="pl-4 mt-2 space-y-2"> 
                                @foreach ($category['items'] as $route => $item)
                                    @if (isset($item['permission']))
                                        @can($item['permission'])
                                            <a href="{{ route($route) }}" class="nav-item {{ request()->routeIs($route.'*') ? 'active-nav' : '' }} flex items-center space-x-3 p-4 rounded-2xl text-gray-700 transition-all group">
                                                <div class="w-10 h-10 bg-gradient-to-br from-{{ $item['color'] }}-100 to-{{ $item['color'] }}-200 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform gentle-shadow">
                                                    <i class="fas {{ $item['icon'] }} text-{{ $item['color'] }}-500 text-sm"></i>
                                                </div>
                                                <div>
                                                    <span class="text-sm font-bold">{{ $item['title'] }}</span>
                                                    @if(isset($item['subtitle']))
                                                        <p class="text-xs text-gray-500">{{ $item['subtitle'] }}</p>
                                                    @endif
                                                </div>
                                            </a>
                                        @endcan
                                    @else
                                        <a href="{{ route($route) }}" class="nav-item {{ request()->routeIs($route.'*') ? 'active-nav' : '' }} flex items-center space-x-3 p-4 rounded-2xl text-gray-700 transition-all group">
                                            <div class="w-10 h-10 bg-gradient-to-br from-{{ $item['color'] }}-100 to-{{ $item['color'] }}-200 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform gentle-shadow">
                                                <i class="fas {{ $item['icon'] }} text-{{ $item['color'] }}-500 text-sm"></i>
                                            </div>
                                            <div>
                                                <span class="text-sm font-bold">{{ $item['title'] }}</span>
                                                @if(isset($item['subtitle']))
                                                    <p class="text-xs text-gray-500">{{ $item['subtitle'] }}</p>
                                                @endif
                                            </div>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </nav> {{-- (3) ปิด <nav> --}}

</div> {{-- (1) ปิด div#sidebar --}}


<div id="mobile-overlay" class="fixed inset-0 z-40 hidden bg-black bg-opacity-50 lg:hidden"></div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const accordions = document.querySelectorAll('.accordion-toggle');

    accordions.forEach(button => {
        
        // --- (โค้ด JavaScript ของ Accordion - เหมือนเดิม) ---
        const content = button.nextElementSibling; 
        const chevron = button.querySelector('.accordion-chevron');

        if (content) {
            button.addEventListener('click', () => {
                const isOpen = content.classList.contains('grid-rows-[1fr]');

                accordions.forEach(otherButton => {
                    const otherContent = otherButton.nextElementSibling;
                    const otherChevron = otherButton.querySelector('.accordion-chevron');
                    
                    if (otherContent) {
                        if (otherButton !== button && otherContent.classList.contains('grid-rows-[1fr]')) {
                            otherContent.classList.remove('grid-rows-[1fr]'); 
                            if(otherChevron) otherChevron.classList.remove('rotate-180'); 
                        }
                    }
                });

                if (isOpen) {
                    content.classList.remove('grid-rows-[1fr]');
                    if(chevron) chevron.classList.remove('rotate-180');
                } else {
                    content.classList.add('grid-rows-[1fr]'); 
                    if(chevron) chevron.classList.add('rotate-180');
                }
            });

            if (content.querySelector('.active-nav')) {
                content.classList.add('grid-rows-[1fr]'); 
                if(chevron) chevron.classList.add('rotate-180');
            }
        }
    });

    // Mobile Sidebar Toggle (เหมือนเดิม)
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

    if (openBtn) {
        openBtn.addEventListener('click', toggleSidebar);
    }
    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', toggleSidebar);
    }

});
</script>
@endpush

