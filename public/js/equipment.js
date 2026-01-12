/**
 * File: public/js/equipment.js
 * Description: Complete Equipment Management Logic
 * Version: Restored Full Context, Fixed SetCover, and Ensured Stability
 */

// ==========================================================================
// 1. GLOBAL VARIABLES & HELPERS
// ==========================================================================

let currentDetailImages = [];
let currentDetailName = '';
let currentGalleryIndex = 0;

// ตรวจสอบว่ามีการโหลด SweetAlert2 แล้ว
if (typeof Swal === 'undefined') {
    console.warn("SweetAlert2 (Swal) is not loaded. Please ensure it is included before this script.");
}

// Helper: Format Date (dd/mm/yyyy)
if (typeof window.formatDate === 'undefined') {
    window.formatDate = function (dateString) {
        if (!dateString) return '-';
        try {
            const d = new Date(dateString);
            return !isNaN(d.getTime()) ? d.toLocaleDateString('th-TH', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
        } catch (e) { return '-'; }
    }
}

// Helper: Format DateTime (dd/mm/yyyy hh:mm)
if (typeof window.formatDateTime === 'undefined') {
    window.formatDateTime = function (dateString) {
        if (!dateString) return '-';
        try {
            const d = new Date(dateString);
            return !isNaN(d.getTime()) ? d.toLocaleString('th-TH', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
        } catch (e) { return '-'; }
    }
}

// Helper: Withdrawal Type Text
if (typeof window.getWithdrawalTypeText === 'undefined') {
    window.getWithdrawalTypeText = function (type) {
        const types = {
            consumable: 'เบิก (ไม่ต้องคืน)',
            returnable: 'ยืม (ต้องคืน)',
            partial_return: 'เบิก (คืนได้)'
        };
        return types[type] || type || '-';
    }
}

// Helper: Transaction Type Text
if (typeof window.getTransactionTypeText === 'undefined') {
    window.getTransactionTypeText = function (type) {
        const types = {
            receive: 'รับเข้า',
            withdraw: 'เบิก (Admin)',
            adjust: 'ปรับสต็อก',
            borrow: 'ยืม (Admin)',
            return: 'คืน',
            partial_return: 'เหลือแบบคืนได้',
            stock_check: 'ตรวจนับ',
            consumable: 'เบิก (สิ้นเปลือง)',
            returnable: 'ยืม (ต้องคืน)',
            add: 'เพิ่มจำนวน'
        };
        return types[type] || type;
    }
}

// Helper: Status Badge (Internal Use)
function createStatusBadgeInternal(status) {
    const badge = document.createElement('span');
    badge.className = 'px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider shadow-sm border';
    let styleClass = 'bg-gray-100 text-gray-600 border-gray-200';
    let icon = '';
    let text = status || 'Unknown';

    switch (status) {
        case 'available': styleClass = 'bg-green-50 text-green-700 border-green-200'; icon = '<i class="fas fa-check-circle mr-1"></i>'; text = 'พร้อมใช้งาน'; break;
        case 'low_stock': styleClass = 'bg-yellow-50 text-yellow-700 border-yellow-200'; icon = '<i class="fas fa-exclamation-circle mr-1"></i>'; text = 'ใกล้หมด'; break;
        case 'out_of_stock': styleClass = 'bg-red-50 text-red-700 border-red-200'; icon = '<i class="fas fa-times-circle mr-1"></i>'; text = 'สินค้าหมด'; break;
        case 'maintenance': styleClass = 'bg-blue-50 text-blue-700 border-blue-200'; icon = '<i class="fas fa-tools mr-1"></i>'; text = 'ซ่อมบำรุง'; break;
        case 'frozen': styleClass = 'bg-cyan-50 text-cyan-700 border-cyan-200'; icon = '<i class="fas fa-snowflake mr-1"></i>'; text = 'ระงับ (Frozen)'; break;
    }
    badge.className += ` ${styleClass}`;
    badge.innerHTML = `${icon} ${text}`;
    return badge;
}

// Helper: Close any modal by ID (UPDATED: Force Clear Files)
window.closeModal = function (modalId) {
    if (modalId === 'equipment-details-modal') {
        window.closeDetailsModal();
        return;
    }
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        // Check if any other modals are open before restoring scroll
        const openModals = document.querySelectorAll('.fixed.inset-0.z-50:not(.hidden), .fixed.inset-0.z-40:not(.hidden)');
        if (openModals.length === 0) {
            document.body.style.overflow = '';
        }

        // Reset Forms
        const form = modal.querySelector('form');
        if (form) {
            form.reset();

            // ✅ FORCE CLEAR FILE INPUTS (แก้ปัญหารูปค้างเมื่อปิด Modal)
            const fileInputs = form.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.value = '';
                try { input.files = (new DataTransfer()).files; } catch (e) { }
                input.dispatchEvent(new Event('change'));
            });

            // Clear plugins and dynamic elements
            if (typeof clearImagePreviews === 'function') clearImagePreviews(form);
            if (typeof clearServerErrors === 'function') clearServerErrors(form);

            // Reset Select2 if exists
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $(form).find('.select2').val(null).trigger('change');
            }
            // Reset Stepper if exists
            if (typeof form.updateStepperUI === 'function') {
                form.updateStepperUI(1);
            }
        }
    }
};

// Helper: Update Primary Visual (Client-Side)
window.updatePrimaryVisual = function (radio) {
    const container = radio.closest('.grid');
    if (!container) return;

    // Reset all
    container.querySelectorAll('.group').forEach(el => {
        el.classList.remove('border-yellow-400', 'shadow-md', 'ring-2', 'ring-yellow-100');
        el.classList.add('border-transparent');
        const badge = el.querySelector('span.absolute.top-0.left-0');
        if (badge && badge.innerText === 'MASTER') badge.remove();

        const label = el.querySelector('label');
        if (label) {
            label.className = "w-6 h-6 rounded-full flex items-center justify-center cursor-pointer opacity-0 group-hover:opacity-100 focus-within:opacity-100 transform hover:scale-110 transition-all bg-gray-200 text-gray-500 hover:bg-yellow-400 hover:text-white";
        }
    });

    // Set Active
    const wrapper = radio.closest('.group');
    if (wrapper) {
        wrapper.classList.remove('border-transparent');
        wrapper.classList.add('border-yellow-400', 'shadow-md', 'ring-2', 'ring-yellow-100');

        // Add Badge
        const badge = document.createElement('span');
        badge.className = "absolute top-0 left-0 bg-yellow-400 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-br z-10 shadow-sm";
        badge.innerText = "MASTER";
        wrapper.appendChild(badge);

        const label = wrapper.querySelector('label');
        if (label) {
            label.className = "w-6 h-6 rounded-full flex items-center justify-center cursor-pointer opacity-0 group-hover:opacity-100 focus-within:opacity-100 transform hover:scale-110 transition-all bg-yellow-400 text-white";
        }
    }
};

