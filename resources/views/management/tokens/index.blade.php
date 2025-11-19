@extends('layouts.app') 

@section('content')
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <h1 class="text-2xl font-semibold text-gray-900 mb-6">
            จัดการ API Token
        </h1>

        <div class="bg-white shadow sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    สร้าง Token ใหม่
                </h3>
                <div class="mt-2 max-w-xl text-sm text-gray-500">
                    <p>
                        สร้าง API Token สำหรับให้ระบบภายนอก (เช่น ระบบ PU) เข้าถึง API ของเรา
                        <span class="font-bold text-red-600">Token จะแสดงให้เห็นเพียงครั้งเดียวเท่านั้น</span>
                    </p>
                </div>
                
                <form id="create-token-form" class="mt-5 sm:flex sm:items-center">
                    @csrf
                    <div class="w-full sm:max-w-xs">
                        <label for="token_name" class="sr-only">ชื่อ Token</label>
                        <input type="text" name="token_name" id="token_name" required
                               class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                               placeholder="เช่น 'PU System Token'">
                    </div>
                    <button type="submit"
                            class="mt-3 w-full inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        สร้าง Token
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    API Tokens ที่มีอยู่
                </h3>

                <div class="flex flex-col">
                    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                            <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ชื่อ (Name)
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            สร้างเมื่อ (Created At)
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">จัดการ</span>
                                        </th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse ($tokens as $token) 
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{-- เปลี่ยนจากข้อความธรรมดาเป็นปุ่ม --}}
                                                <button type="button" class="text-indigo-600 hover:text-indigo-900 hover:underline view-token-details-btn"
                                                        data-token-id="{{ $token->id }}">
                                                    {{ $token->name }}
                                                </button>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $token->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                {{-- ปุ่มลบ (เหมือนเดิม) --}}
                                                <button type="button" class="text-red-600 hover:text-red-900 delete-token-btn"
                                                        data-token-id="{{ $token->id }}"
                                                        data-token-name="{{ $token->name }}">
                                                    Revoke
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                                ยังไม่มีการสร้าง API Token
                                            </td>
                                        </tr>
                                    @endforelse  {{-- แก้ไข Typo ตรงนี้ --}}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
@endsection

