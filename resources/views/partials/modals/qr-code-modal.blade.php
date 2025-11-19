<div id="qr-code-modal" class="fixed inset-0 modal-backdrop-soft hidden items-center justify-center z-[100] p-4">
    <div class="soft-card rounded-3xl w-full max-w-sm flex flex-col animate-slide-up-soft gentle-shadow">
         <div class="p-4 border-b flex justify-between items-center">
             <h3 class="text-lg font-bold gradient-text-soft">🖨️ พิมพ์ป้าย QR/Barcode</h3>
             <button onclick="closeModal('qr-code-modal')" class="p-2 rounded-full hover:bg-gray-100 text-gray-500 text-2xl">&times;</button>
         </div>
         {{-- ✅ Added id="qr-printable-area" --}}
         <div id="qr-printable-area" class="p-6 text-center">
             <h4 id="qr-modal-name" class="text-lg font-bold text-gray-800 mb-4 break-words"></h4>
             <div id="qr-code-container" class="flex justify-center mb-4"></div>
             {{-- ✅✅ MODIFIED: Added id="barcode-container-wrapper" for print replacement --}}
             <div id="barcode-container-wrapper" class="flex flex-col items-center"> {{-- Use flex-col and items-center --}}
                 <canvas id="barcode-container"></canvas>
                 {{-- ✅ ADDED: Paragraph to display equipment name below barcode --}}
                 <p id="qr-barcode-name" class="text-sm text-gray-700 mt-1"></p>
             </div>
         </div>
         <div class="p-4 border-t flex justify-end space-x-3">
             {{-- ✅ Use simple window.print --}}
             <button onclick="printQrModalContent()" class="px-6 py-3 bg-gradient-to-br from-blue-400 to-purple-500 text-white rounded-xl hover:shadow-lg transition-all button-soft gentle-shadow font-bold">
                 <i class="fas fa-print mr-2"></i>พิมพ์
             </button>
         </div>
     </div>
</div>

{{-- Script for libraries and print function (moved from equipment.js for better encapsulation) --}}
@once
    @push('scripts')
        {{-- QRious for QR Code --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
        {{-- JsBarcode for Barcode --}}
        <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

        <script>
            // ✅✅✅ MODIFIED: Function to handle canvas printing correctly
            // นี่คือส่วนที่แก้ไขปัญหา "พิมพ์แล้วว่าง" ครับ
            function printQrModalContent() {
                const printableArea = document.getElementById('qr-printable-area');
                if (!printableArea) {
                    console.error('Printable area not found!');
                    Swal.fire('Error', 'Cannot find content to print.', 'error');
                    return;
                }

                // 1. Get the HTML structure
                const printableHtml = printableArea.innerHTML;

                // 2. Get the *source* canvas elements from the main document (the modal)
                // QRious สร้าง canvas ภายใน container
                const sourceQrCanvas = document.querySelector('#qr-code-container canvas'); 
                // JsBarcode วาดลงบน canvas ที่มีอยู่
                const sourceBarcodeCanvas = document.getElementById('barcode-container'); 

                // 3. Convert source canvases to Data URLs (แปลงเป็นรูปภาพ)
                const qrDataUrl = sourceQrCanvas ? sourceQrCanvas.toDataURL('image/png') : null;
                const barcodeDataUrl = sourceBarcodeCanvas ? sourceBarcodeCanvas.toDataURL('image/png') : null;

                const printWindow = window.open('', '_blank');
                if (printWindow) {
                    printWindow.document.write('<html><head><title>Print Label</title>');
                    
                    // 4. Add Print Styles (สำคัญ: จัดสไตล์ให้ <img> ใหม่)
                    printWindow.document.write(`
                        <style>
                            body { font-family: sans-serif; text-align: center; margin: 10mm; }
                            /* จัดสไตล์ให้แท็ก <img> ใหม่ อ้างอิงจาก ID ของ container */
                            #qr-code-container img { 
                                margin-bottom: 5mm; 
                                /* ดึงขนาดจาก canvas ต้นฉบับเพื่อความแม่นยำ */
                                width: ${sourceQrCanvas ? sourceQrCanvas.width : 180}px;
                                height: ${sourceQrCanvas ? sourceQrCanvas.height : 180}px;
                            }
                            #barcode-container-wrapper img { 
                                margin-bottom: 2mm; 
                                width: ${sourceBarcodeCanvas ? sourceBarcodeCanvas.width : 'auto'}px;
                                height: ${sourceBarcodeCanvas ? sourceBarcodeCanvas.height : '60'}px;
                            }
                            #qr-modal-name { font-size: 1.1em; margin-bottom: 8mm; font-weight: bold; }
                            #qr-barcode-name { font-size: 0.9em; margin-top: 1mm; }
                            @media print {
                                body { margin: 5mm; }
                                @page { size: auto; margin: 5mm; } /* Optional: Adjust page margins */
                            }
                        </style>
                    `);
                    printWindow.document.write('</head><body>');
                    
                    // 5. Write the original HTML structure (ซึ่งมี <canvas> ที่ว่างเปล่า)
                    printWindow.document.write(printableHtml); 
                    
                    printWindow.document.write('</body></html>');
                    printWindow.document.close(); // ปิดการเขียนเอกสาร

                    // 6. ค้นหา <canvas> ที่ว่างเปล่าในหน้าต่างพิมพ์
                    const destQrCanvas = printWindow.document.querySelector('#qr-code-container canvas');
                    const destBarcodeCanvas = printWindow.document.getElementById('barcode-container');

                    // 7. "แทนที่" canvas ที่ว่างเปล่าด้วยแท็ก <img> ใหม่
                    if (destQrCanvas && qrDataUrl) {
                        const qrImg = printWindow.document.createElement('img');
                        qrImg.src = qrDataUrl;
                        destQrCanvas.parentNode.replaceChild(qrImg, destQrCanvas); // แทนที่ canvas ด้วย img
                    }

                    if (destBarcodeCanvas && barcodeDataUrl) {
                        const barcodeImg = printWindow.document.createElement('img');
                        barcodeImg.src = barcodeDataUrl;
                        // แทนที่ canvas (ไม่ใช่ wrapper ของมัน)
                        destBarcodeCanvas.parentNode.replaceChild(barcodeImg, destBarcodeCanvas);
                    }

                    printWindow.focus();
                    
                    // 8. หน่วงเวลาพิมพ์เล็กน้อย เพื่อให้ <img> โหลดทัน
                    setTimeout(() => {
                        printWindow.print();
                        printWindow.close();
                    }, 300); // 300ms
                } else {
                    Swal.fire('Error', 'Could not open print window. Check pop-up blocker.', 'error');
                }
            }
        </script>
    @endpush
@endonce
