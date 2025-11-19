/**
 * File: public/js/equipment.js
 * Description: Handles AJAX interactions for equipment management, including modals and image handling.
 */

// --- Helper Functions (Global Scope or ensure accessibility) ---

// Make sure these helpers are accessible globally or defined before use
// If they are already defined globally in another file (like main.js),
// you might not need to redefine them here, but ensure they are loaded first.
if (typeof window.formatDate === 'undefined') {
    window.formatDate = function(dateString) { if (!dateString) return '-'; try { const d = new Date(dateString); return !isNaN(d.getTime()) ? d.toLocaleDateString('th-TH') : '-'; } catch (e) { return '-'; } }
}
if (typeof window.formatDateTime === 'undefined') {
    window.formatDateTime = function(dateString) { if (!dateString) return '-'; try { const d = new Date(dateString); return !isNaN(d.getTime()) ? d.toLocaleString('th-TH') : '-'; } catch (e) { return '-'; } }
}
if (typeof window.getWithdrawalTypeText === 'undefined') {
    window.getWithdrawalTypeText = function(type) { return { consumable: 'พัสดุสิ้นเปลือง', returnable: 'ต้องคืน', partial_return: 'คืนบางส่วน' }[type] || '-'; }
}
if (typeof window.getTransactionTypeText === 'undefined') {
    window.getTransactionTypeText = function(type) { return { receive: 'รับเข้า', withdraw: 'เบิก', adjust: 'ปรับสต็อก', borrow: 'ยืม', return: 'คืน', stock_check: 'ตรวจนับ', write_off: 'ตัดยอด' }[type] || type || '-'; }
}

// Function to create status badge (can be kept here or moved to a global helper file)
function createStatusBadge(status) {
    const badge = document.createElement('span');
    badge.classList.add('px-2.5', 'py-0.5', 'rounded-full', 'text-xs', 'font-medium', 'inline-block');
    let text = status; let bgColor = 'bg-gray-100'; let textColor = 'text-gray-800';
    switch (status) {
        case 'in_stock': case 'available': text = '✅ พร้อมใช้'; bgColor = 'bg-green-100'; textColor = 'text-green-800'; break;
        case 'in_use': case 'on_loan': text = '👥 ใช้งาน'; bgColor = 'bg-yellow-100'; textColor = 'text-yellow-800'; break;
        case 'low_stock': text = '⚠️ สต็อกต่ำ'; bgColor = 'bg-orange-100'; textColor = 'text-orange-800'; break;
        case 'out_of_stock': text = '⛔ หมด'; bgColor = 'bg-red-100'; textColor = 'text-red-800'; break;
        case 'repairing': case 'maintenance': text = '🛠️ ซ่อม'; bgColor = 'bg-indigo-100'; textColor = 'text-indigo-800'; break;
        case 'on_order': text = '⏳ สั่งซื้อ'; bgColor = 'bg-cyan-100'; textColor = 'text-cyan-800'; break;
        case 'inactive': text = '⭕ ไม่ใช้'; bgColor = 'bg-gray-200'; textColor = 'text-gray-600'; break;
        case 'disposed': case 'sold': case 'written_off': text = '❌ จำหน่าย'; bgColor = 'bg-pink-200'; textColor = 'text-pink-800'; break;
        default: text = status ? status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Unknown';
    }
    badge.textContent = text; badge.classList.add(bgColor, textColor); return badge;
}


// --- Modal Controls ---
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        console.log(`[DEBUG] Modal shown: ${modalId}`);
    } else {
        console.error(`Modal element with ID '${modalId}' not found.`);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        console.log(`[DEBUG] Modal closed: ${modalId}`);

        // Reset form inside the modal if it exists
        const form = modal.querySelector('form');
        if (form) {
            form.reset(); // Reset form fields to default values
            clearImagePreviews(form); // Clear dynamically added image previews
            clearServerErrors(form); // Clear validation errors
            // Reset stepper to step 1 if the form has stepper logic attached
            if (typeof form.updateStepperUI === 'function') {
                 form.updateStepperUI(1);
                 console.log(`[DEBUG] Stepper reset to step 1 for form: #${form.id}`);
            }
            console.log(`[DEBUG] Form reset in modal: ${modalId}`);
        }

        // Specifically reset content area for Add modal to show loading state again
         if (modalId === 'add-equipment-modal') {
            const addModalBody = document.getElementById('add-form-content-wrapper');
            if (addModalBody) {
                // Restore loading state HTML
                addModalBody.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i><p class="mt-2 text-gray-500">Loading form...</p></div>';
                console.log('[DEBUG] Add modal content reset to loading state.');
            }
        }
        // No LightGallery destroy needed anymore
    } else {
        console.error(`Modal element with ID '${modalId}' not found for closing.`);
    }
}

// --- Form Helpers ---

// Function to clear dynamically added image previews in add/edit forms
function clearImagePreviews(form) {
    if (!form || !form.id) return;
    const uniqueSuffix = form.id.split('-').pop(); // Get 'new' or equipment ID
    if (!uniqueSuffix) return;

    // Clear previews generated from file input
    const previewContainer = document.getElementById(`image-previews-${uniqueSuffix}`);
    if (previewContainer) {
        previewContainer.innerHTML = '';
    }

    // Reset state of existing images (for edit form)
    const existingImagesContainer = document.getElementById(`existing-images-container-${uniqueSuffix}`);
    if(existingImagesContainer){
        // Reset delete buttons and opacity
        existingImagesContainer.querySelectorAll('.delete-existing-image-btn').forEach(btn => {
            const wrapper = btn.closest('.relative.group');
            const hiddenInput = wrapper?.querySelector('input[name="delete_images[]"]');
            if (hiddenInput) hiddenInput.disabled = true; // Disable deletion marker
            if (wrapper) wrapper.style.opacity = '1'; // Make visible again
            btn.innerHTML = '<i class="fas fa-times text-xs"></i>'; // Reset icon
            btn.title = 'Mark for Deletion'; // Reset title
            btn.classList.replace('bg-yellow-500', 'bg-red-600'); // Reset color
        });
        // Reset primary image radio buttons to original state
         const primaryRadios = existingImagesContainer.querySelectorAll('.primary-image-radio');
         if (primaryRadios.length > 0) {
            // Find the one that was originally checked (if any, marked during setup)
            const originallyChecked = existingImagesContainer.querySelector('.primary-image-radio[data-originally-checked="true"]');
            if (originallyChecked) {
                originallyChecked.checked = true;
            } else {
                 // If none were originally checked, just uncheck all
                 primaryRadios.forEach(radio => radio.checked = false);
            }
         }
    }

     // Clear the file input itself to remove selected files
     const imageInput = document.getElementById(`images-${uniqueSuffix}`);
     if (imageInput) {
         imageInput.value = ''; // This clears the selected files
     }
     console.log(`[DEBUG_IMG] Image previews and input cleared for suffix: ${uniqueSuffix}`);
}

// Function to clear server-side validation error messages and styles
function clearServerErrors(form) {
    if (!form) return;
    // Remove red border/ring from invalid inputs
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    // Clear error messages below inputs
    form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
    // Clear general error area at the top of the form
    const generalErrorArea = form.querySelector('.general-errors');
    if (generalErrorArea) generalErrorArea.innerHTML = '';
    console.log(`[DEBUG_VALIDATION] Server errors cleared for form: #${form.id}`);
}


// --- Modal Loading Functions ---

