<div id="return-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 bg-black bg-opacity-50">
    <div class="flex flex-col w-full max-w-lg bg-white shadow-xl rounded-2xl gentle-shadow animate-slide-up-soft">
        <form id="return-form" method="POST" action="{{ route('returns.store') }}">
            @csrf
            <input type="hidden" name="transaction_id" id="return-transaction-id">

            <div class="p-5 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-800">รับคืนอุปกรณ์</h3>
                <p id="return-item-name" class="text-sm text-gray-600"></p>
            </div>

            <div class="p-6 space-y-4">

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700">สภาพเมื่อรับคืน *</label>
                    <div class="space-y-3">

                        {{-- Option 1: สภาพดี / ปกติ --}}
                        <label class="flex items-center p-3 border rounded-xl flex-1 cursor-pointer transition-all duration-200 hover:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 has-[:checked]:ring-2 has-[:checked]:ring-blue-200">
                            <input type="radio" name="return_condition" value="good" class="sr-only" checked>
                            <div class="flex items-center justify-center flex-shrink-0 w-5 h-5 border-2 border-gray-300 rounded-full">
                                <div class="w-2.5 h-2.5 bg-blue-600 rounded-full scale-0 transition-transform"></div>
                            </div>
                            <span class="ml-3 text-sm font-medium text-gray-700">สภาพดี / ปกติ</span>
                        </label>

                        {{-- Option 2: ชำรุด / แจ้งเสีย --}}
                        <label class="flex items-center p-3 border rounded-xl flex-1 cursor-pointer transition-all duration-200 hover:border-red-500 has-[:checked]:bg-red-50 has-[:checked]:border-red-500 has-[:checked]:ring-2 has-[:checked]:ring-red-200">
                            <input type="radio" name="return_condition" value="defective" class="sr-only">
                             <div class="flex items-center justify-center flex-shrink-0 w-5 h-5 border-2 border-gray-300 rounded-full">
                                <div class="w-2.5 h-2.5 bg-red-600 rounded-full scale-0 transition-transform"></div>
                            </div>
                            <span class="ml-3 text-sm font-medium text-gray-700">ชำรุด / แจ้งเสีย</span>
                        </label>

                    </div>
                </div>

                {{-- 🟢 START: แก้ไขส่วนนี้ทั้งหมด 🟢 --}}
                <div id="problem-description-wrapper" class="hidden">
                    <label for="problem_description" class="block mb-1 text-sm font-medium text-gray-700">กรุณาระบุสาเหตุ *</label>

                    <select name="problem_description" id="problem_description" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- กรุณาเลือกสาเหตุ --</option>
                        <option value="เสียเพราะตัวอุปกรณ์เอง">เสียเพราะตัวอุปกรณ์เอง</option>
                        <option value="ผู้ใช้งานทำเสีย">ผู้ใช้งานทำเสีย</option>
                        <option value="ผู้ใช้ ใช้งานผิดประเภท">ผู้ใช้ ใช้งานผิดประเภท</option>
                    </select>

                    <p class="mt-2 text-xs text-gray-500">
                        <i class="mr-1 fas fa-info-circle"></i>
                        การระบุเหตุผลนี้ไม่มีการลงโทษใดๆ มีวัตถุประสงค์เพื่อเก็บข้อมูลว่าอุปกรณ์ไม่ได้มาตรฐานเท่านั้น
                    </p>
                </div>
                {{-- 🟢 END: สิ้นสุดการแก้ไข 🟢 --}}

            </div>

            <div class="flex justify-end p-5 space-x-3 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeModal('return-modal')" class="px-4 py-2 font-medium text-gray-800 bg-gray-200 rounded-lg hover:bg-gray-300">ยกเลิก</button>
                <button type="submit" class="px-6 py-2 font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                    <i class="mr-2 fas fa-check-circle"></i>ยืนยันการรับคืน
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    input[type="radio"]:checked + div > div {
        transform: scale(1);
    }
</style>
