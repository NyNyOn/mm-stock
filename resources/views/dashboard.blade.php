@extends('layouts.app')

@section('header', 'Dashboard')
@section('subtitle', 'ภาพรวมของระบบสต็อกอุปกรณ์')

@section('content')
<div class="space-y-6">

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Total Equipment --}}
        <div class="relative p-5 overflow-hidden transition-all duration-300 bg-white border border-blue-100 shadow-sm rounded-2xl hover:shadow-lg group">
            <div class="absolute top-0 right-0 w-32 h-32 -mr-16 -mt-16 bg-blue-50 rounded-full blur-3xl opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <div class="relative flex items-center">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-xl text-blue-600 bg-blue-100 rounded-2xl">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="ml-4">
                    <p class="text-xs font-bold tracking-wider text-gray-400 uppercase">อุปกรณ์ทั้งหมด</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($total_equipment ?? 0) }}</p>
                </div>
            </div>
        </div>

        {{-- Low Stock --}}
        <div class="relative p-5 overflow-hidden transition-all duration-300 bg-white border border-orange-100 shadow-sm rounded-2xl hover:shadow-lg group">
            <div class="absolute top-0 right-0 w-32 h-32 -mr-16 -mt-16 bg-orange-50 rounded-full blur-3xl opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <div class="relative flex items-center">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-xl text-orange-600 bg-orange-100 rounded-2xl">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="ml-4">
                    <p class="text-xs font-bold tracking-wider text-gray-400 uppercase">สต็อกใกล้หมด</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($low_stock_count ?? 0) }}</p>
                </div>
            </div>
        </div>

        {{-- On Order --}}
        <div class="relative p-5 overflow-hidden transition-all duration-300 bg-white border border-indigo-100 shadow-sm rounded-2xl hover:shadow-lg group">
            <div class="absolute top-0 right-0 w-32 h-32 -mr-16 -mt-16 bg-indigo-50 rounded-full blur-3xl opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <div class="relative flex items-center">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-xl text-indigo-600 bg-indigo-100 rounded-2xl">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="ml-4">
                    <p class="text-xs font-bold tracking-wider text-gray-400 uppercase">กำลังสั่งซื้อ</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($on_order_count ?? 0) }}</p>
                </div>
            </div>
        </div>

        {{-- Warranty --}}
        <div class="relative p-5 overflow-hidden transition-all duration-300 bg-white border border-purple-100 shadow-sm rounded-2xl hover:shadow-lg group">
            <div class="absolute top-0 right-0 w-32 h-32 -mr-16 -mt-16 bg-purple-50 rounded-full blur-3xl opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <div class="relative flex items-center">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-xl text-purple-600 bg-purple-100 rounded-2xl">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="ml-4">
                    <p class="text-xs font-bold tracking-wider text-gray-400 uppercase">ใกล้หมดประกัน</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($warranty_count ?? 0) }}</p>
                </div>
            </div>
        </div>

        {{-- Urgent Order --}}
        <div class="relative p-5 overflow-hidden transition-all duration-300 bg-white border border-red-100 shadow-sm rounded-2xl hover:shadow-lg group">
            <div class="absolute top-0 right-0 w-32 h-32 -mr-16 -mt-16 bg-red-50 rounded-full blur-3xl opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <div class="relative flex items-center">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-xl text-red-600 bg-red-100 rounded-2xl">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="ml-4">
                    <p class="text-xs font-bold tracking-wider text-gray-400 uppercase">สั่งซื้อด่วน</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($urgent_order_count ?? 0) }}</p>
                </div>
            </div>
        </div>

        {{-- Scheduled Order --}}
        <div class="relative p-5 overflow-hidden transition-all duration-300 bg-white border border-cyan-100 shadow-sm rounded-2xl hover:shadow-lg group">
            <div class="absolute top-0 right-0 w-32 h-32 -mr-16 -mt-16 bg-cyan-50 rounded-full blur-3xl opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <div class="relative flex items-center">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-xl text-cyan-600 bg-cyan-100 rounded-2xl">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="ml-4">
                    <p class="text-xs font-bold tracking-wider text-gray-400 uppercase">สั่งซื้อตามรอบ</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($scheduled_order_count ?? 0) }}</p>
                </div>
            </div>
        </div>

        {{-- Total Received --}}
        <div class="relative p-5 overflow-hidden transition-all duration-300 bg-white border border-green-100 shadow-sm rounded-2xl hover:shadow-lg group">
            <div class="absolute top-0 right-0 w-32 h-32 -mr-16 -mt-16 bg-green-50 rounded-full blur-3xl opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <div class="relative flex items-center">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-xl text-green-600 bg-green-100 rounded-2xl">
                    <i class="fas fa-dolly-flatbed"></i>
                </div>
                <div class="ml-4">
                    <p class="text-xs font-bold tracking-wider text-gray-400 uppercase">รับเข้าทั้งหมด</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($total_received_count ?? 0) }}</p>
                </div>
            </div>
        </div>

        {{-- Stock Cycle Summary --}}
        @php
            $cycleIssues = collect($stockCycles)->whereIn('status', ['warning', 'locked']);
            $hasCycleIssues = $cycleIssues->count() > 0;
            $cardColor = $hasCycleIssues ? 'red' : 'emerald';
            $cardIcon = $hasCycleIssues ? 'fa-clipboard-list' : 'fa-clipboard-check';
            $cycleLabel = $hasCycleIssues ? 'ต้องนับสต๊อก' : 'สถานะปกติ';
        @endphp
        <div class="relative p-5 overflow-hidden transition-all duration-300 bg-white border border-{{ $cardColor }}-100 shadow-sm rounded-2xl hover:shadow-lg group">
            <div class="absolute top-0 right-0 w-32 h-32 -mr-16 -mt-16 bg-{{ $cardColor }}-50 rounded-full blur-3xl opacity-50 group-hover:scale-150 transition-transform duration-700"></div>
            <div class="relative flex items-center">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-xl text-{{ $cardColor }}-600 bg-{{ $cardColor }}-100 rounded-2xl animate-pulse">
                    <i class="fas {{ $cardIcon }}"></i>
                </div>
                <div class="ml-4">
                    <p class="text-xs font-bold tracking-wider text-gray-400 uppercase">รอบนับสต๊อก</p>
                    <p class="text-2xl font-bold text-gray-800">
                        {{ $hasCycleIssues ? $cycleIssues->count() . ' รายการ' : 'เรียบร้อย' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="p-6 lg:col-span-3 soft-card rounded-2xl gentle-shadow">
            <h3 class="text-lg font-bold text-gray-800">การเคลื่อนไหวของสต็อก (7 วันล่าสุด)</h3>
            <canvas id="lineChart" class="mt-4"></canvas>
        </div>
        <div class="p-6 lg:col-span-2 soft-card rounded-2xl gentle-shadow">
            <h3 class="text-lg font-bold text-gray-800">อุปกรณ์ตามประเภท</h3>
            <canvas id="doughnutChart" class="mt-4"></canvas>
        </div>
    </div>

    {{-- Stock Cycle Detail Section --}}
    <div class="p-6 soft-card rounded-2xl gentle-shadow">
        <div class="flex flex-wrap items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">
                <i class="mr-2 text-blue-500 fas fa-clock"></i> สถานะรอบการนับสต๊อก (105 วัน)
                <span class="ml-2 text-xs font-normal text-gray-500">* นับครั้งล่าสุดจากสินค้าที่เก่าที่สุดในหมวด</span>
            </h3>
            <a href="{{ route('stock-check.index') }}" class="text-sm font-bold text-blue-600 hover:text-blue-800">
                ดูทั้งหมด <i class="ml-1 fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
            @forelse($stockCycles as $cycle)
                @php
                    $daysLeft = $cycle->days_left;
                    $totalCycle = 105;
                    $percent = max(0, min(100, (($totalCycle - $daysLeft) / $totalCycle) * 100));
                    if ($daysLeft < 0) $percent = 100;
                    
                    // Avatar Color Logic
                    $colors = ['red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'];
                    $colorIndex = crc32($cycle->name) % count($colors);
                    $baseColor = $colors[$colorIndex];
                    
                    // Status Color Logic
                    $barColor = 'bg-green-500';
                    $statusText = 'ปกติ';
                    $statusClass = 'text-green-600 bg-green-50';
                    
                    if($percent > 75) {
                        $barColor = 'bg-orange-400';
                        $statusText = 'ใกล้ถึง';
                        $statusClass = 'text-orange-600 bg-orange-50';
                    }
                    if($percent >= 95 || $daysLeft < 0) {
                        $barColor = 'bg-red-500 animate-pulse';
                        $statusText = 'ถึงกำหนด';
                        $statusClass = 'text-red-600 bg-red-50';
                    }
                    
                    $firstChar = mb_substr($cycle->name, 0, 1);
                @endphp
                
                <div class="relative flex flex-col p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            {{-- Avatar --}}
                            <div class="flex items-center justify-center w-12 h-12 text-xl font-bold text-white uppercase rounded-xl shadow-sm bg-gradient-to-br from-{{ $baseColor }}-400 to-{{ $baseColor }}-500">
                                {{ $firstChar }}
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-800 line-clamp-1" title="{{ $cycle->name }}">
                                    {{ $cycle->name }}
                                </h4>
                                <span class="text-xs text-gray-500">{{ $cycle->item_count }} รายการ</span>
                            </div>
                        </div>
                        <span class="px-2 py-1 text-[10px] font-bold rounded-lg {{ $statusClass }}">
                            {{ $statusText }}
                        </span>
                    </div>
                    
                    <div class="mt-auto">
                        <div class="flex justify-between items-end mb-1">
                            <span class="text-xs font-medium text-gray-400">เหลือเวลา</span>
                            <span class="text-xs font-bold {{ $daysLeft < 0 ? 'text-red-500' : 'text-gray-600' }}">
                                {{ $daysLeft < 0 ? abs($daysLeft) . ' วัน (เกิน)' : $daysLeft . ' วัน' }}
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                            <div class="{{ $barColor }} h-2 rounded-full transition-all duration-1000" style="width: {{ $percent }}%"></div>
                        </div>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let lineChartInstance, doughnutChartInstance;

    async function fetchChartData() {
        try {
            const response = await fetch("{{ route('ajax.dashboard.charts') }}");
            const data = await response.json();
            renderLineChart(data.lineChartData);
            renderDoughnutChart(data.doughnutChartData);
        } catch (error) {
            console.error("Could not fetch chart data:", error);
        }
    }

    function renderLineChart(data) {
        const ctx = document.getElementById('lineChart').getContext('2d');
        if (lineChartInstance) lineChartInstance.destroy();
        lineChartInstance = new Chart(ctx, {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    function renderDoughnutChart(data) {
        const ctx = document.getElementById('doughnutChart').getContext('2d');
        if (doughnutChartInstance) doughnutChartInstance.destroy();
        doughnutChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
            }
        });
    }

    fetchChartData();
});
</script>
@endpush
