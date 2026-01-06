{{--
This partial displays the items within a Purchase Order.
It expects:
- $order: The PurchaseOrder model instance (with 'items.equipment.unit', 'items.equipment.images' loaded).
- $defaultDeptKey: The default department key (e.g., 'it') for generating NAS image URLs.
--}}
<div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm bg-white">
    {{-- Desktop Table View --}}
    <div class="hidden md:block">
        <table class="min-w-full text-sm align-middle">
            <thead class="bg-gray-100">
                <tr class="border-b border-gray-200">
                    <th class="p-3 text-left font-semibold text-gray-600 w-16">รูปภาพ</th>
                    <th class="p-3 text-left font-semibold text-gray-600">ชื่ออุปกรณ์/รายละเอียด</th>
                    <th class="p-3 text-center font-semibold text-gray-600 w-28">จำนวน (สั่งซื้อ)</th>
                    <th class="p-3 text-center font-semibold text-gray-600 w-28">คงเหลือ (สต็อก)</th>
                    <th class="p-3 text-center font-semibold text-gray-600 w-16">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($order->items as $item)
                    {{-- ✅ DEBUG: Dump item data --}}
                    {{-- @dump($item->toArray(), $item->equipment?->toArray(), $item->equipment?->images->toArray()) --}}
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="p-3">
                            {{-- Logic to determine the correct image URL --}}
                            @php
                                $equipment = $item->equipment; // Get the related equipment model (if linked)
                                $imageUrl = asset('images/placeholder.webp'); // Default placeholder image
                                $debugSource = 'Default Placeholder'; // Debug variable

                                // <!-- DEBUG: Check initial values -->
                                // <!-- Item ID: {{ $item->id }}, Equipment linked: {{ $equipment ? 'Yes (ID: '.$equipment->id.')' : 'No' }}, Item has own image: {{ $item->image ? 'Yes ('.$item->image.')' : 'No' }} -->

                                // --- ✅ START: แก้ไขการตรวจสอบ images ---
                                // Check if linked to Equipment AND that Equipment has images collection that is not null and not empty
                                // เปลี่ยนจาก $equipment->relationLoaded('images') เป็น isset($equipment->images)
                                if ($equipment && isset($equipment->images) && $equipment->images->isNotEmpty()) {
                                // --- ✅ END: แก้ไขการตรวจสอบ images ---
                                    // <!-- DEBUG: CASE 1 - Linked Equipment with Images -->
                                    $debugSource = 'Equipment (NAS)';
                                    $primaryImage = $equipment->images->firstWhere('is_primary', true) ?? $equipment->images->first();
                                    $imageFileName = $primaryImage->file_name ?? null;
                                    // <!-- DEBUG: Primary Image Filename: {{ $imageFileName ?? 'Not Found' }} -->

                                    if ($imageFileName && isset($defaultDeptKey)) {
                                        // <!-- DEBUG: Attempting to generate NAS route... -->
                                        try {
                                            $imageUrl = route('nas.image', ['deptKey' => $defaultDeptKey, 'filename' => $imageFileName]);
                                            // <!-- DEBUG: NAS URL Generated: {{ $imageUrl }} -->
                                            $debugSource .= ' - Route OK';
                                        } catch (\Exception $e) {
                                            Log::error("Failed to generate NAS image route for PO item {$item->id}: " . $e->getMessage());
                                            $imageUrl = 'https://placehold.co/100x100/ffcccc/e74c3c?text=Route+Err';
                                            // <!-- DEBUG: NAS Route Generation Failed! -->
                                            $debugSource .= ' - Route Error';
                                        }
                                    } else {
                                         $imageUrl = 'https://placehold.co/100x100/e2e8f0/64748b?text=No+Img+Data'; // Equipment has no image records or filename
                                         // <!-- DEBUG: Equipment has images relation, but no filename or defaultDeptKey missing -->
                                         $debugSource .= ' - No Filename/DeptKey';
                                    }

                                } elseif ($item->image) {
                                    // <!-- DEBUG: CASE 2 - Item has its own image -->
                                    $debugSource = 'Item Upload';
                                    $imagePath = 'uploads/po_items/' . $item->image;
                                    // <!-- DEBUG: Checking path: {{ public_path($imagePath) }} -->
                                    // Check if the file actually exists in the public path
                                    if (file_exists(public_path($imagePath))) {
                                        $imageUrl = asset($imagePath);
                                        // <!-- DEBUG: Item Upload URL Generated: {{ $imageUrl }} -->
                                        $debugSource .= ' - File Found';
                                    } else {
                                         $imageUrl = 'https://placehold.co/100x100/fff3cd/f1c40f?text=File+Missing';
                                         Log::warning("PO Item image file missing: " . public_path($imagePath));
                                         // <!-- DEBUG: Item Upload File Missing! -->
                                         $debugSource .= ' - File Missing';
                                    }
                                } else {
                                    // <!-- DEBUG: CASE 3 - Using Default Placeholder -->
                                    $debugSource = 'Default Placeholder (No Data)';
                                }
                                // Else: No linked equipment and no specific item image, use the default placeholder set initially
                            @endphp

                            {{-- Display the determined image URL --}}
                            <!-- Image Source: {{ $debugSource }} -->
                            <img src="{{ $imageUrl }}"
                                 alt="{{ $item->item_description ?? (optional($equipment)->name ?? 'Item Image') }}"
                                 class="object-cover w-12 h-12 rounded-md gentle-shadow border border-gray-100 bg-white" {{-- Added bg-white --}}
                                 onerror="this.onerror=null; this.src='{{ asset('images/placeholder.webp') }}'; console.error('Image failed to load:', this.src)"> {{-- Final fallback on error + Console log --}}
                        </td>
                        <td class="p-3 align-top">
                            {{-- Display item description or equipment name --}}
                            <p class="font-semibold text-gray-800 break-words">
                                {{ $item->item_description ?? (optional($equipment)->name ?? 'N/A') }}
                            </p>
                            {{-- Display Part Number if available from linked equipment --}}
                            @if(optional($equipment)->part_no)
                                <p class="text-xs text-gray-500 mt-0.5">P/N: {{ $equipment->part_no }}</p>
                            @endif
                             {{-- Display linked GLPI ticket if applicable --}}
                             @if($order->type == 'job_order_glpi' && $order->glpi_ticket_id)
                                 {{-- You might need a link to GLPI here if possible --}}
                                {{-- <p class="text-xs text-purple-600 mt-0.5">From GLPI #{{ $order->glpi_ticket_id }}</p> --}}
                             @endif
                        </td>
                        <td class="p-3 text-center align-top">
                            {{-- Display requested quantity and unit --}}
                            <span class="font-bold text-blue-600">{{ $item->quantity_ordered }}</span>
                            <span class="ml-1 text-gray-500">{{ optional($equipment)->unit->name ?? ($item->unit ?? '') }}</span> {{-- Add item->unit if available --}}
                        </td>
                        <td class="p-3 text-center align-top">
                            {{-- Display current stock quantity if linked to equipment --}}
                            @if($equipment)
                                <span class="{{ $equipment->quantity <= ($equipment->min_stock ?? 0) ? 'text-red-600 font-bold' : '' }}"> {{-- Highlight if low stock --}}
                                    {{ $equipment->quantity ?? 0 }}
                                </span>
                                 {{ optional($equipment->unit)->name ?? '' }}
                            @else
                                <span class="text-xs text-gray-400">-</span> {{-- Show dash if not linked --}}
                            @endif
                        </td>
                        <td class="p-3 text-center align-top">
                            {{-- Show delete button only if the PO is still pending --}}
                            @if($order->status == 'pending')
                                @can('po:manage')
                                {{-- Ensure confirmAndDeleteItem is globally accessible --}}
                                <button type="button"
                                    onclick="confirmAndDeleteItem({{ $item->id }}, {{ $order->id }}, '{{ e(Str::limit($item->item_description ?? (optional($equipment)->name ?? 'รายการนี้'), 30)) }}')"
                                    class="px-2 py-1 text-red-500 transition-colors rounded-md hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-300"
                                    title="ลบรายการนี้">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endcan
                            @else
                                <span class="text-xs text-gray-400">-</span> {{-- No actions if PO not pending --}}
                            @endif
                        </td>
                    </tr>
                @empty
                    {{-- Row displayed when there are no items in the order --}}
                    <tr>
                        <td colspan="5" class="py-6 text-center text-gray-500">
                           <i class="fas fa-box-open mr-2"></i> ยังไม่มีรายการในใบสั่งซื้อนี้
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="block md:hidden divide-y divide-gray-100">
        @forelse($order->items as $item)
            @php
                // Image Logic Copy for Mobile (Simplified for reuse if possible but keeping inline for safety)
                $equipment = $item->equipment;
                $imageUrl = asset('images/placeholder.webp');
                if ($equipment && isset($equipment->images) && $equipment->images->isNotEmpty()) {
                    $primaryImage = $equipment->images->firstWhere('is_primary', true) ?? $equipment->images->first();
                    $imageFileName = $primaryImage->file_name ?? null;
                    if ($imageFileName && isset($defaultDeptKey)) {
                         try { $imageUrl = route('nas.image', ['deptKey' => $defaultDeptKey, 'filename' => $imageFileName]); } catch (\Exception $e) {}
                    }
                } elseif ($item->image && file_exists(public_path('uploads/po_items/' . $item->image))) {
                    $imageUrl = asset('uploads/po_items/' . $item->image);
                }
            @endphp
            <div class="p-4 bg-white hover:bg-gray-50 transition-colors">
                <div class="flex gap-4">
                    {{-- Image --}}
                    <div class="flex-shrink-0 w-16 h-16 bg-gray-50 rounded-lg border border-gray-100">
                        <img src="{{ $imageUrl }}" 
                             alt="Item Image" 
                             class="w-full h-full object-contain p-1"
                             onerror="this.onerror=null; this.src='{{ asset('images/placeholder.webp') }}';">
                    </div>
                    
                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-gray-800 text-sm break-words leading-tight mb-1">
                            {{ $item->item_description ?? (optional($equipment)->name ?? 'N/A') }}
                        </p>
                        @if(optional($equipment)->part_no)
                            <p class="text-xs text-gray-500 font-mono mb-2">P/N: {{ $equipment->part_no }}</p>
                        @endif

                        <div class="flex items-center gap-4 text-xs">
                            <div class="bg-blue-50 px-2 py-1 rounded-md border border-blue-100">
                                <span class="text-gray-500">สั่งซื้อ:</span>
                                <span class="font-bold text-blue-700 text-sm ml-1">{{ $item->quantity_ordered }}</span>
                            </div>
                            @if($equipment)
                            <div class="bg-gray-50 px-2 py-1 rounded-md border border-gray-100">
                                <span class="text-gray-500">สต็อก:</span>
                                <span class="font-bold {{ $equipment->quantity <= ($equipment->min_stock ?? 0) ? 'text-red-600' : 'text-gray-700' }} text-sm ml-1">
                                    {{ $equipment->quantity ?? 0 }}
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Action (Delete) --}}
                    @if($order->status == 'pending')
                         @can('po:manage')
                            <button type="button"
                                onclick="confirmAndDeleteItem({{ $item->id }}, {{ $order->id }}, '{{ e(Str::limit($item->item_description ?? (optional($equipment)->name ?? 'รายการนี้'), 20)) }}')"
                                class="flex-shrink-0 w-8 h-8 flex items-center justify-center text-red-400 bg-red-50 rounded-full hover:bg-red-100 hover:text-red-600 self-center">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                         @endcan
                    @endif
                </div>
            </div>
        @empty
            <div class="py-8 text-center text-gray-400 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200 mt-2">
                <i class="fas fa-box-open mr-2 mb-2 block text-2xl"></i>
                <span class="text-sm">ไม่มีรายการสินค้า</span>
            </div>
        @endforelse
    </div>
</div>

{{-- No scripts needed here if confirmAndDeleteItem is globally defined in index.blade.php or purchase_orders.js --}}
