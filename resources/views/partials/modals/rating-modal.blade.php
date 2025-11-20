<!-- Rating Modal -->
<div id="rating-modal" class="fixed inset-0 z-[200] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-90 backdrop-blur-sm"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative w-full max-w-2xl overflow-hidden bg-white rounded-2xl shadow-2xl transform transition-all">
            
            <!-- Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-blue-600 border-b border-indigo-500">
                <h3 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="bg-white/20 w-10 h-10 flex items-center justify-center rounded-full text-2xl shadow-inner">⭐</span> 
                    <span>ให้คะแนนความพึงพอใจ</span>
                </h3>
                <p class="mt-1 text-indigo-100 text-sm">
                    เพื่อสิทธิ์ในการเบิกครั้งถัดไป กรุณาประเมินรายการที่ใช้งานเสร็จสิ้น
                </p>
            </div>

            <!-- Body -->
            <div class="px-6 py-6 bg-gray-50 max-h-[65vh] overflow-y-auto custom-scrollbar" id="rating-list-container">
                <!-- Items injected here -->
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 bg-white border-t border-gray-200 flex justify-between items-center">
                <span class="text-xs text-gray-400"><i class="fas fa-info-circle"></i> ต้องให้คะแนนทุกรายการ</span>
                <button type="button" onclick="closeRatingModal()" class="px-5 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors border border-gray-300">
                    ปิดหน้าต่าง (ยังไม่เบิก)
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #c7c7c7; border-radius: 3px; }
    .rating-btn-active { background-color: #4f46e5 !important; color: white !important; border-color: #4f46e5 !important; transform: scale(1.05); box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3); }
</style>

<script>
    // Prevent duplicate declaration
    if (typeof RATING_MODAL_INITIALIZED === 'undefined') {
        var RATING_MODAL_INITIALIZED = true;
        
        function openRatingModal(unratedItems) {
            const container = document.getElementById('rating-list-container');
            const modal = document.getElementById('rating-modal');
            container.innerHTML = ''; 
            
            if (!unratedItems || unratedItems.length === 0) { closeRatingModal(); return; }

            unratedItems.forEach(item => {
                // Image handling
                let img = item.equipment && item.equipment.image_url ? item.equipment.image_url : 'https://placehold.co/150x150/e2e8f0/64748b?text=No+Image';
                
                // Type Label
                const typeMap = {'consumable': 'เบิกเปลือง', 'returnable': 'ยืมคืน', 'partial_return': 'กึ่งยืม'};
                const typeText = typeMap[item.type] || item.type;

                // Buttons HTML
                let buttonsHtml = '';
                for (let i = 1; i <= 5; i++) {
                    let label = '';
                    if(i==1) label='แย่'; if(i==5) label='ดีเยี่ยม';
                    
                    buttonsHtml += `
                        <button type="button" 
                            onclick="submitRatingItem(${item.id}, ${i}, this)"
                            class="flex-1 py-3 px-1 border rounded-lg font-bold text-lg transition-all duration-200 hover:bg-indigo-50 border-gray-200 text-gray-600 bg-white group relative"
                            data-val="${i}">
                            ${i}
                            ${label ? `<span class="absolute -bottom-5 left-0 right-0 text-[10px] font-normal text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity">${label}</span>` : ''}
                        </button>
                    `;
                }

                const html = `
                    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200 mb-6 relative overflow-hidden" id="rating-card-${item.id}">
                        <div class="flex gap-5 items-start">
                            <div class="w-20 h-20 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden flex-shrink-0">
                                <img src="${img}" class="w-full h-full object-cover" onerror="this.src='https://placehold.co/150x150/e2e8f0/64748b?text=Error'">
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="text-base font-bold text-gray-800 truncate pr-2">${item.equipment ? item.equipment.name : 'Unknown Item'}</h4>
                                        <p class="text-xs text-gray-500 mt-0.5">TXN: #${item.id} • <span class="text-indigo-600 font-medium bg-indigo-50 px-1.5 py-0.5 rounded">${typeText}</span></p>
                                    </div>
                                    <span class="text-[10px] text-gray-400 whitespace-nowrap">${new Date(item.transaction_date).toLocaleDateString('th-TH')}</span>
                                </div>
                                
                                <div class="mt-3">
                                    <div class="flex gap-2 mb-2 items-center justify-between">
                                        ${buttonsHtml}
                                    </div>
                                    <div class="text-center h-6 mt-3" id="desc-${item.id}">
                                         <span class="text-xs text-gray-400">แตะตัวเลขเพื่อโหวต</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', html);
            });

            modal.classList.remove('hidden');
        }

        function closeRatingModal() {
            document.getElementById('rating-modal').classList.add('hidden');
        }

        function submitRatingItem(id, rating, btn) {
            console.log(`[Rating] ID: ${id}, Score: ${rating}`);
            const card = document.getElementById(`rating-card-${id}`);
            const btns = card.querySelectorAll(`button`);
            const descEl = document.getElementById(`desc-${id}`);

            // Disable UI
            btns.forEach(b => {
                b.disabled = true;
                b.classList.add('opacity-40', 'cursor-not-allowed');
                if (b === btn) {
                    b.classList.remove('opacity-40', 'border-gray-200', 'text-gray-600', 'bg-white');
                    b.classList.add('rating-btn-active');
                }
            });
            
            descEl.innerHTML = '<i class="fas fa-circle-notch fa-spin text-indigo-500"></i> กำลังส่งข้อมูล...';

            // API Call
            fetch(`/transactions/${id}/rate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ rating: parseInt(rating) }) // Ensure integer
            })
            .then(res => {
                if (!res.ok) throw new Error(res.statusText);
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    descEl.innerHTML = '<span class="text-green-600 font-bold text-sm"><i class="fas fa-check-circle"></i> เรียบร้อย</span>';
                    
                    // Animate removal
                    setTimeout(() => {
                        card.style.transition = 'all 0.5s ease';
                        card.style.transform = 'translateX(50px)';
                        card.style.opacity = '0';
                        setTimeout(() => { 
                            card.remove(); 
                            // Check if all cleared
                            if (data.remaining_count === 0) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'ขอบคุณครับ!',
                                    text: 'บันทึกคะแนนครบถ้วนแล้ว สามารถทำรายการต่อได้เลย',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    closeRatingModal();
                                    // Optional: Trigger click on original button again if needed
                                });
                            } else {
                                // Show toast for remaining
                                const container = document.getElementById('rating-list-container');
                                if(container.children.length === 0) closeRatingModal(); 
                            }
                        }, 500);
                    }, 600);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(err => {
                console.error('[Rating Error]', err);
                descEl.innerHTML = `<span class="text-red-500 text-xs"><i class="fas fa-exclamation-circle"></i> ${err.message || 'เกิดข้อผิดพลาด'}</span>`;
                btns.forEach(b => {
                    b.disabled = false;
                    b.classList.remove('opacity-40', 'cursor-not-allowed', 'rating-btn-active');
                    b.classList.add('bg-white', 'text-gray-600');
                });
            });
        }
    }
</script>