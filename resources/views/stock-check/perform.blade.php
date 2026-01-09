@extends('layouts.app')

@section('header', 'ดำเนินการตรวจนับสต็อก')
@section('subtitle', $stockCheck->name)

@section('content')
<div class="space-y-6 page animate-slide-up-soft">

    {{-- Alert Messages --}}
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

    <form action="{{ route('stock-checks.update', $stockCheck->id) }}" method="POST" id="stock-check-form">
        @csrf
        @method('PUT')

        <div class="overflow-hidden soft-card rounded-2xl gentle-shadow">
            <div class="flex flex-wrap items-center justify-between p-5 border-b border-gray-100 bg-white">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">รายการอุปกรณ์ที่ต้องนับ</h3>
                    <p class="text-sm text-gray-500">
                        <span class="inline-block w-3 h-3 bg-green-50 rounded-full border border-green-200 mr-1"></span> = นับแล้ว
                        <span class="inline-block w-3 h-3 bg-white rounded-full border border-gray-200 ml-2 mr-1"></span> = ยังไม่นับ
                    </p>
                </div>
                {{-- Progress Summary --}}
                <div class="text-right">
                    <span class="text-sm font-medium text-gray-600">ความคืบหน้า</span>
                    <div class="text-2xl font-bold text-blue-600">
                        <span id="counted-count">0</span> / {{ count($items) }}
                    </div>
                </div>
            </div>

            <div class="w-full">
                <!-- Desktop Header -->
                <div class="hidden md:flex bg-gray-50 uppercase text-xs font-semibold text-gray-500 border-b border-gray-200">
                    <div class="px-6 py-4 flex-1 tracking-wider">อุปกรณ์ / Serial</div>
                    <div class="px-4 py-4 w-32 text-center tracking-wider">จำนวนในระบบ</div>
                    <div class="px-4 py-4 w-96 text-center tracking-wider">ผลการนับจริง</div>
                </div>

                <!-- Items Container -->
                <div class="divide-y divide-gray-100 bg-white" id="items-table-body">
                    @foreach($items as $item)
                        @php
                            $isCounted = !is_null($item->counted_quantity) && $item->counted_quantity !== '';
                            $rowClass = $isCounted ? 'bg-green-50' : '';
                        @endphp
                        
                        <!-- Responsive Row Item -->
                        <div id="row-{{ $item->id }}" class="transition-all duration-200 {{ $rowClass }} flex flex-col md:flex-row md:items-center relative border-b md:border-b-0 last:border-0 hover:bg-gray-50">
                            
                            {{-- Column 1: Equipment Info --}}
                            <div class="px-6 py-4 flex-1">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-12 w-12 md:h-10 md:w-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500">
                                        <i class="fas fa-box text-lg md:text-base"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-base md:text-sm font-bold text-gray-900 leading-tight">
                                            {{ $item->equipment->name ?? 'อุปกรณ์ถูกลบ' }}
                                        </div>
                                        <div class="text-xs text-gray-500 font-mono mt-0.5">
                                            SN: {{ $item->equipment->serial_number ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Column 2: Expected Quantity --}}
                            <div class="px-6 md:px-4 py-2 md:py-4 flex items-center justify-between md:justify-center md:w-32 bg-gray-50/50 md:bg-transparent">
                                <span class="md:hidden text-xs font-bold text-gray-500 uppercase">จำนวนในระบบ:</span>
                                <span class="inline-flex items-center justify-center px-4 py-1.5 rounded-full text-base font-bold bg-blue-100 text-blue-800 shadow-sm">
                                    {{ $item->expected_quantity }}
                                </span>
                            </div>

                            {{-- Column 3: Action Buttons (High Speed UI) --}}
                            <div class="px-6 py-4 md:w-96 text-center">
                                <input type="hidden" 
                                       id="input-qty-{{ $item->id }}" 
                                       name="items[{{ $item->id }}][counted_quantity]" 
                                       value="{{ $item->counted_quantity }}"
                                       class="item-input">

                                {{-- State 1: ยังไม่ได้เลือก --}}
                                <div id="actions-{{ $item->id }}" class="flex flex-col md:flex-row items-center justify-center gap-3 md:gap-2 {{ $isCounted ? 'hidden' : '' }}">
                                    <button type="button" 
                                            onclick="markAsCorrect({{ $item->id }}, {{ $item->expected_quantity }})"
                                            class="w-full md:flex-1 flex items-center justify-center gap-2 px-6 py-3 md:py-2 bg-green-500 hover:bg-green-600 text-white rounded-xl shadow-md active:scale-95 transition-all group">
                                        <svg class="w-6 h-6 md:w-5 md:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="font-bold text-lg md:text-base">ครบ ({{ $item->expected_quantity }})</span>
                                    </button>
                                    
                                    <button type="button" 
                                            onclick="showManualInput({{ $item->id }})"
                                            class="w-full md:w-auto px-6 py-3 md:py-2 bg-white hover:bg-gray-50 text-gray-600 rounded-xl border border-gray-300 shadow-sm font-medium transition-colors"
                                            title="ระบุจำนวนเอง">
                                        <span class="text-base md:text-sm">ไม่ครบ / ระบุเอง</span>
                                    </button>
                                </div>

                                {{-- State 2: โหมดกรอกเอง --}}
                                <div id="manual-{{ $item->id }}" class="hidden flex items-center justify-center gap-2">
                                    <input type="number" 
                                           id="manual-input-{{ $item->id }}"
                                           class="w-32 md:w-24 text-center text-lg font-bold border-gray-300 rounded-xl focus:ring-blue-500 focus:border-blue-500 shadow-sm input-zoom-disable"
                                           placeholder="0"
                                           min="0"
                                           onkeydown="if(event.key === 'Enter') { event.preventDefault(); confirmManual({{ $item->id }}); }">
                                    
                                    <button type="button" onclick="confirmManual({{ $item->id }})" class="p-3 bg-blue-500 text-white rounded-xl hover:bg-blue-600 shadow-md active:scale-95">
                                        <svg class="w-6 h-6 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </button>
                                    <button type="button" onclick="resetItem({{ $item->id }})" class="p-3 text-gray-400 hover:text-red-500">
                                        <svg class="w-6 h-6 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>

                                {{-- State 3: นับเสร็จแล้ว --}}
                                <div id="completed-{{ $item->id }}" class="{{ $isCounted ? '' : 'hidden' }} flex items-center justify-between md:justify-center gap-4 animate-fade-in p-2 md:p-0 bg-green-50/50 md:bg-transparent rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-green-100 text-green-600">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        </span>
                                        <div class="flex flex-col items-start md:items-center">
                                            <span class="text-xs text-gray-500 font-bold uppercase">นับได้จริง</span>
                                            <span id="display-qty-{{ $item->id }}" class="text-2xl font-black text-green-700 leading-none">
                                                {{ $item->counted_quantity }}
                                            </span>
                                        </div>
                                    </div>
                                    <button type="button" onclick="resetItem({{ $item->id }})" class="px-3 py-1.5 text-xs font-bold text-gray-500 bg-white border border-gray-200 rounded-lg hover:text-blue-600 hover:border-blue-300 shadow-sm transition-colors">
                                        แก้ไข
                                    </button>
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 text-sm text-gray-500 flex justify-between items-center">
                <span>* กดปุ่ม "ครบ" เพื่อยืนยันยอดทันที หรือกด "ไม่ครบ" เพื่อระบุจำนวนเอง</span>
            </div>
        </div>

        {{-- Hidden Input สำหรับการ Submit Form --}}
        <input type="hidden" name="complete_check" id="complete_check_input" disabled>
        <input type="hidden" name="reset_progress" id="reset_progress_input" disabled>

        {{-- Action Bar --}}
        <div class="sticky bottom-4 z-10 mt-6 mx-auto max-w-4xl">
            <div class="bg-white/90 backdrop-blur-md border border-gray-200 shadow-lg rounded-2xl p-4 flex flex-wrap justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <a href="{{ route('stock-checks.index') }}" class="text-gray-600 hover:text-gray-900 font-medium px-4">
                        ← กลับ
                    </a>
                    
                    {{-- ปุ่มเรียก Reset Modal --}}
                    <button type="button" 
                            onclick="openResetModal()"
                            class="px-4 py-2 text-sm font-bold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                        <i class="fas fa-undo mr-1"></i> เริ่มนับใหม่
                    </button>

                    {{-- ✅ [NEW] ปุ่มกดครบทั้งหมด --}}
                    <button type="button" 
                            onclick="markAllAsComplete()"
                            class="px-4 py-2 text-sm font-bold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                        <i class="fas fa-check-double mr-1"></i> ครบทั้งหมด
                    </button>
                </div>

                <div class="flex items-center gap-3 ml-auto">
                    <button type="submit" name="save_progress" class="px-5 py-2.5 font-bold text-gray-700 bg-gray-100 border border-gray-300 rounded-xl hover:bg-gray-200 transition-colors">
                        บันทึกแบบร่าง
                    </button>
                    {{-- ปุ่มเรียก Confirmation Modal --}}
                    <button type="button" 
                            onclick="openConfirmationModal()"
                            class="px-6 py-2.5 font-bold text-white bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg hover:from-green-600 hover:to-green-700 hover:shadow-green-500/30 transition-all transform hover:-translate-y-0.5">
                        <i class="fas fa-check-circle mr-2"></i> ยืนยันปิดงาน
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- 1. Confirmation Modal (สีเขียว - ปิดงาน) --}}
    <div id="confirmation-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-600 bg-opacity-75 backdrop-blur-sm" aria-hidden="true" onclick="closeConfirmationModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full animate-scale-up">
                <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-green-100 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="w-6 h-6 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">ยืนยันการปิดงานตรวจนับ</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">ระบบจะทำการ <strong>ปรับยอดสต็อก</strong> ตามจำนวนที่คุณนับได้จริงทันที และบันทึกสถานะงานนี้ว่า "เสร็จสิ้น"</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button type="button" onclick="confirmCompleteCheck()" class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-green-600 border border-transparent rounded-lg shadow-sm hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">
                        ยืนยันปิดงาน
                    </button>
                    <button type="button" onclick="closeConfirmationModal()" class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        ยกเลิก
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Reset Modal (สีแดง - เริ่มนับใหม่) --}}
    <div id="reset-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-red-900 bg-opacity-30 backdrop-blur-sm" aria-hidden="true" onclick="closeResetModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full animate-scale-up">
                <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-red-100 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="w-6 h-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">ต้องการเริ่มนับใหม่ใช่หรือไม่?</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    การกระทำนี้จะ <strong class="text-red-600">ล้างข้อมูลการนับทั้งหมด</strong> ที่คุณทำไว้ในหน้านี้กลับเป็นค่าว่าง คุณจะต้องเริ่มนับใหม่อีกครั้ง
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button type="button" onclick="confirmReset()" class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-red-600 border border-transparent rounded-lg shadow-sm hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">
                        ยืนยันล้างข้อมูล
                    </button>
                    <button type="button" onclick="closeResetModal()" class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        ยกเลิก
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- JavaScript Logic --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        updateProgress();
    });

    // --- Modal Functions (Confirmation) ---
    function openConfirmationModal() {
        document.getElementById('confirmation-modal').classList.remove('hidden');
    }
    function closeConfirmationModal() {
        document.getElementById('confirmation-modal').classList.add('hidden');
    }
    function confirmCompleteCheck() {
        document.getElementById('complete_check_input').disabled = false;
        document.getElementById('complete_check_input').value = '1';
        document.getElementById('stock-check-form').submit();
    }

    // --- Modal Functions (Reset) ---
    function openResetModal() {
        document.getElementById('reset-modal').classList.remove('hidden');
    }
    function closeResetModal() {
        document.getElementById('reset-modal').classList.add('hidden');
    }
    function confirmReset() {
        document.getElementById('reset_progress_input').disabled = false;
        document.getElementById('reset_progress_input').value = '1';
        document.getElementById('stock-check-form').submit();
    }

    // ✅ ฟังก์ชันกดครบทั้งหมด
    function markAllAsComplete() {
        // เลือกทุกปุ่ม "ครบ" ที่ยังแสดงอยู่ (ยังไม่ได้กด)
        const buttons = document.querySelectorAll('div[id^="actions-"]:not(.hidden) button[onclick^="markAsCorrect"]');
        
        if (buttons.length === 0) {
            Swal.fire('Info', 'คุณได้นับครบทุกรายการแล้ว', 'info');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการนับทั้งหมด?',
            text: `กำลังจะทำเครื่องหมายว่า "ครบ" สำหรับ ${buttons.length} รายการที่เหลือ`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ยืนยัน, เหมาหมด!',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#4f46e5'
        }).then((result) => {
            if (result.isConfirmed) {
                buttons.forEach(btn => btn.click());
                Swal.fire('เรียบร้อย!', 'บันทึกรายการทั้งหมดว่า "ครบ" แล้ว', 'success');
            }
        });
    }

    // --- Stock Check Functions (Logic เดิม) ---
    function markAsCorrect(id, expectedQty) {
        document.getElementById(`input-qty-${id}`).value = expectedQty;
        document.getElementById(`display-qty-${id}`).innerText = expectedQty;
        toggleState(id, 'completed');
        highlightRow(id, true);
        updateProgress();
    }


    function showManualInput(id) {
        toggleState(id, 'manual');
        setTimeout(() => document.getElementById(`manual-input-${id}`).focus(), 100);
        highlightRow(id, false);
    }

    function confirmManual(id) {
        const manualInput = document.getElementById(`manual-input-${id}`);
        let qty = manualInput.value;
        if (qty === '') {
            alert('กรุณาระบุจำนวน');
            manualInput.focus();
            return;
        }
        document.getElementById(`input-qty-${id}`).value = qty;
        document.getElementById(`display-qty-${id}`).innerText = qty;
        toggleState(id, 'completed');
        highlightRow(id, true);
        updateProgress();
    }

    function resetItem(id) {
        document.getElementById(`input-qty-${id}`).value = '';
        document.getElementById(`manual-input-${id}`).value = '';
        toggleState(id, 'actions');
        highlightRow(id, false);
        updateProgress();
    }

    function toggleState(id, state) {
        const actionsDiv = document.getElementById(`actions-${id}`);
        const manualDiv = document.getElementById(`manual-${id}`);
        const completedDiv = document.getElementById(`completed-${id}`);

        actionsDiv.classList.add('hidden');
        manualDiv.classList.add('hidden');
        completedDiv.classList.add('hidden');

        if (state === 'actions') actionsDiv.classList.remove('hidden');
        if (state === 'manual') manualDiv.classList.remove('hidden');
        if (state === 'completed') completedDiv.classList.remove('hidden');
    }

    function highlightRow(id, isDone) {
        const row = document.getElementById(`row-${id}`);
        if (isDone) {
            row.classList.add('bg-green-50');
            row.classList.remove('bg-white');
        } else {
            row.classList.remove('bg-green-50');
            row.classList.add('bg-white');
        }
    }

    function updateProgress() {
        const inputs = document.querySelectorAll('.item-input');
        let counted = 0;
        inputs.forEach(input => {
            if (input.value !== '') counted++;
        });
        document.getElementById('counted-count').innerText = counted;
    }
</script>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fadeIn 0.3s ease-out forwards;
    }
    
    @keyframes scaleUp {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    .animate-scale-up {
        animation: scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
</style>
@endsection