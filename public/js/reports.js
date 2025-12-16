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
    const pdfButton = document.getElementById('export-pdf-button');

    const initialReportType = typeof window.initialReportType !== 'undefined' ? window.initialReportType : null;

    // --- Functions ---
    function generateReport() {
        const formData = new FormData(form);
        const reportType = formData.get('report_type');

        if (!reportType) { return Swal.fire('ผิดพลาด!', 'กรุณาเลือกประเภทรายงาน', 'error'); }
        if (reportType === 'user_activity_report' && !formData.get('user_id')) { return Swal.fire('ผิดพลาด!', 'กรุณาเลือกผู้ใช้งาน', 'error'); }

        if(spinner) spinner.style.display = 'inline-block';
        if(searchIcon) searchIcon.style.display = 'none';
        if(submitButton) submitButton.disabled = true;
        if(resultsContainer) resultsContainer.style.display = 'none';

        const params = new URLSearchParams(formData).toString();

        fetch('/reports/generate', {
            method: 'POST',
            body: params,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => {
            if (!response.ok) throw new Error(`Network response was not ok (${response.status})`);
            return response.json();
        })
        .then(data => {
            const reportData = data.data ? data.data : data;
            
            if(resultsContainer) resultsContainer.style.display = 'block';
            renderReport(reportType, reportData);
            resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        })
        .catch(error => {
            // console.error('Fetch Error:', error); // Optional: keep critical error logging
            Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถสร้างรายงานได้: ' + error.message, 'error');
        })
        .finally(() => {
            if(spinner) spinner.style.display = 'none';
            if(searchIcon) searchIcon.style.display = 'inline-block';
            if(submitButton) submitButton.disabled = false;
            if (pdfButton && resultsContainer.style.display !== 'none') {
                pdfButton.style.display = 'inline-block';
            }
        });
    }

    function renderReport(type, data) {
        let tableHTML = '';
        let title = reportTypeSelect.options[reportTypeSelect.selectedIndex].text;
        let subtitle = `ข้อมูล ณ วันที่: ${new Date().toLocaleDateString('th-TH')}`;

        switch (type) {
            case 'stock_summary': tableHTML = renderStockSummary(data); break;
            case 'transaction_history': tableHTML = renderTransactionHistory(data); break;
            case 'borrow_report': tableHTML = renderBorrowReport(data); break;
            case 'low_stock': tableHTML = renderLowStockReport(data); break;
            case 'out_of_stock': tableHTML = renderOutOfStockReport(data); break;
            case 'warranty': tableHTML = renderWarrantyReport(data); break;
            case 'maintenance_report': tableHTML = renderMaintenanceReport(data); break;
            case 'po_report': tableHTML = renderPoReport(data); break;
            case 'disposal_report': tableHTML = renderDisposalReport(data); break;
            case 'consumable_return_report': tableHTML = renderConsumableReturnReport(data); break;
            case 'user_activity_report': tableHTML = renderUserActivityReport(data); break;
            
            case 'inventory_valuation': tableHTML = renderInventoryValuation(data); break;
            case 'department_cost': tableHTML = renderDepartmentCost(data); break;
            case 'top_movers': tableHTML = renderTopMovers(data); break;
            case 'dead_stock': tableHTML = renderDeadStock(data); break;
            case 'audit_logs': tableHTML = renderAuditLogs(data); break;

            default: tableHTML = '<tr><td colspan="6" class="p-8 text-center text-red-500">รูปแบบรายงานไม่ถูกต้อง</td></tr>';
        }

        reportTitle.textContent = title;
        reportSubtitle.textContent = subtitle;
        reportTable.innerHTML = tableHTML;
    }

    const get = (obj, path, def = '-') => path.split('.').reduce((a, b) => (a && a[b]) ? a[b] : null, obj) || def;
    const formatDate = (d) => d ? new Date(d).toLocaleString('th-TH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
    const formatCurrency = (amount) => new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(amount);
    const getBadge = (s) => typeof window.getStatusBadge === 'function' ? window.getStatusBadge(s) : s;

    // ✅ Helper: สร้าง Cell รูปภาพ (Clean No Debug)
    function renderImageCell(item) {
        if (!item) return '';

        let imgObj = item.primary_image;
        if (!imgObj && item.latest_image) {
            imgObj = item.latest_image;
        }

        const hasImage = imgObj && (imgObj.image_url || imgObj.file_path);
        
        if (!hasImage) {
            return `
                <div class="w-12 h-12 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-300">
                    <i class="fas fa-image"></i>
                </div>
            `;
        }

        let imgSrc = '';
        if (imgObj.image_url) {
            imgSrc = imgObj.image_url;
        } else if (imgObj.file_path) {
            const filePath = imgObj.file_path;
            if (filePath.startsWith('http')) {
                imgSrc = filePath;
            } else if (filePath.startsWith('/')) {
                imgSrc = filePath;
            } else if (filePath.includes('uploads/')) {
                if (filePath.startsWith('uploads/')) {
                    imgSrc = '/' + filePath;
                } else {
                    imgSrc = '/images/' + filePath;
                }
            } else {
                imgSrc = '/images/' + filePath; 
            }
        }
        
        const onclickAttr = `onclick="if(typeof showDetailsModal === 'function') showDetailsModal(${item.id});"`;

        return `
            <div class="relative w-12 h-12 rounded-lg overflow-hidden border border-gray-200 shadow-sm group cursor-pointer" ${onclickAttr}>
                <img src="${imgSrc}" 
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" 
                     alt="${item.name}"
                     onerror="this.onerror=null; this.src='/images/placeholder.webp';">
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
            </div>
        `;
    }

    function renderClickableName(item, name = null) {
        const itemName = name || item.name;
        if (!item.id) return itemName;
        
        const onclickAttr = `onclick="if(typeof showDetailsModal === 'function') showDetailsModal(${item.id});"`;
        return `<span class="cursor-pointer font-bold text-indigo-700 hover:text-indigo-900 hover:underline" ${onclickAttr}>${itemName}</span>`;
    }

    // --- Render Functions ---

    function renderStockSummary(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="px-4 py-3">#</th><th class="px-4 py-3 text-center">รูปภาพ</th><th class="px-4 py-3">ชื่ออุปกรณ์</th><th class="px-4 py-3">S/N</th><th class="px-4 py-3">ประเภท</th><th class="px-4 py-3">สถานที่</th><th class="px-4 py-3 text-right">จำนวน</th><th class="px-4 py-3 text-center">สถานะ</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="8" class="p-8 text-center">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach((i,x) => {
            html += `<tr class="border-b hover:bg-gray-50 transition"><td class="px-4 py-3">${x+1}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3 font-mono text-xs">${i.serial_number||'-'}</td><td class="px-4 py-3">${get(i,'category.name')}</td><td class="px-4 py-3">${get(i,'location.name')}</td><td class="px-4 py-3 text-right font-bold">${i.quantity} ${get(i,'unit.name','')}</td><td class="px-4 py-3 text-center">${getBadge(i.status)}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderTransactionHistory(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="px-4 py-3">วันที่</th><th class="px-4 py-3 text-center">รูป</th><th>อุปกรณ์</th><th>รายการ</th><th class="text-right">จำนวน</th><th>ผู้ทำ</th><th>หมายเหตุ</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="7" class="p-8 text-center">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach(tx => {
            const eq = tx.equipment || { id: 0, name: 'Deleted' };
            const typeClass = tx.quantity_change >= 0 ? 'text-green-600' : 'text-red-600';
            // ✅ ใช้ Math.abs() เพื่อแสดงค่าบวกเสมอ
            html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 text-xs">${formatDate(tx.transaction_date)}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(eq)}</td><td class="px-4 py-3 font-medium">${eq.id ? renderClickableName(eq) : eq.name}</td><td class="px-4 py-3 font-bold uppercase">${tx.type}</td><td class="px-4 py-3 text-right font-mono font-bold ${typeClass}">${Math.abs(tx.quantity_change)}</td><td class="px-4 py-3">${get(tx,'user.fullname')}</td><td class="px-4 py-3 text-sm">${tx.notes||'-'}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderBorrowReport(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>วันที่</th><th class="text-center">รูป</th><th>อุปกรณ์</th><th>S/N</th><th>ผู้ยืม</th><th>สถานะ</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="6" class="p-8 text-center">ว่าง</td></tr></tbody>';
        data.forEach(tx => {
             const eq = tx.equipment || {};
             html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3">${formatDate(tx.transaction_date)}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(eq)}</td><td class="px-4 py-3 font-medium">${eq.id ? renderClickableName(eq) : '-'}</td><td class="px-4 py-3 text-xs font-mono">${get(tx,'equipment.serial_number')}</td><td class="px-4 py-3">${get(tx,'user.fullname')}</td><td class="px-4 py-3 text-center">${getBadge('borrowed')}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderLowStockReport(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="text-center">รูป</th><th>ชื่อ</th><th>ประเภท</th><th class="text-right">คงเหลือ</th><th class="text-right">ขั้นต่ำ</th><th>สถานะ</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="6" class="p-8 text-center">ไม่มีสินค้าใกล้หมด</td></tr></tbody>';
        data.forEach(i => {
            html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3">${get(i,'category.name')}</td><td class="px-4 py-3 text-right font-bold text-orange-500">${i.quantity}</td><td class="px-4 py-3 text-right">${i.min_stock}</td><td class="px-4 py-3 text-center">${getBadge('low_stock')}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderOutOfStockReport(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="text-center">รูป</th><th>ชื่อ</th><th>ประเภท</th><th class="text-right">คงเหลือ</th><th>สถานที่</th><th>สถานะ</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="6" class="p-8 text-center text-green-500">เยี่ยมมาก! ไม่มีสินค้าหมดสต็อก</td></tr></tbody>';
        data.forEach(i => {
            html += `<tr class="border-b hover:bg-red-50"><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3">${get(i,'category.name')}</td><td class="px-4 py-3 text-right font-bold text-red-600">${i.quantity}</td><td class="px-4 py-3">${get(i,'location.name')}</td><td class="px-4 py-3 text-center">${getBadge('out_of_stock')}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderWarrantyReport(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="text-center">รูป</th><th>ชื่อ</th><th>S/N</th><th class="text-center">หมดประกัน</th><th class="text-center">สถานะ</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="5" class="p-8 text-center">ไม่พบ</td></tr></tbody>';
        data.forEach(i => {
            const wd = new Date(i.warranty_date);
            const diff = Math.ceil((wd - new Date()) / (1000 * 60 * 60 * 24));
            let st = diff < 0 ? 'หมดอายุ' : 'เหลือ ' + diff + ' วัน';
            let cl = diff < 0 ? 'text-red-600 font-bold' : (diff < 30 ? 'text-orange-500' : 'text-green-600');
            html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3 text-xs font-mono">${i.serial_number}</td><td class="px-4 py-3 text-center">${wd.toLocaleDateString('th-TH')}</td><td class="px-4 py-3 text-center ${cl}">${st}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderMaintenanceReport(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>วันที่</th><th class="text-center">รูป</th><th>อุปกรณ์</th><th>อาการ</th><th>ผู้แจ้ง</th><th>สถานะ</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="6" class="p-8 text-center">ไม่พบ</td></tr></tbody>';
        data.forEach(l => {
            const eq = l.equipment || {};
            html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 whitespace-nowrap text-xs">${formatDate(l.created_at)}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(eq)}</td><td class="px-4 py-3 font-medium">${eq.id ? renderClickableName(eq) : '-'}</td><td class="px-4 py-3 text-sm">${l.problem_description}</td><td class="px-4 py-3">${get(l,'reported_by.fullname')}</td><td class="px-4 py-3 text-center">${getBadge(l.status)}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderPoReport(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>PO No.</th><th>ประเภท</th><th>วันที่</th><th>ผู้สั่ง</th><th>สถานะ</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="5" class="p-8 text-center">ไม่พบ</td></tr></tbody>';
        data.forEach(p => {
            html += `<tr class="border-b"><td class="px-4 py-3 font-mono">#${p.id}</td><td class="px-4 py-3 font-bold">${p.type}</td><td class="px-4 py-3 text-xs">${formatDate(p.ordered_at)}</td><td class="px-4 py-3 text-sm">${get(p,'ordered_by.fullname')}</td><td class="px-4 py-3 text-center">${getBadge(p.status)}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderDisposalReport(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="text-center">รูป</th><th>ชื่อ</th><th>ประเภท</th><th>วันที่ตัดจำหน่าย</th><th>สถานะ</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="5" class="p-8 text-center">ไม่พบ</td></tr></tbody>';
        data.forEach(i => {
            html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3">${get(i,'category.name')}</td><td class="px-4 py-3">${formatDate(i.updated_at)}</td><td class="px-4 py-3 text-center">${getBadge(i.status)}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderConsumableReturnReport(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>วันที่</th><th class="text-center">รูป</th><th>อุปกรณ์</th><th>ผู้คืน</th><th>ผู้อนุมัติ</th><th>สถานะ</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="6" class="p-8 text-center">ไม่พบ</td></tr></tbody>';
        data.forEach(l => {
             const eq = get(l, 'original_transaction.equipment', {});
             html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 whitespace-nowrap text-xs">${formatDate(l.created_at)}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(eq)}</td><td class="px-4 py-3 font-medium">${eq.id ? renderClickableName(eq) : '-'}</td><td class="px-4 py-3">${get(l,'requester.fullname')}</td><td class="px-4 py-3">${get(l,'approver.fullname')}</td><td class="px-4 py-3 text-center">${getBadge(l.status)}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderUserActivityReport(data) { return renderTransactionHistory(data); }

    function renderInventoryValuation(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>#</th><th class="text-center">รูป</th><th>ชื่ออุปกรณ์</th><th>ประเภท</th><th class="text-right">คงเหลือ</th><th class="text-right">ราคา/หน่วย</th><th class="text-right">มูลค่ารวม</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="7" class="p-8 text-center">ไม่พบข้อมูล</td></tr></tbody>';
        let totalVal = 0;
        data.forEach((i,x) => {
            totalVal += i.total_value;
            html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3">${x+1}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3">${get(i,'category.name')}</td><td class="px-4 py-3 text-right">${i.quantity}</td><td class="px-4 py-3 text-right">${formatCurrency(i.price||0)}</td><td class="px-4 py-3 text-right font-bold text-green-600">${formatCurrency(i.total_value)}</td></tr>`;
        });
        html += `<tr class="bg-gray-100 font-bold"><td colspan="6" class="px-4 py-3 text-right">รวมมูลค่าทั้งหมด</td><td class="px-4 py-3 text-right text-green-700 text-lg">${formatCurrency(totalVal)}</td></tr>`;
        return html + '</tbody>';
    }

    function renderDepartmentCost(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>#</th><th>ผู้ใช้งาน</th><th>แผนก</th><th class="text-center">จำนวนครั้ง</th><th class="text-right">ชิ้นรวม</th><th class="text-right">มูลค่า (บาท)</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="6" class="p-8 text-center">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach((i,x) => {
            html += `<tr class="border-b"><td class="px-4 py-3">${x+1}</td><td class="px-4 py-3 font-medium">${i.user_name}</td><td class="px-4 py-3">${i.department}</td><td class="px-4 py-3 text-center">${i.item_count}</td><td class="px-4 py-3 text-right">${i.total_qty}</td><td class="px-4 py-3 text-right font-bold text-red-500">${formatCurrency(i.total_cost)}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderTopMovers(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="text-center">อันดับ</th><th class="text-center">รูป</th><th>ชื่ออุปกรณ์</th><th>ประเภท</th><th class="text-center">ครั้งที่เบิก</th><th class="text-right">ยอดเบิก (ชิ้น)</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="6" class="p-8 text-center">ไม่พบข้อมูล</td></tr></tbody>';
        data.forEach((i,x) => {
             const eq = { id: i.equipment_id, name: i.equipment_name, primary_image: i.primary_image, latest_image: i.latest_image };
             html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 text-center font-bold text-lg ${x<3?'text-yellow-500':''}">${x+1}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(eq)}</td><td class="px-4 py-3 font-medium">${renderClickableName(eq)}</td><td class="px-4 py-3">${i.category}</td><td class="px-4 py-3 text-center">${i.tx_count}</td><td class="px-4 py-3 text-right font-bold text-blue-600">${i.total_qty}</td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderDeadStock(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>#</th><th class="text-center">รูป</th><th>ชื่ออุปกรณ์</th><th>ประเภท</th><th class="text-right">คงเหลือ</th><th class="text-center">เคลื่อนไหวล่าสุด</th><th class="text-center">นิ่ง (วัน)</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="7" class="p-8 text-center text-green-500">ไม่มีสินค้าค้างสต็อกเกินกำหนด</td></tr></tbody>';
        data.forEach((i,x) => {
            html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3">${x+1}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3">${get(i,'category.name')}</td><td class="px-4 py-3 text-right">${i.quantity}</td><td class="px-4 py-3 text-center text-sm">${formatDate(i.last_movement)}</td><td class="px-4 py-3 text-center"><span class="px-3 py-1 rounded-full bg-red-100 text-red-800 font-bold border border-red-200">${i.days_silent} วัน</span></td></tr>`;
        });
        return html + '</tbody>';
    }

    function renderAuditLogs(data) {
        let html = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="px-4 py-3">วันที่/เวลา</th><th class="px-4 py-3">ผู้ดำเนินการ</th><th class="px-4 py-3">การกระทำ</th><th class="px-4 py-3">รายละเอียด</th></tr></thead><tbody>`;
        if (!data.length) return html + '<tr><td colspan="4" class="p-8 text-center text-gray-500">ไม่พบประวัติการแก้ไขข้อมูล</td></tr></tbody>';
        data.forEach(i => {
            html += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 text-xs whitespace-nowrap">${formatDate(i.created_at)}</td><td class="px-4 py-3 font-medium">${get(i,'user.fullname', 'System/Unknown')}</td><td class="px-4 py-3 font-bold text-blue-600">${i.action}</td><td class="px-4 py-3 text-sm text-gray-600">${i.details||'-'}</td></tr>`;
        });
        return html + '</tbody>';
    }

    // --- Events ---
    if(reportTypeSelect) {
        reportTypeSelect.addEventListener('change', function() {
            if (this.value === 'user_activity_report') { if(userFilterContainer) userFilterContainer.style.display = 'block'; } 
            else { if(userFilterContainer) userFilterContainer.style.display = 'none'; }
        });
    }
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        generateReport();
    });

    if (initialReportType) {
        if (reportTypeSelect) {
            reportTypeSelect.value = initialReportType;
            reportTypeSelect.dispatchEvent(new Event('change'));
            generateReport();
        }
    }
});