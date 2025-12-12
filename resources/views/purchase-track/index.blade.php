@extends('layouts.app')

@section('header', '📦 ติดตามสถานะการจัดซื้อ')
@section('subtitle', 'ตรวจสอบสถานะรายการสั่งซื้อจาก PU')

@section('content')
    <!-- Main Container: ปรับให้เต็มจอมากขึ้น (w-full) และเว้นขอบนิดหน่อย -->
    <div class="py-6 w-full px-2 sm:px-6 bg-gray-100 min-h-screen">

        @if($purchaseOrders->isEmpty())
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-12 text-center border-2 border-dashed border-gray-300">
                <div class="flex justify-center mb-4">
                    <div class="bg-gray-50 p-4 rounded-full">
                        <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-gray-900">ยังไม่มีประวัติการสั่งซื้อ</h3>
                <p class="text-gray-500 mt-1">รายการสั่งซื้อที่คุณส่งไปให้ PU จะปรากฏที่นี่</p>
            </div>
        @else
            <div class="space-y-8 pb-20">
                @foreach($purchaseOrders as $po)
                    <!-- Card รายการสั่งซื้อ -->
                    <div class="bg-white overflow-hidden shadow-md sm:rounded-xl border border-gray-200">
                        
                        <!-- Header -->
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-4">
                            <div class="flex items-center gap-4">
                                <div class="bg-white border border-gray-200 p-2.5 rounded-lg shadow-sm">
                                    <span class="text-xs font-bold text-gray-500 block text-center leading-none">PO</span>
                                    <span class="text-sm font-bold text-indigo-600 block text-center leading-none mt-0.5">#{{ $po->id }}</span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800 tracking-tight">{{ $po->po_number }}</h3>
                                    <div class="text-sm text-gray-500 mt-0.5 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        {{ $po->created_at->format('d/m/Y H:i') }}
                                        <span class="text-gray-300">|</span>
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        {{ $po->orderedBy->name ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ปุ่ม Action -->
                            <div>
                                @if($po->status == 'shipped_from_supplier' || $po->status == 'partial_receive')
                                    <a href="{{ route('receive.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        ไปตรวจรับของ
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Timeline / Progress Bar -->
                            <div class="mb-10 mt-2 px-4">
                                @php
                                    $steps = [
                                        'ordered' => ['label' => 'สั่งซื้อแล้ว', 'desc' => 'ส่งใบสั่งซื้อให้ PU', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                                        'shipped_from_supplier' => ['label' => 'อยู่ระหว่างจัดส่ง', 'desc' => 'PU แจ้งส่งของ', 'icon' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0'],
                                        'completed' => ['label' => 'รับของสำเร็จ', 'desc' => 'เข้าสต๊อกเรียบร้อย', 'icon' => 'M5 13l4 4L19 7']
                                    ];

                                    $currentStatus = $po->status;
                                    if ($currentStatus == 'partial_receive') $currentStatus = 'shipped_from_supplier'; 
                                    if ($currentStatus == 'pending' || $currentStatus == 'approved') $currentStatus = 'ordered';

                                    $statusKeys = array_keys($steps);
                                    $currentIndex = array_search($currentStatus, $statusKeys);
                                    if ($currentIndex === false) $currentIndex = 0;
                                    if ($po->status == 'completed') $currentIndex = 2;
                                @endphp

                                <div class="relative">
                                    <div class="absolute top-5 left-0 w-full h-1 bg-gray-200 -translate-y-1/2 rounded-full z-0"></div>
                                    <div class="absolute top-5 left-0 h-1 bg-green-500 -translate-y-1/2 rounded-full z-0 transition-all duration-1000 ease-out" style="width: {{ ($currentIndex / (count($steps)-1)) * 100 }}%;"></div>

                                    <div class="relative z-10 flex justify-between w-full">
                                        @foreach($steps as $key => $step)
                                            @php 
                                                $stepIndex = array_search($key, $statusKeys);
                                                $isActive = $stepIndex <= $currentIndex;
                                                $isCurrent = $stepIndex === $currentIndex;
                                            @endphp
                                            <div class="flex flex-col items-center group w-1/3">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center border-4 transition-all duration-300 z-10 bg-white
                                                    {{ $isActive ? 'border-green-500 text-green-600 shadow-md scale-110' : 'border-gray-300 text-gray-300' }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step['icon'] }}"/></svg>
                                                </div>
                                                <div class="mt-3 text-center">
                                                    <div class="text-sm font-bold {{ $isActive ? 'text-gray-900' : 'text-gray-400' }}">{{ $step['label'] }}</div>
                                                    <div class="text-xs text-gray-500 hidden sm:block mt-0.5">{{ $step['desc'] }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- รายการสินค้า -->
                            <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                                <h4 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2 uppercase tracking-wide">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    รายการสินค้า ({{ $po->items->count() }})
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                                    @foreach($po->items as $item)
                                        @php
                                            $equip = $item->equipment;
                                            $img = ($equip && $equip->latestImage) ? $equip->latestImage->image_url : asset('images/placeholder.webp');
                                            $itemName = $item->item_description ?? ($equip ? $equip->name : 'สินค้าไม่ระบุชื่อ');
                                        @endphp
                                        <div class="flex items-start gap-4 bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow h-full">
                                            <div class="h-16 w-16 rounded-lg bg-gray-100 border border-gray-200 flex-shrink-0 overflow-hidden">
                                                <img src="{{ $img }}" class="h-full w-full object-cover">
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-bold text-gray-900 line-clamp-2 leading-snug" title="{{ $itemName }}">
                                                    {{ $itemName }}
                                                </p>
                                                <div class="mt-2 flex items-center gap-3 text-xs">
                                                    <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-medium whitespace-nowrap">
                                                        สั่ง {{ $item->quantity_ordered }}
                                                    </span>
                                                    @if($item->quantity_received > 0)
                                                        <span class="text-green-600 flex items-center gap-1 font-medium whitespace-nowrap">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                            รับแล้ว {{ $item->quantity_received }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        </div>
                    </div>
                @endforeach

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $purchaseOrders->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection