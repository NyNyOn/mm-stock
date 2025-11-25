@extends('layouts.app')
@section('header', '💻 จัดการอุปกรณ์')
@section('subtitle', 'รายการอุปกรณ์ทั้งหมดในระบบ พร้อมฟีเจอร์ค้นหาและกรองขั้นสูง')

@section('content')
<div id="equipment-page" class="page animate-slide-up-soft">
    {{-- Filter & Search Card --}}
    <div class="p-5 mb-6 soft-card rounded-2xl stat-card gentle-shadow">
        <form method="GET" action="{{ route('equipment.index') }}">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label class="block mb-2 text-sm font-medium text-gray-700">🔍 ค้นหา</label>
                    <input type="text" name="search" placeholder="ชื่อ, Part No., Serial..." value="{{ request('search') }}"
                           class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-transparent border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700">📂 ประเภท</label>
                    <select name="category" class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-transparent border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
                        <option value="">ทั้งหมด</option>
                        @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700">📍 สถานที่</label>
                    <select name="location" class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-transparent border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
                        <option value="">ทั้งหมด</option>
                         @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(request('location') == $loc->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700">⚡ สถานะ</label>
                    <select name="status" class="w-full px-4 py-3 text-sm font-medium text-gray-700 bg-transparent border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 gentle-shadow">
                        <option value="">ทั้งหมด</option>
                        <option value="available" @selected(request('status') == 'available')>✅ พร้อมใช้งาน</option>
                        <option value="in-use" @selected(request('status') == 'in-use')>👥 ถูกยืม/ใช้งานอยู่</option>
                        <option value="low_stock" @selected(request('status') == 'low_stock')>⚠️ สต็อกต่ำ</option>
                        <option value="out_of_stock" @selected(request('status') == 'out_of_stock')>⛔ สต๊อกหมด</option>
                        <option value="repairing" @selected(request('status') == 'repairing')>🛠️ ซ่อมบำรุง</option>
                        <option value="on-order" @selected(request('status') == 'on-order')>⏳ กำลังสั่งซื้อ</option>
                        <option value="inactive" @selected(request('status') == 'inactive')>⭕ ไม่ใช้งาน</option>
                        <option value="disposed" @selected(request('status') == 'disposed')>❌ จำหน่ายออก</option>
                        {{-- เพิ่มตัวเลือกกรอง Frozen --}}
                        <option value="frozen" @selected(request('status') == 'frozen')>❄️ ระงับ (Frozen)</option>
                    </select>
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="w-full px-6 py-3 text-sm font-medium text-green-700 transition-all bg-gradient-to-br from-green-100 to-green-200 rounded-xl hover:shadow-lg button-soft gentle-shadow">
                        <i class="mr-2 text-sm fas fa-search"></i>ค้นหา
                    </button>
                    <a href="{{ route('equipment.index') }}" class="px-4 py-3 text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200" title="ล้างตัวกรอง">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div></div>
        <a href="#" onclick="event.preventDefault(); window.showAddModal ? window.showAddModal() : alert('showAddModal function not found');" class="flex items-center px-4 py-3 text-sm font-medium text-white transition-all bg-gradient-to-br from-blue-400 to-purple-500 rounded-2xl hover:shadow-lg button-soft gentle-shadow">
            <i class="mr-2 text-sm fas fa-plus"></i><span>เพิ่มอุปกรณ์</span>
        </a>
    </div>

    <div class="overflow-hidden soft-card rounded-2xl gentle-shadow">
        {{-- ✅✅✅ START: 1. DESKTOP VIEW ✅✅✅ --}}
        <div class="hidden overflow-x-auto scrollbar-soft md:block">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-blue-50 to-purple-50">
                    <tr>
                        <th class="px-4 py-4 text-sm font-medium text-left text-gray-700">รูป</th>
                        <th class="px-4 py-4 text-sm font-medium text-left text-gray-700">
                            @php $directionForLink = ($sort === 'name' && $direction === 'asc') ? 'desc' : 'asc'; @endphp
                            <a href="{{ route('equipment.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => $directionForLink])) }}" class="flex items-center space-x-1 hover:text-blue-600 sort-link">
                                <span>ชื่ออุปกรณ์</span>
                                @if($sort === 'name') <i class="fas {{ $direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down' }}"></i>
                                @else <i class="fas fa-sort text-gray-300"></i> @endif
                            </a>
                        </th>
                        <th class="px-4 py-4 text-sm font-medium text-left text-gray-700">ประเภท</th>
                        <th class="px-4 py-4 text-sm font-medium text-left text-gray-700">
                             @php $directionForLink = ($sort === 'serial_number' && $direction === 'asc') ? 'desc' : 'asc'; @endphp
                            <a href="{{ route('equipment.index', array_merge(request()->query(), ['sort' => 'serial_number', 'direction' => $directionForLink])) }}" class="flex items-center space-x-1 hover:text-blue-600 sort-link">
                                <span>Part No. / Serial</span>
                                @if($sort === 'serial_number') <i class="fas {{ $direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down' }}"></i>
                                @else <i class="fas fa-sort text-gray-300"></i> @endif
                            </a>
                        </th>
                        <th class="px-4 py-4 text-sm font-medium text-center text-gray-700">
                             @php $directionForLink = ($sort === 'quantity' && $direction === 'asc') ? 'desc' : 'asc'; @endphp
                            <a href="{{ route('equipment.index', array_merge(request()->query(), ['sort' => 'quantity', 'direction' => $directionForLink])) }}" class="flex items-center justify-center space-x-1 hover:text-blue-600 sort-link">
                                <span>จำนวน</span>
                                @if($sort === 'quantity') <i class="fas {{ $direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down' }}"></i>
                                @else <i class="fas fa-sort text-gray-300"></i> @endif
                            </a>
                        </th>
                        <th class="px-4 py-4 text-sm font-medium text-left text-gray-700">สถานที่</th>
                        <th class="px-4 py-4 text-sm font-medium text-left text-gray-700">
                             @php $directionForLink = ($sort === 'status' && $direction === 'asc') ? 'desc' : 'asc'; @endphp
                            <a href="{{ route('equipment.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $directionForLink])) }}" class="flex items-center space-x-1 hover:text-blue-600 sort-link">
                                <span>สถานะ</span>
                                @if($sort === 'status') <i class="fas {{ $direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down' }}"></i>
                                @else <i class="fas fa-sort text-gray-300"></i> @endif
                            </a>
                        </th>
                        <th class="px-2 py-4 text-sm font-medium text-center text-gray-700">สั่งด่วน</th>
                        <th class="px-4 py-4 text-sm font-medium text-left text-gray-700">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($equipments as $item)
                        {{-- 🔥 คำนวณสถานะ Locked 🔥 --}}
                        @php
                            $isFrozen = strtolower($item->status) === 'frozen';
                            $canManage = Auth::user()->canBypassFrozenState(); // ใช้ฟังก์ชันใหม่ใน User Model
                            $shouldLock = $isFrozen && !$canManage;
                        @endphp

                    <tr class="table-row {{ $isFrozen ? 'bg-cyan-50/50' : '' }}">
                        <td class="px-4 py-4">
                            @php
                                $primaryImage = $item->images->firstWhere('is_primary', true) ?? $item->images->first();
                                $imageFileName = $primaryImage->file_name ?? null;
                                $imageUrl = $imageFileName
                                    ? route('nas.image', ['deptKey' => $defaultDeptKey, 'filename' => $imageFileName])
                                    : 'https://placehold.co/100x100/e2e8f0/64748b?text=No+Image';
                            @endphp
                            <img src="{{ $imageUrl }}" alt="{{ $item->name }}" class="object-cover w-12 h-12 rounded-lg shadow-md"
                                 onerror="this.onerror=null; this.src='https://placehold.co/100x100/e2e8f0/64748b?text=Error';" >
                        </td>
                        <td class="px-4 py-4">
                            <a href="#" onclick="event.preventDefault(); window.showDetailsModal ? showDetailsModal({{ $item->id }}) : alert('showDetailsModal function not found');" class="text-sm font-medium text-gray-800 hover:text-blue-600">{{ $item->name }}</a>
                        </td>
                        <td class="px-4 py-4"><span class="px-2 py-1 text-xs font-semibold text-purple-800 bg-purple-100 rounded-full">{{ $item->category->name ?? 'N/A' }}</span></td>

                        <td class="px-4 py-4">
                            <p class="font-mono text-xs text-gray-700">P/N: {{ $item->part_no ?? 'N/A' }}</p>
                            <p class="font-mono text-xs text-gray-500">S/N: {{ $item->serial_number ?? 'N/A' }}</p>
                        </td>

                        <td class="px-4 py-4 text-center"><span class="text-lg font-bold text-gray-800">{{ $item->quantity }}</span></td>

                        <td class="px-4 py-4 align-top">
                            <div class="w-48 whitespace-normal break-words">
                                <span class="text-sm text-gray-700">{{ $item->location->name ?? 'N/A' }}</span>
                            </div>
                        </td>

                        <td class="px-4 py-4"><x-status-badge :status="$item->status" /></td>
                        
                        {{-- ❄️ COLUMN: สั่งด่วน (ซ่อนถ้า Locked) --}}
                        <td class="px-2 py-4 text-center">
                            @if(!$shouldLock)
                                <form action="{{ route('purchase-orders.addItemToUrgent', $item->id) }}" method="POST"
                                      onsubmit="confirmAddItemToPo(event, this, 'ด่วน')"
                                      data-equipment-name="{{ e($item->name) }}">
                                    @csrf
                                    <button type="submit"
                                            class="w-8 h-8 text-red-600 transition-colors bg-red-100 rounded-lg hover:bg-red-500 hover:text-white"
                                            title="เพิ่มลงในใบสั่งซื้อด่วน">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                </form>
                            @else
                                <span class="text-gray-300" title="รายการถูกระงับ (Frozen)">-</span>
                            @endif
                        </td>

                        {{-- ❄️ COLUMN: จัดการ (ซ่อนถ้า Locked) --}}
                        <td class="px-4 py-4">
                            @if(!$shouldLock)
                                <div class="flex space-x-2">
                                    <form action="{{ route('purchase-orders.addItemToScheduled', $item->id) }}" method="POST"
                                          onsubmit="showQuantityModal(event, this)"
                                          data-equipment-name="{{ e($item->name) }}">
                                        @csrf
                                        <button type="submit"
                                                class="p-2 rounded-lg bg-blue-50 hover:bg-blue-100"
                                                title="เพิ่มลงในใบสั่งซื้อตามรอบ">
                                            <i class="text-blue-600 fas fa-shopping-cart"></i>
                                        </button>
                                    </form>
                                    <a href="#" onclick="event.preventDefault(); window.showEditModal ? showEditModal({{ $item->id }}) : alert('showEditModal function not found');" class="p-2 bg-gray-100 rounded-lg hover:bg-gray-200" title="แก้ไข"><i class="text-yellow-600 fas fa-edit"></i></a>
                                    <form action="{{ route('equipment.destroy', $item->id) }}" method="POST" class="delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="delete-button p-2 bg-gray-100 rounded-lg hover:bg-gray-200" title="ลบ" data-equipment-name="{{ e($item->name) }}"><i class="text-red-600 fas fa-trash"></i></button>
                                    </form>
                                </div>
                            @else
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-400 bg-gray-100 rounded-md cursor-not-allowed">
                                    <i class="mr-1 fas fa-lock"></i> Locked
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                        <tr><td colspan="10" class="p-8 text-center text-gray-500">ไม่พบข้อมูลอุปกรณ์</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{-- ✅✅✅ END: 1. DESKTOP VIEW ✅✅✅ --}}


        {{-- ✅✅✅ START: 2. MOBILE VIEW ✅✅✅ --}}
        <div class="block md:hidden divide-y divide-gray-100">
            @forelse ($equipments as $item)
                {{-- 🔥 คำนวณสถานะ Locked (Mobile) 🔥 --}}
                @php
                    $isFrozen = strtolower($item->status) === 'frozen';
                    $canManage = Auth::user()->canBypassFrozenState();
                    $shouldLock = $isFrozen && !$canManage;
                @endphp

                <div class="flex items-center p-4 space-x-4 {{ $isFrozen ? 'bg-cyan-50/50' : '' }}">
                    {{-- Bagian Gambar --}}
                    <div class="flex-shrink-0">
                        @php
                            $primaryImage = $item->images->firstWhere('is_primary', true) ?? $item->images->first();
                            $imageFileName = $primaryImage->file_name ?? null;
                            $imageUrl = $imageFileName
                                ? route('nas.image', ['deptKey' => $defaultDeptKey, 'filename' => $imageFileName])
                                : 'https://placehold.co/100x100/e2e8f0/64748b?text=No+Image';
                        @endphp
                        <img src="{{ $imageUrl }}" alt="{{ $item->name }}" class="object-cover w-16 h-16 rounded-lg shadow-md"
                             onerror="this.onerror=null; this.src='https://placehold.co/100x100/e2e8f0/64748b?text=Error';">
                    </div>

                    {{-- Bagian Info --}}
                    <div class="flex-grow min-w-0">
                        <a href="#" onclick="event.preventDefault(); window.showDetailsModal ? showDetailsModal({{ $item->id }}) : alert('showDetailsModal function not found');"
                           class="text-sm font-bold text-gray-800 hover:text-blue-600 truncate block">{{ $item->name }}</a>
                        <p class="text-xs text-gray-500 font-mono">{{ $item->serial_number ?? 'N/A' }}</p>
                        <div class="mt-2">
                             <x-status-badge :status="$item->status" />
                        </div>
                    </div>

                    {{-- Bagian Aksi & Jumlah --}}
                    <div class="flex flex-col items-end flex-shrink-0 space-y-2">
                         <span class="text-lg font-bold text-gray-800">{{ $item->quantity }}</span>
                         
                         {{-- ❄️ ซ่อนปุ่มแก้ไขถ้า Locked --}}
                         @if(!$shouldLock)
                             <a href="#" onclick="event.preventDefault(); window.showEditModal ? showEditModal({{ $item->id }}) : alert('showEditModal function not found');" class="p-2 bg-gray-100 rounded-lg hover:bg-gray-200" title="แก้ไข">
                                <i class="text-yellow-600 fas fa-edit"></i>
                             </a>
                         @else
                            <span class="text-gray-400" title="ถูกระงับ"><i class="fas fa-lock"></i></span>
                         @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">
                    ไม่พบข้อมูลอุปกรณ์
                </div>
            @endforelse
        </div>
        {{-- ✅✅✅ END: 2. MOBILE VIEW ✅✅✅ --}}


        {{-- Pagination (Shared) --}}
        <div class="p-5 border-t bg-gray-50">
            {{ $equipments->withQueryString()->links() }}
        </div>
    </div>
