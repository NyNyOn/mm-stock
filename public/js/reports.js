document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('report-form');
    if (!form) return;

    // --- Elements ---
    const resultsContainer = document.getElementById('report-results-container');
    const reportTable = document.getElementById('report-table');
    const reportTitle = document.getElementById('report-title');
    const reportSubtitle = document.getElementById('report-subtitle');
    const submitButton = form.querySelector('button[type="submit"]');
    const spinner = submitButton.querySelector('.spinner-border');
    const searchIcon = submitButton.querySelector('.fa-search');
    const reportTypeSelect = document.getElementById('report_type');
    const userFilterContainer = document.getElementById('user-filter-container');

    // ✅✅✅ FIX: Declare initialReportType within this scope ✅✅✅
    // Read the global variable set by the Blade view
    // Use window.initialReportType to safely access the global variable
    const initialReportType = typeof window.initialReportType !== 'undefined' ? window.initialReportType : null;
    
    console.log(`[Reports] Initial report type from Blade: ${initialReportType}`); // Log for confirmation


    // --- Functions (generateReport, renderReport, helpers) ---
    function generateReport() {
        const formData = new FormData(form);
        const reportType = formData.get('report_type');

        if (!reportType) {
            Swal.fire('ผิดพลาด!', 'กรุณาเลือกประเภทรายงาน', 'error');
            return;
        }
        if (reportType === 'user_activity_report' && !formData.get('user_id')) {
            Swal.fire('ผิดพลาด!', 'กรุณาเลือกผู้ใช้งานสำหรับรายงานนี้', 'error');
            return;
        }

        // Show loading state
        if(spinner) spinner.style.display = 'inline-block';
        if(searchIcon) searchIcon.style.display = 'none';
        if(submitButton) submitButton.disabled = true;
        if(resultsContainer) resultsContainer.style.display = 'none'; // Hide previous results

        fetch('/reports/generate', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json',
            }
        })
        .then(response => {
            if (!response.ok) {
                // Try to get error message from response body
                return response.json().then(errData => {
                    throw new Error(errData.message || `Network response was not ok (${response.status})`);
                }).catch(() => {
                    // Fallback if response is not JSON
                    throw new Error(`Network response was not ok (${response.status})`);
                });
            }
            return response.json();
        })
        .then(data => {
            if(resultsContainer) resultsContainer.style.display = 'block'; // Show results container
            renderReport(reportType, data);
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            Swal.fire('เกิดข้อผิดพลาด!', error.message || 'ไม่สามารถสร้างรายงานได้ กรุณาตรวจสอบ Console', 'error');
        })
        .finally(() => {
            // Hide loading state
            if(spinner) spinner.style.display = 'none';
            if(searchIcon) searchIcon.style.display = 'inline-block';
            if(submitButton) submitButton.disabled = false;
        });
    }

    function renderReport(type, data) {
        let tableHTML = '';
        let title = '';
        // Ensure reportTypeSelect exists before accessing options
        const selectedOption = reportTypeSelect ? reportTypeSelect.options[reportTypeSelect.selectedIndex].text : 'รายงาน';
        title = selectedOption;
        let subtitle = `พบข้อมูลทั้งหมด ${data ? data.length : 0} รายการ`;

        switch (type) {
            case 'stock_summary': tableHTML = renderStockSummary(data); break;
            case 'transaction_history': tableHTML = renderTransactionHistory(data); break;
            case 'borrow_report': tableHTML = renderBorrowReport(data); break;
            case 'low_stock': tableHTML = renderLowStockReport(data); break;
            case 'warranty': tableHTML = renderWarrantyReport(data); break;
            case 'maintenance_report': tableHTML = renderMaintenanceReport(data); break;
            case 'po_report': tableHTML = renderPoReport(data); break;
            case 'disposal_report': tableHTML = renderDisposalReport(data); break;
            case 'consumable_return_report': tableHTML = renderConsumableReturnReport(data); break;
            case 'user_activity_report': tableHTML = renderUserActivityReport(data); break;
             default: tableHTML = '<tr><td colspan="6" class="p-8 text-center text-red-500">รูปแบบรายงานไม่ถูกต้อง</td></tr>';
        }

        if(reportTitle) reportTitle.textContent = title;
        if(reportSubtitle) reportSubtitle.textContent = subtitle;
        if(reportTable) reportTable.innerHTML = tableHTML;
    }

    // --- Helper function for safe access and default value ---
    const get = (obj, path, defaultValue = 'N/A') => {
        const value = path.split('.').reduce((a, b) => (a && a[b]) ? a[b] : null, obj);
        return value === null || value === undefined ? defaultValue : value;
    };

    // --- Helper function for date formatting ---
    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        try {
            return new Date(dateString).toLocaleString('th-TH', {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        } catch (e) { console.error("Date formatting error:", e, "Input:", dateString); return 'Invalid Date'; }
    };

    // --- Render Functions ---
    function renderMaintenanceReport(data) {
        let html = `<thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th scope="col" class="px-4 py-3">ID</th><th scope="col" class="px-4 py-3">วันที่แจ้ง</th><th scope="col" class="px-4 py-3">อุปกรณ์</th><th scope="col" class="px-4 py-3">รายละเอียดปัญหา</th><th scope="col" class="px-4 py-3">ผู้แจ้ง</th><th scope="col" class="px-4 py-3">สถานะ</th></tr></thead><tbody>`;
        if (!data || data.length === 0) return html + '<tr><td colspan="6" class="p-8 text-center text-gray-500">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach(log => { html += `<tr class="border-b"><td class="px-4 py-3 font-mono">#${log.id}</td><td class="px-4 py-3">${formatDate(log.created_at)}</td><td class="px-4 py-3 font-medium text-gray-900">${get(log, 'equipment.name')}</td><td class="px-4 py-3" style="max-width: 300px; white-space: normal;">${log.problem_description || '-'}</td><td class="px-4 py-3">${get(log, 'reported_by.fullname')}</td><td class="px-4 py-3"><span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">${log.status}</span></td></tr>`; });
        return html + '</tbody>';
    }

    function renderPoReport(data) {
        let html = `<thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th scope="col" class="px-4 py-3">PO ID</th><th scope="col" class="px-4 py-3">ประเภท</th><th scope="col" class="px-4 py-3">วันที่สั่ง</th><th scope="col" class="px-4 py-3">ผู้สั่ง</th><th scope="col" class="px-4 py-3">รายการ</th><th scope="col" class="px-4 py-3">สถานะ</th></tr></thead><tbody>`;
        if (!data || data.length === 0) return html + '<tr><td colspan="6" class="p-8 text-center text-gray-500">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach(po => { let itemsList = get(po, 'items', []).map(item => `<li>${get(item, 'equipment.name', item.item_description)} (x${item.quantity_ordered})</li>`).join(''); html += `<tr class="border-b"><td class="px-4 py-3 font-mono">#${po.id}</td><td class="px-4 py-3">${po.type}</td><td class="px-4 py-3">${formatDate(po.ordered_at)}</td><td class="px-4 py-3">${get(po, 'ordered_by.fullname', po.glpi_requester_name)}</td><td class="px-4 py-3 text-xs"><ul class="list-disc list-inside">${itemsList}</ul></td><td class="px-4 py-3"><span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">${po.status}</span></td></tr>`; });
        return html + '</tbody>';
     }

    function renderDisposalReport(data) {
        let html = `<thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th scope="col" class="px-4 py-3">ชื่ออุปกรณ์</th><th scope="col" class="px-4 py-3">S/N</th><th scope="col" class="px-4 py-3">ประเภท</th><th scope="col" class="px-4 py-3">วันที่ตัดจำหน่าย/ขาย</th><th scope="col" class="px-4 py-3">สถานะ</th></tr></thead><tbody>`;
        if (!data || data.length === 0) return html + '<tr><td colspan="5" class="p-8 text-center text-gray-500">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach(item => { html += `<tr class="border-b"><td class="px-4 py-3 font-medium text-gray-900">${item.name}</td><td class="px-4 py-3 font-mono">${item.serial_number || '-'}</td><td class="px-4 py-3">${get(item, 'category.name')}</td><td class="px-4 py-3">${formatDate(item.updated_at)}</td><td class="px-4 py-3"><span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">${item.status}</span></td></tr>`; });
        return html + '</tbody>';
     }

    function renderConsumableReturnReport(data) {
        let html = `<thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th scope="col" class="px-4 py-3">ID</th><th scope="col" class="px-4 py-3">วันที่ขอ</th><th scope="col" class="px-4 py-3">อุปกรณ์</th><th scope="col" class="px-4 py-3">ผู้ขอคืน</th><th scope="col" class="px-4 py-3">ผู้อนุมัติ</th><th scope="col" class="px-4 py-3">สถานะ</th></tr></thead><tbody>`;
        if (!data || data.length === 0) return html + '<tr><td colspan="6" class="p-8 text-center text-gray-500">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach(log => { let statusClass = 'bg-yellow-100 text-yellow-800'; if(log.status === 'approved') statusClass = 'bg-green-100 text-green-800'; if(log.status === 'rejected') statusClass = 'bg-red-100 text-red-800'; html += `<tr class="border-b"><td class="px-4 py-3 font-mono">#${log.id}</td><td class="px-4 py-3">${formatDate(log.created_at)}</td><td class="px-4 py-3 font-medium text-gray-900">${get(log, 'original_transaction.equipment.name')}</td><td class="px-4 py-3">${get(log, 'requester.fullname')}</td><td class="px-4 py-3">${get(log, 'approver.fullname')}</td><td class="px-4 py-3"><span class="px-2 py-1 text-xs font-medium rounded-full ${statusClass}">${log.status}</span></td></tr>`; });
        return html + '</tbody>';
     }

    function renderUserActivityReport(data) {
        return renderTransactionHistory(data);
    }

    function renderStockSummary(data) {
        let html = `<thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th scope="col" class="px-4 py-3">#</th><th scope="col" class="px-4 py-3">ชื่ออุปกรณ์</th><th scope="col" class="px-4 py-3">S/N</th><th scope="col" class="px-4 py-3">ประเภท</th><th scope="col" class="px-4 py-3">สถานที่</th><th scope="col" class="px-4 py-3">จำนวน</th><th scope="col" class="px-4 py-3">สถานะ</th></tr></thead><tbody>`;
        if (!data || data.length === 0) return html + '<tr><td colspan="7" class="p-8 text-center text-gray-500">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach((item, index) => { html += `<tr class="border-b"><td class="px-4 py-3">${index + 1}</td><td class="px-4 py-3 font-medium text-gray-900">${item.name || '-'}</td><td class="px-4 py-3 font-mono">${item.serial_number || '-'}</td><td class="px-4 py-3">${get(item, 'category.name')}</td><td class="px-4 py-3">${get(item, 'location.name')}</td><td class="px-4 py-3 font-bold">${item.quantity} ${get(item, 'unit.name','')}</td><td class="px-4 py-3"><span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">${item.status}</span></td></tr>`; });
        return html + '</tbody>';
    }

    function renderTransactionHistory(data) {
        let html = `<thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th scope="col" class="px-4 py-3">วันที่</th><th scope="col" class="px-4 py-3">ชื่ออุปกรณ์</th><th scope="col" class="px-4 py-3">ประเภทรายการ</th><th scope="col" class="px-4 py-3">จำนวน</th><th scope="col" class="px-4 py-3">ผู้ดำเนินการ</th><th scope="col" class="px-4 py-3">หมายเหตุ</th></tr></thead><tbody>`;
        if (!data || data.length === 0) return html + '<tr><td colspan="6" class="p-8 text-center text-gray-500">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach(tx => { const typeClass = tx.quantity_change >= 0 ? 'text-green-600' : 'text-red-600'; html += `<tr class="border-b"><td class="px-4 py-3">${formatDate(tx.transaction_date)}</td><td class="px-4 py-3 font-medium text-gray-900">${get(tx, 'equipment.name', 'DELETED')}</td><td class="px-4 py-3 font-bold">${tx.type.charAt(0).toUpperCase() + tx.type.slice(1)}</td><td class="px-4 py-3 font-bold ${typeClass}">${tx.quantity_change}</td><td class="px-4 py-3">${get(tx, 'user.fullname')}</td><td class="px-4 py-3" style="max-width: 300px; white-space: normal;">${tx.notes || '-'}</td></tr>`; });
        return html + '</tbody>';
    }

    function renderBorrowReport(data) {
        let html = `<thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th scope="col" class="px-4 py-3">วันที่ยืม</th><th scope="col" class="px-4 py-3">ชื่ออุปกรณ์</th><th scope="col" class="px-4 py-3">S/N</th><th scope="col" class="px-4 py-3">ผู้ยืม</th><th scope="col" class="px-4 py-3">วัตถุประสงค์</th></tr></thead><tbody>`;
        if (!data || data.length === 0) return html + '<tr><td colspan="5" class="p-8 text-center text-gray-500">ไม่มีรายการที่กำลังถูกยืม</td></tr></tbody>';
        data.forEach(tx => { html += `<tr class="border-b"><td class="px-4 py-3">${formatDate(tx.transaction_date)}</td><td class="px-4 py-3 font-medium text-gray-900">${get(tx, 'equipment.name', 'DELETED')}</td><td class="px-4 py-3 font-mono">${get(tx, 'equipment.serial_number', '-')}</td><td class="px-4 py-3">${get(tx, 'user.fullname')}</td><td class="px-4 py-3" style="max-width: 300px; white-space: normal;">${tx.notes || '-'}</td></tr>`; });
        return html + '</tbody>';
    }

    function renderLowStockReport(data) {
        let html = `<thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th scope="col" class="px-4 py-3">#</th><th scope="col" class="px-4 py-3">ชื่ออุปกรณ์</th><th scope="col" class="px-4 py-3">S/N</th><th scope="col" class="px-4 py-3">ประเภท</th><th scope="col" class{="px-4 py-3">คงเหลือ</th><th scope="col" class="px-4 py-3">ขั้นต่ำ</th><th scope="col" class="px-4 py-3">หน่วยนับ</th></tr></thead><tbody>`;
        if (!data || data.length === 0) return html + '<tr><td colspan="7" class="p-8 text-center text-gray-500">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach((item, index) => { html += `<tr class="border-b"><td class="px-4 py-3">${index + 1}</td><td class="px-4 py-3 font-medium text-gray-900">${item.name}</td><td class="px-4 py-3 font-mono">${item.serial_number || '-'}</td><td class="px-4 py-3">${get(item, 'category.name')}</td><td class="px-4 py-3 font-bold text-orange-500">${item.quantity}</td><td class="px-4 py-3">${item.min_stock}</td><td class="px-4 py-3">${get(item, 'unit.name', '-')}</td></tr>`; });
        return html + '</tbody>';
    }

    function renderWarrantyReport(data) {
        let html = `<thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th scope="col" class="px-4 py-3">#</th><th scope="col" class="px-4 py-3">ชื่ออุปกรณ์</th><th scope="col" class="px-4 py-3">S/N</th><th scope="col" class="px-4 py-3">วันหมดประกัน</th><th scope="col" class="px-4 py-3">สถานะ</th></tr></thead><tbody>`;
        if (!data || data.length === 0) return html + '<tr><td colspan="5" class="p-8 text-center text-gray-500">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach((item, index) => { const warrantyDate = new Date(item.warranty_date); const today = new Date(); today.setHours(0,0,0,0); let statusText = ''; let statusClass = ''; if (warrantyDate < today) { statusText = 'หมดอายุแล้ว'; statusClass = 'text-red-500 font-bold'; } else { const diffTime = Math.abs(warrantyDate - today); const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); statusText = `เหลือ ${diffDays} วัน`; statusClass = diffDays <= 30 ? 'text-orange-500' : 'text-green-500'; } html += `<tr class="border-b"><td class="px-4 py-3">${index + 1}</td><td class="px-4 py-3 font-medium text-gray-900">${item.name}</td><td class="px-4 py-3 font-mono">${item.serial_number || '-'}</td><td class="px-4 py-3">${warrantyDate.toLocaleDateString('th-TH')}</td><td class="px-4 py-3 ${statusClass}">${statusText}</td></tr>`; });
        return html + '</tbody>';
    }


    // --- Event Listeners ---
    if(reportTypeSelect) { // Add null check for safety
        reportTypeSelect.addEventListener('change', function() {
            // Show/hide user filter based on selection
            if (this.value === 'user_activity_report') {
                if(userFilterContainer) userFilterContainer.style.display = 'block';
            } else {
                if(userFilterContainer) userFilterContainer.style.display = 'none';
            }
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        generateReport();
    });

    // --- Initial Report Generation (if initialReportType is set) ---
    // Use the variable declared at the top of the DOMContentLoaded scope
    if (initialReportType) {
        console.log(`[Reports] Initial report type detected: ${initialReportType}`);
        if (reportTypeSelect) {
            reportTypeSelect.value = initialReportType;
            console.log(`[Reports] Set dropdown to: ${reportTypeSelect.value}`);
            // Trigger change event to update UI (like user filter visibility)
            reportTypeSelect.dispatchEvent(new Event('change'));
            console.log('[Reports] Dispatched change event.');
            // Automatically generate the report
            generateReport();
            console.log('[Reports] Called generateReport() automatically.');
        } else {
            console.error('[Reports] Report type select dropdown not found.');
        }
    } else {
        console.log('[Reports] No initial report type detected. User needs to select one.');
    }
});