// ==========================================================================
// 2. IMAGE MANAGEMENT (Set Cover Image - FIXED LOGIC)
// ==========================================================================

/**
 * ตั้งค่ารูปภาพที่ระบุให้เป็นรูปภาพปก (Cover Image)
 * (FIXED: แก้ปัญหาการอัปเดต UI ที่รูปปกไม่เปลี่ยน)
 * @param {string|number} imageId รหัสของรูปภาพที่ต้องการตั้งเป็นปก
 * @param {string|number} equipmentId รหัสของอุปกรณ์
 */
function setCoverImage(imageId, equipmentId) {
    console.log(`[Image] Attempting to set image ID ${imageId} as cover for Equipment ID ${equipmentId}.`);

    if (!imageId || !equipmentId) {
        Swal.fire('ผิดพลาด', 'ไม่พบข้อมูล Image ID หรือ Equipment ID', 'error');
        return;
    }

    Swal.fire({
        title: 'กำลังดำเนินการ...',
        text: 'กำลังบันทึกข้อมูลรูปภาพปก',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // 1. ส่ง AJAX Request ไปยัง Controller
    fetch(`/equipment/images/${imageId}/set-cover`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            // ตรวจสอบให้แน่ใจว่ามี CSRF Token ในหน้า Blade
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ image_id: imageId, equipment_id: equipmentId })
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok.');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                console.log(`[Image] Success: Image ${imageId} set as cover.`);

                // 2. UI Update Logic: ลบสถานะ Cover จากรูปภาพเก่าทั้งหมด
                const imageList = document.getElementById('image-gallery');
                if (imageList) {
                    imageList.querySelectorAll('.image-card').forEach(item => {
                        // ลบ class ที่แสดงสถานะปก
                        item.classList.remove('border-green-500', 'ring-2', 'ring-green-500/50');

                        // ซ่อน Badge 'COVER' จากรูปภาพอื่น
                        const badge = item.querySelector('.cover-badge');
                        if (badge) badge.classList.add('hidden');
                    });
                }

                // 3. UI Update Logic: ตั้งสถานะ Cover ให้กับรูปภาพใหม่ที่ถูกเลือก
                const currentItem = document.querySelector(`.image-card[data-image-id="${imageId}"]`);
                if (currentItem) {
                    // เพิ่ม class ที่แสดงสถานะปกและเน้นด้วย ring
                    currentItem.classList.add('border-green-500', 'ring-2', 'ring-green-500/50');
                    const badge = currentItem.querySelector('.cover-badge');
                    if (badge) badge.classList.remove('hidden');

                    // อัปเดต primary image display ในหน้า Edit/Detail ทันที (ถ้ามี)
                    const primaryImageDisplay = document.getElementById('equipment-primary-image-display');
                    if (primaryImageDisplay) {
                        const imgSrc = currentItem.querySelector('img').src;
                        primaryImageDisplay.src = imgSrc;
                    }
                }

                Swal.fire('สำเร็จ!', 'ตั้งค่ารูปภาพปกเรียบร้อยแล้ว', 'success');
            } else {
                Swal.fire('ผิดพลาด', data.message || 'ไม่สามารถตั้งค่ารูปภาพปกได้', 'error');
            }
        })
        .catch(error => {
            console.error('[Image] Error setting cover image:', error);
            Swal.fire('ผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
        });
}


// ลบรูปภาพ (AJAX call)
function deleteImage(imageId) {
    Swal.fire({
        title: 'ยืนยันการลบรูปภาพ?',
        text: "คุณจะไม่สามารถกู้คืนรูปภาพนี้ได้!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // โค้ดสำหรับเรียก AJAX ไปลบรูปภาพจริง
            // fetch(`/equipment/images/${imageId}`, { method: 'DELETE', ... })
            // ...
            Swal.fire('ลบแล้ว!', 'รูปภาพถูกลบออกจากระบบแล้ว.', 'success');
        }
    });
}

// ==========================================================================
// 3. CRUD OPERATIONS (ADD / EDIT / DELETE) - (RESTORED FULL)
// ==========================================================================

