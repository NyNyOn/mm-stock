@extends('layouts.app')

@section('header', 'รับคืนพัสดุสิ้นเปลือง')
@section('subtitle', 'สำหรับรับคืนอุปกรณ์ที่เบิกไปแบบไม่ต้องคืน')

@section('content')
<div class="container p-4 mx-auto space-y-6">

    @if (session('success'))
        <div class="p-4 mb-4 text-green-800 bg-green-100 border-l-4 border-green-500 rounded-r-lg" role="alert"><p>{{ session('success') }}</p></div>
    @endif
    @if (session('error'))
        <div class="p-4 mb-4 text-red-800 bg-red-100 border-l-4 border-red-500 rounded-r-lg" role="alert"><p>{{ session('error') }}</p></div>
    @endif

    {{-- ============== ส่วนสร้างคำขอ (ตารางแสดงรายการที่คืนได้) ============== --}}
    <div class="max-w-4xl mx-auto">
        <div class="soft-card rounded-2xl gentle-shadow">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-800">เลือกรายการพัสดุที่ต้องการคืน</h3>
            </div>
            <div class="overflow-x-auto scrollbar-soft">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-sm font-medium text-left">วันที่เบิก</th>
                            <th class="px-4 py-3 text-sm font-medium text-left">อุปกรณ์</th>
                            <th class="px-4 py-3 text-sm font-medium text-center">เหลือให้คืน (ชิ้น)</th>
                            <th class="px-4 py-3 text-sm font-medium text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($returnableItems as $item)
                        @php
                            $remaining = abs($item->quantity_change) - $item->returned_quantity;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">{{ \Carbon\Carbon::parse($item->transaction_date)->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 font-medium">{{ optional($item->equipment)->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-lg font-bold text-center text-blue-600">{{ $remaining }}</td>
                            <td class="px-4 py-3 text-center">
                                @if(isset($pendingReturnTxnIds) && in_array($item->id, $pendingReturnTxnIds))
                                    <span class="px-3 py-1 text-sm font-bold text-yellow-800 bg-yellow-200 rounded-lg">
                                        <i class="fas fa-clock"></i> รออนุมัติ
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        class="px-3 py-1 text-sm font-bold text-white bg-blue-500 rounded-lg hover:bg-blue-600 return-btn"
                                        data-transaction-id="{{ $item->id }}"
                                        data-equipment-name="{{ optional($item->equipment)->name ?? 'N/A' }}"
                                        data-remaining-qty="{{ $remaining }}">
                                        <i class="fas fa-undo"></i> คืนอุปกรณ์
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="p-8 text-center text-gray-500">ไม่พบรายการที่สามารถคืนได้</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($returnableItems->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $returnableItems->appends(['history_page' => $userReturnHistory->currentPage()])->links() }}
            </div>
            @endif
        </div>

        {{-- ============== ส่วนตารางประวัติการขอคืน ============== --}}
        <div class="mt-8 soft-card rounded-2xl gentle-shadow">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-800">ประวัติคำขอคืนของคุณ</h3>
            </div>
            <div class="overflow-x-auto scrollbar-soft">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-sm font-medium text-left">อุปกรณ์ที่ขอคืน</th>
                            <th class="px-4 py-3 text-sm font-medium text-center">จำนวน</th>
                            <th class="px-4 py-3 text-sm font-medium text-left">วันที่ส่งคำขอ</th>
                            <th class="px-4 py-3 text-sm font-medium text-center">สถานะ</th>
                            <th class="px-4 py-3 text-sm font-medium text-left">ผู้อนุมัติ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($userReturnHistory as $history)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">{{ optional(optional($history->originalTransaction)->equipment)->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-lg font-bold text-center text-blue-600">{{ $history->quantity_returned }}</td>
                            <td class="px-4 py-3 text-sm">{{ $history->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($history->status == 'approved')
                                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-200 rounded-full">อนุมัติแล้ว</span>
                                @elseif($history->status == 'rejected')
                                    <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-200 rounded-full">ถูกปฏิเสธ</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-200 rounded-full">รออนุมัติ</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">{{ optional($history->approver)->fullname ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="p-8 text-center text-gray-500">คุณยังไม่มีประวัติการขอคืน</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($userReturnHistory->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $userReturnHistory->appends(['items_page' => $returnableItems->currentPage()])->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- ============== ส่วนสำหรับ Admin (แสดงเฉพาะ Admin) ============== --}}
    @can('permission:manage')
    <div class="mt-8 overflow-hidden soft-card rounded-2xl gentle-shadow">
        <div class="p-5 border-b border-gray-100"><h3 class="text-lg font-bold text-gray-800">รายการคำขอคืนที่รออนุมัติ</h3></div>
        <div class="overflow-x-auto scrollbar-soft">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-sm font-medium text-left">ผู้ส่งคำขอ</th>
                        <th class="px-4 py-3 text-sm font-medium text-left">อุปกรณ์</th>
                        <th class="px-4 py-3 text-sm font-medium text-center">จำนวนที่ขอคืน</th>
                        <th class="px-4 py-3 text-sm font-medium text-left">วันที่ส่งคำขอ</th>
                        <th class="px-4 py-3 text-sm font-medium text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pendingReturns as $return)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ optional($return->requester)->fullname ?? 'N/A' }}</td>
                        <td class="px-4 py-3">{{ optional(optional($return->originalTransaction)->equipment)->name ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-lg font-bold text-center text-blue-600">{{ $return->quantity_returned }}</td>
                        <td class="px-4 py-3 text-sm">{{ $return->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex justify-center space-x-2">
                                {{-- ✅✅✅ แก้ไข Form อนุมัติ ✅✅✅ --}}
                                <form action="{{ route('consumable-returns.approve', $return->id) }}" method="POST" class="needs-confirmation" data-title="ยืนยันการอนุมัติ" data-text="คุณต้องการอนุมัติรายการนี้ใช่หรือไม่?">
                                    @csrf
                                    <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-green-500 rounded-lg">อนุมัติ</button>
                                </form>
                                {{-- ✅✅✅ แก้ไข Form ปฏิเสธ ✅✅✅ --}}
                                <form action="{{ route('consumable-returns.reject', $return->id) }}" method="POST" class="needs-confirmation" data-title="ยืนยันการปฏิเสธ" data-text="คุณต้องการปฏิเสธรายการนี้ใช่หรือไม่?">
                                    @csrf
                                    <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-red-500 rounded-lg">ปฏิเสธ</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="p-8 text-center text-gray-500">ไม่มีรายการรออนุมัติ</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endcan
</div>

{{-- ============== Modal สำหรับกรอกจำนวนคืน ============== --}}
<div id="returnModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 bg-white rounded-lg shadow-xl">
        <h2 class="text-xl font-bold" id="modalTitle">คืนอุปกรณ์</h2>
        <p class="mb-4 text-gray-600" id="modalEquipmentName"></p>

        <form action="{{ route('consumable-returns.store') }}" method="POST">
            @csrf
            <input type="hidden" name="transaction_id" id="modalTransactionId">
            <div class="space-y-4">
                <div>
                    <label for="return_quantity" class="block mb-2 font-bold text-gray-700">จำนวนที่ต้องการคืน</label>
                    <input type="number" name="return_quantity" id="modalReturnQuantity" class="w-full px-3 py-2 border rounded-lg" min="1" required>
                </div>
                <div>
                    <label for="notes" class="block mb-2 font-bold text-gray-700">หมายเหตุ</label>
                    <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
            </div>
            <div class="flex justify-end pt-6 mt-6 space-x-2 border-t">
                <button type="button" id="closeModalBtn" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 font-bold text-white bg-blue-500 rounded-lg hover:bg-blue-600">
                    <i class="mr-1 fas fa-paper-plane"></i> ส่งคำขอคืน
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('returnModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const returnButtons = document.querySelectorAll('.return-btn');

    const modalTransactionId = document.getElementById('modalTransactionId');
    const modalEquipmentName = document.getElementById('modalEquipmentName');
    const modalReturnQuantity = document.getElementById('modalReturnQuantity');

    returnButtons.forEach(button => {
        button.addEventListener('click', function () {
            const transactionId = this.dataset.transactionId;
            const equipmentName = this.dataset.equipmentName;
            const remainingQty = this.dataset.remainingQty;

            modalTransactionId.value = transactionId;
            modalEquipmentName.textContent = equipmentName;
            modalReturnQuantity.value = '1';
            modalReturnQuantity.max = remainingQty;
            modalReturnQuantity.placeholder = 'สูงสุด ' + remainingQty + ' ชิ้น';

            modal.classList.remove('hidden');
        });
    });

    function closeModal() {
        modal.classList.add('hidden');
    }

    closeModalBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
});
</script>

{{-- ✅✅✅ เพิ่ม Script สำหรับ SweetAlert ✅✅✅ --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const confirmationForms = document.querySelectorAll('.needs-confirmation');

    confirmationForms.forEach(form => {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const title = this.dataset.title || 'ยืนยันการดำเนินการ';
            const text = this.dataset.text || 'คุณแน่ใจหรือไม่?';

            Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ใช่, ยืนยัน!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    });
});
</script>
@endpush
