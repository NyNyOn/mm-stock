@extends('layouts.app')

@section('header', 'การตั้งค่าระบบ')
@section('subtitle', 'จัดการการตั้งค่าทั่วไปของแอปพลิเคชัน')

@section('content')
<div class="container p-4 mx-auto space-y-6"> {{-- ✅ Add space-y-6 --}}
    <div class="max-w-2xl p-6 mx-auto soft-card rounded-2xl gentle-shadow">
        <h3 class="text-lg font-bold text-gray-800">หน้าตั้งค่า</h3>
        <p class="mt-2 text-gray-600">
            ส่วนนี้สำหรับจัดการการตั้งค่าทั่วไปของระบบ ซึ่งจะถูกพัฒนาในอนาคต
        </s:p>
        {{-- คุณสามารถเพิ่มฟอร์มการตั้งค่าต่างๆ ได้ที่นี่ --}}


    </div>

    @push('scripts')
    <script>
        document.getElementById('allow_user_return_toggle').addEventListener('change', function() {
            const isChecked = this.checked;
            const label = this.parentElement.querySelector('.status-label');
            label.textContent = isChecked ? 'กำลังบันทึก...' : 'กำลังบันทึก...';
            
            fetch("{{ route('settings.update.return-request') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ enabled: isChecked })
            })
            .then(response => response.json())
            .then(data => {
                label.textContent = isChecked ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: data.message,
                    showConfirmButton: false,
                    timer: 3000
                });
            })
            .catch(error => {
                console.error('Error:', error);
                label.textContent = 'Error';
                this.checked = !isChecked; // Revert
                Swal.fire('Error', 'เกิดข้อผิดพลาดในการบันทึก', 'error');
            });
        });
    </script>
    @endpush







    {{-- ✅✅✅ START: Add Maintenance Mode Section ✅✅✅ --}}
    @can('maintenance:mode') {{-- Check for the new permission --}}
    <div class="max-w-2xl p-6 mx-auto soft-card rounded-2xl gentle-shadow">
        <h3 class="text-xl font-bold text-gray-800">🛠️ โหมดปิดปรับปรุงระบบ</h3>
        <p class="mt-1 text-sm text-gray-500">เปิดโหมดนี้เมื่อต้องการปิดการเข้าถึงระบบชั่วคราวเพื่อทำการแก้ไขหรืออัปเดต</p>

        @php
            $isDown = \App\Http\Controllers\MaintenanceController::isDownForMaintenance();
        @endphp

        <div class="mt-4">
            @if($isDown)
                <div class="flex items-center p-3 mb-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg border border-yellow-200" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span>ขณะนี้ระบบอยู่ในโหมดปิดปรับปรุง</span>
                </div>

                {{-- (ส่วนแสดง Link Bypass - เหมือนเดิม) --}}
                @if (session('maintenance_secret'))
                    <div class="p-4 mb-4 text-sm text-blue-700 bg-blue-100 rounded-lg border border-blue-200" role="alert">
                        <strong class="font-bold">ลิงก์สำหรับ Bypass:</strong>
                        <p class="mt-1">คุณสามารถเข้าถึงเว็บไซต์ได้ผ่านลิงก์นี้ (คัดลอกและเปิดในแท็บใหม่):</p>
                        
                        <div class="flex mt-2">
                            <input type="text" class="flex-grow block w-full px-3 py-2 text-sm text-gray-700 bg-gray-50 border border-gray-300 rounded-l-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                   id="bypass-link" 
                                   value="{{ url('/' . session('maintenance_secret')) }}" 
                                   readonly>
                            <button type="button" 
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-l-0 border-gray-300 rounded-r-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                                    id="copy-bypass-link">
                                <i class="fas fa-clipboard mr-2" id="copy-icon"></i>
                                <span id="copy-text">คัดลอก</span>
                            </button>
                        </div>
                        <small class="block mt-2 text-gray-600">
                            เมื่อคุณเข้าลิงก์นี้ เบราว์เซอร์จะจดจำคุกกี้ไว้ ทำให้คุณเข้าเว็บได้ตามปกติ
                        </small>
                    </div>
                @endif
                {{-- (สิ้นสุดส่วน Link Bypass) --}}

                {{-- Form ปิดโหมด (เหมือนเดิม) --}}
                <form action="{{ route('maintenance.disable') }}" method="POST" id="disable-maintenance-form">
                    @csrf
                    <button type="button"
                            id="disable-maintenance-button"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-power-off mr-2"></i>
                        ปิดโหมดปรับปรุง
                    </button>
                </form>
            @else
                 <div class="flex items-center p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg border border-green-200" role="alert">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>ขณะนี้ระบบเปิดให้บริการตามปกติ</span>
                </div>
                
                {{-- ✅ 1. แก้ไข Form เปิดโหมด --}}
                <form action="{{ route('maintenance.enable') }}" method="POST" id="enable-maintenance-form">
                    @csrf
                    {{-- 1.1 เพิ่ม Input ลับสำหรับเก็บ Secret Key --}}
                    <input type="hidden" name="secret" id="maintenance-secret-input">
                    
                    {{-- 1.2 ปุ่มนี้จะไปเรียก JavaScript ก่อน --}}
                    <button type="button"
                            id="enable-maintenance-button"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        เปิดโหมดปรับปรุง
                    </button>
                </form>
            @endif
        </div>
    </div>
    @endcan
    {{-- ✅✅✅ END: Add Maintenance Mode Section ✅✅✅ --}}

