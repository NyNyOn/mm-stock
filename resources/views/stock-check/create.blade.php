@extends('layouts.app')

@section('header', 'สร้างงานตรวจนับสต็อก')
@section('subtitle', 'กำหนดรอบการตรวจนับสต็อกใหม่')

@section('content')
<div class="container p-4 mx-auto">
    <div class="max-w-2xl mx-auto">
        <div class="soft-card rounded-2xl gentle-shadow">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-800">รายละเอียดงานตรวจนับ</h3>
            </div>
            <div class="p-5">
                <form action="{{ route('stock-checks.store') }}" method="POST">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block mb-2 font-bold text-gray-700">ชื่องานตรวจนับ</label>
                            {{-- สร้างชื่อแนะนำอัตโนมัติจากเดือนและปีปัจจุบัน --}}
                            <input type="text" name="name" id="name" value="ตรวจนับสต็อก ประจำเดือน {{ now()->format('F Y') }}" class="w-full px-3 py-2 border rounded-lg" required>
                        </div>
                        <div>
                            <label for="scheduled_date" class="block mb-2 font-bold text-gray-700">วันที่กำหนดตรวจนับ</label>
                            <input type="date" name="scheduled_date" id="scheduled_date" value="{{ now()->format('Y-m-d') }}" class="w-full px-3 py-2 border rounded-lg" required>
                        </div>
                    </div>
                    <div class="flex justify-end pt-6 mt-6 border-t">
                        <a href="{{ route('stock-checks.index') }}" class="px-4 py-2 mr-2 font-bold text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                            ยกเลิก
                        </a>
                        <button type="submit" class="px-4 py-2 font-bold text-white bg-blue-500 rounded-lg hover:bg-blue-600">
                            <i class="mr-1 fas fa-save"></i>
                            สร้างและเตรียมรายการ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
