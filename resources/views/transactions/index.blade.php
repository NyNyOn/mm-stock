@extends('layouts.app')

@section('header', 'ประวัติธุรกรรมทั้งหมด')
@section('subtitle', 'ประวัติการเบิก จ่าย ยืม คืน ทั้งหมดในระบบ')

@section('content')
<div class="space-y-6 page animate-slide-up-soft">

    {{-- Tabs UI --}}
    <div class="mb-6">
       <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-4 overflow-x-auto" aria-label="Tabs">
                @php
                    $tabs = [
                        // --- ✅✅✅ START: นำ Tab 'pending_confirmation' กลับมา ---
                        'pending_confirmation' => [
                            'name' => 'รายการรอรับ', // Changed name back
                            'icon' => 'fas fa-exclamation-circle text-yellow-500',
                            'permission' => true // Everyone can see items pending their confirmation
                        ],
                        // --- ✅✅✅ END: นำ Tab 'pending_confirmation' กลับมา ---
                        'my_history' => [
                            'name' => 'ประวัติของฉัน',
                            'icon' => 'fas fa-user-clock text-blue-500',
                            'permission' => true // Everyone can see their own history
                        ],
                        'all_history' => [
                            'name' => 'ประวัติทั้งหมด',
                            'icon' => 'fas fa-globe-asia text-gray-500',
                            // Use 'report:view' permission as decided
                            'permission' => auth()->user()->can('report:view') // ✅ Corrected permission
                        ],
                    ];
                @endphp

                @foreach ($tabs as $key => $tab)
                    @if ($tab['permission'])
                        @php
                            // สร้าง URL โดยรวม query string เดิมทั้งหมด ยกเว้น 'page' และ 'status'
                            $currentParams = request()->except(['page', 'status']);
                            $newParams = array_merge($currentParams, ['status' => $key]);
                            $url = route('transactions.index', $newParams);
                            $isActive = ($statusFilter ?? '') === $key;
                        @endphp
                        <a href="{{ $url }}"
                           class="{{ $isActive ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}
                                  whitespace-nowrap flex py-4 px-1 border-b-2 font-medium text-sm items-center space-x-2">
                            <i class="{{ $tab['icon'] }} {{ $isActive ? '' : 'text-gray-400' }}"></i>
                            <span>{{ $tab['name'] }}</span>
                             {{-- Optional: Show count for pending --}}
                             @if($key === 'pending_confirmation' && isset($pendingCount) && $pendingCount > 0)
                                 <span class="ml-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                     {{ $pendingCount }}
                                 </span>
                             @endif
                        </a>
                    @endif
                @endforeach
            </nav>
        </div>
    </div>
    {{-- End Tabs UI --}}


    {{-- Filter Form (เฉพาะแท็บ all_history) --}}
    @if($statusFilter === 'all_history')
        <form id="filter-form" method="GET" action="{{ route('transactions.index', ['status' => 'all_history']) }}" class="p-4 mb-6 bg-white border border-gray-200 rounded-lg shadow-sm">
            {{-- Hidden input to keep status --}}
            <input type="hidden" name="status" value="all_history">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {{-- Search --}}
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">ค้นหา</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="ชื่อ, Serial, หมายเหตุ..." class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                {{-- Type --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">ประเภท</label>
                    <select name="type" id="type" class="block w-full py-2 pl-3 pr-10 mt-1 text-base border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">-- ทุกประเภท --</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" {{ request('type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- User --}}
                <div>
                    <label for="user_id" class="block text-sm font-medium text-gray-700">ผู้ใช้</label>
                    <select name="user_id" id="user_id" class="block w-full py-2 pl-3 pr-10 mt-1 text-base border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">-- ทุกผู้ใช้ --</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->fullname }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Start Date --}}
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">ตั้งแต่วันที่</label>
                    <input type="date" name="start_date" id="start_date" value="{{ request('start_date') }}" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                {{-- End Date --}}
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">ถึงวันที่</label>
                    <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
            </div>
            <div class="flex justify-end mt-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="mr-2 fas fa-filter"></i> กรองข้อมูล
                </button>
                 <a href="{{ route('transactions.index', ['status' => 'all_history']) }}" class="inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                     ล้างค่า
                 </a>
            </div>
        </form>
    @endif
    {{-- End Filter Form --}}

    {{-- Transaction Table Wrapper --}}
    <div id="transaction-table-wrapper" class="overflow-hidden bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        {{-- Header Table --}}
                        <th scope="col" class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">ประเภท</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">อุปกรณ์ / ID</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">ผู้ใช้</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">วันที่</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">รายละเอียด</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">สถานะ</th>
                        <th scope="col" class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="transaction-table-body" class="bg-white divide-y divide-gray-200">
                    {{-- Include table rows partial --}}
                    @include('transactions.partials._table_rows', ['transactions' => $transactions])
                </tbody>
            </table>
        </div>
    </div>
    {{-- End Transaction Table Wrapper --}}

    {{-- Pagination Wrapper --}}
    <div id="pagination-wrapper">
        {{ $transactions->links() }}
    </div>
    {{-- End Pagination Wrapper --}}