</div>
@endsection


{{-- (ส่วน Script คัดลอก - เหมือนเดิม) --}}
@if (session('maintenance_secret'))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const copyButton = document.getElementById('copy-bypass-link');
            const bypassLinkInput = document.getElementById('bypass-link');
            const copyText = document.getElementById('copy-text');
            const copyIcon = document.getElementById('copy-icon');
            if (copyButton && bypassLinkInput) {
                copyButton.addEventListener('click', function() {
                    bypassLinkInput.select();
                    bypassLinkInput.setSelectionRange(0, 99999);
                    try {
                        navigator.clipboard.writeText(bypassLinkInput.value).then(() => {
                            copyText.textContent = 'คัดลอกแล้ว!';
                            copyIcon.className = 'fas fa-check mr-2';
                            setTimeout(() => {
                                copyText.textContent = 'คัดลอก';
                                copyIcon.className = 'fas fa-clipboard mr-2';
                            }, 2000);
                        }).catch(err => {
                            console.error('ไม่สามารถคัดลอกอัตโนมัติได้: ', err);
                            document.execCommand('copy'); // Fallback
                        });
                    } catch (err) {
                        console.error('ไม่รองรับ Clipboard API: ', err);
                    }
                });
            }
        });
    </script>
    @endpush
@endif
{{-- (สิ้นสุด Script คัดลอก) --}}


{{-- ✅✅✅ START: อัปเดต JavaScript สำหรับ Popup (ฉบับ 2-ขั้นตอน) ✅✅✅ --}}
@push('scripts')
{{-- 1. โหลด Library SweetAlert2 --}}
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {


        // 2. ตัวจัดการปุ่ม "เปิดโหมดปรับปรุง" (Logic ใหม่)
        const enableBtn = document.getElementById('enable-maintenance-button');
        const enableForm = document.getElementById('enable-maintenance-form');
        const secretInput = document.getElementById('maintenance-secret-input');

        if (enableBtn && enableForm && secretInput) {
            enableBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // ขั้นตอนที่ 1: ไปขอ Secret Key จาก Server
                fetch("{{ route('maintenance.prepare') }}") // <-- ยิงไป Route ใหม่
                    .then(response => response.json())
                    .then(data => {
                        const newSecret = data.secret;
                        if (!newSecret) {
                            throw new Error('ไม่ได้รับ Secret Key');
                        }

                        // ขั้นตอนที่ 2: แสดง Popup พร้อม Key ที่ได้รับ
                        Swal.fire({
                            title: 'ขั้นตอนที่ 1: รับ Secret Key',
                            icon: 'info',
                            html: `
                                <p class="text-left">เราได้สร้าง Secret Key สำหรับคุณแล้ว กรุณาคัดลอกและเก็บไว้ในที่ปลอดภัย <b>ก่อนกดยืนยัน</b></p>
                                <input type="text" value="${newSecret}" class="w-full p-2 mt-2 font-mono text-sm text-gray-700 bg-gray-100 border border-gray-300 rounded-md" readonly>
                                <p class="mt-4 text-left text-red-600 font-bold">เมื่อคุณกดยืนยัน เว็บไซต์จะเข้าสู่โหมดปิดปรับปรุงทันที!</p>
                            `,
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'ขั้นตอนที่ 2: ยืนยันและเปิดโหมด',
                            cancelButtonText: 'ยกเลิก'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // ขั้นตอนที่ 3: ถ้ากดยืนยัน...
                                // 1. ยัด Key เข้าไปใน Form
                                secretInput.value = newSecret;
                                // 2. Submit Form
                                enableForm.submit();
                            }
                        });
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถสร้าง Secret Key ได้ กรุณาลองใหม่', 'error');
                    });
            });
        }

        // 3. ตัวจัดการปุ่ม "ปิดโหมดปรับปรุง" (Logic นี้เหมือนเดิม)
        const disableBtn = document.getElementById('disable-maintenance-button');
        const disableForm = document.getElementById('disable-maintenance-form');

        if (disableBtn && disableForm) {
            disableBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'ยืนยันการปิดโหมดปรับปรุง?',
                    text: "ระบบจะกลับมาเปิดให้บริการตามปกติ",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ใช่, เปิดใช้งานเว็บ',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        disableForm.submit();
                    }
                });
            });
        }

    });
</script>
@endpush
{{-- ✅✅✅ END: สิ้นสุด JavaScript Popup ✅✅✅ --}}