</div>

@include('partials.modals.add-equipment-modal')
@include('partials.modals.edit-equipment-modal')
@include('partials.modals.equipment-details')
@include('partials.modals.confirmation-modal')
@include('partials.modals.qr-code-modal')
@include('partials.modals.purchase-order-modal')

@push('scripts')
    <script src="{{ asset('js/equipment.js') }}"></script>

    <script>
        setTimeout(function() {
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
        }, 100);

        document.addEventListener('DOMContentLoaded', function() {
            const pageContainer = document.getElementById('equipment-page');
            
            if (pageContainer) {
                pageContainer.addEventListener('click', function(event) {
                    const deleteButton = event.target.closest('.delete-button');
                    
                    if (deleteButton) {
                        event.preventDefault();
                        let form = deleteButton.closest('form.delete-form');
                        let equipmentName = deleteButton.dataset.equipmentName || 'รายการนี้';

                        Swal.fire({
                            title: 'คุณแน่ใจใช่ไหม?',
                            html: `คุณต้องการลบ <b>${equipmentName}</b> ใช่หรือไม่?<br><span class='text-sm text-red-500'>การกระทำนี้ไม่สามารถย้อนกลับได้!</span>`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'ใช่, ลบเลย!',
                            cancelButtonText: 'ยกเลิก'
                        }).then((result) => {
                            if (result.isConfirmed && form) {
                                Swal.fire({ title: 'กำลังลบ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                                form.submit();
                            }
                        });
                    }
                });
            } else {
                console.error("Could not find page container '#equipment-page' for delete listener.");
            }
        });

        function confirmAddItemToPo(event, form, type) {
            event.preventDefault();
            const equipmentName = form.dataset.equipmentName;
            const poTypeName = type === 'ด่วน' ? 'ใบสั่งซื้อด่วน' : 'ใบสั่งซื้อตามรอบ';
            Swal.fire({
                title: `ยืนยันการเพิ่มรายการ`,
                html: `คุณต้องการเพิ่ม <b>${equipmentName}</b><br>ลงใน ${poTypeName} ใช่หรือไม่?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'ใช่, เพิ่มเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        function showQuantityModal(event, form) {
            event.preventDefault();
            const equipmentName = form.dataset.equipmentName;
            Swal.fire({
                title: `สั่งซื้อ (ตามรอบ): ${equipmentName}`,
                input: 'number',
                inputLabel: 'กรุณาระบุจำนวนที่ต้องการสั่งซื้อ',
                inputValue: 1,
                inputAttributes: { min: 1, step: 1 },
                showCancelButton: true,
                confirmButtonText: 'เพิ่มลงตะกร้า',
                cancelButtonText: 'ยกเลิก',
                inputValidator: (value) => {
                    if (!value || value < 1) { return 'กรุณาใส่จำนวนที่มากกว่า 0' }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const quantityInput = document.createElement('input');
                    quantityInput.type = 'hidden';
                    quantityInput.name = 'quantity';
                    quantityInput.value = result.value;
                    form.appendChild(quantityInput);
                    form.submit();
                }
            });
        }
    </script>
@endpush
@endsection