// Function to load and show the Add Equipment modal
async function showAddModal() {
    console.log('[DEBUG] showAddModal called');
    const modal = document.getElementById('add-equipment-modal');
    const modalBody = document.getElementById('add-form-content-wrapper');

    if (!modal || !modalBody) {
        console.error('Add Equipment Modal or body container not found!');
        Swal.fire('ข้อผิดพลาด', 'UI Component หายไป (Add Modal)', 'error');
        return;
    }

    // Show loading state and modal backdrop
    modalBody.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i><p class="mt-2 text-gray-500">Loading form...</p></div>';
    showModal('add-equipment-modal');

    try {
        // Fetch the HTML content for the create form
        // Assumes '/equipment/create' route returns the partial view '_form.blade.php'
        const response = await fetch('/equipment/create');
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const html = await response.text();

        // Inject the fetched form HTML into the modal body
        modalBody.innerHTML = html;

        // Find the newly injected form element
        const newForm = modalBody.querySelector('form');
        // Attach necessary event listeners (submit, image previews, stepper, etc.)
        if (newForm && typeof attachFormEventListeners === 'function') {
            console.log(`[DEBUG_ADD] Calling attachFormEventListeners for new form: #${newForm.id}`);
            attachFormEventListeners(newForm);
        } else {
            // Handle case where form or the attaching function is missing
            console.error('[DEBUG_ADD] Could not find form or attachFormEventListeners function after loading!');
            modalBody.innerHTML = `<p class="text-red-500 text-center p-4">เกิดข้อผิดพลาดในการเตรียมฟอร์ม</p>`;
        }
    } catch (error) {
        // Handle errors during fetch or form setup
        console.error('[DEBUG_ADD] Error loading create form:', error);
        modalBody.innerHTML = `<p class="text-red-500 text-center p-4">เกิดข้อผิดพลาดในการโหลด: ${error.message}</p>`;
    }
}

