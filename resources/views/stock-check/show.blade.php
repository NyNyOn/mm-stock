@extends('layouts.app')

@section('header', 'ผลการตรวจนับสต็อก')
@section('subtitle', $stockCheck->name)

@section('content')
<div class="space-y-6 page animate-slide-up-soft">

    {{-- Summary Card --}}
    <div class="p-5 soft-card rounded-2xl gentle-shadow">
        <h3 class="pb-3 mb-3 text-lg font-bold text-gray-800 border-b">สรุปผลการตรวจนับ</h3>
        <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
            <div>
                <p class="text-gray-500">วันที่กำหนด</p>
                <p class="font-bold text-gray-800">{{ \Carbon\Carbon::parse($stockCheck->scheduled_date)->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-gray-500">วันที่เสร็จสิ้น</p>
                <p class="font-bold text-gray-800">{{ \Carbon\Carbon::parse($stockCheck->completed_at)->format('d/m/Y H:i') }}</p>
            </div>
            <div>
                <p class="text-gray-500">ผู้ตรวจนับ</p>
                <p class="font-bold text-gray-800">{{ optional($stockCheck->checker)->fullname ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-gray-500">จำนวนผลต่าง</p>
                <p class="font-bold text-red-500">{{ $stockCheck->items->where('discrepancy', '!=', 0)->count() }} รายการ</p>
            </div>
        </div>
    </div>

    {{-- Results Table --}}
    <div class="overflow-hidden soft-card rounded-2xl gentle-shadow">
        <div class="p-5 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-800">รายละเอียดผลการตรวจนับ</h3>
        </div>
        <div class="overflow-x-auto scrollbar-soft">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-sm font-medium text-left text-gray-600">อุปกรณ์</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-gray-600">จำนวนในระบบ</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-gray-600">จำนวนที่นับได้</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-gray-600">ผลต่าง</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($stockCheck->items as $item)
                        {{-- ไฮไลท์แถวที่มีผลต่าง --}}
                        <tr class="transition-colors {{ $item->discrepancy != 0 ? 'bg-red-50' : 'hover:bg-gray-50' }}">
                            <td class="px-4 py-3 font-medium text-gray-800">
                                {{ $item->equipment->name ?? 'อุปกรณ์ถูกลบ' }}
                                <p class="text-xs font-normal text-gray-500">{{ $item->equipment->serial_number ?? '' }}</p>
                            </td>
                            <td class="px-4 py-3 text-lg font-bold text-center text-gray-600">{{ $item->expected_quantity }}</td>
                            <td class="px-4 py-3 text-lg font-bold text-center text-blue-600">{{ $item->counted_quantity }}</td>
                            <td class="px-4 py-3 text-lg font-bold text-center {{ $item->discrepancy == 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $item->discrepancy > 0 ? '+' : '' }}{{ $item->discrepancy }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="pt-4 text-right">
        <a href="{{ route('stock-checks.index') }}" class="px-4 py-2 font-bold text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
            กลับไปหน้ารายการ
        </a>
    </div>
</div>
@endsection