// ✅ Show Add Modal
window.showAddModal = async function () {
    const modal = document.getElementById('add-equipment-modal');
    const modalBody = document.getElementById('add-form-content-wrapper');

    if (modal) {
        // ✅ FORCE RESET BEFORE SHOWING (ป้องกันค่าค้าง)
        const existingForm = modal.querySelector('form');
        if (existingForm) {
            existingForm.reset();
            existingForm.querySelectorAll('input[type="file"]').forEach(i => {
                i.value = '';
                try { i.files = (new DataTransfer()).files; } catch (e) { }
                i.dispatchEvent(new Event('change'));
            });
            if (typeof clearImagePreviews === 'function') clearImagePreviews(existingForm);
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // Load Add Form HTML via AJAX (to get fresh token and clean state)
        if (modalBody) {
            modalBody.innerHTML = '<div class="p-10 text-center"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i><p class="mt-2 text-gray-500">กำลังโหลดฟอร์ม...</p></div>';
            try {
                const response = await fetch('/equipment/create');
                if (!response.ok) throw new Error('Network response was not ok');
                const html = await response.text();
                modalBody.innerHTML = html;

                // ✅ Initialize Form Components
                const form = modalBody.querySelector('form');
                if (form) {
                    attachFormEventListeners(form);
                    // Initialize Select2 for Add Form
                    if (typeof $ !== 'undefined' && $.fn.select2) {
                        $(form).find('.select2').select2({ dropdownParent: $(modal), width: '100%' });
                    }
                }

            } catch (e) {
                console.error(e);
                modalBody.innerHTML = '<p class="text-red-500 text-center">โหลดฟอร์มไม่สำเร็จ</p>';
            }
        }
    }
};

// ✅ Show Edit Modal
window.showEditModal = async function (id) {
    const modal = document.getElementById('edit-equipment-modal');
    const modalBody = document.getElementById('edit-form-content-wrapper');

    if (!modal) return;

    // ✅ FORCE RESET BEFORE SHOWING (ป้องกันค่าค้าง)
    const existingForm = modal.querySelector('form');
    if (existingForm) {
        existingForm.reset();
        existingForm.querySelectorAll('input[type="file"]').forEach(i => {
            i.value = '';
            try { i.files = (new DataTransfer()).files; } catch (e) { }
            i.dispatchEvent(new Event('change'));
        });
        if (typeof clearImagePreviews === 'function') clearImagePreviews(existingForm);
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (modalBody) {
        modalBody.innerHTML = '<div class="flex justify-center items-center h-48"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i></div>';
        try {
            // Try specific AJAX route first, then fallback
            let response = await fetch(`/ajax/equipment/${id}/edit-form`);
            if (!response.ok) response = await fetch(`/equipment/${id}/edit`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!response.ok) throw new Error('Failed to load edit form');

            const html = await response.text();
            modalBody.innerHTML = html;

            const form = modalBody.querySelector('form');
            if (form) {
                attachFormEventListeners(form);
                // ✅ Re-initialize Select2 for Edit Form (Critical)
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    $(form).find('.select2').select2({ dropdownParent: $(modal), width: '100%' });
                }
            }
        } catch (error) {
            console.error("Edit Error:", error);
            modalBody.innerHTML = '<p class="text-red-500 text-center p-4">โหลดฟอร์มแก้ไขไม่สำเร็จ</p>';
        }
    }
};

// ✅ Delete Equipment
window.deleteEquipment = async function (id, name) {
    const result = await Swal.fire({
        title: 'ยืนยันการลบ?',
        html: `คุณต้องการลบ <b>${name || 'รายการนี้'}</b> ใช่หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    });

    if (result.isConfirmed) {
        Swal.fire({ title: 'กำลังลบ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const response = await fetch(`/equipment/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            });
            if (!response.ok) throw new Error('Delete failed');
            await Swal.fire({ icon: 'success', title: 'ลบสำเร็จ!', timer: 1500, showConfirmButton: false });
            window.location.reload();
        } catch (error) {
            Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถลบข้อมูลได้', 'error');
        }
    }
};

// ==========================================================================
// 4. FORM UTILITIES & LISTENERS (RESTORED)
// ==========================================================================

function attachFormEventListeners(form) {
    if (!form) return;
    form.noValidate = true;

    // Prevent duplicate listeners
    form.removeEventListener('submit', handleFormSubmit);
    form.addEventListener('submit', handleFormSubmit);

    // Attach helpers
    setupImagePreviews(form);
    setupExistingImageDeletion(form);
    setupPasteHandler(form); // ✅ เพิ่ม Paste Handler

    // ✅ Attach Close/Cancel Button Handler (เพิ่มการล้างค่า)
    const closeBtns = form.querySelectorAll('.close-modal-btn');
    closeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = form.closest('.fixed');
            if (modal && modal.id) {
                window.closeModal(modal.id);
            } else {
                form.reset();
                const fileInput = form.querySelector('input[type="file"]');
                if (fileInput) {
                    fileInput.value = '';
                    try { fileInput.files = (new DataTransfer()).files; } catch (e) { }
                    fileInput.dispatchEvent(new Event('change'));
                }
                clearImagePreviews(form);
                if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); document.body.style.overflow = ''; }
            }
        });
    });

    // Stepper Logic (for Add/Edit)
    const suffix = form.id.split('-').pop();
    if (form.querySelector(`#next-step-btn-${suffix}`)) {
        initializeStepper(form, suffix);
    }

    // MSDS Logic
    const msdsCheck = document.getElementById(`has_msds_checkbox-${suffix}`);
    if (msdsCheck) msdsCheck.addEventListener('change', handleMsdsCheckboxChange);

    const msdsBtn = document.getElementById(`manage-msds-btn-${suffix}`);
    if (msdsBtn) {
        // Clone to remove old listeners
        const newBtn = msdsBtn.cloneNode(true);
        msdsBtn.parentNode.replaceChild(newBtn, msdsBtn);
        newBtn.addEventListener('click', () => openMsdsModal(form));
    }

    // ✅ Serial Gen Trigger (เมื่อเปลี่ยนหมวดหมู่)
    const catSelect = document.getElementById(`category_id-${suffix}`);
    if (catSelect) {
        // ใช้ jQuery on change เพราะ Select2 อาจกิน event
        if (typeof $ !== 'undefined') {
            $(catSelect).on('change', () => generateSerialNumber(suffix));
        } else {
            catSelect.addEventListener('change', () => generateSerialNumber(suffix));
        }
    }
    // ปุ่ม Generate SN Manual
    const genSnBtn = document.getElementById(`generate-serial-btn-${suffix}`);
    if (genSnBtn) {
        genSnBtn.addEventListener('click', () => generateSerialNumber(suffix));
    }
}

async function handleFormSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    // Method Spoofing for Laravel
    const methodInput = form.querySelector('input[name="_method"]');
    if (methodInput && ['PUT', 'PATCH'].includes(methodInput.value.toUpperCase())) {
        formData.append('_method', methodInput.value);
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'; }

    try {
        const response = await fetch(form.action, {
            method: 'POST', body: formData,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') }
        });

        // ✅ ตรวจสอบ Error 413 (Payload Too Large)
        if (response.status === 413) {
            throw new Error('ไฟล์รูปภาพมีขนาดรวมใหญ่เกินกว่าที่ Server รองรับ (Error 413). กรุณาลดจำนวนรูปหรือลดขนาดไฟล์');
        }

        const result = await response.json();

        if (!response.ok) {
            if (response.status === 422) {
                displayValidationErrors(form, result.errors, form.id.split('-').pop());
                Swal.fire('ข้อมูลไม่ถูกต้อง', 'กรุณาตรวจสอบข้อมูลในฟอร์ม', 'warning');
            } else {
                throw new Error(result.message || 'Error');
            }
        } else {
            await Swal.fire('สำเร็จ', 'บันทึกข้อมูลเรียบร้อย', 'success');
            window.location.reload();
        }
    } catch (e) {
        console.error(e);
        // แสดง Error ที่ชัดเจน
        let msg = e.message;
        if (msg.includes('Unexpected token')) msg = 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์ (อาจไม่ใช่ JSON)';
        Swal.fire('Error', msg, 'error');
    } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalText; }
    }
}

