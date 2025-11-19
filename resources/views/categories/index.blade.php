@extends('layouts.app')

@section('header', '📂 จัดการประเภท')
@section('subtitle', 'เพิ่ม/ลบ/แก้ไข ประเภทอุปกรณ์')

@section('content')
    <div id="categories-page" class="page animate-slide-up-soft">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- ซ้าย: ฟอร์มเพิ่มประเภทแบบคงที่ --}}
            <div class="lg:col-span-1">
                <div class="soft-card rounded-2xl p-6 gentle-shadow sticky top-24">
                    <h3 class="text-xl font-bold gradient-text-soft mb-4">📂 เพิ่มประเภทใหม่</h3>

                    {{-- Flash messages --}}
                    @if (session('success'))
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                            <p>{{ session('success') }}</p>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                            <p>{{ session('error') }}</p>
                        </div>
                    @endif

                    {{-- Validation errors --}}
                    @if ($errors->any())
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Create form (RESTful: POST -> categories.store) --}}
                    <form method="POST" action="{{ route('categories.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">ชื่อประเภท *</label>
                                <input
                                    type="text"
                                    name="name"
                                    id="name"
                                    required
                                    value="{{ old('name') }}"
                                    placeholder="เช่น คอมพิวเตอร์, จอภาพ, อุปกรณ์เครือข่าย"
                                    class="mt-1 block w-full px-4 py-3 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 bg-transparent text-gray-700 border-0 text-sm font-medium gentle-shadow"
                                >
                            </div>

                            <div>
                                <label for="prefix" class="block text-sm font-medium text-gray-700">Prefix (สำหรับ S/N)</label>
                                <input
                                    type="text"
                                    name="prefix"
                                    id="prefix"
                                    value="{{ old('prefix') }}"
                                    placeholder="เช่น PC, MON, NET"
                                    class="mt-1 block w-full px-4 py-3 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 bg-transparent text-gray-700 border-0 text-sm font-medium gentle-shadow font-mono"
                                >
                            </div>
                        </div>

                        <div class="mt-6">
                            <button
                                type="submit"
                                class="w-full px-4 py-3 bg-gradient-to-br from-blue-100 to-blue-200 text-blue-700 rounded-xl hover:shadow-lg transition-all button-soft gentle-shadow font-medium"
                            >
                                <i class="fas fa-save mr-2"></i><span>บันทึก</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ขวา: ตารางรายการประเภท --}}
            <div class="lg:col-span-2">
                <div class="soft-card rounded-2xl p-6 gentle-shadow">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">รายการประเภททั้งหมด</h3>

                    <div class="overflow-x-auto scrollbar-soft">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-blue-50 to-purple-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-700 text-sm">ชื่อประเภท</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-700 text-sm">Prefix</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-700 text-sm">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($categories as $category)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-800">
                                            {{ $category->name }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-800">
                                            {{ $category->prefix }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center space-x-2">
                                                {{-- ✅✅✅ START: โค้ดที่แก้ไข ✅✅✅ --}}
                                                <form method="POST"
                                                      action="{{ route('categories.destroy', $category->id) }}"
                                                      class="delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                            class="delete-button p-2 bg-gray-100 rounded-lg hover:bg-gray-200"
                                                            title="ลบ">
                                                        <i class="fas fa-trash text-red-600"></i>
                                                    </button>
                                                </form>
                                                {{-- ✅✅✅ END: โค้ดที่แก้ไข ✅✅✅ --}}
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center p-8 text-gray-500">
                                            ยังไม่มีข้อมูลประเภท
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- เพจิเนชัน: แสดงเมื่อใช้ paginate() --}}
                    @if (method_exists($categories, 'hasPages') && $categories->hasPages())
                        <div class="p-4 bg-gray-50 border-t mt-4 rounded-b-2xl">
                            {{ $categories->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

{{-- ✅✅✅ START: โค้ด JavaScript ที่เพิ่มเข้ามา ✅✅✅ --}}
@push('scripts')
<script>
    // ตรวจสอบว่ามีโค้ดนี้อยู่แล้วใน app.blade.php หรือไม่ ถ้ามีแล้ว ตรงนี้อาจจะไม่จำเป็น
    // แต่ใส่ไว้เพื่อความแน่นอนว่าหน้านี้ทำงานได้
    document.querySelectorAll('.delete-button').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault(); 
            let form = this.closest('form.delete-form');

            Swal.fire({
                title: 'คุณแน่ใจใช่ไหม?',
                text: "ข้อมูลจะถูกลบและไม่สามารถกู้คืนได้!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก',
                customClass: {
                    popup: 'soft-card rounded-2xl p-6 gentle-shadow',
                    confirmButton: 'button-soft bg-blue-500 text-white px-4 py-2 rounded-lg',
                    cancelButton: 'button-soft bg-red-500 text-white px-4 py-2 rounded-lg'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            })
        });
    });
</script>
@endpush
{{-- ✅✅✅ END: โค้ด JavaScript ที่เพิ่มเข้ามา ✅✅✅ --}}