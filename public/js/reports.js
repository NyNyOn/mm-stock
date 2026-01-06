document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('report-form');
    if (!form) return;

    // --- Elements ---
    const resultsContainer = document.getElementById('report-results-container');
    const reportTable = document.getElementById('report-table');
    const reportMobileResults = document.getElementById('report-mobile-results');
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

        if (spinner) spinner.style.display = 'inline-block';
        if (searchIcon) searchIcon.style.display = 'none';
        if (submitButton) submitButton.disabled = true;
        if (resultsContainer) resultsContainer.style.display = 'none';

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

                if (resultsContainer) resultsContainer.style.display = 'block';
                renderReport(reportType, reportData);
                resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            })
            .catch(error => {
                // console.error('Fetch Error:', error); // Optional: keep critical error logging
                Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถสร้างรายงานได้: ' + error.message, 'error');
            })
            .finally(() => {
                if (spinner) spinner.style.display = 'none';
                if (searchIcon) searchIcon.style.display = 'inline-block';
                if (submitButton) submitButton.disabled = false;
                if (pdfButton && resultsContainer.style.display !== 'none') {
                    pdfButton.style.display = 'inline-block';
                }
            });
    }

    function renderReport(type, data) {
        let result = { table: '', mobile: '' };
        let title = reportTypeSelect.options[reportTypeSelect.selectedIndex].text;
        let subtitle = `ข้อมูล ณ วันที่: ${new Date().toLocaleDateString('th-TH')}`;

        switch (type) {
            case 'stock_summary': result = renderStockSummary(data); break;
            case 'transaction_history': result = renderTransactionHistory(data); break;
            case 'borrow_report': result = renderBorrowReport(data); break;
            case 'low_stock': result = renderLowStockReport(data); break;
            case 'out_of_stock': result = renderOutOfStockReport(data); break;
            case 'warranty': result = renderWarrantyReport(data); break;
            case 'maintenance_report': result = renderMaintenanceReport(data); break;
            case 'po_report': result = renderPoReport(data); break;
            case 'disposal_report': result = renderDisposalReport(data); break;
            case 'consumable_return_report': result = renderConsumableReturnReport(data); break;
            case 'user_activity_report': result = renderUserActivityReport(data); break;

            case 'inventory_valuation': result = renderInventoryValuation(data); break;
            case 'department_cost': result = renderDepartmentCost(data); break;
            case 'top_movers': result = renderTopMovers(data); break;
            case 'dead_stock': result = renderDeadStock(data); break;
            case 'audit_logs': result = renderAuditLogs(data); break;

            default:
                result.table = '<tr><td colspan="6" class="p-8 text-center text-red-500">รูปแบบรายงานไม่ถูกต้อง</td></tr>';
                result.mobile = '<div class="p-8 text-center text-red-500 bg-white rounded-xl">รูปแบบรายงานไม่ถูกต้อง</div>';
        }

        reportTitle.textContent = title;
        reportSubtitle.textContent = subtitle;

        if (reportTable) reportTable.innerHTML = result.table;
        if (reportMobileResults) reportMobileResults.innerHTML = result.mobile;
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
        // Table
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="px-4 py-3">#</th><th class="px-4 py-3 text-center">รูปภาพ</th><th class="px-4 py-3">ชื่ออุปกรณ์</th><th class="px-4 py-3">S/N</th><th class="px-4 py-3">ประเภท</th><th class="px-4 py-3">สถานที่</th><th class="px-4 py-3 text-right">จำนวน</th><th class="px-4 py-3 text-center">สถานะ</th></tr></thead><tbody>`;

        // Mobile
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="8" class="p-8 text-center text-gray-500">ไม่พบข้อมูล</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-500 bg-white rounded-xl">ไม่พบข้อมูล</div>'
            };
        }

        data.forEach((i, x) => {
            // Table Row
            table += `<tr class="border-b hover:bg-gray-50 transition"><td class="px-4 py-3">${x + 1}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3 font-mono text-xs">${i.serial_number || '-'}</td><td class="px-4 py-3">${get(i, 'category.name')}</td><td class="px-4 py-3">${get(i, 'location.name')}</td><td class="px-4 py-3 text-right font-bold">${i.quantity} ${get(i, 'unit.name', '')}</td><td class="px-4 py-3 text-center">${getBadge(i.status)}</td></tr>`;

            // Mobile Card
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 relative overflow-hidden">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0">${renderImageCell(i)}</div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${itemHasLink(i) ? renderClickableName(i) : i.name}</h4>
                            <p class="text-xs text-gray-500 mb-1">S/N: ${i.serial_number || '-'}</p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded border border-blue-100">${get(i, 'category.name')}</span>
                                <div class="text-right">
                                    <span class="block text-lg font-bold text-indigo-600 leading-none">${i.quantity}</span>
                                    <span class="text-[10px] text-gray-400">${get(i, 'unit.name', 'ชิ้น')}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                     <div class="mt-3 pt-3 border-t border-gray-50 flex justify-between items-center">
                        <div class="text-xs text-gray-500"><i class="fas fa-map-marker-alt mr-1"></i> ${get(i, 'location.name')}</div>
                        <div>${getBadge(i.status)}</div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    // Helper to check if item has ID for linking
    function itemHasLink(i) { return !!i.id; }

    function renderTransactionHistory(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="px-4 py-3">วันที่</th><th class="px-4 py-3 text-center">รูป</th><th>อุปกรณ์</th><th>รายการ</th><th class="text-right">จำนวน</th><th>ผู้ทำ</th><th>หมายเหตุ</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="7" class="p-8 text-center">ไม่พบข้อมูล</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-500 bg-white rounded-xl">ไม่พบข้อมูล</div>'
            };
        }

        data.forEach(tx => {
            const eq = tx.equipment || { id: 0, name: 'Deleted' };
            const typeClass = tx.quantity_change >= 0 ? 'text-green-600' : 'text-red-600';
            const changeSymbol = tx.quantity_change >= 0 ? '+' : '';

            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 text-xs">${formatDate(tx.transaction_date)}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(eq)}</td><td class="px-4 py-3 font-medium">${eq.id ? renderClickableName(eq) : eq.name}</td><td class="px-4 py-3 font-bold uppercase">${tx.type}</td><td class="px-4 py-3 text-right font-mono font-bold ${typeClass}">${Math.abs(tx.quantity_change)}</td><td class="px-4 py-3">${get(tx, 'user.fullname')}</td><td class="px-4 py-3 text-sm">${tx.notes || '-'}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex gap-4">
                    <div class="flex-shrink-0">${renderImageCell(eq)}</div>
                    <div class="flex-1 min-w-0">
                         <div class="flex justify-between items-start mb-1">
                             <div class="text-xs text-gray-500">${formatDate(tx.transaction_date)}</div>
                             <div class="text-xs font-bold ${typeClass}">${changeSymbol}${tx.quantity_change}</div>
                         </div>
                         <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${eq.id ? renderClickableName(eq) : eq.name}</h4>
                         <div class="flex justify-between items-center text-xs">
                             <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded uppercase font-bold tracking-wider text-[10px]">${tx.type}</span>
                             <span class="text-gray-500 truncate max-w-[100px]"><i class="far fa-user mr-1"></i> ${get(tx, 'user.fullname')}</span>
                         </div>
                         ${tx.notes ? `<div class="mt-2 text-xs text-gray-500 bg-gray-50 p-2 rounded">${tx.notes}</div>` : ''}
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderBorrowReport(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>วันที่</th><th class="text-center">รูป</th><th>อุปกรณ์</th><th>S/N</th><th>ผู้ยืม</th><th>สถานะ</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="6" class="p-8 text-center">ว่าง</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-400 bg-white rounded-xl">ไม่มีรายการยืมขณะนี้</div>'
            };
        }

        data.forEach(tx => {
            const eq = tx.equipment || {};
            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3">${formatDate(tx.transaction_date)}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(eq)}</td><td class="px-4 py-3 font-medium">${eq.id ? renderClickableName(eq) : '-'}</td><td class="px-4 py-3 text-xs font-mono">${get(tx, 'equipment.serial_number')}</td><td class="px-4 py-3">${get(tx, 'user.fullname')}</td><td class="px-4 py-3 text-center">${getBadge('borrowed')}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-blue-100 flex gap-4">
                    <div class="flex-shrink-0">${renderImageCell(eq)}</div>
                    <div class="flex-1 min-w-0">
                         <div class="flex justify-between items-start mb-1">
                             <div class="text-xs text-gray-500"><i class="far fa-calendar-alt mr-1"></i> ${formatDate(tx.transaction_date)}</div>
                             <div>${getBadge('borrowed')}</div>
                         </div>
                         <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${eq.id ? renderClickableName(eq) : '-'}</h4>
                         <p class="text-xs text-gray-500 mb-1">S/N: ${get(tx, 'equipment.serial_number') || '-'}</p>
                         <div class="flex items-center text-xs text-blue-600 font-medium">
                             <i class="fas fa-user mr-1.5"></i> ${get(tx, 'user.fullname')}
                         </div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderLowStockReport(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="text-center">รูป</th><th>ชื่อ</th><th>ประเภท</th><th class="text-right">คงเหลือ</th><th class="text-right">ขั้นต่ำ</th><th>สถานะ</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="6" class="p-8 text-center">ไม่มีสินค้าใกล้หมด</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-green-500 bg-white rounded-xl">ยอดเยี่ยม! ไม่มีสินค้าใกล้หมด</div>'
            };
        }

        data.forEach(i => {
            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3">${get(i, 'category.name')}</td><td class="px-4 py-3 text-right font-bold text-orange-500">${i.quantity}</td><td class="px-4 py-3 text-right">${i.min_stock}</td><td class="px-4 py-3 text-center">${getBadge('low_stock')}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-orange-100 flex gap-4">
                    <div class="flex-shrink-0">${renderImageCell(i)}</div>
                    <div class="flex-1 min-w-0">
                         <div class="flex justify-between items-start mb-1">
                             <span class="bg-orange-50 text-orange-700 text-[10px] px-2 py-0.5 rounded font-bold">Low Stock</span>
                             <div class="text-right">
                                 <span class="text-lg font-bold text-orange-600 leading-none">${i.quantity}</span>
                                 <span class="text-[10px] text-gray-400">/ ${i.min_stock}</span>
                             </div>
                         </div>
                         <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${renderClickableName(i)}</h4>
                         <div class="text-xs text-gray-500">${get(i, 'category.name')}</div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderOutOfStockReport(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="text-center">รูป</th><th>ชื่อ</th><th>ประเภท</th><th class="text-right">คงเหลือ</th><th>สถานที่</th><th>สถานะ</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="6" class="p-8 text-center text-green-500">เยี่ยมมาก! ไม่มีสินค้าหมดสต็อก</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-green-500 bg-white rounded-xl">เยี่ยมมาก! ไม่มีสินค้าหมดสต็อก</div>'
            };
        }

        data.forEach(i => {
            // Table
            table += `<tr class="border-b hover:bg-red-50"><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3">${get(i, 'category.name')}</td><td class="px-4 py-3 text-right font-bold text-red-600">${i.quantity}</td><td class="px-4 py-3">${get(i, 'location.name')}</td><td class="px-4 py-3 text-center">${getBadge('out_of_stock')}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-red-200 flex gap-4 ring-1 ring-red-50">
                    <div class="flex-shrink-0">${renderImageCell(i)}</div>
                    <div class="flex-1 min-w-0">
                         <div class="flex justify-between items-start mb-1">
                             <span class="bg-red-100 text-red-700 text-[10px] px-2 py-0.5 rounded font-bold">Out of Stock</span>
                             <span class="text-lg font-bold text-red-600 leading-none">0</span>
                         </div>
                         <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${renderClickableName(i)}</h4>
                         <div class="flex justify-between items-center text-xs text-gray-500">
                             <span>${get(i, 'category.name')}</span>
                             <span><i class="fas fa-map-marker-alt mr-1"></i> ${get(i, 'location.name')}</span>
                         </div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderWarrantyReport(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="text-center">รูป</th><th>ชื่อ</th><th>S/N</th><th class="text-center">หมดประกัน</th><th class="text-center">สถานะ</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="5" class="p-8 text-center">ไม่พบ</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-400 bg-white rounded-xl">ไม่พบรายการสินค้าใกล้หมดประกัน</div>'
            };
        }

        data.forEach(i => {
            const wd = new Date(i.warranty_date);
            const diff = Math.ceil((wd - new Date()) / (1000 * 60 * 60 * 24));
            let st = diff < 0 ? 'หมดอายุ' : 'เหลือ ' + diff + ' วัน';
            let cl = diff < 0 ? 'text-red-600 font-bold' : (diff < 30 ? 'text-orange-500' : 'text-green-600');

            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3 text-xs font-mono">${i.serial_number}</td><td class="px-4 py-3 text-center">${wd.toLocaleDateString('th-TH')}</td><td class="px-4 py-3 text-center ${cl}">${st}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex gap-4">
                    <div class="flex-shrink-0">${renderImageCell(i)}</div>
                    <div class="flex-1 min-w-0">
                         <div class="flex justify-between items-start mb-1">
                             <span class="text-[10px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded">S/N: ${i.serial_number}</span>
                             <span class="text-xs ${cl}">${st}</span>
                         </div>
                         <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${renderClickableName(i)}</h4>
                         <div class="text-xs text-gray-500">หมดประกัน: ${wd.toLocaleDateString('th-TH')}</div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderMaintenanceReport(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>วันที่</th><th class="text-center">รูป</th><th>อุปกรณ์</th><th>อาการ</th><th>ผู้แจ้ง</th><th>สถานะ</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="6" class="p-8 text-center">ไม่พบ</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-400 bg-white rounded-xl">ไม่พบรายการซ่อมบำรุง</div>'
            };
        }

        data.forEach(l => {
            const eq = l.equipment || {};

            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 whitespace-nowrap text-xs">${formatDate(l.created_at)}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(eq)}</td><td class="px-4 py-3 font-medium">${eq.id ? renderClickableName(eq) : '-'}</td><td class="px-4 py-3 text-sm">${l.problem_description}</td><td class="px-4 py-3">${get(l, 'reported_by.fullname')}</td><td class="px-4 py-3 text-center">${getBadge(l.status)}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-l-4 border-l-yellow-400 border-gray-100 flex gap-4">
                    <div class="flex-shrink-0">${renderImageCell(eq)}</div>
                    <div class="flex-1 min-w-0">
                         <div class="flex justify-between items-start mb-1">
                             <div class="text-xs text-gray-500">${formatDate(l.created_at)}</div>
                             <div>${getBadge(l.status)}</div>
                         </div>
                         <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${eq.id ? renderClickableName(eq) : '-'}</h4>
                         <div class="text-xs text-gray-600 bg-yellow-50 p-2 rounded mb-2 border border-yellow-100">
                             <span class="font-bold">อาการ:</span> ${l.problem_description}
                         </div>
                         <div class="text-xs text-gray-400 text-right">แจ้งโดย: ${get(l, 'reported_by.fullname')}</div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderPoReport(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>PO No.</th><th>ประเภท</th><th>วันที่</th><th>ผู้สั่ง</th><th>สถานะ</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="5" class="p-8 text-center">ไม่พบ</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-400 bg-white rounded-xl">ไม่พบรายการใบสั่งซื้อ</div>'
            };
        }

        data.forEach(p => {
            // Table
            table += `<tr class="border-b"><td class="px-4 py-3 font-mono">#${p.id}</td><td class="px-4 py-3 font-bold">${p.type}</td><td class="px-4 py-3 text-xs">${formatDate(p.ordered_at)}</td><td class="px-4 py-3 text-sm">${get(p, 'ordered_by.fullname')}</td><td class="px-4 py-3 text-center">${getBadge(p.status)}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-indigo-100 relative">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-mono font-bold text-indigo-800 bg-indigo-50 px-2 py-1 rounded">PO #${p.id}</span>
                        <div>${getBadge(p.status)}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm mb-2">
                        <div>
                            <span class="text-xs text-gray-400 block">ประเภท</span>
                            <span class="font-bold text-gray-700">${p.type}</span>
                        </div>
                        <div class="text-right">
                             <span class="text-xs text-gray-400 block">วันที่สั่ง</span>
                             <span class="text-gray-700">${formatDate(p.ordered_at)}</span>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 border-t border-gray-50 pt-2 flex justify-between">
                        <span>ผู้สั่งซื้อ: ${get(p, 'ordered_by.fullname')}</span>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderDisposalReport(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="text-center">รูป</th><th>ชื่อ</th><th>ประเภท</th><th>วันที่ตัดจำหน่าย</th><th>สถานะ</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="5" class="p-8 text-center">ไม่พบ</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-400 bg-white rounded-xl">ไม่พบรายการตัดจำหน่าย</div>'
            };
        }

        data.forEach(i => {
            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3">${get(i, 'category.name')}</td><td class="px-4 py-3">${formatDate(i.updated_at)}</td><td class="px-4 py-3 text-center">${getBadge(i.status)}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex gap-4 opacity-75">
                    <div class="flex-shrink-0 grayscale">${renderImageCell(i)}</div>
                    <div class="flex-1 min-w-0">
                         <div class="flex justify-between items-start mb-1">
                             <div class="text-xs text-gray-500">${formatDate(i.updated_at)}</div>
                             <div>${getBadge(i.status)}</div>
                         </div>
                         <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${renderClickableName(i)}</h4>
                         <div class="text-xs text-gray-500">${get(i, 'category.name')}</div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderConsumableReturnReport(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>วันที่</th><th class="text-center">รูป</th><th>อุปกรณ์</th><th>ผู้คืน</th><th>ผู้อนุมัติ</th><th>สถานะ</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="6" class="p-8 text-center">ไม่พบ</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-400 bg-white rounded-xl">ไม่พบข้อมูล</div>'
            };
        }

        data.forEach(l => {
            const eq = get(l, 'original_transaction.equipment', {});
            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 whitespace-nowrap text-xs">${formatDate(l.created_at)}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(eq)}</td><td class="px-4 py-3 font-medium">${eq.id ? renderClickableName(eq) : '-'}</td><td class="px-4 py-3">${get(l, 'requester.fullname')}</td><td class="px-4 py-3">${get(l, 'approver.fullname')}</td><td class="px-4 py-3 text-center">${getBadge(l.status)}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex gap-4">
                    <div class="flex-shrink-0">${renderImageCell(eq)}</div>
                    <div class="flex-1 min-w-0">
                         <div class="flex justify-between items-start mb-1">
                             <div class="text-xs text-gray-500">${formatDate(l.created_at)}</div>
                             <div>${getBadge(l.status)}</div>
                         </div>
                         <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${eq.id ? renderClickableName(eq) : '-'}</h4>
                         <div class="flex justify-between items-center text-xs text-gray-500 mt-2">
                             <span><i class="fas fa-user-tag mr-1"></i> ผู้คืน: ${get(l, 'requester.fullname').split(' ')[0]}</span>
                             <span><i class="fas fa-user-check mr-1"></i> อนุมัติ: ${get(l, 'approver.fullname').split(' ')[0]}</span>
                         </div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderUserActivityReport(data) { return renderTransactionHistory(data); }

    function renderInventoryValuation(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>#</th><th class="text-center">รูป</th><th>ชื่ออุปกรณ์</th><th>ประเภท</th><th class="text-right">คงเหลือ</th><th class="text-right">ราคา/หน่วย</th><th class="text-right">มูลค่ารวม</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="7" class="p-8 text-center">ไม่พบข้อมูล</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-400 bg-white rounded-xl">ไม่พบข้อมูล</div>'
            };
        }

        let totalVal = 0;
        data.forEach((i, x) => {
            totalVal += i.total_value;
            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3">${x + 1}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3">${get(i, 'category.name')}</td><td class="px-4 py-3 text-right">${i.quantity}</td><td class="px-4 py-3 text-right">${formatCurrency(i.price || 0)}</td><td class="px-4 py-3 text-right font-bold text-green-600">${formatCurrency(i.total_value)}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex gap-4">
                    <div class="flex-shrink-0">${renderImageCell(i)}</div>
                    <div class="flex-1 min-w-0">
                         <div class="flex justify-between items-start mb-1">
                             <h4 class="font-bold text-gray-800 text-sm truncate">${renderClickableName(i)}</h4>
                             <span class="font-bold text-green-600 text-sm whitespace-nowrap">${formatCurrency(i.total_value)}</span>
                         </div>
                         <div class="text-xs text-gray-500 mb-1">${get(i, 'category.name')}</div>
                         <div class="flex justify-between text-xs text-gray-400 border-t border-gray-50 pt-1 mt-1">
                             <span>คงเหลือ: ${i.quantity}</span>
                             <span>ราคา/หน่วย: ${formatCurrency(i.price || 0)}</span>
                         </div>
                    </div>
                </div>
            `;
        });
        table += `<tr class="bg-gray-100 font-bold"><td colspan="6" class="px-4 py-3 text-right">รวมมูลค่าทั้งหมด</td><td class="px-4 py-3 text-right text-green-700 text-lg">${formatCurrency(totalVal)}</td></tr>`;
        mobile = `<div class="bg-green-50 p-4 rounded-xl shadow-sm border border-green-200 mb-4 text-center">
                    <div class="text-sm text-green-800 font-medium">รวมมูลค่าทั้งหมด</div>
                    <div class="text-2xl font-bold text-green-700">${formatCurrency(totalVal)}</div>
                  </div>` + mobile;

        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderDepartmentCost(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>#</th><th>ผู้ใช้งาน</th><th>แผนก</th><th class="text-center">จำนวนครั้ง</th><th class="text-right">ชิ้นรวม</th><th class="text-right">มูลค่า (บาท)</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="6" class="p-8 text-center">ไม่พบข้อมูล</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-400 bg-white rounded-xl">ไม่พบข้อมูล</div>'
            };
        }

        data.forEach((i, x) => {
            // Table
            table += `<tr class="border-b"><td class="px-4 py-3">${x + 1}</td><td class="px-4 py-3 font-medium">${i.user_name}</td><td class="px-4 py-3">${i.department}</td><td class="px-4 py-3 text-center">${i.item_count}</td><td class="px-4 py-3 text-right">${i.total_qty}</td><td class="px-4 py-3 text-right font-bold text-red-500">${formatCurrency(i.total_cost)}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex justify-between items-center">
                    <div class="flex-1">
                        <div class="font-bold text-gray-800 text-sm">${i.user_name}</div>
                        <div class="text-xs text-gray-500">${i.department}</div>
                        <div class="mt-1 text-xs text-gray-400">เบิก: ${i.item_count} ครั้ง | รวม: ${i.total_qty} ชิ้น</div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-red-600 text-base">${formatCurrency(i.total_cost)}</div>
                        <div class="text-[10px] text-gray-400">บาท</div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderTopMovers(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="text-center">อันดับ</th><th class="text-center">รูป</th><th>ชื่ออุปกรณ์</th><th>ประเภท</th><th class="text-center">ครั้งที่เบิก</th><th class="text-right">ยอดเบิก (ชิ้น)</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="6" class="p-8 text-center">ไม่พบข้อมูล</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-400 bg-white rounded-xl">ไม่พบข้อมูล</div>'
            };
        }

        data.forEach((i, x) => {
            const eq = { id: i.equipment_id, name: i.equipment_name, primary_image: i.primary_image, latest_image: i.latest_image };
            const rankColor = x < 3 ? 'text-yellow-500' : 'text-gray-500';

            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 text-center font-bold text-lg ${rankColor}">${x + 1}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(eq)}</td><td class="px-4 py-3 font-medium">${renderClickableName(eq)}</td><td class="px-4 py-3">${i.category}</td><td class="px-4 py-3 text-center">${i.tx_count}</td><td class="px-4 py-3 text-right font-bold text-blue-600">${i.total_qty}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex gap-4 overflow-hidden relative">
                    <div class="absolute top-0 right-0 w-8 h-8 flex items-center justify-center font-bold text-white text-xs rounded-bl-xl ${x < 3 ? 'bg-yellow-400' : 'bg-gray-400'}">
                        #${x + 1}
                    </div>
                    <div class="flex-shrink-0 mt-2">${renderImageCell(eq)}</div>
                    <div class="flex-1 min-w-0 pr-8">
                         <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${renderClickableName(eq)}</h4>
                         <div class="text-xs text-gray-500 mb-2">${i.category}</div>
                         <div class="flex justify-between items-center bg-gray-50 p-2 rounded">
                             <div class="text-center flex-1 border-r border-gray-200">
                                 <span class="block text-xs text-gray-400">จำนวนครั้ง</span>
                                 <span class="font-bold text-gray-700">${i.tx_count}</span>
                             </div>
                             <div class="text-center flex-1">
                                 <span class="block text-xs text-gray-400">ยอดเบิก</span>
                                 <span class="font-bold text-blue-600">${i.total_qty}</span>
                             </div>
                         </div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderDeadStock(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th>#</th><th class="text-center">รูป</th><th>ชื่ออุปกรณ์</th><th>ประเภท</th><th class="text-right">คงเหลือ</th><th class="text-center">เคลื่อนไหวล่าสุด</th><th class="text-center">นิ่ง (วัน)</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="7" class="p-8 text-center text-green-500">ไม่มีสินค้าค้างสต็อกเกินกำหนด</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-green-500 bg-white rounded-xl">ไม่มีสินค้าค้างสต็อกเกินกำหนด</div>'
            };
        }

        data.forEach((i, x) => {
            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3">${x + 1}</td><td class="px-4 py-3 flex justify-center">${renderImageCell(i)}</td><td class="px-4 py-3 font-medium">${renderClickableName(i)}</td><td class="px-4 py-3">${get(i, 'category.name')}</td><td class="px-4 py-3 text-right">${i.quantity}</td><td class="px-4 py-3 text-center text-sm">${formatDate(i.last_movement)}</td><td class="px-4 py-3 text-center"><span class="px-3 py-1 rounded-full bg-red-100 text-red-800 font-bold border border-red-200">${i.days_silent} วัน</span></td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-red-200 flex gap-4">
                    <div class="flex-shrink-0">${renderImageCell(i)}</div>
                    <div class="flex-1 min-w-0">
                         <div class="flex justify-between items-start mb-1">
                             <span class="bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded font-bold">นิ่ง ${i.days_silent} วัน</span>
                             <span class="text-gray-400 text-xs">ล่าสุด: ${formatDate(i.last_movement).split(' ')[0]}</span>
                         </div>
                         <h4 class="font-bold text-gray-800 text-sm mb-1 truncate">${renderClickableName(i)}</h4>
                         <div class="flex justify-between items-center text-xs text-gray-500">
                             <span>${get(i, 'category.name')}</span>
                             <span>คงเหลือ: ${i.quantity}</span>
                         </div>
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    function renderAuditLogs(data) {
        let table = `<thead class="bg-gray-50 text-xs text-gray-700 uppercase"><tr><th class="px-4 py-3">วันที่/เวลา</th><th class="px-4 py-3">ผู้ดำเนินการ</th><th class="px-4 py-3">การกระทำ</th><th class="px-4 py-3">รายละเอียด</th></tr></thead><tbody>`;
        let mobile = '';

        if (!data.length) {
            return {
                table: table + '<tr><td colspan="4" class="p-8 text-center text-gray-500">ไม่พบประวัติการแก้ไขข้อมูล</td></tr></tbody>',
                mobile: '<div class="p-8 text-center text-gray-500 bg-white rounded-xl">ไม่พบประวัติการแก้ไขข้อมูล</div>'
            };
        }

        data.forEach(i => {
            // Table
            table += `<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3 text-xs whitespace-nowrap">${formatDate(i.created_at)}</td><td class="px-4 py-3 font-medium">${get(i, 'user.fullname', 'System/Unknown')}</td><td class="px-4 py-3 font-bold text-blue-600">${i.action}</td><td class="px-4 py-3 text-sm text-gray-600">${i.details || '-'}</td></tr>`;

            // Mobile
            mobile += `
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-start mb-2">
                        <span class="font-bold text-blue-600 text-sm">${i.action}</span>
                        <span class="text-xs text-gray-400">${formatDate(i.created_at)}</span>
                    </div>
                    <div class="text-xs text-gray-600 mb-2">${i.details || '-'}</div>
                    <div class="flex items-center text-xs text-gray-500 bg-gray-50 p-2 rounded">
                        <i class="fas fa-user-shield mr-2"></i> ${get(i, 'user.fullname', 'System/Unknown')}
                    </div>
                </div>
            `;
        });
        return { table: table + '</tbody>', mobile: mobile };
    }

    // --- Events ---
    if (reportTypeSelect) {
        reportTypeSelect.addEventListener('change', function () {
            if (this.value === 'user_activity_report') { if (userFilterContainer) userFilterContainer.style.display = 'block'; }
            else { if (userFilterContainer) userFilterContainer.style.display = 'none'; }
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