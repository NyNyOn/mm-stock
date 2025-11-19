@extends('layouts.app')

@section('header', 'ระบบตรวจนับสต็อก')
@section('subtitle', 'รายการงานตรวจนับสต็อกทั้งหมดในระบบ')

@section('content')
<div class="space-y-6 page animate-slide-up-soft">

    <div class="flex items-center justify-between">
        <div></div>
        <a href="{{ route('stock-checks.create') }}" class="flex items-center px-4 py-2 font-bold text-white bg-green-500 rounded-lg hover:bg-green-600">
            <i class="mr-2 fas fa-plus-circle"></i>
            สร้างงานตรวจนับใหม่
        </a>
    </div>

    <div class="overflow-hidden soft-card rounded-2xl gentle-shadow">
        <div class="p-5 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-800">ประวัติการตรวจนับสต็อก</h3>
        </div>
        <div class="overflow-x-auto scrollbar-soft">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-sm font-medium text-left text-gray-600">ชื่องาน</th>
                        <th class="px-4 py-3 text-sm font-medium text-left text-gray-600">วันที่กำหนด</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-gray-600">สถานะ</th>
                        <th class="px-4 py-3 text-sm font-medium text-left text-gray-600">ผู้ตรวจนับ</th>
                        <th class="px-4 py-3 text-sm font-medium text-left text-gray-600">วันที่เสร็จสิ้น</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-gray-600">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($stockChecks as $check)
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="px-4 py-3 font-bold text-gray-800">{{ $check->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ \Carbon\Carbon::parse($check->scheduled_date)->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($check->status == 'scheduled')
                                    <span class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-200 rounded-full">วางแผนแล้ว</span>
                                @elseif($check->status == 'in_progress')
                                    <span class="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full">กำลังดำเนินการ</span>
                                @elseif($check->status == 'completed')
                                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">เสร็จสมบูรณ์</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ optional($check->checker)->fullname ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $check->completed_at ? \Carbon\Carbon::parse($check->completed_at)->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                @if($check->status == 'scheduled')
                                    <a href="{{ route('stock-checks.perform', $check->id) }}" class="px-3 py-1 text-xs font-medium text-white bg-blue-500 rounded-lg hover:bg-blue-600">
                                        เริ่มตรวจนับ
                                    </a>
                                @elseif($check->status == 'in_progress')
                                     <a href="{{ route('stock-checks.perform', $check->id) }}" class="px-3 py-1 text-xs font-medium text-white bg-yellow-500 rounded-lg hover:bg-yellow-600">
                                        ทำต่อ
                                    </a>
                                @elseif($check->status == 'completed')
                                    <a href="{{ route('stock-checks.show', $check->id) }}" class="px-3 py-1 text-xs font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                                        ดูผลลัพธ์
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500">ยังไม่มีประวัติการตรวจนับสต็อก</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($stockChecks->hasPages())
            <div class="px-5 py-4 border-t border-gray-200 bg-gray-50">{{ $stockChecks->links() }}</div>
        @endif
    </div>
</div>
@endsection
