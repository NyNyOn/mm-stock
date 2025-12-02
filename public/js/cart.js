let cart = [];

// เริ่มต้น: โหลดข้อมูลตะกร้าและ Select2
document.addEventListener('DOMContentLoaded', () => { 
    loadCart(); 
    initGlobalSelect2(); 
});

// โหลดข้อมูลตะกร้าจาก LocalStorage
function loadCart() {
    const saved = localStorage.getItem('mm_stock_cart');
    try { cart = saved ? JSON.parse(saved) : []; } catch (e) { cart = []; localStorage.removeItem('mm_stock_cart'); }
    updateCartCount();
}

// เพิ่มสินค้าลงตะกร้า
function addToCart(id, name, thumbnail, maxStock) {
    const existing = cart.find(i => i.id === id);
    if (existing) {
        if (existing.quantity < maxStock) { existing.quantity++; showToast(`เพิ่ม ${name} เป็น ${existing.quantity} ชิ้น`, 'success'); } 
        else { showToast('จำนวนสินค้าถึงขีดจำกัดแล้ว', 'warning'); return; }
    } else {
        // เพิ่ม field receiver_id, receiver_name ไว้เก็บแยกรายชิ้น (ค่าเริ่มต้น null = ตัวเอง)
        cart.push({ id, name, thumbnail, quantity: 1, maxStock, purpose: '', receiver_id: null, receiver_name: '' });
        showToast(`เพิ่ม ${name} ลงตะกร้าแล้ว`, 'success');
    }
    saveCart();
    const modal = document.getElementById('cartModal');
    if (modal && !modal.classList.contains('hidden')) { renderCartItems(); }
}

function saveCart() { localStorage.setItem('mm_stock_cart', JSON.stringify(cart)); updateCartCount(); }

function updateCartCount() {
    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
    const badge = document.getElementById('cart-count');
    if(badge) { badge.innerText = count; count > 0 ? badge.classList.remove('hidden') : badge.classList.add('hidden'); }
}

// แสดงรายการใน Modal
function renderCartItems() {
    const container = document.getElementById('cart-items-container');
    const emptyMsg = document.getElementById('empty-cart-msg');
    const table = container.closest('table');
    container.innerHTML = '';

    if (cart.length === 0) { emptyMsg.classList.remove('hidden'); if(table) table.classList.add('hidden'); return; }
    emptyMsg.classList.add('hidden'); if(table) table.classList.remove('hidden');

    const purposeOptions = document.getElementById('purpose-options-template')?.innerHTML || '<option value="general_use">เบิกใช้งานทั่วไป</option>';
    const isOtherMode = document.querySelector('input[name="global_receiver_type"]:checked').value === 'other';

    // Show/Hide Receiver Column
    const recCols = document.querySelectorAll('.receiver-col');
    recCols.forEach(col => isOtherMode ? col.classList.remove('hidden') : col.classList.add('hidden'));

    cart.forEach((item, index) => {
        let receiverHtml = '';
        if (isOtherMode) {
            receiverHtml = `
            <td class="px-4 py-3 align-middle receiver-col">
                <select class="item-receiver-select w-full text-sm" data-index="${index}" style="width: 100%;">
                    ${item.receiver_id ? `<option value="${item.receiver_id}" selected>${item.receiver_name}</option>` : ''}
                </select>
            </td>`;
        }

        const row = `
            <tr class="hover:bg-gray-50 border-b last:border-0 transition-colors">
                <td class="px-4 py-3 align-middle">
                    <div class="flex items-center">
                        <img class="h-10 w-10 rounded border object-cover bg-gray-100" 
                             src="${item.thumbnail || '/images/placeholder.webp'}" 
                             onerror="this.src='/images/placeholder.webp'">
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900 max-w-[150px] truncate" title="${item.name}">${item.name}</div>
                            <div class="text-xs text-gray-500">คงเหลือ: ${item.maxStock}</div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-center align-middle">
                    <div class="flex items-center justify-center border border-gray-300 rounded-md w-24 mx-auto overflow-hidden bg-white shadow-sm">
                        <button onclick="updateItem(${index}, 'qty', ${item.quantity - 1})" class="px-2 py-1 bg-gray-50 hover:bg-gray-200 text-gray-600 transition border-r active:bg-gray-300">-</button>
                        <input type="number" min="1" max="${item.maxStock}" value="${item.quantity}" 
                               onchange="updateItem(${index}, 'qty', this.value)" 
                               class="w-8 text-center border-none focus:ring-0 text-sm p-0 text-gray-700 font-medium">
                        <button onclick="updateItem(${index}, 'qty', ${item.quantity + 1})" class="px-2 py-1 bg-gray-50 hover:bg-gray-200 text-gray-600 transition border-l active:bg-gray-300">+</button>
                    </div>
                </td>
                <td class="px-4 py-3 align-middle">
                    <select onchange="updateItem(${index}, 'purpose', this.value)" 
                            class="purpose-select w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm transition">
                        ${purposeOptions}
                    </select>
                </td>
                ${receiverHtml}
                <td class="px-4 py-3 text-center align-middle">
                    <button onclick="removeItem(${index})" class="text-red-400 hover:text-red-600 p-1.5 rounded-full hover:bg-red-50 transition" title="ลบ">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>`;
        container.insertAdjacentHTML('beforeend', row);
        
        // Restore values
        const purposeSelect = container.lastElementChild.querySelector('.purpose-select');
        if (purposeSelect && item.purpose) purposeSelect.value = item.purpose;
    });

    // Initialize Select2 สำหรับรายชิ้น (ถ้ามี)
    if (isOtherMode) {
        $('.item-receiver-select').each(function() {
            const $this = $(this);
            const index = $this.data('index');
            const item = cart[index];
            
            $this.select2({
                dropdownParent: $('#cartModal'),
                placeholder: 'ค้นหาชื่อ...',
                allowClear: true,
                ajax: {
                    url: '{{ route("ajax.handler") }}',
                    type: 'POST',
                    dataType: 'json',
                    delay: 250,
                    data: (params) => ({
                        _token: document.querySelector('meta[name="csrf-token"]').content,
                        action: 'get_ldap_users',
                        q: params.term
                    }),
                    processResults: (data) => ({ results: data.items }),
                    cache: true
                }
            }).on('select2:select', function (e) {
                const data = e.params.data;
                updateItem(index, 'receiver', { id: data.id, text: data.text });
            }).on('select2:clear', function (e) {
                updateItem(index, 'receiver', { id: null, text: '' });
            });
            
            // Set initial selected value for the item
            if (item.receiver_id && item.receiver_name) {
                // ต้องสร้าง option ก่อนแล้วค่อย set val
                const newOption = new Option(item.receiver_name, item.receiver_id, true, true);
                $this.append(newOption).trigger('change');
            }
        });
    }
}

