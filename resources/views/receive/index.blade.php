@extends('layouts.app')

@section('header', '📥 การตรวจสอบและรับวัสดุ (Goods Receiving)')
@section('subtitle', "ศูนย์กลางการยืนยันการรับเข้าคลังสินค้า [" . ($currentDeptName ?? 'General') . "]")

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

        <div id="receive-container" class="max-w-[98%] mx-auto px-2 sm:px-4 mt-6">
            @include('receive.partials._list')
        </div>
    </div>

    @push('scripts')
    <script>
        // Global references (Vanilla JS)
        // Note: These must be re-queried after AJAX/DOM updates if they were inside the partial
        // But since they are queried inside functions, they should work fine as long as IDs exist.
        
        // --- AUTO UPDATE LOGIC ---
        let autoRefreshInterval;

        function fetchReceiveUpdates() {
            // ✅ Safety Check: Don't refresh if user is typing or selecting something (interacting)
            if (document.querySelectorAll('input:focus, select:focus, textarea:focus').length > 0) {
                console.log('User interacting, skipping auto-refresh.');
                return;
            }
            // Check if any row has 'bg-blue-50/20' or 'bg-red-50/20' which implies user started a process
            // (Actually, checking if any select has a value other than '' might be better)
            const selects = document.querySelectorAll('select[id^="status-"]');
            let isDirty = false;
            selects.forEach(sel => {
                if (sel.value && sel.value !== '') isDirty = true;
            });

            if (isDirty) {
               console.log('Form is dirty (Inspection started), skipping auto-refresh.');
               return; 
            }

            const url = window.location.href;

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text();
            })
            .then(html => {
                const container = document.getElementById('receive-container');
                if (container) {
                    // Simple replacement
                    container.innerHTML = html;
                    console.log('✅ Auto-updated receive list.');
                    
                    // Re-attach modal listeners if needed? 
                    // The modal listener is on document/body delegating to ID, which works if ID matches.
                    // But our modal listener code:
                    /*
                    const modal = document.getElementById('reject-modal');
                    if (modal) { ... }
                    */
                   // Since modal is inside the partial, the element reference 'modal' in the script below (DOMContentLoaded) is stale!
                   // We need to re-bind the listener.
                   rebindModalListener();
                }
            })
            .catch(error => console.error('Auto-update failed:', error));
        }
        
        function rebindModalListener() {
            const modal = document.getElementById('reject-modal');
             if (modal) {
                 // Remove old (implicit by replacement)
                 // Add new
                 modal.onclick = function(e) {
                     if (e.target.id === 'reject-modal') {
                        hideRejectModal();
                     }
                 };
             }
        }

        document.addEventListener('DOMContentLoaded', () => {
             rebindModalListener(); // Initial bind (or use the one below)

             if (!document.hidden) {
                 autoRefreshInterval = setInterval(fetchReceiveUpdates, 15000); // 15s wait
             }

             document.addEventListener('visibilitychange', () => {
                 if (document.hidden) {
                     clearInterval(autoRefreshInterval);
                 } else {
                     fetchReceiveUpdates();
                     autoRefreshInterval = setInterval(fetchReceiveUpdates, 15000);
                 }
             });
        });


        // --- ORIGINAL SCRIPTS (Functions are global, so they persist) ---

        const form = document.getElementById('receiveForm'); // This might be null updates. Re-query on submit?
        // Actually, form is inside partial. Global var 'form' is evaluated ONCE at page load.
        // We should change functions to query form dynamically or update the variable.
        // But the functions below use specific ID lookups like getElementById('receiveForm').
        // Wait, line 248: `const form = ...`. This executes ONCE.
        // If we replace DOM, `form` variable refers to detached element.
        // FIX: Remove global const form, use getElementById inside function.

        // Same for other global refs.
        
        // --- HELPER FUNCTIONS ---

        function getEl(id) { return document.getElementById(id); }

        /**
         * Clears and updates the hidden input fields for a specific row to prepare for submission.
         */
        function updateHiddenInputs(itemId, status, qty, rejectType = '') {
            const hiddenInputs = getEl(`hidden-inputs-${itemId}`);
            if(!hiddenInputs) return;
            
            hiddenInputs.innerHTML = '';
            
            if (status === 'pass') {
                let html = `
                    <input type="hidden" name="items[${itemId}][selected]" value="1">
                    <input type="hidden" name="items[${itemId}][receive_now_quantity]" value="${qty}">
                    <input type="hidden" name="items[${itemId}][inspection_status]" value="pass">
                `;
                hiddenInputs.innerHTML = html;

            } else if (status === 'issue') {
                let html = `
                    <input type="hidden" name="items[${itemId}][selected]" value="1">
                    <input type="hidden" name="items[${itemId}][issue_qty_handled]" value="${qty}">
                `;
                if (rejectType) {
                    html += `<input type="hidden" id="final-status-input-${itemId}" name="items[${itemId}][inspection_status]" value="${rejectType}">`;
                }
                hiddenInputs.innerHTML = html;
            }
        }

        // --- MODAL CONTROL FUNCTIONS ---

        function showRejectModal(itemId) {
            console.log(`4. Modal: Showing for Item ID ${itemId}.`);
            const rejectModal = getEl('reject-modal');
            const finalRejectSubmit = getEl('final-reject-submit');
            const rejectReasonSelect = getEl('reject-reason-select');

            if (rejectModal && finalRejectSubmit && rejectReasonSelect) {
                finalRejectSubmit.dataset.itemId = itemId;
                rejectReasonSelect.setAttribute('required', 'required');
                rejectModal.classList.remove('hidden');
            }
        }

        function hideRejectModal() {
            const rejectModal = getEl('reject-modal');
            const rejectReasonSelect = getEl('reject-reason-select');
            const rejectNotesInput = getEl('reject-notes-input');

            if (rejectModal && rejectReasonSelect && rejectNotesInput) {
                rejectReasonSelect.removeAttribute('required');
                rejectReasonSelect.value = '';
                rejectNotesInput.value = '';
                rejectModal.classList.add('hidden');
            }
        }
        
        // --- CORE WORKFLOW LOGIC ---

        function handleStatusChange(itemId) {
            const statusSelect = getEl(`status-${itemId}`);
            const status = statusSelect.value;
            const row = getEl(`row-${itemId}`);
            const maxQty = parseInt(row.dataset.maxQty);
            
            console.log(`--- DEBUG: Item ${itemId} ---`);
            console.log(`1. Status Selected: ${status}`);

            const qtyWrapper = getEl(`qty-wrapper-${itemId}`);
            const actionButtons = getEl(`action-buttons-${itemId}`);
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

                // B. Show Action Button (Receive)
                actionButtons.innerHTML = `
                    <button type="submit" name="single_submit" value="${itemId}" 
                            class="w-full py-3 bg-blue-600 text-white rounded-xl text-sm font-black shadow-lg hover:bg-blue-700 transition-colors animate-flash">
                        นำเข้าคลัง ✅
                    </button>
                `;

                // C. Add Hidden Inputs
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
            const qtyInput = getEl(`qty-input-${itemId}`);
            const actionButtons = getEl(`action-buttons-${itemId}`);
            const issueMsg = getEl(`issue-msg-${itemId}`);
            
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
                
                // Temporarily update hidden inputs (Issue)
                updateHiddenInputs(itemId, 'issue', qty);
            }
        }

        function prepareRejectSubmission(itemId, qty) {
            console.log(`4. Preparing Reject Submission for Item ${itemId}. Qty: ${qty}`);
            
            const finalRejectSubmit = getEl('final-reject-submit');
            if (finalRejectSubmit) {
                finalRejectSubmit.value = itemId;
            }

            // Update hidden inputs
            updateHiddenInputs(itemId, 'issue', qty); 

             showRejectModal(itemId);
        }

        // --- FINAL SUBMIT LOGIC ---

        function finalRejectSubmitAction(event) {
            event.preventDefault(); 
            
            const rejectReasonSelect = getEl('reject-reason-select');
            const finalRejectSubmit = getEl('final-reject-submit');
            const rejectNotesInput = getEl('reject-notes-input');

            if (!rejectReasonSelect || !finalRejectSubmit) return;

            const rejectType = rejectReasonSelect.value;
            const itemId = parseInt(finalRejectSubmit.value);
            const rejectNotes = rejectNotesInput ? rejectNotesInput.value : '';
            
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

            // Finalize hidden inputs
            updateHiddenInputs(itemId, 'issue', qtyInput.value, rejectType);

            // Add notes input to the main form scope
            const form = getEl('receiveForm');
            if (form) {
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
                const btn = form.querySelector('button[name="single_submit_reject"]');
                if(btn) btn.value = itemId;
                form.submit();
            }
        }
        
        function resendInspection(itemId) {
            if(!confirm('ยืนยันการส่งข้อมูลไปยัง PU อีกครั้ง?')) return;

            // Create a temporary form to submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/receive/resend/${itemId}`;
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            
            form.appendChild(csrfInput);
            document.body.appendChild(form);
            form.submit();
        }
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