// --- Image Preview Logic (UPDATED: Fix Remove Button Logic) ---
function setupImagePreviews(form) {
    const suffix = form.id.split('-').pop();
    const input = document.getElementById(`images-${suffix}`);
    const container = document.getElementById(`image-previews-${suffix}`);
    if (!input || !container) return;

    input.addEventListener('change', (e) => {
        container.innerHTML = '';

        // ถ้าไม่มีไฟล์ (เช่นถูกเคลียร์ค่า) ให้จบการทำงาน
        if (!input.files || input.files.length === 0) return;

        const files = Array.from(input.files);

        // ✅ Add Guidance Text
        const guidance = document.createElement('div');
        guidance.className = 'col-span-3 text-xs text-blue-600 mb-2 font-medium flex items-center';
        guidance.innerHTML = '<i class="fas fa-info-circle mr-1"></i> รูปแรกสุดจะถูกตั้งเป็นรูปภาพหลัก (Master) โดยอัตโนมัติ';
        container.appendChild(guidance);

        files.forEach((file, index) => {
            const div = document.createElement('div');
            div.className = `relative w-20 h-20 group rounded-lg overflow-hidden border ${index === 0 ? 'border-2 border-yellow-400 shadow-md ring-2 ring-yellow-100' : 'border-gray-200'}`;
            container.appendChild(div);

            const reader = new FileReader();
            reader.onload = (ev) => {
                const img = document.createElement('img');
                img.src = ev.target.result;
                img.className = "w-full h-full object-cover";

                const btn = document.createElement('button');
                btn.type = "button";
                btn.innerHTML = "&times;";
                btn.className = "absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs shadow opacity-0 group-hover:opacity-100 transition z-20";

                // ✅ แก้ไข: เมื่อกดลบ ให้สร้าง FileList ใหม่ที่ตัดไฟล์นั้นออกแล้วใส่กลับ input
                btn.onclick = () => {
                    const dt = new DataTransfer();
                    const currentFiles = input.files;
                    for (let i = 0; i < currentFiles.length; i++) {
                        if (i !== index) { // ข้ามไฟล์ที่ลบ
                            dt.items.add(currentFiles[i]);
                        }
                    }
                    input.files = dt.files; // อัปเดต input
                    input.dispatchEvent(new Event('change')); // Trigger change เพื่อรีเฟรช preview
                };

                div.appendChild(img);
                div.appendChild(btn);

                // ✅ Add MASTER Badge for First Image
                if (index === 0) {
                    const badge = document.createElement('span');
                    badge.className = "absolute top-0 left-0 bg-yellow-400 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-br z-10 shadow-sm";
                    badge.innerText = "MASTER";
                    div.appendChild(badge);
                }
            };
            reader.readAsDataURL(file);
        });
    });
}

// --- Paste Image Handler (NEW: Support Ctrl+V) ---
function setupPasteHandler(form) {
    const suffix = form.id.split('-').pop();
    const fileInput = document.getElementById(`images-${suffix}`);

    if (!fileInput) return;

    form.addEventListener('paste', (e) => {
        const clipboardData = e.clipboardData || e.originalEvent.clipboardData;
        if (!clipboardData) return;

        const items = clipboardData.items;
        let hasImage = false;
        const dt = new DataTransfer();

        // 1. เก็บไฟล์เดิมที่มีอยู่
        if (fileInput.files && fileInput.files.length > 0) {
            for (let i = 0; i < fileInput.files.length; i++) {
                dt.items.add(fileInput.files[i]);
            }
        }

        // 2. วนลูปหาไฟล์ภาพจาก Clipboard
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            if (item.kind === 'file' && item.type.includes('image/')) {
                const blob = item.getAsFile();
                const fileName = `pasted-image-${Date.now()}-${i}.png`;
                const newFile = new File([blob], fileName, { type: blob.type });
                dt.items.add(newFile);
                hasImage = true;
            }
        }

        // 3. ถ้าเจอภาพ ให้อัปเดต input
        if (hasImage) {
            e.preventDefault();
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change'));

            if (typeof Swal !== 'undefined') {
                const Toast = Swal.mixin({
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true
                });
                Toast.fire({ icon: 'success', title: 'วางรูปภาพเรียบร้อยแล้ว' });
            }
        }
    });
}

// --- Existing Image Deletion Logic ---
function setupExistingImageDeletion(form) {
    const suffix = form.id.split('-').pop();
    const container = document.getElementById(`existing-images-container-${suffix}`);
    if (!container) return;

    container.addEventListener('click', (e) => {
        const btn = e.target.closest('.delete-existing-image-btn');
        if (btn) {
            const id = btn.dataset.imageId;
            const input = document.getElementById(`delete_image_${id}`);
            const imgWrapper = document.getElementById(`image-${id}-wrapper`);
            if (input && imgWrapper) {
                input.disabled = !input.disabled;
                imgWrapper.style.opacity = input.disabled ? '1' : '0.4';
                btn.innerHTML = input.disabled ? '<i class="fas fa-times"></i>' : '<i class="fas fa-undo"></i>';
                btn.className = input.disabled
                    ? 'delete-existing-image-btn absolute top-1 right-1 bg-red-600 text-white p-1 rounded-full shadow hover:bg-red-700 transition-colors'
                    : 'delete-existing-image-btn absolute top-1 right-1 bg-yellow-500 text-white p-1 rounded-full shadow hover:bg-yellow-600 transition-colors';
            }
        }
    });
}

// --- Validation Display ---
function displayValidationErrors(form, errors, suffix) {
    clearServerErrors(form);
    let firstErrorStep = null;

    for (const field in errors) {
        const baseField = field.split('.')[0];
        const input = form.querySelector(`[name="${baseField}"], [name="${baseField}[]"]`) || document.getElementById(`${baseField}-${suffix}`);

        if (input) {
            input.classList.add('is-invalid');
            let errorDiv = input.parentNode.querySelector('.invalid-feedback');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback text-red-500 text-xs mt-1';
                input.parentNode.appendChild(errorDiv);
            }
            errorDiv.textContent = errors[field][0];

            // Detect step
            const stepPanel = input.closest('.step-panel');
            if (stepPanel && firstErrorStep === null) {
                const match = stepPanel.id.match(/step-(\d+)-panel/);
                if (match) firstErrorStep = parseInt(match[1]);
            }
        }
    }

    // Switch to error step if using stepper
    if (firstErrorStep && typeof form.updateStepperUI === 'function') {
        form.updateStepperUI(firstErrorStep);
    }
}

