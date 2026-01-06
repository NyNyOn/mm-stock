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
                    <tr class="transition-colors hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $user->fullname }}</div>
                        </td>
                         <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-600">{{ $user->username }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($user->serviceUserRole && $user->serviceUserRole->userGroup)
                                <span class="px-3 py-1 text-xs font-semibold leading-5 text-blue-800 bg-blue-100 rounded-full">
                                    {{ $user->serviceUserRole->userGroup->name }}
                                </span>
                            @else
                                <span class="px-3 py-1 text-xs font-semibold leading-5 text-gray-800 bg-gray-100 rounded-full">
                                    ผู้ใช้ทั่วไป
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-center whitespace-nowrap">
                            @can('user:manage')
                                {{-- ✅ NEW HIERARCHY CHECK: Use the 'manage-user-role' gate --}}
                                @can('manage-user-role', $user)
                                    @if($user->serviceUserRole && $user->serviceUserRole->userGroup)
                                        {{-- Buttons are only visible if the current user can manage the target user --}}
                                        <button
                                            onclick="openAssignModal('{{ $user->id }}', '{{ $user->fullname }}', '{{ $user->serviceUserRole->group_id }}', '{{ $user->serviceUserRole->userGroup->name }}')"
                                            class="px-3 py-1 text-xs font-bold text-yellow-700 transition duration-150 ease-in-out bg-yellow-100 rounded-lg hover:bg-yellow-200"
                                            title="เปลี่ยนกลุ่มผู้ใช้งาน">
                                            <i class="mr-1 fas fa-exchange-alt"></i> เปลี่ยนกลุ่ม
                                        </button>

                                        <form action="{{ route('management.users.removeGroup', $user->id) }}" method="POST" class="inline-block ml-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                    onclick="confirmRemoveFromGroup(this.closest('form'), '{{ $user->fullname }}', '{{ $user->serviceUserRole->userGroup->name }}')"
                                                    class="px-3 py-1 text-xs font-bold text-red-700 transition duration-150 ease-in-out bg-red-100 rounded-lg hover:bg-red-200"
                                                    title="นำผู้ใช้ออกจากกลุ่ม">
                                                <i class="mr-1 fas fa-user-minus"></i> นำออก
                                            </button>
                                        </form>
                                    @else
                                        {{-- Assign Group Button for users with no group --}}
                                        <button
                                            onclick="openAssignModal('{{ $user->id }}', '{{ $user->fullname }}', '', '')"
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
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center justify-center w-10 h-10 text-lg font-bold text-indigo-600 bg-indigo-100 rounded-full">
                            {{ substr($user->fullname, 0, 1) }}
                        </div>
                        <div>
                            <div class="text-base font-bold text-gray-900">{{ $user->fullname }}</div>
                            <div class="text-sm text-gray-500">{{ $user->username }}</div>
                        </div>
                    </div>
                     <div>
                        @if($user->serviceUserRole && $user->serviceUserRole->userGroup)
                            <span class="px-2 py-1 text-xs font-semibold leading-5 text-blue-800 bg-blue-100 rounded-lg">
                                {{ $user->serviceUserRole->userGroup->name }}
                            </span>
                        @else
                            <span class="px-2 py-1 text-xs font-semibold leading-5 text-gray-800 bg-gray-100 rounded-lg">
                                ผู้ใช้ทั่วไป
                            </span>
                        @endif
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
                                            <button type="button"
                                                    onclick="confirmRemoveFromGroup(this.closest('form'), '{{ $user->fullname }}', '{{ $user->serviceUserRole->userGroup->name }}')"
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

{{-- Assign/Change Group Modal --}}
@can('user:manage')
<div class="fixed inset-0 z-50 items-center justify-center hidden p-4 modal-backdrop-soft" id="assignGroupModal">
    <div class="w-full max-w-lg soft-card rounded-2xl modal-content-wrapper animate-slide-up-soft">
        <form id="assignGroupForm" action="" method="POST">
            @csrf
            @method('PUT')
            <div class="flex items-center justify-between p-5 border-b">
                <h3 class="text-lg font-bold gradient-text-soft">กำหนดกลุ่มสำหรับ <span id="modal_user_name_assign" class="text-blue-600"></span></h3>
                <button type="button" class="text-2xl text-gray-500 hover:text-gray-700" onclick="closeModal('assignGroupModal')">&times;</button>
            </div>
            <div class="p-6">
                <label for="modal_group_id" class="block mb-2 text-sm font-bold text-gray-700">เลือกกลุ่มผู้ใช้งาน</label>
                <select name="group_id" id="modal_group_id" class="w-full input-form" required>
                    <option value="">-- กรุณาเลือกกลุ่ม --</option>
                    @foreach($groups as $group)
                        {{-- ✅ NEW HIERARCHY CHECK: Disable options if the current user's level is not high enough --}}
                        <option value="{{ $group->id }}"
        data-group-name="{{ $group->name }}"
        @cannot('assign-to-group', $group) disabled title="คุณมีระดับสิทธิ์ไม่เพียงพอที่จะกำหนดกลุ่มนี้" @endcannot>
    {{ $group->name }}
</option>
                    @endforeach
                </select>
                <p id="group-selection-message" class="hidden mt-2 text-sm text-red-600">
                    <i class="mr-1 fas fa-exclamation-triangle"></i> คุณมีสิทธิ์ไม่เพียงพอที่จะกำหนดกลุ่ม <span id="restricted-group-name"></span>
                </p>
            </div>
            <div class="flex justify-end p-4 space-x-3 bg-gray-50/50 rounded-b-2xl">
                <button type="button" class="btn-secondary" onclick="closeModal('assignGroupModal')">ยกเลิก</button>
                <button type="submit" class="btn-primary">
                    <i class="mr-1 fas fa-save"></i> บันทึก
                </button>
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

    // Function to open the Assign/Change Group Modal
    function openAssignModal(userId, userName, currentGroupId) {
        const form = document.getElementById('assignGroupForm');
        const modalUserName = document.getElementById('modal_user_name_assign');
        const modalGroupIdSelect = document.getElementById('modal_group_id');
        const groupSelectionMessage = document.getElementById('group-selection-message');
        const restrictedGroupNameSpan = document.getElementById('restricted-group-name');

        form.action = `/management/users/${userId}`;
        modalUserName.textContent = userName;
        modalGroupIdSelect.value = currentGroupId;
        groupSelectionMessage.classList.add('hidden');

        modalGroupIdSelect.onchange = function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.disabled) {
                restrictedGroupNameSpan.textContent = selectedOption.dataset.groupName;
                groupSelectionMessage.classList.remove('hidden');
            } else {
                groupSelectionMessage.classList.add('hidden');
            }
        };

        if (currentGroupId) {
            modalGroupIdSelect.dispatchEvent(new Event('change'));
        }

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

    // Confirmation for removing a user from a group
    function confirmRemoveFromGroup(formElement, userName, userGroupName) {
        showConfirmationModal({
            icon: 'fas fa-exclamation-triangle text-red-500',
            title: `ยืนยันการนำ ${userName} ออกจากกลุ่ม`,
            message: `คุณกำลังจะนำผู้ใช้งาน <strong>${userName}</strong> ออกจากกลุ่ม <strong>${userGroupName}</strong> และทำให้ผู้ใช้ไม่มีสิทธิ์ใดๆ คุณแน่ใจหรือไม่?`,
            confirmButtonText: 'ยืนยันการนำออก',
            confirmButtonClass: 'bg-red-600 hover:bg-red-700',
            onConfirm: () => formElement.submit()
        });
    }
</script>
@endpush
