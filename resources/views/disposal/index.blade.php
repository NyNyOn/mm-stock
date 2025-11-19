@extends('layouts.app')

@section('header', 'รายการรอตัดจำหน่าย/ขาย')
@section('subtitle', 'อุปกรณ์ที่ถูกตัดออกจากสต็อกและรอการดำเนินการขั้นสุดท้าย')

@section('content')
<div class="p-6 soft-card gentle-shadow rounded-2xl">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm bg-white divide-y-2 divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 font-medium text-left text-gray-900 whitespace-nowrap">อุปกรณ์</th>
                    <th class="px-4 py-3 font-medium text-left text-gray-900 whitespace-nowrap">Serial Number</th>
                    <th class="px-4 py-3 font-medium text-left text-gray-900 whitespace-nowrap">ประเภท</th>
                    <th class="px-4 py-3 font-medium text-center text-gray-900 whitespace-nowrap">สถานะ</th>
                    <th class="px-4 py-3 font-medium text-center text-gray-900 whitespace-nowrap">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($disposals as $item)
                <tr>
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-3">

                            {{-- ✅✅✅ START: FINAL CLEAN LOGIC ✅✅✅ --}}
                            @php
                                $imageUrl = asset('images/placeholder.webp'); // Default

                                // 1. พยายามหารูปภาพหลัก/ล่าสุดจาก $item (Controller Eager load มาแล้ว)
                                $displayImage = optional($item->primaryImage)->exists
                                    ? $item->primaryImage
                                    : $item->latestImage; 

                                // 2. ตรวจสอบ ->exists (ถ้า $item มีรูป ก็ใช้รูปของมัน)
                                if (optional($displayImage)->exists) {
                                    $imageUrl = $displayImage->image_url;
                                } 
                                // 3. (Fallback) ถ้า $item ไม่มีรูป, ลองหาจาก Main Stock
                                else {
                                    $mainStock = \App\Models\Equipment::with(['primaryImage', 'latestImage'])
                                        ->where('name', $item->name)
                                        ->where('part_no', $item->part_no) // (จะทำงานแม้ part_no เป็น null)
                                        ->where('id', '!=', $item->id)
                                        ->whereIn('status', ['available', 'low_stock', 'out_of_stock'])
                                        ->whereHas('images') // ค้นหาเฉพาะตัวหลักที่มีรูปเท่านั้น
                                        ->first();

                                    if ($mainStock) {
                                        $mainImage = optional($mainStock->primaryImage)->exists
                                            ? $mainStock->primaryImage
                                            : $mainStock->latestImage;
                                        
                                        if(optional($mainImage)->exists) {
                                            $imageUrl = $mainImage->image_url;
                                        }
                                    }
                                    // ถ้าไม่เจอ Main Stock หรือ Main Stock ไม่มีรูป ก็จะใช้ placeholder
                                }
                            @endphp

                            <img src="{{ $imageUrl }}"
                                 alt="{{ $item->name }}"
                                 class="object-cover w-12 h-12 rounded-lg"
                                 onerror="this.onerror=null; this.src='{{ asset('images/placeholder.webp') }}';">
                            {{-- ✅✅✅ END: FINAL CLEAN LOGIC ✅✅✅ --}}
                            
                            <div>
                                <div class="font-bold text-gray-800">{{ $item->name }}</div>
                                <div class="text-xs text-gray-500">{{ $item->part_no }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 font-mono text-gray-600">{{ $item->serial_number ?? 'N/A' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ optional($item->category)->name }}</td>
                    <td class="px-4 py-3 text-center">
                        @if($item->status == 'disposed')
                            <span class="px-2 py-1 text-xs font-medium text-yellow-800 bg-yellow-100 rounded-full">
                                รอขาย
                            </span>
                        @elseif($item->status == 'sold')
                            <span class="px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full">
                                ขายแล้ว
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                       {{-- Action Buttons --}}
                        @if($item->status == 'disposed')
                            <div class="flex items-center justify-center space-x-2">
                                <form id="restore-form-{{$item->id}}" action="{{ route('disposal.restore', $item->id) }}" method="POST">@csrf</form>
                                <button type="button" onclick="confirmAndSubmitForm(event, 'restore-form-{{$item->id}}', 'ยืนยันการคืนสต็อก?', 'อุปกรณ์ชิ้นนี้จะกลับไปมีสถานะ Available')"
                                    class="px-3 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200">
                                    คืนสต็อก
                                </button>
                                <form id="sell-form-{{$item->id}}" action="{{ route('disposal.sell', $item->id) }}" method="POST">@csrf</form>
                                <button type="button" onclick="confirmAndSubmitForm(event, 'sell-form-{{$item->id}}', 'ยืนยันการขาย?', 'อุปกรณ์ชิ้นนี้จะถูกเปลี่ยนสถานะเป็น &quot;ขายแล้ว&quot;!')"
                                    class="px-3 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-md hover:bg-green-200">
                                    ขายแล้ว
                                </button>
                            </div>
                        @else
                            <div class="flex items-center justify-center text-green-500" title="รายการนี้ขายแล้ว">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="py-12 text-center text-gray-500">
                        <i class="mb-4 text-gray-300 fas fa-box-open fa-3x"></i>
                        <h4 class="text-lg font-semibold">ไม่มีรายการรอตัดจำหน่ายหรือขายแล้ว</h4>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($disposals->hasPages())
        <div class="mt-6">
            {{ $disposals->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
{{-- SweetAlert2 ... --}}
<script>
    function confirmAndSubmitForm(event, formId, title, text) {
        event.preventDefault(); 

        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6', // Blue
            cancelButtonColor: '#d33',    // Red
            confirmButtonText: 'ใช่, ยืนยัน!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById(formId);
                if(form) {
                    form.submit();
                } else {
                    console.error(`Form with ID '${formId}' not found.`);
                    Swal.fire('Error', 'Could not submit the form.', 'error');
                }
            }
        });
    }
</script>
@endpush