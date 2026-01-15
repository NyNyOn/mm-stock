@extends('layouts.app')

@section('header', 'ตรวจสอบของที่มาถึง')
@section('subtitle', 'ตรวจสอบและรับของเข้าสต็อก')

@section('content')
<div class="space-y-6">
    @if($purchaseOrders->isEmpty())
        <div class="p-8 text-center bg-white rounded-lg shadow">
            <i class="mb-4 text-5xl text-gray-400 fas fa-clipboard-check"></i>
            <p class="text-gray-600">ไม่มีรายการที่ต้องตรวจสอบในขณะนี้</p>
        </div>
    @else
        @foreach($purchaseOrders as $po)
            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="p-6 bg-gradient-to-r from-indigo-500 to-purple-600">
                    <div class="flex items-center justify-between text-white">
                        <div>
                            <h3 class="text-xl font-bold">{{ $po->po_number }}</h3>
                            <p class="text-sm opacity-90">สั่งโดย: {{ $po->orderedBy->fullname ?? '

-' }}</p>
                        </div>
                        <div class="text-right">
                            <span class="px-3 py-1 text-xs font-bold bg-white rounded-full text-indigo-600">
                                {{ $po->items->count() }} รายการ
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <form class="inspection-form" data-po-id="{{ $po->id }}">
                        @csrf
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">สินค้า</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">สั่ง</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">รับจริง</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">สถานะ</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">หมายเหตุ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($po->items as $item)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">
                                                {{ $item->equipment->name ?? $item->item_description }}
                                            </div>
                                            @if($item->equipment)
                                                <div class="text-xs text-gray-500">
                                                    S/N: {{ $item->equipment->serial_number }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="text-sm font-semibold">{{ $item->quantity_ordered }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" 
                                                   name="inspections[{{ $loop->index }}][received_quantity]" 
                                                   class="w-20 px-2 py-1 text-center border rounded"
                                                   value="{{ $item->quantity_ordered }}"
                                                   min="0"
                                                   required>
                                            <input type="hidden" name="inspections[{{ $loop->index }}][item_id]" value="{{ $item->id }}">
                                        </td>
                                        <td class="px-4 py-3">
                                            <select name="inspections[{{ $loop->index }}][status]" 
                                                    class="px-3 py-1 border rounded text-sm status-select"
                                                    required>
                                                <option value="accepted" selected>✅ รับเข้าสต็อก</option>
                                                <option value="rejected">❌ ปฏิเสธ</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="text" 
                                                   name="inspections[{{ $loop->index }}][notes]" 
                                                   class="w-full px-2 py-1 text-sm border rounded"
                                                   placeholder="หมายเหตุ (ถ้ามี)">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="flex justify-end mt-6 space-x-3">
                            <button type="submit" class="px-6 py-2 text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                                <i class="mr-2 fas fa-check"></i>
                                บันทึกผลการตรวจสอบ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach

        <div class="mt-6">
            {{ $purchaseOrders->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
document.querySelectorAll('.inspection-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = { inspections: [] };
        
        // แปลง FormData เป็น JSON
        const entries = Array.from(formData.entries());
        const grouped = {};
        
        entries.forEach(([key, value]) => {
            const match = key.match(/inspections\[(\d+)\]\[(\w+)\]/);
            if (match) {
                const index = match[1];
                const field = match[2];
                if (!grouped[index]) grouped[index] = {};
                grouped[index][field] = field === 'received_quantity' ? parseInt(value) : value;
            }
        });
        
        data.inspections = Object.values(grouped);
        
        try {
            Swal.fire({
                title: 'กำลังบันทึก...',
                allowOut sideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            const response = await fetch('{{ route("purchase-inspections.confirm") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                await Swal.fire('สำเร็จ!', result.message, 'success');
                location.reload();
            } else {
                Swal.fire('เกิดข้อผิดพลาด!', result.message, 'error');
            }
        } catch (error) {
            console.error(error);
            Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถบันทึกข้อมูลได้', 'error');
        }
    });
});
</script>
@endpush
@endsection
