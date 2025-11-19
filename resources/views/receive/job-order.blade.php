@extends('layouts.app')

@section('header', 'รับของ Job Order: JOB-PO-' . str_pad($purchaseOrder->id, 4, '0', STR_PAD_LEFT))
@section('subtitle', $purchaseOrder->notes)

@section('content')
<div class="container p-4 mx-auto">
    <div class="flex justify-start mb-4">
        <a href="{{ route('receive.index') }}" class="btn-secondary"><i class="mr-2 fas fa-arrow-left"></i> กลับหน้ารับของ</a>
    </div>

    <div class="p-6 soft-card gentle-shadow rounded-2xl">
        <h3 class="mb-4 text-xl font-bold text-gray-800">รายการในใบสั่งซื้อ</h3>
        <div class="space-y-3">
            @forelse ($purchaseOrder->items as $item)
                <div class="flex flex-col items-start justify-between gap-4 p-4 soft-card rounded-2xl sm:flex-row sm:items-center gentle-shadow">
                    <div class="flex-grow">
                        <p class="font-bold text-gray-800">{{ $item->item_description }}</p>
                        <p class="text-sm text-gray-500">
                            สั่งจำนวน: <span class="font-bold">{{ $item->quantity_ordered }}</span> |
                            รับแล้ว: <span class="font-bold text-green-600">{{ $item->quantity_received ?? 0 }}</span>
                        </p>
                    </div>
                    @if($item->status !== 'received')
                        <button
                            class="w-full px-6 py-3 font-medium text-green-700 transition-colors bg-green-100 sm:w-auto rounded-xl hover:bg-green-200 receive-item-btn"
                            data-item-id="{{ $item->id }}"
                            data-item-name="{{ $item->item_description }}"
                            data-item-qty="{{ $item->quantity_ordered - ($item->quantity_received ?? 0) }}">
                            <i class="mr-2 fas fa-plus"></i>รับของและนำเข้าสต็อก
                        </button>
                    @else
                         <span class="w-full px-3 py-1 text-xs font-semibold text-center text-green-800 bg-green-200 rounded-full sm:w-auto">รับเข้าสต็อกแล้ว</span>
                    @endif
                </div>
            @empty
                <p class="py-8 text-center text-gray-500">ไม่มีรายการในใบสั่งซื้อนี้</p>
            @endforelse
        </div>
    </div>
</div>

{{-- เราจะใช้ Modal เดิมที่คุณมีอยู่แล้ว --}}
@include('partials.modals.add-equipment-modal')

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/equipment.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const isSuperAdmin = {{ $isSuperAdmin ? 'true' : 'false' }};

    document.querySelectorAll('.receive-item-btn').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            const itemName = this.dataset.itemName;
            const itemQty = this.dataset.itemQty;

            if (window.showAddModal) {
                window.showAddModal();
            } else {
                console.error('showAddModal function is not defined.');
                return;
            }

            const addModalContent = document.getElementById('add-form-content-wrapper');
            if (!addModalContent) return;

            const observer = new MutationObserver((mutationsList, observer) => {
                for(const mutation of mutationsList) {
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        const form = addModalContent.querySelector('form');
                        if (form) {
                            // ✅ FIX: เปลี่ยน Selector ให้มองหา id ที่ถูกต้อง (xxx-new)
                            const nameInput = form.querySelector('#name-new');
                            const quantityInput = form.querySelector('#quantity-new');

                            if (nameInput) nameInput.value = itemName;

                            if (quantityInput) {
                                quantityInput.value = itemQty;
                                if (!isSuperAdmin) {
                                    quantityInput.setAttribute('readonly', true);
                                    quantityInput.classList.add('bg-gray-200', 'cursor-not-allowed');
                                } else {
                                    quantityInput.removeAttribute('readonly');
                                    quantityInput.classList.remove('bg-gray-200', 'cursor-not-allowed');
                                }
                            }

                            let hiddenInput = form.querySelector('input[name="purchase_order_item_id"]');
                            if (!hiddenInput) {
                                hiddenInput = document.createElement('input');
                                hiddenInput.type = 'hidden';
                                hiddenInput.name = 'purchase_order_item_id';
                                form.appendChild(hiddenInput);
                            }
                            hiddenInput.value = itemId;

                            observer.disconnect();
                            break;
                        }
                    }
                }
            });

            observer.observe(addModalContent, { childList: true, subtree: true });
        });
    });
});
</script>
@endpush