// Function to load and show the Edit Equipment modal
async function showEditModal(id) {
    console.log(`[DEBUG] showEditModal called for ID: ${id}`);
    const modal = document.getElementById('edit-equipment-modal');
    const modalBody = document.getElementById('edit-form-content-wrapper');

    if (!modal || !modalBody) {
        console.error('Edit Equipment Modal or body container not found!');
        Swal.fire('ข้อผิดพลาด', 'UI Component หายไป (Edit Modal)', 'error');
        return;
    }

    // Show loading state and modal backdrop
    modalBody.innerHTML = '<div class="flex justify-center items-center h-48"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i></div>';
    showModal('edit-equipment-modal');

    try {
        // Fetch the HTML content for the edit form for the specific equipment ID
        const response = await fetch(`/ajax/equipment/${id}/edit-form`);
        if (!response.ok) {
            const errorText = await response.text(); // Get potential error details from server
            console.error(`[DEBUG_EDIT] Failed to fetch edit form. Status: ${response.status}`, errorText);
            modalBody.innerHTML = `<p class="text-red-500 text-center p-4">ไม่สามารถโหลดฟอร์มแก้ไข (${response.status})</p>`;
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const html = await response.text();
        // Inject the fetched form HTML
        modalBody.innerHTML = html;

        // Find the newly injected form and attach listeners
        const newForm = modalBody.querySelector('form');
        if (newForm && typeof attachFormEventListeners === 'function') {
            console.log(`[DEBUG_EDIT] Calling attachFormEventListeners for edit form: #${newForm.id}`);
            attachFormEventListeners(newForm);
        } else {
             // Handle case where form or the attaching function is missing
            console.error('[DEBUG_EDIT] Could not find form or attachFormEventListeners function after loading!');
            modalBody.innerHTML = `<p class="text-red-500 text-center p-4">เกิดข้อผิดพลาดในการเตรียมฟอร์ม</p>`;
        }
    } catch (error) {
        // Handle errors during fetch or form setup
        console.error('[DEBUG_EDIT] Error loading edit form:', error);
        // Display error message only if not already showing a specific server error
        if (!modalBody.innerHTML.includes('text-red-500')) {
            modalBody.innerHTML = `<p class="text-red-500 text-center p-4">เกิดข้อผิดพลาด: ${error.message}</p>`;
        }
    }
}

// --- DETAILS Modal ---
// Function to fetch data and populate the Equipment Details modal
async function showDetailsModal(id) {
    console.log(`[DEBUG_DETAILS] showDetailsModal called for ID: ${id}`);
    const modal = document.getElementById('equipment-details-modal');
    // Get all necessary elements within the modal
    const modalBody = document.getElementById('details-body');
    const loadingSpinner = document.getElementById('details-loading');
    const errorContainer = document.getElementById('details-error-message');
    const detailsFooter = document.getElementById('details-footer');
    const primaryImageDisplay = document.getElementById('details-primary-image');
    const thumbnailContainer = document.getElementById('details-gallery-thumbnails'); // Corrected ID

    // Check if all essential elements exist
    if (!modal || !modalBody || !loadingSpinner || !errorContainer || !detailsFooter || !primaryImageDisplay || !thumbnailContainer) {
        console.error('One or more essential elements for the details modal are missing!', {
            modal: !!modal, body: !!modalBody, loading: !!loadingSpinner, error: !!errorContainer, footer: !!detailsFooter, primaryImg: !!primaryImageDisplay, thumbnails: !!thumbnailContainer
        });
        Swal.fire('ข้อผิดพลาด', 'UI Component หายไป (Details Modal)', 'error');
        return;
    }

    // Reset modal state: Hide content/error, show loading, reset images
    modalBody.classList.add('hidden');
    errorContainer.classList.add('hidden'); errorContainer.textContent = '';
    loadingSpinner.classList.remove('hidden');
    detailsFooter.classList.add('hidden');
    primaryImageDisplay.src = 'https://placehold.co/600x400/e2e8f0/64748b?text=Loading...'; // Placeholder
    thumbnailContainer.innerHTML = '<div class="col-span-6 text-center text-gray-500 text-xs py-2">Loading thumbnails...</div>'; // Placeholder
    showModal('equipment-details-modal'); // Show the modal backdrop and container

    // Removed LightGallery destroy logic

    try {
        // Fetch equipment details data from the server
        const response = await fetch(`/equipment/${id}`); // Assumes route returns JSON
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        // Validate response structure
        if (!result.success || !result.data) throw new Error(result.message || 'Invalid data structure received from server.');

        const item = result.data; // The equipment data object
        console.log(`[DEBUG_DETAILS] Received equipment data successfully:`, item);

        // --- Populate Text Fields ---
        // Helper to safely set text content
        const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || '-'; else console.warn(`Element #${id} not found.`); };

        setText('details-name', item.name);
        // Populate status badge using the helper function
        const statusEl = document.getElementById('details-status');
        if (statusEl && typeof createStatusBadge === 'function') { statusEl.innerHTML = ''; statusEl.appendChild(createStatusBadge(item.status)); } else console.warn('Element #details-status or createStatusBadge not found.');
        setText('details-quantity', item.quantity ?? '0'); // Add unit later if needed
        setText('details-min-stock', item.min_stock ?? '0');
        setText('details-max-stock', item.max_stock ?? '0');
        setText('details-withdrawal-type', window.getWithdrawalTypeText(item.withdrawal_type)); // Use global helper
        setText('details-category', item.category?.name);
        setText('details-location', item.location?.name);
        setText('details-model', item.model);
        setText('details-part-no', item.part_no);
        setText('details-serial', item.serial_number);
        setText('details-supplier', item.supplier);
        setText('details-price', item.price ? parseFloat(item.price).toLocaleString('th-TH', { style: 'currency', currency: 'THB' }) : '-'); // Format price
        setText('details-purchase-date', window.formatDate(item.purchase_date)); // Use global helper
        setText('details-warranty-date', window.formatDate(item.warranty_date)); // Use global helper
        setText('details-notes', item.notes || '-');
        setText('details-created-at', window.formatDateTime(item.created_at)); // Use global helper
        setText('details-updated-at', window.formatDateTime(item.updated_at)); // Use global helper

        // --- Populate MSDS Info ---
        const msdsSectionDiv = document.getElementById('details-msds-section');
        const msdsDetailsEl = document.getElementById('details-msds-details');
        const msdsLinkEl = document.getElementById('details-msds-file');
        if (msdsSectionDiv && msdsDetailsEl && msdsLinkEl) {
             if (item.has_msds) {
                msdsSectionDiv.classList.remove('hidden'); // Show section
                msdsDetailsEl.textContent = item.msds_details || '-'; // Show details
                if (item.msds_file_url) { // If a file URL exists
                    msdsLinkEl.href = item.msds_file_url; // Set link URL
                    msdsLinkEl.classList.remove('hidden'); // Show link
                } else {
                    msdsLinkEl.classList.add('hidden'); // Hide link if no URL
                }
            } else {
                msdsSectionDiv.classList.add('hidden'); // Hide whole section if no MSDS
                msdsLinkEl.classList.add('hidden'); // Ensure link is hidden
            }
        } else { console.warn('One or more MSDS elements not found in details modal.'); }


        // --- Populate Transaction History ---
        const transactionContainer = document.getElementById('details-transactions');
        if (transactionContainer) {
            transactionContainer.innerHTML = ''; // Clear previous entries
            if (item.transactions && item.transactions.length > 0) {
                 item.transactions.forEach(t => {
                    const div = document.createElement('div');
                    div.className = 'p-1.5 border-b last:border-b-0 text-xs'; // Style each transaction entry
                    // Populate with transaction details using helper functions
                    div.innerHTML = `
                        <div class="flex justify-between items-center">
                            <span class="${t.quantity_change >= 0 ? 'text-green-600' : 'text-red-600'} font-medium">${window.getTransactionTypeText(t.type)} (${t.quantity_change >= 0 ? '+' : ''}${t.quantity_change})</span>
                            <span class="text-gray-400">${window.formatDateTime(t.transaction_date)}</span>
                        </div>
                        <div class="flex justify-between items-center text-gray-500">
                            <span>โดย: ${t.user?.fullname || 'System'}</span>
                            <span>${t.status || ''}</span>
                        </div>
                        ${t.notes ? `<p class="text-gray-500 italic mt-0.5">"${t.notes}"</p>` : ''}
                    `;
                    transactionContainer.appendChild(div);
                });
            } else {
                // Show message if no transaction history
                transactionContainer.innerHTML = '<p class="py-4 px-3 text-xs text-center text-gray-500">ไม่มีประวัติ</p>';
            }
        } else { console.warn('Element #details-transactions container not found.'); }

        // --- Populate Image Gallery (Click to Change - NO LIGHTGALLERY) ---
        thumbnailContainer.innerHTML = ''; // Clear loading/previous thumbnails
        let finalPrimaryUrl = 'https://placehold.co/600x400/e2e8f0/64748b?text=No+Image'; // Default placeholder

        if (item.image_urls && item.image_urls.length > 0) {
            // Determine the primary image URL (use designated primary or fallback to the first)
            finalPrimaryUrl = item.primary_image_url || item.image_urls[0];

            // Create thumbnail images for each URL
            item.image_urls.forEach((url, index) => {
                const imgThumb = document.createElement('img');
                imgThumb.src = url; imgThumb.alt = `Thumbnail ${index + 1}`;
                imgThumb.className = 'w-full h-16 object-cover rounded cursor-pointer border-2 border-transparent hover:border-blue-400 transition';
                // Attach onclick event to change the primary image display
                // Assumes updatePrimaryImage is defined (likely in equipment-details.blade.php script)
                imgThumb.onclick = () => {
                    if(typeof window.updatePrimaryImage === 'function') {
                        window.updatePrimaryImage(url);
                    } else {
                        console.error('updatePrimaryImage function is not defined globally or is inaccessible.');
                    }
                };
                thumbnailContainer.appendChild(imgThumb);
            });
        } else {
            // Show message if no images are available
            thumbnailContainer.innerHTML = '<div class="col-span-6 text-center text-gray-500 text-xs py-2">No images</div>';
        }
        // Set the source for the main image display
        primaryImageDisplay.src = finalPrimaryUrl;

        // --- REMOVED LightGallery Initialization Block ---

        // --- Final UI Update ---
        loadingSpinner.classList.add('hidden'); // Hide loading spinner
        modalBody.classList.remove('hidden'); // Show main content area
        detailsFooter.classList.remove('hidden'); // Show footer with buttons

        // --- Attach Listeners to Footer Buttons ---
        // It's crucial to re-attach listeners *after* populating, potentially cloning buttons
        const printBtn = document.getElementById('details-print-btn');
        const editBtn = document.getElementById('details-edit-btn');

        // Store equipment ID on buttons for later use
        if(printBtn) printBtn.setAttribute('data-equipment-id', item.id); else console.warn("Print button 'details-print-btn' not found.");
        if(editBtn) editBtn.setAttribute('data-equipment-id', item.id); else console.warn("Edit button 'details-edit-btn' not found.");

        // Re-attach Listener for Print Button (using cloneNode to remove old listeners)
        if(printBtn){
             const newPrintBtn = printBtn.cloneNode(true);
             printBtn.parentNode.replaceChild(newPrintBtn, printBtn); // Replace old with new
             
             // ✅✅✅ START: MODIFIED PRINT BUTTON LISTENER ✅✅✅
             newPrintBtn.addEventListener('click', () => {
                 const equipmentId = newPrintBtn.getAttribute('data-equipment-id');
                 const equipmentName = document.getElementById('details-name')?.textContent || 'อุปกรณ์';

                 // ✅ NEW: ดึงค่า Serial Number จาก element ที่แสดงใน Modal
                 const equipmentSnElement = document.getElementById('details-serial');
                 const equipmentSn = equipmentSnElement ? equipmentSnElement.textContent : '';

                 // ✅ NEW: ตัดสินใจว่าจะใช้ค่าใดในการเข้ารหัส
                 // ถ้า equipmentSn มีค่า (ไม่ใช่ค่าว่าง หรือ '-') ให้ใช้ SN, ถ้าไม่ ให้ใช้ ID เป็นค่าสำรอง
                 const valueToEncode = (equipmentSn && equipmentSn.trim() !== '-' && equipmentSn.trim() !== '') 
                                     ? equipmentSn.trim() 
                                     : String(equipmentId);
                 
                 console.log(`[DEBUG_QR_CALL] Encoding value: ${valueToEncode} (SN: '${equipmentSn}', Fallback ID: '${equipmentId}')`);


                 // Call the QR Code modal function (ensure it's globally accessible)
                 if (typeof openQrCodeModal === 'function') {
                    if (valueToEncode) {
                        // ✅ MODIFIED: ส่งค่า valueToEncode (ซึ่งอาจเป็น SN หรือ ID) ไปเป็นพารามิเตอร์ตัวแรก
                        openQrCodeModal(valueToEncode, equipmentName); 
                    }
                    else { console.error("Details QR: No value to encode (ID and SN are missing)."); Swal.fire('Error', 'Missing ID/SN for QR', 'error'); }
                 } else {
                    console.error('openQrCodeModal function not found');
                    Swal.fire('Error', 'QR Code function not available.', 'error');
                 }
             });
             // ✅✅✅ END: MODIFIED PRINT BUTTON LISTENER ✅✅✅
        }

        // Re-attach Listener for Edit Button (using cloneNode)
        if(editBtn){
            const newEditBtn = editBtn.cloneNode(true);
            editBtn.parentNode.replaceChild(newEditBtn, editBtn); // Replace old with new
            newEditBtn.addEventListener('click', () => {
                 const equipmentId = newEditBtn.getAttribute('data-equipment-id');
                 if (equipmentId) {
                     closeModal('equipment-details-modal'); // Close details modal
                     // Ensure showEditModal is globally accessible or defined in the same scope
                     if (typeof showEditModal === 'function') {
                         showEditModal(equipmentId); // Open edit modal for this ID
                     } else {
                         console.error('showEditModal function not found');
                         Swal.fire('Error', 'Cannot open edit form.', 'error');
                     }
                 } else { console.error("Edit button missing equipment ID"); }
             });
        }
        console.log("[DEBUG_DETAILS] Details populated and shown.");

    } catch (error) {
        // Handle errors during fetch or population
        console.error('FATAL: Error fetching or processing details:', error);
        loadingSpinner.classList.add('hidden'); // Hide loading
        modalBody.classList.add('hidden'); // Keep content hidden
        detailsFooter.classList.add('hidden'); // Keep footer hidden
        errorContainer.textContent = `เกิดข้อผิดพลาด: ${error.message || 'ไม่สามารถโหลดข้อมูลได้'}`; // Show error message
        errorContainer.classList.remove('hidden'); // Make error visible
    }
}


