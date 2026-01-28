{{--
    ไฟล์นี้คือ Layout ใหม่สำหรับแสดงผลรายการอุปกรณ์แต่ละชิ้นใน Modal
    มีการจัดวางที่เป็นระเบียบมากขึ้นและมีปุ่ม "เพิ่ม" ที่ชัดเจน
--}}
@forelse($items as $item) {{-- Changed variable name from $equipments to $items --}}
    <div class="flex items-center p-3 space-x-4 transition-colors duration-200 rounded-xl soft-card hover:bg-gray-100/50">

        {{-- ✅✅✅ START: แก้ไข Logic การสร้าง URL รูปภาพ (ดีบัคขั้นสุด v2) ✅✅✅ --}}
        @php
            $primaryImage = null;
            $imageFileName = null;
            $imageUrl = asset('images/no-image.png'); // Default
            $debugSource = 'Default Placeholder';
            $debugPrimaryFound = 'No';
            $debugImageCollectionCount = 0;
            $debugFileNameCheck = 'N/A';
            $debugPrimaryImageDump = 'N/A'; // Debug ใหม่: Dump ตัว Image Model

            // 1. ตรวจสอบว่า relation 'images' โหลดมา และไม่ว่างเปล่า
            if ($item->relationLoaded('images') && $item->images->isNotEmpty()) {
                $debugImageCollectionCount = $item->images->count();

                // 2. พยายามหารูป Primary ก่อน, ถ้าไม่มี เอารูปแรก
                $primaryImage = $item->images->firstWhere('is_primary', true) ?? $item->images->first();

                // 3. ตรวจสอบว่าเจอ Model รูปภาพหรือไม่
                if ($primaryImage) {
                    $debugPrimaryFound = 'Yes (ID: ' . $primaryImage->id . ', Class: ' . get_class($primaryImage) . ')';
                    // *** DEBUG DUMP ***
                    ob_start(); // Start output buffering
                    var_dump($primaryImage->toArray()); // Dump attributes
                    $debugPrimaryImageDump = htmlspecialchars(ob_get_clean()); // Get buffered output and clean it

                    // 4. ตรวจสอบว่ามี property 'file_name' และไม่เป็นค่าว่าง
                    // ลองทั้ง $primaryImage->file_name และ $primaryImage['file_name']
                    $fileNameFromProp = $primaryImage->file_name ?? null;
                    $fileNameFromArray = $primaryImage['file_name'] ?? null;

                    if (!empty($fileNameFromProp)) {
                        $imageFileName = $fileNameFromProp;
                        $debugFileNameCheck = 'OK via Property (Found: ' . $imageFileName . ')';
                    } elseif (!empty($fileNameFromArray)) {
                        $imageFileName = $fileNameFromArray;
                        $debugFileNameCheck = 'OK via Array Access (Found: ' . $imageFileName . ')';
                    } else {
                         // กรณีหาไม่เจอทั้งสองแบบ
                         $imageFileName = 'None or Empty';
                         $debugFileNameCheck = 'Failed (file_name is null or empty via both methods)';
                         $debugSource = 'Equipment (Image Model Found, but file_name is Missing)';
                         $imageUrl = 'https://placehold.co/100x100/e2e8f0/64748b?text=No+Filename';
                    }

                    // 5. ถ้าเจอชื่อไฟล์แล้ว (ไม่ว่าจะวิธีไหน) และมี $defaultDeptKey ถึงจะสร้าง URL จริง
                    if ($imageFileName && $imageFileName !== 'None or Empty') {
                        if (isset($defaultDeptKey)) {
                            $debugSource = 'Equipment (NAS)';
                             try {
                                $imageUrl = route('nas.image', ['deptKey' => $defaultDeptKey, 'filename' => $imageFileName]);
                                $debugSource .= ' - Route OK';
                            } catch (\Exception $e) {
                                \Log::error("Failed to generate NAS image route for item {$item->id} in modal: " . $e->getMessage());
                                $imageUrl = 'https://placehold.co/100x100/ffcccc/e74c3c?text=Route+Err';
                                $debugSource .= ' - Route Error';
                            }
                        } else {
                            $debugSource = 'Equipment (Image Model Found, Filename OK, but No DeptKey)';
                            $imageUrl = 'https://placehold.co/100x100/e2e8f0/64748b?text=No+DeptKey';
                        }
                    }
                    // (ส่วน else ถ้าหา file_name ไม่เจอ ถูกย้ายไปอยู่ในเงื่อนไขที่ 4 แล้ว)

                } else {
                     $debugSource = 'Equipment (Images Relation Loaded, but Found No Valid Image Model)';
                     $debugPrimaryFound = 'No (Collection was empty or contained invalid data?)';
                }
            } else {
                 $debugSource = $item->relationLoaded('images') ? 'Equipment (Images Relation Loaded but Empty)' : 'Equipment (Images Relation Not Loaded)';
                 $debugImageCollectionCount = $item->relationLoaded('images') ? 0 : -1; // -1 = Not Loaded
            }

            // --- 🐞 DEBUGGING COMMENTS ---
            // <!-- Item ID: {{ $item->id }} -->
            // <!-- Default Dept Key: {{ $defaultDeptKey ?? 'Not Set' }} -->
            // <!-- Images Collection Count: {{ $debugImageCollectionCount }} -->
            // <!-- Primary Image Found: {{ $debugPrimaryFound }} -->
            // <!-- Primary Image Dump: {{ $debugPrimaryImageDump }} -->
            // <!-- file_name Check: {{ $debugFileNameCheck }} -->
            // <!-- Primary Image Filename: {{ $imageFileName ?? 'None' }} -->
            // <!-- Generated Image URL: {{ $imageUrl }} -->
            // <!-- Debug Source: {{ $debugSource }} -->
            // --- 🐞 END DEBUGGING ---
        @endphp
        {{-- ✅✅✅ END: แก้ไข Logic การสร้าง URL รูปภาพ (ดีบัคขั้นสุด v2) ✅✅✅ --}}

        {{-- ส่วนรูปภาพ (ใช้ $imageUrl ที่สร้างขึ้นใหม่) --}}
        <img src="{{ $imageUrl }}" alt="{{ $item->name }}"
             class="flex-shrink-0 object-cover w-16 h-16 rounded-lg gentle-shadow"
             onerror="this.onerror=null; this.src='{{ asset('images/no-image.png') }}'; console.error('Modal Img Fail:', this.src);"> {{-- Added fallback --}}

        {{-- ส่วนรายละเอียดหลัก --}}
        <div class="flex-grow min-w-0">
            <p class="font-bold text-gray-800 truncate">{{ $item->name }}</p>
            <p class="text-sm text-gray-500 font-mono">S/N: {{ $item->serial_number ?: 'N/A' }}</p>
            {{-- เราจะใช้ Component Status Badge ที่คุณมีอยู่แล้ว --}}
            <div class="mt-1">
                {{-- Make sure StatusBadge component exists and works --}}
                @isset($item->status)
                    <x-status-badge :status="$item->status" />
                @endisset
            </div>
        </div>

        {{-- ส่วนจำนวนคงเหลือและปุ่ม Action --}}
        <div class="flex-shrink-0 text-right">
            <div>
                <span class="text-xl font-bold text-blue-600">{{ $item->quantity }}</span>
                {{-- Use optional chaining for unit --}}
                <span class="text-xs text-gray-500">{{ optional($item->unit)->name ?? 'ชิ้น' }}</span>
            </div>
            <p class="text-xs text-gray-500">คงเหลือในคลัง</p>

            {{-- ปุ่ม "เพิ่ม" ที่ชัดเจน โดยเรียกใช้ฟังก์ชันเดิมของคุณ --}}
            {{-- Make sure promptForQuantity function is globally available --}}
            <button
                onclick="promptForQuantity({{ $item->id }}, '{{ e($item->name) }}')"
                class="px-4 py-2 mt-2 text-sm font-bold text-blue-700 transition-colors bg-blue-100 rounded-lg hover:bg-blue-200">
                <i class="fas fa-plus"></i> เพิ่ม
            </button>
        </div>
    </div>
@empty
    {{-- กรณีไม่พบข้อมูล --}}
    <div class="py-12 text-center text-gray-500">
        <i class="mb-4 text-4xl text-gray-300 fas fa-box-open"></i>
        <p>ไม่พบรายการที่ตรงกับการค้นหา</p>
    </div>
@endforelse {{-- ✅ แก้ไข: @endforelse (ตัวเล็ก) --}}