// เริ่มต้น Select2 ตัวหลัก (Global)
function initGlobalSelect2() {
    $('#global_receiver_id').select2({
        dropdownParent: $('#cartModal'), 
        placeholder: 'พิมพ์ชื่อ หรือรหัสพนักงาน...',
        allowClear: true,
        ajax: {
            url: '{{ route("ajax.handler") }}',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: (params) => ({
                _token: document.querySelector('meta[name="csrf-token"]').content,
                action: 'get_ldap_users',
                q: params.term
            }),
            processResults: (data) => ({ results: data.items }),
            cache: true
        }
    });
}

function updateItem(index, type, val) {
    if (type === 'qty') {
        let q = parseInt(val); if (isNaN(q) || q < 1) q = 1;
        if (q > cart[index].maxStock) { q = cart[index].maxStock; showToast('จำนวนเกินสต็อก', 'warning'); }
        cart[index].quantity = q;
    } else if (type === 'purpose') {
        cart[index].purpose = val;
    } else if (type === 'receiver') {
        cart[index].receiver_id = val.id;
        cart[index].receiver_name = val.text;
    }
    saveCart(); 
    if(type === 'qty') renderCartItems(); 
}

function removeItem(index) {
    Swal.fire({ title: 'ยืนยันการลบ?', icon: 'warning', showCancelButton: true, confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก' }).then((result) => {
        if (result.isConfirmed) { cart.splice(index, 1); saveCart(); renderCartItems(); showToast('ลบรายการเรียบร้อย', 'info'); }
    });
}

function clearCart() {
    if(cart.length === 0) return;
    Swal.fire({ title: 'ล้างตะกร้าทั้งหมด?', text: "รายการทั้งหมดในตะกร้าจะหายไป", icon: 'warning', showCancelButton: true, confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก' }).then((result) => {
        if (result.isConfirmed) { cart = []; saveCart(); renderCartItems(); showToast('ล้างตะกร้าเรียบร้อย', 'info'); }
    });
}

function applyPurposeToAll() {
    const selects = document.querySelectorAll('.purpose-select');
    if (selects.length > 0 && selects[0].value) {
        cart.forEach(i => i.purpose = selects[0].value);
        saveCart(); renderCartItems(); showToast('คัดลอกวัตถุประสงค์เรียบร้อย', 'success');
    } else { Swal.fire('กรุณาเลือกก่อน', 'กรุณาเลือกวัตถุประสงค์ที่ช่องบรรทัดแรกก่อน แล้วค่อยกดปุ่มนี้', 'info'); }
}

