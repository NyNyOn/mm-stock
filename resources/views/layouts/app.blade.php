{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        {{-- ... (ส่วน head เหมือนเดิมทุกประการ) ... --}}
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="app-base-url" content="{{ url('') }}">
        <meta name="can-bypass-frozen" content="{{ Auth::user()->canBypassFrozenState() ? 'true' : 'false' }}">
        <title>{{ config('app.name', 'Laravel') }}</title>

        {{-- Hide Tailwind CDN Warning --}}
        <script>
            (function() {
                const originalWarn = console.warn;
                console.warn = function(...args) {
                    if (args[0] && typeof args[0] === 'string' && args[0].includes('cdn.tailwindcss.com')) return;
                    originalWarn.apply(console, args);
                };
            })();
        </script>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: { 'thai': ['Kanit', 'system-ui', 'sans-serif'], },
                        animation: {
                            'bounce-gentle': 'bounceGentle 2s infinite',
                            'pulse-soft': 'pulseSoft 2s infinite',
                            'float-soft': 'floatSoft 4s ease-in-out infinite',
                            'fade-in': 'fadeIn 0.5s ease-out',
                            'slide-up-soft': 'slideUpSoft 0.6s ease-out',
                            'fade-in-down': 'fadeInDown 0.3s ease-out',
                        },
                        keyframes: {
                            fadeInDown: {
                                '0%': { opacity: '0', transform: 'translateY(-10px)' },
                                '100%': { opacity: '1', transform: 'translateY(0)' },
                            }
                        }
                    }
                }
            }
        </script>
        <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <link href="{{ asset('css/style.css') }}" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

        {{-- LightGallery CSS --}}
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/css/lightgallery.min.css"/>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/css/lg-thumbnail.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/css/lg-zoom.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/css/lg-fullscreen.min.css" />

         {{-- SweetAlert2 (ย้ายมาไว้ใน head เพื่อให้พร้อมใช้งานเสมอ) --}}
         <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        {{-- Flatpickr CSS (ถ้าใช้ในหลายหน้า) --}}
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

        @stack('styles')
    </head>
    <body class="min-h-screen font-thai" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 25%, #e3f2fd 50%, #f3e5f5 75%, #fff3e0 100%);">

        {{-- ... (ส่วน Loading Screen) ... --}}

        <div class="min-h-screen">
            @include('layouts.sidebar')
            <div class="flex flex-col min-h-screen lg:ml-64">
                @include('layouts.topbar')
                <main class="flex-grow p-5">
                    @yield('content')
                    {{ $slot ?? '' }}
                </main>
            </div>
        </div>

        @include('layouts.footer')
        {{-- ... (ส่วน Toast Container และ Modals) ... --}}
         <div id="toast-container" class="fixed top-20 right-4 z-[9999] space-y-4 w-full max-w-xs sm:max-w-sm"></div>
        @include('partials.modals.confirmation-modal')
        @auth
            @include('partials.modals.unconfirmed-pickup-modal')
        @endauth


        {{-- ... (ส่วน Libraries: jQuery, Select2, ฯลฯ) ... --}}
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
        {{-- Tone.js Removed to prevent AudioContext warnings --}}
        {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/tone/14.7.77/Tone.js"></script> --}}
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

        {{-- LightGallery JS --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/lightgallery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/plugins/thumbnail/lg-thumbnail.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/plugins/zoom/lg-zoom.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/plugins/fullscreen/lg-fullscreen.min.js"></script>


        {{-- Script หลักของเรา --}}
        <script src="{{ asset('js/main.js') }}"></script>

        @stack('scripts')

        {{-- สคริปต์สำหรับ Modal, Dropdown, และ การแจ้งเตือนต่างๆ --}}
        <script>
            {{-- ... (ส่วน Modal Functions และ Dropdown Functions เหมือนเดิม) ... --}}
             function showModal(id) {
                const modal = document.getElementById(id);
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeModal(id) {
                const modal = document.getElementById(id);
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }

            function toggleDropdown(dropdownId) {
                document.getElementById(dropdownId).classList.toggle('hidden');
            }

            window.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown-toggle')) {
                    document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                        menu.classList.add('hidden');
                    });
                }
            });


            {{-- ... (ส่วน Unconfirmed Pickups เหมือนเดิม) ... --}}


             {{-- ✅✅✅ START: แก้ไข Popup ต้อนรับ (เพิ่ม Admin/IT) ✅✅✅ --}}
             @auth
                @php
                    // ดึงข้อมูลที่จำเป็นใน PHP ก่อนส่งให้ JS
                    $isSuperAdmin = Auth::user()->id === (int)config('app.super_admin_id');
                    $userGroupSlug = Auth::user()->serviceUserRole?->userGroup?->slug;
                    
                    // แปลง $userGroupSlug เป็นตัวเล็ก
                    $slugLower = $userGroupSlug ? strtolower($userGroupSlug) : null;
                    
                    // ตรวจสอบว่าเป็น Admin/IT (โดยอิงจาก slug ที่เราดีบักได้)
                    $isAdminOrIT = $userGroupSlug && in_array($slugLower, ['it', 'admin', 'administrator', 'administartor', 'itsupport', 'it-support']);
                @endphp

                @if($isSuperAdmin)
                    document.addEventListener('DOMContentLoaded', function() {
                        if (!sessionStorage.getItem('creator_welcomed')) {
                            Swal.fire({
                                position: 'center',
                                showConfirmButton: false,
                                timer: 3000,
                                width: '480px',
                                background: '#FFFFFF',
                                backdrop: `rgba(0,0,0,0.4)`,
                                iconHtml: '<i class="text-6xl text-yellow-400 fas fa-crown"></i>',
                                title: '<span class="text-2xl font-bold text-gray-800">ยินดีต้อนรับกลับมา</span>',
                                html: '<p class="text-4xl font-bold gradient-text-soft">{{ Auth::user()->fullname }}</p><p class="mt-2 text-gray-500">ท่านผู้สร้างระบบ</p>',
                                showClass: { popup: 'animate__animated animate__fadeInDown' },
                                hideClass: { popup: 'animate__animated animate__fadeOutUp' }
                            });
                            sessionStorage.setItem('creator_welcomed', 'true');
                        }
                    });

                {{-- 2. (ใหม่) Pop-up สำหรับ Admin/IT (ที่ไม่ใช่ Super Admin) --}}
                @elseif($isAdminOrIT)
                    document.addEventListener('DOMContentLoaded', function() {
                        // ใช้ session key คนละตัวกับ Super Admin
                        if (!sessionStorage.getItem('admin_welcomed')) { 
                            Swal.fire({
                                position: 'center',
                                showConfirmButton: false,
                                timer: 3000,
                                width: '480px',
                                background: '#FFFFFF',
                                backdrop: `rgba(0,0,0,0.4)`,
                                // เปลี่ยนไอคอนเป็น "โล่" (Shield) สีเขียว
                                iconHtml: '<i class="text-6xl text-green-500 fas fa-user-shield"></i>', 
                                title: '<span class="text-2xl font-bold text-gray-800">ยินดีต้อนรับ</span>',
                                html: '<p class="text-4xl font-bold gradient-text-soft">{{ Auth::user()->fullname }}</p><p class="mt-2 text-gray-500">ผู้ดูแลระบบ</p>',
                                showClass: { popup: 'animate__animated animate__fadeInDown' },
                                hideClass: { popup: 'animate__animated animate__fadeOutUp' }
                            });
                            // ตั้งค่า session key ของ Admin
                            sessionStorage.setItem('admin_welcomed', 'true'); 
                        }
                    });
                @endif

                {{-- 3. (สำคัญ) โค้ดเคลียร์ sessionStorage ตอน Logout (ต้องเคลียร์ทั้ง 2 key) --}}
                const logoutButton = document.querySelector('form[action="{{ route('logout') }}"] button');
                if(logoutButton) {
                    logoutButton.addEventListener('click', function() {
                        sessionStorage.removeItem('creator_welcomed'); // เคลียร์ของ Super Admin
                        sessionStorage.removeItem('admin_welcomed');   // เคลียร์ของ Admin
                    });
                }
             @endauth
             {{-- ✅✅✅ END: สิ้นสุดการแก้ไข Popup ต้อนรับ ✅✅✅ --}}


             {{-- โค้ดแจ้งเตือนสถานะจาก Session (Success/Error) (เหมือนเดิม) --}}
            document.addEventListener('DOMContentLoaded', function () {
                @if (session('success'))
                    var successMessage = {!! json_encode(session('success')) !!};
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: successMessage, 
                        timer: 2500,
                        showConfirmButton: false
                    });
                @endif

                @if (session('error'))
                    var errorMessage = {!! json_encode(session('error')) !!};
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด!',
                        html: errorMessage.replace(/\n/g, '<br>'), 
                        showConfirmButton: true
                    });
                @endif

                @if (session('warning'))
                    var warningMessage = {!! json_encode(session('warning')) !!};
                    Swal.fire({
                        icon: 'warning',
                        title: 'คำเตือน',
                        html: warningMessage.replace(/\n/g, '<br>'),
                        showConfirmButton: true
                    });
                @endif

                @if (session('info'))
                    var infoMessage = {!! json_encode(session('info')) !!};
                    Swal.fire({
                        icon: 'info',
                        title: 'แจ้งเพื่อทราบ',
                        text: infoMessage,
                        timer: 3000,
                        showConfirmButton: false
                    });
                @endif
            });

        </script> {{-- ปิด Tag Script หลัก --}}

        {{-- ... (ส่วน Modal Credits เหมือนเดิม) ... --}}
        <div id="credits-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 modal-backdrop-soft">
            <div class="w-full max-w-md modal-content-wrapper animate-slide-up-soft">
                <div class="relative p-6 soft-card rounded-2xl">
                    <button type="button" class="absolute text-2xl text-gray-400 top-4 right-4 hover:text-gray-600" onclick="closeModal('credits-modal')">&times;</button>
                    <div class="text-center">
                        <span class="inline-block p-3 text-3xl text-blue-600 bg-blue-100 rounded-full">
                            <i class="fas fa-rocket"></i>
                        </span>
                    </div>
                    <h3 class="mt-4 text-xl font-bold text-center gradient-text-soft">Stock Pro</h3>
                    <p class="mt-1 text-sm text-center text-gray-500">Version 2.0 (Dual-DB Arch)</p>
                    <div class="py-4 my-4 border-b border-t"></div>
                    <div class="space-y-4 text-left">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase">Developed By</p>
                            <p class="font-semibold text-gray-700">ITsupport</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase">Assisted By</p>
                            <p class="font-semibold text-gray-700">คู่หูเขียนโค้ด (AI by Google)</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase">Powered By</p>
                            <div class="flex flex-wrap items-center gap-4 mt-2">
                                <span class="px-2 py-1 text-xs font-semibold text-red-700 bg-red-100 rounded-full">Laravel</span>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full text-sky-700 bg-sky-100">Tailwind CSS</span>
                                <span class="px-2 py-1 text-xs font-semibold text-yellow-700 bg-yellow-100 rounded-full">JavaScript</span>
                                <span class="px-2 py-1 text-xs font-semibold text-pink-700 bg-pink-100 rounded-full">SweetAlert2</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @include('partials.modals.rating-modal')    
    </body>
</html>