// --- Serial Number Generation ---
// Function to fetch the next available serial number based on category
async function generateSerialNumber(suffix) {
    console.log(`%c[DEBUG_SN] 1. generateSerialNumber('${suffix}') called.`, "color: #007acc;");
    // Get relevant elements using the unique suffix
    const categorySelect = document.getElementById(`category_id-${suffix}`);
    const serialInput = document.getElementById(`serial_number-${suffix}`);
    const categoryId = categorySelect ? categorySelect.value : null;
    console.log(`%c[DEBUG_SN] 2. CategorySelect found: ${!!categorySelect}, SerialInput found: ${!!serialInput}`, "color: #007acc;");

    // Abort if category isn't selected or serial input doesn't exist
    if (!categoryId) { console.warn(`%c[DEBUG_SN] 3. No Category ID selected. Aborting.`, "color: #orange;"); return; }
    if (!serialInput) { console.error(`%c[DEBUG_SN] 3. Serial input not found for suffix: ${suffix}. Aborting.`, "color: #red;"); return; }
    console.log(`%c[DEBUG_SN] 3. Category ID: ${categoryId}. Proceeding to fetch...`, "color: #007acc;");

    try {
        // Send POST request to the server endpoint
        const response = await fetch("/ajax/next-serial", { // Use root-relative URL
            method: 'POST',
            headers: {
                'Content-Type': 'application/json', 'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') // Get CSRF token
            },
            body: JSON.stringify({ category_id: categoryId })
        });
        console.log(`%c[DEBUG_SN] 4. Fetch response status: ${response.status}`, "color: #007acc;");
        if (!response.ok) { // Handle server errors (e.g., 404, 500)
            const errorData = await response.json(); console.error(`%c[DEBUG_SN] 5. Fetch Error: ${errorData.message || 'Server error'}`, "color: #red;");
            throw new Error(errorData.message || 'Server error');
        }

        const data = await response.json(); // Parse successful JSON response
        console.log(`%c[DEBUG_SN] 5. Fetch Success. Data:`, "color: #007acc;", data);
        if (data.success && data.serial_number) { // If successful and serial provided
            serialInput.value = data.serial_number; // Update the input field
            console.log(`%c[DEBUG_SN] 6. Serial Number updated: ${data.serial_number}`, "color: #28a745; font-weight: bold;");
        } else if (data.success) { // If successful but no serial (e.g., category has no prefix)
            console.log('%c[DEBUG_SN] 6. No new serial number generated (e.g., no prefix).', "color: #007acc;");
        } else { // If server explicitly returned success=false
             console.error(`%c[DEBUG_SN] 6. API returned success=false: ${data.message || 'Failed to generate serial'}`, "color: #red;");
             throw new Error(data.message || 'Failed to generate serial');
        }
    } catch (error) { // Catch fetch errors or errors thrown above
        console.error('%c[DEBUG_SN] 7. CATCH Block Error:', "color: #red;", error);
        Swal.fire('เกิดข้อผิดพลาด', `ไม่สามารถสร้าง Serial Number ได้: ${error.message}`, 'error'); // Show user-friendly error
    }
}


// --- Image Preview Logic (for Add/Edit Forms) ---
// Sets up the preview area when files are selected or pasted
function setupImagePreviews(form) {
    if (!form || !form.id) return; const uniqueSuffix = form.id.split('-').pop(); if (!uniqueSuffix) return;
    const imageInput = document.getElementById(`images-${uniqueSuffix}`); const previewContainer = document.getElementById(`image-previews-${uniqueSuffix}`);
    if (!imageInput || !previewContainer) { console.warn('[DEBUG_IMG] Image input or preview container not found.'); return; }

    // Listener for file selection via browse button
    imageInput.addEventListener('change', function(event) {
        previewContainer.innerHTML = ''; // Clear old previews
        const files = Array.from(event.target.files); if (files.length === 0) return;
        files.forEach(file => { if (!file.type.startsWith('image/')) return; // Skip non-images
            const reader = new FileReader();
            reader.onload = function(e) { // When file is read
                const previewWrapper = document.createElement('div'); previewWrapper.className = 'relative group w-full h-24';
                // Create image preview and remove button
                previewWrapper.innerHTML = `<img src="${e.target.result}" alt="Preview ${file.name}" class="object-cover w-full h-full rounded-lg"> <button type="button" title="Remove ${file.name}" class="remove-preview-btn absolute top-1 right-1 w-5 h-5 bg-red-600 text-white rounded-full flex items-center justify-center text-xs opacity-0 group-hover:opacity-100"><i class="fas fa-times"></i></button>`;
                previewContainer.appendChild(previewWrapper); // Add to container
                previewWrapper._fileData = file; // Store file reference for removal
                // Add listener to the remove button
                previewWrapper.querySelector('.remove-preview-btn').addEventListener('click', () => { previewWrapper.remove(); updateFileList(imageInput, previewWrapper._fileData); });
            }; reader.readAsDataURL(file); // Read the file
        });
    });

    // Listener for pasting images (from clipboard)
    imageInput.addEventListener('paste', (event) => {
        console.log('[DEBUG_IMG] Paste event detected!'); const clipboardData = event.clipboardData || window.clipboardData; if (!clipboardData || !clipboardData.files) return;
        const pastedFiles = clipboardData.files; const dataTransfer = new DataTransfer(); let newImagesPasted = false;
        // Keep existing files
        Array.from(imageInput.files).forEach(file => dataTransfer.items.add(file));
        // Add valid pasted image files
        Array.from(pastedFiles).forEach(file => { if (file.type.startsWith('image/')) { dataTransfer.items.add(file); newImagesPasted = true; } });
        // If new images were pasted, update the input and trigger change event
        if (newImagesPasted) { event.preventDefault(); imageInput.files = dataTransfer.files; imageInput.dispatchEvent(new Event('change', { bubbles: true })); console.log('[DEBUG_IMG] Pasted images added, triggering change event.'); }
    });
}

// Helper function to remove a specific file from a FileList (used by remove button)
function updateFileList(fileInput, fileToRemove) {
    const dataTransfer = new DataTransfer(); // Create a new FileList container
    const files = Array.from(fileInput.files); // Get current files
    // Add all files EXCEPT the one to remove
    files.forEach(file => { if (file !== fileToRemove) dataTransfer.items.add(file); });
    fileInput.files = dataTransfer.files; // Assign the new FileList back to the input
    console.log(`[DEBUG_IMG] FileList updated after removing: ${fileToRemove.name}`);
}

