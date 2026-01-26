@foreach($purchaseOrders as $po)
    @php
        $isCompleted = $po->status === 'completed';
        // ✅ Also Compact for Rejected/Inspection Failed items (Implicit & Explicit)
        $implicitRejection = ($po->items->count() > 0 && $po->items->whereIn('status', ['cancelled', 'rejected'])->count() == $po->items->count());
        $isRejectedContext = in_array($po->status, ['cancelled', 'inspection_failed', 'rejected']) || $implicitRejection;
        
        $isCompact = $isCompleted || $isRejectedContext;
        
        $statusTheme = $isRejectedContext ? 'red' : 'green';
    @endphp

    @if($isCompact)
        {{-- Collapsed Summary for Completed/Rejected Orders --}}
        <div id="summary-{{$po->id}}" onclick="togglePo('{{$po->id}}')" class="bg-gradient-to-r from-{{$statusTheme}}-50 to-white overflow-hidden shadow-sm sm:rounded-xl border border-{{$statusTheme}}-200 cursor-pointer hover:shadow-md transition-all group p-4 flex items-center justify-between relative mb-4">
            <div class="flex items-center gap-4">
                 <div class="w-10 h-10 rounded-full bg-{{$statusTheme}}-100 flex items-center justify-center text-{{$statusTheme}}-600 shadow-sm group-hover:scale-110 transition-transform">
                     @if($isRejectedContext)
                        <i class="fas fa-exclamation-triangle"></i>
                     @else
                        <i class="fas fa-check"></i>
                     @endif
                 </div>
                 <div>
                    <div class="flex items-center gap-2">
                         <span class="font-bold text-gray-800">
                             {{ !empty($po->po_number) ? 'PO: '.$po->po_number : '' }}  
                             {{ !empty($po->po_number) && !empty($po->pr_number) ? ' | ' : '' }}
                             {{ !empty($po->pr_number) ? 'PR: '.$po->pr_number : 'PO #'.$po->id }}
                         </span>
                         @if($isRejectedContext)
                            <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full border border-red-200 shadow-sm">ถูกปฏิเสธ/พบปัญหา</span>
                         @else
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full border border-green-200 shadow-sm animate-pulse">สำเร็จ</span>
                         @endif
                    </div>
                     <p class="text-xs text-gray-500 mt-0.5"><i class="far fa-clock mr-1"></i> {{ $po->updated_at->format('d/m/Y H:i') }} (ล่าสุด)</p>
                 </div>
            </div>
            <div class="flex items-center gap-3">
                 <div class="text-right hidden sm:block">
                     <p class="text-sm font-bold text-gray-700">{{ $po->items->first()->item_name }} {{ $po->items->count() > 1 ? '... (+'.($po->items->count()-1).')' : '' }}</p>
                     @if($isRejectedContext) 
                        <p class="text-xs text-red-500">{{ $po->items->whereIn('status', ['cancelled', 'rejected', 'inspection_failed', 'returned'])->count() }} รายการมีปัญหา</p>
                     @else
                        <p class="text-xs text-gray-500">{{ $po->requester->department->name ?? '-' }}</p>
                     @endif
                 </div>
                 <button class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-400 group-hover:text-indigo-600 shadow-sm">
                     <i class="fas fa-chevron-down"></i>
                 </button>
            </div>
        </div>
    @endif

    <!-- Card รายการสั่งซื้อ -->
    <div id="card-{{$po->id}}" class="bg-white overflow-hidden shadow-md sm:rounded-xl border border-gray-200 {{ $isCompact ? 'hidden' : '' }} mb-4">
         @if($isCompact)
             <div class="bg-{{$statusTheme}}-50 px-4 py-1 border-b border-{{$statusTheme}}-100 flex justify-center cursor-pointer hover:bg-{{$statusTheme}}-100 transition-colors" onclick="togglePo('{{$po->id}}')" title="คลิกเพื่อย่อเก็บ">
                  <span class="text-xs font-bold text-{{$statusTheme}}-700 flex items-center gap-1">
                      <i class="fas fa-chevron-up"></i> ย่อรายละเอียด (Collapse)
                  </span>
             </div>
         @endif
        
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

                            {{-- 2. Show PO Number (if available) - ALWAYS SHOW --}}
                            @if(!empty($po->po_number))
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
                                
                                // Override Logic for Rejection/Cancellation Removed per user request
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

                            @php
                                // Helper for Mapping Active Statuses
                                $statusConfig = match($po->status) {
                                    'ordered' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'label' => 'รอ PU ดำเนินการ'],
                                    'approved' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'label' => 'PU อนุมัติแล้ว (รอของ)'],
                                    'shipped_from_supplier' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'label' => 'อยู่ระหว่างจัดส่ง'],
                                    'partial_receive' => ['bg' => 'bg-orange-50', 'text' => 'text-orange-700', 'border' => 'border-orange-200', 'label' => 'รับของบางส่วน'],
                                    'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'border' => 'border-green-300', 'label' => 'สำเร็จ'],
                                    'pending' => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'border' => 'border-yellow-200', 'label' => 'กำลังดำเนินการ (Pending)'], 
                                    default => ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'border' => 'border-gray-200', 'label' => ucfirst($po->status)]
                                };
                                
                                $isResubmit = $po->pu_data['is_resubmit'] ?? false;
                                $hasRejectedItems = $po->items->contains('status', 'cancelled');
                            @endphp

                            {{-- Status Badge (For Non-Cancelled) --}}
                            @if($po->status !== 'cancelled')
                                <span class="px-3 py-1 ml-2 rounded text-xs font-bold {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} {{ $statusConfig['border'] }} {{ $po->status === 'completed' ? 'animate-pulse ring-2 ring-green-200' : '' }} border flex items-center shadow-sm whitespace-nowrap">
                                    <div class="w-1.5 h-1.5 rounded-full {{ str_replace('text-', 'bg-', $statusConfig['text']) }} mr-2"></div>
                                    {{ $statusConfig['label'] }}
                                </span>
                                
                                {{-- Partial Rejection Indicator --}}
                                @if($hasRejectedItems)
                                    <span class="px-3 py-1 ml-2 rounded text-xs font-bold bg-red-50 text-red-600 border border-red-200 flex items-center shadow-sm whitespace-nowrap" title="มีบางรายการถูกปฏิเสธ">
                                        <i class="fas fa-exclamation-circle mr-1.5"></i> บางรายการถูกปฏิเสธ
                                    </span>
                                @endif

                                {{-- ✈️ Resend Button Removed (Issue Resolved) --}}
                            @else
                                {{-- Cancelled Badge --}}
                                <span class="px-3 py-1 ml-2 rounded text-xs font-bold bg-red-100 text-red-600 border border-red-200 flex items-center shadow-sm">
                                    <i class="fas fa-ban mr-1.5"></i> ถูกปฏิเสธ (Rejected)
                                </span>
                            @endif

                            {{-- Resubmit Indicator --}}
                            @if($isResubmit && $po->status !== 'cancelled' && $po->status !== 'completed')
                                <span class="px-3 py-1 ml-2 rounded text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200 flex items-center shadow-sm animate-pulse">
                                    <i class="fas fa-sync-alt mr-1.5"></i> แก้ไขแล้ว (รอตรวจสอบ)
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
                     {{-- 0. Manual Retry Send (For Pending/Stuck) --}}
                    @if($po->status === 'pending')
                         <form action="{{ route('purchase-orders.retry-send', $po->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 shadow-sm transition-colors whitespace-nowrap" title="กดปุ่มนี้หากรายการค้างอยู่นานเกินไป">
                                <i class="fas fa-paper-plane text-blue-500"></i> ยืนยันส่งข้อมูล (Retry)
                            </button>
                        </form>
                    @endif

                     {{-- 1. Receive Button (Hide on Rejected Page) --}}
                    @if(in_array($po->status, ['shipped_from_supplier', 'partial_receive']) && !request()->routeIs('purchase-track.rejected'))
                        <a href="{{ route('receive.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            ไปตรวจรับของ
                        </a>
                    @endif

                    {{-- 2. Resubmit Button (For Rejected Only) --}}
                    {{-- 2. Resubmit Logic (Advanced 4 Cases) --}}
                     {{-- 2. Resubmit Logic (Advanced 4 Cases & Partial Rejection) --}}
                     @php
                        $hasRejectedItems = $po->items->contains('status', 'cancelled');
                        $isCancelled = $po->status === 'cancelled';
                     @endphp

                     @if($isCancelled || $hasRejectedItems)
                        @php
                            $rejectionCode = 0;
                            // Priority 1: Check Item Level
                            $rejectedItems = $po->items->where('status', 'cancelled');
                            if ($rejectedItems->isNotEmpty()) {
                                // If ANY item is 3 (Fixable), treat as Fixable
                                if ($rejectedItems->contains('rejection_code', 3) || $rejectedItems->contains(fn($i) => str_contains($i->rejection_reason, 'ไม่ชัดเจน'))) {
                                    $rejectionCode = 3;
                                } else {
                                    $rejectionCode = $rejectedItems->first()->rejection_code ?? 0;
                                }
                            }
                            // Priority 2: PO Level (Legacy/Full Rejection)
                            if ($rejectionCode == 0) {
                                $rejectionCode = $po->pu_data['rejection_code'] ?? 0;
                            }
                            
                            // Fallback: Keywords
                            if ($rejectionCode === 0 && isset($po->pu_data['rejection_reason'])) {
                                $r = $po->pu_data['rejection_reason'];
                                if (str_contains($r, 'ไม่ชัดเจน')) $rejectionCode = 3;
                                elseif (str_contains($r, 'ไม่จำเป็น') || str_contains($r, 'งบประมาณ') || str_contains($r, 'ทดแทน')) $rejectionCode = 1;
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

        {{-- 🔵 RESUBMIT INFO BOX --}}
        @if(($po->pu_data['is_resubmit'] ?? false) && $po->status !== 'cancelled' && $po->status !== 'completed')
             @php
                $resubmitNote = '';
                if (str_contains($po->notes, '[Resubmit Info]:')) {
                     $parts = explode('[Resubmit Info]:', $po->notes);
                     $resubmitNote = trim(end($parts));
                }
            @endphp
            <div class="bg-blue-50 border-t border-blue-100 px-6 py-4 flex items-start gap-4">
                <div class="bg-white p-2 rounded-full border border-blue-200 text-blue-600 shadow-sm shrink-0">
                    <i class="fas fa-sync-alt animate-spin-slow"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-blue-800">รายการนี้ได้รับการแก้ไขและส่งใหม่ (Resubmitted)</h4>
                    <p class="text-sm text-blue-700 mt-1 leading-relaxed">
                        คุณได้ทำการแก้ไขและส่งข้อมูลให้ PU ตรวจสอบใหม่แล้ว โดยอ้างอิงเลข PR เดิม 
                        <span class="font-mono font-bold bg-blue-100 px-1.5 py-0.5 rounded text-blue-900 border border-blue-200 text-xs tracking-wider">{{ $po->pr_number }}</span>
                    </p>
                    @if($resubmitNote)
                        <div class="mt-2 text-xs bg-white/60 p-2 rounded border border-blue-200 text-blue-900 inline-block">
                            <i class="fas fa-comment-alt mr-1 text-blue-400"></i>
                            <strong>เหตุผลของคุณ:</strong> "{{ $resubmitNote }}"
                        </div>
                    @endif
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
                            'label' => !empty($po->po_number) ? 'ออก PO แล้ว' : 'ออก PR แล้ว', 
                            'desc' => !empty($po->po_number) ? 'ได้รับเลข PO เรียบร้อย' : 'ส่งคำขอไปที่ PU', 
                            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'
                        ],
                    ];

                    // ✅ Check for "Implicit Full Rejection" (All items rejected but PO status might lag or be 'ordered')
                    $totalItems = $po->items->count();
                    $rejectedItemsCount = $po->items->whereIn('status', ['cancelled', 'rejected'])->count();
                    $isFullyRejectedItems = ($totalItems > 0 && $totalItems == $rejectedItemsCount);

                    $isRejected = in_array($po->status, ['cancelled', 'rejected']) || $isFullyRejectedItems;


                    if ($isRejected) {
                         // ✅ RED PATH: Timeline ends at Rejection
                         $steps['rejected'] = [
                            'label' => 'ถูกปฏิเสธ (Rejected)', 
                            'desc' => 'ยกเลิก/ไม่ผ่านการอนุมัติ', 
                            'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'
                         ];
                    } else {
                        // ✅ GREEN PATH: Standard Shipping -> Complete/Issue
                         $steps['shipped_from_supplier'] = ['label' => 'อยู่ระหว่างจัดส่ง', 'desc' => 'PU แจ้งส่งของ', 'icon' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0'];
                         
                         // Issue Step
                         if ($hasIssues) {
                             $steps['issue'] = [
                                 'label' => 'พบปัญหา/รอแก้ไข', 
                                 'desc' => 'รอ PO ใหม่ หรือ เคลมสินค้า', 
                                 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
                             ];
                         }
                         $steps['completed'] = ['label' => 'รับของสำเร็จ', 'desc' => 'เข้าสต๊อกเรียบร้อย', 'icon' => 'M5 13l4 4L19 7'];
                    }

                    $currentStatus = $po->status;
                    if ($currentStatus == 'partial_receive') $currentStatus = 'shipped_from_supplier'; 
                    if ($currentStatus == 'approved') $currentStatus = 'ordered';
                    if ($currentStatus == 'pending') $currentStatus = 'ordered'; 
                    
                    $statusKeys = array_keys($steps);
                    $currentIndex = array_search($currentStatus, $statusKeys);
                    
                    if ($isRejected) {
                        $currentIndex = 1; // Always end at 'rejected'
                    }
                    
                    // Logic to set active index for Issue step (active but not rejected)
                    if (!$isRejected && $hasIssues && $currentIndex === false) {
                         if ($currentStatus != 'completed') {
                             $currentIndex = array_search('issue', $statusKeys);
                         }
                    }

                    // ✅ Force Issue Step if has issues (Override standard status logic except completed)
                    if (!$isRejected && $hasIssues && $currentStatus != 'completed') {
                        $currentIndex = array_search('issue', $statusKeys);
                    }

                    if ($currentIndex === false && $po->status == 'ordered') $currentIndex = 0;
                    if ($po->status == 'completed') $currentIndex = count($steps) - 1;
                    
                    $barColor = $isRejected ? 'bg-red-500' : 'bg-green-500';
                @endphp

                <div class="relative">
                    <div class="absolute top-5 left-0 w-full h-1 bg-gray-200 -translate-y-1/2 rounded-full z-0"></div>
                    
                    {{-- ✅ Progress Bar fixed to start from center of first node to center of current node --}}
                    <div class="absolute top-5 h-1 {{ $barColor }} -translate-y-1/2 rounded-full z-0 transition-all duration-1000 ease-out" 
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
                            $img = $equip ? $equip->image_url : asset('images/placeholder.webp');
                            $itemName = $item->item_description ?? ($equip ? $equip->name : 'สินค้าไม่ระบุชื่อ');
                            
                            // ✅ ITEM SEPARATION LOGIC
                            $isRejectedPage = request()->routeIs('purchase-track.rejected');
                            $itemIsRejected = in_array($item->status, ['cancelled', 'rejected', 'returned', 'inspection_failed']);

                            // If on Rejected Page, show ONLY rejected items (and maybe items that belong to a fully rejected PO)
                            // If whole PO is rejected, show all items? Or just rejected ones?
                            // Logic: Show item if strict match to page context
                            if ($isRejectedPage && !$itemIsRejected) continue;
                            if (!$isRejectedPage && $itemIsRejected) continue;
                        @endphp
                        <div class="flex items-start gap-4 bg-white p-4 rounded-lg border {{ $itemIsRejected ? 'border-red-300 ring-2 ring-red-50' : 'border-gray-200' }} shadow-sm hover:shadow-md transition-shadow h-full">
                            <div class="h-16 w-16 rounded-lg bg-gray-100 border border-gray-200 flex-shrink-0 overflow-hidden">
                                <img src="{{ $img }}" class="h-full w-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-900 line-clamp-2 leading-snug" title="{{ $itemName }}">
                                    {{ $itemName }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                    <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-medium whitespace-nowrap">
                                        สั่ง {{ $item->quantity_ordered }}
                                    </span>
                                    
                                    {{-- Rejected Item Badge --}}
                                    @if($itemIsRejected)
                                        <span class="text-red-600 flex items-center gap-1 font-bold whitespace-nowrap">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            ถูกปฏิเสธ
                                        </span>
                                    @endif

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
                                
                                {{-- Reason Box per Item --}}
                                @if($itemIsRejected && $item->rejection_reason)
                                    <div class="mt-2 bg-red-50 p-2 rounded text-xs text-red-700 border border-red-100">
                                        <strong>เหตุผล:</strong> {{ $item->rejection_reason }}
                                        @if($item->rejection_code == 3)
                                            <div class="mt-1 text-blue-700 font-medium cursor-pointer underline" onclick="triggerResubmit('{{$po->id}}')">
                                                <i class="fas fa-edit"></i> คลิกที่นี่เพื่อแก้ไขและส่งใหม่
                                            </div>
                                        @elseif(in_array($item->rejection_code, [1,2,4]))
                                            <div class="mt-1 text-red-800 font-medium">
                                                *ต้องสร้างรายการใหม่
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- History Logs (Expanded Section) -->
            <div class="bg-white rounded-xl p-5 border border-gray-200 mt-6">
                 <h4 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2 uppercase tracking-wide">
                    <i class="fas fa-history text-gray-400"></i> ประวัติการดำเนินการ (Logs)
                 </h4>
                 <div class="space-y-3">
                     {{-- Create Log --}}
                     <div class="flex items-start gap-3 relative pb-3 border-l-2 border-gray-100 pl-4 last:border-0 last:pb-0">
                         <div class="absolute -left-[5px] top-1.5 h-2.5 w-2.5 rounded-full bg-gray-300 ring-4 ring-white"></div>
                         <div>
                             <p class="text-sm font-bold text-gray-800">สร้างใบขอซื้อ (Request Created)</p>
                             <p class="text-xs text-gray-500">{{ $po->created_at->format('d/m/Y H:i') }} โดย {{ $po->orderedBy->name ?? 'System' }}</p>
                         </div>
                     </div>

                     {{-- PU History Logs (from pu_data['history']) --}}
                     @if(!empty($po->pu_data['history']) && is_array($po->pu_data['history']))
                         @foreach($po->pu_data['history'] as $log)
                             <div class="flex items-start gap-3 relative pb-3 border-l-2 border-gray-100 pl-4 last:border-0 last:pb-0">
                                 <div class="absolute -left-[5px] top-1.5 h-2.5 w-2.5 rounded-full bg-blue-400 ring-4 ring-white"></div>
                                 <div>
                                     <p class="text-sm font-bold text-gray-800">
                                         {{ ucfirst($log['event'] ?? 'Updated') }}
                                         @if(!empty($log['reason']) && $log['reason'] !== '-')
                                            <span class="text-red-500 text-xs font-normal">({{ $log['reason'] }})</span>
                                         @endif
                                     </p>
                                     <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($log['at'] ?? now())->format('d/m/Y H:i') }}</p>
                                 </div>
                             </div>
                         @endforeach
                     @endif

                     {{-- Completion Log --}}
                     @if($po->status === 'completed')
                        <div class="flex items-start gap-3 relative pb-3 border-l-2 border-gray-100 pl-4 last:border-0 last:pb-0">
                             <div class="absolute -left-[5px] top-1.5 h-2.5 w-2.5 rounded-full bg-green-500 ring-4 ring-white"></div>
                             <div>
                                 <p class="text-sm font-bold text-green-700">คำสั่งซื้อสำเร็จ (Completed)</p>
                                 <p class="text-xs text-gray-500">{{ $po->updated_at->format('d/m/Y H:i') }}</p>
                             </div>
                        </div>
                     @endif
                 </div>
            </div>
            
        </div> <!-- End of Card Content Padding -->
    </div> <!-- End of Card Wrapper -->
@endforeach

<!-- Pagination -->
<div class="mt-4">
    {{ $purchaseOrders->links() }}
</div>

<script>
    if (typeof triggerResubmit !== 'function') {
        window.triggerResubmit = function(id) {
            Swal.fire({
                title: 'แก้ไขและส่งใหม่',
                text: "กรุณาระบุข้อความถึง PU (อธิบายเหตุผลการแก้ไข):",
                input: 'textarea',
                inputPlaceholder: 'เช่น แก้ไขราคาให้แล้วครับ, แนบสเปคใหม่ให้แล้วครับ...',
                inputAttributes: {
                    'aria-label': 'Type your message here'
                },
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ยืนยันส่งข้อมูล',
                cancelButtonText: 'ยกเลิก',
                inputValidator: (value) => {
                    if (!value) {
                        return 'กรุณาระบุข้อความอธิบายก่อนส่งครับ!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('resubmit-note-'+id).value = result.value;
                    document.getElementById('resubmit-form-'+id).submit();
                }
            });
        }
    }
</script>
