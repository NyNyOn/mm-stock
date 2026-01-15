@extends('layouts.app')

@section('header', '🚫 รายการที่ถูกปฏิเสธ')
@section('subtitle', 'ตรวจสอบและแก้ไขรายการที่ถูกปฏิเสธจาก PU')

@section('content')
    <div class="py-6 w-full px-2 sm:px-6 bg-red-50/50 min-h-screen">
        
        {{-- Link Back to Active --}}
        <div class="mb-6">
            <a href="{{ route('purchase-track.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-indigo-600 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i> กลับไปดูรายการที่กำลังดำเนินการ
            </a>
        </div>

        @if($purchaseOrders->isEmpty())
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-12 text-center border-2 border-dashed border-gray-300">
                <div class="flex justify-center mb-4">
                    <div class="bg-green-50 p-4 rounded-full">
                        <i class="fas fa-check-circle text-4xl text-green-400"></i>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-gray-900">ไม่มีรายการที่ถูกปฏิเสธ</h3>
                <p class="text-gray-500 mt-1">เยี่ยมมาก! รายการสั่งซื้อของคุณราบรื่นดี</p>
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
                const container = document.getElementById('track-container');
                if (container) {
                    container.innerHTML = html;
                } else if (html.trim().length > 100) {
                     window.location.reload();
                }
            })
            .catch(error => console.error('Auto-update failed:', error));
        }

        document.addEventListener('DOMContentLoaded', () => {
             if (!document.hidden) {
                 autoRefreshInterval = setInterval(fetchUpdates, 15000); 
             }
             document.addEventListener('visibilitychange', () => {
                 if (document.hidden) {
                     clearInterval(autoRefreshInterval);
                 } else {
                     fetchUpdates();
                     autoRefreshInterval = setInterval(fetchUpdates, 15000);
                 }
             });
        });
    </script>
    @endpush
@endsection
