<div id="rating-modal" class="fixed inset-0 z-[200] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-90 backdrop-blur-sm"></div>

    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative w-full max-w-3xl overflow-hidden bg-white rounded-2xl shadow-2xl transform transition-all dark:bg-gray-800">
            
            <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-blue-600 border-b border-indigo-500">
                <h3 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="bg-white/20 w-10 h-10 flex items-center justify-center rounded-full text-2xl shadow-inner">⭐</span> 
                    <span>ประเมินความพึงพอใจ</span>
                </h3>
                <p class="mt-1 text-indigo-100 text-sm">
                    กรุณาให้คะแนนตามสภาพจริงของอุปกรณ์แต่ละประเภท
                </p>
            </div>

            <div class="px-6 py-6 bg-gray-50 max-h-[70vh] overflow-y-auto custom-scrollbar dark:bg-gray-900" id="rating-list-container">
                </div>

            <div class="px-6 py-4 bg-gray-100 border-t border-gray-200 flex justify-end dark:bg-gray-800 dark:border-gray-700">
                <button type="button" class="text-gray-500 hover:text-gray-700 text-sm font-medium px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700" onclick="closeRatingModal()">
                    ปิดหน้าต่าง (ยังไม่ประเมิน)
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // ✅ ฟังก์ชันเปิด Modal
    function openRatingModal(unratedItems) {
        const modal = document.getElementById('rating-modal');
        const container = document.getElementById('rating-list-container');
        
        // เคลียร์ข้อมูลเก่า
        container.innerHTML = '';

        // 🏷️ แยกชุดคำตามประเภทการเบิก (Type-Specific Labels)
        const ratingConfig = {
            'consumable': {
                1: { text: 'คุณภาพแย่',    icon: '😫', color: 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100' },
                2: { text: 'พอใช้',        icon: '😐', color: 'bg-orange-50 text-orange-600 border-orange-200 hover:bg-orange-100' },
                3: { text: 'ตามมาตรฐาน',   icon: '🙂', color: 'bg-yellow-50 text-yellow-600 border-yellow-200 hover:bg-yellow-100' },
                4: { text: 'คุณภาพดี',     icon: '😀', color: 'bg-lime-50 text-lime-600 border-lime-200 hover:bg-lime-100' },
                5: { text: 'ดีเยี่ยม',     icon: '✨', color: 'bg-green-50 text-green-600 border-green-200 hover:bg-green-100' }
            },
            'returnable': {
                1: { text: 'ชำรุด/พัง',    icon: '🛠️', color: 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100' },
                2: { text: 'สภาพเก่า',     icon: '🏚️', color: 'bg-orange-50 text-orange-600 border-orange-200 hover:bg-orange-100' },
                3: { text: 'ใช้งานได้ปกติ', icon: '👌', color: 'bg-yellow-50 text-yellow-600 border-yellow-200 hover:bg-yellow-100' },
                4: { text: 'สภาพดี',       icon: '🔨', color: 'bg-lime-50 text-lime-600 border-lime-200 hover:bg-lime-100' },
                5: { text: 'สภาพใหม่',     icon: '💎', color: 'bg-green-50 text-green-600 border-green-200 hover:bg-green-100' }
            },
            'partial_return': {
                1: { text: 'ต้องซ่อมแซม',  icon: '🔧', color: 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100' },
                2: { text: 'มีตำหนิ',      icon: '⚠️', color: 'bg-orange-50 text-orange-600 border-orange-200 hover:bg-orange-100' },
                3: { text: 'ปานกลาง',     icon: '🆗', color: 'bg-yellow-50 text-yellow-600 border-yellow-200 hover:bg-yellow-100' },
                4: { text: 'สมบูรณ์',      icon: '✅', color: 'bg-lime-50 text-lime-600 border-lime-200 hover:bg-lime-100' },
                5: { text: 'ไร้ที่ติ',     icon: '🏆', color: 'bg-green-50 text-green-600 border-green-200 hover:bg-green-100' }
            },
            'default': {
                1: { text: 'ต้องปรับปรุง', icon: '😞', color: 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100' },
                2: { text: 'พอใช้',       icon: '😐', color: 'bg-orange-50 text-orange-600 border-orange-200 hover:bg-orange-100' },
                3: { text: 'ปานกลาง',    icon: '🙂', color: 'bg-yellow-50 text-yellow-600 border-yellow-200 hover:bg-yellow-100' },
                4: { text: 'ดี',          icon: '😀', color: 'bg-lime-50 text-lime-600 border-lime-200 hover:bg-lime-100' },
                5: { text: 'ดีเยี่ยม',    icon: '😍', color: 'bg-green-50 text-green-600 border-green-200 hover:bg-green-100' }
            }
        };

        if (!unratedItems || unratedItems.length === 0) return;

        unratedItems.forEach((item, index) => {
            const typeKey = item.type && ratingConfig[item.type] ? item.type : 'default';
            const currentLabels = ratingConfig[typeKey];

            const itemDiv = document.createElement('div');
            itemDiv.className = `mb-4 bg-white p-5 rounded-xl shadow-sm border border-gray-200 relative overflow-hidden animate-fade-in-up dark:bg-gray-800 dark:border-gray-700`;
            itemDiv.style.animationDelay = `${index * 100}ms`;
            itemDiv.id = `rating-card-${item.id}`;

            // 1. Badge (ย้ายตำแหน่งออกจากรูปภาพ)
            let typeBadge = '';
            if(item.type === 'consumable') typeBadge = '<span class="text-orange-600 bg-orange-100 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider whitespace-nowrap">วัสดุสิ้นเปลือง</span>';
            else if(item.type === 'returnable') typeBadge = '<span class="text-purple-600 bg-purple-100 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider whitespace-nowrap">ยืม-คืน</span>';
            else typeBadge = '<span class="text-blue-600 bg-blue-100 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider whitespace-nowrap">เบิกคืนได้</span>';

            // ส่วนหัว (Header)
            const itemHeader = `
                <div class="flex items-start gap-4 mb-3">
                    <div class="w-16 h-16 flex-shrink-0 bg-gray-100 rounded-lg overflow-hidden border border-gray-200 dark:bg-gray-700 dark:border-gray-600 relative group">
                        <img src="${item.equipment_image_url || '/images/placeholder.webp'}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="Equipment">
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2 min-w-0">
                                <h4 class="text-base font-bold text-gray-800 truncate dark:text-gray-100" title="${item.equipment?.name}">${item.equipment?.name || 'Unknown Item'}</h4>
                                <div class="flex-shrink-0">${typeBadge}</div>
                            </div>
                            <span class="text-xs font-mono text-gray-400 bg-gray-100 px-2 py-1 rounded dark:bg-gray-700 ml-2 flex-shrink-0">#${item.id}</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-0.5 dark:text-gray-400"><i class="fas fa-calendar-alt mr-1 opacity-70"></i> ${new Date(item.transaction_date).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' })}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400"><i class="fas fa-cubes mr-1 opacity-70"></i> ${Math.abs(item.quantity_change)} ${item.equipment?.unit?.name || 'ชิ้น'}</p>
                    </div>
                </div>
            `;

            // ปุ่มประเมิน
            let buttonsHtml = `<div class="grid grid-cols-5 gap-2 mb-0">`; // mb-0 เพราะเอา comment ออกแล้ว
            for (let i = 1; i <= 5; i++) {
                const label = currentLabels[i];
                buttonsHtml += `
                    <button type="button" 
                        class="rating-btn group relative flex flex-col items-center justify-center p-2 rounded-xl border transition-all duration-200 ${label.color} dark:bg-opacity-10 dark:border-opacity-20 h-16"
                        onclick="submitRating(${item.id}, ${i}, this)"
                        data-score="${i}">
                        <span class="text-xl mb-1 group-hover:scale-125 transition-transform filter drop-shadow-sm">${label.icon}</span>
                        <span class="text-[9px] font-bold whitespace-nowrap overflow-hidden text-ellipsis w-full text-center leading-tight">${label.text}</span>
                        <div class="absolute inset-0 rounded-xl ring-2 ring-offset-2 ring-indigo-500 opacity-0 scale-95 transition-all duration-200 pointer-events-none selection-ring"></div>
                    </button>
                `;
            }
            buttonsHtml += `</div>`;

            // 2. ส่วนคอมเมนต์ (เอา Input ออกเหลือแต่ Status)
            const statusSection = `
                <div id="rating-status-${item.id}" class="mt-2 h-0 overflow-hidden text-xs font-medium text-center opacity-0 transition-all"></div>
            `;

            itemDiv.innerHTML = itemHeader + buttonsHtml + statusSection;
            container.appendChild(itemDiv);
        });

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; 
    }

    function closeRatingModal() {
        const modal = document.getElementById('rating-modal');
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        
        if (window.hasRated) {
            window.location.reload();
        }
    }

    // ✅ ฟังก์ชันส่งคะแนน
    async function submitRating(transactionId, score, btnElement) {
        const card = document.getElementById(`rating-card-${transactionId}`);
        const statusText = document.getElementById(`rating-status-${transactionId}`);
        // 2. เอาค่า comment ออก (ส่งค่าว่าง)
        const comment = ''; 

        // Lock Buttons
        const allBtns = card.querySelectorAll('.rating-btn');
        allBtns.forEach(b => {
            b.disabled = true;
            b.classList.add('opacity-40', 'cursor-not-allowed', 'grayscale');
            b.querySelector('.selection-ring').classList.remove('opacity-100', 'scale-100');
        });
        
        // Highlight Selected
        btnElement.classList.remove('opacity-40', 'cursor-not-allowed', 'grayscale');
        btnElement.classList.add('ring-2', 'ring-offset-2', 'ring-indigo-500', 'transform', 'scale-105', 'z-10', 'bg-white', 'shadow-md');
        btnElement.querySelector('.selection-ring').classList.add('opacity-100', 'scale-100');

        statusText.style.height = 'auto'; // Show status area
        statusText.innerHTML = '<span class="text-indigo-600"><i class="fas fa-circle-notch fa-spin"></i> กำลังบันทึก...</span>';
        statusText.classList.remove('opacity-0');

        try {
            const response = await fetch(`/transactions/${transactionId}/rate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ rating: score, rating_comment: comment })
            });

            const data = await response.json();

            if (data.success) {
                window.hasRated = true;
                statusText.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle"></i> บันทึกสำเร็จ</span>';
                
                // Animation & Collapse
                setTimeout(() => {
                    card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.transform = 'translateX(100%)';
                    card.style.opacity = '0';
                    card.style.maxHeight = '0';
                    card.style.padding = '0';
                    card.style.margin = '0';
                    card.style.border = 'none';
                    
                    setTimeout(() => {
                        card.remove();
                        const container = document.getElementById('rating-list-container');
                        
                        // 3. เอา Alert (Swal.fire) ออก -> ปิด Modal ทันทีถ้าหมด
                        if (container.children.length === 0) {
                            closeRatingModal();
                        }
                    }, 400);
                }, 700); // ลดเวลา delay ลงเพื่อให้รู้สึกเร็วขึ้น
            } else {
                throw new Error(data.message || 'บันทึกไม่สำเร็จ');
            }
        } catch (error) {
            console.error('Rating Error:', error);
            statusText.innerHTML = `<span class="text-red-600"><i class="fas fa-exclamation-circle"></i> ${error.message}</span>`;
            allBtns.forEach(b => {
                b.disabled = false;
                b.classList.remove('opacity-40', 'cursor-not-allowed', 'grayscale');
            });
        }
    }
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f9fafb; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    @keyframes fade-in-up { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fade-in-up 0.4s ease-out forwards; }
</style>