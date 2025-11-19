@extends('layouts.app')

@section('header', '📊 รายงาน')
@section('subtitle', 'รายงานทั้งหมด')

@section('content')
<div class="space-y-6 page animate-slide-up-soft">

    {{-- Filter Section (โค้ดเดิมของคุณ) --}}
    <div id="report-filters-card" class="p-6 soft-card rounded-2xl gentle-shadow">
        <div class="flex items-center mb-4">
            <i class="mr-4 text-2xl text-blue-500 fas fa-filter"></i>
            <div>
                <h3 class="text-xl font-bold text-gray-800">ตัวกรองรายงาน</h3>
                <p class="text-sm text-gray-500">เลือกเงื่อนไขเพื่อดูข้อมูลที่ต้องการ</p>
            </div>
        </div>
        <form id="report-form" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
            @csrf
            <div>
                <label for="report_type" class="block mb-1 text-sm font-medium text-gray-700">ประเภทรายงาน</label>
                <select id="report_type" name="report_type" required class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-transparent border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
                    <option value="">-- เลือกรายงาน --</option>
                    <option value="stock_summary" @selected(old('report_type', $initialReportType) == 'stock_summary')>สรุปสต๊อกคงคลัง</option>
                    <option value="transaction_history" @selected(old('report_type', $initialReportType) == 'transaction_history')>ประวัติธุรกรรม</option>
                    <option value="borrow_report" @selected(old('report_type', $initialReportType) == 'borrow_report')>รายการที่กำลังยืม</option>
                    <option value="low_stock" @selected(old('report_type', $initialReportType) == 'low_stock')>รายงานสินค้าใกล้หมดสต๊อก</option>
                    <option value="warranty" @selected(old('report_type', $initialReportType) == 'warranty')>รายงานประกัน</option>
                    <option value="maintenance_report" @selected(old('report_type', $initialReportType) == 'maintenance_report')>รายงานการซ่อมบำรุง</option>
                    <option value="po_report" @selected(old('report_type', $initialReportType) == 'po_report')>รายงานใบสั่งซื้อ (PO)</option>
                    <option value="disposal_report" @selected(old('report_type', $initialReportType) == 'disposal_report')>รายงานการตัดจำหน่าย</option>
                    <option value="consumable_return_report" @selected(old('report_type', $initialReportType) == 'consumable_return_report')>รายงานการคืนพัสดุสิ้นเปลือง</option>
                    <option value="user_activity_report" @selected(old('report_type', $initialReportType) == 'user_activity_report')>รายงานกิจกรรมผู้ใช้งาน</option>
                </select>
            </div>
            <div>
                <label for="start_date" class="block mb-1 text-sm font-medium text-gray-700">วันที่เริ่มต้น</label>
                <input type="date" id="start_date" name="start_date" class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-transparent border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
            </div>
            <div>
                <label for="end_date" class="block mb-1 text-sm font-medium text-gray-700">วันที่สิ้นสุด</label>
                <input type="date" id="end_date" name="end_date" class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-transparent border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
            </div>
            <div>
                <label for="category_id" class="block mb-1 text-sm font-medium text-gray-700">ประเภท</label>
                <select id="category_id" name="category_id" class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-transparent border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
                    <option value="">-- ทุกประเภท --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="location_id" class="block mb-1 text-sm font-medium text-gray-700">สถานที่</label>
                <select id="location_id" name="location_id" class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-transparent border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
                    <option value="">-- ทุกสถานที่ --</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            {{-- User Filter (ซ่อนไว้ก่อน) --}}
            <div id="user-filter-container" style="display: none;">
                <label for="user_id" class="block mb-1 text-sm font-medium text-gray-700">ผู้ใช้งาน</label>
                <select id="user_id" name="user_id" class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-transparent border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
                    <option value="">-- เลือกผู้ใช้งาน --</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->fullname }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Submit Button --}}
            <div class="flex items-end md:col-span-2 lg:col-span-1">
                <button type="submit" class="w-full px-6 py-3 font-medium text-blue-700 transition-all bg-gradient-to-br from-blue-100 to-blue-200 rounded-xl hover:shadow-lg button-soft gentle-shadow">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                    <i class="mr-2 fas fa-search"></i>สร้างรายงาน
                </button>
            </div>
        </form>
    </div>

    {{-- Report Display Section (อัปเดตแล้ว) --}}
    <div id="report-results-container" class="soft-card rounded-2xl gentle-shadow" style="display: none;">
         
         <div class="flex flex-wrap items-center justify-between p-5 bg-gradient-to-r from-blue-50 to-purple-50">
            <div>
                <h3 id="report-title" class="text-xl font-bold text-gray-800">ผลลัพธ์รายงาน</h3>
                <p id="report-subtitle" class="text-sm text-gray-600">กรุณาเลือกเงื่อนไขและกด "สร้างรายงาน"</p>
            </div>
            
            <div class="mt-2 md:mt-0">
                <button id="export-pdf-button" type="button" class="px-4 py-3 font-medium text-red-700 transition-all bg-gradient-to-br from-red-100 to-red-200 rounded-xl hover:shadow-lg button-soft gentle-shadow" style="display: none;">
                    <i class="mr-2 fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
         </div>
         
        <div class="p-5 overflow-x-auto scrollbar-soft">
            <table class="w-full text-sm text-left text-gray-500" id="report-table">
                {{-- Content will be injected by reports.js --}}
            </table>
        </div>
    </div>
