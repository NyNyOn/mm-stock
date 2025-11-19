@forelse($transactions as $tx)
        <tr class="transition-colors hover:bg-gray-50">
            
            {{-- ✅✅✅ START: ใช้ตรรกะ `match` ใหม่สำหรับคอลัมน์ "ประเภท" ✅✅✅ --}}
            <td class="px-4 py-3">
                @php
                    // ตรรกะ `match` ใหม่ที่รวมทุกประเภทไว้
                    $details = match($tx->type) {
                        'receive'    => ['icon' => 'fa-plus', 'color' => 'green', 'title' => 'เพิ่มสต็อกใหม่'],
                        'withdraw'   => ['icon' => 'fa-minus', 'color' => 'red', 'title' => 'เบิกอุปกรณ์'],
                        'borrow'     => ['icon' => 'fa-tag', 'color' => 'yellow', 'title' => 'ยืมอุปกรณ์'],
                        'return'     => ['icon' => 'fa-undo-alt', 'color' => 'blue', 'title' => 'รับคืนอุปกรณ์'],
                        'adjust'     => ['icon' => 'fa-sliders-h', 'color' => 'gray', 'title' => 'ปรับสต็อก'],
                        'consumable' => ['icon' => 'fa-box-open', 'color' => 'red', 'title' => 'เบิก (ไม่ต้องคืน)'],
                        'returnable' => ['icon' => 'fa-hand-holding-heart', 'color' => 'yellow', 'title' => 'ยืม (ต้องคืน)'],
                        'partial_return' => ['icon' => 'fa-recycle', 'color' => 'red', 'title' => 'เบิก (เหลือคืนได้)'],

                        // --- ประเภทที่เพิ่มเข้ามาจากตรรกะเดิม (กันพลาด) ---
                        'borrow_temporary' => ['icon' => 'fa-stopwatch', 'color' => 'gray', 'title' => 'ยืมชั่วคราว'],
                        'dispose' => ['icon' => 'fa-trash-alt', 'color' => 'gray', 'title' => 'จำหน่าย'],
                        'lost' => ['icon' => 'fa-search-minus', 'color' => 'gray', 'title' => 'สูญหาย'],
                        'found' => ['icon' => 'fa-search-plus', 'color' => 'gray', 'title' => 'ตรวจพบ'],
                        'transfer_in' => ['icon' => 'fa-sign-in-alt', 'color' => 'gray', 'title' => 'รับโอน'],
                        'transfer_out' => ['icon' => 'fa-sign-out-alt', 'color' => 'gray', 'title' => 'โอนออก'],
                        // --- สิ้นสุดประเภทที่เพิ่ม ---

                        default      => ['icon' => 'fa-info-circle', 'color' => 'gray', 'title' => ucfirst($tx->type)]
                    };
                    
                    // ตรรกะสีที่มาจากคุณ
                    $colorClasses = [
                        'green' => 'bg-green-100 text-green-600', 
                        'red' => 'bg-red-100 text-red-600', 
                        'yellow' => 'bg-yellow-100 text-yellow-600', 
                        'blue' => 'bg-blue-100 text-blue-600', 
                        'gray' => 'bg-gray-100 text-gray-600'
                    ][$details['color'] ?? 'gray']; // ใช้ 'gray' เป็น fallback
                @endphp

                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md {{ $colorClasses }}">
                    {{-- เพิ่ม 'fas' เพื่อให้ Font Awesome ทำงาน --}}
                    <i class="mr-2 fas {{ $details['icon'] }}"></i>
                    {{ $details['title'] }}
                </span>
            </td>
            {{-- ✅✅✅ END: สิ้นสุดการแก้ไขคอลัมน์ "ประเภท" ✅✅✅ --}}
            
            {{-- ส่วนแสดงชื่ออุปกรณ์ (คงเดิม) --}}
            <td class="px-4 py-3 text-sm font-medium text-gray-800" style="white-space: normal; max-width: 300px; word-wrap: break-word;">
                {{ optional($tx->equipment)->name ?? 'N/A' }}
                <p class="text-xs text-gray-500">#TXN-{{ str_pad($tx->id, 4, '0', STR_PAD_LEFT) }}</p>
            </td>

            {{-- คอลัมน์ "ผู้ใช้" (คงเดิม) --}}
            <td class="px-4 py-3 text-sm text-gray-700">
                @if(in_array($tx->type, ['withdraw', 'borrow', 'borrow_temporary', 'consumable', 'returnable', 'partial_return']) && $tx->user)
                    {{-- ประเภทที่ user เป็นคนเริ่ม --}}
                    {{ $tx->user->fullname }}
                @elseif($tx->handler)
                    {{-- ประเภทที่ admin เป็นคนทำ (receive, adjust, return, ฯลฯ) --}}
                    {{ $tx->handler->fullname }}
                    <span class="text-xs text-gray-500">(ผู้ดำเนินการ)</span>
                @else
                    {{ optional($tx->user)->fullname ?? (optional($tx->handler)->fullname ?? 'System') }}
                @endif
            </td>

            <td class="px-4 py-3 text-sm text-gray-600">
                {{ \Carbon\Carbon::parse($tx->transaction_date)->format('d/m/Y H:i') }}</td>

            <td class="px-4 py-3 text-center">
                {{-- ปุ่มรายละเอียด --}}
                <div class="text-blue-500 cursor-pointer" onclick="showTransactionDetails({{ $tx->id }})">
                    <i class="fas fa-info-circle"></i>
                </div>
            </td>

            {{-- ✅✅✅ START: อัปเดตคอลัมน์ "สถานะ" ✅✅✅ --}}
            <td class="px-4 py-3 text-center">
                @if($tx->status == 'pending')
                    <span class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full">รอจัดส่ง</span>
                @elseif($tx->status == 'shipped')
                    <span class="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full">รอผู้ใช้รับ</span>
                @elseif($tx->status == 'completed')
                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">เสร็จสมบูรณ์</span>
                
                {{-- 🌟 เพิ่ม 'elseif' นี้สำหรับสถานะใหม่ 🌟 --}}
                @elseif($tx->status == 'cancelled')
                    <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full line-through">ยกเลิกแล้ว</span>
                
                @else
                    <span class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full">{{ ucfirst($tx->status) }}</span>
                @endif
            </td>
            {{-- ✅✅✅ END: อัปเดตคอลัมน์ "สถานะ" ✅✅✅ --}}


            {{-- ✅✅✅ START: อัปเดตคอลัมน์ "จัดการ" (อัปเดตครั้งที่ 4 - เพิ่มการตรวจสอบเวลา) ✅✅✅ --}}
            <td class="px-4 py-3 text-sm text-center">
                
                @if($tx->status == 'pending')
                    
                    <div class="flex items-center justify-center space-x-1">

                        {{-- 1. Admin: ปุ่มยืนยันจัดส่ง --}}
                        @can('permission:manage')
                            <form action="{{ route('transactions.adminConfirmShipment', $tx->id) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-green-500 rounded-lg hover:bg-green-600">
                                    ยืนยันจัดส่ง
                                </button>
                            </form>
                        @endcan
    
                        {{-- 2. User (ที่ไม่ใช่ Admin): ปุ่มยกเลิก --}}
                        @if(Auth::id() === $tx->user_id && !Auth::user()->can('permission:manage'))
                            <form action="{{ route('transactions.userCancel', $tx->id) }}" method="POST" class="m-0" 
                                  onsubmit="event.preventDefault(); confirmCancelPending(this);">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-red-500 rounded-lg hover:bg-red-600">
                                    ยกเลิก
                                </button>
                            </form>
                        @endif
    
                        {{-- 3. Admin: ปุ่มยกเลิก (Pending) --}}
                        @can('permission:manage')
                            <form action="{{ route('transactions.userCancel', $tx->id) }}" method="POST" class="m-0" 
                                  onsubmit="event.preventDefault(); confirmCancelPendingAdmin(this);">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-2 py-1 text-xs font-medium text-white bg-red-500 rounded-lg hover:bg-red-600" title="Admin: ยกเลิกรายการ Pending">
                                    <i class="fas fa-times"></i> ยกเลิก
                                </button>
                            </form>
                        @endcan
    
                    </div>

                @elseif($tx->status == 'shipped' && (Auth::id() === $tx->user_id || Auth::user()->can('permission:manage')))
                    {{-- 3. Shipped: ยืนยันรับของ --}}
                    <form action="{{ route('transactions.userConfirmReceipt', $tx->id) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-blue-500 rounded-lg hover:bg-blue-600">
                            ยืนยันรับของ
                        </button>
                    </form>
                
                @elseif($tx->status == 'completed')
                    {{-- 4. Completed: แสดงเครื่องหมายถูก และ (ถ้าเป็น Admin) แสดงปุ่มยกเลิก (Reversal) --}}
                    <div class="flex items-center justify-center space-x-2">
                        <span class="text-green-500" title="รายการเสร็จสมบูรณ์">
                            <i class="fas fa-check-circle"></i>
                        </span>

                        {{-- 🌟🌟🌟 START: อัปเดตปุ่ม Reversal ของ Admin 🌟🌟🌟 --}}
                        @can('permission:manage')
                            {{-- 
                                เพิ่มเงื่อนไข:
                                5. ต้องมี confirmed_at
                                6. confirmed_at ต้องไม่เกิน 24 ชั่วโมง
                            --}}
                            @if(
                                $tx->quantity_change < 0 && 
                                (is_null($tx->returned_quantity) || $tx->returned_quantity == 0) &&
                                (!empty($tx->confirmed_at) && \Carbon\Carbon::parse($tx->confirmed_at)->diffInHours(\Carbon\Carbon::now()) <= 24)
                            )
                                <form action="{{ route('transactions.adminCancel', $tx->id) }}" method="POST" class="m-0" 
                                      onsubmit="event.preventDefault(); confirmCancelCompleted(this);">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="px-2 py-0.5 text-xs font-medium text-white bg-red-600 rounded-md hover:bg-red-700" title="Admin: ยกเลิกและคืนสต็อก (ภายใน 24 ชม.)">
                                        <i class="fas fa-history"></i> ยกเลิก
                                    </button>
                                </form>
                            @endif
                        @endcan
                        {{-- 🌟🌟🌟 END: อัปเดตปุ่ม Reversal ของ Admin 🌟🌟🌟 --}}
                    </div>

                @elseif($tx->status == 'closed')
                    {{-- 5. Closed (เช่น ถูก write-off ไปแล้ว): แสดงเครื่องหมายถูก --}}
                    <div class="flex items-center justify-center text-green-500" title="รายการปิดสมบูรณ์">
                        <i class="fas fa-check-circle"></i>
                    </div>
                
                @elseif($tx->status == 'cancelled')
                    {{-- 6. Cancelled: แสดงเครื่องหมาย X --}}
                     <div class="flex items-center justify-center text-red-500" title="รายการถูกยกเลิก">
                        <i class="fas fa-times-circle"></i>
                    </div>

                @else
                    {{-- 7. สถานะอื่นๆ --}}
                    <span class="text-xs text-gray-400">-</span>
                @endif
            </td>
            {{-- ✅✅✅ END: อัปเดตคอลัมน์ "จัดการ" (อัปเดตครั้งที่ 4) ✅✅✅ --}}

        </tr>
    @empty
        <tr>
            <td colspan="7" class="p-8 text-center text-gray-500">ไม่พบประวัติการทำธุรกรรม</td>
        </tr>
    @endforelse

    {{-- 🌟🌟🌟 START: เพิ่ม JavaScript Functions 🌟🌟🌟 --}}
    <script>
        // (ฟังก์ชันเหล่านี้จะถูกเรียกโดย onsubmit ที่เราเพิ่งเพิ่มเข้าไป)

        function confirmCancelPending(formElement) {
            Swal.fire({
                title: 'ยกเลิกรายการ?',
                text: 'คุณต้องการยกเลิกรายการเบิกนี้ใช่หรือไม่?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33', // Red
                cancelButtonColor: '#3085d6', // Blue
                confirmButtonText: 'ใช่, ยกเลิกเลย',
                cancelButtonText: 'ปิด'
            }).then((result) => {
                if (result.isConfirmed) {
                    formElement.submit(); // Submit the form
                }
            });
        }

        function confirmCancelPendingAdmin(formElement) {
            Swal.fire({
                title: 'Admin: ยกเลิกรายการ Pending?',
                text: 'คุณต้องการยกเลิกรายการที่กำลัง Pending นี้ใช่หรือไม่? (สต็อกจะไม่ถูกคืนเพราะยังไม่ได้ตัด)',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ยกเลิก',
                cancelButtonText: 'ปิด'
            }).then((result) => {
                if (result.isConfirmed) {
                    formElement.submit();
                }
            });
        }

        function confirmCancelCompleted(formElement) {
            Swal.fire({
                title: '!!! ⚠️ คำเตือน (Admin) !!!',
                // (ใช้ html แทน text เพื่อให้ขึ้นบรรทัดใหม่ได้)
                html: 'การดำเนินการนี้จะ <strong>[ยกเลิก]</strong> รายการที่เสร็จสมบูรณ์ และ <strong>[คืนสต็อก]</strong> กลับเข้าคลัง<br><br>คุณแน่ใจหรือไม่ว่าต้องการยกเลิกรายการนี้?',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ยืนยันยกเลิกและคืนสต็อก',
                cancelButtonText: 'ปิด'
            }).then((result) => {
                if (result.isConfirmed) {
                    formElement.submit();
                }
            });
        }
    </script>
    {{-- 🌟🌟🌟 END: เพิ่ม JavaScript Functions 🌟🌟🌟 --}}