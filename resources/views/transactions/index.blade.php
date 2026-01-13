@extends('layouts.app')

@section('header', 'ประวัติธุรกรรมและติดตามสถานะ')
@section('subtitle', 'ตรวจสอบสถานะการเบิก-จ่าย และประวัติการใช้งานอุปกรณ์')

@section('content')
<div class="container mx-auto p-4 lg:p-6 space-y-6">

    @php
        // ✅ Fetch Settings Once
        $allowReturn = \App\Models\Setting::where('key', 'allow_user_return_request')->value('value') == '1';
    @endphp

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

    {{-- TABS NAVIGATION --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8 overflow-x-auto" aria-label="Tabs">
            
            {{-- Admin Pending Tab --}}
            @can('equipment:manage')
            <a href="{{ route('transactions.index', ['status' => 'admin_pending']) }}" 
               class="relative whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors group
                      {{ $statusFilter == 'admin_pending' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <i class="fas fa-user-shield {{ $statusFilter == 'admin_pending' ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                รอจัดส่ง (Admin)
                {{-- (Badge logic remains same) --}}
                @if(isset($adminPendingCount) && $adminPendingCount > 0)
                    <span class="absolute -top-1 -right-2 flex h-4 w-4 items-center justify-center">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-4 w-4 bg-red-500 text-[10px] text-white items-center justify-center ring-2 ring-white">
                            {{ $adminPendingCount }}
                        </span>
                    </span>
                @endif
                {{-- 🔴 ADDED: Badge for Return Requests --}}
                @php 
                    $returnReqCount = \App\Models\Transaction::where('status', 'return_requested')->count();
                @endphp
                @if($returnReqCount > 0)
                     <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-800">
                        <i class="fas fa-undo mr-1"></i> {{ $returnReqCount }} คืน
                    </span>
                @endif
            </a>
            @endcan

            {{-- My Pending Tab --}}
            <a href="{{ route('transactions.index', ['status' => 'my_pending']) }}" 
               class="relative whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors group
                      {{ $statusFilter == 'my_pending' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <i class="fas fa-clock {{ $statusFilter == 'my_pending' ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                รายการที่ต้องจัดการ
                @if(isset($myPendingCount) && $myPendingCount > 0)
                    <span class="absolute -top-1 -right-2 flex h-4 w-4 items-center justify-center">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-4 w-4 bg-orange-500 text-[10px] text-white items-center justify-center ring-2 ring-white">
                            {{ $myPendingCount }}
                        </span>
                    </span>
                @endif
            </a>

            {{-- My History Tab --}}
            <a href="{{ route('transactions.index', ['status' => 'my_history']) }}" 
               class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors group
                      {{ $statusFilter == 'my_history' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <i class="fas fa-history {{ $statusFilter == 'my_history' ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                ประวัติของฉัน
            </a>

            {{-- All History Tab --}}
            @can('report:view')
            <a href="{{ route('transactions.index', ['status' => 'all_history']) }}" 
               class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors group
                      {{ $statusFilter == 'all_history' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <i class="fas fa-database {{ $statusFilter == 'all_history' ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                ประวัติทั้งหมด (Admin)
            </a>
            @endcan
        </nav>
    </div>

    {{-- Search Filter --}}
    @if($statusFilter == 'all_history')
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
            <form action="{{ route('transactions.index') }}" method="GET" class="flex flex-wrap gap-4">
                <input type="hidden" name="status" value="all_history">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 mb-1">คำค้นหา</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="ชื่อ, อุปกรณ์, Serial..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div class="w-auto self-end">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 shadow-sm transition-colors">
                        <i class="fas fa-search mr-1"></i> ค้นหา
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- TABLE CONTAINER (Desktop) --}}
    <div class="hidden md:block bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-32">วันที่ / เวลา</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/3">อุปกรณ์ / วัตถุประสงค์</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">ประเภท</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">จำนวน</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">สถานะ</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">รายละเอียด</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="transaction-table-body" class="bg-white divide-y divide-gray-200">
                    @include('transactions.partials._table_rows', ['transactions' => $transactions, 'allowReturn' => $allowReturn])
                </tbody>
            </table>
        </div>
    </div>

    {{-- CARD CONTAINER (Mobile) --}}
    <div class="block md:hidden space-y-4">
        @include('transactions.partials._mobile_cards', ['transactions' => $transactions, 'allowReturn' => $allowReturn])
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $transactions->links() }}
    </div>

</div>

{{-- MODERN DETAILS MODAL --}}
<div id="detailsModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-gray-900 bg-opacity-60 backdrop-blur-sm transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col transform transition-all scale-100 m-4 relative">
        
        {{-- 1. Header with Gradient --}}
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5 flex justify-between items-center flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-lg text-white">
                    <i class="fas fa-file-invoice text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">รายละเอียดธุรกรรม</h3>
                    <p class="text-xs text-blue-100 opacity-90" id="modalTxDate">วันที่: -</p>
                </div>
            </div>
            <button onclick="closeDetailsModal()" class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded-full p-1.5 transition-all focus:outline-none">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        {{-- 2. Body Content (Scrollable) --}}
        <div class="p-6 sm:p-8 space-y-6 overflow-y-auto custom-scrollbar">
            
            {{-- Top Section: Image & Key Info --}}
            <div class="flex flex-col sm:flex-row gap-6">
                {{-- Image Container --}}
                <div class="flex-shrink-0 w-full sm:w-40 h-40 bg-gray-50 rounded-xl border border-gray-200 shadow-sm overflow-hidden relative group flex items-center justify-center">
                    <img id="modalImg" src="" class="max-w-full max-h-full object-contain p-1" alt="Equipment Image">
                </div>

                {{-- Text Info --}}
                <div class="flex-1 space-y-3">
                    <div>
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">อุปกรณ์</span>
                        <h4 class="text-xl font-bold text-gray-900 leading-tight" id="modalEquipment">-</h4>
                    </div>
                    
                    <div class="flex flex-wrap gap-3">
                        <div class="bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
                            <span class="text-[10px] text-gray-500 uppercase block mb-0.5">Serial Number</span>
                            <span class="text-sm font-mono font-semibold text-gray-700" id="modalSerial">-</span>
                        </div>
                        <div class="bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
                            <span class="text-[10px] text-gray-500 uppercase block mb-0.5">Transaction ID</span>
                            <span class="text-sm font-mono font-semibold text-indigo-600" id="modalTxId">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100"></div>

            {{-- Middle Section: Status Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- User --}}
                <div class="flex items-center gap-3 p-3 rounded-xl bg-blue-50/50 border border-blue-100">
                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-gray-400 uppercase">ผู้ทำรายการ</span>
                        <p class="text-sm font-bold text-gray-800" id="modalUser">-</p>
                    </div>
                </div>

                {{-- Type --}}
                <div class="flex items-center gap-3 p-3 rounded-xl bg-purple-50/50 border border-purple-100">
                    <div class="w-10 h-10 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center">
                        <i class="fas fa-tag"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-gray-400 uppercase">ประเภท</span>
                        <p class="text-sm font-bold text-gray-800" id="modalType">-</p>
                    </div>
                </div>

                {{-- Status --}}
                <div class="col-span-1 sm:col-span-2 flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-200">
                    <div class="w-10 h-10 rounded-full bg-white border border-gray-200 text-gray-500 flex items-center justify-center" id="modalStatusIconBg">
                        <i class="fas fa-info-circle" id="modalStatusIcon"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-gray-400 uppercase">สถานะปัจจุบัน</span>
                        <p class="text-sm font-bold text-gray-800" id="modalStatus">-</p>
                    </div>
                </div>
            </div>

            {{-- Bottom Section: Notes --}}
            <div>
                <label class="flex items-center gap-2 text-xs font-bold text-gray-500 uppercase mb-2">
                    <i class="fas fa-comment-alt text-gray-400"></i> วัตถุประสงค์ / หมายเหตุ
                </label>
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 min-h-[80px]">
                    <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line" id="modalNotes">-</p>
                </div>
            </div>

        </div>
        
        {{-- 3. Modal Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex justify-end border-t border-gray-100 flex-shrink-0">
            <button onclick="closeDetailsModal()" class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-100 hover:text-gray-900 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-200">
                ปิดหน้าต่าง
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // 1. Confirm Action Logic
    function confirmAction(form, title, text, icon, confirmBtn, btnColor) {
        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: btnColor || '#4f46e5',
            cancelButtonColor: '#9ca3af',
            confirmButtonText: confirmBtn || 'ยืนยัน',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-xl' }
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    }

    // 2. Modal Logic (Global Scope)
    window.showTransactionDetails = async function (transactionId) {
        const modal = document.getElementById('detailsModal');
        const imgEl = document.getElementById('modalImg');
        
        // Reset & Show Loading
        document.getElementById('modalEquipment').innerText = 'กำลังโหลด...';
        document.getElementById('modalImg').src = '{{ asset("images/placeholder.webp.gif") }}';
        modal.classList.remove('hidden');

        try {
            const response = await fetch(`/transactions/${transactionId}`);
            const result = await response.json();
            
            if(result.success) {
                const txn = result.data;
                
                // Populate Basic Info
                document.getElementById('modalTxId').innerText = '#' + String(txn.id).padStart(5, '0');
                document.getElementById('modalEquipment').innerText = txn.equipment?.name || '-';
                document.getElementById('modalSerial').innerText = txn.equipment?.serial_number || '-';
                document.getElementById('modalUser').innerText = txn.user?.fullname || '-';

                // Type Mapping
                const typeMap = {
                    'withdraw': 'เบิกของ',
                    'borrow': 'ยืมใช้',
                    'return': 'คืนของ',
                    'consumable': 'เบิกสิ้นเปลือง',
                    'returnable': 'ยืมคืน',
                    'partial_return': 'เบิก(คืนได้)',
                    'add': 'รับเข้า',
                    'receive': 'รับเข้า',
                    'adjust': 'ปรับปรุง'
                };
                document.getElementById('modalType').innerText = typeMap[txn.type] || (txn.type || '').toUpperCase();

                // Date
                const d = new Date(txn.transaction_date);
                document.getElementById('modalTxDate').innerText = d.toLocaleDateString('th-TH') + ' ' + d.toLocaleTimeString('th-TH', {hour: '2-digit', minute:'2-digit'}) + ' น.';

                // Status Styling & Mapping
                const statusEl = document.getElementById('modalStatus');
                const iconBg = document.getElementById('modalStatusIconBg');
                const icon = document.getElementById('modalStatusIcon');
                const status = txn.status;

                const statusMap = {
                    'pending': 'รออนุมัติ',
                    'approved': 'อนุมัติแล้ว',
                    'shipped': 'จัดส่งแล้ว',
                    'user_confirm_pending': 'รอรับของ',
                    'completed': 'สำเร็จ',
                    'cancelled': 'ยกเลิก',
                    'rejected': 'ปฏิเสธ',
                    'returned': 'คืนของแล้ว',
                    'borrowed': 'ยืมอยู่',
                    'return_requested': 'แจ้งคืน', // ✅ Added
                };
                statusEl.innerText = statusMap[status] || status.toUpperCase();
                
                // Reset classes first
                iconBg.className = 'w-10 h-10 rounded-full flex items-center justify-center transition-colors';
                
                if(status === 'completed' || status === 'returned') {
                    statusEl.className = 'text-sm font-bold text-emerald-600';
                    iconBg.classList.add('bg-emerald-100', 'text-emerald-600');
                    icon.className = 'fas fa-check';
                } else if(status === 'pending') {
                    statusEl.className = 'text-sm font-bold text-yellow-600';
                    iconBg.classList.add('bg-yellow-100', 'text-yellow-600');
                    icon.className = 'fas fa-clock';
                } else if(status === 'cancelled' || status === 'rejected') {
                    statusEl.className = 'text-sm font-bold text-red-600 line-through';
                    iconBg.classList.add('bg-red-100', 'text-red-600');
                    icon.className = 'fas fa-times';
                } else {
                    statusEl.className = 'text-sm font-bold text-blue-600';
                    iconBg.classList.add('bg-blue-100', 'text-blue-600');
                    icon.className = 'fas fa-info';
                }

                // ✅ FIXED: Notes & Purpose Logic
                // รวมข้อมูลวัตถุประสงค์และหมายเหตุไว้ในตัวแปรเดียว โดยไม่ใส่คำนำหน้าซ้ำซ้อน
                let displayText = '';
                
                if(txn.purpose) {
                    let pText = txn.purpose;
                    if (pText === 'general_use') pText = 'เบิกใช้งานทั่วไป';
                    else if (pText && pText.startsWith('glpi-')) {
                        const ticketId = txn.glpi_ticket_id || pText.split('-')[2] || '?';
                        let ticketName = '';
                        
                        // Try to get name from relation or appended attribute
                        if (txn.glpi_ticket && txn.glpi_ticket.name) {
                            ticketName = txn.glpi_ticket.name;
                        } else if (txn.glpi_ticket_relation && txn.glpi_ticket_relation.name) {
                            ticketName = txn.glpi_ticket_relation.name;
                        }

                        pText = 'GLPI Ticket #' + ticketId;
                        if (ticketName) {
                            pText += ': ' + ticketName;
                        }
                    }
                    // แสดงวัตถุประสงค์อย่างเดียว ไม่ต้องมี prefix เพราะหัวข้อ Modal บอกแล้ว
                    displayText = pText;
                }

                if(txn.notes) {
                    let cleanNotes = txn.notes;
                    // Remove auto-generated GLPI reference from notes to avoid duplication with the header
                    // Matches "อ้างอิงใบงาน GLPI #123" or "อ้างอิง GLPI #123" at start of string
                    cleanNotes = cleanNotes.replace(/^อ้างอิง(ใบงาน)?\s*GLPI\s*#\d+\s*/i, '').trim();

                    if(cleanNotes) {
                        if(displayText) displayText += '\n';
                        displayText += cleanNotes;
                    }
                }
                
                document.getElementById('modalNotes').innerText = displayText || '-';
                
                // Image
                if(txn.equipment?.latest_image?.image_url) {
                    imgEl.src = txn.equipment.latest_image.image_url; 
                } else {
                    imgEl.src = '{{ asset("images/placeholder.webp") }}';
                }
            } else {
                document.getElementById('modalEquipment').innerText = 'ไม่พบข้อมูล';
            }
        } catch(e) {
            console.error(e);
            document.getElementById('modalEquipment').innerText = 'เกิดข้อผิดพลาด';
        }
    };

    window.closeDetailsModal = function() {
        document.getElementById('detailsModal').classList.add('hidden');
    };

    // 3. Global Helpers for Inline Calls
    window.submitConfirmShipment = (form) => confirmAction(form, 'ยืนยันส่งของ', 'ยืนยันว่าได้ส่งมอบพัสดุแล้ว?', 'warning', 'ใช่, ส่งของ', '#4f46e5');
    window.submitConfirmReceipt = (form) => confirmAction(form, 'ยืนยันรับของ', 'ได้รับของครบถ้วนแล้ว?', 'question', 'ได้รับแล้ว', '#10b981');
    window.submitUserCancel = (form) => confirmAction(form, 'ยกเลิกคำขอ', 'คุณต้องการยกเลิกรายการนี้?', 'warning', 'ใช่, ยกเลิก', '#ef4444');
    window.submitAdminReject = (form) => confirmAction(form, 'ปฏิเสธคำขอ', 'ต้องการปฏิเสธรายการนี้?', 'warning', 'ยืนยันปฏิเสธ', '#ef4444');
    window.submitAdminCancel = (form) => confirmAction(form, 'ยกเลิก (Reversal)', 'รายการนี้เสร็จสิ้นแล้ว การยกเลิกจะคืนสต็อกกลับเข้าคลัง', 'error', 'ยืนยัน Reversal', '#ef4444');

</script>
@endpush