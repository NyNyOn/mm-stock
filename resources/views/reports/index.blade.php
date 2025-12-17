@extends('layouts.app')

@section('header', '📊 รายงาน')
@section('subtitle', 'ศูนย์รวมข้อมูลและการวิเคราะห์ระบบ')

@section('content')
<div class="space-y-6 page animate-slide-up-soft">

    {{-- Filter Section --}}
    <div id="report-filters-card" class="p-6 soft-card rounded-2xl gentle-shadow">
        <div class="flex items-center mb-4">
            <div class="flex items-center justify-center w-10 h-10 mr-4 bg-blue-100 rounded-full">
                <i class="text-blue-600 fas fa-chart-pie"></i>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-800">สร้างรายงาน</h3>
                <p class="text-sm text-gray-500">เลือกประเภทรายงานและเงื่อนไขที่ต้องการวิเคราะห์</p>
            </div>
        </div>
        <form id="report-form" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
            @csrf
            
            {{-- 1. ประเภทรายงาน --}}
            <div class="lg:col-span-1">
                <label for="report_type" class="block mb-1 text-sm font-bold text-gray-700">📑 ประเภทรายงาน</label>
                <select id="report_type" name="report_type" required class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-400 focus:border-transparent gentle-shadow">
                    <option value="">-- กรุณาเลือก --</option>
                    
                    <optgroup label="📦 คลังสินค้าและอุปกรณ์">
                        <option value="stock_summary" @selected(old('report_type', $initialReportType) == 'stock_summary')>📊 สรุปสต๊อกคงคลัง (Stock Balance)</option>
                        <option value="low_stock" @selected(old('report_type', $initialReportType) == 'low_stock')>⚠️ สินค้าใกล้หมด (Low Stock)</option>
                        <option value="out_of_stock" @selected(old('report_type', $initialReportType) == 'out_of_stock')>⛔ สินค้าหมด (Out of Stock)</option>
                        <option value="dead_stock" @selected(old('report_type', $initialReportType) == 'dead_stock')>🕸️ สินค้าไม่เคลื่อนไหว (Deadstock)</option>
                        <option value="warranty" @selected(old('report_type', $initialReportType) == 'warranty')>🛡️ รายงานประกันใกล้หมด (Warranty)</option>
                    </optgroup>

                    <optgroup label="💰 การเงินและต้นทุน">
                        <option value="inventory_valuation" @selected(old('report_type', $initialReportType) == 'inventory_valuation')>💵 มูลค่าสินค้าคงคลังรวม (Valuation)</option>
                        <option value="department_cost" @selected(old('report_type', $initialReportType) == 'department_cost')>🏢 สรุปยอดเบิกแยกตามแผนก (Cost Usage)</option>
                    </optgroup>

                    <optgroup label="📈 สถิติและการใช้งาน">
                        <option value="transaction_history" @selected(old('report_type', $initialReportType) == 'transaction_history')>🔄 ประวัติธุรกรรมทั้งหมด (All Logs)</option>
                        <option value="top_movers" @selected(old('report_type', $initialReportType) == 'top_movers')>🔥 10 อันดับสินค้าเบิกสูงสุด (Top Movers)</option>
                        <option value="borrow_report" @selected(old('report_type', $initialReportType) == 'borrow_report')>⏳ รายการที่กำลังถูกยืม (Active Borrow)</option>
                        <option value="user_activity_report" @selected(old('report_type', $initialReportType) == 'user_activity_report')>👤 พฤติกรรมการใช้งานรายบุคคล</option>
                    </optgroup>

                    <optgroup label="🛠️ การจัดการและซ่อมบำรุง">
                        <option value="maintenance_report" @selected(old('report_type', $initialReportType) == 'maintenance_report')>🔧 ประวัติการซ่อมบำรุง</option>
                        <option value="po_report" @selected(old('report_type', $initialReportType) == 'po_report')>🛒 รายงานใบสั่งซื้อ (Purchasing)</option>
                        <option value="disposal_report" @selected(old('report_type', $initialReportType) == 'disposal_report')>🗑️ รายการตัดจำหน่าย (Disposal)</option>
                        <option value="consumable_return_report" @selected(old('report_type', $initialReportType) == 'consumable_return_report')>📥 รายงานคืนวัสดุสิ้นเปลือง</option>
                    </optgroup>

                    <optgroup label="👮 ความปลอดภัยและตรวจสอบ">
                        <option value="audit_logs" @selected(old('report_type', $initialReportType) == 'audit_logs')>📝 ประวัติการแก้ไขข้อมูล (Audit Logs)</option>
                    </optgroup>
                </select>
            </div>

            {{-- 2. ตัวกรองวันที่ --}}
            <div>
                <label for="start_date" class="block mb-1 text-sm font-medium text-gray-700">วันที่เริ่มต้น</label>
                <input type="date" id="start_date" name="start_date" class="w-full px-4 py-3 text-sm text-gray-700 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-400">
            </div>
            <div>
                <label for="end_date" class="block mb-1 text-sm font-medium text-gray-700">วันที่สิ้นสุด</label>
                <input type="date" id="end_date" name="end_date" class="w-full px-4 py-3 text-sm text-gray-700 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-400">
            </div>

            {{-- 3. ตัวกรองอื่นๆ --}}
            <div>
                <label for="category_id" class="block mb-1 text-sm font-medium text-gray-700">ประเภท</label>
                <select id="category_id" name="category_id" class="w-full px-4 py-3 text-sm text-gray-700 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-400">
                    <option value="">-- ทุกประเภท --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="location_id" class="block mb-1 text-sm font-medium text-gray-700">สถานที่</label>
                <select id="location_id" name="location_id" class="w-full px-4 py-3 text-sm text-gray-700 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-400">
                    <option value="">-- ทุกสถานที่ --</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- User Filter --}}
            <div id="user-filter-container" style="display: none;" class="md:col-span-2 lg:col-span-1">
                <label for="user_id" class="block mb-1 text-sm font-medium text-gray-700">ผู้ใช้งาน (เฉพาะเจาะจง)</label>
                <select id="user_id" name="user_id" class="w-full px-4 py-3 text-sm text-gray-700 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-400">
                    <option value="">-- เลือกผู้ใช้งาน --</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->fullname }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Submit Button --}}
            <div class="flex items-end md:col-span-2 lg:col-span-5">
                <button type="submit" class="w-full px-6 py-3 font-bold text-white transition-all shadow-lg bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl hover:shadow-xl hover:-translate-y-0.5 active:translate-y-0">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                    <i class="mr-2 fas fa-search"></i> ออกรายงาน (Generate Report)
                </button>
            </div>
        </form>
    </div>

    {{-- Report Display Section --}}
    <div id="report-results-container" class="soft-card rounded-2xl gentle-shadow" style="display: none;">
         
         <div class="flex flex-wrap items-center justify-between p-5 border-b border-gray-100 bg-gray-50 rounded-t-2xl">
            <div>
                <h3 id="report-title" class="text-xl font-bold text-gray-800">ผลลัพธ์รายงาน</h3>
                <p id="report-subtitle" class="text-sm text-gray-500">ข้อมูล ณ ปัจจุบัน</p>
            </div>
            
            <div class="mt-2 md:mt-0">
                @php
                    $user = Auth::user();
                    $superAdminId = (int)config('app.super_admin_id', 9);
                    
                    // ✅ แก้ไข: ใช้ hasPermissionTo แทนการเช็ค Role Slug เพื่อความเสถียรข้าม Database
                    $canExportPdf = ($user->id === $superAdminId) || $user->hasPermissionTo('report:export');
                @endphp

                @if($canExportPdf)
                    <button id="export-pdf-button" type="button" class="px-4 py-2 font-medium text-white transition-all bg-red-500 rounded-lg shadow hover:bg-red-600 hover:shadow-md" style="display: none;">
                        <i class="mr-2 fas fa-file-pdf"></i> Download PDF
                    </button>
                @endif
            </div>
         </div>
         
        <div class="p-5 overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 align-middle" id="report-table">
                {{-- Content from JS --}}
            </table>
        </div>
    </div>
