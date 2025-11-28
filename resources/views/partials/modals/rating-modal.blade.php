{{-- 
    Rating Modal Component (Debug Version)
    - เพิ่มความสามารถในการอ่าน Error จาก HTML Response ของ Laravel
    - ช่วยให้รู้ว่า Error 500 เกิดจากอะไร (เช่น Column missing, Class not found)
--}}

{{-- 1. Main Rating Modal --}}
<div id="rating-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-600 bg-opacity-75 backdrop-blur-sm" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-xl sm:w-full animate-scale-up">
            <form id="rating-form" onsubmit="event.preventDefault(); trySubmitRating();">
                @csrf
                <div class="bg-white">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 h-20 w-20 bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm flex items-center justify-center p-1">
                                <img id="rating-item-img" src="" class="h-full w-full object-contain" alt="Equipment Image">
                            </div>
                            <div class="flex-1 min-w-0 pt-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span id="rating-counter" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 mb-2">
                                            รายการที่ 1 / 1
                                        </span>
                                        <h3 id="rating-item-name" class="text-lg font-bold text-gray-900 leading-tight truncate pr-4">
                                            ชื่ออุปกรณ์
                                        </h3>
                                    </div>
                                    <div class="text-gray-400"><i class="fas fa-clipboard-list text-xl"></i></div>
                                </div>
                                <p id="rating-item-sn" class="text-sm text-gray-500 font-mono mt-1">SN: -</p>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-6">
                        <div class="text-center sm:text-left mb-6">
                            <h3 class="text-base font-semibold leading-6 text-gray-900 flex items-center gap-2">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-yellow-100 text-yellow-600 text-xs"><i class="fas fa-star"></i></span>
                                ประเมินความพึงพอใจ
                            </h3>
                        </div>
                        <div class="space-y-6" id="questions-container"></div>
                        <div class="mt-6">
                            <label for="rating-comment" class="block text-sm font-medium text-gray-700">ข้อเสนอแนะเพิ่มเติม (ถ้ามี)</label>
                            <textarea id="rating-comment" name="comment" rows="2" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-gray-50 focus:bg-white transition-colors" placeholder="เช่น แบตหมดไว, เครื่องร้อนเร็ว"></textarea>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 sm:flex sm:flex-row-reverse gap-2">
                    <button type="button" onclick="trySubmitRating()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2.5 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm transition-all active:scale-95 shadow-blue-500/30">
                        ถัดไป / ส่งข้อมูล
                    </button>
                    <button type="button" onclick="closeRatingModal()" class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2.5 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all active:scale-95">
                        ไว้ทีหลัง
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 2. Confirmation Modal --}}
<div id="rating-confirm-modal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-80 backdrop-blur-sm" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-sm sm:w-full animate-scale-up">
            <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-green-100 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">ยืนยันข้อมูล</h3>
                        <div class="mt-2"><p class="text-sm text-gray-500">ตรวจสอบความถูกต้องเรียบร้อยแล้วใช่หรือไม่?</p></div>
                    </div>
                </div>
            </div>
            <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                <button type="button" onclick="finalSubmitRating()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">ยืนยัน</button>
                <button type="button" onclick="closeConfirmModal()" class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">กลับไปแก้ไข</button>
            </div>
        </div>
    </div>
</div>

