{{-- 
    Rating Modal Component (New System: 👍👌👎)
    - เปลี่ยนจากระบบดาว 3 คำถามเป็น 3 ปุ่มเลือก: ถูกใจ / พอใช้ / แย่
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
                    <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-purple-50">
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
                                    <div class="text-indigo-400"><i class="fas fa-clipboard-check text-xl"></i></div>
                                </div>
                                <p id="rating-item-sn" class="text-sm text-gray-500 font-mono mt-1">SN: -</p>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-8">
                        <div class="text-center mb-8">
                            <h3 class="text-xl font-bold text-gray-900 mb-2">อุปกรณ์นี้เป็นอย่างไรบ้าง?</h3>
                            <p class="text-sm text-gray-500">กรุณาให้คะแนนความพึงพอใจของคุณ</p>
                        </div>

                        {{-- ✅ 3 ปุ่มเลือก: ถูกใจ / พอใช้ / แย่ --}}
                        <div class="grid grid-cols-3 gap-4 mb-8">
                            {{-- ถูกใจ --}}
                            <label class="cursor-pointer group">
                                <input type="radio" name="feedback_type" value="good" class="peer sr-only feedback-radio" required>
                                <div class="flex flex-col items-center p-5 rounded-2xl border-2 border-gray-200 
                                    peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:shadow-lg peer-checked:shadow-green-200/50
                                    hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 group-hover:scale-[1.02]">
                                    <span class="text-5xl mb-3 transform group-hover:scale-110 transition-transform">👍</span>
                                    <span class="text-base font-bold text-green-600">ถูกใจ</span>
                                    <span class="text-xs text-gray-400 mt-1">ดีมาก!</span>
                                </div>
                            </label>
                            
                            {{-- พอใช้ --}}
                            <label class="cursor-pointer group">
                                <input type="radio" name="feedback_type" value="neutral" class="peer sr-only feedback-radio">
                                <div class="flex flex-col items-center p-5 rounded-2xl border-2 border-gray-200 
                                    peer-checked:border-yellow-500 peer-checked:bg-yellow-50 peer-checked:shadow-lg peer-checked:shadow-yellow-200/50
                                    hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 group-hover:scale-[1.02]">
                                    <span class="text-5xl mb-3 transform group-hover:scale-110 transition-transform">👌</span>
                                    <span class="text-base font-bold text-yellow-600">พอใช้</span>
                                    <span class="text-xs text-gray-400 mt-1">ปานกลาง</span>
                                </div>
                            </label>
                            
                            {{-- แย่ --}}
                            <label class="cursor-pointer group">
                                <input type="radio" name="feedback_type" value="bad" class="peer sr-only feedback-radio">
                                <div class="flex flex-col items-center p-5 rounded-2xl border-2 border-gray-200 
                                    peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:shadow-lg peer-checked:shadow-red-200/50
                                    hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 group-hover:scale-[1.02]">
                                    <span class="text-5xl mb-3 transform group-hover:scale-110 transition-transform">👎</span>
                                    <span class="text-base font-bold text-red-600">แย่</span>
                                    <span class="text-xs text-gray-400 mt-1">ต้องปรับปรุง</span>
                                </div>
                            </label>
                        </div>

                        {{-- แสดงผลที่เลือก --}}
                        <div id="feedback-preview" class="hidden text-center p-4 rounded-xl bg-gray-50 border border-gray-200 mb-6">
                            <span class="text-sm text-gray-600">คุณเลือก: </span>
                            <span id="feedback-preview-text" class="font-bold text-lg"></span>
                        </div>

                        <div>
                            <label for="rating-comment" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-comment-alt mr-1 text-gray-400"></i>
                                ข้อเสนอแนะเพิ่มเติม (ถ้ามี)
                            </label>
                            <textarea id="rating-comment" name="comment" rows="2" 
                                class="block w-full border-gray-300 rounded-xl shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-50 focus:bg-white transition-colors" 
                                placeholder="เช่น แบตหมดไว, อุปกรณ์ชำรุด, ใช้งานได้ดี..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 sm:flex sm:flex-row-reverse gap-2">
                    <button type="button" onclick="trySubmitRating()" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-6 py-3 bg-indigo-600 text-base font-bold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto transition-all active:scale-95 shadow-indigo-500/30">
                        <i class="fas fa-paper-plane mr-2"></i>
                        ส่งประเมิน
                    </button>
                    <button type="button" onclick="closeRatingModal()" class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-3 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto transition-all active:scale-95">
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
                        <h3 class="text-lg font-medium leading-6 text-gray-900">พบปัญหา</h3>
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
    if (typeof window.ratingQueue === 'undefined') {
        window.ratingQueue = [];
        window.currentRatingIndex = 0;
    }

    window.openRatingModal = function(items) {
        if (!Array.isArray(items) || items.length === 0) { 
            Swal.fire('Info', 'ไม่มีรายการที่ต้องประเมิน', 'info'); 
            return; 
        }
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
        if (item.equipment_image_url) { imgEl.src = item.equipment_image_url; } 
        else { imgEl.src = "{{ asset('images/no-image.png') }}"; }

        // Reset selection
        document.querySelectorAll('.feedback-radio').forEach(r => r.checked = false);
        document.getElementById('feedback-preview').classList.add('hidden');
        document.getElementById('rating-comment').value = '';
    }

    // ✅ แสดง Preview เมื่อเลือก
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('feedback-radio')) {
            const preview = document.getElementById('feedback-preview');
            const previewText = document.getElementById('feedback-preview-text');
            const val = e.target.value;
            
            let emoji = '', label = '', colorClass = '';
            if (val === 'good') { emoji = '👍'; label = 'ถูกใจ'; colorClass = 'text-green-600'; }
            else if (val === 'neutral') { emoji = '👌'; label = 'พอใช้'; colorClass = 'text-yellow-600'; }
            else if (val === 'bad') { emoji = '👎'; label = 'แย่'; colorClass = 'text-red-600'; }
            
            previewText.innerHTML = `<span class="${colorClass}">${emoji} ${label}</span>`;
            preview.classList.remove('hidden');
        }
    });

    window.trySubmitRating = function() {
        const selected = document.querySelector('input[name="feedback_type"]:checked');
        if (!selected) { 
            Swal.fire({ icon: 'warning', title: 'กรุณาเลือกประเมิน', text: 'เลือก ถูกใจ / พอใช้ / แย่', confirmButtonText: 'ตกลง' });
            return; 
        }
        
        document.getElementById('rating-modal').classList.add('hidden');
        document.getElementById('rating-confirm-modal').classList.remove('hidden');
    }

    window.finalSubmitRating = async function() {
        const item = window.ratingQueue[window.currentRatingIndex];
        const feedbackType = document.querySelector('input[name="feedback_type"]:checked').value;

        const formData = {
            feedback_type: feedbackType,
            comment: document.getElementById('rating-comment').value,
            _token: '{{ csrf_token() }}'
        };

        const btn = document.querySelector('#rating-confirm-modal button[onclick="finalSubmitRating()"]');
        const originalText = btn.innerText;
        btn.innerText = 'กำลังส่ง...';
        btn.disabled = true;

        const submitUrl = item.submit_url || `/transactions/${item.id}/rate`;

        try {
            const response = await fetch(submitUrl, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json' 
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json(); 

            if (response.ok && result.success) {
                closeConfirmModal();
                window.currentRatingIndex++;
                
                if (window.currentRatingIndex < window.ratingQueue.length) {
                    document.getElementById('rating-modal').classList.remove('hidden');
                }
                showRatingItem(window.currentRatingIndex);
            } else {
                throw new Error(result.message || 'Server returned error');
            }
        } catch (error) {
            console.error(error);
            closeConfirmModal();
            showErrorModal(error.message || 'เกิดข้อผิดพลาดในการส่งข้อมูล');
            document.getElementById('rating-modal').classList.remove('hidden');
        } finally {
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }

    window.closeConfirmModal = function() {
        document.getElementById('rating-confirm-modal').classList.add('hidden');
    }

    window.closeRatingModal = function() {
        document.getElementById('rating-modal').classList.add('hidden');
    }

    window.showErrorModal = function(msg) {
        document.getElementById('error-message-text').innerText = msg;
        document.getElementById('rating-error-modal').classList.remove('hidden');
    }

    window.closeErrorModal = function() {
        document.getElementById('rating-error-modal').classList.add('hidden');
    }
</script>

<style>
    @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .animate-scale-up { animation: scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    @keyframes shake { 0%,100%{transform:translateX(0);}10%,30%,50%,70%,90%{transform:translateX(-5px);}20%,40%,60%,80%{transform:translateX(5px);} }
    .animate-shake { animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both; }
</style>