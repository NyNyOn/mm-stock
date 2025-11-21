/**
 * File: public/js/equipment.js
 * Description: Complete Equipment Management Logic
 * Version: Fully Restored (Original Form Logic + New Details/QR Features)
 */

// ==========================================================================
// 1. GLOBAL VARIABLES & HELPERS
// ==========================================================================

let currentDetailImages = [];
let currentDetailName = '';
let currentGalleryIndex = 0;

// Helper: Format Date (dd/mm/yyyy)
if (typeof window.formatDate === 'undefined') {
    window.formatDate = function(dateString) {
        if (!dateString) return '-';
        try {
            const d = new Date(dateString);
            return !isNaN(d.getTime()) ? d.toLocaleDateString('th-TH', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
        } catch (e) { return '-'; }
    }
}

// Helper: Format DateTime (dd/mm/yyyy hh:mm)
if (typeof window.formatDateTime === 'undefined') {
    window.formatDateTime = function(dateString) {
        if (!dateString) return '-';
        try {
            const d = new Date(dateString);
            return !isNaN(d.getTime()) ? d.toLocaleString('th-TH', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
        } catch (e) { return '-'; }
    }
}

// Helper: Withdrawal Type Text
if (typeof window.getWithdrawalTypeText === 'undefined') {
    window.getWithdrawalTypeText = function(type) {
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
    window.getTransactionTypeText = function(type) {
        const types = {
            receive: 'รับเข้า',
            withdraw: 'เบิก',
            adjust: 'ปรับสต็อก',
            borrow: 'ยืม',
            return: 'คืน',
            stock_check: 'ตรวจนับ'
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
    }
    badge.className += ` ${styleClass}`;
    badge.innerHTML = `${icon} ${text}`;
    return badge;
}

// Helper: Close any modal by ID
window.closeModal = function(modalId) {
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
            // Clear plugins and dynamic elements
            if(typeof clearImagePreviews === 'function') clearImagePreviews(form);
            if(typeof clearServerErrors === 'function') clearServerErrors(form);
            
            // Reset Select2 if exists
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $(form).find('.select2').val(null).trigger('change');
            }
            // Reset Stepper if exists
            if(typeof form.updateStepperUI === 'function') {
                form.updateStepperUI(1);
            }
        }
    }
};

// ==========================================================================
// 2. CRUD OPERATIONS (ADD / EDIT / DELETE) - RESTORED
// ==========================================================================

// ✅ Show Add Modal
window.showAddModal = async function() {
    const modal = document.getElementById('add-equipment-modal');
    const modalBody = document.getElementById('add-form-content-wrapper');

    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Load Add Form HTML via AJAX (to get fresh token and clean state)
        if(modalBody) {
             modalBody.innerHTML = '<div class="p-10 text-center"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i><p class="mt-2 text-gray-500">กำลังโหลดฟอร์ม...</p></div>';
             try {
                 const response = await fetch('/equipment/create');
                 if (!response.ok) throw new Error('Network response was not ok');
                 const html = await response.text();
                 modalBody.innerHTML = html;
                 
                 // ✅ Initialize Form Components
                 const form = modalBody.querySelector('form');
                 if(form) {
                     attachFormEventListeners(form);
                     // Initialize Select2 for Add Form
                     if (typeof $ !== 'undefined' && $.fn.select2) {
                        $(form).find('.select2').select2({ dropdownParent: $(modal), width: '100%' });
                     }
                 }
                 
             } catch(e) {
                 console.error(e);
                 modalBody.innerHTML = '<p class="text-red-500 text-center">โหลดฟอร์มไม่สำเร็จ</p>';
             }
        }
    }
};

