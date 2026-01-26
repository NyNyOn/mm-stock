@if($pendingPOs->isEmpty())
    <div class="bg-white shadow-xl rounded-2xl p-16 text-center border border-gray-200 flex flex-col items-center justify-center min-h-[400px]">
        {{-- ✅ แก้ไข SVG Path ที่ผิดพลาด (l-2-414 -> l-2.414) --}}
        <svg class="h-20 w-20 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        </svg>
        <h3 class="text-2xl font-bold text-gray-900">ไม่พบรายการรอตรวจรับ</h3>
        <p class="text-gray-500 mt-2">รายการที่ได้รับการแจ้งส่งจากฝ่ายจัดซื้อ (PU) จะปรากฏที่นี่</p>
    </div>
@else
    <!-- Global Form Container -->
    <form action="{{ route('receive.process') }}" id="receiveForm" method="POST">
        @csrf
        
        <div class="space-y-10">
            @foreach($pendingPOs as $po)
                <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-200">
                    <div class="bg-gray-100 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">เอกสารอ้างอิง</span>
                            <div class="flex items-baseline gap-2 mt-0.5">
                                @if($po->po_number)
                                    <span class="text-xl font-black text-indigo-700" title="PO Number">
                                        <i class="fas fa-file-invoice mr-1"></i>{{ $po->po_number }}
                                    </span>
                                @else
                                    <span class="text-xl font-black text-gray-400" title="Internal ID">#{{ $po->id }}</span>
                                @endif
                                
                                @if($po->pr_number)
                                    <span class="text-xs font-bold text-gray-500 bg-gray-200 px-2 py-1 rounded-md border border-gray-300" title="PR Number">
                                        PR: {{ $po->pr_number }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-600 border border-blue-100 uppercase shadow-sm">
                            สถานะ: {{ $po->status_label }}
                        </span>
                    </div>

                    <div class="w-full">
                        <!-- Desktop Header (Hidden on Mobile) -->
                        <div class="hidden md:flex bg-white text-gray-500 text-xs uppercase font-bold tracking-wider sticky top-0 border-b border-gray-200">
                            <div class="px-4 py-3 w-20 text-center">รูปภาพ</div>
                            <div class="px-4 py-3 flex-1 text-left">รายละเอียดและข้อมูล PO</div>
                            <div class="px-4 py-3 w-24 text-center bg-gray-50/50">ยอดค้างรับ</div>
                            <div class="px-4 py-3 w-64 text-left pl-6 bg-indigo-50/10">1. ผลการตรวจรับ</div>
                            <div class="px-4 py-3 w-32 text-center bg-indigo-50/10">2. จำนวนรับจริง</div>
                            <div class="px-4 py-3 w-48 text-right pr-6 bg-indigo-50/10">3. การดำเนินการ</div>
                        </div>

                        <!-- Rows Container -->
                        <div class="divide-y divide-gray-100 text-sm bg-white">
                            @foreach($po->items as $item)
                                @php
                                    $remaining = $item->quantity_ordered - ($item->quantity_received ?? 0);
                                    $isLinked = !is_null($item->equipment);
                                    $itemId = (int) $item->id;
                                    $qtyToUse = (int) $remaining; 
                                    $imgUrl = ($item->equipment && $item->equipment->latestImage) ? $item->equipment->latestImage->image_url : asset('images/placeholder.webp');
                                    $itemName = $item->item_description ?? ($item->equipment->name ?? 'N/A');
                                    $unitName = $item->equipment->unit->name ?? 'หน่วย';
                                @endphp
                                
                                <!-- Responsive Row Item -->
                                <div id="row-{{ $itemId }}" 
                                    data-item-id="{{ $itemId }}" 
                                    data-max-qty="{{ $qtyToUse }}" 
                                    data-status="" 
                                    class="flex flex-col md:flex-row transition-colors duration-200 hover:bg-gray-50 border-b md:border-b-0 last:border-0 relative">
                                    
                                    <!-- 1. รูปภาพ (Image) -->
                                    <div class="p-4 md:w-20 md:text-center flex-shrink-0 flex items-center justify-center md:items-start md:justify-center bg-gray-50 md:bg-transparent">
                                        <img src="{{ $imgUrl }}" onerror="this.onerror=null;this.src='{{ asset('images/placeholder.webp') }}';" 
                                             class="w-20 h-20 md:w-16 md:h-16 rounded-xl object-cover border-2 border-gray-200 bg-white shadow-md">
                                    </div>
                                    
                                    <!-- 2. รายละเอียด (Details) -->
                                    <div class="px-4 py-2 md:py-4 md:flex-1">
                                        <div class="md:hidden text-xs font-bold text-gray-400 uppercase mb-1">รายละเอียดสินค้า</div>
                                        <p class="text-base font-bold text-gray-900 line-clamp-2 mb-1">{{ $itemName }}</p>
                                        <div class="text-xs text-gray-500 space-y-0.5">
                                            <p><span class="font-semibold text-gray-700">รหัสอุปกรณ์:</span> {{ $item->equipment_id ?? 'N/A' }}</p>
                                            <p>
                                                <span class="font-semibold text-gray-700">สั่งซื้อ:</span> <span class="font-bold text-indigo-600">{{ $item->quantity_ordered }}</span> {{ $unitName }}
                                                | <span class="font-semibold text-gray-700">รับเข้าแล้ว:</span> <span class="font-bold text-green-600">{{ $item->quantity_received ?? 0 }}</span> {{ $unitName }}
                                            </p>
                                            @if(!$isLinked) 
                                                <div class="mt-2">
                                                    <button type="button" onclick="openLinkModal({{ $itemId }}, '{{ addslashes($item->item_description) }}')"
                                                            class="inline-flex items-center px-3 py-1.5 border border-indigo-300 text-xs font-bold rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors shadow-sm">
                                                        <i class="fas fa-link mr-1.5"></i> เชื่อมโยงอุปกรณ์ (Link)
                                                    </button>
                                                    <p class="text-[10px] text-gray-500 mt-1 pl-1">
                                                        ต้องเชื่อมโยงกับอุปกรณ์ในระบบก่อนจึงจะรับเข้าได้
                                                    </p>
                                                </div>
                                            @endif
                                        </div>
                                        <div id="hidden-inputs-{{ $itemId }}"></div>
                                    </div>
                                    
                                    <!-- 3. ยอดค้างรับ (Remaining) -->
                                    <div class="px-4 py-2 md:py-4 md:w-24 md:text-center md:bg-gray-50/50 md:border-r border-gray-100 flex items-center justify-between md:flex-col md:justify-start">
                                        <span class="md:hidden text-sm font-bold text-gray-600">ยอดค้างรับ:</span>
                                        <div class="text-center">
                                            <div class="font-extrabold text-xl text-red-600">{{ $remaining }}</div>
                                            <span class="text-sm font-medium text-gray-500">{{ $unitName }}</span>
                                        </div>
                                    </div>

                                    <!-- 4. ผลการตรวจรับ (Inspection) -->
                                    <div class="px-4 py-2 md:py-4 md:w-64 md:pl-6 bg-indigo-50/10 border-t md:border-t-0 border-dashed border-gray-200">
                                        <label class="md:hidden block text-xs font-bold text-indigo-500 uppercase mb-1">1. ผลการตรวจรับ</label>
                                        
                                        @if($item->inspection_status)
                                            {{-- ✅ Case: Already inspected/rejected -> Block Input --}}
                                            <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-xl text-center relative group">
                                                <div class="text-sm font-bold text-yellow-700">
                                                    <i class="fas fa-clock mr-1"></i> รอ PU ตรวจสอบ
                                                </div>
                                                <div class="text-xs text-yellow-600 mt-1">
                                                    ส่งผล: {{ match($item->inspection_status) {
                                                        'pass' => '✅ ครบถ้วน',
                                                        'incomplete' => '📦 ไม่ครบ',
                                                        'damaged' => '🔨 เสียหาย',
                                                        'wrong_item' => '❌ ผิดรุ่น',
                                                        'quality_issue' => '⚠️ คุณภาพ',
                                                        default => $item->inspection_status
                                                    } }}
                                                </div>
                                                
                                                {{-- ✈️ Resend Button (Visible on Hover) --}}
                                                <button type="button" onclick="resendInspection({{ $item->id }})" 
                                                        class="absolute top-1 right-1 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity p-1.5 bg-blue-500 text-white rounded-full shadow-md hover:bg-blue-600 focus:ring-2 focus:ring-blue-300 transition-all" 
                                                        title="ส่งข้อมูลซ้ำ (Resend to PU)">
                                                    <i class="fas fa-paper-plane text-xs"></i>
                                                </button>
                                            </div>
                                            {{-- Hidden Input to preserve state if needed, or just nothing --}}
                                        @elseif($isLinked)
                                            <select id="status-{{ $itemId }}" onchange="handleStatusChange({{ $itemId }})"
                                                    class="w-full text-sm font-semibold border-gray-300 rounded-xl py-2.5 px-3 shadow-md focus:ring-2 focus:ring-indigo-400 transition-all cursor-pointer">
                                                <option value="" disabled selected>-- เลือกผลการตรวจ --</option>
                                                <option value="pass">✅ ครบถ้วนสมบูรณ์ (รับเข้าคลัง)</option>
                                                <option value="issue">⚠️ พบปัญหา (แจ้งส่งคืน)</option>
                                            </select>
                                        @else
                                            <span class="text-red-500 text-sm p-2 bg-red-50 rounded-lg shadow-inner block text-center">🚫 ไม่ผ่านเกณฑ์การรับเข้า</span>
                                        @endif
                                    </div>

                                    <!-- 5. จำนวนรับจริง (Quantity) -->
                                    <div class="px-4 py-2 md:py-4 md:w-32 md:text-center bg-indigo-50/10">
                                        <div class="flex items-center justify-between md:justify-center h-full">
                                            <label class="md:hidden text-xs font-bold text-indigo-500 uppercase mr-4">2. จำนวนรับจริง</label>
                                            <div id="qty-wrapper-{{ $itemId }}" class="flex-1 md:flex-none flex flex-col items-end md:items-center justify-center min-h-[50px] md:min-h-[70px]">
                                                <!-- Dynamic Content -->
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 6. การดำเนินการ (Actions) -->
                                    <div class="px-4 py-3 md:py-4 md:w-48 md:pr-6 bg-indigo-50/10 md:text-right border-t md:border-t-0 border-gray-100">
                                            <div class="flex items-center justify-between md:justify-end h-full">
                                            <label class="md:hidden text-xs font-bold text-indigo-500 uppercase mr-4">3. ยืนยัน</label>
                                            <div id="action-buttons-{{ $itemId }}" class="flex-1 md:flex-none flex items-center justify-end min-h-[44px]">
                                                <!-- Dynamic Buttons -->
                                            </div>
                                            </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Modal ยืนยันส่งคืน -->
        <div id="reject-modal" class="fixed inset-0 bg-gray-900/75 z-50 flex items-center justify-center p-4 hidden">
            <div id="reject-modal-content" class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm border-t-4 border-red-500">
                <h4 class="text-2xl font-bold text-red-700 mb-4 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    ยืนยันการปฏิเสธการรับ
                </h4>
                <p class="text-sm text-gray-600 mb-4">โปรดระบุสาเหตุการปฏิเสธเพื่อแจ้งไปยังฝ่ายจัดซื้อ (PU) **รายการนี้จะไม่ถูกนำเข้าคลังสินค้าจนกว่าจะได้รับคำสั่งยืนยันจาก PU**</p>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-bold text-gray-700 block mb-1">สาเหตุหลัก (บังคับเลือก):</label>
                        <select name="notes_reject_type" id="reject-reason-select" class="w-full border-gray-300 rounded-lg text-sm shadow-inner p-2">
                            <option value="" disabled selected>-- เลือกสาเหตุ --</option>
                            <option value="incomplete">1. 📦 ของไม่ครบตามจำนวนสั่งซื้อ</option>
                            <option value="damaged">2. 🔨 ของเสียหาย/ชำรุด</option>
                            <option value="wrong_item">3. ❌ รหัส/รุ่น/สเปค ไม่ตรงกับคำสั่งซื้อ</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-bold text-gray-700 block mb-1">หมายเหตุเพิ่มเติม (ถ้ามี):</label>
                        <input type="text" name="notes_reject" id="reject-notes-input" class="w-full border-gray-300 rounded-lg text-sm shadow-inner p-2" placeholder="ระบุรายละเอียดเพิ่มเติม...">
                    </div>
                </div>
                <div class="flex gap-3 justify-end mt-6">
                    <button type="button" onclick="hideRejectModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors">ยกเลิก</button>
                    <button type="submit" name="single_submit_reject" id="final-reject-submit" onclick="finalRejectSubmitAction(event)" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg font-bold shadow-md hover:bg-red-700 transition-colors">
                        ยืนยันและส่งคืน 📤
                    </button>
                </div>
                <input type="hidden" id="modal-status-input" name="">
            </div>
        </div>

        <!-- Footer -->
        <div class="fixed bottom-0 right-0 left-0 lg:left-64 border-t border-gray-200 bg-white/95 backdrop-blur-md p-4 z-30 shadow-[0_-4px_10px_rgba(0,0,0,0.05)]">
            <div class="max-w-7xl mx-auto flex justify-end gap-4 px-4">
                <button type="button" onclick="window.history.back()" class="px-6 py-2.5 bg-white border border-gray-300 rounded-xl text-gray-700 font-bold hover:bg-gray-50 transition-colors shadow-sm">
                    ย้อนกลับ
                </button>
                <button type="submit" id="save-all-button" class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg transition-all">
                    บันทึกการดำเนินการทั้งหมด
                </button>
            </div>
        </div>
    </form>
@endif
