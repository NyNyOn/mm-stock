/**
 * transactions.js
 * Handles the equipment withdrawal and borrowing system.
 */

let searchDebounceTimer;
let withdrawalItems = [];

function withdrawThisItem(item) {
    closeModal('equipment-details-modal');
    showWithdrawalModal('borrow');
    addItemToWithdrawalList(item);
}

function showWithdrawalModal(type = 'withdraw') {
    withdrawalItems = [];
    const modal = document.getElementById('withdrawal-modal');
    if (!modal) return;

    modal.dataset.type = type;

    const title = modal.querySelector('#withdrawal-modal-title span');
    const icon = modal.querySelector('#withdrawal-modal-icon');
    const requestorLabel = modal.querySelector('#requestor-label');
    const purposeLabel = modal.querySelector('#purpose-label');
    const submitButton = modal.querySelector('#submit-withdrawal-btn');

    if (type === 'borrow') {
        title.textContent = 'สร้างรายการยืม';
        icon.className = 'text-purple-500 fas fa-hand-holding-heart';
        requestorLabel.textContent = 'ชื่อผู้ยืม *';
        purposeLabel.textContent = 'วัตถุประสงค์การยืม';
        submitButton.innerHTML = '<i class="fas fa-check-circle mr-2"></i>ยืนยันการยืม';
    } else {
        title.textContent = 'สร้างรายการเบิกจ่าย';
        icon.className = 'text-orange-500 fas fa-box-open';
        requestorLabel.textContent = 'ชื่อผู้ขอเบิก *';
        purposeLabel.textContent = 'วัตถุประสงค์การเบิก';
        submitButton.innerHTML = '<i class="fas fa-check-circle mr-2"></i>ยืนยันการเบิก';
    }

    const form = document.getElementById('withdrawal-form');
    if (form) form.reset();
    updateWithdrawalUI();
    showModal('withdrawal-modal');
}

function openSelectItemModal() {
    const searchInput = document.getElementById('select-item-search');
    if (searchInput) searchInput.value = '';

    searchEquipmentForSelection(1);
    showModal('select-item-modal');

    if (searchInput) {
        searchInput.removeEventListener('keyup', handleSearchInput);
        searchInput.addEventListener('keyup', handleSearchInput);
    }
}

function handleSearchInput() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        searchEquipmentForSelection(1);
    }, 300);
}

async function searchEquipmentForSelection(page = 1) {
    const listContainer = document.getElementById('select-item-list');
    const term = document.getElementById('select-item-search')?.value || '';
    if (listContainer) listContainer.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin text-2xl"></i></div>';

    try {
        const response = await fetch(`/ajax/items?q=${encodeURIComponent(term)}&page=${page}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const result = await response.json();

        if (listContainer) listContainer.innerHTML = '';
        if (result.data && result.data.length > 0) {
            result.data.forEach(item => {
                const isAdded = withdrawalItems.some(reqItem => reqItem.id === item.id);

                // 🌟 ADD: Star Rating Display 🌟
                let starHtml = '';
                if (item.avg_rating) {
                    starHtml = `<div class="flex items-center text-xs text-yellow-500 font-medium mt-1 bg-yellow-50 px-2 py-0.5 rounded-md inline-block w-auto self-start">
                                    <span class="mr-1">${item.avg_rating}</span> <i class="fas fa-star"></i>
                                </div>`;
                } else {
                    starHtml = `<div class="text-xs text-gray-400 mt-1 px-2 py-0.5">ยังไม่มีคะแนน</div>`;
                }

                const card = document.createElement('div');
                card.className = `soft-card p-3 rounded-lg flex items-center space-x-4 transition-all ${isAdded ? 'opacity-50 bg-gray-100' : 'cursor-pointer hover:shadow-md hover:scale-[1.02]'}`;
                if (!isAdded) {
                    card.onclick = () => handleSelectItem(item);
                }
                card.innerHTML = `
                    <img src="${item.image_url || '/images/placeholder.webp'}" class="w-16 h-16 object-cover rounded-lg gentle-shadow flex-shrink-0" alt="${item.name}">
                    <div class="flex-grow min-w-0 flex flex-col justify-center">
                        <p class="font-bold text-gray-800 truncate text-sm">${item.name}</p>
                        <p class="text-xs text-gray-500 font-mono">${item.serial_number || 'N/A'}</p>
                        ${starHtml}
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-lg font-bold text-blue-600">${item.quantity}</p>
                        <p class="text-xs text-gray-500">คงเหลือ</p>
                    </div>
                    <div class="w-12 text-center flex-shrink-0">
                        ${isAdded ? '<i class="fas fa-check-circle text-green-500 text-2xl"></i>' : '<button class="p-3 bg-gray-100 rounded-full hover:bg-blue-100"><i class="fas fa-plus text-blue-500"></i></button>'}
                    </div>
                `;
                listContainer.appendChild(card);
            });
        } else {
            if (listContainer) listContainer.innerHTML = '<p class="text-center text-gray-500 p-8">ไม่พบอุปกรณ์ที่ตรงตามเงื่อนไข</p>';
        }
    } catch (error) {
        console.error('Search error:', error);
        if (listContainer) listContainer.innerHTML = '<p class="text-center text-red-500 p-8">เกิดข้อผิดพลาดในการค้นหา</p>';
    }
}

function handleSelectItem(item) {
    addItemToWithdrawalList(item);
    closeModal('select-item-modal');
}

function addItemToWithdrawalList(item) {
    const existingItem = withdrawalItems.find(i => i.id === item.id);
    if (existingItem) {
        if (existingItem.withdraw_quantity < existingItem.quantity) {
            existingItem.withdraw_quantity++;
        } else {
            showToast(`สต็อกของ ${item.name} ไม่เพียงพอ`, 'warning');
        }
    } else {
        withdrawalItems.push({ ...item, withdraw_quantity: 1 });
    }
    updateWithdrawalUI();
}

function updateWithdrawalUI() {
    const container = document.getElementById('withdrawal-items-container');
    const placeholder = document.getElementById('no-withdrawal-items-placeholder');
    if (!container) return;
    container.innerHTML = '';
    if (withdrawalItems.length > 0) {
        if (placeholder) placeholder.style.display = 'none';
        withdrawalItems.forEach((item, index) => {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'flex items-center p-2 bg-white border rounded-lg';
            itemDiv.innerHTML = `
                <img src="${item.image_url || '/images/placeholder.webp'}" alt="${item.name}" class="w-10 h-10 object-cover rounded-md mr-3">
                <div class="flex-grow">
                    <p class="font-medium text-sm text-gray-800">${item.name}</p>
                    <p class="text-xs text-gray-500">คงเหลือ: ${item.quantity}</p>
                </div>
                <div class="flex items-center space-x-2">
                    <label class="text-sm">จำนวน:</label>
                    <input type="number" value="${item.withdraw_quantity}" min="1" max="${item.quantity}" onchange="updateWithdrawalItemQuantity(${index}, this.value)" class="w-16 px-2 py-1 border border-gray-300 rounded-md text-center">
                    <button type="button" onclick="removeWithdrawalItem(${index})" class="p-2 text-red-500 hover:bg-red-100 rounded-full">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            container.appendChild(itemDiv);
        });
    } else {
        if (placeholder) placeholder.style.display = 'block';
    }
}