{{-- 3. Error Modal --}}
<div id="rating-error-modal" class="fixed inset-0 z-[70] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-80 backdrop-blur-sm" aria-hidden="true" onclick="closeErrorModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-md sm:w-full animate-shake">
            <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-red-100 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">พบปัญหา (Error Log)</h3>
                        <div class="mt-2 w-full">
                            <p class="text-sm text-gray-500 mb-2">กรุณาแคปหน้าจอนี้แจ้งผู้ดูแลระบบ:</p>
                            <div class="p-3 bg-gray-100 rounded text-xs font-mono text-red-600 break-all overflow-y-auto max-h-40 border border-gray-300" id="error-message-text">
                                {{-- Error Detail will be here --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" onclick="closeErrorModal()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">รับทราบ</button>
            </div>
        </div>
    </div>
</div>

<script>
    if (typeof window.RATING_QUESTIONS === 'undefined') {
        window.RATING_QUESTIONS = {
            'one_way': [
                { id: 'q1', label: 'คุณภาพวัสดุ (Material Quality)', options: [{ value: 1, emoji: '👎', text: 'คุณภาพต่ำ', class: 'text-red-600' }, { value: 2, emoji: '📦', text: 'ยังไม่เคยใช้งาน', class: 'text-gray-500' }, { value: 3, emoji: '🛡️', text: 'ทนทานดี', class: 'text-green-600' }] },
                { id: 'q2', label: 'ความเหมาะสมกับงาน (Suitability)', options: [{ value: 1, emoji: '❌', text: 'ไม่ตรงงาน', class: 'text-red-600' }, { value: 2, emoji: '📦', text: 'ยังไม่เคยใช้งาน', class: 'text-gray-500' }, { value: 3, emoji: '✅', text: 'ใช้งานดี', class: 'text-green-600' }] },
                { id: 'q3', label: 'ความคุ้มค่า (Worthiness)', options: [{ value: 1, emoji: '💸', text: 'หมดไว', class: 'text-red-600' }, { value: 2, emoji: '📦', text: 'ยังไม่เคยใช้งาน', class: 'text-gray-500' }, { value: 3, emoji: '💎', text: 'คุ้มค่า', class: 'text-green-600' }] }
            ],
            'return_consumable': [
                { id: 'q1', label: 'การใช้งานจริง (Experience)', options: [{ value: 1, emoji: '😩', text: 'ใช้ยาก', class: 'text-red-600' }, { value: 2, emoji: '📦', text: 'ยังไม่เคยใช้งาน', class: 'text-gray-500' }, { value: 3, emoji: '🤩', text: 'ลื่นไหล', class: 'text-green-600' }] },
                { id: 'q2', label: 'การกะปริมาณ (Estimation)', options: [{ value: 1, emoji: '📉', text: 'เหลือเยอะ', class: 'text-red-600' }, { value: 2, emoji: '📦', text: 'ยังไม่เคยใช้งาน', class: 'text-gray-500' }, { value: 3, emoji: '🎯', text: 'พอดีเป๊ะ', class: 'text-green-600' }] },
                { id: 'q3', label: 'สภาพของเหลือคืน (Condition)', options: [{ value: 1, emoji: '🏚️', text: 'สภาพแย่', class: 'text-red-600' }, { value: 2, emoji: '📦', text: 'ยังไม่เคยใช้งาน', class: 'text-gray-500' }, { value: 3, emoji: '✨', text: 'เหมือนใหม่', class: 'text-green-600' }] }
            ],
            'borrow': [
                { id: 'q1', label: 'ประสิทธิภาพเครื่อง (Performance)', options: [{ value: 1, emoji: '🐌', text: 'อืด/ช้า', class: 'text-red-600' }, { value: 2, emoji: '📦', text: 'ยังไม่เคยใช้งาน', class: 'text-gray-500' }, { value: 3, emoji: '🚀', text: 'เร็ว/แรง', class: 'text-green-600' }] },
                { id: 'q2', label: 'ความถนัดมือ (Ergonomics)', options: [{ value: 1, emoji: '✋', text: 'จับยาก', class: 'text-red-600' }, { value: 2, emoji: '📦', text: 'ยังไม่เคยใช้งาน', class: 'text-gray-500' }, { value: 3, emoji: '👌', text: 'ถนัดมือ', class: 'text-green-600' }] },
                { id: 'q3', label: 'สภาพหลังใช้ (Condition)', options: [{ value: 1, emoji: '🤕', text: 'มีรอยเพิ่ม', class: 'text-red-600' }, { value: 2, emoji: '📦', text: 'ยังไม่เคยใช้งาน', class: 'text-gray-500' }, { value: 3, emoji: '🆕', text: 'สภาพเดิม', class: 'text-green-600' }] }
            ]
        };
    }

    if (typeof window.ratingQueue === 'undefined') {
        window.ratingQueue = [];
        window.currentRatingIndex = 0;
    }

    window.openRatingModal = function(items) {
        if (!Array.isArray(items) || items.length === 0) { Swal.fire('Info', 'ไม่มีรายการที่ต้องประเมิน', 'info'); return; }
        window.ratingQueue = items;
        window.currentRatingIndex = 0;
        showRatingItem(0);
        document.getElementById('rating-modal').classList.remove('hidden');
    }

    window.showRatingItem = function(index) {
        if (index >= window.ratingQueue.length) {
            closeRatingModal();
            Swal.fire({ icon: 'success', title: 'ขอบคุณครับ!', text: 'บันทึกการประเมินครบทุกรายการแล้ว', timer: 2000, showConfirmButton: false });
            setTimeout(() => location.reload(), 2000);
            return;
        }
        const item = window.ratingQueue[index];
        const total = window.ratingQueue.length;
        
        document.getElementById('rating-counter').innerText = `รายการที่ ${index + 1} / ${total}`;
        document.getElementById('rating-item-name').innerText = item.equipment?.name || 'Unknown Item';
        document.getElementById('rating-item-sn').innerText = item.equipment?.serial_number ? `SN: ${item.equipment.serial_number}` : 'SN: -';
        
        const imgEl = document.getElementById('rating-item-img');
        if (item.equipment_image_url) { imgEl.src = item.equipment_image_url; } else { imgEl.src = "{{ asset('images/placeholder.webp') }}"; }

        let typeKey = 'one_way';
        if (item.type === 'borrow' || item.type === 'returnable') typeKey = 'borrow';
        else if (item.type === 'partial_return') typeKey = 'return_consumable';
        else typeKey = 'one_way';

        const questions = window.RATING_QUESTIONS[typeKey] || window.RATING_QUESTIONS['one_way'];
        const container = document.getElementById('questions-container');
        container.innerHTML = '';

        questions.forEach((q, i) => {
            const html = `
                <div class="question-group border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                    <label class="block text-sm font-bold text-gray-800 mb-3">${i + 1}. ${q.label}</label>
                    <div class="grid grid-cols-3 gap-3">
                        ${q.options.map(opt => `
                            <label class="cursor-pointer group relative">
                                <input type="radio" name="${q.id}" value="${opt.value}" class="peer sr-only rating-radio" data-question="${q.id}" onclick="handleRadioClick(this)" required ${opt.value === 2 ? 'checked' : ''}>
                                <div class="h-20 flex flex-col items-center justify-center p-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:border-transparent transition-all shadow-sm ${opt.value === 1 ? 'peer-checked:ring-red-500 peer-checked:bg-red-50' : ''} ${opt.value === 2 ? 'peer-checked:ring-gray-400 peer-checked:bg-gray-100' : ''} ${opt.value === 3 ? 'peer-checked:ring-green-500 peer-checked:bg-green-50' : ''}">
                                    <span class="text-2xl mb-1 filter drop-shadow-sm transform group-hover:scale-110 transition-transform">${opt.emoji}</span>
                                    <span class="text-xs font-bold text-center leading-tight ${opt.class}">${opt.text}</span>
                                </div>
                            </label>
                        `).join('')}
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        });
        document.getElementById('rating-comment').value = '';
    }

    window.closeRatingModal = function() { document.getElementById('rating-modal').classList.add('hidden'); }

    window.handleRadioClick = function(radio) {
        if (radio.value == 2) {
            const allRadios = document.querySelectorAll('.rating-radio[value="2"]');
            allRadios.forEach(r => r.checked = true);
        }
    }

    window.showErrorModal = function(msg) {
        document.getElementById('error-message-text').innerText = msg;
        document.getElementById('rating-error-modal').classList.remove('hidden');
    }
    window.closeErrorModal = function() { document.getElementById('rating-error-modal').classList.add('hidden'); }
    window.closeConfirmModal = function() { document.getElementById('rating-confirm-modal').classList.add('hidden'); document.getElementById('rating-modal').classList.remove('hidden'); }

    window.trySubmitRating = function() {
        const q1 = document.querySelector('input[name="q1"]:checked')?.value;
        const q2 = document.querySelector('input[name="q2"]:checked')?.value;
        const q3 = document.querySelector('input[name="q3"]:checked')?.value;

        if (!q1 || !q2 || !q3) { showErrorModal('กรุณาตอบคำถามให้ครบทุกข้อ'); return; }
        
        const values = [q1, q2, q3];
        const hasUnused = values.includes('2');
        const hasScore = values.includes('1') || values.includes('3');
        if (hasUnused && hasScore) { showErrorModal('ข้อมูลขัดแย้งกัน: หากท่านเลือก "ยังไม่เคยใช้งาน" จำเป็นต้องเลือกเหมือนกันทุกข้อ'); return; }

        document.getElementById('rating-modal').classList.add('hidden');
        document.getElementById('rating-confirm-modal').classList.remove('hidden');
    }

    // ✅ AJAX Submission Logic (Debug Enhanced)
    window.finalSubmitRating = async function() {
        const item = window.ratingQueue[window.currentRatingIndex];
        const formData = {
            q1: document.querySelector('input[name="q1"]:checked').value,
            q2: document.querySelector('input[name="q2"]:checked').value,
            q3: document.querySelector('input[name="q3"]:checked').value,
            comment: document.getElementById('rating-comment').value,
            _token: '{{ csrf_token() }}'
        };

        const btn = document.querySelector('#rating-confirm-modal button[onclick="finalSubmitRating()"]');
        const originalText = btn.innerText;
        btn.innerText = 'กำลังส่ง...';
        btn.disabled = true;

        try {
            const response = await fetch(`/transactions/${item.id}/rate`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json' 
                },
                body: JSON.stringify(formData)
            });

            // ตรวจสอบว่า Response เป็น JSON หรือไม่
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                const text = await response.text();
                
                // 🕵️‍♂️ Detective Code: พยายามแกะ Error จาก HTML
                let debugMessage = "Server Error (500): ไม่สามารถอ่านค่า JSON ได้";
                
                // ลองหา Title ของหน้า Error
                const titleMatch = text.match(/<title>(.*?)<\/title>/i);
                if (titleMatch && titleMatch[1]) {
                    debugMessage += "\n\n[" + titleMatch[1] + "]";
                }

                // ลองหา Exception Message (เฉพาะ Laravel Ignition Page)
                // มองหาคำว่า SQLSTATE หรือ Exception
                if (text.includes('SQLSTATE')) {
                    const matches = text.match(/SQLSTATE\[.*?\]: (.*?)( in |$)/);
                    if (matches && matches[1]) debugMessage += "\n\nSQL Error: " + matches[1];
                } else if (text.includes('Exception:')) {
                     const matches = text.match(/Exception: (.*?)( in |$)/);
                     if (matches && matches[1]) debugMessage += "\n\nException: " + matches[1];
                }

                console.error("Server Error HTML:", text); // Log เต็มๆ ใน Console
                throw new Error(debugMessage);
            }

            const result = await response.json(); 

            if (response.ok && result.success) {
                closeConfirmModal();
                document.getElementById('rating-modal').classList.remove('hidden');
                window.currentRatingIndex++;
                showRatingItem(window.currentRatingIndex);
            } else {
                throw new Error(result.message || 'Server returned error');
            }
        } catch (error) {
            console.error(error);
            closeConfirmModal();
            // แสดง Error ที่แกะมาได้ ใน Modal
            showErrorModal(error.message || 'เกิดข้อผิดพลาดในการส่งข้อมูล');
            document.getElementById('rating-modal').classList.remove('hidden');
        } finally {
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }
</script>

<style>
    @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .animate-scale-up { animation: scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    @keyframes shake { 0%,100%{transform:translateX(0);}10%,30%,50%,70%,90%{transform:translateX(-5px);}20%,40%,60%,80%{transform:translateX(5px);} }
    .animate-shake { animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both; }
</style>