@extends('layouts.app')
@section('header', '📝 งานตรวจนับสต็อก')
@section('subtitle', 'รายการงานตรวจนับสต็อกทั้งหมดและสถานะตามประเภทอุปกรณ์')

@section('content')
<div class="page animate-slide-up-soft">
    {{-- Action Button --}}
    <div class="flex justify-between items-center mb-6">
        <div class="text-lg font-semibold text-gray-700">รายการงานตรวจนับ</div>
        <a href="{{ route('stock-checks.create') }}" class="flex items-center px-4 py-3 text-sm font-medium text-white transition-all bg-gradient-to-br from-blue-400 to-purple-500 rounded-2xl hover:shadow-lg button-soft gentle-shadow">
            <i class="mr-2 text-sm fas fa-plus"></i><span>สร้างงานตรวจนับใหม่</span>
        </a>
    </div>

    <div class="overflow-hidden soft-card rounded-2xl gentle-shadow">
        {{-- Table View (Desktop) --}}
        <div class="hidden md:block overflow-x-auto scrollbar-soft">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-blue-50 to-purple-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ชื่องาน</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ประเภทอุปกรณ์</th> 
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">กำหนดการ/วันที่ทำล่าสุด</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">สถานะ</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ผู้ตรวจนับ</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 uppercase tracking-wider">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse ($stockChecks as $check)
                        <tr class="hover:bg-gray-50 transition-colors {{ $check->status === 'completed' ? 'text-gray-500' : 'text-gray-900' }}">
                            <td class="px-6 py-4 text-sm font-medium">
                                <a href="{{ route('stock-checks.show', $check) }}" class="hover:text-blue-600 transition-colors">{{ $check->name }}</a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if ($check->category)
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-purple-100 text-purple-800 border border-purple-200">
                                        {{ $check->category->name }}
                                    </span>
                                @else
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-gray-100 text-gray-500 border border-gray-200">
                                        ทั้งหมด
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                @if ($check->status === 'completed' && $check->completed_at)
                                    <p class="text-xs text-gray-500">เสร็จสิ้นเมื่อ:</p>
                                    <p class="font-bold text-green-600">{{ $check->completed_at->format('d/m/Y H:i') }}</p>
                                @else
                                    <p class="text-xs text-gray-500">กำหนดการ:</p>
                                    <p class="font-bold text-orange-600">{{ $check->scheduled_date->format('d/m/Y') }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($check->status === 'completed')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-green-100 text-green-800">✅ เสร็จสมบูรณ์</span>
                                @elseif ($check->status === 'in_progress')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-blue-100 text-blue-800">⏳ กำลังดำเนินการ</span>
                                @else
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-yellow-100 text-yellow-800">📅 รอดำเนินการ</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                {{ $check->checker->fullname ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                @if ($check->status !== 'completed')
                                    <a href="{{ route('stock-checks.perform', $check) }}" 
                                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                                        <i class="fas fa-play mr-2"></i> เริ่มตรวจนับ
                                    </a>
                                @else
                                    <a href="{{ route('stock-checks.show', $check) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">
                                        <i class="fas fa-eye mr-1"></i> ดูรายละเอียด
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 text-lg">
                                😭 ไม่พบงานตรวจนับสต็อกในระบบ
                                <p class="text-sm mt-2"><a href="{{ route('stock-checks.create') }}" class="text-blue-500 hover:text-blue-700 font-bold">คลิกที่นี่เพื่อสร้างงานใหม่</a></p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Card View (Mobile) --}}
        <div class="block md:hidden space-y-4 p-4 bg-gray-50">
            @forelse ($stockChecks as $check)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden relative">
                    <!-- Status Stripe -->
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 
                        {{ $check->status === 'completed' ? 'bg-green-500' : ($check->status === 'in_progress' ? 'bg-blue-500' : 'bg-yellow-400') }}">
                    </div>
                    
                    <div class="p-5 pl-6">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="text-lg font-bold text-gray-800 leading-tight">
                                <a href="{{ route('stock-checks.show', $check) }}" class="hover:text-blue-600">
                                    {{ $check->name }}
                                </a>
                            </h3>
                            <!-- Status Badge -->
                            @if ($check->status === 'completed')
                                <span class="flex-shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-green-100 text-green-700">เสร็จสิ้น</span>
                            @elseif ($check->status === 'in_progress')
                                <span class="flex-shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-blue-100 text-blue-700">กำลังดำเนินการ</span>
                            @else
                                <span class="flex-shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-yellow-100 text-yellow-700">รอดำเนินการ</span>
                            @endif
                        </div>

                        <div class="space-y-2 mb-4">
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-cubes w-5 text-purple-500"></i>
                                <span class="font-medium mr-2">หมวดหมู่:</span>
                                @if ($check->category)
                                    <span class="text-purple-700 font-bold">{{ $check->category->name }}</span>
                                @else
                                    <span class="text-gray-500">ทั้งหมด</span>
                                @endif
                            </div>
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-calendar-alt w-5 text-orange-500"></i>
                                @if ($check->status === 'completed' && $check->completed_at)
                                    <span class="font-medium mr-2">เสร็จเมื่อ:</span>
                                    <span class="text-green-600 font-bold">{{ $check->completed_at->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="font-medium mr-2">กำหนดการ:</span>
                                    <span class="text-gray-700">{{ $check->scheduled_date->format('d/m/Y') }}</span>
                                @endif
                            </div>
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-user w-5 text-blue-400"></i>
                                <span class="font-medium mr-2">ผู้ตรวจ:</span>
                                <span>{{ $check->checker->fullname ?? '-' }}</span>
                            </div>
                        </div>

                        <!-- Action Button -->
                        @if ($check->status !== 'completed')
                            <a href="{{ route('stock-checks.perform', $check) }}" 
                               class="block w-full text-center py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl font-bold shadow-lg shadow-green-200 active:scale-95 transition-transform">
                                <i class="fas fa-play mr-2"></i> เริ่มตรวจนับ
                            </a>
                        @else
                            <a href="{{ route('stock-checks.show', $check) }}" 
                               class="block w-full text-center py-3 bg-white border border-gray-200 text-indigo-600 rounded-xl font-bold hover:bg-gray-50">
                                <i class="fas fa-eye mr-2"></i> ดูรายละเอียด
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-10">
                    <div class="text-6xl mb-4">😭</div>
                    <p class="text-gray-500 font-medium">ไม่พบงานตรวจนับสต็อก</p>
                    <a href="{{ route('stock-checks.create') }}" class="inline-block mt-4 px-6 py-2 bg-blue-500 text-white rounded-lg font-bold shadow-md">
                        สร้างงานใหม่
                    </a>
                </div>
            @endforelse
        </div>
        
        {{-- Pagination --}}
        <div class="p-5 border-t bg-gray-50">
            {{ $stockChecks->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection