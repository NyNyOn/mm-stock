@forelse($transactions as $txn)
    @php
        // Row Styling Logic (copied from table rows)
        $isCancelled = in_array($txn->status, ['cancelled', 'rejected']);
        $cardClass = $isCancelled ? 'bg-gray-50 opacity-75' : 'bg-white';
        if (isset($statusFilter) && $statusFilter == 'admin_pending') $cardClass .= ' ring-2 ring-yellow-400/30';

        // Purpose Logic
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

        // Type Logic
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

        // Status Logic
        $statusMap = [
            'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'รออนุมัติ', 'icon' => 'fa-hourglass-start'],
            'approved' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'label' => 'อนุมัติแล้ว', 'icon' => 'fa-check'],
            'shipped' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'label' => 'จัดส่งแล้ว', 'icon' => 'fa-truck'],
            'user_confirm_pending' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'label' => 'รอรับของ', 'icon' => 'fa-box'],
            'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'สำเร็จ', 'icon' => 'fa-check-circle'],
            'cancelled' => ['label' => 'ยกเลิก'], 
            'rejected' => ['label' => 'ปฏิเสธ'],
            'returned' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'คืนของแล้ว', 'icon' => 'fa-check'], // ✅ Added
            'borrowed' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'ยืมอยู่', 'icon' => 'fa-clock'], // ✅ Added
        ];
        $sc = $statusMap[$txn->status] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => $txn->status, 'icon' => 'fa-circle'];
    @endphp

    <div class="{{ $cardClass }} rounded-xl shadow-sm border border-gray-200 overflow-hidden relative">
        
        {{-- Header: Date & Status --}}
        <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div class="flex flex-col">
                <span class="text-xs font-bold text-gray-500">
                    {{ \Carbon\Carbon::parse($txn->transaction_date)->format('d/m/Y') }}
                </span>
                <span class="text-[10px] text-gray-400">
                    {{ \Carbon\Carbon::parse($txn->transaction_date)->format('H:i') }} น.
                </span>
            </div>
            
            <div>
                @if($isCancelled)
                    <span class="text-xs font-bold text-red-500 line-through decoration-red-300 flex items-center gap-1">
                        <i class="fas fa-times-circle"></i> {{ $sc['label'] }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $sc['bg'] }} {{ $sc['text'] }}">
                        @if($txn->status == 'pending')
                            <span class="animate-pulse mr-1">●</span>
                        @else
                            <i class="fas {{ $sc['icon'] }} mr-1"></i>
                        @endif
                        {{ $sc['label'] }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Body: Item Details --}}
        <div class="p-4 flex gap-4">
            {{-- Image --}}
            <div class="flex-shrink-0">
                <img class="h-16 w-16 rounded-lg object-cover border border-gray-200 bg-gray-100" 
                     src="{{ $txn->equipment->latestImage ? route('nas.image', ['deptKey' => 'mm', 'filename' => $txn->equipment->latestImage->file_name]) : asset('images/placeholder.webp') }}" 
                     onerror="this.src='{{ asset('images/placeholder.webp') }}'">
            </div>

            {{-- Info --}}
            <div class="flex-1 min-w-0 space-y-2">
                <div>
                    <h4 class="text-sm font-bold text-gray-900 line-clamp-2 leading-tight">
                        {{ $txn->equipment->name }}
                    </h4>
                    <p class="text-xs text-gray-500 font-mono mt-0.5">SN: {{ $txn->equipment->serial_number ?? '-' }}</p>
                </div>
                
                {{-- Badges Row --}}
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $tc['bg'] }} {{ $tc['text'] }}">
                        {{ $tc['label'] }}
                    </span>
                    
                    @if($purposeText)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100 max-w-[100px] truncate">
                            <i class="fas fa-tag mr-1 text-[9px]"></i> {{ $purposeText }}
                        </span>
                    @endif
                </div>

                <div class="flex justify-between items-end mt-2">
                     {{-- Quantity --}}
                     <div class="text-sm font-bold {{ $isCancelled ? 'line-through text-gray-400' : ($txn->quantity_change < 0 ? 'text-red-600' : 'text-green-600') }}">
                        {{ $txn->quantity_change > 0 ? '+' : '' }}{{ $txn->quantity_change }} {{ $txn->equipment->unit->name ?? 'หน่วย' }}
                    </div>

                    {{-- Admin logic: Show User --}}
                    @if(Auth::user()->can('equipment:manage') || (isset($statusFilter) && $statusFilter == 'all_history'))
                        <div class="text-xs text-gray-400 flex items-center">
                            <i class="fas fa-user-circle mr-1"></i> {{ \Illuminate\Support\Str::limit($txn->user->fullname, 15) }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Footer: Actions --}}
        <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex justify-between items-center gap-2">
            
            {{-- Detail Button (Left) --}}
            <button onclick="showTransactionDetails({{ $txn->id }})" class="text-gray-500 hover:text-indigo-600 text-xs font-bold flex items-center gap-1 bg-white border border-gray-200 rounded px-2 py-1 shadow-sm">
                <i class="fas fa-info-circle"></i> รายละเอียด
            </button>

            {{-- Action Buttons (Right) --}}
            <div class="flex items-center gap-2">
                {{-- 1. Rating --}}
                 @if($txn->status === 'completed' && in_array($txn->type, ['consumable', 'returnable', 'partial_return', 'borrow', 'withdraw']))
                    @if($txn->rating)
                         @if(is_null($txn->rating->rating_score))
                             {{-- Not Used / N/A --}}
                             <div class="flex items-center space-x-1 text-xs" title="ประเมินแล้ว: ยังไม่เคยใช้งาน">
                                <span class="text-gray-400 font-bold bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200">📦 ยังไม่ใช้</span>
                             </div>
                         @else
                             {{-- Rated --}}
                             <div class="flex text-yellow-400 space-x-0.5 text-xs" title="ให้คะแนนแล้ว">
                                <i class="fas fa-star"></i>
                                <span class="text-gray-500 font-bold ml-1">{{ number_format($txn->rating->rating_score, 1) }}</span>
                             </div>
                         @endif
                    @elseif(Auth::id() === $txn->user_id)
                        <button onclick="openRatingModal('{{ route('transactions.rate', $txn->id) }}', '{{ $txn->type == 'borrow' ? 'borrow' : ($txn->equipment->is_consumable ? 'one_way' : 'return_consumable') }}')" 
                                class="text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-3 py-1 rounded text-xs font-bold transition-colors">
                            <i class="far fa-edit mr-1"></i> ประเมิน
                        </button>
                    @endif
                 @endif

                {{-- 2. Confirm Receipt --}}
                @if(in_array($txn->status, ['shipped', 'user_confirm_pending']) && (Auth::id() == $txn->user_id || Auth::user()->can('permission:manage')))
                    <form action="{{ route('transactions.userConfirmReceipt', $txn->id) }}" method="POST" onsubmit="event.preventDefault(); window.submitConfirmReceipt(this);">
                        @csrf 
                        <button type="submit" class="px-3 py-1 bg-green-600 text-white text-xs font-bold rounded shadow-sm hover:bg-green-700 transition">
                            รับของ
                        </button>
                    </form>
                @endif
                
                {{-- 3. Cancel (User) --}}
                @if($txn->status == 'pending' && Auth::id() == $txn->user_id)
                    <form action="{{ route('transactions.userCancel', $txn->id) }}" method="POST" onsubmit="event.preventDefault(); window.submitUserCancel(this);">
                        @method('PATCH') @csrf 
                        <button class="px-3 py-1 bg-white border border-red-200 text-red-500 text-xs font-bold rounded hover:bg-red-50 transition">
                            ยกเลิก
                        </button>
                    </form>
                @endif

                 {{-- 4. Admin Actions --}}
                 @if($txn->status == 'pending' && Auth::user()->can('equipment:manage'))
                    <form action="{{ route('transactions.adminConfirmShipment', $txn->id) }}" method="POST" onsubmit="event.preventDefault(); window.submitConfirmShipment(this);">
                        @csrf 
                        <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 shadow-md transition-all flex items-center gap-1 font-bold">
                            <i class="fas fa-paper-plane text-[10px]"></i> <span>ส่งของ</span>
                        </button>
                    </form>
                    <form action="{{ route('transactions.userCancel', $txn->id) }}" method="POST" onsubmit="event.preventDefault(); window.submitAdminReject(this);">
                        @method('PATCH') @csrf 
                        <button type="submit" class="px-3 py-1 bg-white border border-red-200 text-red-600 rounded hover:bg-red-50 hover:border-red-300 transition-all flex items-center gap-1 font-bold text-xs">
                            <i class="fas fa-times text-[10px]"></i> <span>ปฏิเสธ</span>
                        </button>
                    </form>
                 @endif

            </div>
        </div>

    </div>
@empty
    <div class="text-center py-12 bg-white rounded-xl border border-dashed border-gray-300">
        <div class="text-gray-400 mb-2">
            <i class="fas fa-inbox text-4xl opacity-50"></i>
        </div>
        <p class="text-gray-500 font-medium">ไม่พบรายการ</p>
    </div>
@endforelse
