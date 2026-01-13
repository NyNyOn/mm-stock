@extends('layouts.app')

@section('header', 'จัดการพัสดุที่เบิกไป')
@section('subtitle', 'เลือกส่งคืนของเหลือ หรือ แจ้งใช้งานหมดแล้ว')

@section('content')
<div class="container p-4 mx-auto space-y-6">

    @if (session('success'))
        <div class="p-4 mb-4 text-green-800 bg-green-100 border-l-4 border-green-500 rounded-r-lg shadow-sm"><p><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</p></div>
    @endif
    @if (session('error'))
        <div class="p-4 mb-4 text-red-800 bg-red-100 border-l-4 border-red-500 rounded-r-lg shadow-sm"><p><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</p></div>
    @endif

    {{-- ============== ตารางรายการที่จัดการได้ ============== --}}
    <div class="max-w-5xl mx-auto">
        <div class="soft-card rounded-2xl gentle-shadow overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-white">
                <h3 class="text-lg font-bold text-gray-800">📦 รายการพัสดุคงค้างของคุณ</h3>
                <p class="text-sm text-gray-500">กรุณาจัดการรายการเมื่อใช้งานเสร็จสิ้น</p>
            </div>
            
            {{-- Desktop Table View --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 text-gray-500 text-sm uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-3 text-left">สินค้า</th>
                            <th class="px-6 py-3 text-center">วันที่เบิก</th>
                            <th class="px-6 py-3 text-center">คงเหลือ (ชิ้น)</th>
                            <th class="px-6 py-3 text-center">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($returnableItems as $item)
                        @php
                            $remaining = abs($item->quantity_change) - $item->returned_quantity;
                            
                            $imgUrl = asset('images/placeholder.webp');
                            if ($item->equipment && $item->equipment->latestImage) {
                                $deptKey = config('department_stocks.default_nas_dept_key', 'mm');
                                $imgUrl = route('nas.image', ['deptKey' => $deptKey, 'filename' => $item->equipment->latestImage->file_name]);
                            }
                        @endphp
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-4">
                                    <div class="h-12 w-12 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden flex-shrink-0">
                                        <img src="{{ $imgUrl }}" class="h-full w-full object-cover">
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-800">{{ optional($item->equipment)->name ?? 'Unknown' }}</div>
                                        <div class="text-xs text-gray-500">{{ optional($item->equipment)->serial_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-gray-600">
                                {{ \Carbon\Carbon::parse($item->transaction_date)->format('d/m/Y H:i น.') }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-blue-600 bg-blue-100 rounded-full">
                                    {{ $remaining }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if(isset($pendingReturnTxnIds) && in_array($item->id, $pendingReturnTxnIds))
                                    <span class="inline-flex items-center px-3 py-1 text-xs font-bold text-yellow-700 bg-yellow-100 rounded-full border border-yellow-200">
                                        <i class="fas fa-clock mr-1"></i> รอตรวจสอบ
                                    </span>
                                @else
                                    <button 
                                        type="button"
                                        class="action-btn inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-sm transition-all transform hover:scale-105"
                                        data-id="{{ $item->id }}"
                                        data-name="{{ optional($item->equipment)->name }}"
                                        data-remaining="{{ $remaining }}">
                                        จัดการ <i class="fas fa-chevron-right ml-2 text-xs"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-gray-400 bg-gray-50">
                                <i class="fas fa-box-open text-4xl mb-3 block opacity-50"></i>
                                ไม่พบรายการค้างส่งคืน
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Card View --}}
            <div class="block md:hidden divide-y divide-gray-100">
                @forelse ($returnableItems as $item)
                    @php
                        $remaining = abs($item->quantity_change) - $item->returned_quantity;
                        $imgUrl = asset('images/placeholder.webp');
                        if ($item->equipment && $item->equipment->latestImage) {
                            $deptKey = config('department_stocks.default_nas_dept_key', 'mm');
                            $imgUrl = route('nas.image', ['deptKey' => $deptKey, 'filename' => $item->equipment->latestImage->file_name]);
                        }
                    @endphp
                    <div class="p-4 bg-white">
                        <div class="flex gap-4">
                             {{-- Image --}}
                             <div class="flex-shrink-0 w-20 h-20 bg-gray-100 rounded-xl overflow-hidden border border-gray-100">
                                <img src="{{ $imgUrl }}" class="w-full h-full object-cover">
                            </div>
                            
                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-gray-800 text-sm mb-1 break-words">{{ optional($item->equipment)->name ?? 'Unknown' }}</h4>
                                <p class="text-xs text-gray-500 mb-2">{{ optional($item->equipment)->serial_number }}</p>
                                
                                <div class="flex items-center text-xs text-gray-500 mb-2">
                                     <i class="far fa-calendar-alt mr-1"></i>
                                     {{ \Carbon\Carbon::parse($item->transaction_date)->format('d/m/Y H:i น.') }}
                                </div>
                            </div>
                            
                            {{-- Remaining Badge --}}
                            <div class="flex flex-col items-center justify-start pt-1">
                                <span class="flex flex-col items-center justify-center w-10 h-10 bg-blue-50 text-blue-700 rounded-xl border border-blue-100">
                                    <span class="text-xs font-semibold">เหลือ</span>
                                    <span class="text-sm font-bold leading-none">{{ $remaining }}</span>
                                </span>
                            </div>
                        </div>

                        {{-- Action Button --}}
                        <div class="mt-3">
                             @if(isset($pendingReturnTxnIds) && in_array($item->id, $pendingReturnTxnIds))
                                <div class="w-full py-2 text-center text-sm font-bold text-yellow-700 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <i class="fas fa-clock mr-1"></i> รอตรวจสอบ
                                </div>
                            @else
                                <button 
                                    type="button"
                                    class="action-btn w-full py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 active:scale-95 transition-all shadow-md shadow-indigo-200 flex justify-center items-center"
                                    data-id="{{ $item->id }}"
                                    data-name="{{ optional($item->equipment)->name }}"
                                    data-remaining="{{ $remaining }}">
                                    จัดการรายการ <i class="fas fa-chevron-right ml-2 text-xs"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-400 bg-gray-50">
                        <i class="fas fa-box-open text-4xl mb-3 block opacity-50"></i>
                        <span class="text-sm">ไม่พบรายการค้างส่งคืน</span>
                    </div>
                @endforelse
            </div>
            @if($returnableItems->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50">
                {{ $returnableItems->appends(['history_page' => $userReturnHistory->currentPage()])->links() }}
            </div>
            @endif
        </div>

        {{-- ============== ส่วนตารางประวัติการขอคืน ============== --}}
        <div class="mt-8 soft-card rounded-2xl gentle-shadow">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-800">🕒 ประวัติคำขอคืนของคุณ</h3>
            </div>
            {{-- Desktop Table View --}}
            <div class="hidden md:block overflow-x-auto scrollbar-soft">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-sm font-medium text-left">รูป</th>
                            <th class="px-4 py-3 text-sm font-medium text-left">อุปกรณ์</th>
                            <th class="px-4 py-3 text-sm font-medium text-center">ประเภท</th>
                            <th class="px-4 py-3 text-sm font-medium text-center">จำนวน</th>
                            <th class="px-4 py-3 text-sm font-medium text-left">วันที่ส่งคำขอ</th>
                            <th class="px-4 py-3 text-sm font-medium text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($userReturnHistory as $history)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                @php
                                    $imgUrl = 'https://placehold.co/100x100/e2e8f0/64748b?text=No+Image';
                                    if ($history->originalTransaction && $history->originalTransaction->equipment && $history->originalTransaction->equipment->latestImage) {
                                         $deptKey = config('department_stocks.default_nas_dept_key', 'mm');
                                         $filename = $history->originalTransaction->equipment->latestImage->file_name;
                                         $imgUrl = route('nas.image', ['deptKey' => $deptKey, 'filename' => $filename]);
                                    }
                                @endphp
                                <div class="w-10 h-10 overflow-hidden bg-gray-100 rounded-md border">
                                    <img src="{{ $imgUrl }}" class="object-cover w-full h-full">
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">
                                {{ optional(optional($history->originalTransaction)->equipment)->name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($history->action_type == 'write_off')
                                    <span class="px-2 py-1 bg-gray-200 text-gray-600 rounded text-xs">ใช้หมด</span>
                                @else
                                    <span class="px-2 py-1 bg-blue-100 text-blue-600 rounded text-xs">คืนของ</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-lg font-bold text-center text-gray-700">{{ $history->quantity_returned }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $history->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($history->status == 'approved')
                                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-200 rounded-full">อนุมัติแล้ว</span>
                                @elseif($history->status == 'rejected')
                                    <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-200 rounded-full">ถูกปฏิเสธ</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-200 rounded-full">รออนุมัติ</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="p-8 text-center text-gray-500">คุณยังไม่มีประวัติการขอคืน</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Card View --}}
            <div class="block md:hidden divide-y divide-gray-100">
                @forelse ($userReturnHistory as $history)
                    <div class="p-4 bg-white">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-bold text-gray-800 text-sm mr-2">{{ optional(optional($history->originalTransaction)->equipment)->name ?? 'N/A' }}</h4>
                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ $history->created_at->format('d/m/Y') }}</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                @if($history->action_type == 'write_off')
                                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs font-medium">ใช้หมด</span>
                                @else
                                    <span class="px-2 py-1 bg-blue-50 text-blue-600 rounded text-xs font-medium">คืนของ</span>
                                @endif
                                <span class="text-sm">จำนวน: <strong>{{ $history->quantity_returned }}</strong></span>
                            </div>
                            
                            <div>
                                @if($history->status == 'approved')
                                    <span class="px-2 py-1 text-xs font-bold text-green-700 bg-green-100 rounded-full">อนุมัติ</span>
                                @elseif($history->status == 'rejected')
                                    <span class="px-2 py-1 text-xs font-bold text-red-700 bg-red-100 rounded-full">ปฏิเสธ</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-bold text-yellow-700 bg-yellow-100 rounded-full">รออนุมัติ</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-400">คุณยังไม่มีประวัติการขอคืน</div>
                @endforelse
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
    <div class="mt-8 overflow-hidden soft-card rounded-2xl gentle-shadow border-2 border-indigo-100">
        <div class="p-5 bg-indigo-50 border-b border-indigo-100">
            <h3 class="text-lg font-bold text-indigo-900"><i class="fas fa-user-shield mr-2"></i>รายการคำขอคืนที่รออนุมัติ (Admin)</h3>
        </div>
        <div class="overflow-x-auto scrollbar-soft">
            <table class="w-full">
                <thead class="bg-white border-b">
                    <tr>
                        <th class="px-4 py-3 text-sm font-medium text-left text-gray-500">ผู้ส่งคำขอ</th>
                        <th class="px-4 py-3 text-sm font-medium text-left text-gray-500">อุปกรณ์</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-gray-500">ประเภท</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-gray-500">จำนวน</th>
                        <th class="px-4 py-3 text-sm font-medium text-left text-gray-500">วันที่ส่งคำขอ</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-gray-500">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($pendingReturns as $return)
                    @php
                        $adminImgUrl = asset('images/placeholder.webp');
                        if ($return->originalTransaction && $return->originalTransaction->equipment && $return->originalTransaction->equipment->latestImage) {
                            $deptKey = config('department_stocks.default_nas_dept_key', 'mm');
                            $filename = $return->originalTransaction->equipment->latestImage->file_name;
                            $adminImgUrl = route('nas.image', ['deptKey' => $deptKey, 'filename' => $filename]);
                        }
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-indigo-700">{{ optional($return->requester)->fullname ?? 'N/A' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 overflow-hidden bg-gray-100 rounded-md border">
                                    <img src="{{ $adminImgUrl }}" class="object-cover w-full h-full">
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ optional(optional($return->originalTransaction)->equipment)->name ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-500">TXN #{{ $return->original_transaction_id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($return->action_type == 'write_off')
                                <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-bold">แจ้งใช้หมด</span>
                            @else
                                <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold">ขอคืนของ</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-lg font-bold text-center text-blue-600">{{ $return->quantity_returned }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $return->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex justify-center space-x-2">
                                <form action="{{ route('consumable-returns.approve', $return->id) }}" method="POST" class="needs-confirmation" data-title="อนุมัติรายการ" data-text="ยืนยันการอนุมัติรายการนี้ใช่หรือไม่?">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 text-xs font-bold text-white bg-green-500 rounded hover:bg-green-600 shadow-sm">
                                        <i class="fas fa-check"></i> อนุมัติ
                                    </button>
                                </form>
                                <form action="{{ route('consumable-returns.reject', $return->id) }}" method="POST" class="needs-confirmation" data-title="ปฏิเสธคำขอ" data-text="รายการนี้จะถูกปฏิเสธ">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 text-xs font-bold text-white bg-red-500 rounded hover:bg-red-600 shadow-sm">
                                        <i class="fas fa-times"></i> ปฏิเสธ
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="p-8 text-center text-gray-400">ไม่มีรายการรออนุมัติ</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endcan
</div>

{{-- ============== New Selection Modal (No Notes) ============== --}}
<div id="actionModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black bg-opacity-60 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-100">
        {{-- Header --}}
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-bold text-gray-800" id="modalItemName">...</h3>
                <p class="text-xs text-gray-500">เลือกสิ่งที่ต้องการทำกับรายการนี้</p>
            </div>
            <button type="button" onclick="closeActionModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form action="{{ route('consumable-returns.store') }}" method="POST" id="actionForm">
            @csrf
            <input type="hidden" name="transaction_id" id="modalTransactionId">
            <input type="hidden" name="action_type" id="modalActionType">

            <div class="p-6">
                {{-- Choice Buttons --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" id="choiceContainer">
                    {{-- Option 1: Return --}}
                    <div class="choice-card cursor-pointer border-2 border-gray-200 rounded-xl p-4 text-center hover:border-blue-500 hover:bg-blue-50 transition-all group" onclick="selectAction('return')">
                        <div class="w-12 h-12 mx-auto bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-3 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <i class="fas fa-undo text-xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-800">คืนของที่เหลือ</h4>
                        <p class="text-xs text-gray-500 mt-1">กรณีใช้งานเสร็จแล้ว และมีของเหลือคืน Stock</p>
                    </div>

                    {{-- Option 2: Write Off --}}
                    <div class="choice-card cursor-pointer border-2 border-gray-200 rounded-xl p-4 text-center hover:border-red-500 hover:bg-red-50 transition-all group" onclick="selectAction('write_off')">
                        <div class="w-12 h-12 mx-auto bg-gray-100 text-gray-600 rounded-full flex items-center justify-center mb-3 group-hover:bg-red-500 group-hover:text-white transition-colors">
                            <i class="fas fa-trash-alt text-xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-800">ใช้หมดแล้ว</h4>
                        <p class="text-xs text-gray-500 mt-1">กรณีใช้งานจนหมดสภาพ หรือไม่เหลือซากให้คืน</p>
                    </div>
                </div>

                {{-- Dynamic Content Area --}}
                <div id="dynamicContent" class="hidden space-y-4">
                    {{-- Return Input --}}
                    <div id="returnInputGroup" class="hidden bg-blue-50 p-4 rounded-xl border border-blue-100">
                        <label class="block text-sm font-bold text-blue-800 mb-2">จำนวนที่จะส่งคืน (จาก <span id="maxQtyDisplay"></span> ชิ้น)</label>
                        <div class="flex items-center">
                            <input type="number" name="return_quantity" id="returnQtyInput" class="flex-1 border-gray-300 rounded-l-lg focus:ring-blue-500 focus:border-blue-500 text-center font-bold text-lg" min="1">
                            <span class="bg-blue-200 text-blue-800 px-4 py-2 rounded-r-lg font-bold text-sm">ชิ้น</span>
                        </div>
                    </div>

                    {{-- Write Off Message --}}
                    <div id="writeOffMessage" class="hidden bg-red-50 p-4 rounded-xl border border-red-100 text-center">
                        <p class="text-red-800 font-bold"><i class="fas fa-exclamation-triangle mr-1"></i> ยืนยันการแจ้งใช้หมด</p>
                        <p class="text-xs text-red-600 mt-1">ระบบจะเคลียร์ยอดคงเหลือ <span id="writeOffQtyDisplay" class="font-bold"></span> ชิ้น ออกจากรายการของคุณ</p>
                    </div>
                    
                    {{-- ❌ ตัดช่อง Notes ออกตามคำขอ --}}
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex justify-end space-x-3 hidden" id="submitArea">
                <button type="button" onclick="resetSelection()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 font-medium">ย้อนกลับ</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white text-sm font-bold rounded-lg hover:bg-indigo-700 shadow-lg transform active:scale-95 transition-all">
                    ยืนยันรายการ
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const modal = document.getElementById('actionModal');
    const choiceContainer = document.getElementById('choiceContainer');
    const dynamicContent = document.getElementById('dynamicContent');
    const returnInputGroup = document.getElementById('returnInputGroup');
    const writeOffMessage = document.getElementById('writeOffMessage');
    const submitArea = document.getElementById('submitArea');
    
    // Variables
    let currentMaxQty = 0;

    // Open Modal
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const remaining = this.dataset.remaining;

            document.getElementById('modalTransactionId').value = id;
            document.getElementById('modalItemName').textContent = name;
            document.getElementById('maxQtyDisplay').textContent = remaining;
            document.getElementById('writeOffQtyDisplay').textContent = remaining;
            document.getElementById('returnQtyInput').max = remaining;
            document.getElementById('returnQtyInput').value = remaining; // Default max
            
            currentMaxQty = parseInt(remaining);
            
            resetSelection();
            modal.classList.remove('hidden');
        });
    });

    function closeActionModal() {
        modal.classList.add('hidden');
    }

    function resetSelection() {
        choiceContainer.classList.remove('hidden');
        dynamicContent.classList.add('hidden');
        submitArea.classList.add('hidden');
        document.getElementById('modalActionType').value = '';
        
        // Reset styles
        document.querySelectorAll('.choice-card').forEach(el => {
            el.classList.remove('ring-2', 'ring-indigo-500', 'bg-blue-50', 'bg-red-50');
        });
    }

    window.selectAction = function(type) {
        document.getElementById('modalActionType').value = type;
        
        choiceContainer.classList.add('hidden'); // Hide choices
        dynamicContent.classList.remove('hidden'); // Show form
        submitArea.classList.remove('hidden'); // Show submit button

        if (type === 'return') {
            returnInputGroup.classList.remove('hidden');
            writeOffMessage.classList.add('hidden');
            document.getElementById('returnQtyInput').required = true;
        } else {
            returnInputGroup.classList.add('hidden');
            writeOffMessage.classList.remove('hidden');
            document.getElementById('returnQtyInput').required = false;
        }
    };
    
    // SweetAlert Confirmation
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
                    confirmButtonText: 'ยืนยัน',
                    cancelButtonText: 'ยกเลิก',
                    customClass: {
                        popup: 'rounded-xl',
                        confirmButton: 'rounded-lg',
                        cancelButton: 'rounded-lg'
                    }
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