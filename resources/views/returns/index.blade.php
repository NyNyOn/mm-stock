@extends('layouts.app')

@section('header', 'คืนอุปกรณ์ / แจ้งเสีย')
@section('subtitle', 'จัดการรายการยืมที่ยังไม่ได้ส่งคืน')

@section('content')
<div class="p-6 soft-card gentle-shadow">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm bg-white divide-y-2 divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 font-medium text-left text-gray-900 whitespace-nowrap">อุปกรณ์</th>
                    <th class="px-4 py-3 font-medium text-left text-gray-900 whitespace-nowrap">ผู้ยืม</th>
                    <th class="px-4 py-3 font-medium text-center text-gray-900 whitespace-nowrap">จำนวน</th>
                    <th class="px-4 py-3 font-medium text-left text-gray-900 whitespace-nowrap">วันที่ยืม</th>
                    <th class="px-4 py-3 font-medium text-center text-gray-900 whitespace-nowrap">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($borrowedItems as $transaction)
                <tr>
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-3">
                            
                            {{-- ✅✅✅ START: Image Logic Fix ✅✅✅ --}}
                            @php
                                // Use optional() for safety in case equipment was deleted
                                $equipment = optional($transaction->equipment);
                                
                                // Use the relationships defined in Equipment.php (it-stock/app/Models/Equipment.php)
                                // Check if primaryImage exists (is not the default placeholder)
                                $displayImage = $equipment->primaryImage->exists 
                                                ? $equipment->primaryImage 
                                                : $equipment->latestImage;

                                // Call the 'image_url' accessor (assumed to be on EquipmentImage model)
                                // This accessor will build the route('nas.image', ...) or a placeholder
                                $imageUrl = $displayImage->image_url; 
                            @endphp
                            
                            <img src="{{ $imageUrl }}" 
                                 alt="{{ $equipment->name ?? 'N/A' }}" 
                                 class="object-cover w-10 h-10 rounded-lg"
                                 {{-- Add onerror fallback for safety --}}
                                 onerror="this.onerror=null; this.src='https://placehold.co/100x100/e2e8f0/64748b?text=Error';">
                            {{-- ✅✅✅ END: Image Logic Fix ✅✅✅ --}}
                            
                            <div>
                                <p class="font-medium text-gray-900">{{ $equipment->name ?? 'Equipment Deleted' }}</p>
                                <p class="text-xs text-gray-500">{{ $equipment->serial_number ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">{{ optional($transaction->user)->fullname ?? 'N/A' }}</td>
                    <td class="px-4 py-3 text-center">{{ abs($transaction->quantity_change) }}</td>
                    <td class="px-4 py-3">{{ $transaction->transaction_date->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-3 text-center">
                        <button
                            onclick="openReturnModal({{ $transaction->id }}, '{{ $equipment->name ?? 'N/A' }}')"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-500 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-opacity-75">
                            <i class="fas fa-undo"></i> คืน
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="py-12 text-center text-gray-500">
                        <i class="mb-4 text-green-300 fas fa-check-circle fa-3x"></i>
                        <h4 class="text-lg font-semibold">ไม่มีรายการค้างส่งคืน</h4>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('partials.modals.return-modal')
@endsection

@push('scripts')
<script>
    function openReturnModal(transactionId, itemName) {
        document.getElementById('return-transaction-id').value = transactionId;
        document.getElementById('return-item-name').textContent = itemName;

        // Reset to default state
        document.querySelector('input[name="return_condition"][value="good"]').checked = true;
        document.getElementById('problem-description-wrapper').classList.add('hidden');

        showModal('return-modal');
    }

    // Listener to show/hide problem description based on radio button selection
    document.querySelectorAll('input[name="return_condition"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const problemWrapper = document.getElementById('problem-description-wrapper');
            if (this.value === 'defective') {
                problemWrapper.classList.remove('hidden');
            } else {
                problemWrapper.classList.add('hidden');
            }
        });
    });
</script>
@endpush
