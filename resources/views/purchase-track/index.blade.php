@extends('layouts.app')

@section('header', '📦 ติดตามสถานะการจัดซื้อ')
@section('subtitle', 'ตรวจสอบสถานะรายการสั่งซื้อจาก PU')

@section('content')
    <div class="py-6 w-full px-2 sm:px-6 bg-gray-100 min-h-screen">

        {{-- Navigate Tabs --}}
        {{-- Navigate / Links --}}
        <div class="mb-6 flex justify-end">
            <a href="{{ route('purchase-track.rejected') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-red-600 transition-colors shadow-sm">
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

        function fetchUpdates() {
            // Check if user is scrolling or interacting? Maybe not necessary for simple list.
            
            // Current URL (maintains pagination page=2 etc.)
            const url = window.location.href;

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text();
            })
            .then(html => {
                // Check if content changed? For now just replace.
                // Or better: Use a hidden version hash? 
                // Replacing innerHTML of container
                const container = document.getElementById('track-container');
                if (container) {
                    // Simple DOM Diffing or just replace? 
                    // Replace is easiest but resets scroll if inside container.
                    // Since container is full page height, scroll is on 'window'.
                    // Replacing content inside shouldn't reset window scroll unless content height changes DRASTICALLY.
                    container.innerHTML = html;
                    console.log('✅ Auto-updated tracking list.');
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

        // Start Polling every 15 seconds
        document.addEventListener('DOMContentLoaded', () => {
             // Only run if tab is visible to save resources
             if (!document.hidden) {
                 autoRefreshInterval = setInterval(fetchUpdates, 15000); // 15s
             }

             document.addEventListener('visibilitychange', () => {
                 if (document.hidden) {
                     clearInterval(autoRefreshInterval);
                 } else {
                     fetchUpdates(); // Fetch immediately on return
                     autoRefreshInterval = setInterval(fetchUpdates, 15000);
                 }
             });
        });
    </script>
    @endpush
@endsection