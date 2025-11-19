<form id="edit-equipment-form-instance"
      class="space-y-6 edit-equipment-form-instance"
      method="POST"
      action="{{ route('equipment.update', $equipment->id) }}"
      enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 min-w-0">
        {{-- ========== LEFT: Fields ========== --}}
        <div class="md:col-span-2 space-y-4 min-w-0">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">ชื่ออุปกรณ์ *</label>
                <input type="text" name="name" required
                       value="{{ old('name', $equipment->name) }}"
                       class="w-full px-4 py-3 soft-card rounded-xl">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
                <div class="min-w-0">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Part No.</label>
                    <input type="text" name="part_no"
                           value="{{ old('part_no', $equipment->part_no) }}"
                           class="w-full px-4 py-3 soft-card rounded-xl">
                </div>
                <div class="min-w-0">
                    <label class="block text-sm font-bold text-gray-700 mb-2">รุ่น/โมเดล</label>
                    <input type="text" name="model"
                           value="{{ old('model', $equipment->model) }}"
                           class="w-full px-4 py-3 soft-card rounded-xl">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
                <div class="min-w-0">
                    <label class="block text-sm font-bold text-gray-700 mb-2">ประเภท *</label>
                    <select name="category_id" required class="w-full px-4 py-3 soft-card rounded-xl">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $equipment->category_id) == $cat->id)>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Serial Number</label>
                    <input type="text" name="serial_number"
                           value="{{ old('serial_number', $equipment->serial_number) }}"
                           class="w-full px-4 py-3 soft-card rounded-xl">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
                <div class="min-w-0">
                    <label class="block text-sm font-bold text-gray-700 mb-2">สถานที่เก็บ *</label>
                    <select name="location_id" required class="w-full px-4 py-3 soft-card rounded-xl">
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" @selected(old('location_id', $equipment->location_id) == $loc->id)>
                                {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label class="block text-sm font-bold text-gray-700 mb-2">หน่วยนับ</label>
                    <select name="unit_id" class="w-full px-4 py-3 soft-card rounded-xl">
                        <option value="">-- เลือกหน่วยนับ --</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected(old('unit_id', $equipment->unit_id) == $unit->id)>
                                {{ $unit->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ========== RIGHT: Image Upload + Preview (Fixed Height) ========== --}}
        <div class="md:col-span-1 space-y-2 min-w-0">
            <label class="block text-sm font-bold text-gray-700">รูปภาพ</label>

            @php
                $hasImage = $equipment->image && $equipment->image !== 'default_equipment.png';
                $imgSrc = $hasImage
                    ? asset('uploads/' . $equipment->image)
                    : 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';
            @endphp

            <div id="image-paste-box"
                 class="w-full bg-gray-50 soft-card rounded-2xl border-2 border-dashed border-gray-300 p-4 flex flex-col items-center justify-center text-center cursor-pointer {{ $hasImage ? 'hidden' : '' }}">
                <i class="fas fa-image text-3xl text-gray-400 mb-2"></i>
                <p class="text-sm text-gray-600">คลิกเพื่ออัปโหลด หรือกด Ctrl/⌘+V เพื่อวางรูป</p>
                <input id="image-file-input" type="file" name="image" accept="image/*" class="sr-only">
                <button type="button"
                        class="mt-3 px-4 py-2 bg-white rounded-xl border hover:bg-gray-50"
                        onclick="document.getElementById('image-file-input').click()">
                    เลือกไฟล์
                </button>
            </div>

            <div id="image-preview-container"
                 class="mt-2 soft-card rounded-2xl p-2 overflow-hidden {{ $hasImage ? '' : 'hidden' }}">
                <div class="relative w-full overflow-hidden rounded-xl border bg-gray-100 max-h-64 flex items-center justify-center">
                    <img id="image-preview-edit"
                         src="{{ $imgSrc }}"
                         alt="Preview"
                         class="max-h-64 w-auto h-auto object-contain select-none pointer-events-none">
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button"
                        class="px-4 py-2 bg-gradient-to-br from-blue-100 to-blue-200 text-blue-700 rounded-xl hover:shadow button-soft"
                        onclick="document.getElementById('image-file-input').click()">
                    เปลี่ยนรูป
                </button>
                <button type="button"
                        id="remove-image-btn"
                        class="px-4 py-2 bg-red-50 text-red-600 rounded-xl hover:bg-red-100">
                    ลบรูป
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mt-4 min-w-0">
        <div class="min-w-0">
            <label class="block text-sm font-bold text-gray-700 mb-2">จำนวน *</label>
            <input type="number" name="quantity" required
                   value="{{ old('quantity', $equipment->quantity) }}" min="0"
                   class="w-full px-4 py-3 soft-card rounded-xl">
        </div>
        <div class="min-w-0">
            <label class="block text-sm font-bold text-gray-700 mb-2">สต็อกขั้นต่ำ *</label>
            <input type="number" name="min_stock" required
                   value="{{ old('min_stock', $equipment->min_stock) }}" min="0"
                   class="w-full px-4 py-3 soft-card rounded-xl">
        </div>
        <div class="min-w-0">
            <label class="block text-sm font-bold text-gray-700 mb-2">สต็อกสูงสุด *</label>
            <input type="number" name="max_stock" required
                   value="{{ old('max_stock', $equipment->max_stock ?? 0) }}" min="0"
                   class="w-full px-4 py-3 soft-card rounded-xl">
        </div>
        <div class="min-w-0">
            <label class="block text-sm font-bold text-gray-700 mb-2">ราคา</label>
            <input type="number" name="price" step="0.01"
                   value="{{ old('price', $equipment->price) }}" min="0"
                   class="w-full px-4 py-3 soft-card rounded-xl">
        </div>
        <div class="min-w-0">
            <label class="block text-sm font-bold text-gray-700 mb-2">ผู้จัดจำหน่าย</label>
            <input type="text" name="supplier"
                   value="{{ old('supplier', $equipment->supplier) }}"
                   class="w-full px-4 py-3 soft-card rounded-xl">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 min-w-0">
        <div class="min-w-0">
            <label class="block text-sm font-bold text-gray-700 mb-2">วันที่ซื้อ</label>
            <input type="date" name="purchase_date"
                   value="{{ old('purchase_date', $equipment->purchase_date) }}"
                   class="w-full px-4 py-3 soft-card rounded-xl">
        </div>
        <div class="min-w-0">
            <label class="block text-sm font-bold text-gray-700 mb-2">วันหมดประกัน</label>
            <input type="date" name="warranty_date"
                   value="{{ old('warranty_date', $equipment->warranty_date) }}"
                   class="w-full px-4 py-3 soft-card rounded-xl">
        </div>
    </div>

    <div class="mt-4">
        <label class="block text-sm font-bold text-gray-700 mb-2">หมายเหตุ</label>
        <textarea name="notes" rows="3"
                  class="w-full px-4 py-3 soft-card rounded-xl">{{ old('notes', $equipment->notes) }}</textarea>
    </div>

    <div class="pt-4 text-right">
        <button type="submit"
                class="px-8 py-4 bg-gradient-to-br from-blue-400 to-purple-500 text-white rounded-2xl hover:shadow-lg transition-all button-soft gentle-shadow font-bold">
            <i class="fas fa-save mr-2"></i>อัปเดตข้อมูล
        </button>
    </div>
</form>

<script>
(function() {
  const fileInput = document.getElementById('image-file-input');
  const previewImg = document.getElementById('image-preview-edit');
  const previewBox = document.getElementById('image-preview-container');
  const pasteBox = document.getElementById('image-paste-box');
  const removeBtn = document.getElementById('remove-image-btn');

  function showPreview(url) {
    if (previewImg) {
      previewImg.src = url;
      previewImg.classList.add('max-h-64','object-contain');
    }
    pasteBox?.classList.add('hidden');
    previewBox?.classList.remove('hidden');
  }

  function showUploader() {
    if (previewImg) previewImg.src = '#';
    previewBox?.classList.add('hidden');
    pasteBox?.classList.remove('hidden');
    if (fileInput) fileInput.value = '';
  }

  fileInput?.addEventListener('change', function(e) {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    showPreview(url);
  });

  removeBtn?.addEventListener('click', function() {
    showUploader();
  });

  window.addEventListener('paste', function(e) {
    const items = e.clipboardData && e.clipboardData.items;
    if (!items) return;
    for (const item of items) {
      if (item.type.indexOf('image') === 0) {
        const file = item.getAsFile();
        const url = URL.createObjectURL(file);
        showPreview(url);
        break;
      }
    }
  }, false);
})();
</script>