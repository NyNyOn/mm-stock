{{--
This partial displays the items within a Purchase Order.
It expects:
- $order: The PurchaseOrder model instance (with 'items.equipment.unit', 'items.equipment.images' loaded).
- $defaultDeptKey: The default department key (e.g., 'it') for generating NAS image URLs.
--}}
<div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm bg-white">
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

{{-- No scripts needed here if confirmAndDeleteItem is globally defined in index.blade.php or purchase_orders.js --}}
