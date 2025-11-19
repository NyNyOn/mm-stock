    @extends('layouts.app')

    @section('header', '🔧 รายการซ่อมบำรุง')
    @section('subtitle', 'Maintenance')

    @section('content')
    <div class="p-8">
        {{-- Page Header --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">🔧 รายการซ่อมบำรุง</h1>
            <p class="mt-1 text-gray-500">รายการอุปกรณ์ทั้งหมดที่อยู่ในสถานะ "รอซ่อม"</p>
        </div>

        {{-- Main Content Card --}}
        <div class="p-6 soft-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-600">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="p-4 rounded-l-xl">#</th>
                            <th scope="col" class="p-4">รูปภาพ</th>
                            <th scope="col" class="p-4">ชื่ออุปกรณ์</th>
                            <th scope="col" class="p-4">Serial Number</th>
                            <th scope="col" class="p-4">ผู้แจ้งซ่อม</th>
                            <th scope="col" class="p-4" style="min-width: 250px;">รายละเอียดปัญหา</th>
                            <th scope="col" class="p-4">วันที่แจ้ง</th>
                            <th scope="col" class="p-4 text-center rounded-r-xl">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {{-- Use @forelse for cleaner empty state handling --}}
                        @forelse ($maintenanceLogs as $log)
                            <tr class="transition-colors hover:bg-gray-50/50">
                                <td class="p-4 font-medium">{{ $loop->iteration }}</td>
                                <td class="p-4">
                                    {{-- Image Logic using mainStockItem --}}
                                    @php
                                        $mainEquipment = optional($log->mainStockItem); 
                                        $displayImage = $mainEquipment->primaryImage->exists ? $mainEquipment->primaryImage : $mainEquipment->latestImage;
                                        $imageUrl = $displayImage->image_url; 
                                    @endphp
                                    <img src="{{ $imageUrl }}"
                                         alt="{{ $mainEquipment->name ?? 'N/A' }}"
                                         class="object-cover w-16 h-12 rounded-lg"
                                         onerror="this.onerror=null; this.src='https://placehold.co/100x100/e2e8f0/64748b?text=Error';">
                                </td>
                                {{-- Use main stock name if available --}}
                                <td class="p-4 font-bold text-gray-800">{{ $mainEquipment->name ?? ($log->equipment->name ?? 'N/A') }}</td>
                                {{-- Serial still comes from the temporary item --}}
                                <td class="p-4">{{ $log->equipment->serial_number ?? 'N/A' }}</td>
                                <td class="p-4">{{ $log->reportedBy->fullname ?? 'N/A' }}</td>
                                <td class="p-4">{{ $log->problem_description }}</td>
                                <td class="p-4 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                <td class="p-4">
                                    {{-- Action Form --}}
                                    <form class="flex items-center justify-center space-x-2"
                                          action="{{ route('maintenance.update', $log->id) }}"
                                          method="POST"
                                          onsubmit="confirmAction(event, this);">
                                        @csrf
                                        <button type="submit" name="action" value="complete_repair"
                                                class="px-4 py-2 text-xs font-bold text-white transition-transform bg-green-500 rounded-lg shadow-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-opacity-75 hover:scale-105">
                                            <i class="mr-1 fas fa-check"></i> ซ่อมเสร็จ
                                        </button>
                                        <button type="submit" name="action" value="write_off"
                                                class="px-4 py-2 text-xs font-bold text-white transition-transform bg-red-500 rounded-lg shadow-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-opacity-75 hover:scale-105">
                                            <i class="mr-1 fas fa-trash"></i> ตัดจำหน่าย
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        {{-- This is the @empty block that was missing --}}
                        @empty
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-500">
                                    ✨ ไม่มีรายการอุปกรณ์ที่รอซ่อมในขณะนี้ ✨
                                </td>
                            </tr>
                        @endforelse {{-- Ensure @endforelse is present --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- JavaScript Section for SweetAlert2 --}}
    @push('scripts')
    <script>
        // Function to handle the confirmation popup
        function confirmAction(event, form) {
            event.preventDefault(); // Stop the form from submitting immediately
            
            // Get the action value from the clicked button
            const action = event.submitter ? event.submitter.value : 'unknown';
            const actionText = action === 'complete_repair' ? 'ซ่อมเสร็จ' : 'ตัดจำหน่าย';
            const title = `ยืนยันการ "${actionText}"?`;
            const text = `คุณต้องการอัปเดตสถานะรายการนี้ใช่หรือไม่`;

            Swal.fire({
                title: title, text: text, icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#3085d6', cancelButtonColor: '#d33',
                confirmButtonText: 'ใช่, ยืนยัน!', cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Manually add the clicked button's value before submitting
                    if (!form.querySelector('input[name="action"]')) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden'; hiddenInput.name = 'action'; hiddenInput.value = action; 
                        form.appendChild(hiddenInput);
                    } else { form.querySelector('input[name="action"]').value = action; }
                    
                    form.submit(); // Submit if confirmed
                }
            });
        }

        // Check for session flash messages and show toasts
        @if (session('success'))
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success', title: '{{ session('success') }}',
                showConfirmButton: false, timer: 3500, timerProgressBar: true
            });
        @endif
        @if (session('error'))
            Swal.fire({
                toast: true, position: 'top-end', icon: 'error', title: '{{ session('error') }}',
                showConfirmButton: false, timer: 3500, timerProgressBar: true
            });
        @endif
    </script>
    @endpush
    @endsection
    

