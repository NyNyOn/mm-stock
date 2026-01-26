@extends('layouts.app')

@section('header', '📝 เพิ่มอุปกรณ์ใหม่')
@section('subtitle', 'เพิ่มข้อมูลอุปกรณ์ใหม่เข้าสู่ระบบ')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="p-6 bg-white soft-card rounded-2xl gentle-shadow">
        
        <div class="mb-6 border-b border-gray-100 pb-4">
             <div class="flex items-center space-x-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-plus text-lg"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">แบบฟอร์มเพิ่มอุปกรณ์</h2>
                    <p class="text-sm text-gray-500">กรุณากรอกข้อมูลให้ครบถ้วนในแต่ละขั้นตอน</p>
                </div>
            </div>
        </div>

            @include('equipment.partials._form')
    </div>
</div>
@endsection

@push('scripts')
    {{-- Include the main equipment logic script --}}
    <script src="{{ asset('js/equipment.js') }}"></script>
    
    <script>
        // Ensure SweetAlert messages defined in controller are shown
        document.addEventListener('DOMContentLoaded', function() {
            @if (session('success'))
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: "{{ session('success') }}",
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            @endif

            @if (session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด!',
                    html: `{!! session('error') !!}`
                });
            @endif
        });
    </script>
@endpush