</div>

{{-- ✅ Include Equipment Details Modal --}}
@include('partials.modals.equipment-details')

@endsection

@push('scripts')
    <script>
        window.initialReportType = @json($initialReportType ?? null);
    </script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.10/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.10/vfs_fonts.js"></script>

    {{-- ✅ Load Equipment JS for Modal Functionality --}}
    <script src="{{ asset('js/equipment.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pdfButton = document.getElementById('export-pdf-button');
            const reportResultsContainer = document.getElementById('report-results-container');
            
            if (typeof pdfMake === 'undefined') return;

            pdfMake.fonts = {
                THSarabun: {
                    normal: '{{ asset('fonts/THSarabunNew.ttf') }}',
                    bold: '{{ asset('fonts/THSarabunNew Bold.ttf') }}',
                    italics: '{{ asset('fonts/THSarabunNew Italic.ttf') }}',
                    bolditalics: '{{ asset('fonts/THSarabunNew BoldItalic.ttf') }}'
                }
            };

            function parseHtmlTable() {
                const table = document.getElementById('report-table');
                if (!table) return { body: [], widths: [] };

                const tableBody = [];
                const colWidths = [];
                const headerData = [];
                // Skip 'รูปภาพ' column for PDF export
                const skipIndices = []; 

                // Header
                const headerRows = table.querySelectorAll('thead tr th');
                const headerCells = [];
                
                headerRows.forEach((th, index) => {
                    const thText = th.textContent.trim();
                    if(thText === 'รูปภาพ') {
                        skipIndices.push(index);
                        return;
                    }
                    headerData.push(thText.toLowerCase());
                    headerCells.push({ text: thText, style: 'tableHeader' });

                    if (index === 0 || thText === '#' || thText.toLowerCase() === 'id') {
                        colWidths.push('auto');
                    } else {
                        colWidths.push('*');
                    }
                });
                tableBody.push(headerCells);

                // Body
                const bodyRows = table.querySelectorAll('tbody tr');
                bodyRows.forEach(tr => {
                    if (tr.cells.length <= 1 && tr.innerText.includes('ไม่พบ')) return;

                    const rowCells = [];
                    tr.querySelectorAll('td').forEach((td, index) => {
                        if(skipIndices.includes(index)) return; // Skip image column

                        let styleName = 'tableBody';
                        const headerText = headerData[rowCells.length] || ''; 
                        
                        if (index === 0 || headerText.includes('#') || headerText.includes('ลำดับ')) {
                            styleName = 'alignCenter';
                        }
                        
                        rowCells.push({ text: td.innerText.trim(), style: styleName });
                    });
                    if (rowCells.length > 0) tableBody.push(rowCells);
                });

                return { body: tableBody, widths: colWidths };
            }

            function exportReportToPdf() {
                 try {
                    const tableConfig = parseHtmlTable();
                    if (tableConfig.body.length <= 1) {
                        alert('ไม่พบข้อมูลตารางสำหรับ Export');
                        return;
                    }

                    const docDefinition = {
                        pageSize: 'A4',
                        pageOrientation: 'landscape',
                        defaultStyle: { font: 'THSarabun', fontSize: 10 },
                        content: [
                            { text: document.getElementById('report-title').innerText, style: 'header' },
                            { text: document.getElementById('report-subtitle').innerText, style: 'subheader' },
                            { text: `วันที่พิมพ์: ${new Date().toLocaleString('th-TH')}`, style: 'subheader', margin: [0, 0, 0, 10] },
                            {
                                table: {
                                    headerRows: 1,
                                    widths: tableConfig.widths,
                                    body: tableConfig.body
                                },
                                layout: 'lightHorizontalLines'
                            }
                        ],
                        styles: {
                            header: { fontSize: 16, bold: true, margin: [0, 0, 0, 5] },
                            subheader: { fontSize: 10, margin: [0, 0, 0, 2], color: '#555' },
                            tableHeader: { bold: true, fontSize: 11, color: 'black', fillColor: '#eeeeee', alignment: 'center' },
                            tableBody: { fontSize: 10, alignment: 'left' },
                            alignCenter: { fontSize: 10, alignment: 'center' }
                        }
                    };

                    const reportType = document.getElementById('report_type').value || 'report';
                    pdfMake.createPdf(docDefinition).download(`report_${reportType}_${new Date().toISOString().slice(0, 10)}.pdf`);

                } catch (error) {
                    console.error('Error exporting PDF:', error);
                    alert('เกิดข้อผิดพลาดในการสร้าง PDF: ' + error.message);
                }
            }

            if (pdfButton) {
                pdfButton.addEventListener('click', exportReportToPdf);
                 const observer = new MutationObserver((mutations) => {
                    for (const mutation of mutations) {
                        if (mutation.attributeName === 'style') {
                            if (reportResultsContainer.style.display !== 'none') {
                                pdfButton.style.display = 'inline-block';
                            } else {
                                pdfButton.style.display = 'none';
                            }
                        }
                    }
                });
                observer.observe(reportResultsContainer, { attributes: true, attributeFilter: ['style'] });
            }
        });
        
        window.getStatusBadge = function(status) {
             let colorClass = 'bg-gray-100 text-gray-800';
            let icon = '';
            let label = status;

            switch (status) {
                case 'available': colorClass = 'bg-green-100 text-green-800'; icon = '<i class="fas fa-check-circle mr-1"></i>'; label = 'พร้อมใช้งาน'; break;
                case 'in-use': case 'borrowed': colorClass = 'bg-blue-100 text-blue-800'; icon = '<i class="fas fa-user-clock mr-1"></i>'; label = 'ถูกยืม'; break;
                case 'low_stock': colorClass = 'bg-yellow-100 text-yellow-800'; icon = '<i class="fas fa-exclamation-triangle mr-1"></i>'; label = 'สต็อกต่ำ'; break;
                case 'out_of_stock': colorClass = 'bg-red-100 text-red-800'; icon = '<i class="fas fa-times-circle mr-1"></i>'; label = 'สินค้าหมด'; break;
                case 'repairing': case 'maintenance': colorClass = 'bg-orange-100 text-orange-800'; icon = '<i class="fas fa-tools mr-1"></i>'; label = 'ซ่อมบำรุง'; break;
                case 'disposed': colorClass = 'bg-gray-200 text-gray-600'; icon = '<i class="fas fa-trash-alt mr-1"></i>'; label = 'ตัดจำหน่าย'; break;
                case 'pending': colorClass = 'bg-blue-50 text-blue-600'; icon = '<i class="fas fa-hourglass-half mr-1"></i>'; label = 'รออนุมัติ'; break;
                case 'approved': colorClass = 'bg-green-50 text-green-600'; icon = '<i class="fas fa-check mr-1"></i>'; label = 'อนุมัติแล้ว'; break;
                case 'rejected': colorClass = 'bg-red-50 text-red-600'; icon = '<i class="fas fa-ban mr-1"></i>'; label = 'ไม่อนุมัติ'; break;
                case 'completed': colorClass = 'bg-teal-50 text-teal-600'; icon = '<i class="fas fa-flag-checkered mr-1"></i>'; label = 'เสร็จสิ้น'; break;
                case 'safe': colorClass = 'bg-green-50 text-green-600'; icon = '<i class="fas fa-shield-alt mr-1"></i>'; label = 'ปกติ'; break;
                case 'warning': colorClass = 'bg-orange-100 text-orange-600'; icon = '<i class="fas fa-exclamation mr-1"></i>'; label = 'เตือน'; break;
                case 'locked': colorClass = 'bg-red-100 text-red-600'; icon = '<i class="fas fa-lock mr-1"></i>'; label = 'ถูกระงับ'; break;
            }
            return `<span class="px-2 py-1 text-xs font-semibold rounded-full ${colorClass} whitespace-nowrap border border-opacity-20 border-current">${icon} ${label}</span>`;
        };
    </script>
    
    <script src="{{ asset('js/reports.js') }}"></script>
@endpush