// --- Existing Image Deletion Logic (for Edit Forms) ---
// Adds listeners to toggle deletion markers for already uploaded images
function setupExistingImageDeletion(form) {
    if (!form || !form.id) return; const uniqueSuffix = form.id.split('-').pop(); if (!uniqueSuffix) return;
    const container = document.getElementById(`existing-images-container-${uniqueSuffix}`); if (!container) return;

    // Mark originally checked primary image (for reset)
    container.querySelectorAll('.primary-image-radio').forEach(radio => { if(radio.checked) radio.setAttribute('data-originally-checked', 'true'); else radio.removeAttribute('data-originally-checked'); });

    // Add single event listener to the container for delegation
    container.addEventListener('click', function(event) {
        // Handle Delete/Undo button clicks
        const deleteButton = event.target.closest('.delete-existing-image-btn');
        if (deleteButton) {
            const imageId = deleteButton.dataset.imageId; const imageWrapper = document.getElementById(`image-${imageId}-wrapper`); const hiddenInput = document.getElementById(`delete_image_${imageId}`); // Input holding ID to delete
            if (imageWrapper && hiddenInput) {
                // Toggle the disabled state of the hidden input (marks for deletion)
                if (hiddenInput.disabled) { // If currently NOT marked for deletion
                    hiddenInput.disabled = false; // Enable input (mark for deletion)
                    imageWrapper.style.opacity = '0.4'; // Dim the image
                    deleteButton.innerHTML = '<i class="fas fa-undo text-xs"></i>'; // Change icon to Undo
                    deleteButton.title = 'Cancel Deletion'; deleteButton.classList.replace('bg-red-600', 'bg-yellow-500'); // Change color
                } else { // If currently marked for deletion
                    hiddenInput.disabled = true; // Disable input (cancel deletion)
                    imageWrapper.style.opacity = '1'; // Restore opacity
                    deleteButton.innerHTML = '<i class="fas fa-times text-xs"></i>'; // Change icon back to Delete
                    deleteButton.title = 'Mark for Deletion'; deleteButton.classList.replace('bg-yellow-500', 'bg-red-600'); // Change color back
                }
            }
        }
        // Handle Primary image radio button clicks (clear original marker)
        const primaryRadio = event.target.closest('.primary-image-radio');
        if(primaryRadio) {
            // When a new primary is selected, remove the marker from all
            container.querySelectorAll('.primary-image-radio').forEach(r => r.removeAttribute('data-originally-checked'));
        }
    });
}

// --- MSDS Logic ---
// Opens a SweetAlert modal to manage MSDS details and file upload
async function openMsdsModal(form) {
     if (!form || !form.id) { console.error('[DEBUG_MSDS] Form missing.'); return; } const uniqueSuffix = form.id.split('-').pop(); if (!uniqueSuffix) { console.error('[DEBUG_MSDS] Cannot get suffix'); return; }
     // Get relevant elements from the main form
     const detailsHiddenInput = document.getElementById(`msds_details_hidden-${uniqueSuffix}`); const fileStatusElement = document.getElementById(`msds-file-status-${uniqueSuffix}`);
     if (!detailsHiddenInput || !fileStatusElement) { console.error('[DEBUG_MSDS] MSDS elements not found'); Swal.fire('Error', 'UI component missing.', 'error'); return; }

    // Get current details and file status to pass to the modal content fetch
    const existingFileLink = fileStatusElement.querySelector('a'); let currentDetails = detailsHiddenInput.value;
    let currentFileStatusHtml = existingFileLink ? `ไฟล์ปัจจุบัน: <a href='${existingFileLink.href}' target='_blank' class='text-blue-600 hover:underline'>${existingFileLink.textContent}</a>` : 'ยังไม่มีไฟล์';
    // Check if a new file was already staged dynamically (before saving)
    const dynamicFileInput = form.querySelector('input[name="msds_file"][data-dynamic="true"]');
    if (dynamicFileInput && dynamicFileInput.files.length > 0) currentFileStatusHtml = `ไฟล์ใหม่: ${dynamicFileInput.files[0].name}`;

    try {
        // Fetch the HTML content for the SweetAlert modal
        const response = await fetch(`/ajax/equipment/msds-form?details=${encodeURIComponent(currentDetails)}&fileStatus=${encodeURIComponent(currentFileStatusHtml)}`);
        if (!response.ok) throw new Error(`Failed to load MSDS form (Status: ${response.status})`);
        const formHtml = await response.text();

        // Show SweetAlert with the fetched HTML
        const { value: data, isConfirmed } = await Swal.fire({
            title: 'จัดการข้อมูล MSDS', html: formHtml, focusConfirm: false, showCancelButton: true, confirmButtonText: 'บันทึก', cancelButtonText: 'ยกเลิก', allowOutsideClick: false, width: '600px',
            didOpen: () => { // Populate modal with current data
                const swalTextarea = Swal.getPopup()?.querySelector('#swal-msds-details'); if (swalTextarea) swalTextarea.value = currentDetails;
                const swalFileInput = Swal.getPopup()?.querySelector('#swal-msds-file'); const swalFileStatus = Swal.getPopup()?.querySelector('#swal-msds-current-file-status');
                if (swalFileStatus) swalFileStatus.innerHTML = currentFileStatusHtml; // Display current file status
                // Update status text when a new file is chosen in Swal modal
                if(swalFileInput && swalFileStatus) swalFileInput.addEventListener('change', (e) => { swalFileStatus.innerHTML = e.target.files.length > 0 ? `ไฟล์ใหม่: ${e.target.files[0].name}` : currentFileStatusHtml; });
            },
            preConfirm: () => { // Get values from Swal modal before closing
                const p = Swal.getPopup(); return { details: p?.querySelector('#swal-msds-details')?.value || '', file: p?.querySelector('#swal-msds-file')?.files[0] };
            }
        });

        if (isConfirmed) { // If user confirmed
            detailsHiddenInput.value = data.details; // Update hidden input in main form
            if(dynamicFileInput) dynamicFileInput.remove(); // Remove previous dynamically added file input if exists

            let newFileStatusHtml = '';
            if (data.file) { // If a new file was selected in Swal
                 // Create a new hidden file input and attach the selected file
                 const newFileInput = document.createElement('input'); newFileInput.type = 'file'; newFileInput.name = 'msds_file'; newFileInput.style.display = 'none'; newFileInput.setAttribute('data-dynamic', 'true');
                 const dataTransfer = new DataTransfer(); dataTransfer.items.add(data.file); newFileInput.files = dataTransfer.files;
                 form.appendChild(newFileInput); // Add to main form
                 newFileStatusHtml = `ไฟล์ใหม่: ${data.file.name}`; // Update status text
            } else {
                 // If no new file, keep the existing status text
                 newFileStatusHtml = existingFileLink ? `ไฟล์ปัจจุบัน: <a href='${existingFileLink.href}' target='_blank' class='text-blue-600 hover:underline'>${existingFileLink.textContent}</a>` : 'ยังไม่มีไฟล์';
            }
            fileStatusElement.innerHTML = newFileStatusHtml; // Update status element in main form
            console.log('[DEBUG_MSDS] MSDS data updated locally.');
        }
    } catch (error) { console.error('MSDS modal error:', error); Swal.fire('เกิดข้อผิดพลาด', `ไม่สามารถโหลดฟอร์ม MSDS ได้ (${error.message})`, 'error'); }
}

// Handles showing/hiding the MSDS management button based on checkbox state
function handleMsdsCheckboxChange(event) {
    const cb = event.target;
    const suffix = cb.id.split('-').pop();
    const container = document.getElementById(`msds-management-container-${suffix}`);
    if (container) {
        container.style.display = cb.checked ? 'block' : 'none';
        console.log(`[DEBUG_MSDS] Container visibility toggled for suffix: ${suffix}`);
    } else {
        console.warn(`[DEBUG_MSDS] Container not found for suffix: ${suffix}`);
    }
}