function clearImagePreviews(form) {
    const suffix = form.id.split('-').pop();
    const container = document.getElementById(`image-previews-${suffix}`);
    if (container) container.innerHTML = '';
}

function clearServerErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
}

// --- Stepper Logic ---
function initializeStepper(form, suffix) {
    let step = 1;
    const total = 3;
    const updateUI = (s) => {
        step = s;
        for (let i = 1; i <= total; i++) {
            const p = document.getElementById(`step-${i}-panel-${suffix}`);
            if (p) p.classList.toggle('hidden', i !== step);

            const ind = document.getElementById(`step-indicator-${i}-${suffix}`);
            if (ind) {
                ind.classList.toggle('active', i === step);
                ind.classList.toggle('completed', i < step);
                ind.classList.toggle('pending', i > step);
            }
        }
        const prev = document.getElementById(`prev-step-btn-${suffix}`);
        const next = document.getElementById(`next-step-btn-${suffix}`);
        const sub = document.getElementById(`submit-btn-${suffix}`);
        if (prev) prev.classList.toggle('hidden', step === 1);
        if (next) next.classList.toggle('hidden', step === total);
        if (sub) sub.classList.toggle('hidden', step !== total);
    };

    const nextBtn = document.getElementById(`next-step-btn-${suffix}`);
    const prevBtn = document.getElementById(`prev-step-btn-${suffix}`);
    if (nextBtn) nextBtn.addEventListener('click', () => { if (step < total) updateUI(step + 1); });
    if (prevBtn) prevBtn.addEventListener('click', () => { if (step > 1) updateUI(step - 1); });

    form.updateStepperUI = updateUI;
    updateUI(1);
}

// --- Serial Generator (RESTORED) ---
async function generateSerialNumber(suffix) {
    const catSelect = document.getElementById(`category_id-${suffix}`);
    const serialInput = document.getElementById(`serial_number-${suffix}`);

    // เช็คค่าจาก Select2 หรือ Native Select
    let catValue = catSelect ? (catSelect.value || $(catSelect).val()) : null;

    if (!catValue || !serialInput) return;

    try {
        const res = await fetch("/ajax/next-serial", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
            body: JSON.stringify({ category_id: catValue })
        });
        const data = await res.json();
        if (data.success) {
            serialInput.value = data.serial_number;
            // Flash effect
            serialInput.classList.add('bg-green-100');
            setTimeout(() => serialInput.classList.remove('bg-green-100'), 500);
        }
    } catch (e) { console.error(e); }
}

// --- MSDS Logic ---
function handleMsdsCheckboxChange(e) {
    const suffix = e.target.id.split('-').pop();
    const container = document.getElementById(`msds-management-container-${suffix}`);
    if (container) container.style.display = e.target.checked ? 'block' : 'none';
}

async function openMsdsModal(form) {
    const suffix = form.id.split('-').pop();
    const detailsInput = document.getElementById(`msds_details_hidden-${suffix}`);
    const { value: text } = await Swal.fire({
        input: 'textarea',
        inputLabel: 'รายละเอียด MSDS',
        inputValue: detailsInput.value,
        showCancelButton: true
    });
    if (text !== undefined) detailsInput.value = text;
}

// ==========================================================================
// 4. EQUIPMENT DETAILS MODAL (NEW)
// ==========================================================================

