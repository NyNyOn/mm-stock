@extends('layouts.app')

{{-- Department Name is implicit for this cloned app instance. Get from config for display. --}}
@php
    // Read the department key specific to THIS cloned application instance from .env via config/app.php
    $currentDeptKey = config('app.dept_key', 'it'); // Default to 'it' if not set in .env
    // Read department configurations (names, etc.) from config/department_stocks.php
    $departmentsConfig = config('department_stocks.departments', []);
    // Find the name for the current department key
    $currentDeptName = $departmentsConfig[$currentDeptKey]['name'] ?? strtoupper($currentDeptKey); // Use key as fallback name
@endphp

@section('header', '📥 รับของเข้าสต็อก - แผนก ' . $currentDeptName)
@section('subtitle', 'ตรวจสอบรายการที่สั่งซื้อสำหรับแผนกนี้ และยืนยันจำนวนที่ได้รับจริง')

{{-- Add custom styles if needed --}}
@push('styles')
<style>
    .po-card { transition: all 0.3s ease; }
    .po-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    /* Hide number input spinners (for better UI consistency) */
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; } /* Firefox */
</style>
@endpush

@section('content')
<div class="space-y-6 page animate-slide-up-soft">

    {{-- Main Form for submitting received quantities --}}
    <form action="{{ route('receive.process') }}" method="POST" id="receive-form">
        @csrf {{-- CSRF protection token --}}

        {{-- Display Session Messages (Success, Error, Warning) --}}
        @if (session('success')) <div class="p-4 mb-4 text-green-800 bg-green-100 border-l-4 border-green-500 rounded-r-lg" role="alert"><p>{!! session('success') !!}</p></div> @endif
        @if (session('error'))   <div class="p-4 mb-4 text-red-800 bg-red-100 border-l-4 border-red-500 rounded-r-lg" role="alert"><p>{!! session('error') !!}</p></div>   @endif
        @if (session('warning')) <div class="p-4 mb-4 text-yellow-800 bg-yellow-100 border-l-4 border-yellow-500 rounded-r-lg" role="alert"><p>{!! session('warning') !!}</p></div> @endif

        {{-- Display Validation Errors --}}
        @if ($errors->any())
            <div class="p-4 mb-4 text-red-800 bg-red-100 border-l-4 border-red-500 rounded-r-lg">
                <p class="font-bold">ข้อมูลไม่ถูกต้อง:</p>
                <ul class="mt-1 list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        {{-- Check if there are any Purchase Orders waiting for receiving --}}
        @if($purchaseOrders->isEmpty())
            {{-- Display message if no POs are found --}}
            <div class="p-6 text-center bg-white border-2 border-dashed rounded-lg soft-card">
                <i class="mb-4 text-5xl text-gray-400 fas fa-inbox"></i>
                <h3 class="text-lg font-semibold text-gray-700">ไม่มีรายการรอรับของสำหรับแผนก {{ $currentDeptName }}</h3>
                <p class="text-gray-500">ไม่พบใบสั่งซื้อ (PO) ที่มีสถานะรอรับของในฐานข้อมูลนี้</p>
                <p class="mt-2 text-xs text-gray-400">(สถานะ PO ที่แสดงคือ: shipped_from_supplier, partial_receive, pending)</p>
            </div>
        @else
            {{-- Display POs in a grid layout --}}
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                {{-- Loop through each Purchase Order --}}
                @foreach($purchaseOrders as $po)
                    {{-- Only render the card if the PO actually has items needing receiving (fetched by controller) --}}
                    @if($po->items->isNotEmpty())
                        <div class="flex flex-col bg-white rounded-2xl gentle-shadow po-card soft-card">
                            {{-- PO Header Section --}}
                            <div class="flex items-center justify-between p-4 border-b">
                                {{-- PO Number and Requester Info --}}
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800">PO #{{ $po->po_number ?? $po->id }}</h3>
                                    <p class="text-sm text-gray-500">
                                        {{-- Display User who ordered (from central DB) or fallback name --}}
                                        @if($po->orderedBy) สั่งโดย: {{ $po->orderedBy->fullname ?? 'N/A' }}
                                        @elseif($po->requester_name) ผู้ขอ: {{ $po->requester_name }}
                                        @else N/A @endif
                                        | เมื่อ: {{ $po->created_at->format('d/m/Y H:i') }} {{-- More precise timestamp --}}
                                    </p>
                                </div>
                                {{-- PO Status Badge (uses StatusHelper) --}}
                                <span class="px-3 py-1 text-xs font-semibold rounded-full {{ \App\Helpers\StatusHelper::getPurchaseOrderStatusClass($po->status) }}">
                                    {{ \App\Helpers\StatusHelper::getPurchaseOrderStatusText($po->status) }}
                                </span>
                            </div>

                            {{-- List of PO Items within this PO --}}
                            <div class="flex-grow p-4 space-y-3 divide-y divide-gray-100">
                                @foreach($po->items as $item)
                                    @php
                                        // Calculate quantities for display and input max value
                                        $alreadyReceived = $item->quantity_received ?? 0;
                                        $remainingToReceive = $item->quantity_ordered - $alreadyReceived;
                                    @endphp

                                    {{-- Check if this PO Item is linked to an Equipment record in the local DB --}}
                                    @if($item->equipment_id)
                                        {{-- Case 1: Item is linked - Show input field --}}
                                        <div class="grid grid-cols-12 gap-3 pt-3 first:pt-0">
                                            {{-- Item Description and Link --}}
                                            <div class="col-span-12 md:col-span-6">
                                                <p class="font-semibold text-gray-800 break-words">{{ $item->item_description }}</p>
                                                {{-- Link to the Equipment detail page in this local app --}}
                                                <a href="{{ route('equipment.show', $item->equipment_id) }}" target="_blank" class="text-xs text-blue-500 hover:underline" title="ดูรายละเอียดอุปกรณ์ (ID: {{ $item->equipment_id }})">
                                                    (สต็อก ID: {{ $item->equipment_id }})
                                                </a>
                                            </div>
                                            {{-- Quantity Information --}}
                                            <div class="col-span-7 md:col-span-3">
                                                <p class="text-xs font-medium text-gray-500">สั่ง/รับแล้ว/เหลือ</p>
                                                <p class="font-semibold">
                                                    {{ $item->quantity_ordered }} /
                                                    <span class="text-green-600">{{ $alreadyReceived }}</span> /
                                                    <span class="text-blue-600">{{ $remainingToReceive }}</span>
                                                </p>
                                            </div>
                                            {{-- Input field for Quantity Received Now --}}
                                            <div class="col-span-5 md:col-span-3">
                                                <label for="item-{{ $item->id }}" class="text-xs font-medium text-gray-500">รับเข้า:</label>
                                                <input type="number"
                                                       name="items[{{ $item->id }}][receive_now_quantity]" {{-- Array notation for form submission --}}
                                                       id="item-{{ $item->id }}"
                                                       min="0"
                                                       max="{{ $remainingToReceive }}" {{-- Prevent entering more than remaining --}}
                                                       placeholder="0"
                                                       value="{{ old('items.'.$item->id.'.receive_now_quantity') }}" {{-- Retain input value if validation fails --}}
                                                       class="w-full px-2 py-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                       step="1"> {{-- Only allow whole numbers --}}
                                                {{-- Hidden fields to send necessary data back to the controller --}}
                                                <input type="hidden" name="items[{{ $item->id }}][ordered_quantity]" value="{{ $item->quantity_ordered }}">
                                                <input type="hidden" name="items[{{ $item->id }}][already_received]" value="{{ $alreadyReceived }}">
                                            </div>
                                        </div>
                                    @else
                                        {{-- Case 2: Item is NOT linked - Show warning message --}}
                                        <div class="grid grid-cols-12 gap-3 pt-3 opacity-60 first:pt-0">
                                            {{-- Item Description --}}
                                            <div class="col-span-12 md:col-span-7">
                                                <p class="font-semibold text-gray-600">{{ $item->item_description }} (รายการใหม่)</p>
                                            </div>
                                            {{-- Warning Message --}}
                                            <div class="col-span-12 md:col-span-5">
                                                <p class="text-xs text-yellow-700 bg-yellow-100 px-2 py-1 rounded-md">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    ต้อง <a href="{{ route('equipment.index') }}" target="_blank" class="font-bold underline hover:text-yellow-800" title="ไปที่หน้าจัดการอุปกรณ์">เพิ่ม/แก้ไขอุปกรณ์</a> และผูก PO Item นี้ก่อน (ในฐานข้อมูล {{ $currentDeptName }})
                                                </p>
                                            </div>
                                        </div>
                                    @endif {{-- End check for equipment_id --}}
                                @endforeach {{-- End loop through items in this PO --}}
                            </div> {{-- End PO Items List container --}}
                        </div> {{-- Close PO Card --}}
                    @endif {{-- End check if PO has items --}}
                @endforeach {{-- End loop through all Purchase Orders --}}
            </div> {{-- Close Grid container --}}

            {{-- Submit Button Area --}}
            <div class="flex justify-end p-5 mt-6 bg-white rounded-2xl gentle-shadow soft-card">
                 {{-- The button to submit the form --}}
                 <button type="submit" class="inline-flex items-center px-6 py-3 font-semibold text-white bg-indigo-600 border border-transparent rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50" id="submit-receive-btn">
                     <i class="mr-2 fas fa-check-circle"></i>
                     ยืนยันการรับของที่กรอก
                 </button>
            </div>
        @endif {{-- End check if purchaseOrders is empty --}}
    </form> {{-- Close the main form --}}
