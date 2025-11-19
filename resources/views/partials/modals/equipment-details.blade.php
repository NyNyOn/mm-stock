<div id="equipment-details-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 animate-fade-in-fast">
    {{-- Modal is wider (max-w-5xl), Body is 50/50 grid --}}
    <div class="relative w-full max-w-5xl max-h-[90vh] bg-white rounded-2xl shadow-xl soft-card animate-slide-up-soft flex flex-col" role="dialog" aria-modal="true">

        {{-- Header --}}
        <div class="flex items-center justify-between p-5 border-b flex-shrink-0">
            <h3 class="text-xl font-semibold text-gray-800">
                <i class="mr-2 fas fa-info-circle text-blue-500"></i>รายละเอียดอุปกรณ์
            </h3>
            <button onclick="closeModal('equipment-details-modal')" class="text-gray-400 hover:text-gray-600">
                <span class="text-2xl">&times;</span>
            </button>
        </div>

        {{-- Body: Use flex-grow for scrolling area --}}
        <div class="flex-grow overflow-y-auto scrollbar-soft">
            {{-- Loading State --}}
            <div id="details-loading" class="p-8 text-center">
                <i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i>
                <p class="mt-3 text-gray-600">กำลังโหลดข้อมูล...</p>
            </div>
            
            {{-- Error State --}}
            <div id="details-error-message" class="hidden p-8 text-center">
                <i class="fas fa-times-circle text-4xl text-red-500"></i>
                <p class="mt-3 text-red-600">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>
            </div>

            {{-- Content Body (Main container, hidden by default) --}}
            {{-- Grid 50/50: md:grid-cols-2 --}}
            <div id="details-body" class="hidden grid grid-cols-1 md:grid-cols-2 gap-0">
                
                {{-- Left Side: Image Gallery --}}
                <div class="p-6 border-r border-gray-100 flex flex-col">
                    <div class="flex-grow flex items-center justify-center mb-4">
                         <img id="details-primary-image" src="https://placehold.co/600x400/e2e8f0/64748b?text=Loading..." alt="Equipment Image" class="w-full max-w-md h-auto object-contain rounded-lg shadow-md max-h-[300px]">
                    </div>
                    {{-- Thumbnail Grid --}}
                    <div id="details-gallery-thumbnails" class="grid grid-cols-6 gap-2 flex-shrink-0">
                        {{-- Thumbnails will be populated by JS --}}
                        <div class="col-span-6 text-center text-gray-500 text-xs py-2">Loading thumbnails...</div>
                    </div>
                </div>
                
                {{-- Right Side: Details --}}
                <div class="p-6">
                    {{-- Name and Status --}}
                    <div class="mb-5">
                        <h4 id="details-name" class="text-2xl font-bold text-gray-800 break-words">...</h4>
                        <div id="details-status" class="mt-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Loading...</span>
                        </div>
                    </div>

                    {{-- Tabs --}}
                    <div class="mb-4 border-b border-gray-200">
                        <nav class="flex -mb-px space-x-4" aria-label="Tabs">
                            {{-- Tab 1: Main Info (Default Active) --}}
                            <button onclick="switchDetailsTab(this, 'details-tab-main')" 
                                    class="details-tab-btn whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm text-blue-600 border-blue-500" 
                                    aria-current="page">
                                <i class="fas fa-info-circle mr-1.5"></i>ข้อมูลหลัก
                            </button>
                            {{-- Tab 2: Transaction History --}}
                            <button onclick="switchDetailsTab(this, 'details-tab-history')" 
                                    class="details-tab-btn whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 border-transparent">
                                <i class="fas fa-history mr-1.5"></i>ประวัติ
                            </button>
                            {{-- Tab 3: MSDS (If applicable, shown by JS) --}}
                            <button id="details-msds-tab" onclick="switchDetailsTab(this, 'details-tab-msds')" 
                                    class="details-tab-btn hidden whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 border-transparent">
                                <i class="fas fa-file-medical-alt mr-1.5 text-red-500"></i>MSDS
                            </button>
                        </nav>
                    </div>

                    {{-- Tab Panels Wrapper --}}
                    <div>
                        {{-- Panel 1: Main Info (Default Active) --}}
                        <div id="details-tab-main" class="details-tab-panel space-y-4">
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div class="font-medium text-gray-500">จำนวนคงเหลือ:</div>
                                <div><span id="details-quantity" class="font-bold text-lg text-blue-700">...</span></div>
                                
                                <div class="font-medium text-gray-500">Min Stock:</div>
                                <div id="details-min-stock" class="text-gray-800">...</div>

                                <div class="font-medium text-gray-500">Max Stock:</div>
                                <div id="details-max-stock" class="text-gray-800">...</div>

                                <div class="font-medium text-gray-500">ประเภทการเบิก:</div>
                                <div id="details-withdrawal-type" class="text-gray-800">...</div>

                                <div class="font-medium text-gray-500">หมวดหมู่:</div>
                                <div id="details-category" class="text-gray-800">...</div>

                                <div class="font-medium text-gray-500">สถานที่จัดเก็บ:</div>
                                <div id="details-location" class="text-gray-800">...</div>
                            </div>
                            <hr class="my-3">
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div class="font-medium text-gray-500">Model:</div>
                                <div id="details-model" class="text-gray-800">...</div>
                                
                                <div class="font-medium text-gray-500">Part No:</div>
                                <div id="details-part-no" class="text-gray-800">...</div>

                                <div class="font-medium text-gray-500">Serial No:</div>
                                <div id="details-serial" class="text-gray-800 font-mono">...</div>

                                <div class="font-medium text-gray-500">Supplier:</div>
                                <div id="details-supplier" class="text-gray-800">...</div>

                                <div class="font-medium text-gray-500">Price:</div>
                                <div id="details-price" class="text-gray-800">...</div>

                                <div class="font-medium text-gray-500">Purchase Date:</div>
                                <div id="details-purchase-date" class="text-gray-800">...</div>

                                <div class="font-medium text-gray-500">Warranty Exp:</div>
                                <div id="details-warranty-date" class="text-gray-800">...</div>

                                <div class="font-medium text-gray-500">Added:</div>
                                <div id="details-created-at" class="text-gray-800">...</div>

                                <div class="font-medium text-gray-500">Updated:</div>
                                <div id="details-updated-at" class="text-gray-800">...</div>
                            </div>
                            <hr class="my-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Notes:</label>
                                <p id="details-notes" class="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 p-3 rounded-lg min-h-[50px]">...</p>
                            </div>
                        </div>

                        {{-- Panel 2: History (Hidden) --}}
                        <div id="details-tab-history" class="details-tab-panel hidden">
                            <p class="text-sm font-medium text-gray-600 mb-2">ประวัติ 5 รายการล่าสุด</p>
                            <div id="details-transactions" class="space-y-2 max-h-[300px] overflow-y-auto scrollbar-soft pr-2">
                                {{-- History items will be populated by JS --}}
                                <p class="py-4 px-3 text-xs text-center text-gray-500">Loading history...</p>
                            </div>
                        </div>
                        
                        {{-- Panel 3: MSDS (Hidden) --}}
                        <div id="details-tab-msds" class="details-tab-panel hidden space-y-3">
                             <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">รายละเอียด (Details):</label>
                                <p id="details-msds-details" class="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 p-3 rounded-lg min-h-[50px]">...</p>
                            </div>
                            <div>
                                <a href="#" id="details-msds-file" target="_blank" class="hidden items-center px-4 py-2 text-sm font-medium text-white transition-all bg-gradient-to-br from-red-500 to-red-600 rounded-lg hover:shadow-lg button-soft gentle-shadow">
                                    <i class="fas fa-file-pdf mr-2"></i>ดาวน์โหลดไฟล์ MSDS
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div id="details-footer" class="flex items-center justify-end p-5 border-t bg-gray-50 rounded-b-2xl flex-shrink-0">
            <button onclick="closeModal('equipment-details-modal')" type="button" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-blue-300 focus:outline-none">
                ปิด
            </button>
            
            {{-- This button will trigger the QR Code modal --}}
            <button id="details-print-btn" type="button" class="ml-3 px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-br from-gray-700 to-gray-800 rounded-lg hover:shadow-lg transition-all button-soft gentle-shadow">
                <i class="fas fa-print mr-2"></i>พิมพ์ป้าย QR/Barcode
            </button>

            @can('equipment:manage')
            <button id="details-edit-btn" type="button" class="ml-3 px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-br from-blue-400 to-purple-500 rounded-lg hover:shadow-lg transition-all button-soft gentle-shadow">
                <i class="fas fa-edit mr-2"></i>แก้ไข
            </button>
            @endcan
        </div>

    </div>
