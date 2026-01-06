<header class="sticky top-0 z-30 border-b border-gray-100 soft-card">
    <div class="flex items-center justify-between p-5">
        <div class="flex items-center min-w-0 space-x-5">
            <button onclick="toggleSidebar()" class="flex-shrink-0 p-3 transition-all lg:hidden rounded-xl hover:bg-gray-100 button-soft">
                <i class="text-lg text-gray-600 fas fa-bars"></i>
            </button>
            <div class="min-w-0 animate-fade-in">
                <h2 id="page-title" class="text-xl font-bold truncate md:text-2xl gradient-text-soft">@yield('header')</h2>
                <p id="page-subtitle" class="hidden mt-1 text-sm font-medium text-gray-600 truncate sm:block">@yield('subtitle')</p>
            </div>

            {{-- (โค้ดเดิมของคุณ: Auto-Confirm) --}}
            @can('transaction:auto_confirm')
                <div class="hidden sm:flex items-center ml-4 px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full animate-pulse-soft"
                     title="โหมดข้ามการยืนยันทำงานอยู่: การเบิก/ยืมของคุณจะถูกยืนยันและตัดสต็อกทันที">
                    <i class="fas fa-magic mr-1"></i>
                    <span>ระบบยืนยันอัตโนมัติ</span>
                </div>
            @endcan
        </div>

        <div class="flex items-center space-x-2 sm:space-x-6">
            {{-- (โค้ดเดิมของคุณ: ปุ่ม Export) --}}
            <div class="items-center hidden space-x-3 sm:flex">
                <button onclick="alert('ฟังก์ชัน Export ยังไม่ถูกสร้าง');"
                        class="p-3 text-blue-600 transition-all rounded-2xl bg-gradient-to-br from-blue-100 to-blue-200 hover:shadow-lg button-soft gentle-shadow"
                        title="ส่งออกข้อมูล">
                    <i class="text-sm fas fa-download"></i>
                </button>
            </div>
            {{-- (โค้ดเดิมของคุณ: ปุ่ม Notifications) --}}
            <div class="relative" id="notifications-button-wrapper">
                <button onclick="toggleDropdown('notifications-dropdown')" class="relative p-3 transition-all rounded-2xl hover:bg-gray-100 button-soft">
                    <i class="text-sm text-gray-600 fas fa-bell"></i>
                </button>
            </div>

            {{-- (โค้ดเดิมของคุณ: Dropdown โปรไฟล์) --}}
            <div class="relative" id="profile-button-wrapper">
                <button onclick="toggleDropdown('profile-dropdown')" class="flex items-center p-2 space-x-2 transition-all sm:space-x-4 sm:p-4 rounded-3xl hover:bg-gray-100 button-soft">

                    @auth
                        <x-user-profile-picture :user="Auth::user()" size="lg" />
                    @endauth

                    <div class="hidden text-left md:block">
                        @auth
                            <p class="text-sm font-bold text-gray-800 truncate sm:text-lg">{{ Auth::user()->fullname }}</p>
                            <p class="text-xs text-gray-600 sm:text-base">
                                @if(Auth::user()->id === (int)config('auth.super_admin_id'))
                                    ผู้ดูแลระบบสูงสุด
                                @else
                                    {{ Auth::user()->serviceUserRole?->userGroup?->name ?? 'ไม่ระบุ' }}
                                @endif
                            </p>
                        @else
                            <p class="text-sm font-bold text-gray-800 truncate sm:text-lg">ผู้เยี่ยมชม</p>
                            <p class="text-xs text-gray-600 sm:text-base">สิทธิ์การเข้าชม</p>
                        @endauth
                    </div>
                    <i class="hidden text-gray-400 fas fa-chevron-down sm:block"></i>
                </button>

                {{-- (โค้ดเดิมของคุณ: เนื้อหา Dropdown) --}}
                <div id="profile-dropdown" class="absolute right-0 z-50 hidden mt-2 origin-top-right w-72 rounded-2xl soft-card gentle-shadow-lg animate-fade-in-down">
                    <div class="p-4">
                        <div class="flex items-center mb-4 space-x-4">
                            @auth
                                <x-user-profile-picture :user="Auth::user()" size="lg" />
                            @endauth
                            <div class="min-w-0">
                                <p class="font-bold text-gray-800 truncate">{{ Auth::user()->fullname ?? 'ผู้เยี่ยมชม' }}</p>
                                <p class="text-sm text-gray-500 truncate">{{ Auth::user()->username ?? 'ไม่มีชื่อผู้ใช้' }}</p>
                            </div>
                        </div>

                        <nav class="flex flex-col space-y-2">
                            {{-- (โค้ดเดิมของคุณ: เมนูข้อมูลพนักงาน) --}}
                            <a href="{{ Auth::check() && method_exists(Auth::user(), 'getProfileLink') ? Auth::user()->getProfileLink() : '#' }}" target="_blank" class="flex items-center p-3 space-x-3 transition-colors rounded-lg hover:bg-gray-100">
                                <i class="w-6 text-center text-gray-500 fas fa-id-card"></i>
                                <span class="font-medium text-gray-700">ดูข้อมูลพนักงาน</span>
                            </a>
                            {{-- (โค้ดเดิมของคุณ: เมนูตั้งค่า) --}}
                            <a href="{{ route('settings.index') }}" class="flex items-center p-3 space-x-3 transition-colors rounded-lg hover:bg-gray-100">
                                <i class="w-6 text-center text-gray-500 fas fa-cog"></i>
                                <span class="font-medium text-gray-700">ตั้งค่า</span>
                            </a>
                            {{-- (โค้ดเดิมของคุณ: เมนูช่วยเหลือ) --}}
                            <a href="{{ route('help.index') }}" class="flex items-center p-3 space-x-3 transition-colors rounded-lg hover:bg-gray-100">
                                <i class="w-6 text-center text-gray-500 fas fa-question-circle"></i>
                                <span class="font-medium text-gray-700">ช่วยเหลือ</span>
                            </a>
                            
                            {{-- ✅✅✅ START: เพิ่มเมนู Changelog (แบบสวยงาม) ✅✅✅ --}}
                            <a href="{{ route('changelog.index') }}"
                               class="flex items-center p-3 space-x-3 text-gray-700 transition-colors rounded-lg group 
                                      hover:bg-indigo-50 hover:text-indigo-600 
                                      dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-indigo-400">
                                {{-- 1. เปลี่ยนไอคอน และ 2. เพิ่ม group-hover ให้ไอคอนเปลี่ยนสีตาม --}}
                                <i class="w-6 text-center text-gray-500 fas fa-rocket 
                                          group-hover:text-indigo-500 dark:group-hover:text-indigo-400"></i>
                                <span class="font-medium">ประวัติการอัปเดต</span>
                            </a>
                            {{-- ✅✅✅ END: เพิ่มเมนู Changelog ✅✅✅ --}}

                        </nav>

                        <hr class="my-3">

                        {{-- (โค้ดเดิมของคุณ: ปุ่ม Logout) --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    onclick="sessionStorage.removeItem('creator_welcomed'); sessionStorage.removeItem('admin_welcomed');"
                                    class="flex items-center w-full p-3 space-x-3 text-red-600 transition-colors rounded-lg hover:bg-red-50">
                                <i class="w-6 text-center fas fa-sign-out-alt"></i>
                                <span class="font-medium">ออกจากระบบ</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- (โค้ดเดิมของคุณ: Style) --}}
    <style>
        /* Adjust based on your topbar height */
        .pt-topbar-padding {
            padding-top: 80px; /* Example: Adjust this value (e.g., 64px, 72px) to match your topbar height */
        }
        /* Simple pulse animation */
        @keyframes pulse-soft {
            50% { opacity: .7; }
        }
        .animate-pulse-soft {
            animation: pulse-soft 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</header>