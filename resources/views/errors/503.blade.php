<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - ปิดปรับปรุงระบบ</title>
    {{-- Include Tailwind CSS --}}
    <script src="https://cdn.tailwindcss.com"></script>
    {{-- Include Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Kanit', sans-serif;
        }
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 flex items-center justify-center min-h-screen p-4">

    @php
        // ดึงข้อมูลจาก Settings
        $maintenanceStart = \App\Models\Setting::where('key', 'maintenance_start')->value('value');
        $maintenanceEnd = \App\Models\Setting::where('key', 'maintenance_end')->value('value');
        $maintenanceMessage = \App\Models\Setting::where('key', 'maintenance_message')->value('value');
        
        // Format datetime for display
        $startFormatted = $maintenanceStart ? \Carbon\Carbon::parse($maintenanceStart)->locale('th')->translatedFormat('j M Y H:i น.') : null;
        $endFormatted = $maintenanceEnd ? \Carbon\Carbon::parse($maintenanceEnd)->locale('th')->translatedFormat('j M Y H:i น.') : null;
    @endphp

    <div class="text-center max-w-2xl mx-auto">
        
        {{-- Animated Icon --}}
        <div class="mb-8 animate-float">
            <div class="inline-flex items-center justify-center w-32 h-32 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 shadow-2xl shadow-orange-500/30">
                <i class="fas fa-tools text-5xl text-white"></i>
            </div>
        </div>

        {{-- Main Card --}}
        <div class="bg-white/10 backdrop-blur-lg rounded-3xl shadow-2xl p-8 md:p-10 border border-white/20">
            
            {{-- Title --}}
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-3">
                <i class="fas fa-cog fa-spin text-yellow-400 mr-2"></i>
                กำลังปรับปรุงระบบ
            </h1>
            
            {{-- Subtitle --}}
            <p class="text-blue-200 text-lg mb-6">
                ระบบ {{ config('app.name', 'IT Stock') }} กำลังอยู่ในระหว่างการปรับปรุง
            </p>

            {{-- Custom Message (if provided) --}}
            @if($maintenanceMessage)
                <div class="bg-white/10 rounded-xl p-4 mb-6 border border-white/10">
                    <p class="text-white text-base italic">
                        <i class="fas fa-quote-left text-yellow-400 mr-2 opacity-50"></i>
                        {{ $maintenanceMessage }}
                        <i class="fas fa-quote-right text-yellow-400 ml-2 opacity-50"></i>
                    </p>
                </div>
            @endif

            {{-- Schedule Info --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                {{-- Start Time --}}
                @if($startFormatted)
                <div class="bg-red-500/20 rounded-xl p-4 border border-red-500/30">
                    <div class="flex items-center justify-center gap-2 text-red-300 text-sm mb-1">
                        <i class="fas fa-clock"></i>
                        <span>เริ่มปิดระบบ</span>
                    </div>
                    <p class="text-white font-bold text-lg">{{ $startFormatted }}</p>
                </div>
                @endif

                {{-- End Time --}}
                @if($endFormatted)
                <div class="bg-green-500/20 rounded-xl p-4 border border-green-500/30">
                    <div class="flex items-center justify-center gap-2 text-green-300 text-sm mb-1">
                        <i class="fas fa-check-circle"></i>
                        <span>คาดว่าจะเปิด</span>
                    </div>
                    <p class="text-white font-bold text-lg">{{ $endFormatted }}</p>
                </div>
                @endif
            </div>

            {{-- Apology Message --}}
            <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                <p class="text-blue-200 text-sm">
                    <i class="fas fa-heart text-red-400 mr-1 animate-pulse-slow"></i>
                    ขออภัยในความไม่สะดวก กรุณาลองเข้าใช้งานใหม่อีกครั้งภายหลัง
                </p>
            </div>
        </div>

        {{-- Footer --}}
        <p class="mt-6 text-blue-300/60 text-sm">
            <i class="fas fa-shield-alt mr-1"></i>
            {{ config('app.name', 'IT Stock') }} &copy; {{ date('Y') }}
        </p>
    </div>

</body>
</html>
