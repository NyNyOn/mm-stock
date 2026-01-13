@extends('layouts.app')

@section('header', 'คืนอุปกรณ์ / แจ้งเสีย')
@section('subtitle', 'จัดการรายการยืมที่ยังไม่ได้ส่งคืน')

@section('content')
<div class="container mx-auto p-4 lg:p-6 space-y-6">

    {{-- Alert Messages --}}
    @if (session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm flex items-center animate-fade-in-down">
            <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm flex items-center animate-fade-in-down">
            <i class="fas fa-exclamation-circle mr-2"></i> {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8">
        <div class="px-6 py-5 border-b border-gray-200 bg-blue-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-blue-900"><i class="fas fa-box-open mr-2"></i>รายการที่คุณกำลังยืมอยู่ (My Items)</h2>
                <p class="text-sm text-blue-600">กด "แจ้งคืน" เพื่อส่งคำขอคืนอุปกรณ์ให้กับเจ้าหน้าที่</p>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 text-xs font-semibold bg-white text-blue-700 rounded-full border border-blue-100">
                    {{ count($myItems) }} รายการ
                </span>
            </div>
        </div>

        {{-- My Items Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 font-medium text-left text-gray-500 uppercase tracking-wider">รูป</th>
                        <th class="px-6 py-3 font-medium text-left text-gray-500 uppercase tracking-wider">อุปกรณ์</th>
                        <th class="px-6 py-3 font-medium text-center text-gray-500 uppercase tracking-wider">ประเภท</th>
                        <th class="px-6 py-3 font-medium text-center text-gray-500 uppercase tracking-wider">จำนวนที่ค้าง</th>
                        <th class="px-6 py-3 font-medium text-center text-gray-500 uppercase tracking-wider">วันที่ยืม</th>
                        <th class="px-6 py-3 font-medium text-center text-gray-500 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($myItems as $transaction)
                    <tr>
                        <td class="px-6 py-4">
                            @php
                                $imgUrl = 'https://placehold.co/100x100/e2e8f0/64748b?text=No+Image';
                                if ($transaction->equipment && $transaction->equipment->images->isNotEmpty()) {
                                     $primaryImage = $transaction->equipment->images->firstWhere('is_primary', true) ?? $transaction->equipment->images->first();
                                     if($primaryImage) {
                                         $deptKey = config('department_stocks.default_nas_dept_key', 'mm');
                                         $imgUrl = route('nas.image', ['deptKey' => $deptKey, 'filename' => $primaryImage->file_name]);
                                     }
                                }
                            @endphp
                            <div class="h-12 w-12 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden flex-shrink-0">
                                <img src="{{ $imgUrl }}" class="h-full w-full object-cover">
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-bold text-gray-800">{{ optional($transaction->equipment)->name }}</span>
                            <div class="text-xs text-gray-500">{{ optional($transaction->equipment)->serial_number }}</div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($transaction->type == 'borrow')
                                <span class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-bold">ยืม</span>
                            @elseif($transaction->type == 'returnable')
                                <span class="px-2 py-1 bg-teal-100 text-teal-700 rounded-full text-xs font-bold">เบิก (คืนได้)</span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-bold">{{ $transaction->type }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center font-bold text-gray-700">
                            {{ abs($transaction->quantity_change) - ($transaction->returned_quantity ?? 0) }}
                        </td>
                        <td class="px-6 py-4 text-center text-gray-500">
                             {{ $transaction->transaction_date->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 text-center">
                        <td class="px-6 py-4 text-center">
                            @if($transaction->status == 'return_requested')
                                <span class="px-3 py-1 text-xs font-semibold bg-purple-100 text-purple-700 rounded-full border border-purple-200">
                                    <i class="fas fa-clock mr-1"></i> รอการยืนยัน
                                </span>
                            @elseif($allowUserReturn)
                            <button onclick="openReturnRequestModal({{ $transaction->id }}, '{{ optional($transaction->equipment)->name }}')" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-bold shadow-sm transition-all hover:scale-105">
                                <i class="fas fa-check-circle mr-1"></i> คืนอุปกรณ์
                            </button>
                            @else
                                <span class="text-xs text-gray-400 italic">ติดต่อ Admin เพื่อคืน</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                            ไม่พบรายการยืมค้างส่งคืน
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>



</div>

    {{-- ✅ Admin Section: All Borrowed Items --}}
    @if(isset($allBorrowedItems) && count($allBorrowedItems) > 0)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8">
        <div class="px-6 py-5 border-b border-gray-200 bg-purple-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-purple-900"><i class="fas fa-users-cog mr-2"></i>รายการยืมทั้งหมด (สำหรับ Admin)</h2>
                <p class="text-sm text-purple-600">กด "รับคืน" เพื่อดำเนินการคืนอุปกรณ์แทนผู้ใช้งาน</p>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 text-xs font-semibold bg-white text-purple-700 rounded-full border border-purple-100">
                    {{ count($allBorrowedItems) }} รายการ
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 font-medium text-left text-gray-500 uppercase tracking-wider">ผู้ยืม</th>
                        <th class="px-6 py-3 font-medium text-left text-gray-500 uppercase tracking-wider">รูป</th>
                        <th class="px-6 py-3 font-medium text-left text-gray-500 uppercase tracking-wider">อุปกรณ์</th>
                        <th class="px-6 py-3 font-medium text-left text-gray-500 uppercase tracking-wider">สถานะ</th>
                        <th class="px-6 py-3 font-medium text-center text-gray-500 uppercase tracking-wider">จำนวนที่ค้าง</th>
                        <th class="px-6 py-3 font-medium text-center text-gray-500 uppercase tracking-wider">วันที่ยืม</th>
                        <th class="px-6 py-3 font-medium text-center text-gray-500 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($allBorrowedItems as $transaction)
                    <tr class="hover:bg-purple-50/30 transition-colors">
                        {{-- User --}}
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="h-8 w-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold text-xs mr-3">
                                    {{ substr($transaction->user->fullname ?? 'Unknown', 0, 1) }}
                                </span>
                                <div>
                                    <div class="text-sm font-bold text-gray-900">{{ $transaction->user->fullname ?? 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500">{{ $transaction->user->employeecode ?? '-' }}</div>
                                </div>
                            </div>
                        </td>

                        {{-- Image --}}
                        <td class="px-6 py-4">
                            @php
                                $imgUrl = 'https://placehold.co/100x100/e2e8f0/64748b?text=No+Image';
                                if ($transaction->equipment && $transaction->equipment->images->isNotEmpty()) {
                                     $primaryImage = $transaction->equipment->images->firstWhere('is_primary', true) ?? $transaction->equipment->images->first();
                                     if($primaryImage) {
                                         $deptKey = config('department_stocks.default_nas_dept_key', 'mm');
                                         $imgUrl = route('nas.image', ['deptKey' => $deptKey, 'filename' => $primaryImage->file_name]);
                                     }
                                }
                            @endphp
                            <div class="h-10 w-10 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden flex-shrink-0">
                                <img src="{{ $imgUrl }}" class="h-full w-full object-cover">
                            </div>
                        </td>

                        {{-- Equipment --}}
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-bold text-gray-800">{{ optional($transaction->equipment)->name }}</span>
                            <div class="text-xs text-gray-500">{{ optional($transaction->equipment)->serial_number }}</div>
                        </td>

                        {{-- Status/Type --}}
                        <td class="px-6 py-4 text-left">
                            @if($transaction->status == 'return_requested')
                                <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded-lg text-xs font-bold animate-pulse inline-flex items-center">
                                    <i class="fas fa-undo mr-1"></i> แจ้งคืนมาแล้ว
                                </span>
                            @else
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-lg text-xs font-bold inline-flex items-center">
                                    <i class="fas fa-clock mr-1"></i> กำลังยืม
                                </span>
                            @endif
                        </td>

                        {{-- Quantity --}}
                        <td class="px-6 py-4 text-center font-bold text-gray-700">
                            {{ abs($transaction->quantity_change) - ($transaction->returned_quantity ?? 0) }}
                        </td>

                        {{-- Date --}}
                        <td class="px-6 py-4 text-center text-gray-500">
                             {{ $transaction->transaction_date->format('d/m/Y') }}
                        </td>

                        {{-- Action --}}
                        <td class="px-6 py-4 text-center">
                            <button onclick="openReceiveModal({{ $transaction->id }}, '{{ addslashes(optional($transaction->equipment)->name) }}', '{{ $transaction->status == 'return_requested' ? 'Unknown' : 'Good' }}')" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-bold shadow-sm transition-all hover:scale-105">
                                <i class="fas fa-hand-holding mr-1"></i> รับคืน
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                            ไม่พบรายการยืมค้างในระบบ
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

{{-- Modal 1: User Request Return --}}
<div id="request-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 bg-black bg-opacity-50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden animate-slide-up-soft">
        <div class="px-6 py-4 bg-blue-600 text-white flex justify-between items-center">
            <h3 class="font-bold text-lg"><i class="fas fa-check-circle mr-2"></i>คืนอุปกรณ์ทันที</h3>
            <button onclick="closeModal('request-modal')" class="text-white hover:text-blue-200"><i class="fas fa-times"></i></button>
        </div>
        <form id="request-form" method="POST">
            @csrf
            <div class="p-6 space-y-4">
                <p class="text-gray-600 text-sm">คุณกำลังแจ้งคืน: <strong id="req-item-name" class="text-gray-900"></strong></p>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">สภาพอุปกรณ์</label>
                    <div class="flex space-x-4">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="radio" name="return_condition" value="good" checked class="text-green-600 focus:ring-green-500" onchange="toggleProblemInput(false)">
                            <span class="text-sm">สภาพดี / ปกติ</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="radio" name="return_condition" value="defective" class="text-red-600 focus:ring-red-500" onchange="toggleProblemInput(true)">
                            <span class="text-sm">ชำรุด / เสียหาย</span>
                        </label>
                    </div>
                </div>
                <div id="req-problem-box" class="hidden animate-fade-in-down">
                    <label class="block text-sm font-bold text-gray-700 mb-1">กรุณาระบุสาเหตุ *</label>
                    <select name="problem_description" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <option value="">-- กรุณาเลือกสาเหตุ --</option>
                        <option value="เสียเพราะตัวอุปกรณ์เอง">เสียเพราะตัวอุปกรณ์เอง</option>
                        <option value="ผู้ใช้งานทำเสีย">ผู้ใช้งานทำเสีย</option>
                        <option value="ผู้ใช้ ใช้งานผิดประเภท">ผู้ใช้ ใช้งานผิดประเภท</option>
                    </select>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-2 border-t border-gray-100">
                <button type="button" onclick="closeModal('request-modal')" class="px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-lg text-sm font-bold">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-lg text-sm font-bold shadow-md">ยืนยันการคืน</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 2: Admin Receive Return (Existing Modal Logic but reused) --}}
@include('partials.modals.return-modal')

@endsection

@push('scripts')
<script>
    // --- User Request Modal ---
    function openReturnRequestModal(id, name) {
        document.getElementById('req-item-name').innerText = name;
        document.getElementById('request-form').action = `/transactions/${id}/request-return`;
        document.getElementById('req-problem-box').classList.add('hidden');
        document.querySelector('input[name="return_condition"][value="good"]').checked = true;
        showModal('request-modal');
    }

    function toggleProblemInput(show) {
        if(show) document.getElementById('req-problem-box').classList.remove('hidden');
        else document.getElementById('req-problem-box').classList.add('hidden');
    }

    // --- Admin Receive Modal ---
    function openReceiveModal(id, name, condition) {
        // Reuse existing return-modal logic
        // We set the form action to returns.store (which is default in the include)
        // But we need to pre-fill values based on user's request
        
        document.getElementById('return-transaction-id').value = id;
        document.getElementById('return-item-name').textContent = name + " (ยืนยันรับคืน)";
        
        // Pre-select condition based on user request
        if(condition === 'Defective') {
            document.querySelector('input[name="return_condition"][value="defective"]').click();
        } else {
            document.querySelector('input[name="return_condition"][value="good"]').click();
        }

        showModal('return-modal');
    }

    // Listener for Admin Return Modal (to toggle problem description)
    document.addEventListener('DOMContentLoaded', function() {
        const adminRadios = document.querySelectorAll('#return-form input[name="return_condition"]');
        const adminProblemWrapper = document.getElementById('problem-description-wrapper');

        adminRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'defective') {
                    adminProblemWrapper.classList.remove('hidden');
                } else {
                    adminProblemWrapper.classList.add('hidden');
                }
            });
        });
    });
</script>
@endpush
