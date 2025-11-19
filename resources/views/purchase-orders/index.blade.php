@extends('layouts.app')

@section('header', 'ใบสั่งซื้อ (Purchase Orders)')
@section('subtitle', 'จัดการใบสั่งซื้อตามรอบ, ใบสั่งซื้อด่วน และใบสั่งซื้อจาก GLPI')

@section('content')
    <div class="space-y-6">

        {{-- Scheduled Purchase Orders --}}
        <div class="p-6 soft-card gentle-shadow">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">🛒 ใบสั่งซื้อตามรอบ (Scheduled)</h3>
                    <p class="mt-1 text-sm text-gray-500">รายการที่สร้างจากระบบตรวจสอบสต็อกอัตโนมัติ</p>
                </div>
                <div class="flex items-center space-x-2">
                    @can('po:create')
                    <form id="run-stock-check-form" action="{{ route('purchase-orders.runStockCheck') }}" method="POST"
                        class="hidden">@csrf</form>
                    <button type="button"
                        onclick="confirmAndSubmitForm(event, 'run-stock-check-form', 'ยืนยันการรันคำสั่ง?', 'ระบบจะตรวจสอบสต็อกและสร้างใบสั่งซื้ออัตโนมัติ')"
                        class="flex items-center px-4 py-2 text-sm font-medium bg-gradient-to-br from-cyan-100 to-cyan-200 text-cyan-700 rounded-xl hover:shadow-lg button-soft">
                        <i class="mr-2 fas fa-cogs"></i>
                        <span>ตรวจสอบสต็อกต่ำ</span>
                    </button>
                    <button type="button" id="set-auto-requester-btn"
                        class="flex items-center px-4 py-2 text-sm font-medium bg-gradient-to-br from-indigo-100 to-indigo-200 text-indigo-700 rounded-xl hover:shadow-lg button-soft">
                        <i class="mr-2 fas fa-user-cog"></i> ตั้งค่าผู้สั่งอัตโนมัติ
                    </button>
                    @endcan
                    @can('po:manage')
                    <form id="submit-scheduled-form" action="{{ route('purchase-orders.submitScheduled') }}" method="POST"
                        class="hidden">@csrf</form>
                    <button type="button" @if(!$scheduledOrder || $scheduledOrder->items->isEmpty()) disabled @endif
                        onclick="confirmAndSubmitForm(event, 'submit-scheduled-form', 'ยืนยันการส่ง?', 'ต้องการส่งใบสั่งซื้อตามรอบไปที่ฝ่ายจัดซื้อใช่หรือไม่')"
                        class="flex items-center px-4 py-2 text-sm font-medium bg-gradient-to-br from-green-100 to-green-200 text-green-700 rounded-xl hover:shadow-lg button-soft disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="mr-2 fas fa-paper-plane"></i>
                        <span>ส่งใบสั่งซื้อ</span>
                    </button>
                    @endcan
                </div>
            </div>
            <div class="mt-4">
                @if ($scheduledOrder && $scheduledOrder->items->isNotEmpty())
                    <div id="po-items-container-{{ $scheduledOrder->id }}">
                        @include('purchase-orders.partials._po_items_table_glpi', ['order' => $scheduledOrder])
                    </div>
                @else
                    <div class="py-4 text-sm text-center text-gray-500">ไม่มีรายการในใบสั่งซื้อตามรอบ</div>
                @endif
            </div>
        </div>

        {{-- Urgent Purchase Orders --}}
        <div class="p-6 soft-card gentle-shadow">
             <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">⚡ ใบสั่งซื้อด่วน (Urgent)</h3>
                    <p class="mt-1 text-sm text-gray-500">รายการที่ถูกเพิ่มโดยผู้ดูแลระบบ</p>
                </div>
                @can('po:manage')
                 <form id="submit-urgent-form" action="{{ route('purchase-orders.submitUrgent') }}" method="POST" class="hidden">@csrf</form>
                 <button type="button" @if($urgentOrders->isEmpty() || $urgentOrders->every(fn($order) => $order->items->isEmpty())) disabled @endif
                     onclick="confirmAndSubmitForm(event, 'submit-urgent-form', 'ยืนยันการส่ง?', 'ต้องการส่งใบสั่งซื้อด่วนทั้งหมดไปที่ฝ่ายจัดซื้อใช่หรือไม่')"
                     class="flex items-center px-4 py-2 text-sm font-medium bg-gradient-to-br from-green-100 to-green-200 text-green-700 rounded-xl hover:shadow-lg button-soft disabled:opacity-50 disabled:cursor-not-allowed">
                     <i class="mr-2 fas fa-paper-plane"></i>
                     <span>ส่งใบสั่งซื้อทั้งหมด</span>
                 </button>
                @endcan
            </div>
             <div class="mt-4 space-y-4">
                @forelse($urgentOrders as $order)
                     @if($order->items->isNotEmpty())
                        <div id="po-items-container-{{ $order->id }}">
                            @include('purchase-orders.partials._po_items_table_glpi', ['order' => $order])
                        </div>
                     @endif
                @empty
                    <div class="py-4 text-sm text-center text-gray-500">ไม่มีใบสั่งซื้อด่วน</div>
                @endforelse
                @if($urgentOrders->isNotEmpty() && $urgentOrders->every(fn($order) => $order->items->isEmpty()))
                     <div class="py-4 text-sm text-center text-gray-500">ไม่มีรายการในใบสั่งซื้อด่วน</div>
                @endif
            </div>
        </div>

        {{-- GLPI Purchase Orders --}}
        <div class="p-6 soft-card gentle-shadow">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="mr-2 text-purple-500 fas fa-ticket-alt"></i> ใบคำขอจาก GLPI (IT)
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">รายการที่ดึงมาจาก Ticket สถานะ "รอการอนุมัติ/รออะไหล่" โดยอัตโนมัติ</p>
                </div>
                <div class="flex items-center space-x-2">
                    <form id="run-glpi-sync-form" action="{{ route('purchase-orders.runGlpiSync') }}" method="POST" class="hidden">@csrf</form>
                    <button type="button"
                        onclick="confirmAndSubmitForm(event, 'run-glpi-sync-form', 'ยืนยันการตรวจสอบ?', 'ระบบจะเชื่อมต่อกับ GLPI เพื่อดึงใบงานใหม่เข้ามา')"
                        class="flex items-center px-4 py-2 text-sm font-medium bg-gradient-to-br from-purple-100 to-purple-200 text-purple-700 rounded-xl hover:shadow-lg button-soft">
                        <i class="mr-2 fas fa-sync"></i><span>ตรวจสอบใบงาน GLPI</span>
                    </button>
                    
                    <button type="button" id="set-auto-job-requester-btn"
                        class="flex items-center px-4 py-2 text-sm font-medium bg-gradient-to-br from-indigo-100 to-indigo-200 text-indigo-700 rounded-xl hover:shadow-lg button-soft">
                        <i class="mr-2 fas fa-user-cog"></i> ตั้งค่าผู้สั่งตาม Job
                    </button>

                    @if ($glpiOrders->isNotEmpty())
                        <form id="submit-glpi-orders-form" action="{{ route('purchase-orders.submitJobOrders') }}" method="POST" class="hidden">@csrf</form>
                        <button type="button"
                            onclick="confirmAndSubmitForm(event, 'submit-glpi-orders-form', 'ยืนยันส่งใบสั่งซื้อตาม Job?', 'ใบสั่งซื้อตาม Job ทั้งหมดจะถูกส่งไปที่ฝ่ายจัดซื้อ')"
                            class="flex items-center px-4 py-2 text-sm font-bold text-gray-700 bg-gradient-to-br from-gray-200 to-gray-300 rounded-xl hover:shadow-lg button-soft">
                            <i class="mr-2 fas fa-paper-plane"></i><span>ส่งใบสั่งซื้อตาม Job ทั้งหมด</span>
                        </button>
                    @endif
                </div>
            </div>

            <div class="mt-4 space-y-4">
                @forelse ($glpiOrders as $order)
                <div class="border-2 border-purple-200 bg-purple-50/50 rounded-2xl">
                    <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-purple-100 rounded-t-xl">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-purple-800">{{ $order->notes }}</p>
                            <p class="text-xs text-gray-600">ผู้ร้องขอ: {{ $order->glpi_requester_name ?? 'N/A' }}</p>
                        </div>
                        <div class="flex items-center flex-shrink-0 space-x-2">
                            <form id="delete-po-form-{{ $order->id }}" action="{{ route('purchase-orders.destroy', $order->id) }}" method="POST">
                                @csrf @method('DELETE')
                            </form>
                            <button type="button"
                                    onclick="confirmAndSubmitForm(event, 'delete-po-form-{{ $order->id }}', 'ยืนยันการลบ?', 'คุณต้องการลบใบคำขอของ Ticket นี้ใช่หรือไม่?')"
                                    class="px-3 py-1 text-xs font-bold text-red-700 bg-red-200 rounded-lg hover:bg-red-300"
                                    title="ลบใบคำขอนี้">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-4">
                        <div id="po-items-container-{{ $order->id }}">
                           @include('purchase-orders.partials._po_items_table_glpi', ['order' => $order])
                        </div>

                        <div class="pt-4 mt-4 border-t">
                            <button onclick="openAddItemModal({{ $order->id }})"
                                    class="w-full px-4 py-2 text-sm font-bold text-center text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200">
                                <i class="fas fa-plus"></i> เพิ่มรายการสั่งซื้อ
                            </button>
                        </div>
                    </div>
                </div>
                @empty
                <div class="py-12 text-center text-gray-500">
                    <i class="mb-4 text-gray-300 fas fa-check-circle fa-3x"></i>
                    <h4 class="text-lg font-semibold">ไม่มีใบคำขอจาก GLPI ที่รอดำเนินการ</h4>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    @include('partials.modals.select-item-modal')

    <!-- Modal ตั้งค่าผู้สั่งอัตโนมัติ (Scheduled) -->
    <div id="autoRequesterModal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 modal-backdrop-soft">
        <div class="w-full max-w-lg soft-card rounded-2xl modal-content-wrapper animate-slide-up-soft">
            <div class="flex items-center justify-between p-5 border-b">
                <h3 class="text-lg font-bold gradient-text-soft">ตั้งค่าผู้สั่งอัตโนมัติ (สำหรับ PO สต็อกต่ำ)</h3>
                <button type="button" class="text-2xl text-gray-500 hover:text-gray-700" onclick="closeModal('autoRequesterModal')">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-gray-600">เลือก User ที่จะใช้เป็น "ผู้ร้องขอ" (Requester) สำหรับใบสั่งซื้อ (PO) ที่ระบบสร้างอัตโนมัติเมื่อสต็อกต่ำ</p>
                <form id="autoRequesterForm">
                    @csrf
                    <div>
                        <label for="automation_requester_id" class="block mb-1 text-sm font-medium text-gray-700">เลือกผู้ใช้งาน:</label>
                        <select id="automation_requester_id" name="automation_requester_id" required class="w-full">
                            <option value="">กำลังโหลด...</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="flex justify-end p-4 space-x-2 bg-gray-50/50 rounded-b-2xl">
                <button type="button" class="px-4 py-2 font-medium text-gray-800 bg-gray-200 rounded-lg hover:bg-gray-300" onclick="closeModal('autoRequesterModal')">ปิด</button>
                <button type="button" class="px-6 py-2 font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700" id="saveAutoRequesterBtn">บันทึก</button>
            </div>
        </div>
    </div>

    <!-- Modal ตั้งค่าผู้สั่งอัตโนมัติ (Job/GLPI) -->
    <div id="autoJobRequesterModal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 modal-backdrop-soft">
        <div class="w-full max-w-lg soft-card rounded-2xl modal-content-wrapper animate-slide-up-soft">
            <div class="flex items-center justify-between p-5 border-b">
                <h3 class="text-lg font-bold gradient-text-soft">ตั้งค่าผู้สั่งอัตโนมัติ (สำหรับ PO ตาม Job/GLPI)</h3>
                <button type="button" class="text-2xl text-gray-500 hover:text-gray-700" onclick="closeModal('autoJobRequesterModal')">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-gray-600">เลือก User ที่จะใช้เป็น "ผู้ร้องขอ" (Requester) สำหรับใบสั่งซื้อ (PO) ที่ระบบดึงมาจาก GLPI โดยอัตโนมัติ</p>
                <form id="autoJobRequesterForm">
                    @csrf
                    <div>
                        <label for="automation_job_requester_id" class="block mb-1 text-sm font-medium text-gray-700">เลือกผู้ใช้งาน:</label>
                        <select id="automation_job_requester_id" name="automation_job_requester_id" required class="w-full">
                            <option value="">กำลังโหลด...</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="flex justify-end p-4 space-x-2 bg-gray-50/50 rounded-b-2xl">
                <button type="button" class="px-4 py-2 font-medium text-gray-800 bg-gray-200 rounded-lg hover:bg-gray-300" onclick="closeModal('autoJobRequesterModal')">ปิด</button>
                <button type="button" class="px-6 py-2 font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700" id="saveAutoJobRequesterBtn">บันทึก</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
{{-- JavaScript เดิมสำหรับ Modal เพิ่ม Item และอื่นๆ --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ... (โค้ด JavaScript เดิมสำหรับ openAddItemModal และอื่นๆ) ...
        let currentPoId;
        let currentStockStatus = 'in_stock';
        let searchDebounce;

        const modal = document.getElementById('select-item-modal');
        const searchInput = document.getElementById('select-item-search');
        const itemList = document.getElementById('select-item-list');
        const pagination = document.getElementById('select-item-pagination');
        const tabInStock = document.getElementById('tab-in-stock');
        const tabOutOfStock = document.getElementById('tab-out-of-stock');

        window.openAddItemModal = function(orderId) {
            currentPoId = orderId;
            searchInput.value = '';
            currentStockStatus = 'in_stock';
            updateTabs();
            fetchItemsForPO(1, '');
            if (typeof showModal === 'function') {
                showModal('select-item-modal');
            }
        }

        function fetchItemsForPO(page = 1, query = '') {
            const formData = new FormData();
            formData.append('action', 'search_items');
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('q', query);
            formData.append('page', page);
            formData.append('stock_status', currentStockStatus);

            itemList.innerHTML = `<tr><td colspan="4" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
            pagination.innerHTML = '';

            fetch('{{ route('ajax.handler') }}', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    itemList.innerHTML = data.items_html;
                    pagination.innerHTML = data.pagination_html;
                } else {
                    itemList.innerHTML = `<tr><td colspan="4" class="text-center p-4 text-red-500">Error</td></tr>`;
                }
            });
        }

        function updateTabs() {
            const isActive = currentStockStatus === 'in_stock';
            tabInStock.className = `px-4 py-2 text-sm font-semibold text-center border-b-2 tab-button ${isActive ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`;
            tabOutOfStock.className = `px-4 py-2 text-sm font-semibold text-center border-b-2 tab-button ${!isActive ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`;
        }

        tabInStock.addEventListener('click', () => {
            currentStockStatus = 'in_stock';
            updateTabs();
            fetchItemsForPO(1, searchInput.value);
        });

        tabOutOfStock.addEventListener('click', () => {
            currentStockStatus = 'out_of_stock';
            updateTabs();
            fetchItemsForPO(1, searchInput.value);
        });

        searchInput.addEventListener('keyup', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                fetchItemsForPO(1, searchInput.value);
            }, 300);
        });

        document.addEventListener('click', function(event) {
            if (event.target.matches('#select-item-pagination a')) {
                event.preventDefault();
                const page = new URL(event.target.href).searchParams.get('page');
                fetchItemsForPO(page, searchInput.value);
            }
        });

        window.promptForQuantity = async function(equipmentId, equipmentName) {
            const { value: quantity } = await Swal.fire({
                title: `เพิ่ม: ${equipmentName}`, input: 'number',
                inputLabel: 'กรุณาระบุจำนวน', inputValue: 1,
                inputAttributes: { min: 1 }, showCancelButton: true,
                confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก',
                inputValidator: (v) => !v || v < 1 ? 'ต้องมากกว่า 0' : null
            });

            if (quantity && currentPoId) {
                try {
                    const response = await fetch(`/purchase-orders/${currentPoId}/add-item`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ equipment_id: equipmentId, quantity: quantity })
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message);
                    if(typeof closeModal === 'function') closeModal('select-item-modal');
                    await Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: result.message, timer: 1500, showConfirmButton: false });
                    await refreshItemsList(currentPoId);
                } catch (error) {
                    Swal.fire('ผิดพลาด', error.message, 'error');
                }
            }
        }

        window.confirmAndDeleteItem = async function(itemId, orderId, itemName) {
            const { isConfirmed } = await Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                html: `คุณต้องการลบรายการ <br><strong class="text-lg text-red-600">${itemName || 'ที่เลือก'}</strong><br> ออกจากใบสั่งซื้อนี้ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            });

            if (isConfirmed) {
                try {
                    const response = await fetch(`/purchase-orders/item/${itemId}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message);
                    await Swal.fire({
                        icon: 'success',
                        title: 'ลบสำเร็จ!',
                        text: `ลบรายการ ${itemName || ''} เรียบร้อยแล้ว`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    await refreshItemsList(orderId);
                } catch (error) {
                    Swal.fire('ผิดพลาด', error.message, 'error');
                }
            }
        }

        async function refreshItemsList(orderId) {
            const container = document.getElementById(`po-items-container-${orderId}`);
            if (!container) return;
            try {
                const response = await fetch(`/purchase-orders/${orderId}/items-view`);
                container.innerHTML = await response.text();
            } catch (error) {
                container.innerHTML = '<p class="text-red-500">Error refreshing list.</p>';
            }
        }
    });
</script>

{{-- JavaScript สำหรับ Modal ตั้งค่าผู้สั่ง (Scheduled) --}}
<script>
    $(document).ready(function() {
        $('#automation_requester_id').select2({
            dropdownParent: $('#autoRequesterModal')
        });

        $('#set-auto-requester-btn').on('click', function() {
            var selectBox = $('#automation_requester_id');
            selectBox.html('<option value="">กำลังโหลด...</option>').trigger('change');
            
            if (typeof showModal === 'function') {
                showModal('autoRequesterModal');
            }

            $.ajax({
                url: "{{ route('ajax.get-ldap-users-with-setting', ['settingKey' => 'automation_requester_id']) }}",
                type: 'GET',
                success: function(data) {
                    selectBox.html('<option value="">-- กรุณาเลือก --</option>');
                    var currentRequesterId = data.current_requester_id;
                    $.each(data.users, function(index, user) {
                        var selected = (user.id == currentRequesterId) ? 'selected' : '';
                        selectBox.append('<option value="' + user.id + '" ' + selected + '>' + user.fullname + ' (' + user.username + ')</option>');
                    });
                    selectBox.trigger('change');
                },
                error: function() {
                    Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถโหลดรายชื่อผู้ใช้งานได้', 'error');
                    if (typeof closeModal === 'function') closeModal('autoRequesterModal');
                }
            });
        });

        $('#saveAutoRequesterBtn').on('click', function() {
            var selectedUserId = $('#automation_requester_id').val();
            if (!selectedUserId) {
                Swal.fire('ผิดพลาด!', 'กรุณาเลือกผู้ใช้งาน', 'error');
                return;
            }

            $.ajax({
                url: "{{ route('settings.update.automation-requester') }}",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    user_id: selectedUserId
                },
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    if (typeof closeModal === 'function') {
                         closeModal('autoRequesterModal');
                    }
                },
                error: function() {
                     Swal.fire('ผิดพลาด!', 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error');
                }
            });
        });
    });
</script>

{{-- JavaScript สำหรับ Modal ตั้งค่าผู้สั่ง (Job/GLPI) --}}
<script>
    $(document).ready(function() { 
        $('#automation_job_requester_id').select2({
            dropdownParent: $('#autoJobRequesterModal')
        });

        $('#set-auto-job-requester-btn').on('click', function() {
            var selectBox = $('#automation_job_requester_id');
            selectBox.html('<option value="">กำลังโหลด...</option>').trigger('change');
            
            if (typeof showModal === 'function') {
                showModal('autoJobRequesterModal');
            }

            $.ajax({
                url: "{{ route('ajax.get-ldap-users-with-setting', ['settingKey' => 'automation_job_requester_id']) }}",
                type: 'GET',
                success: function(data) {
                    selectBox.html('<option value="">-- กรุณาเลือก --</option>');
                    var currentRequesterId = data.current_requester_id;
                    $.each(data.users, function(index, user) {
                        var selected = (user.id == currentRequesterId) ? 'selected' : '';
                        selectBox.append('<option value="' + user.id + '" ' + selected + '>' + user.fullname + ' (' + user.username + ')</option>');
                    });
                    selectBox.trigger('change');
                },
                error: function() {
                    Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถโหลดรายชื่อผู้ใช้งานได้', 'error');
                    if (typeof closeModal === 'function') closeModal('autoJobRequesterModal');
                }
            });
        });

        $('#saveAutoJobRequesterBtn').on('click', function() {
            var selectedUserId = $('#automation_job_requester_id').val();
            if (!selectedUserId) {
                Swal.fire('ผิดพลาด!', 'กรุณาเลือกผู้ใช้งาน', 'error');
                return;
            }

            $.ajax({
                url: "{{ route('settings.update.automation-job-requester') }}",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    user_id: selectedUserId
                },
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    if (typeof closeModal === 'function') {
                         closeModal('autoJobRequesterModal');
                    }
                },
                error: function() {
                     Swal.fire('ผิดพลาด!', 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error');
                }
            });
        });
    });
</script>
@endpush