// ✅ Show Edit Modal
window.showEditModal = async function(id) {
    const modal = document.getElementById('edit-equipment-modal');
    const modalBody = document.getElementById('edit-form-content-wrapper');

    if (!modal) return;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    if(modalBody) {
        modalBody.innerHTML = '<div class="flex justify-center items-center h-48"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i></div>';
        try {
            // Try specific AJAX route first, then fallback
            let response = await fetch(`/ajax/equipment/${id}/edit-form`);
            if (!response.ok) response = await fetch(`/equipment/${id}/edit`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            
            if (!response.ok) throw new Error('Failed to load edit form');
            
            const html = await response.text();
            modalBody.innerHTML = html;
            
            const form = modalBody.querySelector('form');
            if(form) {
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
window.deleteEquipment = async function(id, name) {
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
// 3. FORM UTILITIES & LISTENERS (RESTORED)
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

    // Stepper Logic (for Add/Edit)
    const suffix = form.id.split('-').pop();
    if(form.querySelector(`#next-step-btn-${suffix}`)) {
        initializeStepper(form, suffix);
    }
    
    // MSDS Logic
    const msdsCheck = document.getElementById(`has_msds_checkbox-${suffix}`);
    if(msdsCheck) msdsCheck.addEventListener('change', handleMsdsCheckboxChange);
    
    const msdsBtn = document.getElementById(`manage-msds-btn-${suffix}`);
    if(msdsBtn) {
        // Clone to remove old listeners
        const newBtn = msdsBtn.cloneNode(true);
        msdsBtn.parentNode.replaceChild(newBtn, msdsBtn);
        newBtn.addEventListener('click', () => openMsdsModal(form));
    }
    
    // ✅ Serial Gen Trigger (เมื่อเปลี่ยนหมวดหมู่)
    const catSelect = document.getElementById(`category_id-${suffix}`);
    if(catSelect) {
        // ใช้ jQuery on change เพราะ Select2 อาจกิน event
        if (typeof $ !== 'undefined') {
            $(catSelect).on('change', () => generateSerialNumber(suffix));
        } else {
            catSelect.addEventListener('change', () => generateSerialNumber(suffix));
        }
    }
    // ปุ่ม Generate SN Manual
    const genSnBtn = document.getElementById(`generate-serial-btn-${suffix}`);
    if(genSnBtn) {
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
    if(submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'; }

    try {
        const response = await fetch(form.action, {
            method: 'POST', body: formData,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') }
        });
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
    } catch(e) {
        console.error(e);
        Swal.fire('Error', e.message, 'error');
    } finally {
        if(submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalText; }
    }
}

// --- Image Preview Logic ---
function setupImagePreviews(form) {
    const suffix = form.id.split('-').pop();
    const input = document.getElementById(`images-${suffix}`);
    const container = document.getElementById(`image-previews-${suffix}`);
    if(!input || !container) return;

    input.addEventListener('change', (e) => {
        container.innerHTML = '';
        Array.from(e.target.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = (ev) => {
                const div = document.createElement('div');
                div.className = 'relative w-20 h-20 group';
                div.innerHTML = `<img src="${ev.target.result}" class="w-full h-full object-cover rounded border"><button type="button" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs shadow opacity-0 group-hover:opacity-100 transition" onclick="this.parentElement.remove()">&times;</button>`;
                container.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    });
}

// --- Existing Image Deletion Logic ---
function setupExistingImageDeletion(form) {
    const suffix = form.id.split('-').pop();
    const container = document.getElementById(`existing-images-container-${suffix}`);
    if(!container) return;
    
    container.addEventListener('click', (e) => {
        const btn = e.target.closest('.delete-existing-image-btn');
        if(btn) {
            const id = btn.dataset.imageId;
            const input = document.getElementById(`delete_image_${id}`);
            const imgWrapper = document.getElementById(`image-${id}-wrapper`);
            if(input && imgWrapper) {
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
        
        if(input) {
            input.classList.add('is-invalid');
            let errorDiv = input.parentNode.querySelector('.invalid-feedback');
            if(!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback text-red-500 text-xs mt-1';
                input.parentNode.appendChild(errorDiv);
            }
            errorDiv.textContent = errors[field][0];
            
             // Detect step
            const stepPanel = input.closest('.step-panel');
            if (stepPanel && firstErrorStep === null) {
                 const match = stepPanel.id.match(/step-(\d+)-panel/);
                 if(match) firstErrorStep = parseInt(match[1]);
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
    if(container) container.innerHTML = '';
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
        for(let i=1; i<=total; i++) {
            const p = document.getElementById(`step-${i}-panel-${suffix}`);
            if(p) p.classList.toggle('hidden', i!==step);
            
            const ind = document.getElementById(`step-indicator-${i}-${suffix}`);
            if(ind) {
                ind.classList.toggle('active', i===step);
                ind.classList.toggle('completed', i<step);
                ind.classList.toggle('pending', i>step);
            }
        }
        const prev = document.getElementById(`prev-step-btn-${suffix}`);
        const next = document.getElementById(`next-step-btn-${suffix}`);
        const sub = document.getElementById(`submit-btn-${suffix}`);
        if(prev) prev.classList.toggle('hidden', step===1);
        if(next) next.classList.toggle('hidden', step===total);
        if(sub) sub.classList.toggle('hidden', step!==total);
    };
    
    const nextBtn = document.getElementById(`next-step-btn-${suffix}`);
    const prevBtn = document.getElementById(`prev-step-btn-${suffix}`);
    if(nextBtn) nextBtn.addEventListener('click', () => { if(step<total) updateUI(step+1); });
    if(prevBtn) prevBtn.addEventListener('click', () => { if(step>1) updateUI(step-1); });
    
    form.updateStepperUI = updateUI;
    updateUI(1);
}

// --- Serial Generator (RESTORED) ---
async function generateSerialNumber(suffix) {
    const catSelect = document.getElementById(`category_id-${suffix}`);
    const serialInput = document.getElementById(`serial_number-${suffix}`);
    
    // เช็คค่าจาก Select2 หรือ Native Select
    let catValue = catSelect ? (catSelect.value || $(catSelect).val()) : null;
    
    if(!catValue || !serialInput) return;
    
    try {
        const res = await fetch("/ajax/next-serial", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
            body: JSON.stringify({ category_id: catValue })
        });
        const data = await res.json();
        if(data.success) {
            serialInput.value = data.serial_number;
            // Flash effect
            serialInput.classList.add('bg-green-100');
            setTimeout(() => serialInput.classList.remove('bg-green-100'), 500);
        }
    } catch(e) { console.error(e); }
}

// --- MSDS Logic ---
function handleMsdsCheckboxChange(e) {
    const suffix = e.target.id.split('-').pop();
    const container = document.getElementById(`msds-management-container-${suffix}`);
    if(container) container.style.display = e.target.checked ? 'block' : 'none';
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

window.showDetailsModal = async function(equipmentId) {
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
        if(el) el.textContent = (val === null || val === undefined || val === '') ? '-' : val; 
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
    if(statusWrapper) {
        statusWrapper.innerHTML = '';
        statusWrapper.appendChild(createStatusBadgeInternal(item.status));
    }

    const msdsTab = document.getElementById('details-msds-tab');
    const msdsDetails = document.getElementById('details-msds-details');
    const msdsLink = document.getElementById('details-msds-file');
    
    if (item.has_msds) {
        if(msdsTab) msdsTab.classList.remove('hidden');
        if(msdsDetails) msdsDetails.textContent = item.msds_details || '-';
        if (msdsLink && item.msds_file_url) {
            msdsLink.href = item.msds_file_url;
            msdsLink.classList.remove('hidden');
            msdsLink.classList.add('inline-flex');
        }
    } else {
        if(msdsTab) msdsTab.classList.add('hidden');
    }
    
    const firstTab = document.querySelector("[onclick*='details-tab-main']");
    if(firstTab) switchDetailsTab(firstTab, 'details-tab-main');

    const historyBox = document.getElementById('details-transactions');
    if(historyBox) {
        historyBox.innerHTML = '';
        if(item.transactions && item.transactions.length > 0) {
            item.transactions.forEach(t => {
                const isPlus = t.quantity_change >= 0;
                const div = document.createElement('div');
                div.className = `p-2.5 border-b text-xs mb-1 last:border-0`;
                div.innerHTML = `
                    <div class="flex justify-between font-bold text-gray-700">
                        <span>${window.getTransactionTypeText(t.type)}</span>
                        <span class="font-mono text-gray-400">${window.formatDateTime(t.transaction_date)}</span>
                    </div>
                    <div class="flex justify-between text-gray-500 mt-0.5">
                        <span>${t.user?.fullname || 'System'}</span>
                        <span class="${isPlus ? 'text-green-600' : 'text-red-600'} font-bold">(${isPlus ? '+' : ''}${t.quantity_change})</span>
                    </div>
                `;
                historyBox.appendChild(div);
            });
        } else {
            historyBox.innerHTML = '<div class="text-center text-gray-400 text-xs py-4">ไม่มีประวัติ</div>';
        }
    }

    setupGallery(item);
    setupDetailButtons(item);
}

function setupGallery(item) {
    const primaryImg = document.getElementById('details-primary-image');
    const thumbContainer = document.getElementById('details-gallery-thumbnails');
    if (!primaryImg || !thumbContainer) return;
    
    thumbContainer.innerHTML = '';
    let finalUrl = 'https://placehold.co/600x400/e2e8f0/64748b?text=No+Image';

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
        currentDetailImages = [finalUrl];
    }

    primaryImg.src = finalUrl;

    if (currentDetailImages.length > 1) {
        currentDetailImages.forEach(url => {
            const div = document.createElement('div');
            div.className = 'relative aspect-square cursor-pointer group';
            div.innerHTML = `<img src="${url}" class="w-full h-full object-cover rounded border-2 border-transparent hover:border-indigo-500 transition shadow-sm">`;
            div.onclick = () => {
                primaryImg.style.opacity = '0.5';
                setTimeout(() => { primaryImg.src = url; primaryImg.style.opacity = '1'; }, 150);
            };
            thumbContainer.appendChild(div);
        });
    }
}

function setupDetailButtons(item) {
    const editBtn = document.getElementById('details-edit-btn');
    const printBtn = document.getElementById('details-print-btn');

    if (editBtn) {
        const newEdit = editBtn.cloneNode(true);
        editBtn.parentNode.replaceChild(newEdit, editBtn);
        newEdit.setAttribute('data-equipment-id', item.id);
        newEdit.addEventListener('click', () => {
            window.closeDetailsModal();
            if (typeof window.showEditModal === 'function') window.showEditModal(item.id);
        });
    }

    if (printBtn) {
        const newPrint = printBtn.cloneNode(true);
        printBtn.parentNode.replaceChild(newPrint, printBtn);
        newPrint.setAttribute('data-equipment-id', item.id);
        newPrint.addEventListener('click', () => {
            const sn = item.serial_number && item.serial_number !== '-' ? item.serial_number : String(item.id);
            window.closeDetailsModal();
            setTimeout(() => {
                if (typeof window.openQrCodeModal === 'function') {
                    window.openQrCodeModal(sn, item.name);
                    const qrModal = document.getElementById('qr-code-modal');
                    if(qrModal) { qrModal.classList.remove('hidden'); qrModal.style.zIndex = '99999'; }
                }
            }, 200);
        });
    }
}

window.switchDetailsTab = function(selectedBtn, targetPanelId) {
    document.querySelectorAll('.details-tab-btn').forEach(btn => {
        btn.className = 'details-tab-btn flex-1 py-2 px-4 text-sm font-medium rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-white/60 transition-all duration-200';
        btn.removeAttribute('aria-current');
    });
    document.querySelectorAll('.details-tab-panel').forEach(panel => panel.classList.add('hidden'));
    selectedBtn.className = 'details-tab-btn flex-1 py-2 px-4 text-sm font-bold rounded-lg shadow-sm bg-white dark:bg-gray-600 text-indigo-600 dark:text-white transition-all duration-200 ring-1 ring-black/5';
    selectedBtn.setAttribute('aria-current', 'page');
    const target = document.getElementById(targetPanelId);
    if(target) target.classList.remove('hidden');
}

window.triggerDetailImageSlider = function() {
    if (typeof window.openImageSlider === 'function') {
        window.openImageSlider(currentDetailImages, currentDetailName);
    } else {
        const primaryImg = document.getElementById('details-primary-image');
        if(primaryImg) window.open(primaryImg.src, '_blank');
    }
}

window.closeDetailsModal = function() {
    const modal = document.getElementById('equipment-details-modal');
    if(modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
}

// ==========================================================================
// 5. IMAGE SLIDER / LIGHTBOX
// ==========================================================================

window.openImageSlider = function(images, title) {
    if (!images || images.length === 0) return;
    galleryImages = images;
    currentGalleryIndex = 0;
    const caption = document.getElementById('gallery-caption');
    if(caption) caption.textContent = title || 'รายละเอียด';
    updateGalleryView();
    const modal = document.getElementById('gallery-modal');
    if(modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
}

window.changeGalleryImage = function(direction) {
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
    if(img) { img.style.opacity = '0'; setTimeout(() => { img.src = galleryImages[currentGalleryIndex]; img.style.opacity = '1'; }, 150); }
    if(counter) counter.textContent = `${currentGalleryIndex + 1} / ${galleryImages.length}`;
    if(prevBtn) prevBtn.classList.toggle('hidden', galleryImages.length <= 1);
    if(nextBtn) nextBtn.classList.toggle('hidden', galleryImages.length <= 1);
}

window.closeGalleryModal = function() {
    const modal = document.getElementById('gallery-modal');
    if(modal) modal.classList.add('hidden');
    const detailsModal = document.getElementById('equipment-details-modal');
    if(!detailsModal || detailsModal.classList.contains('hidden')) document.body.style.overflow = '';
}

// ==========================================================================
// 6. QR CODE & BARCODE
// ==========================================================================

window.openQrCodeModal = function(data, name) {
    const modal = document.getElementById('qr-code-modal');
    if (!modal) return;

    const titleEl = document.getElementById('qr-modal-title');
    if(titleEl) titleEl.textContent = name || 'QR Code';

    // QR
    const qrContainer = document.getElementById('qr-code-container');
    if(qrContainer) {
        qrContainer.innerHTML = '';
        if (typeof QRCode !== 'undefined') new QRCode(qrContainer, { text: data, width: 200, height: 200, colorDark : "#000000", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.H });
        else qrContainer.innerHTML = '<p class="text-red-500">Library Missing</p>';
    }

    // Barcode
    const barcodeContainer = document.getElementById('barcode-container');
    const barcodeName = document.getElementById('qr-barcode-name');
    if (barcodeContainer) {
        if (typeof JsBarcode !== 'undefined') {
            JsBarcode(barcodeContainer, data, { format: "CODE128", lineColor: "#000", width: 2, height: 60, displayValue: false });
            if(barcodeName) barcodeName.textContent = data;
        }
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.style.zIndex = '99999'; 

    const printBtn = document.getElementById('print-qr-btn');
    if(printBtn) {
        const newBtn = printBtn.cloneNode(true);
        printBtn.parentNode.replaceChild(newBtn, printBtn);
        newBtn.addEventListener('click', () => {
            const printWindow = window.open('', '', 'height=600,width=800');
            const qrImg = qrContainer.querySelector('img')?.src || '';
            const barcodeImg = barcodeContainer.toDataURL ? barcodeContainer.toDataURL() : '';

            printWindow.document.write('<html><head><title>Print Tags</title>');
            printWindow.document.write('<style>body { font-family: "Sarabun", sans-serif; text-align: center; margin: 20px; } .tag-card { border: 1px dashed #999; padding: 20px; margin: 10px; display: inline-block; width: 250px; vertical-align: top; page-break-inside: avoid; } .qr-img { width: 150px; height: 150px; } .barcode-img { width: 200px; height: 60px; margin-top: 10px; } .title { font-weight: bold; font-size: 16px; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; } .code { font-family: monospace; font-size: 14px; color: #555; }</style>');
            printWindow.document.write('</head><body>');
            
            printWindow.document.write('<div class="tag-card">');
            if(qrImg) printWindow.document.write(`<img src="${qrImg}" class="qr-img" />`);
            printWindow.document.write(`<div class="title">${name}</div><div class="code">${data}</div></div>`);

            if(barcodeImg) {
                printWindow.document.write('<div class="tag-card">');
                printWindow.document.write(`<img src="${barcodeImg}" class="barcode-img" />`);
                printWindow.document.write(`<div class="title">${name}</div><div class="code">${data}</div></div>`);
            }

            printWindow.document.write('</body></html>');
            printWindow.document.close();
            setTimeout(() => { printWindow.focus(); printWindow.print(); }, 500);
        });
    }
}

// ==========================================================================
// 7. PO OPERATIONS
// ==========================================================================

window.confirmAddItemToPo = function(event, form, type) {
    event.preventDefault();
    const equipmentName = form.dataset.equipmentName;
    const poTypeName = type === 'ด่วน' ? 'ใบสั่งซื้อด่วน' : 'ใบสั่งซื้อตามรอบ';
    Swal.fire({
        title: `ยืนยันการเพิ่มรายการ`, html: `คุณต้องการเพิ่ม <b>${equipmentName}</b><br>ลงใน ${poTypeName} ใช่หรือไม่?`, icon: 'question',
        showCancelButton: true, confirmButtonText: 'ใช่, เพิ่มเลย!', cancelButtonText: 'ยกเลิก'
    }).then((result) => { if (result.isConfirmed) form.submit(); });
}

window.showQuantityModal = function(event, form) {
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
});