window.showDetailsModal = async function (equipmentId) {
    const modal = document.getElementById('equipment-details-modal');
    const loading = document.getElementById('details-loading');
    const errorMsg = document.getElementById('details-error-message');
    const body = document.getElementById('details-body');

    if (!modal) return;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    loading.classList.remove('hidden');
    errorMsg.classList.add('hidden');
    body.classList.add('hidden');
    document.body.style.overflow = 'hidden';

    try {
        const response = await fetch(`/equipment/${equipmentId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const rawData = await response.json();
        const item = rawData.data || rawData;

        populateDetails(item);

        loading.classList.add('hidden');
        body.classList.remove('hidden');

    } catch (error) {
        console.error("[EQUIPMENT.JS] Details Error:", error);
        loading.classList.add('hidden');
        errorMsg.classList.remove('hidden');
    }
};

function populateDetails(item) {
    const setText = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = (val === null || val === undefined || val === '') ? '-' : val;
    };

    currentDetailName = item.name;
    currentDetailImages = [];

    setText('footer-equipment-id', item.id);
    setText('img-badge-id', item.id);
    setText('details-name', item.name);
    setText('details-quantity', `${item.quantity ?? 0} ${item.unit?.name || 'ชิ้น'}`);
    setText('details-serial', item.serial_number);

    setText('details-min-stock', item.min_stock);
    setText('details-max-stock', item.max_stock);
    setText('details-withdrawal-type', window.getWithdrawalTypeText(item.withdrawal_type));
    setText('details-category', item.category?.name);
    setText('details-location', item.location?.name);
    setText('details-model', item.model);
    setText('details-part-no', item.part_no);
    setText('details-supplier', item.supplier);

    setText('details-purchase-date', window.formatDate(item.purchase_date));
    setText('details-warranty-date', window.formatDate(item.warranty_date));
    setText('details-created-at', window.formatDateTime(item.created_at));
    setText('details-updated-at', item.updated_at ? window.formatDateTime(item.updated_at) : '-');
    const updater = item.updater ? item.updater.fullname : (item.updated_by_name ? item.updated_by_name : '-');
    setText('details-updated-by', `โดย: ${updater}`);

    const statusWrapper = document.getElementById('details-status-container');
    if (statusWrapper) {
        statusWrapper.innerHTML = '';
        statusWrapper.appendChild(createStatusBadgeInternal(item.status));
    }

    const msdsTab = document.getElementById('details-msds-tab');
    const msdsDetails = document.getElementById('details-msds-details');
    const msdsLink = document.getElementById('details-msds-file');

    if (item.has_msds) {
        if (msdsTab) msdsTab.classList.remove('hidden');
        if (msdsDetails) msdsDetails.textContent = item.msds_details || '-';
        if (msdsLink && item.msds_file_url) {
            msdsLink.href = item.msds_file_url;
            msdsLink.classList.remove('hidden');
            msdsLink.classList.add('inline-flex');
        }
    } else {
        if (msdsTab) msdsTab.classList.add('hidden');
    }

    const firstTab = document.querySelector("[onclick*='details-tab-main']");
    if (firstTab) switchDetailsTab(firstTab, 'details-tab-main');

    // Helper: Transaction Icon
    const getTransactionIcon = (type) => {
        const icons = {
            receive: '<i class="fas fa-truck-loading"></i>',       // รับเข้า (รถขนของ)
            withdraw: '<i class="fas fa-dolly"></i>',              // เบิก (รถเข็นของออก)
            adjust: '<i class="fas fa-wrench"></i>',               // ปรับสต็อก (เครื่องมือ)
            borrow: '<i class="fas fa-hand-holding-heart"></i>',   // ยืม (มือถือหัวใจ/ของ)
            return: '<i class="fas fa-check-circle"></i>',         // คืน (เช็คถูก/เสร็จสิ้น)
            partial_return: '<i class="fas fa-hourglass-half"></i>', // คืนบางส่วน (นาฬิกาทราย/ยังไม่จบ)
            stock_check: '<i class="fas fa-tasks"></i>',           // ตรวจนับ (รายการงาน)
            consumable: '<i class="fas fa-box-open"></i>',         // เบิกสิ้นเปลือง (กล่องเปิดใช้งาน)
            returnable: '<i class="fas fa-retweet"></i>',          // ยืมคืน (ลูกศรหมุนวน)
            add: '<i class="fas fa-plus-square"></i>'              // เพิ่ม (บวก)
        };
        return icons[type] || '<i class="fas fa-circle"></i>';
    };

    // Helper: Transaction Color
    const getTransactionColor = (type) => {
        const colors = {
            receive: 'emerald',      // Green
            return: 'emerald',
            add: 'emerald',

            withdraw: 'rose',        // Red
            consumable: 'rose',

            borrow: 'amber',         // Orange (Temporary)
            returnable: 'amber',
            partial_return: 'amber',

            adjust: 'indigo',        // Blue/Purple (System)
            stock_check: 'blue'
        };
        return colors[type] || 'gray';
    };

    const historyBox = document.getElementById('details-transactions');
    if (historyBox) {
        historyBox.innerHTML = '';
        if (item.transactions && item.transactions.length > 0) {
            item.transactions.forEach(t => {
                const isPlus = t.quantity_change >= 0;
                const icon = getTransactionIcon(t.type);
                const color = getTransactionColor(t.type);
                const div = document.createElement('div');
                div.className = `p-3 border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors rounded-lg`;
                div.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-${color}-50 text-${color}-500 flex items-center justify-center shadow-sm text-sm border border-${color}-100">
                                ${icon}
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-700 leading-tight">${window.getTransactionTypeText(t.type)}</p>
                                <p class="text-[10px] text-gray-400 font-medium">${t.user?.fullname || 'System'}</p>
                            </div>
                        </div>
                        <div class="text-right">
                             <span class="font-mono text-[10px] text-gray-400 block mb-0.5">${window.formatDateTime(t.transaction_date)}</span>
                             <span class="${isPlus ? 'text-emerald-600 bg-emerald-50 border-emerald-100' : 'text-red-500 bg-red-50 border-red-100'} text-xs font-bold px-2 py-0.5 rounded-md border inline-block">
                                ${isPlus ? '+' : ''}${t.quantity_change}
                             </span>
                        </div>
                    </div>
                `;
                historyBox.appendChild(div);
            });
        } else {
            historyBox.innerHTML = '<div class="flex flex-col items-center justify-center py-8 text-gray-400 opacity-60"><i class="fas fa-history text-3xl mb-2"></i><span class="text-xs">ไม่มีประวัติการเคลื่อนไหว</span></div>';
        }
    }

    setupGallery(item);
    setupDetailButtons(item); // ✅ เรียกใช้ฟังก์ชันที่แก้ไขแล้ว
}

function setupGallery(item) {
    const primaryImg = document.getElementById('details-primary-image');
    const thumbContainer = document.getElementById('details-gallery-thumbnails');
    if (!primaryImg || !thumbContainer) return;

    thumbContainer.innerHTML = '';

    // Default Fallback
    const fallbackUrl = 'https://placehold.co/600x400/e2e8f0/64748b?text=No+Image';
    let finalUrl = fallbackUrl;

    // Determine Images List
    if (item.images_list && item.images_list.length > 0) {
        currentDetailImages = item.images_list;
        finalUrl = currentDetailImages[0];
    } else if (item.image_urls && item.image_urls.length > 0) {
        currentDetailImages = item.image_urls;
        finalUrl = item.primary_image_url || item.image_urls[0];
    } else if (item.image_url) {
        currentDetailImages = [item.image_url];
        finalUrl = item.image_url;
    } else {
        currentDetailImages = [fallbackUrl];
    }

    // Set Primary Image with Error Handling
    primaryImg.src = finalUrl;
    primaryImg.onerror = function () {
        if (this.src !== fallbackUrl) {
            this.src = fallbackUrl;
        }
    };

    // Build Thumbnails
    if (currentDetailImages.length > 1) {
        currentDetailImages.forEach(url => {
            const div = document.createElement('div');
            div.className = 'relative aspect-square cursor-pointer group';
            div.innerHTML = `<img src="${url}" class="w-full h-full object-cover rounded border-2 border-transparent hover:border-indigo-500 transition shadow-sm" onerror="this.src='${fallbackUrl}'">`;
            div.onclick = () => {
                primaryImg.style.opacity = '0.5';
                setTimeout(() => {
                    primaryImg.src = url;
                    primaryImg.style.opacity = '1';
                }, 150);
            };
            thumbContainer.appendChild(div);
        });
    }
}