// --- Stepper Logic ---
// Initializes the stepper UI and navigation for multi-step forms
function initializeStepper(form, uniqueSuffix) {
    let currentStep = 1; // Start at step 1
    const totalSteps = 3; // Total number of steps

    // Find all necessary Stepper Elements
    const prevBtn = document.getElementById(`prev-step-btn-${uniqueSuffix}`);
    const nextBtn = document.getElementById(`next-step-btn-${uniqueSuffix}`);
    const submitBtn = document.getElementById(`submit-btn-${uniqueSuffix}`);
    const contentScroller = document.getElementById(`form-stepper-content-${uniqueSuffix}`);
    const generateSerialBtn = document.getElementById(`generate-serial-btn-${uniqueSuffix}`); // Optional button
    const categorySelect = document.getElementById(`category_id-${uniqueSuffix}`); // For auto-serial gen
    const stepIndicators = [ document.getElementById(`step-indicator-1-${uniqueSuffix}`), document.getElementById(`step-indicator-2-${uniqueSuffix}`), document.getElementById(`step-indicator-3-${uniqueSuffix}`) ];
    const stepPanels = [ document.getElementById(`step-1-panel-${uniqueSuffix}`), document.getElementById(`step-2-panel-${uniqueSuffix}`), document.getElementById(`step-3-panel-${uniqueSuffix}`) ];

    // Function to update the UI based on the current step
    function updateStepperUI(targetStep) {
        console.log(`%c[STEPPER DEBUG] updateStepperUI called. Target step: ${targetStep}`, "color: #purple;");
        currentStep = targetStep;

        // Show/Hide step panels
        stepPanels.forEach((panel, index) => { if (panel) panel.classList.toggle('hidden', index + 1 !== currentStep); });
        // Scroll content area to top
        if (contentScroller) contentScroller.scrollTop = 0;

        // Update step indicators (active, completed, pending)
        stepIndicators.forEach((indicator, index) => {
            if (indicator) {
                const stepNum = index + 1; const textEl = indicator.querySelector('.stepper-text'); const checkEl = indicator.querySelector('.stepper-check'); const iconBg = indicator.querySelector('span:first-child');
                // Reset classes and icons
                indicator.classList.remove('active', 'completed', 'pending'); indicator.classList.add('pending');
                if(textEl) textEl.classList.remove('hidden'); if(checkEl) checkEl.classList.add('hidden'); if(iconBg) iconBg.className = 'flex items-center justify-center w-8 h-8 bg-gray-100 rounded-full shrink-0';
                // Apply 'completed' style
                if (stepNum < currentStep) { indicator.classList.remove('pending'); indicator.classList.add('completed'); if(textEl) textEl.classList.add('hidden'); if(checkEl) checkEl.classList.remove('hidden'); if(iconBg) iconBg.className = 'flex items-center justify-center w-8 h-8 bg-green-600 text-white rounded-full shrink-0'; }
                // Apply 'active' style
                else if (stepNum === currentStep) { indicator.classList.remove('pending'); indicator.classList.add('active'); if(iconBg) iconBg.className = 'flex items-center justify-center w-8 h-8 bg-blue-100 rounded-full shrink-0'; }
            }
        });

        // Show/Hide navigation buttons
        if(prevBtn) prevBtn.classList.toggle('hidden', currentStep === 1); // Hide Prev on step 1
        if(nextBtn) nextBtn.classList.toggle('hidden', currentStep === totalSteps); // Hide Next on last step
        if(submitBtn) submitBtn.classList.toggle('hidden', currentStep !== totalSteps); // Show Submit only on last step
    }

    // Expose the update function on the form element for external calls (e.g., reset)
    form.updateStepperUI = updateStepperUI;

    // Check if all essential stepper elements exist before attaching listeners
    if (prevBtn && nextBtn && submitBtn && contentScroller && !stepIndicators.includes(null) && !stepPanels.includes(null)) {
        // Attach listeners for Next and Previous buttons
        nextBtn.addEventListener('click', () => { if (currentStep < totalSteps) updateStepperUI(currentStep + 1); });
        prevBtn.addEventListener('click', () => { if (currentStep > 1) updateStepperUI(currentStep - 1); });
        console.log(`%c[STEPPER DEBUG] Core stepper nav listeners attached.`, "color: #28a745;");

        // Attach optional listeners if elements exist
        if (generateSerialBtn) { generateSerialBtn.addEventListener('click', () => generateSerialNumber(uniqueSuffix)); console.log(`%c[STEPPER DEBUG] 'Generate Serial' listener attached.`, "color: #28a745;"); }
        if (categorySelect) { categorySelect.addEventListener('change', () => generateSerialNumber(uniqueSuffix)); console.log(`%c[STEPPER DEBUG] 'Category Change' listener attached.`, "color: #28a745;"); }

        // Set the initial UI state to Step 1
        updateStepperUI(1);
        console.log(`%c[STEPPER DEBUG] Initial UI set to step 1.`, "color: #28a745;");
    } else {
        console.error(`%c[STEPPER DEBUG] CRITICAL: Missing core stepper elements. Navigation might not work.`, "color: #red; font-weight: bold;");
    }
}


// --- Main Function to Attach All Listeners to a Form ---
// This function is called after a form (add or edit) is loaded into the DOM
function attachFormEventListeners(form) {
    if (!form) { console.error('attachFormEventListeners called with null form.'); return; }
    console.log(`[DEBUG] Attaching listeners to form: #${form.id}`);

    // Disable default browser validation (we handle it on submit)
    form.noValidate = true;

    // Remove previous submit listener to prevent duplicates, then add the current one
    form.removeEventListener('submit', handleFormSubmit);
    form.addEventListener('submit', handleFormSubmit);

    // Setup image previews and deletion logic
    setupImagePreviews(form);
    setupExistingImageDeletion(form);

    // Setup MSDS checkbox toggle and modal button listener
    const uniqueSuffix = form.id.split('-').pop();
    if (!uniqueSuffix) { console.warn('Cannot get unique suffix from form ID.'); return; } // Need suffix for element IDs
    const msdsCheckbox = document.getElementById(`has_msds_checkbox-${uniqueSuffix}`);
    const msdsContainer = document.getElementById(`msds-management-container-${uniqueSuffix}`);
    const manageMsdsBtn = document.getElementById(`manage-msds-btn-${uniqueSuffix}`);
    // Attach listener for MSDS checkbox change
    if (msdsCheckbox && msdsContainer) { msdsCheckbox.removeEventListener('change', handleMsdsCheckboxChange); msdsCheckbox.addEventListener('change', handleMsdsCheckboxChange); }
    // Attach listener for "Manage MSDS" button (use cloneNode trick to remove old listeners)
    if (manageMsdsBtn) { const newBtn = manageMsdsBtn.cloneNode(true); manageMsdsBtn.parentNode.replaceChild(newBtn, manageMsdsBtn); newBtn.addEventListener('click', () => openMsdsModal(form)); }

    // Add listener for the "Cancel" button within the form
    form.addEventListener('click', (event) => {
        const closeButton = event.target.closest('.close-modal-btn');
        if (closeButton) {
            const modal = form.closest('.fixed.inset-0.z-50'); // Find parent modal
            if (modal?.id) closeModal(modal.id); // Close the modal
        }
    });

    // Initialize stepper logic *if* this form uses a stepper (check for stepper buttons)
    const nextStepButton = form.querySelector(`#next-step-btn-${uniqueSuffix}`);
    if (nextStepButton) {
        initializeStepper(form, uniqueSuffix);
    }
    console.log(`[DEBUG] All listeners attached for form: #${form.id}`);
}