@push('scripts') 
<script>
    $(document).ready(function () {
        // ตรวจสอบว่ามี SweetAlert และ jQuery
        if (typeof Swal === 'undefined' || typeof $ === 'undefined') {
            console.error('jQuery หรือ SweetAlert2 ยังไม่ได้โหลด');
            alert('เกิดข้อผิดพลาดในการโหลดสคริปต์หน้าเว็บ');
            return;
        }

        const csrfToken = $('meta[name="csrf-token"]').attr('content'); 
        // (1) กำหนด URL พื้นฐานสำหรับ AJAX
        const baseUrl = '{{ url('management/tokens') }}'; 

        // --- (ส่วนที่ 1: โค้ดสำหรับสร้าง Token - เหมือนเดิม) ---
        $('#create-token-form').on('submit', function (e) {
            e.preventDefault();
            const tokenName = $('#token_name').val();
            if (!tokenName) {
                Swal.fire('ผิดพลาด', 'กรุณาระบุชื่อ Token', 'error');
                return;
            }
            
            $.ajax({
                url: '{{ route('management.tokens.store') }}', // ไปที่ Method 'store'
                method: 'POST',
                data: {
                    _token: csrfToken,
                    token_name: tokenName
                },
                success: function (response) {
                    Swal.fire({
                        title: 'สร้าง Token สำเร็จ!',
                        html: `
                            <p class="mb-4">นี่คือ Token ใหม่ของคุณ กรุณาคัดลอกและเก็บไว้ในที่ปลอดภัย ระบบจะไม่แสดง Token นี้อีก:</p>
                            <input type="text" readonly
                                   class="w-full bg-gray-100 border border-gray-300 rounded p-2 font-mono text-sm"
                                   value="${response.plainTextToken}"
                                   onclick="this.select(); document.execCommand('copy');"
                            >
                            <small class="text-gray-500">คลิกที่ช่องข้อความเพื่อคัดลอก</small>
                        `,
                        icon: 'success',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        confirmButtonText: 'รับทราบ (และรีโหลดหน้า)'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            location.reload(); 
                        }
                    });
                    $('#token_name').val(''); 
                },
                error: function (xhr) {
                    const errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'เกิดข้อผิดพลาดในการสร้าง Token';
                    Swal.fire('เกิดข้อผิดพลาด', errorMsg, 'error');
                }
            });
        });

        // --- (ส่วนที่ 2: โค้ดสำหรับลบ Token - เหมือนเดิม) ---
        $(document).on('click', '.delete-token-btn', function () {
            const tokenId = $(this).data('token-id');
            const tokenName = $(this).data('token-name');
            const deleteUrl = baseUrl + '/' + tokenId; // (เช่น /management/tokens/1)

            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: `คุณต้องการ Revoke (ลบ) Token ที่ชื่อ "${tokenName}" ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: deleteUrl, // ไปที่ Method 'destroy'
                        method: 'POST', 
                        data: {
                            _token: csrfToken,
                            _method: 'DELETE' // ใช้ Method Spoofing ของ Laravel
                        },
                        success: function (response) {
                            Swal.fire(
                                'ลบสำเร็จ!',
                                `Token "${tokenName}" ถูกลบแล้ว`,
                                'success'
                            ).then(() => {
                                location.reload(); 
                            });
                        },
                        error: function (xhr) {
                            const errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'เกิดข้อผิดพลาดในการลบ Token';
                            Swal.fire('เกิดข้อผิดพลาด', errorMsg, 'error');
                        }
                    });
                }
            });
        });


        // --- (ส่วนที่ 3: โค้ดสำหรับดูรายละเอียด) --- 
        $(document).on('click', '.view-token-details-btn', function () {
            const tokenId = $(this).data('token-id');
            // ✅✅✅ สร้าง URL ให้ถูกต้อง ✅✅✅
            const detailUrl = `${baseUrl}/${tokenId}`; // เช่น /management/tokens/123

            // ยิง AJAX (GET) ไปที่ Method 'show'
            $.ajax({
                url: detailUrl,
                method: 'GET', // ✅✅✅ ใช้ GET method ✅✅✅
                success: function(response) {
                    // จัดรูปแบบ Abilities (สิทธิ์) ให้อ่านง่าย
                    let abilitiesHtml = 'ไม่จำกัดสิทธิ์ (Wildcard *)';
                    if (response.abilities && response.abilities.length > 0 && response.abilities[0] !== '*') {
                         abilitiesHtml = response.abilities.map(ability => 
                            `<span class="inline-block bg-indigo-100 text-indigo-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded-full">${ability}</span>`
                        ).join(' ');
                    } else if (response.abilities && response.abilities.length === 0) {
                         abilitiesHtml = '<span class="text-gray-500">ไม่มีสิทธิ์ใดๆ</span>'; // กรณี []
                    }

                    // แสดงผลใน SweetAlert
                    Swal.fire({
                        title: `รายละเอียด Token: ${response.name}`,
                        html: `
                            <div class="text-left space-y-2 mt-4">
                                <p>
                                    <strong>สร้างเมื่อ:</strong>
                                    <span>${response.created_at}</span>
                                </p>
                                <p>
                                    <strong>ใช้งานล่าสุด:</strong>
                                    <span>${response.last_used_at}</span>
                                </p>
                                <div class="pt-2">
                                    <strong class="block mb-2">สิทธิ์ (Abilities):</strong>
                                    <div>${abilitiesHtml}</div>
                                </div>
                            </div>
                        `,
                        icon: 'info',
                        confirmButtonText: 'ปิด'
                    });
                },
                error: function(xhr) { // ✅ เพิ่มการแสดง Error Message จาก Controller
                    const errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'ไม่สามารถดึงรายละเอียด Token ได้';
                    Swal.fire('เกิดข้อผิดพลาด', errorMsg, 'error');
                }
            });
        });

    });
</script>
@endpush