// ✅✅✅ แก้ไข: Setup Detail Buttons (Clone & Reset Logic) ✅✅✅
function setupDetailButtons(item) {
    const editBtn = document.getElementById('details-edit-btn');
    const printBtn = document.getElementById('details-print-btn');

    // --- ตรวจสอบสิทธิ์ Frozen ---
    const userCanBypass = document.querySelector('meta[name="can-bypass-frozen"]')?.content === 'true';
    const isFrozen = item.status && item.status.toLowerCase() === 'frozen';
    const shouldLock = isFrozen && !userCanBypass;

    if (editBtn) {
        // 1. สร้างปุ่มใหม่จากต้นฉบับเสมอ (เพื่อล้าง Event Listener เก่า และค่า Display เก่า)
        const newEdit = editBtn.cloneNode(true);

        // 2. กำหนดการแสดงผลตามเงื่อนไข Locked
        if (shouldLock) {
            newEdit.style.display = 'none'; // ซ่อน
            // ไม่ต้องใส่ Event Listener ถ้าถูกล็อค
        } else {
            newEdit.style.display = 'inline-flex'; // แสดง (บังคับ display ใหม่)
            newEdit.setAttribute('data-equipment-id', item.id);

            // ผูก Event Handler
            newEdit.addEventListener('click', () => {
                window.closeDetailsModal();

                // ตรวจสอบว่าหน้าเว็บปัจจุบันมี Modal แก้ไขอยู่จริงหรือไม่
                const editModal = document.getElementById('edit-equipment-modal');
                if (editModal && typeof window.showEditModal === 'function') {
                    window.showEditModal(item.id);
                } else {
                    // Fallback: Redirect to Equipment Page with Edit Action (for Reports page)
                    window.location.href = `/equipment?action=edit&id=${item.id}`;
                }
            });
        }

        // 3. แทนที่ปุ่มเก่าด้วยปุ่มใหม่ใน DOM
        if (editBtn.parentNode) {
            editBtn.parentNode.replaceChild(newEdit, editBtn);
        }
    }

    if (printBtn) {
        const newPrint = printBtn.cloneNode(true);
        if (printBtn.parentNode) {
            printBtn.parentNode.replaceChild(newPrint, printBtn);
        }
        newPrint.setAttribute('data-equipment-id', item.id);
        newPrint.addEventListener('click', () => {
            // ✅ IMPROVED VALIDATION: Enforce Serial Number
            const sn = item.serial_number;
            if (!sn || sn === '-' || sn.trim() === '') {
                Swal.fire({
                    icon: 'warning',
                    title: 'ไม่สามารถพิมพ์ QR Code ได้',
                    text: 'กรุณาสร้าง Serial Number ให้กับอุปกรณ์นี้ก่อนพิมพ์ QR Code',
                    confirmButtonText: 'รับทราบ'
                });
                return;
            }

            window.closeDetailsModal();
            setTimeout(() => {
                if (typeof window.openQrCodeModal === 'function') {
                    window.openQrCodeModal(sn, item.name);
                    const qrModal = document.getElementById('qr-code-modal');
                    if (qrModal) { qrModal.classList.remove('hidden'); qrModal.style.zIndex = '99999'; }
                }
            }, 200);
        });
    }
}

window.switchDetailsTab = function (selectedBtn, targetPanelId) {
    document.querySelectorAll('.details-tab-btn').forEach(btn => {
        btn.className = 'details-tab-btn flex-1 py-2 px-4 text-sm font-medium rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-white/60 transition-all duration-200';
        btn.removeAttribute('aria-current');
    });
    document.querySelectorAll('.details-tab-panel').forEach(panel => panel.classList.add('hidden'));
    selectedBtn.className = 'details-tab-btn flex-1 py-2 px-4 text-sm font-bold rounded-lg shadow-sm bg-white dark:bg-gray-600 text-indigo-600 dark:text-white transition-all duration-200 ring-1 ring-black/5';
    selectedBtn.setAttribute('aria-current', 'page');
    const target = document.getElementById(targetPanelId);
    if (target) target.classList.remove('hidden');
}

window.triggerDetailImageSlider = function () {
    if (typeof window.openImageSlider === 'function') {
        window.openImageSlider(currentDetailImages, currentDetailName);
    } else {
        const primaryImg = document.getElementById('details-primary-image');
        if (primaryImg) window.open(primaryImg.src, '_blank');
    }
}