// --- QR Code Modal Functionality ---
// Function to generate QR/Barcode and show the modal
// ✅✅✅ START: MODIFIED QR CODE FUNCTION ✅✅✅
// (Parameter 1 changed from equipmentId to valueToEncode)
window.openQrCodeModal = function(valueToEncode, equipmentName) { // Make explicitly global
    console.log(`[DEBUG_QR] Opening QR Modal for Value: ${valueToEncode}, Name: ${equipmentName}`);
    const modal = document.getElementById('qr-code-modal');
    // Get elements using IDs from qr-code-modal.blade.php
    const nameEl = document.getElementById('qr-modal-name');
    const qrContainer = document.getElementById('qr-code-container'); // DIV container
    const barcodeCanvas = document.getElementById('barcode-container'); // CANVAS container
    const barcodeNameEl = document.getElementById('qr-barcode-name'); // P tag for name below barcode

    // Check if all required elements are found
    if (!modal || !nameEl || !qrContainer || !barcodeCanvas || !barcodeNameEl) {
        console.error('One or more elements for QR code modal are missing!', { modal, nameEl, qrContainer, barcodeCanvas, barcodeNameEl });
        Swal.fire('Error', 'UI Component Missing (QR Modal)', 'error');
        return;
    }

    // Populate the equipment name (both top heading and below barcode)
    nameEl.textContent = equipmentName || 'N/A';
    barcodeNameEl.textContent = equipmentName || ''; // Set name below barcode

    // --- Generate QR Code ---
    qrContainer.innerHTML = ''; // Clear previous QR code
    const qrCanvas = document.createElement('canvas'); // Create canvas dynamically for QRious
    try {
        if (typeof QRious !== 'undefined') { // Check if QRious library is loaded
            // ✅ MODIFIED: Use valueToEncode
            new QRious({ element: qrCanvas, value: String(valueToEncode), size: 180, level: 'H' });
            qrContainer.appendChild(qrCanvas); // Add generated canvas to the container
            console.log(`[DEBUG_QR] QRious generated for value: ${valueToEncode}`);
        } else {
            console.error('QRious library is not loaded!');
            qrContainer.innerHTML = '<p class="text-red-500">QR Error</p>'; // Show error in container
        }
    } catch (error) {
        console.error('Error generating QR code:', error);
        qrContainer.innerHTML = '<p class="text-red-500">QR Gen Error</p>'; // Show error
    }

    // --- Generate Barcode ---
    try {
        if (typeof JsBarcode !== 'undefined') { // Check if JsBarcode library is loaded
            // ✅ MODIFIED: Use valueToEncode
            JsBarcode(barcodeCanvas, String(valueToEncode), {
                format: "CODE128",
                lineColor: "#000",
                width: 2,          // Bar width
                height: 60,        // Bar height
                displayValue: false, // DO NOT display the value (SN or ID) automatically
                margin: 5          // Add some margin around the barcode
                // text option removed as we are using a separate <p> tag now
            });
            console.log(`[DEBUG_QR] JsBarcode generated for value: ${valueToEncode} (display value hidden)`);
        } else {
            console.error('JsBarcode library is not loaded!');
            // Draw error message on the canvas if library failed
            const ctx = barcodeCanvas.getContext('2d');
            ctx.clearRect(0, 0, barcodeCanvas.width, barcodeCanvas.height);
            ctx.font = "16px Arial"; ctx.fillStyle = "red"; ctx.textAlign = "center";
            ctx.fillText("Barcode Lib Error", barcodeCanvas.width / 2, 20);
        }
    } catch (error) { // Catch errors during barcode generation
        console.error('Error generating Barcode:', error);
        try {
            // Attempt to draw error on canvas
            const ctx = barcodeCanvas.getContext('2d');
            ctx.clearRect(0, 0, barcodeCanvas.width, barcodeCanvas.height);
            ctx.font = "16px Arial"; ctx.fillStyle = "red"; ctx.textAlign = "center";
            ctx.fillText("Barcode Gen Error", barcodeCanvas.width / 2, 20);
        } catch(canvasError) { console.error('Could not display barcode error on canvas:', canvasError); }
    }
    // ✅✅✅ END: MODIFIED QR CODE FUNCTION ✅✅✅

    // --- Print Button Logic ---
    // The print logic is now handled by the printQrModalContent() function
    // defined in qr-code-modal.blade.php's @push('scripts') section.
    // No need to attach listener here anymore.

    // Show the populated modal
    showModal('qr-code-modal');
}


// --- Initialize listeners on page load ---
// This runs once when the page is initially loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('[DEBUG] DOMContentLoaded');
    // Use event delegation on a stable parent element (e.g., body or a main container)
    // Adjust '#equipment-page' if your main content area has a different ID
    const pageContainer = document.getElementById('equipment-page') || document.body;

    pageContainer.addEventListener('click', (event) => {
        // Check if the clicked element or its parent triggers the Edit Modal
        const editTrigger = event.target.closest('a[onclick*="showEditModal"], button[onclick*="showEditModal"]');
        if(editTrigger) {
            if (editTrigger.tagName === 'A') event.preventDefault(); // Prevent default link behavior
            // Extract ID from the onclick attribute (simple regex)
            const match = editTrigger.getAttribute('onclick')?.match(/showEditModal\(\s*(\d+)\s*\)/);
            if (match?.[1]) showEditModal(parseInt(match[1], 10)); // Call showEditModal with the ID
            return; // Stop processing this click event
        }

        // Check if the clicked element or its parent triggers the Details Modal
        const detailsTrigger = event.target.closest('a[onclick*="showDetailsModal"], button[onclick*="showDetailsModal"]');
        if (detailsTrigger) {
             if (detailsTrigger.tagName === 'A') event.preventDefault();
            // Extract ID
            const match = detailsTrigger.getAttribute('onclick')?.match(/showDetailsModal\(\s*(\d+)\s*\)/);
            if (match?.[1]) showDetailsModal(parseInt(match[1], 10)); // Call showDetailsModal
            return;
        }

        // Check if the clicked element or its parent is a Delete Button
        const deleteButton = event.target.closest('.delete-button');
        if (deleteButton) {
            event.preventDefault(); // Prevent default button/link behavior
            const form = deleteButton.closest('form.delete-form'); // Find parent form if exists
            const name = deleteButton.dataset.equipmentName || 'รายการนี้'; // Get name from data attribute
            // Get action URL from form or button data attribute
            const actionUrl = form ? form.action : deleteButton.dataset.actionUrl;
            // Extract ID from the URL
            const match = actionUrl?.match(/equipment\/(\d+)/);
            if (match?.[1]) {
                deleteEquipment(parseInt(match[1], 10), name); // Call delete function
            } else {
                 console.error("Could not extract ID for deletion from:", actionUrl);
                Swal.fire('Error', 'Cannot find item ID.', 'error');
            }
            return;
        }
    });

    // Add listener for the main "Add Equipment" button
    const addEquipmentBtn = document.getElementById('add-equipment-btn');
    if (addEquipmentBtn) {
        addEquipmentBtn.addEventListener('click', showAddModal);
    } else {
        console.warn("Button with ID 'add-equipment-btn' not found.");
    }
});