</div> {{-- Close page container --}}

{{-- Include Transaction Detail Modal --}}
{{-- Make sure the included file path is correct --}}
@include('partials.modals.transaction-modal')

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.getElementById('transaction-table-body');
        const paginationWrapper = document.getElementById('pagination-wrapper');
        const filterForm = document.getElementById('filter-form'); // For filters
        let currentLatestTimestamp = {{ $transactions->isNotEmpty() ? \Carbon\Carbon::parse($transactions->first()->transaction_date)->timestamp : now()->timestamp }};
        let isFetching = false; // Prevent multiple simultaneous fetches

        // Function to show transaction details modal
        window.showTransactionDetails = async function (transactionId) {
            try {
                const response = await fetch(`/transactions/${transactionId}`);
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error(`Fetch Error: ${response.status}`, errorText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();

                if (result.success && result.data) {
                    const tx = result.data;
                    const modal = document.getElementById('transaction-details-modal');

                    if (!modal) {
                        console.error("CRITICAL: Modal Element 'transaction-details-modal' not found!");
                        Swal.fire('Config Error!', 'ไม่พบ Element ของ Modal (ID: transaction-details-modal). กรุณาตรวจสอบไฟล์ Blade.', 'error');
                        return;
                    }

                    // --- Safely Populate Modal Elements ---
                    const setText = (id, value) => {
                        const el = modal.querySelector(`#${id}`);
                        if (el) el.textContent = (value === null || typeof value === 'undefined') ? '-' : value;
                    };
                    const setHtml = (id, value) => { const el = modal.querySelector(`#${id}`); if (el) el.innerHTML = value || '-'; };
                    const setAttr = (id, attr, value) => { const el = modal.querySelector(`#${id}`); if (el) el.setAttribute(attr, value || '#'); };
                    const setVisibility = (id, visible) => { const el = modal.querySelector(`#${id}`); if (el) el.classList.toggle('hidden', !visible); };

                    // Populate basic info
                    setText('modal-tx-id', tx.id);
                    setText('modal-tx-equipment-name', tx.equipment?.name || 'N/A');
                    setText('modal-tx-equipment-serial', tx.equipment?.serial_number || 'N/A');
                    setAttr('modal-tx-image', 'src', tx.equipment?.latest_image?.image_url || '{{ asset('images/placeholder.webp') }}');
                    const imgEl = modal.querySelector('#modal-tx-image');
                    if(imgEl) imgEl.onerror = function() { this.src = '{{ asset('images/placeholder.webp') }}'; };
                    setText('modal-tx-type', formatTransactionType(tx.type));
                    setText('modal-tx-quantity', Math.abs(tx.quantity_change || 0));
                    setText('modal-tx-user', tx.user?.fullname || 'N/A');
                    setText('modal-tx-date', formatDateTime(tx.transaction_date));
                    setHtml('modal-tx-status', getStatusBadge(tx.status));
                    setText('modal-tx-handler', tx.handler?.fullname || 'N/A');
                    setText('modal-tx-admin-confirm-date', formatDateTime(tx.admin_confirmed_at));
                    setText('modal-tx-user-confirm-date', formatDateTime(tx.user_confirmed_at));

                    // --- ✅✅✅ START: Corrected Logic for Purpose, Notes, GLPI ---
                    const isGlpiPurposeDirect = tx.purpose === 'glpi_ticket'; // Check dedicated field first
                    const isGlpiPurposePrefixed = tx.purpose && tx.purpose.startsWith('glpi-'); // Check for prefix (e.g., glpi-it-123)
                    const isGlpi = isGlpiPurposeDirect || isGlpiPurposePrefixed;

                    const glpiId = tx.glpi_ticket_id; // Use the dedicated ID field
                    const glpiRelation = tx.glpi_ticket_relation; // From Controller's load()
                    const glpiBaseUrl = "{{ config('services.glpi.url', '') }}"; // Assuming you have this config

                    // Purpose Section
                    // Show this section ONLY if it's NOT a GLPI purpose
                    setVisibility('modal-tx-purpose-section', !isGlpi);
                    if (!isGlpi) {
                        let purposeText = tx.purpose || '-';
                        if (tx.purpose && tx.purpose.trim() === 'general_use') {
                             purposeText = 'เบิกใช้งานทั่วไป';
                         }
                        // Add more mappings here if needed
                        setText('modal-tx-purpose', purposeText);
                    } else {
                        setText('modal-tx-purpose', '-'); // Clear if it was GLPI
                    }


                    // GLPI Section
                    // Show this section if it IS a GLPI purpose AND we have the ID and base URL
                    const glpiVisible = isGlpi && glpiId && glpiBaseUrl;
                    setVisibility('modal-tx-glpi-section', glpiVisible);
                    if (glpiVisible) {
                        setAttr('modal-tx-glpi-link', 'href', `${glpiBaseUrl}/front/ticket.form.php?id=${glpiId}`);
                        // Try to display ticket name from relation, fallback to just ID
                        setText('modal-tx-glpi-link', `ใบงาน #${glpiId}${glpiRelation ? ': ' + glpiRelation.name : ''}`);
                    }

                    // Notes Section
                    // Always show the notes section, but display '-' if empty
                    setText('modal-tx-notes', tx.notes || '-');
                    // --- ✅✅✅ END: Corrected Logic ---


                    // Safely call global showModal
                    if(typeof showModal === 'function'){
                        showModal('transaction-details-modal');
                    } else {
                        console.error('Global showModal function not found!');
                        Swal.fire('Script Error!', 'ไม่พบฟังก์ชัน showModal หลักของระบบ', 'error');
                    }

                } else {
                    console.error('API response unsuccessful or data missing:', result);
                    if(typeof Swal !== 'undefined'){
                        Swal.fire('ผิดพลาด!', result.message || 'ไม่สามารถโหลดข้อมูลรายละเอียดได้', 'error');
                    } else {
                        alert(result.message || 'ไม่สามารถโหลดข้อมูลรายละเอียดได้');
                    }
                }
            } catch (error) {
                console.error('Error fetching/processing transaction details:', error);
                 if(typeof Swal !== 'undefined'){
                    Swal.fire('ผิดพลาด!', 'เกิดข้อผิดพลาดในการโหลดรายละเอียด: ' + error.message, 'error');
                } else {
                     alert('เกิดข้อผิดพลาดในการโหลดรายละเอียด: ' + error.message);
                }
            }
        };

        // Helper functions
        function formatTransactionType(type) {
             switch (type) {
                case 'withdraw': return 'เบิก'; case 'borrow': return 'ยืม';
                case 'borrow_temporary': return 'ยืมชั่วคราว'; case 'return': 'คืน';
                case 'receive': return 'รับเข้า'; case 'adjust': return 'ปรับสต็อก';
                case 'dispose': return 'จำหน่าย'; case 'lost': return 'สูญหาย';
                case 'found': return 'ตรวจพบ'; case 'transfer_in': return 'รับโอน';
                case 'transfer_out': return 'โอนออก';
                default: return type ? type.charAt(0).toUpperCase() + type.slice(1) : '-';
            }
        }
        function formatDateTime(dateTimeString) {
             if (!dateTimeString) return '-';
            try {
                const date = new Date(dateTimeString);
                if (isNaN(date.getTime())) return '-';
                return date.toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'short' });
            } catch (e) { console.error('Date formatting error:', e); return '-'; }
        }
        function getStatusBadge(status) {
            let bgColor = 'bg-gray-100'; let textColor = 'text-gray-800'; let text = status || 'Unknown';
            switch (status) {
                case 'pending': bgColor = 'bg-yellow-100'; textColor = 'text-yellow-800'; text = 'รออนุมัติ'; break;
                case 'approved': bgColor = 'bg-blue-100'; textColor = 'text-blue-800'; text = 'อนุมัติแล้ว'; break;
                case 'shipped': bgColor = 'bg-cyan-100'; textColor = 'text-cyan-800'; text = 'จัดส่งแล้ว'; break;
                case 'user_confirm_pending': bgColor = 'bg-orange-100'; textColor = 'text-orange-800'; text = 'รอยืนยันรับ'; break;
                case 'completed': bgColor = 'bg-green-100'; textColor = 'text-green-800'; text = 'เสร็จสมบูรณ์'; break;
                case 'rejected': bgColor = 'bg-red-100'; textColor = 'text-red-800'; text = 'ปฏิเสธ'; break;
                case 'cancelled': bgColor = 'bg-gray-100'; textColor = 'text-gray-800'; text = 'ยกเลิก'; break;
                case 'closed': bgColor = 'bg-purple-100'; textColor = 'text-purple-800'; text = 'ปิดงาน (คืนครบ/ตัดยอด)'; break;
            }
            const safeText = text.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            return `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${bgColor} ${textColor}">${safeText}</span>`;
        }

        // AJAX Function to fetch transactions (used for polling)
        async function fetchTransactions(url, isPolling = false) {
            if (isFetching && isPolling) return;
            isFetching = true;
            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                if (!response.ok) {
                     console.error(`Fetch error: Server responded with status ${response.status}`);
                     isFetching = false; return;
                }
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    console.error(`Fetch error: Expected JSON response, but got ${contentType || 'no content type'}`);
                    const errorText = await response.text();
                    console.error("Response text (non-JSON):", errorText.substring(0, 500));
                    isFetching = false; return;
                }
                const data = await response.json();
                if (data.html && data.timestamp) {
                    if (isPolling) {
                        if (data.timestamp > currentLatestTimestamp) {
                            currentLatestTimestamp = data.timestamp;
                            tableBody.innerHTML = data.html;
                        }
                    } else {
                        currentLatestTimestamp = data.timestamp;
                        tableBody.innerHTML = data.html;
                        if (data.pagination) paginationWrapper.innerHTML = data.pagination;
                        else paginationWrapper.innerHTML = '';
                    }
                }
            } catch (error) {
                console.error('Error fetching transactions:', error);
                if (error instanceof SyntaxError) console.error("JSON Parsing Error: The server response was not valid JSON.");
            } finally {
                isFetching = false;
            }
        }

        // AJAX Polling function
        async function checkForUpdates() {
            if (isFetching) return;
            try {
                const response = await fetch('{{ route('ajax.transactions.latestTimestamp') }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                if (!response.ok) { console.error(`Timestamp check error: Server responded with status ${response.status}`); return; }
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    console.error(`Timestamp check error: Expected JSON, got ${contentType || 'none'}`);
                    const errorText = await response.text(); console.error("Response text (non-JSON):", errorText.substring(0, 500)); return;
                }
                let data;
                try { data = await response.json(); }
                catch (jsonError) { console.error("Timestamp check error: Failed to parse JSON.", jsonError); return; }
                if (data.latest_timestamp && data.latest_timestamp > currentLatestTimestamp) {
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.delete('page'); // Go to page 1
                    fetchTransactions(currentUrl.toString(), true); // Fetch new data (isPolling = true)
                }
            } catch (error) { console.error('Error checking for updates:', error); }
        }

        // Start polling if on the 'all_history' tab
        if ('{{ $statusFilter ?? "" }}' === 'all_history') {
            setInterval(checkForUpdates, 15000); // Check every 15 seconds
        }

    });
</script>
@endpush

