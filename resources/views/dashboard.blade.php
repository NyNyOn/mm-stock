@extends('layouts.app')

@section('header', 'Dashboard')
@section('subtitle', 'ภาพรวมของระบบสต็อกอุปกรณ์')

@section('content')
<div class="space-y-6">

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-5 text-center soft-card rounded-2xl gentle-shadow">
            <div class="flex items-center justify-center mx-auto w-14 h-14 bg-gradient-to-br from-blue-100 to-blue-200 rounded-xl gentle-shadow">
                <i class="text-2xl text-blue-500 fas fa-boxes"></i>
            </div>
            <p class="mt-4 text-3xl font-bold text-blue-600">{{ $total_equipment ?? 0 }}</p>
            <p class="mt-1 text-sm font-semibold text-gray-600">อุปกรณ์ทั้งหมด (ชิ้น)</p>
        </div>

        <div class="p-5 text-center soft-card rounded-2xl gentle-shadow">
            <div class="flex items-center justify-center mx-auto w-14 h-14 bg-gradient-to-br from-orange-100 to-orange-200 rounded-xl gentle-shadow">
                <i class="text-2xl text-orange-500 fas fa-exclamation-triangle"></i>
            </div>
            <p class="mt-4 text-3xl font-bold text-orange-600">{{ $low_stock_count ?? 0 }}</p>
            <p class="mt-1 text-sm font-semibold text-gray-600">สต็อกใกล้หมด (รายการ)</p>
        </div>

        <div class="p-5 text-center soft-card rounded-2xl gentle-shadow">
            <div class="flex items-center justify-center mx-auto w-14 h-14 bg-gradient-to-br from-indigo-100 to-indigo-200 rounded-xl gentle-shadow">
                <i class="text-2xl text-indigo-500 fas fa-shipping-fast"></i>
            </div>
            <p class="mt-4 text-3xl font-bold text-indigo-600">{{ $on_order_count ?? 0 }}</p>
            <p class="mt-1 text-sm font-semibold text-gray-600">กำลังสั่งซื้อ (รายการ)</p>
        </div>

        <div class="p-5 text-center soft-card rounded-2xl gentle-shadow">
            <div class="flex items-center justify-center mx-auto w-14 h-14 bg-gradient-to-br from-purple-100 to-purple-200 rounded-xl gentle-shadow">
                <i class="text-2xl text-purple-500 fas fa-calendar-check"></i>
            </div>
            <p class="mt-4 text-3xl font-bold text-purple-600">{{ $warranty_count ?? 0 }}</p>
            <p class="mt-1 text-sm font-semibold text-gray-600">ใกล้หมดประกัน (30 วัน)</p>
        </div>

        <div class="p-5 text-center soft-card rounded-2xl gentle-shadow">
            <div class="flex items-center justify-center mx-auto w-14 h-14 bg-gradient-to-br from-red-100 to-red-200 rounded-xl gentle-shadow">
                <i class="text-2xl text-red-500 fas fa-bolt"></i>
            </div>
            <p class="mt-4 text-3xl font-bold text-red-600">{{ $urgent_order_count ?? 0 }}</p>
            <p class="mt-1 text-sm font-semibold text-gray-600">สั่งซื้อด่วน (ครั้ง)</p>
        </div>

        <div class="p-5 text-center soft-card rounded-2xl gentle-shadow">
            <div class="flex items-center justify-center mx-auto w-14 h-14 bg-gradient-to-br from-cyan-100 to-cyan-200 rounded-xl gentle-shadow">
                <i class="text-2xl text-cyan-500 fas fa-calendar-alt"></i>
            </div>
            <p class="mt-4 text-3xl font-bold text-cyan-600">{{ $scheduled_order_count ?? 0 }}</p>
            <p class="mt-1 text-sm font-semibold text-gray-600">สั่งซื้อตามรอบ (ครั้ง)</p>
        </div>

        <div class="p-5 text-center soft-card rounded-2xl gentle-shadow">
            <div class="flex items-center justify-center mx-auto w-14 h-14 bg-gradient-to-br from-green-100 to-green-200 rounded-xl gentle-shadow">
                <i class="text-2xl text-green-500 fas fa-dolly-flatbed"></i>
            </div>
            <p class="mt-4 text-3xl font-bold text-green-600">{{ $total_received_count ?? 0 }}</p>
            <p class="mt-1 text-sm font-semibold text-gray-600">รับเข้าทั้งหมด (ชิ้น)</p>
        </div>

        <div class="p-5 text-center soft-card rounded-2xl gentle-shadow">
            <div class="flex items-center justify-center mx-auto w-14 h-14 bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl gentle-shadow">
                <i class="text-2xl text-gray-500 fas fa-question-circle"></i>
            </div>
            <p class="mt-4 text-3xl font-bold text-gray-600">--</p>
            <p class="mt-1 text-sm font-semibold text-gray-600">รอข้อมูล</p>
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
