<div id="scan-withdrawal-modal" class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm hidden items-center justify-center z-[100] p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md relative flex flex-col max-h-[90vh] animate-slide-up-soft">
        <div class="p-5 border-b flex justify-between items-center">
            <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-qrcode mr-2 text-blue-500"></i>สแกนเพื่อเบิก/ยืม</h3>
            <button onclick="closeWithdrawalScannerModal()" class="p-2 rounded-full hover:bg-gray-100 text-gray-500 text-2xl">&times;</button>
        </div>
        <div id="scanner-view" class="p-4 flex-grow flex flex-col items-center justify-center">
            <p class="text-center text-gray-500 text-sm mb-4">หันกล้องไปที่ QR Code หรือ Barcode</p>
            <div id="qr-reader" class="w-full rounded-2xl overflow-hidden border"></div>
        </div>
        <div id="scanner-result-view" class="p-6 hidden flex-grow flex flex-col items-center justify-center text-center">
            <div id="scanner-result-icon" class="mb-4"></div>
            <img id="scanner-result-image" src="" alt="Item Image" class="w-24 h-24 object-cover rounded-lg shadow-md mb-4">
            <p id="scanner-result-text" class="text-gray-700 font-medium"></p>
            <p id="scanner-result-stock" class="text-sm text-gray-500"></p>
            <button onclick="startWithdrawalScanner()" class="mt-6 w-full px-6 py-3 bg-cyan-500 text-white rounded-2xl font-bold hover:bg-cyan-600 transition">
                <i class="fas fa-camera mr-2"></i>สแกนชิ้นต่อไป
            </button>
        </div>
    </div>
</div>