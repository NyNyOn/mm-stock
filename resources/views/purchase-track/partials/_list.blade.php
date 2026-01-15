@foreach($purchaseOrders as $po)
    <!-- Card รายการสั่งซื้อ -->
    <div class="bg-white overflow-hidden shadow-md sm:rounded-xl border border-gray-200">
        
        <!-- Header -->
        @php
            $hasReturnedItems = $po->items->contains('status', 'returned');
        @endphp
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-4 w-full">
                <div class="bg-white border border-gray-200 p-2.5 rounded-lg shadow-sm flex-shrink-0">
                    <span class="text-xs font-bold text-gray-500 block text-center leading-none">
                        {{ !empty($po->po_number) ? 'PO' : (!empty($po->pr_number) ? 'PR' : 'ID') }}
                    </span>
                    <span class="text-sm font-bold text-indigo-600 block text-center leading-none mt-0.5">#{{ $po->id }}</span>
                </div>
                <div class="flex-grow">
                        <div class="flex items-center flex-wrap gap-2">
                            {{-- 1. Show PR Number (if available) --}}
                            @if(!empty($po->pr_number))
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] uppercase font-bold text-blue-600 bg-blue-100 px-1.5 py-0.5 rounded border border-blue-200 min-w-[24px] text-center">PR</span>
                                    <h3 class="text-lg font-bold text-blue-700 tracking-tight leading-none">{{ $po->pr_number }}</h3>
                                </div>
                            @endif

                            {{-- Separator if both exist --}}
                            @if(!empty($po->pr_number) && !empty($po->po_number))
                                <span class="text-gray-400 font-medium text-lg">-</span>
                            @endif

                            {{-- 2. Show PO Number (if available) - HIDDEN if Returned --}}
                            @if(!empty($po->po_number) && !$hasReturnedItems)
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] uppercase font-bold text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded border border-gray-300 min-w-[24px] text-center">PO</span>
                                    <h3 class="text-lg font-bold text-gray-900 tracking-tight leading-none">{{ $po->po_number }}</h3>
                                </div>
                            @endif
                        
                            @if(empty($po->po_number) && empty($po->pr_number))
                                    <h3 class="text-lg font-bold text-gray-400 tracking-tight italic">รอเลขจาก PU...</h3>
                            @endif

                            {{-- ✅ Show "Waiting for New PO" if items are returned --}}
                            @if($hasReturnedItems)
                                <div class="flex items-center gap-1.5 animate-pulse ml-2">
                                    <span class="text-[10px] uppercase font-bold text-orange-600 bg-orange-100 px-2 py-0.5 rounded-full border border-orange-200">
                                        <i class="fas fa-sync-alt mr-1"></i> รอ PO ใหม่
                                    </span>
                                </div>
                            @endif

                            {{-- ✅ Move Badge Here --}}
                            @php
                                $badgeClasses = match($po->type) {
                                    'urgent' => 'bg-red-100 text-red-600 border border-red-200 animate-pulse',
                                    'scheduled' => 'bg-sky-100 text-sky-600 border border-sky-200 animate-pulse',
                                    'job_order', 'job_order_glpi' => 'bg-green-100 text-green-600 border border-green-200',
                                    default => 'bg-gray-100 text-gray-600 border border-gray-200'
                                };
                                
                                // Override Logic for Rejection/Cancellation
                                if ($po->status === 'cancelled') {
                                    $badgeClasses = 'bg-gray-100 text-gray-500 border border-gray-300'; // Make Type gray if rejected
                                }
                            @endphp
                            <span class="px-2 py-0.5 rounded text-xs font-bold self-center ml-2 {{ $badgeClasses }}">
                                {{ match($po->type) { 
                                    'urgent' => 'ด่วน', 
                                    'scheduled' => 'ตามรอบ', 
                                    'job_order' => 'สั่งงาน', 
                                    'job_order_glpi' => 'สั่งงาน (GLPI)', 
                                    default => ucfirst($po->type) 
                                } }}
                            </span>
                            
                            {{-- 🔴 REJECTED STATUS BADGE --}}
                            @if($po->status === 'cancelled')
                                <span class="px-3 py-1 ml-2 rounded text-xs font-bold bg-red-100 text-red-600 border border-red-200 flex items-center shadow-sm">
                                    <i class="fas fa-ban mr-1.5"></i> ถูกปฏิเสธ (Rejected)
                                </span>
                            @endif
                        </div>
                    <div class="text-sm text-gray-500 mt-0.5 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $po->ordered_at ? \Carbon\Carbon::parse($po->ordered_at)->format('d/m/Y H:i') : 'เพิ่งสั่งซื้อ' }}
                        <span class="text-gray-300">|</span>
                        
                        {{-- ... (Requester Name Logic matches original) ... --}}
                        {{-- (Omitting for brevity, assume content below remains but just showing where badge inserted) --}}
                        {{-- Actually, I need to include the displayName logic for context matching if ReplaceFileContent is strict --}}
                         <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        
                        @php
                            $displayName = 'N/A';
                            $targetUser = null;
                            if ($po->type === 'urgent') {
                                if ($po->requester) $targetUser = $po->requester;
                                elseif ($po->orderedBy) $targetUser = $po->orderedBy;
                                elseif ($po->ordered_by_user_id || $po->requester_id) { $targetUser = \App\Models\User::find($po->ordered_by_user_id ?? $po->requester_id); }
                            } else {
                                $settingKey = match($po->type) { 'scheduled' => 'automation_requester_id', 'job_order', 'job_order_glpi' => 'automation_job_requester_id', default => null };
                                if ($settingKey) $settingUserId = \App\Models\Setting::where('key', $settingKey)->value('value');
                                if (isset($settingUserId)) $targetUser = \App\Models\User::find($settingUserId);
                            }
                            if ($targetUser) {
                                $displayName = $targetUser->name;
                                try { 
                                     $ldapUser = \Illuminate\Support\Facades\DB::table('depart_it_db.sync_ldap')->where('username', $displayName)->first();
                                     if ($ldapUser && !empty($ldapUser->fullname)) $displayName = $ldapUser->fullname; 
                                } catch (\Exception $e) {}
                            }
                        @endphp
                        
                        <span class="font-medium text-gray-700">{{ $displayName }}</span>
                    </div>
                </div>
                
                {{-- Action Buttons --}}
                <div class="ml-auto flex flex-col items-end gap-2">
                     {{-- 1. Receive Button --}}
                    @if(in_array($po->status, ['shipped_from_supplier', 'partial_receive', 'ordered']))
                        <a href="{{ route('receive.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            ไปตรวจรับของ
                        </a>
                    @endif

                    {{-- 2. Resubmit Button (For Rejected Only) --}}
                    {{-- 2. Resubmit Logic (Advanced 4 Cases) --}}
                     @if($po->status === 'cancelled')
                        @php
                            $rejectionCode = $po->pu_data['rejection_code'] ?? 0;
                            // Keywords fallback if code missing (Backward Compat)
                            if ($rejectionCode === 0 && isset($po->pu_data['rejection_reason'])) {
                                $r = $po->pu_data['rejection_reason'];
                                if (str_contains($r, 'ไม่ชัดเจน')) $rejectionCode = 3;
                                elseif (str_contains($r, 'ไม่จำเป็น') || str_contains($r, 'งบประมาณ') || str_contains($r, 'ทดแทน')) $rejectionCode = 1; // Block
                            }
                        @endphp

                        @if($rejectionCode == 3)
                            {{-- Case 3: Allow Resubmit with Explanation --}}
                            <form id="resubmit-form-{{$po->id}}" action="{{ route('purchase-orders.resubmit', $po->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="resubmit_note" id="resubmit-note-{{$po->id}}">
                                <button type="button" onclick="triggerResubmit('{{$po->id}}')" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-indigo-600 text-indigo-700 hover:bg-indigo-50 text-sm font-bold rounded-lg shadow-sm transition-all whitespace-nowrap">
                                    <i class="fas fa-edit"></i> แก้ไขและส่งใหม่
                                </button>
                            </form>
                        @elseif($rejectionCode == 0)
                            {{-- Case 0: Unknown - Allow but warn (Safe fallback) --}}
                             <form id="resubmit-form-{{$po->id}}" action="{{ route('purchase-orders.resubmit', $po->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="resubmit_note" id="resubmit-note-{{$po->id}}">
                                <button type="button" onclick="triggerResubmit('{{$po->id}}')" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-400 text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-lg shadow-sm whitespace-nowrap">
                                    <i class="fas fa-edit"></i> แก้ไขและส่งใหม่
                                </button>
                            </form>
                        @else
                            {{-- Case 1, 2, 4: Block Resubmit --}}
                            <div class="flex flex-col items-end">
                                <a href="{{ route('user.equipment.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors whitespace-nowrap opacity-75">
                                    <i class="fas fa-shopping-cart"></i> เปิดขอซื้อใหม่
                                </a>
                                <span class="text-[10px] text-red-500 mt-1">*กรณีนี้ต้องสร้างใหม่เท่านั้น</span>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
        
        {{-- 🔥 REJECTION REASON BOX --}}
        @if($po->status === 'cancelled' && ($po->pu_data['rejection_reason'] ?? false))
            <div class="bg-red-50 border-t border-red-100 px-6 py-4 flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-red-500 mt-0.5 text-lg"></i>
                <div>
                    <h4 class="text-sm font-bold text-red-800">เหตุผลที่ถูกปฏิเสธ:</h4>
                    <p class="text-sm text-red-700 mt-1 leading-relaxed">
                        {{ $po->pu_data['rejection_reason'] }}
                        @if($po->pu_data['rejected_by'] ?? false)
                            <span class="text-red-500 text-xs pl-1">(โดย {{ $po->pu_data['rejected_by'] }})</span>
                        @endif
                    </p>
                </div>
            </div>
        @endif

        <div class="p-6">
            <!-- Timeline / Progress Bar -->
            <div class="mb-10 mt-2 px-4">
                @php
                    $items = $po->items;
                    // Check for issues or returns
                    $hasIssues = $po->items->contains(function($item) {
                        return $item->status == 'returned' || in_array($item->inspection_status, ['damaged', 'wrong_item', 'quality_issue']);
                    });

                    $steps = [
                        'ordered' => [
                            'label' => !empty($po->po_number) ? 'ออก PO แล้ว' : (!empty($po->pr_number) ? 'ออก PR แล้ว' : 'สั่งซื้อแล้ว'), 
                            'desc' => !empty($po->po_number) ? 'ได้รับเลข PO เรียบร้อย' : (!empty($po->pr_number) ? 'รอออกเลข PO' : 'ส่งคำขอไปที่ PU'), 
                            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'
                        ],
                        'shipped_from_supplier' => ['label' => 'อยู่ระหว่างจัดส่ง', 'desc' => 'PU แจ้งส่งของ', 'icon' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0'],
                    ];

                    // ✅ Inject Step 3: Problem/Return (if applicable)
                    if ($hasIssues) {
                        $steps['issue'] = [
                            'label' => 'พบปัญหา/รอแก้ไข', 
                            'desc' => 'รอ PO ใหม่ หรือ เคลมสินค้า', 
                            'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
                        ];
                    }

                    $steps['completed'] = ['label' => 'รับของสำเร็จ', 'desc' => 'เข้าสต๊อกเรียบร้อย', 'icon' => 'M5 13l4 4L19 7'];

                    $currentStatus = $po->status;
                    if ($currentStatus == 'partial_receive') $currentStatus = 'shipped_from_supplier'; 
                    if ($currentStatus == 'approved') $currentStatus = 'ordered';
                    if ($currentStatus == 'pending') $currentStatus = 'ordered'; 

                    $statusKeys = array_keys($steps);
                    $currentIndex = array_search($currentStatus, $statusKeys);
                    
                    // Logic to set active index for Issue step
                    if ($hasIssues && $currentIndex === false) {
                         if ($currentStatus != 'completed') {
                             $currentIndex = array_search('issue', $statusKeys);
                         }
                    }

                    // ✅ Force Issue Step if has issues (Override standard status logic except completed)
                    if ($hasIssues && $currentStatus != 'completed') {
                        $currentIndex = array_search('issue', $statusKeys);
                    }

                    if ($currentIndex === false && $po->status == 'ordered') $currentIndex = 0;
                    if ($po->status == 'completed') $currentIndex = count($steps) - 1;
                @endphp

                <div class="relative">
                    <div class="absolute top-5 left-0 w-full h-1 bg-gray-200 -translate-y-1/2 rounded-full z-0"></div>
                    
                    {{-- ✅ Progress Bar fixed to start from center of first node to center of current node --}}
                    <div class="absolute top-5 h-1 bg-green-500 -translate-y-1/2 rounded-full z-0 transition-all duration-1000 ease-out" 
                         style="left: {{ 50 / count($steps) }}%; width: {{ $currentIndex * (100 / count($steps)) }}%;"></div>

                    <div class="relative z-10 flex justify-between w-full">
                        @foreach($steps as $key => $step)
                            @php 
                                $stepIndex = array_search($key, $statusKeys);
                                $isActive = $stepIndex <= $currentIndex;
                                $isCurrent = $stepIndex === $currentIndex;
                            @endphp
                            <div class="flex flex-col items-center group" style="width: {{ 100 / count($steps) }}%;">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center border-4 transition-all duration-300 z-10 bg-white
                                    {{ $isActive ? 'border-green-500 text-green-600 shadow-md' : 'border-gray-300 text-gray-300' }}
                                    {{ $isCurrent ? 'ring-4 ring-green-100 scale-110' : '' }}">
                                    
                                    {{-- Animating Icon if Current --}}
                                    <svg class="w-5 h-5 {{ $isCurrent ? 'animate-bounce' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step['icon'] }}"/></svg>
                                </div>
                                <div class="mt-3 text-center">
                                    <div class="text-sm font-bold {{ $isActive ? 'text-gray-900' : 'text-gray-400' }}">{{ $step['label'] }}</div>
                                    <div class="text-xs text-gray-500 hidden sm:block mt-0.5">{{ $step['desc'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- รายการสินค้า -->
            <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                <h4 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2 uppercase tracking-wide">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    รายการสินค้า ({{ $po->items->count() }})
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    @foreach($po->items as $item)
                        @php
                            $equip = $item->equipment;
                            $img = ($equip && $equip->images->isNotEmpty()) ? $equip->images->first()->image_url : asset('images/placeholder.webp');
                            $itemName = $item->item_description ?? ($equip ? $equip->name : 'สินค้าไม่ระบุชื่อ');
                        @endphp
                        <div class="flex items-start gap-4 bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow h-full">
                            <div class="h-16 w-16 rounded-lg bg-gray-100 border border-gray-200 flex-shrink-0 overflow-hidden">
                                <img src="{{ $img }}" class="h-full w-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-900 line-clamp-2 leading-snug" title="{{ $itemName }}">
                                    {{ $itemName }}
                                </p>
                                <div class="mt-2 flex items-center gap-3 text-xs">
                                    <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-medium whitespace-nowrap">
                                        สั่ง {{ $item->quantity_ordered }}
                                    </span>
                                    @if($item->quantity_received > 0)
                                        <span class="text-green-600 flex items-center gap-1 font-medium whitespace-nowrap">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            รับแล้ว {{ $item->quantity_received }}
                                        </span>
                                    @endif

                                    @if($item->status == 'returned')
                                        <span class="text-orange-600 flex items-center gap-1 font-medium whitespace-nowrap" title="{{ $item->inspection_notes }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                            ส่งคืนแล้ว (รอ PO ใหม่)
                                        </span>
                                    @elseif(in_array($item->inspection_status, ['damaged', 'wrong_item', 'quality_issue']))
                                        <span class="text-red-600 flex items-center gap-1 font-medium whitespace-nowrap" title="{{ $item->inspection_notes }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            ปัญหา: {{ match($item->inspection_status) { 'damaged' => 'ของเสียหาย', 'wrong_item' => 'ผิดรุ่น', 'quality_issue' => 'ไม่ได้คุณภาพ', default => 'ปฏิเสธรับ' } }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
@endforeach

<!-- Pagination -->
<div class="mt-4">
    {{ $purchaseOrders->links() }}
</div>

<script>
    if (typeof triggerResubmit !== 'function') {
        window.triggerResubmit = function(id) {
            const note = prompt("กรุณาระบุข้อความถึง PU (อธิบายเหตุผลการแก้ไข):");
            if (note !== null) {
                document.getElementById('resubmit-note-'+id).value = note;
                document.getElementById('resubmit-form-'+id).submit();
            }
        }
    }
</script>
