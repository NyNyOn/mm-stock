@forelse($transactions as $txn)
    @php
        // 1. จัดการสีแถว (Row Styling)
        $isCancelled = in_array($txn->status, ['cancelled', 'rejected']);
        $rowClass = $isCancelled ? 'bg-gray-50 opacity-60' : 'hover:bg-gray-50 transition-colors duration-200';
        if (isset($statusFilter) && $statusFilter == 'admin_pending') $rowClass .= ' bg-yellow-50/30';

        // 2. จัดการข้อความวัตถุประสงค์ (Purpose) - แปลไทย
        $purposeText = null;
        if (!empty($txn->purpose)) {
            if ($txn->purpose === 'general_use') {
                $purposeText = 'เบิกใช้งานทั่วไป';
            } elseif ($txn->purpose === 'glpi_ticket' || str_starts_with($txn->purpose, 'glpi-')) {
                $purposeText = $txn->glpi_ticket_id ? 'GLPI #' . $txn->glpi_ticket_id : 'อ้างอิง Ticket';
            } else {
                $purposeText = $txn->purpose;
            }
        }
    @endphp

    <tr class="{{ $rowClass }} border-b border-gray-100 last:border-0 group">
        
        {{-- 1. วันที่ / เวลา --}}
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex flex-col">
                <span class="text-sm font-bold text-gray-700">
                    {{ \Carbon\Carbon::parse($txn->transaction_date)->format('d/m/Y') }}
                </span>
                <span class="text-xs text-gray-400 font-medium flex items-center gap-1 mt-0.5">
                    <i class="far fa-clock text-[10px]"></i>
                    {{ \Carbon\Carbon::parse($txn->transaction_date)->format('H:i') }} น.
                </span>
            </div>
        </td>

        {{-- 2. อุปกรณ์ & วัตถุประสงค์ --}}
        <td class="px-6 py-4">
            <div class="flex items-start space-x-3">
                {{-- รูปภาพ --}}
                <div class="flex-shrink-0 h-10 w-10 group-hover:scale-105 transition-transform duration-200">
                    <img class="h-10 w-10 rounded-lg object-cover border border-gray-200 shadow-sm" 
                         src="{{ $txn->equipment->latestImage ? route('nas.image', ['deptKey' => 'mm', 'filename' => $txn->equipment->latestImage->file_name]) : asset('images/placeholder.webp') }}" 
                         alt=""
                         onerror="this.src='{{ asset('images/placeholder.webp') }}'">
                </div>
                
                {{-- ข้อความรายละเอียด --}}
                <div class="flex flex-col min-w-0">
                    <span class="text-sm font-bold text-gray-800 truncate" title="{{ $txn->equipment->name }}">
                        {{ $txn->equipment->name }}
                    </span>
                    
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        {{-- Serial Number Badge --}}
                        <span class="text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200 font-mono">
                            SN: {{ $txn->equipment->serial_number ?? '-' }}
                        </span>

                        {{-- Purpose Badge (แสดงวัตถุประสงค์อย่างเดียว) --}}
                        @if($purposeText)
                            <span class="text-[10px] text-blue-800 bg-blue-100 px-1.5 py-0.5 rounded border border-blue-200 truncate max-w-[150px]" title="{{ $purposeText }}">
                                <i class="fas fa-tag mr-1 text-[9px]"></i>{{ $purposeText }}
                            </span>
                        @endif
                    </div>
                    
                    {{-- Admin View: Requester Name --}}
                    @if(Auth::user()->can('equipment:manage') || (isset($statusFilter) && $statusFilter == 'all_history'))
                        <div class="text-xs text-gray-500 mt-1 flex items-center">
                            <i class="fas fa-user-circle mr-1 text-gray-400"></i> 
                            <span class="truncate max-w-[150px]">{{ $txn->user->fullname }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </td>

        {{-- 3. ประเภท (Type Badge) --}}
        <td class="px-6 py-4 text-center">
            @php
                $typeMap = [
                    'withdraw' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'label' => 'เบิกของ', 'icon' => 'fa-minus-circle'],
                    'borrow' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'ยืมใช้', 'icon' => 'fa-clock'],
                    'return' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'label' => 'คืนของ', 'icon' => 'fa-undo'],
                    'consumable' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'label' => 'เบิกสิ้นเปลือง', 'icon' => 'fa-box-open'],
                    'returnable' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-800', 'label' => 'ยืมคืน', 'icon' => 'fa-exchange-alt'],
                    'partial_return' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'label' => 'เบิก(คืนได้)', 'icon' => 'fa-puzzle-piece'],
                    'add' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'รับเข้า', 'icon' => 'fa-plus-circle'],
                    'receive' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'รับเข้า', 'icon' => 'fa-plus-circle'],
                    'adjust' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'label' => 'ปรับปรุง', 'icon' => 'fa-sliders-h'],
                ];
                $tc = $typeMap[$txn->type] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => ucfirst($txn->type), 'icon' => 'fa-circle'];

                // ✅ Override for Write-off (Consumed)
                if ($txn->type === 'adjust' && $txn->quantity_change == 0) {
                    $tc = ['bg' => 'bg-gray-50', 'text' => 'text-gray-500', 'label' => 'ใช้หมดแล้ว', 'icon' => 'fa-check-double'];
                }
            @endphp
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $tc['bg'] }} {{ $tc['text'] }}">
                <i class="fas {{ $tc['icon'] }} mr-1.5"></i> {{ $tc['label'] }}
            </span>
        </td>

        {{-- 4. จำนวน --}}
        <td class="px-6 py-4 text-center">
            <div class="flex flex-col items-center justify-center">
                <span class="text-sm font-bold {{ $isCancelled ? 'line-through text-gray-400' : ($txn->quantity_change < 0 ? 'text-red-600' : 'text-green-600') }}">
                    {{ $txn->quantity_change > 0 ? '+' : '' }}{{ $txn->quantity_change }}
                </span>
                <span class="text-[10px] text-gray-400">{{ $txn->equipment->unit->name ?? 'หน่วย' }}</span>
            </div>
        </td>

        {{-- 5. สถานะ (Status) --}}
        <td class="px-6 py-4 text-center">
            @php
                $statusMap = [
                    'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'รออนุมัติ', 'icon' => 'fa-hourglass-start'],
                    'approved' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'label' => 'อนุมัติแล้ว', 'icon' => 'fa-check'],
                    'shipped' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'label' => 'จัดส่งแล้ว', 'icon' => 'fa-truck'],
                    'user_confirm_pending' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'label' => 'รอรับของ', 'icon' => 'fa-box'],
                    'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'สำเร็จ', 'icon' => 'fa-check-circle'],
                    
                    // สองสถานะนี้จะถูกจัดการเป็นพิเศษด้านล่าง (Red Strikethrough)
                    'cancelled' => ['label' => 'ยกเลิก'], 
                    'rejected' => ['label' => 'ปฏิเสธ'],
                    'returned' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'คืนของแล้ว', 'icon' => 'fa-check'], // ✅ Added
                    'borrowed' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'ยืมอยู่', 'icon' => 'fa-clock'], // ✅ Added
                    'return_requested' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'label' => 'แจ้งคืน', 'icon' => 'fa-undo'], // ✅ Fixed translation
                ];
                
                $sc = $statusMap[$txn->status] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => $txn->status, 'icon' => 'fa-circle'];
            @endphp

            @if($isCancelled)
                {{-- 🔥 สถานะยกเลิก: สีแดง + ขีดฆ่า --}}
                <div class="flex items-center justify-center text-red-500 font-bold text-sm opacity-80">
                    <i class="fas fa-times-circle mr-1.5"></i>
                    <span class="line-through decoration-2 decoration-red-300">{{ $sc['label'] }}</span>
                </div>
            @else
                {{-- 🟢 สถานะปกติ: Badge --}}
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $sc['bg'] }} {{ $sc['text'] }}">
                    @if($txn->status == 'pending')
                        <span class="relative flex h-2 w-2 mr-1.5">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-yellow-500"></span>
                        </span>
                    @else
                        <i class="fas {{ $sc['icon'] }} mr-1.5"></i>
                    @endif
                    {{ $sc['label'] }}
                </span>
            @endif
        </td>

        {{-- 6. คะแนน (Rating) --}}
        <td class="px-6 py-4 text-center whitespace-nowrap">
            @if($txn->status === 'completed' && in_array($txn->type, ['consumable', 'returnable', 'partial_return', 'borrow', 'withdraw']))
                @if($txn->rating)
                    @php $score = $txn->rating->rating_score; @endphp
                    <div class="flex flex-col items-center justify-center cursor-help" title="คะแนนเฉลี่ย: {{ is_null($score) ? 'N/A' : number_format($score, 2) }}">
                        @if(is_null($score))
                            {{-- กรณีเลือก "ยังไม่เคยใช้งาน" --}}
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                📦 ยังไม่ใช้
                            </span>
                        @else
                            {{-- กรณีมีคะแนน --}}
                            <div class="flex text-yellow-400 space-x-0.5">
                                @for($i=1; $i<=5; $i++)
                                    @if($score >= $i)
                                        <i class="fas fa-star text-[10px]"></i>
                                    @elseif($score >= $i - 0.5)
                                        <i class="fas fa-star-half-alt text-[10px]"></i>
                                    @else
                                        <i class="far fa-star text-gray-300 text-[10px]"></i>
                                    @endif
                                @endfor
                            </div>
                            <span class="text-[10px] font-bold text-gray-500 mt-0.5">({{ number_format($score, 1) }})</span>
                        @endif
                    </div>
                @else
                    {{-- ยังไม่ได้ประเมิน (โชว์เฉพาะเจ้าของรายการ) --}}
                    @if(Auth::id() === $txn->user_id)
                        <button onclick="openRatingModal([{
                                    id: {{ $txn->id }},
                                    submit_url: '{{ route('transactions.rate', $txn->id) }}',
                                    type: '{{ $txn->type == 'borrow' ? 'borrow' : ($txn->equipment->is_consumable ? 'one_way' : 'return_consumable') }}',
                                    equipment: {
                                        name: '{{ addslashes($txn->equipment->name) }}',
                                        serial_number: '{{ $txn->equipment->serial_number }}',
                                        category_id: {{ $txn->equipment->category_id ?? 'null' }}
                                    },
                                    equipment_image_url: '{{ $txn->equipment->latestImage ? route('nas.image', ['deptKey' => 'mm', 'filename' => $txn->equipment->latestImage->file_name]) : asset('images/placeholder.webp') }}'
                                }])" 
                                class="text-indigo-600 hover:text-indigo-800 text-xs font-bold hover:underline transition-all flex items-center justify-center gap-1 mx-auto">
                            <i class="far fa-edit"></i> ประเมิน
                        </button>
                    @else
                        <span class="text-gray-300 text-xs">-</span>
                    @endif
                @endif
            @else
                <span class="text-gray-300 text-xs">-</span>
            @endif
        </td>

        {{-- 7. รายละเอียด (Detail Button) --}}
        <td class="px-6 py-4 text-center">
            <button onclick="showTransactionDetails({{ $txn->id }})" 
                    class="text-gray-400 hover:text-blue-600 transition-all duration-200 transform hover:scale-110 focus:outline-none p-1 rounded-full hover:bg-blue-50"
                    title="ดูรายละเอียด">
                <i class="fas fa-info-circle text-xl"></i>
            </button>
        </td>

        {{-- 8. จัดการ (Actions) --}}
        <td class="px-6 py-4 text-center">
            <div class="flex items-center justify-center gap-2">
                
                @if($txn->status == 'pending' && Auth::user()->can('equipment:manage'))
                    <div class="flex items-center gap-2">
                        <form action="{{ route('transactions.adminConfirmShipment', $txn->id) }}" method="POST" onsubmit="event.preventDefault(); window.submitConfirmShipment(this);">
                            @csrf 
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-md transition-all flex items-center gap-2 transform hover:scale-105 font-bold text-sm" title="ยืนยันส่งของ">
                                <i class="fas fa-paper-plane"></i> <span>ยืนยันส่งของ</span>
                            </button>
                        </form>
                        <form action="{{ route('transactions.userCancel', $txn->id) }}" method="POST" onsubmit="event.preventDefault(); window.submitAdminReject(this);">
                            @method('PATCH') @csrf 
                            <button type="submit" class="px-4 py-2 bg-white border-2 border-red-100 text-red-600 rounded-lg hover:bg-red-50 hover:border-red-200 transition-all flex items-center gap-2 font-bold text-sm" title="ปฏิเสธ">
                                <i class="fas fa-times"></i> <span>ปฏิเสธ</span>
                            </button>
                        </form>
                    </div>
                @endif

                {{-- USER OR ADMIN: Confirm Receipt --}}
                @if(in_array($txn->status, ['shipped', 'user_confirm_pending']) && (Auth::id() == $txn->user_id || Auth::user()->can('permission:manage')))
                    <form action="{{ route('transactions.userConfirmReceipt', $txn->id) }}" method="POST" onsubmit="event.preventDefault(); window.submitConfirmReceipt(this);">
                        @csrf 
                        <button type="submit" 
                                class="inline-flex items-center px-3 py-1.5 {{ Auth::id() == $txn->user_id ? 'bg-green-500 hover:bg-green-600' : 'bg-purple-500 hover:bg-purple-600' }} text-white text-xs font-bold rounded-md shadow-sm transition-all hover:shadow-md" 
                                title="{{ Auth::id() == $txn->user_id ? 'ได้รับของแล้ว' : 'ยืนยันแทนผู้ใช้' }}">
                            @if(Auth::id() == $txn->user_id)
                                <i class="fas fa-check mr-1.5"></i> รับของ
                            @else
                                <i class="fas fa-user-check mr-1.5"></i> รับแทน
                            @endif
                        </button>
                    </form>
                @endif

                {{-- User Cancel --}}
                @if($txn->status == 'pending' && Auth::id() == $txn->user_id)
                    <form action="{{ route('transactions.userCancel', $txn->id) }}" method="POST" onsubmit="event.preventDefault(); window.submitUserCancel(this);">
                        @method('PATCH') @csrf 
                        <button class="text-xs font-medium text-red-500 hover:text-red-700 underline decoration-red-200 underline-offset-2 hover:decoration-red-500 transition-all">
                            ยกเลิก
                        </button>
                    </form>
                @endif

                {{-- Admin Reversal --}}
                @can('equipment:manage')
                    @if($txn->status == 'completed' && isset($txn->confirmed_at) && \Carbon\Carbon::parse($txn->confirmed_at)->diffInHours(now()) < 24 && $txn->quantity_change < 0)
                        <form action="{{ route('transactions.adminCancel', $txn->id) }}" method="POST" onsubmit="event.preventDefault(); window.submitAdminCancel(this);">
                            @method('PATCH') @csrf
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 flex items-center gap-1 px-2 py-1 rounded hover:bg-red-50 transition-colors" title="Reversal (ภายใน 24 ชม.)">
                                <i class="fas fa-history"></i> ยกเลิก
                            </button>
                        </form>
                    @endif
                @endcan


            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="px-6 py-16 text-center bg-white">
            <div class="flex flex-col items-center justify-center text-gray-400">
                <div class="bg-gray-50 p-4 rounded-full mb-3">
                    <i class="fas fa-inbox text-3xl text-gray-300"></i>
                </div>
                <p class="text-sm font-medium text-gray-500">ไม่พบข้อมูลรายการ</p>
                <p class="text-xs text-gray-400 mt-1">ลองเปลี่ยนตัวกรองหรือค้นหาใหม่อีกครั้ง</p>
            </div>
        </td>
    </tr>
@endforelse