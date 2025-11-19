<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Laravel') }} - Login</title>
        
        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <!-- Icons -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        
        <!-- Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
        
        <style>
            /* ใช้ Kanit เป็นฟอนต์หลัก */
            body { 
                font-family: 'Kanit', sans-serif; 
            }
            /* ✅ แก้ไข: ไล่สีสำหรับหัวข้อ (โทนสีน้ำเงินเข้ม/กรมท่า) */
            .gradient-text-mm { 
                background: linear-gradient(to right, #1d4ed8, #1e3a8a); /* Blue-800 to Blue-900 */
                -webkit-background-clip: text; 
                -webkit-text-fill-color: transparent; 
            }
            /* ✅ แก้ไข: พื้นหลังไล่สี (โทนฟ้าอ่อน/เทาอ่อน) */
            .mm-gradient-bg {
                background: linear-gradient(135deg, #f0f9ff 0%, #f8fafc 100%); /* Sky-50 to Slate-50 */
            }
        </style>
    </head>
    <body class="antialiased">
        {{-- ✅ แก้ไข: เปลี่ยน class พื้นหลัง --}}
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 mm-gradient-bg">
            
            <!-- Logo และ Branding -->
            <div>
                <a href="/" class="flex flex-col items-center group">
                    <!-- ✅ แก้ไข: ไอคอนสำหรับ MM (Management) -->
                    <div class="w-24 h-24 bg-gradient-to-br from-blue-700 to-blue-900 rounded-3xl flex items-center justify-center shadow-lg shadow-blue-500/30 transition-all duration-300 group-hover:shadow-xl group-hover:scale-105">
                        <!-- ไอคอนผู้บริหาร (User Tie) -->
                        <i class="fas fa-user-tie text-white text-5xl transition-transform duration-300 group-hover:scale-110"></i>
                    </div>
                    
                    <!-- ✅ แก้ไข: ชื่อระบบ -->
                    <h1 class="text-3xl font-bold gradient-text-mm mt-5">
                        MM Stock System
                    </h1>
                    
                    <!-- ✅ แก้ไข: ชื่่อเต็ม -->
                    <p class="text-gray-500 text-sm mt-1">
                        Management Department
                    </p>
                </a>
            </div>

            <!-- การ์ดสำหรับฟอร์ม (Slot) -->
            <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-white/80 backdrop-blur-lg shadow-2xl overflow-hidden sm:rounded-2xl border border-white/50">
                {{ $slot }}
            </div>

            <!-- ✅ แก้ไข: Footer -->
            <div class="mt-8 text-center text-xs text-gray-500">
                &copy; {{ date('Y') }} Management Department. All rights reserved.
            </div>
        </div>
    </body>
</html>

