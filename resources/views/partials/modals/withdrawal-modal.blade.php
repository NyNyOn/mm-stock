<div id="withdrawal-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col gentle-shadow animate-slide-up-soft">
        <div class="flex items-center justify-between p-5 border-b border-gray-200">
            <h3 id="withdrawal-modal-title" class="flex items-center space-x-3 text-lg font-bold text-gray-800">
                <i id="withdrawal-modal-icon" class="text-blue-500 fas fa-box-open"></i>
                <span>สร้างรายการ</span>
            </h3>
            <button onclick="closeModal('withdrawal-modal')" class="text-2xl text-gray-400 hover:text-gray-600">&times;</button>
        </div>

        <div class="p-6 space-y-4 overflow-y-auto scrollbar-soft">
            <form id="withdrawal-form" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="requestor_name" id="requestor-label" class="block mb-1 text-sm font-medium text-gray-700">ชื่อผู้ขอ *</label>
                        <input
                            type="text"
                            name="requestor_name"
                            id="requestor_name"
                            class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                            value="{{ old('requestor_name', Auth::user()->full_name ?? Auth::user()->name ?? Auth::user()->username ?? Auth::user()->email ?? '') }}"
                            required
                            readonly
                        >
                    </div>

                    <div class="hidden">
                        <label for="purpose" id="purpose-label" class="block mb-1 text-sm font-medium text-gray-700">วัตถุประสงค์</label>
                        <input type="text" name="purpose" id="purpose" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label for="notes" class="block mb-1 text-sm font-medium text-gray-700">หมายเหตุ *</label>
                    <textarea name="notes" id="notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required></textarea>
                </div>
            </form>

            <div class="pt-2">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-bold text-gray-700">รายการอุปกรณ์ *</h4>
                    <div class="flex space-x-2">
                        <button type="button" onclick="openSelectItemModal()" class="flex items-center px-3 py-2 space-x-2 text-xs font-bold text-green-700 bg-green-100 rounded-lg hover:bg-green-200">
                            <i class="fas fa-plus"></i><span>เลือก</span>
                        </button>
                        <button type="button" onclick="showWithdrawalScannerModal()" class="flex items-center px-3 py-2 space-x-2 text-xs font-bold rounded-lg bg-cyan-100 text-cyan-700 hover:bg-cyan-200">
                            <i class="fas fa-qrcode"></i><span>สแกน</span>
                        </button>
                    </div>
                </div>
                <div id="withdrawal-items-container" class="space-y-2 bg-gray-50 p-2 rounded-lg min-h-[100px]">
                    <div id="no-withdrawal-items-placeholder" class="p-8 text-center">
                        <p class="text-gray-500">ยังไม่มีรายการอุปกรณ์</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end p-5 space-x-3 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="closeModal('withdrawal-modal')" class="px-4 py-2 font-medium text-gray-800 bg-gray-200 rounded-lg hover:bg-gray-300">ยกเลิก</button>
            <button type="button" id="submit-withdrawal-btn" onclick="submitWithdrawal()" class="px-6 py-2 font-medium text-white transition-shadow rounded-lg bg-gradient-to-r from-blue-500 to-purple-500 hover:shadow-lg">
                <i class="mr-2 fas fa-check-circle"></i>ยืนยัน
            </button>
        </div>
    </div>
</div>

{{-- เติมชื่อผู้ขออัตโนมัติเมื่อโมดัลแสดง / หน้าโหลด --}}
<script>
(function () {
  const modal = document.getElementById('withdrawal-modal');
  const input = document.getElementById('requestor_name');

  // ชื่อจาก Blade (Auth) และสำรองจากตัวแปร global ถ้ามี
  const bladeName = @json(Auth::user()->full_name ?? Auth::user()->name ?? Auth::user()->username ?? Auth::user()->email ?? '');
  const globalName = (window.APP_USER_NAME || '').toString();
  const defaultName = (bladeName || globalName || '').toString();

  function fillRequester() {
    if (!input) return;
    if (!input.value || !input.value.trim()) {
      input.value = defaultName;
    }
  }

  // เติมตอนหน้าโหลด
  document.addEventListener('DOMContentLoaded', fillRequester);

  // เติมทุกครั้งที่โมดัลถูกเปิด (คลาส 'hidden' ถูกเอาออก)
  if (modal) {
    const obs = new MutationObserver(() => {
      if (!modal.classList.contains('hidden')) {
        setTimeout(fillRequester, 0);
      }
    });
    obs.observe(modal, { attributes: true, attributeFilter: ['class'] });
  }
})();
</script>