// --- Form Submission Logic ---
// Handles submitting both Add and Edit forms via AJAX
async function handleFormSubmit(event) {
    event.preventDefault(); // Prevent default browser submission
    console.log('%c[DEBUG] 1. Submit Fired!', 'color: blue; font-weight: bold;');
    const form = event.target.closest('form'); if (!form) return; // Ensure we have a form

    clearServerErrors(form); // Clear previous validation errors
    // Find dynamically added MSDS file input (if any) to handle it later
    const dynamicFileInput = form.querySelector('input[name="msds_file"][data-dynamic="true"]');
    const formData = new FormData(form); // Create FormData from the form

    // Ensure _method is included for PATCH requests (Laravel spoofing)
    const methodInput = form.querySelector('input[name="_method"]');
    if (methodInput && methodInput.value.toUpperCase() === 'PATCH' && !formData.has('_method')) {
        formData.append('_method', 'PATCH');
    }

    // Find the submit button (visible one if stepper, or the only one)
    const submitButton = form.querySelector('button[type="submit"]:not(.hidden)') || form.querySelector('button[type="submit"]');
    const originalButtonHtml = submitButton ? submitButton.innerHTML : ''; // Store original button text/icon

    // Disable button and show loading state
    if (submitButton) { submitButton.disabled = true; submitButton.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>Processing...`; }
    console.log('[DEBUG] 2. FormData prepared.');

    try {
        console.log(`[DEBUG] 3. Sending AJAX POST to: ${form.action}`);
        // Send the request
        const response = await fetch(form.action, {
            method: 'POST', // Always POST, Laravel handles PATCH/PUT via _method field
            body: formData,
            headers: {
                'Accept': 'application/json', // Expect JSON response
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') // Add CSRF token
            }
        });
        console.log(`[DEBUG] 4. Response Status: ${response.status}`);
        const result = await response.json(); // Parse JSON response

        if (!response.ok) { // Check if response status is not 2xx
            console.error(`[DEBUG] 5. Request Failed`, result);
            if (response.status === 422 && result.errors) { // Handle validation errors (422)
                displayValidationErrors(form, result.errors, form.id.split('-').pop());
                // Don't throw generic error, displayValidationErrors shows specific warnings
                throw new Error('Validation Error'); // Throw specific error to stop processing
            } else { // Handle other errors (e.g., 500, 403)
                throw new Error(result.message || `HTTP error ${response.status}`);
            }
        }

        // --- Success ---
        console.log('[DEBUG] 5. Request Success!', result);
        const modal = form.closest('.fixed.inset-0.z-50'); // Find the parent modal
        if (modal?.id) closeModal(modal.id); // Close the modal on success

        // Show success message
        await Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: result.message || 'บันทึกข้อมูลเรียบร้อยแล้ว', timer: 2000, showConfirmButton: false });
        window.location.reload(); // Reload the page to show updated data

    } catch (error) { // Catch errors from fetch or thrown above
        console.error('[DEBUG] 6. Catch Block Error:', error);
        // Show generic error only if it wasn't a validation error (already handled)
        if (!error.message || !error.message.includes('Validation')) {
            Swal.fire('เกิดข้อผิดพลาด!', error.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
        }
    } finally { // Runs regardless of success or error
        // Re-enable submit button and restore original content
        if (submitButton) { submitButton.disabled = false; submitButton.innerHTML = originalButtonHtml; }
        // Remove dynamically added file input after submission attempt
        if (dynamicFileInput) dynamicFileInput.remove();
        console.log('[DEBUG] 7. Final cleanup.');
    }
}

// --- Validation Error Display Logic ---
// Displays validation errors received from the server on the form
function displayValidationErrors(form, errors, uniqueSuffix) {
     if (!form || !errors) return; console.log('[DEBUG_VALIDATION] Displaying Errors:', errors);
     const suffix = uniqueSuffix || form.id.split('-').pop(); // Get form suffix ('new' or ID)
     if (!suffix) { console.error("[DEBUG_VALIDATION] Cannot determine form suffix."); return; }

    let firstErrorStep = null; // Track the first step containing an error

    // Loop through each field that has errors
    for (const field in errors) {
        let baseField = field.split('.')[0]; // Handle array fields like images.*
        const inputId = `${baseField.replace('[]','')}-${suffix}`; // Construct potential ID
        // Find the input element by ID or name
        let input = document.getElementById(inputId) || form.querySelector(`[name="${baseField}"], [name="${baseField}[]"]`);
        const errorMsg = errors[field][0]; // Get the first error message for the field

        if (input) {
            input.classList.add('is-invalid'); // Add error styling to the input
            const parent = input.closest('.form-group') || input.parentNode; // Find suitable parent
            // Find or create the error message container
            let errorDiv = parent.querySelector('.invalid-feedback');
            if (!errorDiv) { // If it doesn't exist, create and append it
                errorDiv = document.createElement('div'); errorDiv.className = 'invalid-feedback';
                // Place error message appropriately (after flex container if needed)
                const inputParent = input.parentElement;
                if(inputParent && (inputParent.classList.contains('flex') || inputParent.classList.contains('relative'))) {
                    inputParent.after(errorDiv); // Place after the wrapper div
                } else {
                    input.after(errorDiv); // Place directly after the input
                }
            }
            errorDiv.textContent = errorMsg; // Set the error message text

            // Determine which step the error belongs to (for stepper forms)
            if (firstErrorStep === null) {
                const stepPanel = input.closest('.step-panel');
                if (stepPanel && stepPanel.id) {
                    const stepMatch = stepPanel.id.match(/step-(\d+)-panel/); // Extract step number
                    if (stepMatch && stepMatch[1]) {
                        firstErrorStep = parseInt(stepMatch[1], 10);
                    }
                }
            }
        } else {
            // Log warning if input couldn't be found, display in general error area
            console.warn(`[VALIDATION] Input not found for field: ${field} (Tried ID: ${inputId})`);
            const generalArea = form.querySelector('.general-errors');
            if (generalArea) generalArea.innerHTML += `<p>${errorMsg}</p>`;
        }
    }

    // If an error was found in a specific step, switch to that step and show warning
    if (firstErrorStep !== null) {
        console.log(`%c[VALIDATION] First error found in Step ${firstErrorStep}. Switching UI...`, "color: #orange;");
        if (typeof form.updateStepperUI === 'function') {
            form.updateStepperUI(firstErrorStep); // Call stepper update function
        }
        Swal.fire('ข้อมูลไม่ครบ', `กรุณาตรวจสอบข้อมูลในขั้นตอนที่ ${firstErrorStep}`, 'warning');
    } else if (Object.keys(errors).length > 0) { // If errors exist but step couldn't be determined
        // Show generic warning (e.g., for general errors)
        Swal.fire('ข้อมูลไม่ครบ', 'กรุณาตรวจสอบข้อผิดพลาดในฟอร์ม', 'warning');
    }
}


// --- DELETE Function ---
// Handles the confirmation and AJAX request for deleting equipment
async function deleteEquipment(id, equipmentName) {
     console.log(`%c[DELETE] Request to delete ID: ${id} (${equipmentName})`, 'color: red;');
    // Show confirmation dialog
    const { isConfirmed } = await Swal.fire({
        title: 'ยืนยันการลบ?',
        html: `คุณต้องการลบ <b>${equipmentName}</b> ใช่หรือไม่?<br><span class='text-sm text-red-500'>การดำเนินการนี้ไม่สามารถย้อนกลับได้!</span>`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'ใช่, ลบเลย!', cancelButtonText: 'ยกเลิก'
    });

    if (!isConfirmed) return; // Abort if user cancels

    // Show loading state while processing deletion
    Swal.fire({ title: 'กำลังลบ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        // Send DELETE request to the server
        const response = await fetch(`/equipment/${id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json', // Expect JSON response
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') // Add CSRF token
            }
        });
        console.log(`[DELETE] Response Status: ${response.status}`);
        const result = await response.json(); // Parse response

        if (!response.ok) { // Handle server errors
            console.error(`[DELETE] Deletion Failed`, result);
            throw new Error(result.message || `HTTP error ${response.status}`);
        }

        // --- Success ---
        console.log(`[DELETE] Deletion Success`, result);
        // Show success message briefly
        await Swal.fire({ icon: 'success', title: 'ลบสำเร็จ!', text: result.message || 'ลบข้อมูลเรียบร้อยแล้ว', timer: 2000, showConfirmButton: false });
        window.location.reload(); // Reload the page to reflect the deletion

    } catch (error) { // Catch errors from fetch or thrown above
        console.error(`[DELETE] Error during deletion:`, error);
        Swal.fire('เกิดข้อผิดพลาด!', error.message || 'ไม่สามารถลบข้อมูลได้', 'error'); // Show error to user
    }
}