@extends('layouts.app')

@section('header', '📦 ติดตามสถานะการจัดซื้อ')
@section('subtitle', 'ตรวจสอบสถานะรายการสั่งซื้อจาก PU')

@section('content')
    <div class="py-6 w-full px-2 sm:px-6 bg-gray-100 min-h-screen">

        {{-- Navigate Tabs --}}
        {{-- Navigate / Links --}}
        {{-- Navigate / Links & Search --}}
        <div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
            {{-- Search Form --}}
            <form action="{{ url()->current() }}" method="GET" class="w-full md:w-1/2 flex gap-2">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}" 
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm" 
                           placeholder="ค้นหาเลข PO, PR หรือชื่อสินค้า...">
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 shadow-sm transition text-sm font-medium">
                    ค้นหา
                </button>
                @if(request('search'))
                    <a href="{{ url()->current() }}" class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">
                        ล้าง
                    </a>
                @endif
            </form>
            
            <a href="{{ route('purchase-track.rejected') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-red-600 transition-colors shadow-sm whitespace-nowrap">
                <i class="fas fa-history mr-2"></i> ดูประวัติรายการที่ถูกปฏิเสธ (Rejected History)
            </a>
        </div>

        @if($purchaseOrders->isEmpty())

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-12 text-center border-2 border-dashed border-gray-300">
                <div class="flex justify-center mb-4">
                    <div class="bg-gray-50 p-4 rounded-full">
                        <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-gray-900">ยังไม่มีประวัติการสั่งซื้อ</h3>
                <p class="text-gray-500 mt-1">รายการสั่งซื้อที่คุณส่งไปให้ PU จะปรากฏที่นี่</p>
            </div>
        @else
            <div id="track-container" class="space-y-8 pb-20">
                @include('purchase-track.partials._list')
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        let autoRefreshInterval;

        window.fetchUpdates = function() {
            // Check if user is scrolling or interacting? Maybe not necessary for simple list.
            
            // Current URL (maintains pagination page=2 etc.)
            const url = window.location.href;

            // 1. Capture Expanded State
            const expandedDetails = Array.from(document.querySelectorAll('[id^="card-"]'))
                .filter(el => !el.classList.contains('hidden'))
                .map(el => el.id);

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
            .then(response => {
                if (response.redirected && response.url.includes('/login')) {
                    window.location.reload();
                    return Promise.reject('Session expired');
                }
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text();
            })
            .then(html => {
                const container = document.getElementById('track-container');
                if (container) {
                    container.innerHTML = html;
                    
                    // 2. Restore Expanded State
                    expandedDetails.forEach(id => {
                        const card = document.getElementById(id);
                        const summaryId = id.replace('card-', 'summary-');
                        const summary = document.getElementById(summaryId);
                        
                        if (card) {
                            card.classList.remove('hidden');
                            if (summary) summary.classList.add('hidden');
                        }
                    });
                    
                    console.log('✅ Auto-updated tracking list safely.');
                } else {
                    // Case: User was on empty state, now data came? 
                    // Need to reload full page if structure changed from Empty -> List.
                    if (html.trim().length > 100 && !document.getElementById('track-container')) {
                        window.location.reload(); 
                    }
                }
            })
            .catch(error => console.error('Auto-update failed:', error));
        }

        window.togglePo = function(id) {
            const summary = document.getElementById('summary-' + id);
            const card = document.getElementById('card-' + id);
            
            if (card.classList.contains('hidden')) {
                // Expand
                card.classList.remove('hidden');
                if(summary) summary.classList.add('hidden');
            } else {
                // Collapse
                card.classList.add('hidden');
                if(summary) summary.classList.remove('hidden');
            }
        }

        // Start Polling every 15 seconds
        document.addEventListener('DOMContentLoaded', () => {
             // Only run if tab is visible to save resources
             if (!document.hidden) {
                 autoRefreshInterval = setInterval(window.fetchUpdates, 15000); // 15s
             }

             document.addEventListener('visibilitychange', () => {
                 if (document.hidden) {
                     clearInterval(autoRefreshInterval);
                 } else {
                     window.fetchUpdates(); // Fetch immediately on return
                     autoRefreshInterval = setInterval(window.fetchUpdates, 15000);
                 }
             });
        });
    </script>
    @endpush
@endsection