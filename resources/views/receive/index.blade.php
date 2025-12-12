@extends('layouts.app')

@section('header', '📥 ตรวจรับอุปกรณ์ (Receive)')
@section('subtitle', "ศูนย์กลางการตรวจสอบและรับสินค้าเข้าคลัง [$currentDeptName]")

@section('content')
    <div class="w-full bg-gray-50 min-h-screen pb-40 lg:pb-32 font-sans">
        
        <!-- Header Wizard -->
        <div class="bg-white border-b border-gray-200 py-4 px-4 sticky top-0 z-20 shadow-sm">
            <div class="max-w-7xl mx-auto flex items-center justify-center gap-4 text-sm">
                <div class="flex items-center gap-2 text-indigo-600 font-bold">
                    <span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-xs">1</span>
                    <span>เลือกผลตรวจ</span>
                </div>
                <div class="w-8 h-px bg-gray-300"></div>
                <div class="flex items-center gap-2 text-gray-500 font-medium">
                    <span class="w-6 h-6 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-xs">2</span>
                    <span>กดยืนยัน (ปุ่มจะแสดงเมื่อเลือกแล้ว)</span>
                </div>
            </div>
        </div>

        <div class="max-w-[98%] mx-auto px-2 sm:px-4 mt-6">
            
            <!-- Alert Messages -->
            @if(session('success'))
                <div class="mb-6 bg-white border-l-4 border-green-500 p-4 shadow rounded-lg flex items-center gap-3 animate-bounce-in">
                    <div class="bg-green-100 p-2 rounded-full text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div><h4 class="font-bold text-green-800">สำเร็จ</h4><p class="text-sm text-green-600">{!! session('success') !!}</p></div>
                </div>
            @endif

            @if($purchaseOrders->isEmpty())
                <div class="bg-white shadow-sm rounded-2xl p-16 text-center border border-gray-200 flex flex-col items-center justify-center min-h-[400px]">
                    <div class="p-6 bg-gray-50 rounded-full mb-6">
                        <svg class="h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2-414-2.414A1 1 0 006.586 13H4" /></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">ไม่พบรายการรอตรวจรับ</h3>
                    <p class="text-gray-500 mt-2">เมื่อ PU แจ้งส่งของ รายการจะปรากฏที่นี่</p>
                </div>
            @else
                <!-- Global Modal and Form Container -->
                <form action="{{ route('receive.process') }}" x-data="{ 
                        sending: false, 
                        currentRejectItemId: null, 
                        showRejectModal: false, 
                        tempReason: '', 
                        
                        // ฟังก์ชันเปิด Modal (กำหนดใน x-data เพื่อให้เข้าถึง Scope ได้แน่นอน)
                        openRejectModal(itemId, issueType) {
                            this.currentRejectItemId = itemId;
                            this.tempReason = issueType; // เก็บค่าจริงที่จะ submit
                            this.showRejectModal = true;
                        },

                        // แปลงค่าสาเหตุที่เลือก (สำหรับแสดงใน Modal)
                        getReasonText(reasonValue) {
                            switch(reasonValue) {
                                case 'incomplete': return 'ของไม่ครบ (Incomplete)';
                                case 'damaged': return 'ของเสียพัง (Damaged)';
                                case 'wrong_item': return 'ผิดรุ่น (Wrong Item)';
                                default: return 'ไม่ระบุ';
                            }
                        }
                    }" 
                    id="receiveForm" method="POST">
                    @csrf
                    
                    <div class="space-y-10">
                        @foreach($purchaseOrders as $po)
                            <!-- PO Card -->
                            <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-200" x-data="{ debug: false }">
                                
                                <!-- PO Header -->
                                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-4">
                                    <div class="flex items-center gap-4">
                                        <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 shadow-sm text-center min-w-[80px]">
                                            <span class="text-[10px] uppercase font-bold text-gray-400 block">PO #</span>
                                            <span class="text-lg font-black text-indigo-600 block leading-none">{{ $po->po_number }}</span>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <div class="flex items-center gap-3 font-medium">
                                                <span class="flex items-center gap-1 bg-gray-200 px-2 py-0.5 rounded text-gray-700 text-xs">
                                                    📅 {{ $po->ordered_at ? $po->ordered_at->format('d/m/Y') : '-' }}
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    👤 {{ $po->orderedBy->name ?? 'ไม่ระบุ' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border 
                                            {{ $po->status == 'shipped_from_supplier' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-yellow-50 text-yellow-600 border-yellow-200' }}">
                                            {{ str_replace('_', ' ', $po->status) }}
                                        </div>
                                        <button type="button" @click="debug = !debug" class="text-gray-300 hover:text-gray-500 text-xs p-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Debug View -->
                                <div x-show="debug" class="bg-gray-900 text-green-400 p-4 text-xs font-mono overflow-x-auto border-b border-gray-700" style="display:none;">
                                    <strong>PO Data:</strong>
                                    <pre>{{ json_encode($po->items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </div>

                                <!-- Items Table -->
                                <div class="overflow-x-auto custom-scrollbar">
                                    <table class="min-w-full divide-y divide-gray-100 table-fixed">
                                        <thead class="bg-white text-gray-500 text-xs uppercase font-bold tracking-wider">
                                            <tr>
                                                <th class="px-4 py-3 w-20 text-center">รูปภาพ</th>
                                                <th class="px-4 py-3 w-1/3 text-left">รายละเอียดสินค้า</th>
                                                
                                                <th class="px-4 py-3 w-24 text-center bg-gray-50 border-r border-gray-100">ยอดสั่งซื้อ</th>
                                                <th class="px-4 py-3 w-24 text-center bg-blue-50/30 text-blue-800 border-r border-gray-100">รอรับเข้า</th>
                                                
                                                <th class="px-4 py-3 w-64 text-left pl-6 bg-indigo-50/10">1. เลือกผลการตรวจรับ</th>
                                                <th class="px-4 py-3 w-32 text-center bg-indigo-50/10">2. จำนวนรับจริง</th>
                                                <th class="px-4 py-3 w-48 text-right bg-indigo-50/10 pr-6">3. ยืนยัน</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-50 text-sm">
                                            @foreach($po->items as $index => $item)
                                                @php
                                                    $remaining = $item->quantity_ordered - ($item->quantity_received ?? 0);
                                                    $equipment = $item->equipment;
                                                    $imgUrl = ($equipment && $equipment->latestImage) ? $equipment->latestImage->image_url : asset('images/placeholder.webp');
                                                    $isLinked = !is_null($equipment);
                                                    $itemName = $item->item_description ?? ($equipment ? $equipment->name : 'ระบุชื่อไม่ได้');
                                                @endphp
                                                
                                                <tr class="transition-colors duration-200 group" 
                                                    x-data="{ 
                                                        status: '', // ค่าเริ่มต้นว่าง ('')
                                                        qty: '', 
                                                        maxQty: {{ $remaining }},
                                                        issueType: '', 
                                                        checked: false, 
                                                        
                                                        updateLogic() {
                                                            this.issueType = ''; 
                                                            if (this.status === 'pass') {
                                                                this.qty = this.maxQty;
                                                            } else if (this.status === 'issue') {
                                                                this.qty = 0;
                                                            } else {
                                                                this.qty = '';
                                                            }
                                                            this.checkActionAvailability();
                                                        },

                                                        checkActionAvailability() {
                                                            if (this.status === 'pass') {
                                                                this.checked = true; // ปุ่มฟ้าโผล่
                                                            } else if (this.status === 'issue' && this.issueType !== '' && this.qty !== '') {
                                                                this.checked = true; // ปุ่มแดงโผล่
                                                            } else {
                                                                this.checked = false; // ปุ่มซ่อน
                                                            }
                                                        }
                                                    }"
                                                    :class="status === 'pass' ? 'bg-blue-50/20' : (status === 'issue' ? 'bg-red-50/20' : 'hover:bg-gray-50')"
                                                >
                                                    <!-- Image -->
                                                    <td class="px-4 py-4 align-top text-center">
                                                        <img src="{{ $imgUrl }}" class="w-12 h-12 rounded-lg object-cover border border-gray-200 mx-auto bg-white">
                                                    </td>

                                                    <!-- Details -->
                                                    <td class="px-4 py-4 align-top">
                                                        <p class="text-sm font-bold text-gray-900 line-clamp-2" title="{{ $itemName }}">{{ $itemName }}</p>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            @if($isLinked) ID: {{ $equipment->id }} @else <span class="text-red-500 font-bold">No ID (รับไม่ได้)</span> @endif
                                                        </p>
                                                        
                                                        <!-- Hidden Inputs -->
                                                        <input type="checkbox" name="items[{{ $item->id }}][selected]" value="1" x-model="checked" class="hidden">
                                                        <input type="hidden" name="items[{{ $item->id }}][ordered_quantity]" value="{{ $item->quantity_ordered }}">
                                                        <input type="hidden" name="items[{{ $item->id }}][already_received]" value="{{ $item->quantity_received ?? 0 }}">
                                                        
                                                        <!-- Map status to backend -->
                                                        <input type="hidden" name="items[{{ $item->id }}][inspection_status]" :value="status === 'pass' ? 'pass' : issueType">
                                                    </td>

                                                    <!-- Ordered Qty -->
                                                    <td class="px-4 py-4 align-top text-center bg-gray-50/50 border-r border-gray-100">
                                                        <span class="text-lg font-bold text-gray-400 block">{{ $item->quantity_ordered }}</span>
                                                        <span class="text-[10px] text-gray-400 uppercase block">{{ $item->unit_name ?? 'Unit' }}</span>
                                                    </td>

                                                    <!-- Remaining Qty -->
                                                    <td class="px-4 py-4 align-top text-center bg-blue-50/30 border-r border-gray-100">
                                                        <span class="text-lg font-black text-blue-600 block">{{ $remaining }}</span>
                                                        <span class="text-[10px] text-blue-400 uppercase block">รอรับ</span>
                                                    </td>

                                                    <!-- 1. Dropdown (Main Selection) -->
                                                    <td class="px-4 py-4 align-top pl-6">
                                                        @if($isLinked)
                                                            <div class="relative">
                                                                <select x-model="status" @change="updateLogic()"
                                                                        class="w-full text-sm font-semibold border-gray-300 rounded-xl py-2.5 pl-3 pr-8 focus:ring-2 shadow-sm cursor-pointer appearance-none transition-all"
                                                                        :class="{
                                                                            'text-gray-400 bg-white border-gray-300': status === '',
                                                                            'bg-blue-50 text-blue-700 border-blue-300 focus:border-blue-500 focus:ring-blue-200': status === 'pass',
                                                                            'bg-red-50 text-red-700 border-red-300 focus:border-red-500 focus:ring-red-200': status === 'issue'
                                                                        }">
                                                                    <option value="" selected disabled>-- เลือกผลตรวจ --</option>
                                                                    <option value="pass">✅ 1. ครบถ้วนสมบูรณ์</option>
                                                                    <option value="issue">⚠️ 2. ของไม่ครบ / เสีย / ผิดรุ่น</option>
                                                                </select>
                                                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Sub-menu for Issue -->
                                                            <div x-show="status === 'issue'" x-transition class="mt-3">
                                                                <label class="text-[10px] text-red-500 font-bold ml-1 mb-1 block">ระบุสาเหตุหลัก:</label>
                                                                <select x-model="issueType" @change="checkActionAvailability()"
                                                                        class="w-full text-xs border-red-300 bg-white rounded-lg px-3 py-2 shadow-inner focus:ring-red-500">
                                                                    <option value="" disabled selected>-- เลือกสาเหตุ --</option>
                                                                    <option value="incomplete">ของไม่ครบ (Incomplete)</option>
                                                                    <option value="damaged">ของเสีย/พัง (Damaged)</option>
                                                                    <option value="wrong_item">ผิดรุ่น (Wrong Item)</option>
                                                                </select>
                                                            </div>
                                                        @else
                                                            <div class="text-xs text-red-400 italic text-center p-2 bg-red-50 rounded border border-red-100">รับไม่ได้ (No ID)</div>
                                                        @endif
                                                    </td>

                                                    <!-- 2. Qty Input -->
                                                    <td class="px-4 py-4 align-top text-center">
                                                        @if($isLinked)
                                                            <div x-show="status !== ''" x-transition>
                                                                <input type="number" name="items[{{ $item->id }}][receive_now_quantity]" 
                                                                       x-model="qty" min="0" :max="maxQty" @input="checkActionAvailability()"
                                                                       class="w-24 text-center font-black text-xl rounded-xl border-2 shadow-inner h-12 transition-colors focus:ring-4"
                                                                       :class="status === 'pass' ? 'border-blue-300 text-blue-600 bg-blue-50 focus:ring-blue-100' : 'border-red-300 text-red-600 bg-white focus:ring-red-100'"
                                                                       :readonly="status === 'pass'">
                                                                
                                                                <div x-show="status === 'pass'" class="text-[10px] text-blue-500 mt-1 font-bold animate-pulse">Auto-Filled</div>
                                                                <div x-show="status === 'issue' && checked" class="text-[10px] text-red-500 mt-1 font-bold animate-pulse">พร้อมส่งคืน</div>
                                                                <div x-show="status === 'issue' && !checked" class="text-[10px] text-gray-500 mt-1 font-bold">กรอกข้อมูลให้ครบ</div>
                                                            </div>
                                                        @endif
                                                    </td>

                                                    <!-- 3. Action Buttons (Dynamic) -->
                                                    <td class="px-4 py-4 align-top text-right pr-6 relative">
                                                        @if($isLinked)
                                                            <!-- ปุ่มรับเข้า (Pass) -->
                                                            <button x-show="checked && status === 'pass'" x-transition
                                                                    type="submit" name="single_submit" value="{{ $item->id }}"
                                                                    class="w-full py-3 bg-blue-100 hover:bg-blue-200 text-blue-600 rounded-xl text-xs font-black shadow-md flex items-center justify-center gap-2 transform active:scale-95 transition-all animate-flash border-2 border-blue-200">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                                    รับเข้าคลัง
                                                            </button>

                                                            <!-- ปุ่มแจ้งปัญหา (Issue) -->
                                                            <button x-show="checked && status === 'issue'" x-transition
                                                                    type="button" @click="openRejectModal({{ $item->id }}, issueType)"
                                                                    class="w-full py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md flex items-center justify-center gap-2 transform active:scale-95 transition-all animate-flash-red ring-4 ring-red-100">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                                แจ้งปัญหา/ส่งคืน
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Global Reject Confirmation Modal -->
                    <div x-show="showRejectModal" x-cloak class="fixed inset-0 bg-gray-900 bg-opacity-75 z-40 flex items-center justify-center p-4" x-transition.opacity>
                        <div @click.away="showRejectModal = false" class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm transform transition-all duration-300">
                            <h4 class="text-xl font-bold text-red-700 mb-2 flex items-center gap-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                ยืนยันการส่งคืนสินค้า
                            </h4>
                            <p class="text-sm text-gray-600 mb-4">
                                ระบบจะส่งข้อมูลปัญหานี้กลับไปยัง PU และรายการนี้ **จะไม่ถูกนำเข้าสต๊อก**
                            </p>
                            
                            <!-- สาเหตุที่ส่งคืน (Final Choice) -->
                            <div class="space-y-2 mb-4 bg-red-50 p-3 rounded-lg border border-red-100">
                                <label class="text-sm font-bold text-red-800 mb-1 block">กรุณายืนยันสาเหตุ:</label>
                                <span class="text-base font-medium text-red-600 mb-2 block" x-text="getReasonText(tempReason)"></span>

                                <label class="text-sm font-bold text-red-800 mb-1 block">หมายเหตุเพิ่มเติม (ถ้ามี):</label>
                                <input type="text" name="notes_reject" placeholder="เช่น กล่องบุบ, สีผิด..." class="w-full text-xs border-red-300 bg-white rounded-lg px-3 py-2 shadow-inner">
                            </div>

                            <div class="flex gap-3 justify-end">
                                <button type="button" @click="showRejectModal = false" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm rounded-lg hover:bg-gray-200 font-medium">ยกเลิก</button>
                                <button type="submit" name="single_submit_reject" :value="currentRejectItemId" 
                                        class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 font-bold shadow-md">
                                    ยืนยันส่งคืน
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- End Global Modal -->


                    <!-- Footer Submit (Save All) -->
                    <div class="fixed bottom-0 right-0 left-0 lg:left-64 border-t border-gray-200 bg-white/95 backdrop-blur-md p-4 z-30 shadow-[0_-4px_20px_rgba(0,0,0,0.1)]">
                        <div class="max-w-7xl mx-auto flex justify-end gap-4 px-4">
                            <button type="button" onclick="history.back()" class="px-6 py-2.5 bg-white border border-gray-300 rounded-xl text-gray-700 font-bold hover:bg-gray-50 transition-colors shadow-sm">
                                ย้อนกลับ
                            </button>
                            <button type="submit" @click="sending = true" class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg flex items-center gap-2 transform active:scale-95 transition-all" :class="{'opacity-75 cursor-not-allowed': sending}">
                                <span x-show="!sending">บันทึกทั้งหมดที่เลือก</span>
                                <span x-show="sending">กำลังบันทึก...</span>
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar { height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        @keyframes bounce-in { 0% { transform: scale(0.95); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        .animate-bounce-in { animation: bounce-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        
        @keyframes flash-blue {
            0%, 100% { background-color: #dbeafe; color: #2563eb; border-color: #bfdbfe; }
            50% { background-color: #ffffff; color: #3b82f6; border-color: #60a5fa; }
        }
        .animate-flash { animation: flash-blue 1.2s infinite; }

        @keyframes flash-red {
            0%, 100% { background-color: #dc2626; color: white; border-color: #dc2626; }
            50% { background-color: #ffffff; color: #dc2626; border-color: #dc2626; }
        }
        .animate-flash-red { animation: flash-red 0.4s infinite; }
    </style>
@endsection