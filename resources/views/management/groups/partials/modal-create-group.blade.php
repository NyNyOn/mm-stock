<div class="fixed inset-0 z-50 items-center justify-center hidden bg-black bg-opacity-50" id="createGroupModal">
    <div class="w-full max-w-lg p-5 mx-auto bg-white rounded-2xl soft-card">
        <form action="{{ route('management.groups.store') }}" method="POST">
            @csrf
            <div class="flex items-start justify-between pb-3 border-b">
                <h3 class="text-xl font-bold">สร้างกลุ่มผู้ใช้ใหม่</h3>
                <button type="button" class="text-black" onclick="closeModal('createGroupModal')">&times;</button>
            </div>
            <div class="py-4 space-y-4">
                <div>
                    <label for="name" class="block mb-2 font-bold text-gray-700">ชื่อกลุ่ม (ภาษาอังกฤษ)*</label>
                    <input type="text" name="name" id="name" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div>
                    <label for="description" class="block mb-2 font-bold text-gray-700">คำอธิบาย (ภาษาไทย)</label>
                    <input type="text" name="description" id="description" class="w-full px-3 py-2 border rounded-lg">
                </div>
            </div>
            <div class="flex justify-end pt-3 border-t">
                <button type="button" class="px-4 py-2 mr-2 text-white bg-gray-500 rounded-lg hover:bg-gray-700" onclick="closeModal('createGroupModal')">ปิด</button>
                <button type="submit" class="px-4 py-2 text-white bg-green-500 rounded-lg hover:bg-green-700">บันทึก</button>
            </div>
        </form>
    </div>
</div>
