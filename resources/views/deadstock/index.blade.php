@extends('layouts.app')
@section('header', '🕸️ Deadstock Report')
@section('subtitle', 'รายการอุปกรณ์ที่ไม่มีการเคลื่อนไหว')

@section('content')
<div class="space-y-6 page animate-slide-up-soft">
    
    {{-- Filter Bar --}}
    <div class="p-6 mb-6 bg-white rounded-2xl gentle-shadow">
        <div class="flex items-center mb-4">
            <div class="flex items-center justify-center w-10 h-10 mr-4 bg-orange-100 rounded-full">
                <i class="text-orange-600 fas fa-spider"></i>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-800">ตัวกรอง Deadstock</h3>
                <p class="text-sm text-gray-500">ค้นหาสินค้าที่นิ่งเกินจำนวนวันที่กำหนด</p>
            </div>
        </div>

        <form method="GET" action="{{ route('deadstock.index') }}" class="flex flex-col gap-4 md:flex-row md:items-end">
            <div class="w-full md:w-1/3">
                <label class="block mb-2 text-sm font-bold text-gray-700">📦 เลือกความเก่า (จำนวนวันที่นิ่ง)</label>
                <select name="days" class="w-full border-gray-300 rounded-xl focus:border-gray-500 focus:ring-gray-500" onchange="this.form.submit()">
                    <option value="30" {{ $daysInactive == 30 ? 'selected' : '' }}>30 วันขึ้นไป (เริ่มนิ่ง)</option>
                    <option value="90" {{ $daysInactive == 90 ? 'selected' : '' }}>90 วันขึ้นไป (ไตรมาส)</option>
                    <option value="180" {{ $daysInactive == 180 ? 'selected' : '' }}>180 วันขึ้นไป (ครึ่งปี)</option>
                    <option value="365" {{ $daysInactive == 365 ? 'selected' : '' }}>365 วันขึ้นไป (1 ปี !!)</option>
                </select>
            </div>
            <div class="w-full md:w-1/3">
                <label class="block mb-2 text-sm font-bold text-gray-700">📁 หมวดหมู่</label>
                <select name="category_id" class="w-full border-gray-300 rounded-xl focus:border-gray-500 focus:ring-gray-500" onchange="this.form.submit()">
                    <option value="">ทุกหมวดหมู่</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-auto">
                <a href="{{ route('deadstock.index') }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-gray-600 transition-colors bg-gray-100 rounded-xl hover:bg-gray-200">
                    <i class="fas fa-sync-alt mr-2"></i> รีเซ็ต
                </a>
            </div>
        </form>
    </div>

    {{-- Result Cards --}}
    <div class="grid grid-cols-1 gap-6">
        @if($deadstockItems->isEmpty())
            <div class="p-10 text-center bg-white rounded-3xl gentle-shadow">
                <div class="mb-4 text-6xl text-green-200">
                    <i class="fas fa-broom"></i>
                </div>
                <h3 class="text-xl font-bold text-green-600">สต๊อกสะอาดมาก!</h3>
                <p class="text-gray-500">ไม่พบรายการ Deadstock ตามเงื่อนไขที่เลือก ({{ $daysInactive }} วัน)</p>
            </div>
        @else
            {{-- Summary Card --}}
            <div class="p-4 mb-2 border-l-4 border-orange-400 bg-orange-50 rounded-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-orange-800">พบรายการค้างสต๊อก</p>
                        <p class="text-2xl font-bold text-orange-900">{{ $deadstockItems->total() }} รายการ</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-orange-600">เงื่อนไข</p>
                        <p class="text-sm font-bold text-orange-800">นิ่งเกิน {{ $daysInactive }} วัน</p>
                    </div>
                </div>
            </div>

            {{-- Item List --}}
            <div class="overflow-hidden bg-white gentle-shadow rounded-2xl">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold tracking-wider text-left text-gray-500 uppercase">#</th>
                            <th class="px-6 py-4 text-xs font-bold tracking-wider text-center text-gray-500 uppercase">รูปภาพ</th>
                            <th class="px-6 py-4 text-xs font-bold tracking-wider text-left text-gray-500 uppercase">สินค้า / อุปกรณ์</th>
                            <th class="px-6 py-4 text-xs font-bold tracking-wider text-center text-gray-500 uppercase">คงเหลือ</th>
                            <th class="px-6 py-4 text-xs font-bold tracking-wider text-left text-gray-500 uppercase">เคลื่อนไหวล่าสุด</th>
                            <th class="px-6 py-4 text-xs font-bold tracking-wider text-center text-gray-500 uppercase">ระยะเวลานิ่ง (วัน)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($deadstockItems as $item)
                            <tr class="transition-colors hover:bg-gray-50 group">
                                <td class="px-6 py-4 text-gray-500">{{ $loop->iteration + ($deadstockItems->currentPage() - 1) * $deadstockItems->perPage() }}</td>
                                <td class="px-6 py-4 flex justify-center">
                                    {{-- ใช้ image_url จาก Model ได้เลย --}}
                                    <div class="relative w-12 h-12 rounded-lg overflow-hidden border border-gray-200 cursor-pointer" 
                                         onclick="showDetailsModal({{ $item->id }})">
                                        <img src="{{ $item->image_url }}" 
                                             alt="{{ $item->name }}" 
                                             class="w-full h-full object-cover"
                                             onerror="this.onerror=null; this.src='{{ asset('images/placeholder.webp') }}';">
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <div class="text-sm font-bold text-indigo-700 cursor-pointer hover:underline" onclick="showDetailsModal({{ $item->id }})">
                                            {{ $item->name }}
                                        </div>
                                        <div class="text-xs text-gray-500">{{ optional($item->category)->name }}</div>
                                        <div class="text-xs text-gray-400 font-mono">{{ $item->serial_number ?? '-' }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $item->quantity }} {{ optional($item->unit)->name ?? 'ชิ้น' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    {{ \Carbon\Carbon::parse($item->last_movement_date)->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    @php
                                        $days = $item->days_silent;
                                        $badgeColor = match(true) {
                                            $days >= 365 => 'bg-red-100 text-red-800',
                                            $days >= 180 => 'bg-orange-100 text-orange-800',
                                            default => 'bg-yellow-100 text-yellow-800'
                                        };
                                    @endphp
                                    <span class="px-3 py-1 text-sm font-bold rounded-lg {{ $badgeColor }}">
                                        {{ number_format($days) }} วัน
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $deadstockItems->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Include Modal --}}
@include('partials.modals.equipment-details')

@endsection

@push('scripts')
{{-- Load Equipment JS for Modal --}}
<script src="{{ asset('js/equipment.js') }}"></script>
@endpush