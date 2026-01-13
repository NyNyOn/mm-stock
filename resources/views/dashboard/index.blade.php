@extends('layouts.app')
@section('header', '🏠 Dashboard')
@section('subtitle', 'ภาพรวมระบบจัดการสต๊อกอุปกรณ์ ')

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

        {{-- 1.1 Super Admin (Soft Luxury Theme - Thai) --}}
        @if(Auth::user()->id === $superAdminId)
            <div class="relative overflow-hidden mb-6 rounded-2xl shadow-sm gentle-shadow animate-slide-up-soft group border border-indigo-100 bg-white">
                {{-- Background with Animated Gradient Flow --}}
                <div class="absolute inset-0 bg-gradient-to-r from-white via-indigo-50 to-white animate-gradient-flow opacity-80"></div>
                
                {{-- ✅ [Visual Depth] Floating Orbs --}}
                <div class="absolute top-[-50%] left-[-10%] w-96 h-96 bg-indigo-200 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
                <div class="absolute top-[-50%] right-[-10%] w-96 h-96 bg-purple-200 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
                <div class="absolute -bottom-32 left-20 w-96 h-96 bg-pink-200 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>

                {{-- ✅ [Watermark] --}}
                <div class="absolute inset-0 opacity-[0.03] bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] mix-blend-multiply pointer-events-none"></div>
                
                 {{-- ✅ [Real-time Clock] --}}
                <div class="absolute top-4 right-6 hidden md:flex items-center space-x-2 text-indigo-400/60 z-20">
                    <i class="far fa-clock"></i>
                    <span id="live-clock-super" class="text-sm font-mono tracking-widest">--:--:--</span>
                </div>

                <div class="relative p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center space-x-6 z-10 w-full md:w-auto">
                        <div class="relative group-hover:scale-105 transition-transform duration-500">
                             {{-- Pulse Ring --}}
                            <div class="absolute inset-0 bg-indigo-400 rounded-full blur opacity-20 animate-pulse"></div>
                            
                            {{-- Profile Picture Logic --}}
                            @php
                                $profileImg = Auth::user()->photo_url;
                            @endphp

                            @if($profileImg)
                                <img src="{{ $profileImg }}" 
                                     alt="Profile" 
                                     class="flex-shrink-0 w-24 h-24 object-cover rounded-full border-4 border-white shadow-lg shadow-indigo-200 relative z-10">
                            @else
                                <div class="flex-shrink-0 w-24 h-24 flex items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg shadow-indigo-200 text-white relative z-10 border-4 border-white">
                                     <i class="fas fa-user-astronaut text-4xl"></i>
                                </div>
                            @endif
                            
                             <div class="absolute bottom-0 right-0 w-6 h-6 bg-green-400 border-2 border-white rounded-full z-20" title="Online"></div>
                        </div>
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <span class="px-3 py-1 text-xs font-bold tracking-wider text-indigo-700 uppercase bg-indigo-100 border border-indigo-200 rounded-full shine-effect">
                                    <i class="fas fa-crown mr-1"></i> ผู้ดูแลระดับสูงสุด
                                </span>
                            </div>
                            <h2 class="text-3xl font-bold text-gray-800 tracking-tight">
                                ยินดีต้อนรับ, <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600">{{ Auth::user()->fullname }}</span>
                            </h2>
                            <p class="mt-2 text-gray-500 text-sm md:text-base">
                                วันนี้เป็นวันที่ดีในการเริ่มงาน จัดการระบบได้เลยครับ
                            </p>
                        </div>
                    </div>

                    {{-- Quick Action HUD (Glassmorphism) --}}
                    <div class="w-full md:w-auto grid grid-cols-2 md:grid-cols-4 gap-3 z-10">
                        <a href="{{ route('management.users.index') }}" class="group/btn flex flex-col items-center justify-center p-3 rounded-xl bg-white/60 backdrop-blur-sm hover:bg-white border border-indigo-50 hover:border-indigo-200 shadow-sm hover:shadow-md transition-all duration-300 relative overflow-hidden">
                             <div class="absolute inset-0 bg-indigo-50 opacity-0 group-hover/btn:opacity-50 transition-opacity"></div>
                            <i class="fas fa-users-cog text-indigo-500 group-hover/btn:text-indigo-600 text-xl mb-1 transition-colors relative z-10"></i>
                            <span class="text-[10px] font-bold text-gray-600 group-hover/btn:text-indigo-700 relative z-10">จัดการผู้ใช้</span>
                        </a>
                         <a href="{{ route('maintenance.index') }}" class="group/btn flex flex-col items-center justify-center p-3 rounded-xl bg-white/60 backdrop-blur-sm hover:bg-white border border-indigo-50 hover:border-indigo-200 shadow-sm hover:shadow-md transition-all duration-300 relative overflow-hidden">
                             <div class="absolute inset-0 bg-indigo-50 opacity-0 group-hover/btn:opacity-50 transition-opacity"></div>
                            <i class="fas fa-tools text-indigo-500 group-hover/btn:text-indigo-600 text-xl mb-1 transition-colors relative z-10"></i>
                            <span class="text-[10px] font-bold text-gray-600 group-hover/btn:text-indigo-700 relative z-10">แจ้งซ่อม</span>
                        </a>
                         <a href="{{ route('settings.index') }}" class="group/btn flex flex-col items-center justify-center p-3 rounded-xl bg-white/60 backdrop-blur-sm hover:bg-white border border-indigo-50 hover:border-indigo-200 shadow-sm hover:shadow-md transition-all duration-300 relative overflow-hidden">
                             <div class="absolute inset-0 bg-indigo-50 opacity-0 group-hover/btn:opacity-50 transition-opacity"></div>
                            <i class="fas fa-cogs text-indigo-500 group-hover/btn:text-indigo-600 text-xl mb-1 transition-colors relative z-10"></i>
                             <span class="text-[10px] font-bold text-gray-600 group-hover/btn:text-indigo-700 relative z-10">ตั้งค่าระบบ</span>
                        </a>
                         <a href="{{ route('changelog.index') }}" class="group/btn flex flex-col items-center justify-center p-3 rounded-xl bg-white/60 backdrop-blur-sm hover:bg-white border border-indigo-50 hover:border-indigo-200 shadow-sm hover:shadow-md transition-all duration-300 relative overflow-hidden">
                             <div class="absolute inset-0 bg-indigo-50 opacity-0 group-hover/btn:opacity-50 transition-opacity"></div>
                            <i class="fas fa-history text-indigo-500 group-hover/btn:text-indigo-600 text-xl mb-1 transition-colors relative z-10"></i>
                             <span class="text-[10px] font-bold text-gray-600 group-hover/btn:text-indigo-700 relative z-10">ประวัติ</span>
                        </a>
                    </div>
                </div>
            </div>

        {{-- 1.2 IT & Admin (Soft Tech Theme - Thai) --}}
        @elseif($userGroupSlug && in_array(strtolower(str_replace(' ', '', $userGroupSlug)), ['it', 'admin', 'administrator', 'administartor', 'itsupport', 'it-support']))
             <div class="relative overflow-hidden mb-6 rounded-2xl shadow-sm gentle-shadow animate-slide-up-soft group border border-blue-100 bg-white">
                {{-- Background with Animated Gradient Flow --}}
                <div class="absolute inset-0 bg-gradient-to-r from-white via-sky-50 to-white animate-gradient-flow opacity-80"></div>
                
                {{-- ✅ [Visual Depth] Floating Orbs --}}
                <div class="absolute top-[-50%] left-[-10%] w-80 h-80 bg-sky-200 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
                <div class="absolute bottom-[-50%] right-[-10%] w-80 h-80 bg-blue-200 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>

                {{-- ✅ [Watermark] --}}
                <div class="absolute inset-0 opacity-[0.03] bg-[url('https://www.transparenttextures.com/patterns/connected.png')] mix-blend-multiply pointer-events-none"></div>

                 <div class="absolute -right-10 -bottom-10 opacity-5 transform rotate-12">
                    <i class="fas fa-shield-alt text-9xl text-blue-400"></i>
                </div>
                
                {{-- ✅ [Real-time Clock] --}}
                <div class="absolute top-4 right-6 hidden md:flex items-center space-x-2 text-blue-400/60 z-20">
                    <i class="far fa-clock"></i>
                    <span id="live-clock-admin" class="text-sm font-mono tracking-widest">--:--:--</span>
                </div>

                <div class="relative p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center space-x-5 z-10 w-full md:w-auto">
                         <div class="relative group-hover:scale-105 transition-transform duration-500">
                             {{-- Pulse Ring --}}
                            <div class="absolute inset-0 bg-sky-400 rounded-full blur opacity-20 animate-pulse"></div>
                            
                            {{-- Profile Picture Logic --}}
                            @php
                                $profileImgIT = Auth::user()->photo_url;
                            @endphp

                            @if($profileImgIT)
                                <img src="{{ $profileImgIT }}" 
                                     alt="Profile" 
                                     class="flex-shrink-0 w-20 h-20 object-cover rounded-full border-4 border-white shadow-lg shadow-blue-200 relative z-10">
                            @else
                                <div class="flex-shrink-0 w-20 h-20 flex items-center justify-center rounded-full bg-gradient-to-br from-sky-400 to-blue-500 shadow-lg shadow-blue-200 text-white relative z-10 border-4 border-white">
                                     <i class="fas fa-user-shield text-3xl"></i>
                                </div>
                            @endif
                            <div class="absolute bottom-0 right-0 w-5 h-5 bg-green-400 border-2 border-white rounded-full z-20" title="Online"></div>
                        </div>
                        <div>
                             <div class="flex items-center gap-3 mb-1">
                                <span class="px-2 py-0.5 text-[10px] font-bold tracking-wider text-blue-700 uppercase bg-blue-100 border border-blue-200 rounded-md shine-effect">
                                    <i class="fas fa-check-circle mr-1"></i> ผู้ดูแลระบบ
                                </span>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-800">
                                สวัสดี, {{ Auth::user()->fullname }}
                            </h2>
                            <p class="mt-1 text-gray-600 text-sm">
                                สถานะระบบปกติ พร้อมสำหรับการจัดการข้อมูลวันนี้
                            </p>
                        </div>
                    </div>


                     {{-- Admin Quick Links (Soft) --}}
                     <div class="flex items-center gap-3 z-10 w-full md:w-auto justify-start md:justify-end overflow-x-auto pb-2 md:pb-0">
                        <a href="{{ route('management.users.index') }}" class="flex items-center space-x-2 px-4 py-2.5 rounded-lg bg-white/70 hover:bg-white border border-blue-200 hover:border-blue-400 transition-all text-blue-700 text-sm font-bold whitespace-nowrap shadow-sm hover:shadow-md">
                            <i class="fas fa-users"></i>
                            <span>ผู้ใช้งาน</span>
                        </a>
                        <a href="{{ route('management.groups.index') }}" class="flex items-center space-x-2 px-4 py-2.5 rounded-lg bg-white/70 hover:bg-white border border-blue-200 hover:border-blue-400 transition-all text-blue-700 text-sm font-bold whitespace-nowrap shadow-sm hover:shadow-md">
                            <i class="fas fa-key"></i>
                            <span>สิทธิ์</span>
                        </a>
                        <a href="{{ route('settings.index') }}" class="flex items-center space-x-2 px-4 py-2.5 rounded-lg bg-white/70 hover:bg-white border border-blue-200 hover:border-blue-400 transition-all text-blue-700 text-sm font-bold whitespace-nowrap shadow-sm hover:shadow-md">
                            <i class="fas fa-sliders-h"></i>
                            <span>ตั้งค่า</span>
                        </a>
                     </div>
                </div>
            </div>

        {{-- 1.3 General User --}}
        @else
            <div class="p-6 mb-6 soft-card rounded-2xl stat-card gentle-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="mb-3 text-2xl font-bold gradient-text-soft">🌸 ยินดีต้อนรับสู่ Stock Pro</h1>
                        <p class="mb-4 text-sm leading-relaxed text-gray-600">ระบบจัดการสต๊อกอุปกรณ์ แบบ Smart & Modern</p>
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

            {{-- 5.1 รอบการนับสต๊อก (REDESIGNED: Grid Layout with Letter Avatars) --}}
            <div class="col-span-full mb-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <i class="fas fa-clipboard-list mr-2 text-indigo-500"></i> รอบการนับสต๊อก (105 วัน)
                        <span class="ml-2 text-xs font-normal text-gray-500 hidden md:inline">* แสดง 20 รายการล่าสุดที่ต้องตรวจสอบ</span>
                    </h3>
                    <a href="{{ route('stock-checks.index') }}" class="text-sm font-bold text-blue-600 hover:text-blue-800">
                        ดูทั้งหมด <i class="ml-1 fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="flex flex-col space-y-4 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
                    @forelse($stockCycles as $cycle)
                        @php
                            $daysLeft = (int) $cycle->days_left; 
                            $totalCycle = 105;
                            $percent = max(0, min(100, (($totalCycle - $daysLeft) / $totalCycle) * 100));
                            if ($daysLeft < 0) $percent = 100;
                            
                            // Color Logic
                            $colors = ['red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'];
                            $colorIndex = crc32($cycle->name) % count($colors);
                            $baseColor = $colors[$colorIndex];
                            
                            // Status Logic
                            $barColor = 'bg-gradient-to-r from-green-400 to-green-500';
                            $statusText = 'ปกติ';
                            $statusBadge = 'bg-green-100 text-green-700 border-green-200';
                            $cardBorder = 'border-l-4 border-l-transparent text-gray-500 hover:border-l-gray-300';
                            $rowBg = 'bg-white hover:bg-gray-50';
                            $urgent = false;
                            
                            if($percent > 75) {
                                $barColor = 'bg-gradient-to-r from-orange-400 to-orange-500';
                                $statusText = 'ใกล้ถึงกำหนด';
                                $statusBadge = 'bg-orange-100 text-orange-800 border-orange-200';
                                $cardBorder = 'border-l-4 border-l-orange-500';
                                $rowBg = 'bg-orange-50/40 hover:bg-orange-50';
                                $urgent = true;
                            }
                            if($percent >= 95 || $daysLeft < 0) {
                                $barColor = 'bg-gradient-to-r from-red-500 to-red-600 animate-pulse';
                                $statusText = 'รายการด่วน';
                                $statusBadge = 'bg-red-100 text-red-800 border-red-200';
                                $cardBorder = 'border-l-4 border-l-red-500';
                                $rowBg = 'bg-red-50/40 hover:bg-red-50';
                                $urgent = true;
                            }
                            
                            $firstChar = mb_substr($cycle->name, 0, 1);
                        @endphp
                        
                        <div class="relative grid grid-cols-1 xl:grid-cols-12 gap-4 items-center p-4 border rounded-xl shadow-sm transition-all duration-300 group {{ $rowBg }} {{ $cardBorder }}">
                            
                            {{-- 1. Left: Avatar & Name --}}
                            <div class="xl:col-span-5 flex items-center w-full">
                                <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 text-xl font-bold text-white uppercase rounded-full shadow-md bg-gradient-to-br from-{{ $baseColor }}-400 to-{{ $baseColor }}-600 mr-3">
                                    {{ $firstChar }}
                                </div>
                                <div class="min-w-0 flex-grow">
                                    <h4 class="text-base font-bold text-gray-800 truncate" title="{{ $cycle->name }}">
                                        {{ $cycle->name }}
                                    </h4>
                                    <div class="flex items-center flex-wrap gap-2 mt-1">
                                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded-md bg-white border border-gray-200 text-gray-600 whitespace-nowrap">
                                            <i class="fas fa-box-open mr-1"></i> {{ $cycle->item_count }} รายการ
                                        </span>
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-md border {{ $statusBadge }} whitespace-nowrap">
                                            {{ $statusText }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- 2. Middle: Dates info --}}
                            <div class="xl:col-span-4 flex items-center justify-between xl:justify-center w-full text-sm text-gray-500 space-x-0 xl:space-x-6 border-t xl:border-t-0 border-b xl:border-b-0 border-gray-100 py-2 xl:py-0">
                                <div class="flex flex-col xl:items-start text-left">
                                    <span class="text-[10px] text-gray-400 font-medium">ตรวจล่าสุด</span>
                                    <span class="font-semibold text-gray-700 text-xs">
                                        <i class="far fa-clock mr-1"></i> {{ $cycle->formatted_date ?? '-' }}
                                    </span>
                                </div>
                                <div class="flex flex-col xl:items-start text-right xl:text-left">
                                    <span class="text-[10px] text-gray-400 font-medium">นัดถัดไป</span>
                                    <span class="font-semibold text-indigo-600 text-xs">
                                        <i class="far fa-calendar-alt mr-1"></i> {{ isset($cycle->next_check_date) ? \Carbon\Carbon::parse($cycle->next_check_date)->format('d/m/Y') : '-' }}
                                    </span>
                                </div>
                            </div>

                            {{-- 3. Right: Countdown & Action --}}
                            <div class="xl:col-span-3 flex items-center justify-between xl:justify-end w-full space-x-3">
                                <div class="text-right">
                                    <span class="block text-[10px] text-gray-400">เหลือเวลา</span>
                                    <span class="text-xl font-black {{ $daysLeft < 0 ? 'text-red-600' : 'text-gray-800' }}">
                                        {{ $daysLeft < 0 ? abs($daysLeft) : number_format($daysLeft) }}
                                        <span class="text-xs font-medium text-gray-500">วัน</span>
                                    </span>
                                </div>
                                <a href="{{ route('stock-checks.create', ['category_id' => $cycle->id]) }}" 
                                   class="flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-full bg-white border border-gray-200 text-gray-400 hover:text-indigo-600 hover:border-indigo-600 hover:bg-indigo-50 transition-all shadow-sm"
                                   title="ตรวจนับสต๊อกรายการนี้">
                                    <i class="fas fa-chevron-right text-sm"></i>
                                </a>
                            </div>

                            {{-- Bottom Progress Bar --}}
                            <div class="absolute bottom-0 left-0 w-full h-1 bg-gray-100 rounded-b-xl overflow-hidden">
                                <div class="{{ $barColor }} h-full transition-all duration-1000" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-12 text-center text-gray-400 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                            <i class="fas fa-check-circle text-5xl mb-3 text-green-200"></i>
                            <p class="text-lg font-medium">ไม่มีรายการที่ต้องนับในเร็วๆ นี้</p>
                            <p class="text-sm">ทุกหมวดหมู่ได้รับการตรวจสอบแล้ว</p>
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
                            <p class="text-sm font-bold text-blue-600 truncate">
                                {{ optional($item->equipment)->name ?? ($item->name ?? $item->item_description) }}
                            </p>
                            @if(isset($item->quantity_ordered))
                                <span class="text-xs text-blue-400">จำนวน: {{ $item->quantity_ordered }}</span>
                            @endif
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
                            <input type="color" id="{{ $key }}-start" name="{{ $key }}-start" class="w-full h-10 p-1 border border-gray-300 rounded-lg cursor-pointer transition-shadow hover:shadow-md" value="#ffffff">
                        </div>
                        <div>
                            <label for="{{ $key }}-end" class="block text-xs font-medium text-gray-500">สีเข้ม (End)</label>
                            <input type="color" id="{{ $key }}-end" name="{{ $key }}-end" class="w-full h-10 p-1 border border-gray-300 rounded-lg cursor-pointer transition-shadow hover:shadow-md" value="#000000">
                        </div>
                         <div>
                            <label for="{{ $key }}-border" class="block text-xs font-medium text-gray-500">สีขอบ/ตัวเลข (Border)</label>
                            <input type="color" id="{{ $key }}-border" name="{{ $key }}-border" class="w-full h-10 p-1 border border-gray-300 rounded-lg cursor-pointer transition-shadow hover:shadow-md" value="#000000">
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
        // ✅ Updated: รับค่าจาก Controller โดยตรง (แม่นยำกว่า)
        window.lockedStockCount = {{ $lockedStockCount ?? 0 }};
        window.warningStockCount = {{ $warningStockCount ?? 0 }};
        
        // ✅ Check Permissions for Notification
        @php
             $isSuperAdmin = Auth::user()->id === (int)config('app.super_admin_id', 9);
             $userGroupSlug = Auth::user()->serviceUserRole?->userGroup?->slug;
             $slugLower = $userGroupSlug ? strtolower($userGroupSlug) : '';
             $isAdminOrIT = in_array($slugLower, ['it', 'admin', 'administrator', 'itsupport', 'it-support']);
        @endphp
        window.canNotifyStock = {{ ($isSuperAdmin || $isAdminOrIT) ? 'true' : 'false' }};

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

    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Live Clock Logic
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('th-TH', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: false 
            });
            
            const superClock = document.getElementById('live-clock-super');
            const adminClock = document.getElementById('live-clock-admin');
            
            if(superClock) superClock.textContent = timeString;
            if(adminClock) adminClock.textContent = timeString;
        }
        
        setInterval(updateClock, 1000);
        updateClock(); // Initial call
        
        // ... existing chart logic ...
    });
    </script>
    </script>
    {{-- 🔥 โหลด dashboard.js ทีหลัง เพื่อให้ฟังก์ชันข้างบนเรียกใช้ได้ --}}
    <script src="{{ asset('js/dashboard.js') }}?v={{ time() }}"></script>
    <script>
        // Force reload css grid logic if needed
        window.addEventListener('resize', function() {
            if(window.dashboardChart) window.dashboardChart.resize();
        });
    </script>
@endpush