</div>
@endsection

{{-- 
    ================================================================
    ✅ (อัปเดต) ส่วน SCRIPT ทั้งหมด (ใช้ PDFMAKE พร้อมการจัดรูปแบบมาตรฐาน)
    ================================================================
--}}
@push('scripts')
    {{-- (โค้ดเดิมของคุณ) ส่งค่า $initialReportType ไปให้ JavaScript --}}
    <script>
        window.initialReportType = @json($initialReportType ?? null);
    </script>
    
    {{-- 1. เรียกใช้ PDFMAKE (เหมือนเดิม) --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.10/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.10/vfs_fonts.js"></script>

    {{-- 2. LOGIC สำหรับการ EXPORT ด้วย PDFMAKE (อัปเดตแล้ว) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pdfButton = document.getElementById('export-pdf-button');
            const reportResultsContainer = document.getElementById('report-results-container');
            
            if (!pdfButton || !reportResultsContainer) {
                console.error('PDF Button หรือ Report Container not found!');
                return;
            }
            
            if (typeof pdfMake === 'undefined') {
                console.error('pdfMake is not loaded! Check library links.');
                return;
            }

            // --- (สำคัญ!) 1. กำหนดค่าฟอนต์จาก /public/fonts/ ---
            pdfMake.fonts = {
                THSarabun: { // นี่คือชื่อที่เราจะใช้เรียกในสไตล์
                    normal: '{{ asset('fonts/THSarabunNew.ttf') }}',
                    bold: '{{ asset('fonts/THSarabunNew Bold.ttf') }}',
                    italics: '{{ asset('fonts/THSarabunNew Italic.ttf') }}',
                    bolditalics: '{{ asset('fonts/THSarabunNew BoldItalic.ttf') }}'
                }
            };

            // --- 2. (อัปเดต) Function ดึงข้อมูลจากตาราง HTML ---
            // (จะคืนค่าเป็น object ที่มีทั้ง body และ widths)
            function parseHtmlTable() {
                const table = document.getElementById('report-table');
                if (!table) return { body: [], widths: [] };

                const tableBody = [];
                const colWidths = [];
                const headerData = []; // เก็บ text ของ header ไว้เช็ค
                
                // คำที่บ่งบอกว่าเป็นคอลัมน์ตัวเลข (สำหรับจัดชิดขวา)
                const numericKeywords = ['จำนวน', 'คงเหลือ', 'ขั้นต่ำ', 'qty', 'quantity', 'min', 'stock', 'id'];

                // 2.1 ดึงหัวตาราง (thead)
                const headerRows = table.querySelectorAll('thead tr th');
                const headerCells = [];
                
                headerRows.forEach((th, index) => {
                    const thText = th.textContent.trim();
                    headerData.push(thText.toLowerCase()); // เก็บ text (ตัวเล็ก) ไว้เช็ค
                    
                    // ตั้งค่าสไตล์ (หัวตารางทั้งหมดจัดกลาง)
                    headerCells.push({ text: thText, style: 'tableHeader' });

                    // 2.2 (อัปเดต) ตั้งค่าความกว้างคอลัมน์
                    if (index === 0 || thText === '#') {
                        colWidths.push('auto'); // คอลัมน์ # หรือ ลำดับ
                    } else {
                        colWidths.push('*'); // คอลัมน์ที่เหลือ
                    }
                });
                tableBody.push(headerCells);

                // 2.3 ดึงเนื้อหา (tbody)
                const bodyRows = table.querySelectorAll('tbody tr');
                bodyRows.forEach(tr => {
                    const rowCells = [];
                    tr.querySelectorAll('td').forEach((td, index) => {
                        let styleName = 'tableBody'; // ค่าเริ่มต้น (ชิดซ้าย)
                        
                        // 2.4 (อัปเดต) ตรวจสอบการจัดตำแหน่ง
                        const headerText = headerData[index] || '';
                        
                        if (index === 0 || headerText.includes('#') || headerText.includes('ลำดับ')) {
                            styleName = 'alignCenter'; // คอลัมน์ # จัดกลาง
                        } else if (numericKeywords.some(keyword => headerText.includes(keyword))) {
                            styleName = 'alignRight'; // คอลัมน์ตัวเลข จัดขวา
                        }
                        
                        rowCells.push({ text: td.textContent.trim(), style: styleName });
                    });
                    tableBody.push(rowCells);
                });

                return { body: tableBody, widths: colWidths };
            }

            // --- 3. Function หลักสำหรับ Export ---
            function exportReportToPdf() {
                try {
                    // (อัปเดต) 3.1 ดึงข้อมูลและสัดส่วน
                    const tableConfig = parseHtmlTable();

                    if (tableConfig.body.length === 0) {
                        alert('ไม่พบข้อมูลตารางสำหรับ Export');
                        return;
                    }

                    // --- 4. กำหนดโครงสร้างเอกสาร PDF (อัปเดต) ---
                    const docDefinition = {
                        pageSize: 'A4',
                        pageOrientation: 'landscape', // แนวนอน
                        defaultStyle: {
                            font: 'THSarabun' // ✅ ใช้ฟอนต์ไทยเป็นหลัก
                        },
                        content: [
                            // 4.1 หัวข้อรายงาน
                            { text: document.getElementById('report-title').innerText, style: 'header' },
                            { text: document.getElementById('report-subtitle').innerText, style: 'subheader' },
                            { text: `วันที่พิมพ์: ${new Date().toLocaleString('th-TH')}`, style: 'subheader', margin: [0, 0, 0, 10] },
                            
                            // 4.2 ตาราง (อัปเดต)
                            {
                                table: {
                                    headerRows: 1,
                                    widths: tableConfig.widths, // 👈 (อัปเดต) ใช้สัดส่วนที่คำนวณมา
                                    body: tableConfig.body     // 👈 (อัปเดต) ใช้เนื้อหาที่แปลงมา
                                },
                                layout: 'lightHorizontalLines' // ธีมตาราง (เส้นแนวนอน)
                            }
                        ],
                        // 4.3 สไตล์ (อัปเดต)
                        styles: {
                            header: {
                                fontSize: 18,
                                bold: true,
                                margin: [0, 0, 0, 5]
                            },
                            subheader: {
                                fontSize: 10,
                                margin: [0, 0, 0, 2]
                            },
                            tableHeader: {
                                bold: true,
                                fontSize: 11,
                                color: 'black',
                                fillColor: '#eeeeee', // สีพื้นหลังหัวตาราง
                                alignment: 'center' // 👈 (อัปเดต) หัวตารางจัดกลาง
                            },
                            tableBody: {
                                fontSize: 10,
                                alignment: 'left' // 👈 ค่าเริ่มต้น
                            },
                            // ✅ (เพิ่ม) สไตล์สำหรับจัดตำแหน่ง
                            alignRight: {
                                fontSize: 10,
                                alignment: 'right'
                            },
                            alignCenter: {
                                fontSize: 10,
                                alignment: 'center'
                            }
                        }
                    };

                    // --- 5. สร้างและดาวน์โหลด PDF ---
                    const reportType = document.getElementById('report_type').value || 'report';
                    pdfMake.createPdf(docDefinition).download(`report_${reportType}_${new Date().toISOString().slice(0, 10)}.pdf`);

                } catch (error) {
                    console.error('Error exporting PDF with pdfMake:', error);
                    alert('เกิดข้อผิดพลาดในการสร้าง PDF ด้วย pdfMake');
                }
            }

            // --- (โค้ดเดิม) สั่งให้ปุ่ม PDF ทำงานเมื่อคลิก ---
            pdfButton.addEventListener('click', exportReportToPdf);

            // --- (โค้ดเดิม) Logic การแสดง/ซ่อนปุ่ม PDF ---
            const observer = new MutationObserver((mutations) => {
                for (const mutation of mutations) {
                    if (mutation.attributeName === 'style') {
                        const targetElement = mutation.target;
                        
                        if (targetElement.style.display !== 'none') {
                            pdfButton.style.display = 'inline-block'; // แสดงปุ่ม PDF
                        } else {
                            pdfButton.style.display = 'none'; // ซ่อนปุ่ม PDF
                        }
                    }
                }
            });

            // เริ่มสังเกตการณ์
            observer.observe(reportResultsContainer, {
                attributes: true, 
                attributeFilter: ['style'] 
            });
        });
    </script>
    
    {{-- 3. (โค้ดเดิมของคุณ) เรียกใช้ reports.js เป็นไฟล์สุดท้าย (ยังต้องใช้) --}}
    <script src="{{ asset('js/reports.js') }}"></script>
@endpush