</div>

<script>
    // Make sure this script block runs only once
    if (typeof window.initDetailsModal === 'undefined') {
        window.initDetailsModal = true; // Set flag

        // Function to switch tabs
        function switchDetailsTab(selectedBtn, targetPanelId) {
            // Get all tab buttons and panels
            document.querySelectorAll('.details-tab-btn').forEach(btn => {
                btn.classList.remove('text-blue-600', 'border-blue-500');
                btn.classList.add('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');
                btn.removeAttribute('aria-current');
            });
            document.querySelectorAll('.details-tab-panel').forEach(panel => {
                panel.classList.add('hidden');
            });

            // Activate the selected tab button
            selectedBtn.classList.add('text-blue-600', 'border-blue-500');
            selectedBtn.classList.remove('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'border-transparent');
            selectedBtn.setAttribute('aria-current', 'page');

            // Show the target panel
            const targetPanel = document.getElementById(targetPanelId);
            if (targetPanel) {
                targetPanel.classList.remove('hidden');
            }
        }
        
        // Function to update the main image display
        function updatePrimaryImage(url) {
            const primaryImageDisplay = document.getElementById('details-primary-image');
            if (primaryImageDisplay) {
                primaryImageDisplay.src = url;
            }
        }
        // Expose to global scope so img onclick can find it
        window.updatePrimaryImage = updatePrimaryImage;


        // Function to populate data (called by showDetailsModal in equipment.js)
        // This function might be defined inside showDetailsModal itself, 
        // but we need the button listeners here.
        document.addEventListener('DOMContentLoaded', () => {
            const editBtn = document.getElementById('details-edit-btn');
            const printBtn = document.getElementById('details-print-btn');

            // Add Click Listener for Edit Button
             if(editBtn){
                 editBtn.addEventListener('click', () => {
                     const equipmentId = editBtn.getAttribute('data-equipment-id');
                     if (equipmentId) {
                         closeModal('equipment-details-modal');
                         // showEditModal MUST be globally defined (e.g., in equipment.js)
                         if (typeof showEditModal === 'function') {
                            showEditModal(equipmentId); 
                         } else {
                            console.error('showEditModal function not found');
                         }
                     } else {
                         console.error("Edit button missing equipment ID");
                     }
                 });
             }

            // ✅✅✅ START: MODIFIED PRINT BUTTON LISTENER ✅✅✅
            // This is the logic that determines WHAT to send to the QR code modal
             if(printBtn){
                 printBtn.addEventListener('click', () => {
                     const equipmentId = printBtn.getAttribute('data-equipment-id');
                     const equipmentName = document.getElementById('details-name')?.textContent || 'อุปกรณ์';
                     
                     // ✅ NEW: Get Serial Number from the details modal DOM
                     const equipmentSnElement = document.getElementById('details-serial');
                     const equipmentSn = equipmentSnElement ? equipmentSnElement.textContent : '';

                     // ✅ NEW: Logic to decide which value to encode
                     // Use SN if it exists and is not just '-', otherwise fallback to ID
                     const valueToEncode = (equipmentSn && equipmentSn.trim() !== '-' && equipmentSn.trim() !== '') 
                                          ? equipmentSn.trim() 
                                          : String(equipmentId);

                     console.log(`[DEBUG_QR_CALL] Encoding value: ${valueToEncode} (SN: '${equipmentSn}', Fallback ID: '${equipmentId}')`);

                     // Call the QR Code modal function (must be globally defined, e.g., in equipment.js)
                     if (typeof openQrCodeModal === 'function') {
                         // ✅ MODIFIED: Use valueToEncode and check it
                         if (valueToEncode) {
                             openQrCodeModal(valueToEncode, equipmentName);
                         } else { 
                             console.error("Details QR: No value to encode (ID and SN are missing)."); 
                             Swal.fire('Error', 'Missing ID/SN for QR', 'error'); 
                         }
                     } else {
                         console.error('openQrCodeModal function not found');
                         Swal.fire('Error', 'QR Code function not available.', 'error');
                     }
                 });
             }
             // ✅✅✅ END: MODIFIED PRINT BUTTON LISTENER ✅✅✅
        });
        
        // This function will be called by showDetailsModal() in equipment.js
        // to populate the modal with data.
        function populateDetailsModal(data) {
            console.log("[DEBUG_DETAILS] Populating data...", data);

            // Helper to safely set text content
            const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || '-'; else console.warn(`Element #${id} not found.`); };

            setText('details-name', data.name);
            const statusEl = document.getElementById('details-status');
            if (statusEl && typeof createStatusBadge === 'function') { statusEl.innerHTML = ''; statusEl.appendChild(createStatusBadge(data.status)); } else console.warn('Element #details-status or createStatusBadge not found.');
            
            setText('details-quantity', data.quantity ?? '0');
            setText('details-min-stock', data.min_stock ?? '0');
            setText('details-max-stock', data.max_stock ?? '0');
            setText('details-withdrawal-type', window.getWithdrawalTypeText(data.withdrawal_type));
            setText('details-category', data.category?.name);
            setText('details-location', data.location?.name);
            setText('details-model', data.model);
            setText('details-part-no', data.part_no);
            setText('details-serial', data.serial_number);
            setText('details-supplier', data.supplier);
            setText('details-price', data.price ? parseFloat(data.price).toLocaleString('th-TH', { style: 'currency', currency: 'THB' }) : '-');
            setText('details-purchase-date', window.formatDate(data.purchase_date));
            setText('details-warranty-date', window.formatDate(data.warranty_date));
            setText('details-notes', data.notes || '-');
            setText('details-created-at', window.formatDateTime(data.created_at));
            setText('details-updated-at', window.formatDateTime(data.updated_at));

            // --- Populate MSDS Info ---
            const msdsTab = document.getElementById('details-msds-tab');
            const msdsDetailsEl = document.getElementById('details-msds-details');
            const msdsLinkEl = document.getElementById('details-msds-file');

            if (data.has_msds) {
                if(msdsTab) msdsTab.classList.remove('hidden');
                if(msdsDetailsEl) msdsDetailsEl.textContent = data.msds_details || '-';
                if (msdsLinkEl) {
                    if (data.msds_file_url) {
                        msdsLinkEl.href = data.msds_file_url;
                        msdsLinkEl.classList.remove('hidden');
                    } else {
                        msdsLinkEl.classList.add('hidden');
                    }
                }
            } else {
                if(msdsTab) msdsTab.classList.add('hidden');
                if(msdsLinkEl) msdsLinkEl.classList.add('hidden');
            }
             // Reset to first tab
            const firstTabBtn = document.querySelector('.details-tab-btn');
            if(firstTabBtn) switchDetailsTab(firstTabBtn, 'details-tab-main');


            // --- Populate Transaction History ---
            const transactionContainer = document.getElementById('details-transactions');
            if (transactionContainer) {
                transactionContainer.innerHTML = ''; // Clear
                if (data.transactions && data.transactions.length > 0) {
                    data.transactions.forEach(t => {
                        const div = document.createElement('div');
                        div.className = 'p-1.5 border-b last:border-b-0 text-xs';
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
                    transactionContainer.innerHTML = '<p class="py-4 px-3 text-xs text-center text-gray-500">ไม่มีประวัติ</p>';
                }
            }

            // --- Populate Image Gallery ---
            const primaryImageDisplay = document.getElementById('details-primary-image');
            const thumbnailContainer = document.getElementById('details-gallery-thumbnails');
            thumbnailContainer.innerHTML = ''; // Clear
            let finalPrimaryUrl = 'https://placehold.co/600x400/e2e8f0/64748b?text=No+Image';

            if (data.image_urls && data.image_urls.length > 0) {
                finalPrimaryUrl = data.primary_image_url || data.image_urls[0];
                data.image_urls.forEach((url, index) => {
                    const imgThumb = document.createElement('img');
                    imgThumb.src = url; imgThumb.alt = `Thumbnail ${index + 1}`;
                    imgThumb.className = 'w-full h-16 object-cover rounded cursor-pointer border-2 border-transparent hover:border-blue-400 transition';
                    // Use the globally defined function
                    imgThumb.onclick = () => window.updatePrimaryImage(url); 
                    thumbnailContainer.appendChild(imgThumb);
                });
            } else {
                thumbnailContainer.innerHTML = '<div class="col-span-6 text-center text-gray-500 text-xs py-2">No images</div>';
            }
            primaryImageDisplay.src = finalPrimaryUrl;

            // --- Set Button Data ---
            // This is crucial for the listeners above
            const editBtn = document.getElementById('details-edit-btn');
            const printBtn = document.getElementById('details-print-btn');
            // Store the equipment ID on the buttons for later use
            if(editBtn) editBtn.setAttribute('data-equipment-id', data.id); else console.warn("Edit button 'details-edit-btn' not found.");
            if(printBtn) printBtn.setAttribute('data-equipment-id', data.id); else console.warn("Print button 'details-print-btn' not found.");

            // Add Click Listener for Edit Button (moved from DOMContentLoaded)
             if(editBtn){
                // Clone and replace to ensure previous listeners are removed
                const newEditBtn = editBtn.cloneNode(true);
                editBtn.parentNode.replaceChild(newEditBtn, editBtn);
                newEditBtn.addEventListener('click', () => {
                     const equipmentId = newEditBtn.getAttribute('data-equipment-id');
                     if (equipmentId) {
                         closeModal('equipment-details-modal');
                         // ✅ This function MUST be defined in equipment.js
                         if (typeof showEditModal === 'function') {
                             showEditModal(equipmentId); 
                         } else {
                             console.error('showEditModal is not defined globally');
                             Swal.fire('Error', 'Cannot open edit modal.', 'error');
                         }
                     } else {
                         console.error("Edit button missing equipment ID");
                     }
                 });
             }
             
            // ✅✅✅ START: RE-ADD PRINT BUTTON LISTENER (CLONED) ✅✅✅
            // This block was missing, causing the old (wrong) listener from equipment.js to run.
            // By adding it here, it will override the old one.
            if(printBtn){
                // Clone and replace to ensure previous listeners are removed
                const newPrintBtn = printBtn.cloneNode(true);
                printBtn.parentNode.replaceChild(newPrintBtn, printBtn);
                
                newPrintBtn.addEventListener('click', () => {
                     const equipmentId = newPrintBtn.getAttribute('data-equipment-id');
                     const equipmentName = document.getElementById('details-name')?.textContent || 'อุปกรณ์';
                     
                     // Get Serial Number from the details modal DOM
                     const equipmentSnElement = document.getElementById('details-serial');
                     const equipmentSn = equipmentSnElement ? equipmentSnElement.textContent : '';

                     // Logic to decide which value to encode
                     const valueToEncode = (equipmentSn && equipmentSn.trim() !== '-' && equipmentSn.trim() !== '') 
                                          ? equipmentSn.trim() 
                                          : String(equipmentId);

                     console.log(`[DEBUG_QR_CALL_FIXED] Encoding value: ${valueToEncode} (SN: '${equipmentSn}', Fallback ID: '${equipmentId}')`);

                     // Call the QR Code modal function (must be globally defined)
                     if (typeof openQrCodeModal === 'function') {
                         if (valueToEncode) {
                             openQrCodeModal(valueToEncode, equipmentName);
                         } else { 
                             console.error("Details QR: No value to encode."); 
                             Swal.fire('Error', 'Missing ID/SN for QR', 'error'); 
                         }
                     } else {
                         console.error('openQrCodeModal function not found');
                         Swal.fire('Error', 'QR Code function not available.', 'error');
                     }
                 });
             }
             // ✅✅✅ END: RE-ADD PRINT BUTTON LISTENER (CLONED) ✅✅✅


            console.log("[DEBUG_DETAILS] Details populated and shown.");
        }
    } else {
        console.log("[DEBUG_DETAILS] Script block already processed, skipping re-definition.");
    }
</script>