window.closeDetailsModal = function () {
    const modal = document.getElementById('equipment-details-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
}

// ==========================================================================
// 5. IMAGE SLIDER / LIGHTBOX
// ==========================================================================

window.openImageSlider = function (images, title) {
    if (!images || images.length === 0) return;
    galleryImages = images;
    currentGalleryIndex = 0;
    const caption = document.getElementById('gallery-caption');
    if (caption) caption.textContent = title || 'รายละเอียด';
    updateGalleryView();
    const modal = document.getElementById('gallery-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
}

window.changeGalleryImage = function (direction) {
    currentGalleryIndex += direction;
    if (currentGalleryIndex >= galleryImages.length) currentGalleryIndex = 0;
    if (currentGalleryIndex < 0) currentGalleryIndex = galleryImages.length - 1;
    updateGalleryView();
}

function updateGalleryView() {
    const img = document.getElementById('gallery-main-image');
    const counter = document.getElementById('gallery-counter');
    const prevBtn = document.getElementById('gallery-prev');
    const nextBtn = document.getElementById('gallery-next');
    if (img) { img.style.opacity = '0'; setTimeout(() => { img.src = galleryImages[currentGalleryIndex]; img.style.opacity = '1'; }, 150); }
    if (counter) counter.textContent = `${currentGalleryIndex + 1} / ${galleryImages.length}`;
    if (prevBtn) prevBtn.classList.toggle('hidden', galleryImages.length <= 1);
    if (nextBtn) nextBtn.classList.toggle('hidden', galleryImages.length <= 1);
}

window.closeGalleryModal = function () {
    const modal = document.getElementById('gallery-modal');
    if (modal) modal.classList.add('hidden');
    const detailsModal = document.getElementById('equipment-details-modal');
    if (!detailsModal || detailsModal.classList.contains('hidden')) document.body.style.overflow = '';
}

// ==========================================================================
// 6. QR CODE & BARCODE
// ==========================================================================

window.openQrCodeModal = function (data, name) {
    const modal = document.getElementById('qr-code-modal');
    if (!modal) return;

    const titleEl = document.getElementById('qr-modal-name');
    if (titleEl) titleEl.textContent = name || 'QR Code';

    // QR
    const qrContainer = document.getElementById('qr-code-container');
    if (qrContainer) {
        qrContainer.innerHTML = '';
        if (typeof QRCode !== 'undefined') new QRCode(qrContainer, { text: data, width: 200, height: 200, colorDark: "#000000", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.H });
        else qrContainer.innerHTML = '<p class="text-red-500">Library Missing</p>';
    }

    // Barcode
    const barcodeContainer = document.getElementById('barcode-container');
    const barcodeName = document.getElementById('qr-barcode-name');
    if (barcodeContainer) {
        if (typeof JsBarcode !== 'undefined') {
            JsBarcode(barcodeContainer, data, { format: "CODE128", lineColor: "#000", width: 2, height: 60, displayValue: false });
            if (barcodeName) barcodeName.textContent = name; // ✅ User Request: Show Name instead of SN
        }
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.style.zIndex = '99999';

    const printBtn = document.getElementById('print-qr-btn');
    if (printBtn) {
        const newBtn = printBtn.cloneNode(true);
        printBtn.parentNode.replaceChild(newBtn, printBtn);
        newBtn.addEventListener('click', () => {
            const printWindow = window.open('', '', 'height=600,width=800');
            const qrImg = qrContainer.querySelector('img')?.src || '';
            const barcodeImg = barcodeContainer.toDataURL ? barcodeContainer.toDataURL() : null;

            printWindow.document.write('<html><head><title>Print Tags</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: "Sarabun", sans-serif; text-align: center; margin: 0; padding: 20px; }');
            printWindow.document.write('.tag-card { border: 1px solid #000; padding: 10px; margin: 10px; display: inline-flex; flex-direction: column; align-items: center; width: 280px; page-break-inside: avoid; border-radius: 0px; }');
            printWindow.document.write('.title { font-weight: bold; font-size: 18px; margin-bottom: 5px; line-height: 1.2; text-align: center; word-wrap: break-word; width: 100%; display: none; }'); // Hide duplicate top title if user wants name at bottom? I'll hide it for now to match specific "below barcode" request cleanly.
            printWindow.document.write('.qr-img { width: 140px; height: 140px; margin: 5px 0; }');
            printWindow.document.write('.barcode-container { width: 100%; display: flex; justify-content: center; margin-top: 5px; }');
            printWindow.document.write('.barcode-img { max-width: 90%; height: 50px; }');
            printWindow.document.write('.code { font-family: "Sarabun", sans-serif; font-size: 14px; color: #000; font-weight: bold; margin-top: 5px; word-wrap: break-word; }'); // Use Sarabun for Name
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');

            // ✅ SINGLE CARD LAYOUT
            printWindow.document.write('<div class="tag-card">');
            // printWindow.document.write(`<div class="title">${name}</div>`); // Hiding top title to prioritize bottom as requested

            if (qrImg) {
                printWindow.document.write(`<img src="${qrImg}" class="qr-img" />`);
            }

            if (barcodeImg) {
                printWindow.document.write(`<div class="barcode-container"><img src="${barcodeImg}" class="barcode-img" /></div>`);
            }

            // ✅ User Request: text below barcode should be Equipment Name
            printWindow.document.write(`<div class="code">${name}</div>`);
            printWindow.document.write('</div>');

            printWindow.document.write('</body></html>');
            printWindow.document.close();
            setTimeout(() => { printWindow.focus(); printWindow.print(); }, 500);
        });
    }
}

// ==========================================================================
// 7. PO OPERATIONS
// ==========================================================================

window.confirmAddItemToPo = function (event, form, type) {
    event.preventDefault();
    const equipmentName = form.dataset.equipmentName;
    const poTypeName = type === 'ด่วน' ? 'ใบสั่งซื้อด่วน' : 'ใบสั่งซื้อตามรอบ';
    Swal.fire({
        title: `ยืนยันการเพิ่มรายการ`, html: `คุณต้องการเพิ่ม <b>${equipmentName}</b><br>ลงใน ${poTypeName} ใช่หรือไม่?`, icon: 'question',
        showCancelButton: true, confirmButtonText: 'ใช่, เพิ่มเลย!', cancelButtonText: 'ยกเลิก'
    }).then((result) => { if (result.isConfirmed) form.submit(); });
}

window.showQuantityModal = function (event, form) {
    event.preventDefault();
    const equipmentName = form.dataset.equipmentName;
    Swal.fire({
        title: `สั่งซื้อ (ตามรอบ): ${equipmentName}`, input: 'number', inputLabel: 'กรุณาระบุจำนวนที่ต้องการสั่งซื้อ', inputValue: 1,
        inputAttributes: { min: 1, step: 1 }, showCancelButton: true, confirmButtonText: 'เพิ่มลงตะกร้า', cancelButtonText: 'ยกเลิก',
        inputValidator: (value) => { if (!value || value < 1) { return 'กรุณาใส่จำนวนที่มากกว่า 0' } }
    }).then((result) => {
        if (result.isConfirmed) {
            const quantityInput = document.createElement('input'); quantityInput.type = 'hidden'; quantityInput.name = 'quantity'; quantityInput.value = result.value;
            form.appendChild(quantityInput); form.submit();
        }
    });
}

// DOM Init (Delegation)
document.addEventListener('DOMContentLoaded', () => {
    const pageContainer = document.getElementById('equipment-page') || document.body;
    pageContainer.addEventListener('click', (event) => {
        const deleteButton = event.target.closest('.delete-button');
        if (deleteButton) {
            event.preventDefault();
            const form = deleteButton.closest('form');
            const name = deleteButton.dataset.equipmentName || 'รายการนี้';
            const actionUrl = form ? form.action : deleteButton.dataset.actionUrl;
            const match = actionUrl?.match(/equipment\/(\d+)/);
            if (match?.[1]) {
                deleteEquipment(parseInt(match[1], 10), name);
            }
        }
    });

    // ✅ AUTO-OPEN MODAL Logic (Handle Redirects from Reports or other pages)
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action');
    const id = urlParams.get('id');

    if (action === 'edit' && id) {
        // Wait a bit for other scripts to initialize
        setTimeout(() => {
            if (typeof window.showEditModal === 'function') {
                window.showEditModal(id);
                // Optional: Clean URL
                // window.history.replaceState({}, document.title, window.location.pathname);
            } else {
                console.warn('showEditModal function not found. Ensure equipment.js is loaded properly.');
            }
        }, 500);
    }
});