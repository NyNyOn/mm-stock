@extends('layouts.app')

@section('header', '📥 การตรวจสอบและรับวัสดุ (Goods Receiving)')
@section('subtitle', "ศูนย์กลางการยืนยันการรับเข้าคลังสินค้า [$currentDeptName]")

@section('content')
    <div class="w-full bg-gray-50 min-h-screen pb-40 lg:pb-32 font-sans">
        
        <!-- Header Wizard -->
        <div class="bg-white border-b border-gray-200 py-4 px-4 sticky top-0 z-20 shadow-lg">
            <div class="max-w-7xl mx-auto flex items-center justify-center gap-4 text-sm">
                <div class="flex items-center gap-2 text-indigo-600 font-bold">
                    <span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-xs">1</span>
                    <span>เลือกผลการตรวจรับ</span>
                </div>
                <div class="w-8 h-px bg-gray-300"></div>
                <div class="flex items-center gap-2 text-gray-500 font-medium">
                    <span class="w-6 h-6 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-xs">2</span>
                    <span>ดำเนินการยืนยัน (ปุ่มจะแสดงเมื่อข้อมูลครบถ้วน)</span>
                </div>
            </div>
        </div>

        <div class="max-w-[98%] mx-auto px-2 sm:px-4 mt-6">
            
            @if($purchaseOrders->isEmpty())
                <div class="bg-white shadow-xl rounded-2xl p-16 text-center border border-gray-200 flex flex-col items-center justify-center min-h-[400px]">
                    <svg class="h-20 w-20 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2-414-2.414A1 1 0 006.586 13H4" /></svg>
                    <h3 class="text-2xl font-bold text-gray-900">ไม่พบรายการรอตรวจรับ</h3>
                    <p class="text-gray-500 mt-2">รายการที่ได้รับการแจ้งส่งจากฝ่ายจัดซื้อ (PU) จะปรากฏที่นี่</p>
                </div>
            @else
                <!-- Global Form Container -->
                <form action="{{ route('receive.process') }}" id="receiveForm" method="POST">
                    @csrf
                    
                    <div class="space-y-10">
                        @foreach($purchaseOrders as $po)
                            <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-200">
                                <div class="bg-gray-100 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold text-gray-600">คำสั่งซื้อ (PO)</span>
                                        <span class="text-xl font-black text-indigo-700">#{{ $po->po_number }}</span>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-600 border border-blue-100 uppercase shadow-sm">
                                        สถานะ: {{ str_replace('_', ' ', $po->status) }}
                                    </span>
                                </div>

                                <div class="overflow-x-auto custom-scrollbar">
                                    <table class="min-w-full divide-y divide-gray-200 table-fixed">
                                        <thead class="bg-white text-gray-500 text-xs uppercase font-bold tracking-wider sticky top-0">
                                            <tr>
                                                <th class="px-4 py-3 w-20 text-center">รูปภาพ</th>
                                                <th class="px-4 py-3 w-1/3 text-left">รายละเอียดและข้อมูล PO</th>
                                                <th class="px-4 py-3 w-24 text-center bg-gray-50/50">ยอดค้างรับ</th>
                                                <th class="px-4 py-3 w-64 text-left pl-6 bg-indigo-50/10">1. ผลการตรวจรับ</th>
                                                <th class="px-4 py-3 w-32 text-center bg-indigo-50/10">2. จำนวนรับจริง</th>
                                                <th class="px-4 py-3 w-48 text-right pr-6 bg-indigo-50/10">3. การดำเนินการ</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100 text-sm">
                                            @foreach($po->items as $item)
                                                @php
                                                    $remaining = $item->quantity_ordered - ($item->quantity_received ?? 0);
                                                    $isLinked = !is_null($item->equipment);
                                                    $itemId = (int) $item->id;
                                                    $qtyToUse = (int) $remaining; 
                                                    $imgUrl = ($item->equipment && $item->equipment->latestImage) ? $item->equipment->latestImage->image_url : asset('images/placeholder.webp');
                                                    $itemName = $item->item_description ?? ($item->equipment->name ?? 'N/A');
                                                    $unitName = $item->unit_name ?? 'หน่วย';
                                                @endphp
                                                
                                                <tr id="row-{{ $itemId }}" 
                                                    data-item-id="{{ $itemId }}" 
                                                    data-max-qty="{{ $qtyToUse }}" 
                                                    data-status="" 
                                                    class="transition-colors duration-200 hover:bg-gray-50">
                                                    
                                                    <!-- รูปภาพ -->
                                                    <td class="px-4 py-4 align-top text-center">
                                                        <img src="{{ $imgUrl }}" onerror="this.onerror=null;this.src='{{ asset('images/placeholder.webp') }}';" 
                                                             class="w-16 h-16 rounded-xl object-cover border-2 border-gray-200 mx-auto bg-white shadow-md">
                                                    </td>
                                                    
                                                    <!-- รายละเอียดสินค้าและข้อมูล PO -->
                                                    <td class="px-4 py-4 align-top">
                                                        <p class="text-base font-bold text-gray-900 line-clamp-2 mb-1">{{ $itemName }}</p>
                                                        <div class="text-xs text-gray-500 space-y-0.5">
                                                            <p><span class="font-semibold text-gray-700">รหัสอุปกรณ์:</span> {{ $item->equipment_id ?? 'N/A' }}</p>
                                                            <p>
                                                                <span class="font-semibold text-gray-700">สั่งซื้อ:</span> <span class="font-bold text-indigo-600">{{ $item->quantity_ordered }}</span> {{ $unitName }}
                                                                | <span class="font-semibold text-gray-700">รับเข้าแล้ว:</span> <span class="font-bold text-green-600">{{ $item->quantity_received ?? 0 }}</span> {{ $unitName }}
                                                            </p>
                                                            @if(!$isLinked) 
                                                                <p class="text-red-500 font-medium flex items-center gap-1 mt-1">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                    ไม่สามารถนำเข้าระบบได้ (ไม่มีรหัสอุปกรณ์ผูก)
                                                                </p> 
                                                            @endif
                                                        </div>
                                                        <div id="hidden-inputs-{{ $itemId }}"></div>
                                                    </td>
                                                    
                                                    <!-- ยอดค้างรับ (ลดขนาด) -->
                                                    <td class="px-4 py-4 text-center font-extrabold text-xl text-red-600 bg-gray-50/50 border-r border-gray-100 align-top">
                                                        {{ $remaining }}
                                                        <span class="text-sm font-medium text-gray-500 block">{{ $unitName }}</span>
                                                    </td>

                                                    <!-- 1. ผลการตรวจรับ -->
                                                    <td class="px-4 py-4 pl-6 align-top bg-indigo-50/10">
                                                        @if($isLinked)
                                                            <select id="status-{{ $itemId }}" onchange="handleStatusChange({{ $itemId }})"
                                                                    class="w-full text-sm font-semibold border-gray-300 rounded-xl py-2.5 px-3 shadow-md focus:ring-2 focus:ring-indigo-400 transition-all cursor-pointer">
                                                                <option value="" disabled selected>-- เลือกผลการตรวจ --</option>
                                                                <option value="pass">✅ ครบถ้วนสมบูรณ์ (รับเข้าคลัง)</option>
                                                                <option value="issue">⚠️ พบปัญหา (แจ้งส่งคืน)</option>
                                                            </select>
                                                        @else
                                                            <span class="text-red-500 text-sm p-2 bg-red-50 rounded-lg shadow-inner block text-center">🚫 ไม่ผ่านเกณฑ์การรับเข้า</span>
                                                        @endif
                                                    </td>

                                                    <!-- 2. จำนวนรับจริง (ลดขนาด) -->
                                                    <td class="px-4 py-4 text-center align-top bg-indigo-50/10">
                                                        <div id="qty-wrapper-{{ $itemId }}" class="flex flex-col items-center justify-center h-full min-h-[70px]">
                                                            <!-- Dynamic Content -->
                                                        </div>
                                                    </td>

                                                    <!-- 3. การดำเนินการ -->
                                                    <td class="px-4 py-4 text-right pr-6 align-top bg-indigo-50/10">
                                                        <div id="action-buttons-{{ $itemId }}" class="min-h-[44px] flex items-center justify-end">
                                                            <!-- Dynamic Buttons -->
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Modal ยืนยันส่งคืน -->
                    <div id="reject-modal" class="fixed inset-0 bg-gray-900/75 z-50 flex items-center justify-center p-4 hidden">
                        <div id="reject-modal-content" class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm border-t-4 border-red-500">
                            <h4 class="text-2xl font-bold text-red-700 mb-4 flex items-center gap-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                ยืนยันการปฏิเสธการรับ
                            </h4>
                            <p class="text-sm text-gray-600 mb-4">โปรดระบุสาเหตุการปฏิเสธเพื่อแจ้งไปยังฝ่ายจัดซื้อ (PU) **รายการนี้จะไม่ถูกนำเข้าคลังสินค้าจนกว่าจะได้รับคำสั่งยืนยันจาก PU**</p>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-sm font-bold text-gray-700 block mb-1">สาเหตุหลัก (บังคับเลือก):</label>
                                    <select name="notes_reject_type" id="reject-reason-select" class="w-full border-gray-300 rounded-lg text-sm shadow-inner p-2">
                                        <option value="" disabled selected>-- เลือกสาเหตุ --</option>
                                        <option value="incomplete">1. 📦 ของไม่ครบตามจำนวนสั่งซื้อ</option>
                                        <option value="damaged">2. 🔨 ของเสียหาย/ชำรุด</option>
                                        <option value="wrong_item">3. ❌ รหัส/รุ่น/สเปค ไม่ตรงกับคำสั่งซื้อ</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-bold text-gray-700 block mb-1">หมายเหตุเพิ่มเติม (ถ้ามี):</label>
                                    <input type="text" name="notes_reject" id="reject-notes-input" class="w-full border-gray-300 rounded-lg text-sm shadow-inner p-2" placeholder="ระบุรายละเอียดเพิ่มเติม...">
                                </div>
                            </div>
                            <div class="flex gap-3 justify-end mt-6">
                                <button type="button" onclick="hideRejectModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors">ยกเลิก</button>
                                <button type="submit" name="single_submit_reject" id="final-reject-submit" onclick="finalRejectSubmitAction(event)" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg font-bold shadow-md hover:bg-red-700 transition-colors">
                                    ยืนยันและส่งคืน 📤
                                </button>
                            </div>
                            <input type="hidden" id="modal-status-input" name="">
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="fixed bottom-0 right-0 left-0 lg:left-64 border-t border-gray-200 bg-white/95 backdrop-blur-md p-4 z-30 shadow-[0_-4px_10px_rgba(0,0,0,0.05)]">
                        <div class="max-w-7xl mx-auto flex justify-end gap-4 px-4">
                            <button type="button" onclick="window.history.back()" class="px-6 py-2.5 bg-white border border-gray-300 rounded-xl text-gray-700 font-bold hover:bg-gray-50 transition-colors shadow-sm">
                                ย้อนกลับ
                            </button>
                            <button type="submit" id="save-all-button" class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg transition-all">
                                บันทึกการดำเนินการทั้งหมด
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        // Global references (Vanilla JS)
        const form = document.getElementById('receiveForm');
        const rejectModal = document.getElementById('reject-modal');
        const rejectReasonSelect = document.getElementById('reject-reason-select');
        const rejectNotesInput = document.getElementById('reject-notes-input');
        const finalRejectSubmit = document.getElementById('final-reject-submit');
        const modalStatusInput = document.getElementById('modal-status-input');

        /**
         * Clears and updates the hidden input fields for a specific row to prepare for submission.
         * @param {number} itemId 
         * @param {string} status 'pass', 'issue', or '' (clear)
         * @param {number} qty The quantity handled (for logging/receipt).
         * @param {string} [rejectType] Only used for Issue submission.
         */
        function updateHiddenInputs(itemId, status, qty, rejectType = '') {
            const hiddenInputs = document.getElementById(`hidden-inputs-${itemId}`);
            hiddenInputs.innerHTML = '';
            
            if (status === 'pass') {
                // PASS: ใช้ฟิลด์ receive_now_quantity สำหรับการรับเข้าคลังทันที
                let html = `
                    <input type="hidden" name="items[${itemId}][selected]" value="1">
                    <input type="hidden" name="items[${itemId}][receive_now_quantity]" value="${qty}">
                    <input type="hidden" name="items[${itemId}][inspection_status]" value="pass">
                `;
                hiddenInputs.innerHTML = html;

            } else if (status === 'issue') {
                // ISSUE: ใช้ฟิลด์ issue_qty_handled สำหรับการบันทึกปริมาณที่พบปัญหาเท่านั้น
                // **ไม่ใช้ receive_now_quantity** เพื่อป้องกันการนำเข้าคลังทันที
                let html = `
                    <input type="hidden" name="items[${itemId}][selected]" value="1">
                    <input type="hidden" name="items[${itemId}][issue_qty_handled]" value="${qty}">
                `;
                
                if (rejectType) {
                    // เพิ่มสถานะการ Reject เมื่อได้สาเหตุจาก Modal แล้ว
                    html += `<input type="hidden" id="final-status-input-${itemId}" name="items[${itemId}][inspection_status]" value="${rejectType}">`;
                }
                
                hiddenInputs.innerHTML = html;
            }
        }

        // --- MODAL CONTROL FUNCTIONS ---

        function showRejectModal(itemId) {
            console.log(`4. Modal: Showing for Item ID ${itemId}.`);
            
            finalRejectSubmit.dataset.itemId = itemId;
            rejectReasonSelect.setAttribute('required', 'required');
            rejectModal.classList.remove('hidden');
        }

        function hideRejectModal() {
            rejectReasonSelect.removeAttribute('required');
            rejectReasonSelect.value = '';
            rejectNotesInput.value = '';
            rejectModal.classList.add('hidden');
        }
        
        // --- CORE WORKFLOW LOGIC ---

        function handleStatusChange(itemId) {
            const statusSelect = document.getElementById(`status-${itemId}`);
            const status = statusSelect.value;
            const row = document.getElementById(`row-${itemId}`);
            const maxQty = parseInt(row.dataset.maxQty);
            
            console.log(`--- DEBUG: Item ${itemId} ---`);
            console.log(`1. Status Selected: ${status}`);

            const qtyWrapper = document.getElementById(`qty-wrapper-${itemId}`);
            const actionButtons = document.getElementById(`action-buttons-${itemId}`);
            row.dataset.status = status;
            row.classList.remove('bg-blue-50/20', 'bg-red-50/20');
            qtyWrapper.innerHTML = '';
            actionButtons.innerHTML = '';
            updateHiddenInputs(itemId, '', 0);

            if (status === 'pass') {
                console.log('2. Workflow PASS: Auto-filling Qty and showing button.');
                row.classList.add('bg-blue-50/20');
                
                // A. Show Qty Input (Readonly & Auto-filled)
                qtyWrapper.innerHTML = `
                    <input type="number" id="qty-input-${itemId}" value="${maxQty}" min="0" max="${maxQty}" 
                           class="w-24 text-center font-black text-xl rounded-xl border-2 h-12 bg-blue-100 border-blue-400 text-blue-700 shadow-inner"
                           readonly>
                    <div class="text-[10px] text-blue-600 mt-1 font-bold animate-pulse">🎉 ครบถ้วน (รับเข้าเต็มจำนวน)</div>
                `;

                // B. Show Action Button (Receive) - Submits form directly
                actionButtons.innerHTML = `
                    <button type="submit" name="single_submit" value="${itemId}" 
                            class="w-full py-3 bg-blue-600 text-white rounded-xl text-sm font-black shadow-lg hover:bg-blue-700 transition-colors animate-flash">
                        นำเข้าคลัง ✅
                    </button>
                `;

                // C. Add Hidden Inputs for submission - PASS (Includes receive_now_quantity)
                updateHiddenInputs(itemId, 'pass', maxQty);

            } else if (status === 'issue') {
                console.log('2. Workflow ISSUE: Clearing Qty and awaiting input.');
                row.classList.add('bg-red-50/20');

                // A. Show Qty Input (Editable)
                qtyWrapper.innerHTML = `
                    <input type="number" id="qty-input-${itemId}" value="" min="0" max="${maxQty}" 
                           oninput="checkQtyInput(${itemId})"
                           class="w-24 text-center font-black text-xl rounded-xl border-2 h-12 bg-white border-red-400 text-red-700 shadow-lg focus:ring-4 focus:ring-red-100">
                    <div id="issue-msg-${itemId}" class="text-[10px] text-gray-700 mt-1 font-medium">กรุณากรอกจำนวนที่ต้องการรับจริง</div>
                `;
            }
        }

        function checkQtyInput(itemId) {
            const qtyInput = document.getElementById(`qty-input-${itemId}`);
            const actionButtons = document.getElementById(`action-buttons-${itemId}`);
            const issueMsg = document.getElementById(`issue-msg-${itemId}`);
            
            const qty = qtyInput.value;
            const isQtyValid = qty !== '' && qty !== null && Number(qty) >= 0;

            console.log(`3. Qty Input Change: ${qty}. Valid: ${isQtyValid}`);

            actionButtons.innerHTML = '';
            updateHiddenInputs(itemId, '', 0);

            issueMsg.innerHTML = isQtyValid ? 'พร้อมแจ้งปัญหา' : 'กรุณากรอกจำนวนที่ต้องการรับจริง';
            issueMsg.classList.toggle('text-gray-700', !isQtyValid);
            issueMsg.classList.toggle('text-red-600', isQtyValid);
            issueMsg.classList.toggle('font-bold', isQtyValid);
            
            if (isQtyValid) {
                // Show Action Button (Issue)
                actionButtons.innerHTML = `
                    <button type="button" onclick="prepareRejectSubmission(${itemId}, ${qty})"
                            class="w-full py-3 bg-red-600 text-white rounded-xl text-sm font-black shadow-lg hover:bg-red-700 transition-colors animate-flash-red">
                        แจ้งปัญหา / ส่งคืน ⚠️
                    </button>
                `;
                
                // Temporarily update hidden inputs (Issue, without final rejectType, using issue_qty_handled)
                updateHiddenInputs(itemId, 'issue', qty);
            }
        }

        function prepareRejectSubmission(itemId, qty) {
            console.log(`4. Preparing Reject Submission for Item ${itemId}. Qty: ${qty}`);
            
            finalRejectSubmit.value = itemId;

            // Update hidden inputs one last time before modal (just in case)
            updateHiddenInputs(itemId, 'issue', qty); 

            showRejectModal(itemId);
        }

        // --- FINAL SUBMIT LOGIC ---

        function finalRejectSubmitAction(event) {
            event.preventDefault(); 

            const rejectType = rejectReasonSelect.value;
            const itemId = parseInt(finalRejectSubmit.value);
            const rejectNotes = rejectNotesInput.value;
            
            if (!rejectType) {
                console.log('ERROR: Reject reason not selected. Blocking submission.');
                rejectReasonSelect.reportValidity(); 
                return;
            }

            const qtyInput = document.querySelector(`#hidden-inputs-${itemId} input[name$="[issue_qty_handled]"]`);
            if (!qtyInput) {
                 console.error(`Fatal Error: Hidden issue_qty_handled input not found for item ${itemId}. Cannot submit.`);
                 hideRejectModal();
                 return;
            }

            // Finalize hidden inputs: update inspection_status and add rejection notes
            // This call uses the issue status logic, ensuring issue_qty_handled is used, not receive_now_quantity.
            updateHiddenInputs(itemId, 'issue', qtyInput.value, rejectType);

            // Add notes input to the main form scope
            let notesInput = form.querySelector('#final-reject-notes');
            if (!notesInput) {
                notesInput = document.createElement('input');
                notesInput.type = 'hidden';
                notesInput.id = 'final-reject-notes';
                form.appendChild(notesInput);
            }
            notesInput.name = `items[${itemId}][notes_reject_description]`;
            notesInput.value = rejectNotes;
            
            // Set the submit button's value and submit the form
            hideRejectModal();
            console.log(`5. Final Submission: Item ${itemId} rejected with reason: ${rejectType}. Submitting form.`);
            
            // Submit the form using the specific reject button's value
            form.querySelector('button[name="single_submit_reject"]').value = itemId;
            form.submit();
        }

        // Setup event listener for modal background click
        document.getElementById('reject-modal').addEventListener('click', (e) => {
            if (e.target.id === 'reject-modal') {
                hideRejectModal();
            }
        });

    </script>
    <style>
        /* Custom Styles for aesthetics and animations */
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Blue Flash for Receive Button */
        @keyframes flash-blue { 
            0%, 100% { background-color: #3b82f6; border-color: #60a5fa; color: white; } 
            50% { background-color: #2563eb; border-color: #3b82f6; color: white; } 
        }
        .animate-flash { animation: flash-blue 1.2s infinite; }

        /* Red Flash for Reject Button */
        @keyframes flash-red { 
            0%, 100% { background-color: #dc2626; color: white; border-color: #fca5a5; } 
            50% { background-color: #b91c1c; color: white; border-color: #ef4444; } 
        }
        .animate-flash-red { animation: flash-red 0.4s infinite; }

    </style>
    @endpush
@endsection