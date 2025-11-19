{{-- resources/views/partials/modals/msds-modal.blade.php --}}
{{-- Updated UI for SweetAlert Content --}}
<div class="text-left p-4 space-y-5 bg-gray-50 rounded-lg"> {{-- Added padding, background, and increased spacing --}}

    {{-- Details Section --}}
    <div>
        <label for="swal-msds-details" class="block text-sm font-semibold text-gray-700 mb-1.5">
            <i class="fas fa-align-left mr-1 text-gray-500"></i> รายละเอียด MSDS
        </label>
        <textarea id="swal-msds-details"
                  class="swal2-textarea w-full border border-gray-300 rounded-lg shadow-sm p-2.5 text-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out"
                  style="min-height: 120px; resize: vertical;"
                  placeholder="รายละเอียดสารเคมี, ข้อควรระวังในการใช้งาน, การปฐมพยาบาลเบื้องต้น...">{{ $details ?? '' }}</textarea>
    </div>

    {{-- File Upload Section --}}
    <div class="border-t border-gray-200 pt-4 space-y-2"> {{-- Added top border and spacing --}}
        <label for="swal-msds-file" class="block text-sm font-semibold text-gray-700 mb-1.5">
            <i class="fas fa-paperclip mr-1 text-gray-500"></i> อัปโหลดไฟล์ใหม่ (ถ้าต้องการเปลี่ยน)
        </label>
        {{-- Custom styled file input (appearance handled by Tailwind classes on the wrapper/label) --}}
        <div class="flex items-center space-x-2">
             <input type="file" id="swal-msds-file" name="swal_msds_file_input" {{-- Name added for potential styling hooks --}}
                   class="swal2-file block w-full text-sm text-gray-500 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-l-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200"
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png,.txt">
        </div>
        {{-- File Status Display --}}
        <div class="mt-2 text-xs text-gray-600 flex items-center">
           <i class="fas fa-info-circle mr-1.5 text-gray-400"></i>
           <span>สถานะไฟล์ปัจจุบัน:</span>
           <span id="swal-msds-current-file-status" class="ml-1 font-medium">{!! $fileStatus ?? '<span class="text-gray-500">ยังไม่มีการอัปโหลดไฟล์</span>' !!}</span>
        </div>
    </div>
</div>