function updateWithdrawalItemQuantity(index, quantity) {
    const item = withdrawalItems[index];
    let newQuantity = parseInt(quantity, 10);
    if (isNaN(newQuantity) || newQuantity < 1) {
        newQuantity = 1;
    } else if (newQuantity > item.quantity) {
        newQuantity = item.quantity;
        showToast(`จำนวนเบิก/ยืมของ ${item.name} มีได้ไม่เกิน ${item.quantity}`, 'warning');
    }
    withdrawalItems[index].withdraw_quantity = newQuantity;
    updateWithdrawalUI();
}

function removeWithdrawalItem(index) {
    withdrawalItems.splice(index, 1);
    updateWithdrawalUI();
}

async function submitWithdrawal() {
    const form = document.getElementById('withdrawal-form');
    const requestorName = form.querySelector('[name="requestor_name"]').value;
    const purpose = form.querySelector('[name="purpose"]').value;
    const notes = form.querySelector('[name="notes"]').value;
    const transactionType = document.getElementById('withdrawal-modal').dataset.type || 'withdraw';

    if (!requestorName.trim()) {
        return showToast('กรุณากรอกชื่อผู้ขอ', 'error');
    }
    if (withdrawalItems.length === 0) {
        return showToast('กรุณาเพิ่มรายการอุปกรณ์', 'error');
    }

    // เตรียมข้อมูล
    const itemsToSubmit = withdrawalItems.map(item => ({ id: item.id, quantity: item.withdraw_quantity }));

    // ✅ เปลี่ยน Route เป็น /ajax/user/transact เพื่อใช้ Rating Block Logic
    const apiEndpoint = '/ajax/user/transact';

    const payload = {
        // Field สำหรับ handleUserTransaction
        equipment_id: itemsToSubmit[0].id,
        quantity: itemsToSubmit[0].quantity,
        type: transactionType === 'borrow' ? 'returnable' : 'consumable',
        requestor_type: 'self', // สมมติเบิกให้ตัวเอง

        // Field เดิม
        requestor_name: requestorName,
        purpose: purpose,
        notes: notes,
        items: itemsToSubmit // เผื่อไว้
    };

    const submitButton = document.getElementById('submit-withdrawal-btn');
    const originalButtonText = submitButton.innerHTML;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
    submitButton.disabled = true;

    try {
        const response = await fetch(apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok) {
            showToast(data.message, 'success');
            closeModal('withdrawal-modal');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            // ⛔ ดักจับ Rating Block (Error 403)
            if (response.status === 403 && data.error_code === 'UNRATED_TRANSACTIONS') {
                closeModal('withdrawal-modal');

                if (typeof openRatingModal === 'function') {
                    openRatingModal(data.unrated_items);
                    Swal.fire({
                        icon: 'warning',
                        title: 'ติดเงื่อนไข',
                        text: data.message,
                        confirmButtonText: 'ตกลง'
                    });
                } else {
                    alert(data.message + "\n(Please check layout include)");
                }
                return;
            }

            // showToast(data.message || 'เกิดข้อผิดพลาด', 'error');
            Swal.fire({
                icon: 'error',
                title: null,
                html: data.message || 'เกิดข้อผิดพลาด',
                confirmButtonText: 'ปิด',
                customClass: { popup: 'w-full max-w-lg' }
            });
        }
    } catch (error) {
        console.error('Submission error:', error);
        showToast('การเชื่อมต่อล้มเหลว', 'error');
    } finally {
        submitButton.innerHTML = originalButtonText;
        submitButton.disabled = false;
    }
}