// ใช้ผู้รับหลักกับทุกรายการ
function applyReceiverToAll() {
    const globalId = $('#global_receiver_id').val();
    const globalName = $('#global_receiver_id option:selected').text();

    if (!globalId) { Swal.fire('แจ้งเตือน', 'กรุณาเลือกผู้รับหลักด้านบนก่อน', 'info'); return; }

    cart.forEach(i => {
        i.receiver_id = globalId;
        i.receiver_name = globalName;
    });
    saveCart();
    renderCartItems(); // รีเฟรชตารางให้ Select2 รายคนอัปเดตค่าตาม
    showToast('นำไปใช้กับทุกรายการแล้ว', 'success');
}

function openCartModal() { 
    renderCartItems(); 
    document.getElementById('cartModal').classList.remove('hidden'); 
    document.body.style.overflow = 'hidden'; 
}
function closeCartModal() { 
    document.getElementById('cartModal').classList.add('hidden'); 
    document.body.style.overflow = 'auto'; 
}

function toggleGlobalReceiverInput() {
    const isOther = document.querySelector('input[name="global_receiver_type"]:checked').value === 'other';
    const div = document.getElementById('global_receiver_input_container');
    if (isOther) { 
        div.classList.remove('hidden'); 
    } else { 
        div.classList.add('hidden'); 
        cart.forEach(i => { i.receiver_id = null; i.receiver_name = ''; });
        saveCart();
    }
    renderCartItems();
}

async function submitCart() {
    if (cart.length === 0) return Swal.fire('ตะกร้าว่างเปล่า', 'กรุณาเลือกสินค้าก่อน', 'warning');
    
    const isOtherMode = document.querySelector('input[name="global_receiver_type"]:checked').value === 'other';
    const missingReceiver = isOtherMode && cart.some(i => !i.receiver_id);

    if (missingReceiver) {
        return Swal.fire('ข้อมูลไม่ครบ', 'กรุณาระบุผู้รับให้ครบทุกรายการ (หรือใช้ปุ่ม "ใช้ผู้รับคนนี้กับทุกรายการ")', 'warning');
    }

    const missingPurpose = cart.some(i => !i.purpose);
    if (missingPurpose) {
        const confirm = await Swal.fire({ title: 'วัตถุประสงค์ไม่ครบ', text: 'บางรายการยังไม่ระบุ ยืนยันทำต่อหรือไม่? (ระบบจะใช้วัตถุประสงค์ทั่วไป)', icon: 'question', showCancelButton: true, confirmButtonText: 'ยืนยันการเบิก', cancelButtonText: 'กลับไปแก้ไข' });
        if(!confirm.isConfirmed) return;
    }

    // ✅ ดึง dept_key จาก URL หรือ element (สมมติว่าเก็บไว้ใน meta tag หรือ hidden input)
    const currentDeptKey = new URLSearchParams(window.location.search).get('dept') || 'mm'; 

    const payload = {
        items: cart.map(i => ({
            equipment_id: i.id,
            quantity: i.quantity,
            notes: i.purpose, 
            // ส่ง receiver_id เป็นตัวเลข/null เพื่อให้ Controller บันทึก
            receiver_id: i.receiver_id 
        })),
        dept_key: currentDeptKey // ✅ ส่ง dept_key ไปเช็คข้ามแผนก
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    Swal.fire({ title: 'กำลังบันทึกข้อมูล...', text: 'กรุณารอสักครู่', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });

    try {
        const response = await fetch('/transactions/bulk-withdraw', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, 
            body: JSON.stringify(payload) 
        });
        
        const data = await response.json();
        
        if (response.ok) { 
            await Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: 'บันทึกรายการเบิกเรียบร้อยแล้ว', timer: 2000, showConfirmButton: false });
            cart = []; saveCart(); closeCartModal(); window.location.reload(); 
        } else { 
            // จัดการ Error ที่มาจาก Controller (เช่น สต็อกไม่พอ หรือ เบิกข้ามแผนก)
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message || 'Unknown Error', confirmButtonText: 'ปิด' });
        }
    } catch (e) { 
        console.error(e); 
        Swal.fire({ icon: 'error', title: 'การเชื่อมต่อล้มเหลว', text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่', confirmButtonText: 'ปิด' });
    }
}

function showToast(msg, type = 'success') {
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
    Toast.fire({ icon: type, title: msg });
}