</div>
@endsection

{{-- Add JavaScript for input validation and submit handling --}}
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('receive-form');
        const submitButton = document.getElementById('submit-receive-btn');
        const quantityInputs = form.querySelectorAll('input[type="number"][name^="items["]');

        // Add input event listener to each quantity input field
        quantityInputs.forEach(input => {
            input.addEventListener('input', function() {
                // Remove any non-digit characters (allows only 0-9)
                this.value = this.value.replace(/[^0-9]/g, '');

                // Parse the integer value
                let value = parseInt(this.value, 10);
                const max = parseInt(this.max, 10);
                const min = parseInt(this.min, 10); // Should be 0

                if (isNaN(value)) {
                    // If input becomes empty or non-numeric after stripping, allow it (treated as 0 by controller)
                    this.value = '';
                } else if (value < min) {
                    // Reset to minimum value (0) if user tries to enter negative
                    this.value = min;
                } else if (value > max) {
                    // Cap the value at the maximum remaining quantity
                    this.value = max;
                    // Optional: Provide feedback if capped (e.g., using SweetAlert)
                    if (typeof Swal !== 'undefined') {
                        // Example: Show a temporary toast notification
                        // const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
                        // Toast.fire({ icon: 'warning', title: `ไม่สามารถรับเกิน ${max}` });
                    }
                }
            });
             // Add change event listener to clear invalid input if user clicks away
             input.addEventListener('change', function() {
                 let value = parseInt(this.value, 10);
                 if (isNaN(value)) { this.value = ''; } // Clear if not a number after input
             });
        });

        // Add submit event listener to the form
        if (form && submitButton) {
            form.addEventListener('submit', function(event) {
                let quantityEntered = false;
                // Check if at least one quantity greater than 0 has been entered
                quantityInputs.forEach(input => {
                    if (input.value !== '' && parseInt(input.value, 10) > 0) {
                        quantityEntered = true;
                    }
                });

                // If no quantity > 0 was entered, prevent submission and show alert
                if (!quantityEntered) {
                     // Use SweetAlert for a nicer message if available
                     if (typeof Swal !== 'undefined') {
                         Swal.fire({
                            title: 'ข้อมูลไม่ครบถ้วน',
                            text: 'กรุณากรอกจำนวนที่รับเข้าอย่างน้อยหนึ่งรายการ (ต้องมากกว่า 0)',
                            icon: 'warning',
                            confirmButtonColor: '#4f46e5' // Indigo
                         });
                     } else {
                         // Fallback to basic browser alert
                         alert('กรุณากรอกจำนวนที่รับเข้าอย่างน้อยหนึ่งรายการ (ต้องมากกว่า 0)');
                     }
                     event.preventDefault(); // Stop form submission
                     return; // Exit the function
                }

                // If quantities are entered, disable the submit button to prevent double clicks
                submitButton.disabled = true;
                // Change button text to indicate processing
                submitButton.innerHTML = '<i class="mr-2 fas fa-spinner fa-spin"></i> กำลังประมวลผล...';
                // Form will now submit normally
            });
        }
    });
</script>
@endpush

