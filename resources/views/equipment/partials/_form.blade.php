@php
    // Use 'new' suffix for create form, or equipment ID for edit form
    $uniqueSuffix = $equipment->exists ? $equipment->id : 'new';
    // Define $deptKeyForImages using the passed $defaultDeptKey or fallback
    $deptKeyForImages = $defaultDeptKey ?? config('department_stocks.default_key', 'it');
@endphp

{{-- Main form tag --}}
{{-- ✅ ADDED novalidate to prevent browser validation errors on hidden steps --}}
<form id="{{ $equipment->exists ? 'edit-equipment-form-'.$equipment->id : 'create-equipment-form-new' }}"
      method="POST"
      action="{{ $equipment->exists ? route('equipment.update', $equipment->id) : route('equipment.store') }}"
      enctype="multipart/form-data"
      novalidate> {{-- Prevent default browser validation --}}
    @csrf
    @if($equipment->exists)
        @method('PATCH') {{-- Use PATCH for updates --}}
    @endif

    {{-- ✅ MOVED: Stepper Indicators are now here, directly under the form tag --}}
    {{-- ========== 1. Stepper Indicators ========== --}}
    <div class="px-4 py-3 border-b border-gray-200"> {{-- Adjusted padding --}}
        <ol class="flex items-center w-full">
            {{-- Step 1 Indicator --}}
            <li id="step-indicator-1-{{ $uniqueSuffix }}" class="stepper-indicator active flex w-full items-center text-blue-600 after:content-[''] after:w-full after:h-1 after:border-b after:border-gray-200 after:border-1 after:inline-block">
                <span class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-full shrink-0">
                    <span class="stepper-text">1</span>
                    <i class="fas fa-check stepper-check hidden"></i>
                </span>
                <span class="ml-2 text-sm font-medium">ข้อมูลพื้นฐาน</span>
            </li>
            {{-- Step 2 Indicator --}}
            <li id="step-indicator-2-{{ $uniqueSuffix }}" class="stepper-indicator pending flex w-full items-center text-gray-500 after:content-[''] after:w-full after:h-1 after:border-b after:border-gray-200 after:border-1 after:inline-block">
                <span class="flex items-center justify-center w-8 h-8 bg-gray-100 rounded-full shrink-0">
                    <span class="stepper-text">2</span>
                    <i class="fas fa-check stepper-check hidden"></i>
                </span>
                <span class="ml-2 text-sm font-medium">สต็อก/จัดซื้อ</span>
            </li>
            {{-- Step 3 Indicator --}}
            <li id="step-indicator-3-{{ $uniqueSuffix }}" class="stepper-indicator pending flex items-center text-gray-500">
                <span class="flex items-center justify-center w-8 h-8 bg-gray-100 rounded-full shrink-0">
                    <span class="stepper-text">3</span>
                    <i class="fas fa-check stepper-check hidden"></i>
                </span>
                <span class="ml-2 text-sm font-medium">ไฟล์แนบ</span>
            </li>
        </ol>
    </div>

    {{-- General Error Area --}}
    {{-- ✅ EDIT: Removed top padding (pt-2) completely --}}
    <div class="general-errors text-red-500 text-sm px-6"></div>


    {{-- ========== 2. Stepper Content Panels (Scrollable Area) ========== --}}
    {{-- This container holds all step panels --}}
    {{-- ✅✅✅ EDIT: Re-added max-h-[65vh] and overflow-y-auto ✅✅✅ --}}
    {{-- ✅ (Fix 2) ปรับ Padding ให้น้อยลงบนมือถือ --}}
    <div id="form-stepper-content-{{ $uniqueSuffix }}" class="p-4 sm:p-6 bg-gray-50 max-h-[65vh] overflow-y-auto scrollbar-soft">

        {{-- === Step 1: Basic Information === --}}
        <div id="step-1-panel-{{ $uniqueSuffix }}" class="step-panel">
            {{-- ... Step 1 content remains the same ... --}}
            <fieldset class="p-6 bg-white rounded-xl border border-gray-200 shadow-sm">
                <legend class="text-base font-semibold text-gray-700 mb-5 px-2">ขั้นตอนที่ 1: ข้อมูลพื้นฐาน</legend>
                
                {{-- ✅ EDIT 1: Added clear-both --}}
                <div class="space-y-5 clear-both">
                    <div class="form-group">
                        <label for="name-{{ $uniqueSuffix }}" class="form-label mb-1.5">ชื่ออุปกรณ์ <span class="text-red-500">*</span></label>
                        <input type="text" id="name-{{ $uniqueSuffix }}" name="name" required value="{{ old('name', $equipment->name ?? '') }}" class="input-form">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="form-group">
                            <label for="category_id-{{ $uniqueSuffix }}" class="form-label mb-1.5">ประเภท <span class="text-red-500">*</span></label>
                            <select id="category_id-{{ $uniqueSuffix }}" name="category_id" required class="input-form">
                                <option value="">-- เลือกประเภท --</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_id', $equipment->category_id ?? '') == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group">
                            <label for="location_id-{{ $uniqueSuffix }}" class="form-label mb-1.5">สถานที่จัดเก็บ <span class="text-red-500">*</span></label>
                            <select id="location_id-{{ $uniqueSuffix }}" name="location_id" required class="input-form">
                                <option value="">-- เลือกสถานที่ --</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" @selected(old('location_id', $equipment->location_id ?? '') == $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                         <div class="form-group">
                            <label for="part_no-{{ $uniqueSuffix }}" class="form-label mb-1.5">Part Number</label>
                            <input type="text" id="part_no-{{ $uniqueSuffix }}" name="part_no" value="{{ old('part_no', $equipment->part_no ?? '') }}" class="input-form">
                            <div class="invalid-feedback"></div>
                        </div>
                        {{-- ✅ START EDIT: Removed Generate Button & Adjusted Input --}}
                        <div class="form-group">
                            <label for="serial_number-{{ $uniqueSuffix }}" class="form-label mb-1.5">Serial Number</label>
                            {{-- Removed the wrapping div and the button --}}
                            <input type="text" id="serial_number-{{ $uniqueSuffix }}" name="serial_number" value="{{ old('serial_number', $equipment->serial_number ?? '') }}" class="input-form"> {{-- Removed rounded-r-none flex-grow --}}
                            <div class="invalid-feedback"></div>
                        </div>
                        {{-- ✅ END EDIT --}}
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="form-group">
                            <label for="model_name-{{ $uniqueSuffix }}" class="form-label mb-1.5">ชื่อรุ่น</label>
                            <input type="text" id="model_name-{{ $uniqueSuffix }}" name="model_name" value="{{ old('model_name', $equipment->model_name ?? '') }}" class="input-form">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group">
                            <label for="model_number-{{ $uniqueSuffix }}" class="form-label mb-1.5">หมายเลขรุ่น</label>
                            <input type="text" id="model_number-{{ $uniqueSuffix }}" name="model_number" value="{{ old('model_number', $equipment->model_number ?? '') }}" class="input-form">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="withdrawal_type-{{ $uniqueSuffix }}" class="form-label mb-1.5">ประเภทการเบิก <span class="text-red-500">*</span></label>
                        <select id="withdrawal_type-{{ $uniqueSuffix }}" name="withdrawal_type" required class="input-form">
                            <option value="">-- เลือกประเภท --</option>
                            <option value="consumable" @selected(old('withdrawal_type', $equipment->withdrawal_type ?? '') == 'consumable')>พัสดุสิ้นเปลือง (ไม่ต้องคืน)</option>
                            <option value="returnable" @selected(old('withdrawal_type', $equipment->withdrawal_type ?? '') == 'returnable')>เบิกแล้วต้องคืน</option>
                            <option value="partial_return" @selected(old('withdrawal_type', $equipment->withdrawal_type ?? '') == 'partial_return')>เบิกแบบเหลือแล้วคืนได้</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
            </fieldset>
        </div>

        {{-- === Step 2: Stock & Purchase === --}}
        <div id="step-2-panel-{{ $uniqueSuffix }}" class="step-panel hidden">
            {{-- ... Step 2 content remains the same ... --}}
             <div class="space-y-8">
                <fieldset class="p-6 bg-white rounded-xl border border-gray-200 shadow-sm">
                    <legend class="text-base font-semibold text-gray-700 mb-5 px-2">ขั้นตอนที่ 2: ข้อมูลสต็อก</legend>
                    
                    {{-- ✅ START: (Layout Fix) จัดเรียงใหม่ตามรูปวาด (image_d941e4.png) --}}
                    {{-- ใช้ space-y-5 เพื่อเรียง 3 แถวในแนวตั้ง --}}

                    {{-- ✅ EDIT 1: Added clear-both --}}
                    <div class="space-y-5 clear-both">

                        {{-- แถวที่ 1: "คงคลัง" (Full Width) --}}
                        <div class="form-group">
                            <label for="quantity-{{ $uniqueSuffix }}" class="form-label mb-1.5">คงคลัง <span class="text-red-500">*</span></label>
                            <input type="text" id="quantity-{{ $uniqueSuffix }}" name="quantity" required value="{{ old('quantity', $equipment->quantity ?? 0) }}"
                                   inputmode="numeric" pattern="[0-9]*"
                                   @if(!($canEditQuantity ?? true)) readonly class="input-form bg-gray-100 cursor-not-allowed" @else class="input-form" @endif>
                            @if(!($canEditQuantity ?? true))
                                <small class="text-xs text-orange-500 mt-1 block"><i class="fas fa-lock mr-1"></i>คุณไม่มีสิทธิ์แก้ไขจำนวนคงคลัง</small>
                            @endif
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        {{-- แถวที่ 2: "ขั้นต่ำ" / "สูงสุด" (50/50 Split) --}}
                        {{-- ใช้ grid-cols-1 (มือถือ) และ sm:grid-cols-2 (จอใหญ่) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div class="form-group">
                                <label for="min_stock-{{ $uniqueSuffix }}" class="form-label mb-1.5">ขั้นต่ำ <span class="text-red-500">*</span></label>
                                <input type="text" id="min_stock-{{ $uniqueSuffix }}" name="min_stock" required value="{{ old('min_stock', $equipment->min_stock ?? 0) }}"
                                       inputmode="numeric" pattern="[0-9]*" class="input-form">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-group">
                                <label for="max_stock-{{ $uniqueSuffix }}" class="form-label mb-1.5">สูงสุด <span class="text-red-500">*</span></label>
                                <input type="text" id="max_stock-{{ $uniqueSuffix }}" name="max_stock" required value="{{ old('max_stock', $equipment->max_stock ?? 0) }}"
                                       inputmode="numeric" pattern="[0-9]*" class="input-form">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>

                        {{-- แถวที่ 3: "หน่วยนับ" (Full Width) --}}
                        <div class="form-group">
                            <label for="unit_id-{{ $uniqueSuffix }}" class="form-label mb-1.5">หน่วยนับ <span class="text-red-500">*</span></label>
                            <select id="unit_id-{{ $uniqueSuffix }}" name="unit_id" required class="input-form">
                                <option value="">-- เลือกหน่วย --</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}" @selected(old('unit_id', $equipment->unit_id ?? '') == $unit->id)>{{ $unit->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                    </div>
                    {{-- ✅ END: (Layout Fix) --}}

                </fieldset>

                <fieldset class="p-6 bg-white rounded-xl border border-gray-200 shadow-sm">
                    <legend class="text-base font-semibold text-gray-700 mb-5 px-2">ข้อมูลจัดซื้อ/ประกัน</legend>
                    
                    {{-- ✅ EDIT 1: Added clear-both --}}
                    {{-- ✅ EDIT 2: Re-structured layout to match "Stock" layout pattern --}}
                    <div class="space-y-5 clear-both">
                        
                        {{-- แถวที่ 1: "ผู้จัดจำหน่าย" (Full Width) --}}
                        <div class="form-group">
                            <label for="supplier-{{ $uniqueSuffix }}" class="form-label mb-1.5">ผู้จัดจำหน่าย</label>
                            <input type="text" id="supplier-{{ $uniqueSuffix }}" name="supplier" value="{{ old('supplier', $equipment->supplier ?? '') }}" class="input-form">
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        {{-- แถวที่ 2: "วันที่ซื้อ" / "วันหมดประกัน" (50/50 Split) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div class="form-group">
                                <label for="purchase_date-{{ $uniqueSuffix }}" class="form-label mb-1.5">วันที่ซื้อ</label>
                                <input type="date" id="purchase_date-{{ $uniqueSuffix }}" name="purchase_date" value="{{ old('purchase_date', optional($equipment->purchase_date)->format('Y-m-d') ?? '') }}" class="input-form">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-group">
                                <label for="warranty_date-{{ $uniqueSuffix }}" class="form-label mb-1.5">วันหมดประกัน</label>
                                <input type="date" id="warranty_date-{{ $uniqueSuffix }}" name="warranty_date" value="{{ old('warranty_date', optional($equipment->warranty_date)->format('Y-m-d') ?? '') }}" class="input-form">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>

                    </div>
                    {{-- ✅ END EDIT 2 --}}

                </fieldset>
            </div>
        </div>

        {{-- === Step 3: Files & Notes === --}}
        <div id="step-3-panel-{{ $uniqueSuffix }}" class="step-panel hidden">
            {{-- ... Step 3 content remains the same ... --}}
             <div class="space-y-8">
                <fieldset class="p-6 bg-white rounded-xl border border-gray-200 shadow-sm">
                    <legend class="text-base font-semibold text-gray-700 mb-5 px-2">ขั้นตอนที่ 3: รูปภาพ</legend>
                    
                    {{-- ✅ EDIT 1: Added clear-both --}}
                    <div class="space-y-5 clear-both">
                        <div class="form-group">
                            <label for="images-{{ $uniqueSuffix }}" class="form-label mb-1.5">อัปโหลดรูปภาพใหม่</label>
                            
                            {{-- ✅✅✅ START: (เฟส 3.2) แก้ไข Input File ✅✅✅ --}}
                            <input type="file" id="images-{{ $uniqueSuffix }}" name="images[]" multiple 
                                   accept="image/*,.heic,.heif" 
                                   capture="environment" 
                                   class="input-file-form">
                            {{-- ✅✅✅ END: (เฟส 3.2) แก้ไข Input File ✅✅✅ --}}
                                    
                            <div class="invalid-feedback"></div>
                            <div id="image-previews-{{ $uniqueSuffix }}" class="grid grid-cols-3 gap-3 mt-4">
                                {{-- JS will add previews here --}}
                            </div>
                        </div>

                        @if($equipment->exists && $equipment->images->isNotEmpty())
                        <div class="mt-5 border-t border-gray-200 pt-5 form-group">
                            <h4 class="mb-3 text-xs font-semibold text-gray-500 uppercase">รูปภาพที่มีอยู่</h4>
                            <div id="existing-images-container-{{ $uniqueSuffix }}" class="grid grid-cols-3 gap-3">
                                @foreach($equipment->images as $image)
                                    @if($image->file_name)
                                    <div id="image-{{ $image->id }}-wrapper" class="relative group rounded-lg overflow-hidden border-2 transition-all duration-300 {{ $image->is_primary ? 'border-yellow-400 shadow-md ring-2 ring-yellow-100' : 'border-transparent hover:border-gray-200' }}">
                                        
                                        @if($image->is_primary)
                                            <span class="absolute top-0 left-0 bg-yellow-400 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-br z-10 shadow-sm">MASTER</span>
                                        @endif

                                        <img src="{{ route('nas.image', ['deptKey' => $deptKeyForImages, 'filename' => $image->file_name]) }}"
                                             alt="Existing Image {{ $image->id }}" class="object-cover w-full h-24"
                                             onerror="this.onerror=null; this.src='https://placehold.co/100x100/e2e8f0/64748b?text=Error';">
                                        <div class="absolute inset-0 flex items-center justify-center space-x-1 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-opacity">
                                            <button type="button" data-image-id="{{ $image->id }}" title="Mark for Deletion"
                                                    class="delete-existing-image-btn w-6 h-6 bg-red-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 focus:opacity-100 transform hover:scale-110 transition-all">
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                            <input type="hidden" id="delete_image_{{ $image->id }}" name="delete_images[]" value="{{ $image->id }}" disabled>
                                            
                                            {{-- Radio for Primary Selection (Hidden logic, visual only) --}}
                                            <label title="Set as Primary" class="w-6 h-6 rounded-full flex items-center justify-center cursor-pointer opacity-0 group-hover:opacity-100 focus-within:opacity-100 transform hover:scale-110 transition-all {{ $image->is_primary ? 'bg-yellow-400 text-white' : 'bg-gray-200 text-gray-500 hover:bg-yellow-400 hover:text-white' }}">
                                                <input type="radio" name="primary_image" value="{{ $image->id }}" class="primary-image-radio sr-only" @checked($image->is_primary) onchange="updatePrimaryVisual(this)">
                                                <i class="fas fa-star text-xs"></i>
                                            </label>
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                            <small class="block mt-3 text-xs text-gray-500">คลิก <i class="fas fa-star text-yellow-400"></i> เพื่อเลือกรูปหลัก, รูปแรกสุดจะแสดงเป็นปก</small>
                        </div>
                        @endif
                    </div>
                </fieldset>

                <fieldset class="p-6 bg-white rounded-xl border border-gray-200 shadow-sm">
                    <legend class="text-base font-semibold text-gray-700 mb-5 px-2">ข้อมูล MSDS</legend>
                    
                    {{-- ✅ EDIT 1: Added clear-both --}}
                    <div class="form-group clear-both">
                        <div class="flex items-center mb-4">
                            <input type="checkbox" id="has_msds_checkbox-{{ $uniqueSuffix }}" name="has_msds" value="1"
                                   class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                                   @checked(old('has_msds', $equipment->has_msds ?? false)) >
                            <label for="has_msds_checkbox-{{ $uniqueSuffix }}" class="ml-3 text-sm font-medium text-gray-700">มีเอกสาร MSDS</label>
                        </div>
                        <div id="msds-management-container-{{ $uniqueSuffix }}" class="space-y-3" style="{{ old('has_msds', $equipment->has_msds ?? false) ? '' : 'display: none;' }}">
                            <button type="button" id="manage-msds-btn-{{ $uniqueSuffix }}" class="btn-secondary text-sm w-full">
                                <i class="fas fa-file-alt mr-2"></i>จัดการข้อมูล/ไฟล์ MSDS
                            </button>
                            <small id="msds-file-status-{{ $uniqueSuffix }}" class="block mt-2 text-xs text-gray-500">
                                @if(!empty($equipment->msds_file_path))
                                    ไฟล์ปัจจุบัน: <a href="{{ $equipment->msds_file_url }}" target="_blank" class="text-blue-600 hover:underline">{{ basename($equipment->msds_file_path) }}</a>
                                @else
                                    ยังไม่มีการอัปโหลดไฟล์ MSDS
                                @endif
                            </small>
                        </div>
                            <input type="hidden" name="msds_details" id="msds_details_hidden-{{ $uniqueSuffix }}" value="{{ old('msds_details', $equipment->msds_T ?? '') }}">
                            {{-- ✅ New Hidden File Input for MSDS --}}
                            <input type="file" name="msds_file" id="msds_file_hidden-{{ $uniqueSuffix }}" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
                        </div>
                </fieldset>

                <fieldset class="p-6 bg-white rounded-xl border border-gray-200 shadow-sm hidden">
                    <legend class="text-base font-semibold text-gray-700 mb-5 px-2">หมายเหตุ</legend>
                    
                    {{-- ✅ EDIT 1: Added clear-both --}}
                    <div class="form-group clear-both">
                        <textarea id="notes-{{ $uniqueSuffix }}" name="notes" rows="4" class="input-form">{{ old('notes', $equipment->notes ?? '') }}</textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                </fieldset>
             </div>
        </div>
    </div>

    {{-- ========== 3. Form Buttons Footer ========== --}}
    {{-- ✅ (Fix 1) แก้ไขปุ่มซ้อนกันบนมือถือ:
         - flex-col-reverse: เรียงแนวตั้งบนมือถือ (ปุ่ม Action อยู่บน)
         - sm:flex-row: กลับเป็นแนวนอนบนจอใหญ่
         - sm:justify-between: กลับไปจัดซ้าย-ขวา บนจอใหญ่
         - gap-2: เพิ่มระยะห่าง
    --}}
    <div class="px-4 py-3 flex flex-col-reverse sm:flex-row sm:justify-between sm:items-center gap-2 bg-gray-50 border-t border-gray-200 rounded-b-xl">
        {{-- Left Side: Cancel Button --}}
        <button type="button" class="btn-secondary close-modal-btn w-full sm:w-auto">
            ยกเลิก
        </button>

        {{-- Right Side: Navigation & Actions (Prev, Next, Save) --}}
        <div class="flex flex-row gap-2 w-full sm:w-auto sm:justify-end">
            {{-- Previous Button --}}
            <button type="button" id="prev-step-btn-{{ $uniqueSuffix }}" class="btn-secondary hidden flex-1 sm:flex-none sm:w-auto">
                <i class="fas fa-arrow-left mr-2"></i> ย้อนกลับ
            </button>

            {{-- Next Button --}}
            <button type="button" id="next-step-btn-{{ $uniqueSuffix }}" class="btn-primary flex-1 sm:flex-none sm:w-auto">
                ถัดไป <i class="fas fa-arrow-right ml-2"></i>
            </button>

            {{-- Submit Button --}}
            <button type="submit" class="btn-primary hidden flex-1 sm:flex-none sm:w-auto" id="submit-btn-{{ $uniqueSuffix }}">
                 <i class="fas fa-save mr-2"></i>บันทึกข้อมูล
            </button>
        </div>
    </div>
</form> {{-- End Form --}}

{{-- Styles are correct, no changes needed here --}}
<style>
    /* ... Existing styles ... */
     .form-label { display: block; font-size: 0.875rem; line-height: 1.25rem; font-weight: 500; color: rgb(55 65 81); }
    
    /* ✅ (Fix 3) แก้ปัญหาช่องตัวเลขมองไม่เห็นบนมือถือ
       - เปลี่ยน font-size จาก 0.875rem (14px) เป็น 1rem (16px) เพื่อป้องกัน iOS auto-zoom
    */
    .input-form { display: block; width: 100%; padding: 0.75rem 1rem; font-size: 1rem; /* 👈 แก้ไขตรงนี้ */ font-weight: 500; color: rgb(55 65 81); background-color: #fff; border: 1px solid rgb(209 213 219); border-radius: 0.75rem; transition: border-color 150ms ease-in-out, box-shadow 150ms ease-in-out; }
    
    .input-form:focus { border-color: rgb(59 130 246); box-shadow: 0 0 0 2px rgb(59 130 246 / 0.5); outline: 2px solid transparent; outline-offset: 2px; }
    .input-form.bg-gray-100 { background-color: rgb(243 244 246); }
    .input-form.cursor-not-allowed { cursor: not-allowed; }
    .input-file-form { display: block; width: 100%; font-size: 0.875rem; line-height: 1.25rem; color: rgb(107 114 128); border: 1px solid rgb(209 213 219); border-radius: 0.5rem; cursor: pointer; background-color: #fff; }
    .input-file-form:focus { outline: 1px solid rgb(59 130 246 / 0.5); }
    .input-file-form::file-selector-button { margin-right: 1rem; padding: 0.5rem 1rem; border-radius: 0.5rem 0 0 0.5rem; border-width: 0; font-size: 0.875rem; font-weight: 600; background-color: rgb(239 246 255); color: rgb(29 78 216); transition: background-color 150ms ease-in-out; }
    .input-file-form:hover::file-selector-button { background-color: rgb(219 234 254); }
    .invalid-feedback { color: rgb(239 68 68); font-size: 0.75rem; line-height: 1rem; margin-top: 0.375rem; }
    input.is-invalid, select.is-invalid, textarea.is-invalid { border-color: rgb(239 68 68); box-shadow: 0 0 0 1px rgb(239 68 68); }
    .btn-primary { display: inline-flex; align-items: center; justify-content: center; padding: 0.625rem 1.5rem; font-size: 0.875rem; font-weight: 500; color: #fff; background-color: rgb(37 99 235); border: 1px solid transparent; border-radius: 0.5rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); transition: background-color 150ms ease-in-out; }
    .btn-primary:hover { background-color: rgb(29 78 216); }
    .btn-primary:focus { outline: 2px solid transparent; outline-offset: 2px; box-shadow: 0 0 0 2px white, 0 0 0 4px rgb(59 130 246); }
    .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
    .btn-secondary { display: inline-flex; align-items: center; justify-content: center; margin-right: 0.5rem; padding: 0.625rem 1.5rem; font-size: 0.875rem; font-weight: 500; color: rgb(55 65 81); background-color: #fff; border: 1px solid rgb(209 213 219); border-radius: 0.5rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); transition: background-color 150ms ease-in-out; }
    .btn-secondary:hover { background-color: rgb(249 250 251); }
    .btn-secondary:focus { outline: 2px solid transparent; outline-offset: 2px; box-shadow: 0 0 0 2px white, 0 0 0 4px rgb(99 102 241); }
    .stepper-indicator.active .stepper-text { color: rgb(37 99 235); } .stepper-indicator.active span:first-child { background-color: rgb(219 234 254); } .stepper-indicator.active { color: rgb(37 99 235); }
    .stepper-indicator.completed .stepper-text { display: none; } .stepper-indicator.completed .stepper-check { display: block; color: #fff; } .stepper-indicator.completed span:first-child { background-color: rgb(22 163 74); } .stepper-indicator.completed { color: rgb(22 163 74); } .stepper-indicator.completed::after { border-color: rgb(22 163 74); }
    .stepper-indicator.pending .stepper-text { color: rgb(107 114 128); } .stepper-indicator.pending span:first-child { background-color: rgb(243 244 246); } .stepper-indicator.pending { color: rgb(107 114 128); } .stepper-indicator.pending::after { border-color: rgb(229 231 235); }
    fieldset legend { float: left; padding: 0 0.5rem; margin-left: 0.5rem; font-size: 0.875rem; line-height: 1.25rem; }
    fieldset { padding-top: 1rem !important; }

    {{-- ✅ NOTE: This relies on 'clear-both' being available in your Tailwind config --}}
    {{-- If not, you might need to add: .clear-both { clear: both; } --}}
</style>

{{-- ========== 4. Stepper JavaScript Logic (Simplified - relies on equipment.js for core logic) ========== --}}
{{-- This script now only focuses on initializing the stepper UI and attaching listeners to stepper buttons --}}
{{-- It assumes the main validation and AJAX logic is in equipment.js --}}
<script>
    // Self-invoking function to attach listeners to this dynamically loaded form
    (function() {
        console.log(`%c--- [STEPPER DEBUG] STARTING INIT (PARTIAL) ---`, "color: #28a745; font-size: 1.2em;");
        const uniqueSuffix = "{{ $uniqueSuffix }}";
        const formId = `{{ $equipment->exists ? 'edit-equipment-form-'.$equipment->id : 'create-equipment-form-new' }}`;
        const stepperForm = document.getElementById(formId);

        if (!stepperForm) {
            console.error(`%c[STEPPER DEBUG] CRITICAL: Form #${formId} not found. Stepper will NOT work.`, "color: #red; font-weight: bold;");
            return;
        }
        console.log(`%c[STEPPER DEBUG] 1. Form #${formId} found. Suffix: '${uniqueSuffix}'`, "color: #28a745;");

        // Prevent default 'Enter' key submission (Remains the same)
        stepperForm.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                const target = event.target;
                if (target && target.tagName.toLowerCase() !== 'textarea') {
                    event.preventDefault();
                    console.log(`%c[STEPPER DEBUG] Enter key pressed on non-textarea. Prevented submit.`, "color: #orange;");
                }
            }
        });

        // We rely on equipment.js to call attachFormEventListeners
        if (typeof attachFormEventListeners !== 'function') {
            console.error(`%c[STEPPER DEBUG] CRITICAL: global 'attachFormEventListeners' function is NOT defined. Submit validation will fail.`, "color: #red; font-weight: bold;");
        } else {
             console.log(`%c[STEPPER DEBUG] Global 'attachFormEventListeners' function found. (Will be called by equipment.js)`, "color: #28a745;");
        }

        console.log(`%c--- [STEPPER DEBUG] INIT FINISHED (PARTIAL VIEW) ---`, "color: #28a745; font-size: 1.2em;");

    })();
</script>