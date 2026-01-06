@extends('layouts.app')

@section('header', 'ผู้ดูแลระบบ')
@section('subtitle', 'จัดการผู้ใช้งานและสิทธิ์การเข้าถึงระบบอย่างมีประสิทธิภาพ')

@section('content')
<div class="container px-4 mx-auto">

    {{-- Session Messages --}}
    @if (session('success'))
        <div class="p-4 mb-6 text-green-800 bg-green-100 border-l-4 border-green-500 rounded-r-lg animate-fade-in" role="alert">
            <p class="font-bold">สำเร็จ!</p>
            <p>{{ session('success') }}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="p-4 mb-6 text-red-800 bg-red-100 border-l-4 border-red-500 rounded-r-lg animate-fade-in" role="alert">
            <p class="font-bold">เกิดข้อผิดพลาด!</p>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    {{-- Stats Dashboard --}}
    @php
        // Group Users Logic
        $itUsers = $users->filter(fn($u) => $u->serviceUserRole && $u->serviceUserRole->userGroup && (str_contains(strtolower($u->serviceUserRole->userGroup->name), 'it') || str_contains(strtolower($u->serviceUserRole->userGroup->name), 'support')));
        $adminUsers = $users->filter(fn($u) => $u->serviceUserRole && $u->serviceUserRole->userGroup && str_contains(strtolower($u->serviceUserRole->userGroup->name), 'admin') && !$itUsers->contains($u));
        $standardUsers = $users->filter(fn($u) => $u->serviceUserRole && $u->serviceUserRole->userGroup && str_contains(strtolower($u->serviceUserRole->userGroup->name), 'user') && !$itUsers->contains($u) && !$adminUsers->contains($u));
        $generalUsers = $users->diff($itUsers)->diff($adminUsers)->diff($standardUsers);
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        {{-- Card 1: IT Support --}}
        <div class="p-4 bg-white rounded-2xl shadow-sm border border-red-100 relative group overflow-hidden">
            <div class="absolute right-0 top-0 p-3 opacity-10">
                <i class="fas fa-tools text-6xl text-red-500 transform rotate-12"></i>
            </div>
            <div class="relative z-10">
                <div class="flex items-center space-x-2 text-red-700 font-bold mb-1">
                    <i class="fas fa-user-astronaut"></i> <span>IT Support</span>
                </div>
                <div class="text-3xl font-black text-gray-800">{{ $itUsers->count() }} <span class="text-sm font-medium text-gray-500">คน</span></div>
                <button onclick="document.getElementById('list-it').classList.toggle('hidden')" class="text-xs text-red-500 underline mt-2 hover:text-red-700">ดูรายชื่อ</button>
                <div id="list-it" class="hidden mt-2 p-2 bg-red-50 rounded-lg text-xs text-red-800 space-y-1">
                    @forelse($itUsers as $u)
                        <div class="truncate">• {{ $u->fullname }}</div>
                    @empty
                        <div>- ไม่มี -</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Card 2: Administrators --}}
        <div class="p-4 bg-white rounded-2xl shadow-sm border border-blue-100 relative group overflow-hidden">
             <div class="absolute right-0 top-0 p-3 opacity-10">
                <i class="fas fa-shield-alt text-6xl text-blue-500 transform -rotate-12"></i>
            </div>
             <div class="relative z-10">
                <div class="flex items-center space-x-2 text-blue-600 font-bold mb-1">
                    <i class="fas fa-user-shield"></i> <span>ผู้ดูแล (Admin)</span>
                </div>
                <div class="text-3xl font-black text-gray-800">{{ $adminUsers->count() }} <span class="text-sm font-medium text-gray-500">คน</span></div>
                 <button onclick="document.getElementById('list-admin').classList.toggle('hidden')" class="text-xs text-blue-500 underline mt-2 hover:text-blue-700">ดูรายชื่อ</button>
                <div id="list-admin" class="hidden mt-2 p-2 bg-blue-50 rounded-lg text-xs text-blue-800 space-y-1">
                     @forelse($adminUsers as $u)
                        <div class="truncate">• {{ $u->fullname }}</div>
                    @empty
                        <div>- ไม่มี -</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Card 3: Standard Users --}}
        <div class="p-4 bg-white rounded-2xl shadow-sm border border-indigo-100 relative group overflow-hidden">
             <div class="absolute right-0 top-0 p-3 opacity-10">
                <i class="fas fa-users text-6xl text-indigo-500"></i>
            </div>
             <div class="relative z-10">
                <div class="flex items-center space-x-2 text-indigo-600 font-bold mb-1">
                    <i class="fas fa-user-check"></i> <span>ผู้ใช้งาน (User)</span>
                </div>
                <div class="text-3xl font-black text-gray-800">{{ $standardUsers->count() }} <span class="text-sm font-medium text-gray-500">คน</span></div>
                <p class="text-xs text-gray-400 mt-2">เข้าถึงฟังก์ชันพื้นฐาน</p>
            </div>
        </div>

         {{-- Card 4: General --}}
        <div class="p-4 bg-white rounded-2xl shadow-sm border border-green-100 relative group overflow-hidden">
             <div class="absolute right-0 top-0 p-3 opacity-10">
                <i class="fas fa-seedling text-6xl text-green-500"></i>
            </div>
             <div class="relative z-10">
                <div class="flex items-center space-x-2 text-green-600 font-bold mb-1">
                    <i class="fas fa-user"></i> <span>ทั่วไป (General)</span>
                </div>
                <div class="text-3xl font-black text-gray-800">{{ $generalUsers->count() }} <span class="text-sm font-medium text-gray-500">คน</span></div>
                <p class="text-xs text-gray-400 mt-2">ไม่มีสิทธิ์พิเศษ</p>
            </div>
        </div>
    </div>

    <div class="p-6 soft-card rounded-2xl gentle-shadow">
        <div class="flex flex-wrap items-center justify-between pb-5 mb-6 border-b">
            <h2 class="text-xl font-bold text-gray-800">รายชื่อผู้ใช้งานทั้งหมด</h2>
            <form method="GET" action="{{ route('management.users.index') }}" class="flex w-full mt-4 md:w-auto md:mt-0">
                <input type="text" name="search" placeholder="ค้นหาด้วยชื่อหรือ username..."
                       value="{{ request('search') }}"
                       class="w-full px-4 py-2 text-sm border-gray-200 rounded-l-lg soft-card focus:ring-blue-500 focus:border-blue-500">
                <button type="submit" class="px-4 py-2 text-sm text-white transition duration-150 ease-in-out bg-blue-500 rounded-r-lg hover:bg-blue-600">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>


        {{-- Desktop Table Container --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-sm bg-white">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-xs font-bold tracking-wider text-left text-gray-600 uppercase">ชื่อ - นามสกุล</th>
                        <th class="px-6 py-3 text-xs font-bold tracking-wider text-left text-gray-600 uppercase">Username</th>
                        <th class="px-6 py-3 text-xs font-bold tracking-wider text-left text-gray-600 uppercase">กลุ่ม / สถานะ</th>
                        <th class="px-6 py-3 text-xs font-bold tracking-wider text-center text-gray-600 uppercase">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($users as $user)
                    <tr class="transition-colors border-b last:border-b-0 cursor-pointer group hover:bg-gray-50 relative" 
                        onclick="toggleRow(this, '{{ $user->id }}')" 
                        id="user-row-{{ $user->id }}"
                        data-can-manage="{{ Auth::user()->can('manage-user-role', $user) }}">
                        
                        @php
                            // Default Style (General)
                            $badgeClass = 'text-green-700 bg-green-50'; 
                            $nameClass = 'text-gray-900';
                            $roleName = 'ผู้ใช้ทั่วไป';

                            if($user->serviceUserRole && $user->serviceUserRole->userGroup) {
                                $groupName = strtolower($user->serviceUserRole->userGroup->name);
                                $roleName = $user->serviceUserRole->userGroup->name;

                                // Logic 1: ID 9 OR IT -> Red Name & Badge
                                if ($user->id == 9 || str_contains($groupName, 'it') || str_contains($groupName, 'support')) {
                                    $badgeClass = 'text-red-700 bg-red-50 ring-1 ring-red-100';
                                    $nameClass = 'text-red-700 font-bold';
                                }
                                // Logic 2: Admin OR User -> Bright Blue Name & Badge
                                elseif (str_contains($groupName, 'admin') || str_contains($groupName, 'user')) {
                                    $badgeClass = 'text-blue-600 bg-blue-50 ring-1 ring-blue-100'; // Bright Blue
                                    $nameClass = 'text-blue-600 font-bold';
                                }
                            }
                        @endphp

                        {{-- Hidden Checkbox for Logic --}}
                        <input type="checkbox" name="selected_users[]" value="{{ $user->id }}" id="cb-{{ $user->id }}" class="hidden user-checkbox">

                        <td class="px-6 py-4 whitespace-nowrap relative">
                            {{-- Selection Indicator Strip --}}
                            <div id="ind-{{ $user->id }}" class="absolute left-0 top-0 bottom-0 w-1 bg-blue-500 rounded-r opacity-0 transition-opacity"></div>
                            
                            <div class="flex items-center space-x-3">
                                {{-- Checkmark Icon (Hidden by default, shows when selected) --}}
                                <div id="check-icon-{{ $user->id }}" class="w-6 h-6 rounded-full bg-blue-500 text-white flex items-center justify-center opacity-0 scale-90 transition-all absolute -left-2 transform shadow-md" style="display: none;">
                                    <i class="fas fa-check text-xs"></i>
                                </div>
                                <div class="text-sm font-medium {{ $nameClass }} pl-2">{{ $user->fullname }}</div>
                            </div>
                        </td>
                         <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-600">{{ $user->username }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 text-xs font-semibold leading-5 rounded-full {{ $badgeClass }}">
                                {{ $roleName }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-center whitespace-nowrap">
                            @can('user:manage')
                                {{-- ✅ NEW HIERARCHY CHECK: Use the 'manage-user-role' gate --}}
                                @can('manage-user-role', $user)
                                    @if($user->serviceUserRole && $user->serviceUserRole->userGroup)
                                        {{-- Buttons are only visible if the current user can manage the target user --}}
                                    <button
                                        onclick="event.stopPropagation(); openAssignModal('{{ $user->id }}', '{{ $user->fullname }}', '{{ $user->serviceUserRole->group_id }}', '{{ $user->serviceUserRole->userGroup->name }}')"
                                        class="px-3 py-1 text-xs font-bold text-yellow-700 transition duration-150 ease-in-out bg-yellow-100 rounded-lg hover:bg-yellow-200"
                                        title="เปลี่ยนกลุ่มผู้ใช้งาน">
                                        <i class="mr-1 fas fa-exchange-alt"></i> เปลี่ยนกลุ่ม
                                    </button>

                                    <form action="{{ route('management.users.removeGroup', $user->id) }}" method="POST" class="inline-block ml-2">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                onclick="event.stopPropagation()"
                                                class="px-3 py-1 text-xs font-bold text-red-700 transition duration-150 ease-in-out bg-red-100 rounded-lg hover:bg-red-200"
                                                title="นำผู้ใช้ออกจากกลุ่ม">
                                            <i class="mr-1 fas fa-user-minus"></i> นำออก
                                        </button>
                                    </form>
                                    @else
                                        {{-- Assign Group Button for users with no group --}}
                                        <button
                                            onclick="event.stopPropagation(); openAssignModal('{{ $user->id }}', '{{ $user->fullname }}', '', '')"
                                            class="px-3 py-1 text-xs font-bold text-green-700 transition duration-150 ease-in-out bg-green-100 rounded-lg hover:bg-green-200"
                                            title="กำหนดกลุ่มผู้ใช้งาน">
                                           <i class="mr-1 fas fa-user-plus"></i> กำหนดกลุ่ม
                                        </button>
                                    @endif
                                @else
                                    {{-- If the current user CANNOT manage the target user, show a dash --}}
                                    <span class="px-3 py-1 text-xs font-semibold leading-5 text-gray-800 bg-gray-100 rounded-full">-</span>
                                @endcan
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-10 text-center text-gray-500">
                            <i class="mb-3 text-4xl fas fa-users-slash"></i>
                            <p class="text-lg">ไม่พบข้อมูลผู้ใช้งาน</p>
                            <p class="mt-1 text-sm">ลองใช้คำค้นหาอื่น หรือเพิ่มผู้ใช้งานใหม่</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Card List Container --}}
        <div class="space-y-4 md:hidden">
            @forelse($users as $user)
            <div class="p-5 bg-white border border-gray-100 shadow-sm rounded-2xl">
                @php
                    // Default Style (General)
                    $badgeClass = 'text-green-700 bg-green-50'; 
                    $nameClass = 'text-gray-900';
                    $roleName = 'ผู้ใช้ทั่วไป';

                    if($user->serviceUserRole && $user->serviceUserRole->userGroup) {
                        $groupName = strtolower($user->serviceUserRole->userGroup->name);
                        $roleName = $user->serviceUserRole->userGroup->name;

                        // Logic 1: ID 9 OR IT -> Red Name & Badge
                        if ($user->id == 9 || str_contains($groupName, 'it') || str_contains($groupName, 'support')) {
                            $badgeClass = 'text-red-700 bg-red-50 ring-1 ring-red-100';
                            $nameClass = 'text-red-700 font-bold';
                        }
                        // Logic 2: Admin OR User -> Bright Blue Name & Badge
                        elseif (str_contains($groupName, 'admin') || str_contains($groupName, 'user')) {
                            $badgeClass = 'text-blue-600 bg-blue-50 ring-1 ring-blue-100'; // Bright Blue
                            $nameClass = 'text-blue-600 font-bold';
                        }
                    }
                @endphp

                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center justify-center w-10 h-10 text-lg font-bold text-indigo-600 bg-indigo-100 rounded-full">
                            {{ substr($user->fullname, 0, 1) }}
                        </div>
                        <div>
                            <div class="text-base font-bold {{ $nameClass }}">{{ $user->fullname }}</div>
                            <div class="text-sm text-gray-500">{{ $user->username }}</div>
                        </div>
                    </div>
                     <div>
                        <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-lg {{ $badgeClass }}">
                            {{ $roleName }}
                        </span>
                    </div>
                </div>

                <div class="pt-3 mt-3 border-t border-gray-100">
                    <div class="flex flex-col gap-2">
                         @can('user:manage')
                            @can('manage-user-role', $user)
                                @if($user->serviceUserRole && $user->serviceUserRole->userGroup)
                                    <div class="grid grid-cols-2 gap-3">
                                        <button
                                            onclick="openAssignModal('{{ $user->id }}', '{{ $user->fullname }}', '{{ $user->serviceUserRole->group_id }}', '{{ $user->serviceUserRole->userGroup->name }}')"
                                            class="flex items-center justify-center w-full px-4 py-2 text-sm font-bold text-yellow-700 bg-yellow-100 rounded-xl hover:bg-yellow-200">
                                            <i class="mr-2 fas fa-exchange-alt"></i> เปลี่ยนกลุ่ม
                                        </button>

                                        <form action="{{ route('management.users.removeGroup', $user->id) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="flex items-center justify-center w-full px-4 py-2 text-sm font-bold text-red-700 bg-red-100 rounded-xl hover:bg-red-200">
                                                <i class="mr-2 fas fa-user-minus"></i> นำออก
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <button
                                        onclick="openAssignModal('{{ $user->id }}', '{{ $user->fullname }}', '', '')"
                                        class="flex items-center justify-center w-full px-4 py-2 text-sm font-bold text-green-700 bg-green-100 rounded-xl hover:bg-green-200">
                                       <i class="mr-2 fas fa-user-plus"></i> กำหนดกลุ่ม
                                    </button>
                                @endif
                            @else
                                <div class="p-2 text-xs text-center text-gray-400 bg-gray-50 rounded-xl">
                                    <i class="fas fa-lock mr-1"></i> ไม่มีสิทธิ์จัดการผู้ใช้นี้
                                </div>
                            @endcan
                        @endcan
                    </div>
                </div>
            </div>
            @empty
            <div class="p-8 text-center bg-gray-50 rounded-2xl">
                 <i class="mb-3 text-4xl text-gray-400 fas fa-users-slash"></i>
                <p class="text-gray-500">ไม่พบข้อมูลผู้ใช้งาน</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

    {{-- Bulk Action Bar (Moved Outside) --}}
    <div id="bulk-action-bar" class="hidden fixed bottom-6 left-1/2 transform -translate-x-1/2 z-50 bg-gray-900/90 text-white px-6 py-4 rounded-2xl shadow-2xl backdrop-blur-sm flex items-center space-x-6 animate-slide-up-soft transition-all duration-300">
        <div class="flex items-center space-x-3">
            <div class="bg-blue-500 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm" id="selected-count">0</div>
            <span class="font-medium">รายการที่เลือก</span>
        </div>
        <div class="h-6 w-px bg-gray-700"></div>
        <button onclick="openBulkAssignModal()" class="flex items-center space-x-2 text-blue-300 hover:text-white transition-colors font-bold">
            <i class="fas fa-exchange-alt"></i>
            <span>เปลี่ยนกลุ่ม</span>
        </button>
        <button onclick="deselectAll()" class="text-gray-400 hover:text-white transition-colors">
            <i class="fas fa-times"></i>
        </button>
    </div>

{{-- Assign/Change Group Modal (Refactored to One-Click Cards) --}}
@can('user:manage')
<div class="fixed inset-0 z-50 items-center justify-center hidden p-4 modal-backdrop-soft" id="assignGroupModal">
    <div class="w-full max-w-4xl soft-card rounded-2xl modal-content-wrapper animate-slide-up-soft overflow-hidden">
        <form id="assignGroupForm" action="" method="POST">
            @csrf
            {{-- Method Field will be injected via JS for PUT/POST --}}
            <div id="method-field-container"></div>
            
            {{-- Hidden container for bulk user IDs --}}
            <div id="bulk-user-ids-container"></div>

            <div class="flex items-center justify-between p-6 border-b bg-gray-50">
                <div>
                     <h3 class="text-xl font-bold gradient-text-soft" id="modal-title">กำหนดกลุ่มผู้ใช้งาน</h3>
                     <p class="text-sm text-gray-500" id="modal-subtitle">เลือกสิทธิ์ที่ต้องการกำหนดให้ผู้ใช้</p>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" onclick="closeModal('assignGroupModal')">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-8 bg-white max-h-[70vh] overflow-y-auto">
                {{-- Role Cards Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($groups as $group)
                        @php
                            // Determine Color Theme based on Group Name
                            $gName = strtolower($group->name);
                            $cardColor = 'border-gray-100 hover:border-gray-300 hover:bg-gray-50';
                            $iconColor = 'text-gray-400';
                            $textColor = 'text-gray-700';
                            $icon = 'fa-user-tag';
                            $ringColor = 'focus:ring-gray-200';

                            if (str_contains($gName, 'it') || str_contains($gName, 'support')) {
                                $cardColor = 'border-red-100 bg-red-50/30 hover:bg-red-50 hover:border-red-200';
                                $iconColor = 'text-red-500';
                                $textColor = 'text-red-800';
                                $icon = 'fa-tools';
                                $ringColor = 'focus:ring-red-200';
                            } elseif (str_contains($gName, 'admin')) {
                                $cardColor = 'border-blue-100 bg-blue-50/30 hover:bg-blue-50 hover:border-blue-200';
                                $iconColor = 'text-blue-500';
                                $textColor = 'text-blue-800';
                                $icon = 'fa-user-shield';
                                $ringColor = 'focus:ring-blue-200';
                            } elseif (str_contains($gName, 'user')) {
                                $cardColor = 'border-indigo-100 bg-indigo-50/30 hover:bg-indigo-50 hover:border-indigo-200';
                                $iconColor = 'text-indigo-500';
                                $textColor = 'text-indigo-800';
                                $icon = 'fa-user';
                                $ringColor = 'focus:ring-indigo-200';
                            }
                        @endphp

                        <button type="submit" name="group_id" value="{{ $group->id }}"
                                class="relative group flex flex-col items-center justify-center p-6 border-2 rounded-2xl transition-all duration-200 transform hover:-translate-y-1 hover:shadow-lg focus:outline-none focus:ring-4 {{ $ringColor }} {{ $cardColor }}"
                                @cannot('assign-to-group', $group) disabled style="opacity: 0.5; cursor: not-allowed;" title="ระดับสิทธิ์ไม่เพียงพอ" @endcannot>
                            
                            <div class="mb-3 p-4 bg-white rounded-full shadow-sm group-hover:scale-110 transition-transform">
                                <i class="fas {{ $icon }} text-2xl {{ $iconColor }}"></i>
                            </div>
                            <span class="text-lg font-bold {{ $textColor }}">{{ $group->name }}</span>
                            <span class="text-xs text-gray-400 mt-1">คลิกเพื่อเลือกทันที</span>

                            @cannot('assign-to-group', $group)
                                <div class="absolute inset-0 bg-gray-100/50 backdrop-blur-[1px] rounded-2xl flex items-center justify-center">
                                    <span class="bg-white px-2 py-1 rounded-lg text-xs font-bold text-gray-500 shadow-sm"><i class="fas fa-lock mr-1"></i> ล็อค</span>
                                </div>
                            @endcannot
                        </button>
                    @endforeach
                </div>
            </div>
        </form>
    </div>
</div>
@endcan

@include('partials.modals.confirmation-modal')

@endsection

@push('scripts')
<script>
    // Global function to show/close modals
    function showModal(id) {
        document.getElementById(id)?.classList.remove('hidden');
        document.getElementById(id)?.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal(id) {
        document.getElementById(id)?.classList.add('hidden');
        document.getElementById(id)?.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    // --- BULK SELECTION LOGIC (CLICK ROW MODE) ---
    function toggleRow(row, userId) {
        // Prevent action if user permission is locked
        if (row.dataset.canManage !== '1') return;

        const checkbox = document.getElementById(`cb-${userId}`);
        const indicator = document.getElementById(`ind-${userId}`);
        const checkIcon = document.getElementById(`check-icon-${userId}`);
        
        // Toggle Checkbox
        checkbox.checked = !checkbox.checked;

        // Toggle Visuals
        if (checkbox.checked) {
            row.classList.add('bg-blue-50', 'border-blue-100');
            indicator.classList.remove('opacity-0');
            
            // Show Check Icon
            checkIcon.style.display = 'flex';
            requestAnimationFrame(() => {
                checkIcon.classList.remove('opacity-0', 'scale-90'); 
            });
        } else {
            row.classList.remove('bg-blue-50', 'border-blue-100');
            indicator.classList.add('opacity-0');
            
            // Hide Check Icon
            checkIcon.classList.add('opacity-0', 'scale-90');
            setTimeout(() => {
                checkIcon.style.display = 'none';
            }, 150);
        }

        updateBulkState();
    }

    function createRipple(event) {
        // Optional: Ripple effect for premium feel (omitted for simplicity if not needed)
    }

    function updateBulkState() {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        const count = checkboxes.length;
        const bulkBar = document.getElementById('bulk-action-bar');
        const countBadge = document.getElementById('selected-count');

        countBadge.textContent = count;
        if (count > 0) {
            bulkBar.classList.remove('hidden');
        } else {
            bulkBar.classList.add('hidden');
        }
    }

    function deselectAll() {
        // Reset all rows
        document.querySelectorAll('tr[onclick]').forEach(row => {
            row.classList.remove('bg-blue-50', 'border-blue-100');
        });
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
        
        // Hide indicators
        document.querySelectorAll('[id^="ind-"]').forEach(el => el.classList.add('opacity-0'));
        document.querySelectorAll('[id^="check-icon-"]').forEach(el => {
            el.classList.add('opacity-0', 'scale-90');
            el.style.display = 'none';
        });

        updateBulkState();
    }

    // --- MODAL LOGIC (ONE-CLICK & BULK) ---
    const form = document.getElementById('assignGroupForm');
    const methodContainer = document.getElementById('method-field-container');
    const bulkContainer = document.getElementById('bulk-user-ids-container');
    const modalTitle = document.getElementById('modal-title');
    const modalSubtitle = document.getElementById('modal-subtitle');

    // Single User Mode
    function openAssignModal(userId, userName, currentGroupId, currentGroupName) {
        form.action = `/management/users/${userId}`;
        methodContainer.innerHTML = '@method("PUT")'; // Inject PUT for single update
        bulkContainer.innerHTML = ''; // Clear bulk inputs

        modalTitle.innerHTML = `เปลี่ยนกลุ่ม: <span class="text-blue-600">${userName}</span>`;
        modalSubtitle.textContent = `ปัจจุบัน: ${currentGroupName || 'ไม่มีกลุ่ม'}`;
        
        showModal('assignGroupModal');
    }

    // Bulk Mode
    function openBulkAssignModal() {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        if (checkboxes.length === 0) return;

        form.action = `{{ route('management.users.bulkUpdate') }}`;
        methodContainer.innerHTML = ''; // POST is default, no spoofing needed
        
        // Generate hidden inputs for each selected user ID
        bulkContainer.innerHTML = '';
        checkboxes.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'user_ids[]';
            input.value = cb.value;
            bulkContainer.appendChild(input);
        });

        modalTitle.innerHTML = `กำหนดกลุ่มให้ผู้ใช้ <span class="text-blue-600">${checkboxes.length} คน</span>`;
        modalSubtitle.textContent = 'เลือกกลุ่มที่ต้องการเปลี่ยนให้รายการที่เลือกทั้งหมด';

        showModal('assignGroupModal');
    }


    // Function to show a custom confirmation modal
    function showConfirmationModal({ icon, title, message, confirmButtonText = 'ยืนยัน', confirmButtonClass = 'bg-blue-500 hover:bg-blue-600', onConfirm }) {
        const modal = document.getElementById('confirmation-modal');
        document.getElementById('confirmation-icon').className = icon;
        document.getElementById('confirmation-title').textContent = title;
        document.getElementById('confirmation-message').innerHTML = message;
        const confirmBtn = document.getElementById('confirmation-confirm-btn');
        confirmBtn.textContent = confirmButtonText;
        confirmBtn.className = `px-6 py-2 font-bold text-white rounded-lg ${confirmButtonClass}`;

        confirmBtn.onclick = () => {
            onConfirm?.();
            closeModal('confirmation-modal');
        };
        document.getElementById('confirmation-cancel-btn').onclick = () => closeModal('confirmation-modal');

        showModal('confirmation-modal');
    }

</script>
@endpush
