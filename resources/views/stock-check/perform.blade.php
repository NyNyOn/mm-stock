@extends('layouts.app')

@section('header', 'ดำเนินการตรวจนับสต็อก')
@section('subtitle', $stockCheck->name)

@section('content')
<div class="space-y-6 page animate-slide-up-soft">

    @if (session('success'))
        <div class="p-4 text-green-800 bg-green-100 border-l-4 border-green-500 rounded-r-lg" role="alert">
            <p>{{ session('success') }}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="p-4 text-red-800 bg-red-100 border-l-4 border-red-500 rounded-r-lg" role="alert">
            <p>{{ session('error') }}</p>
        </div>
    @endif


    <form action="{{ route('stock-checks.update', $stockCheck->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="overflow-hidden soft-card rounded-2xl gentle-shadow">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-800">รายการอุปกรณ์ที่ต้องนับ</h3>
                <p class="text-sm text-gray-500">กรุณากรอกจำนวนที่นับได้จริงในช่อง "จำนวนที่นับได้" หากไม่พบอุปกรณ์ให้ใส่ 0</p>
            </div>
            <div class="overflow-x-auto scrollbar-soft">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-sm font-medium text-left text-gray-600">อุปกรณ์</th>
                            <th class="px-4 py-3 text-sm font-medium text-center text-gray-600">จำนวนในระบบ</th>
                            <th class="px-4 py-3 text-sm font-medium text-center text-gray-600" style="width: 150px;">จำนวนที่นับได้</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($items as $item)
                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    {{ $item->equipment->name ?? 'อุปกรณ์ถูกลบ' }}
                                    <p class="text-xs font-normal text-gray-500">{{ $item->equipment->serial_number ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-lg font-bold text-center text-blue-600">{{ $item->expected_quantity }}</td>
                                <td class="px-4 py-3">
                                    <!-- จุดแก้ไขที่ 1: ใช้ ?? 0 เพื่อแสดงค่าที่บันทึกไว้ หรือ 0 ถ้ายังไม่มีค่า -->
                                    <input type="number" name="items[{{ $item->id }}][counted_quantity]" value="{{ $item->counted_quantity ?? 0 }}" class="w-full px-2 py-1 text-center border rounded-lg focus:ring-blue-500 focus:border-blue-500" min="0">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- จุดแก้ไขที่ 2: ลบส่วน pagination links ทิ้ง -->
            {{-- 
            @if ($items->hasPages())
                <div class="px-5 py-4 border-t border-gray-200 bg-gray-50">{{ $items->links() }}</div>
            @endif 
            --}}

        </div>

        <div class="flex justify-between pt-6 mt-4 border-t">
            <a href="{{ route('stock-checks.index') }}" class="px-4 py-2 font-bold text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                กลับไปหน้ารายการ
            </a>
            <div class="space-x-2">
                <button type="submit" name="save_progress" class="px-4 py-2 font-bold text-gray-700 bg-yellow-400 rounded-lg hover:bg-yellow-500">
                    บันทึกความคืบหน้า
                </button>
                <button type="submit" name="complete_check" class="px-4 py-2 font-bold text-white bg-green-500 rounded-lg hover:bg-green-600">
                    ตรวจนับเสร็จสิ้น
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
