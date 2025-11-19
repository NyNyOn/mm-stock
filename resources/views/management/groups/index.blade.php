@extends('layouts.app')
@section('header', '👥 จัดการกลุ่มและสิทธิ์')
@section('subtitle', 'สร้างกลุ่มผู้ใช้และกำหนดสิทธิ์การเข้าถึงส่วนต่างๆ ของระบบ')

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

    {{-- Header and Action Buttons --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div></div>
        <div class="flex-shrink-0">
            @if(Auth::user()->getRoleLevel() >= 90)
                <button onclick="showModal('create-group-modal')" class="shadow-lg btn-primary">
                    <i class="mr-2 fas fa-plus"></i> สร้างกลุ่มใหม่
                </button>
            @endif
        </div>
    </div>

    {{-- Group Cards Layout --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($groups as $group)
            <div class="flex flex-col transition-all duration-300 soft-card rounded-2xl gentle-shadow soft-hover">
                <div class="p-5 border-b">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-800">{{ $group->name }}</h3>
                        <span class="px-3 py-1 text-xs font-semibold text-indigo-800 bg-indigo-100 rounded-full">
                            Level: {{ $group->hierarchy_level }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">{{ $group->description ?? 'ไม่มีคำอธิบาย' }}</p>
                </div>
                <div class="flex-grow p-5 space-y-3">
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="w-5 mr-3 text-center fas fa-users text-sky-500"></i>
                        <span>มีสมาชิก {{ $group->serviceUserRoles->count() }} คน</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="w-5 mr-3 text-center text-teal-500 fas fa-shield-alt"></i>
                        <span>กำหนดไว้ {{ $group->permissions->count() }} สิทธิ์</span>
                    </div>
                </div>
                <div class="p-4 mt-auto bg-gray-50/50 rounded-b-2xl">
                    <div class="flex items-center justify-end space-x-2">
                        <button
                            onclick="openPermissionsModal('{{ $group->id }}', '{{ e($group->name) }}', {{ json_encode($group->permissions->pluck('id')) }})"
                            class="px-4 py-2 text-xs font-bold text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200"
                            title="จัดการสิทธิ์">
                            จัดการสิทธิ์
                        </button>

                        @if(Auth::user()->getRoleLevel() > $group->hierarchy_level)
                            <a href="{{ route('management.groups.edit', $group) }}" class="px-4 py-2 text-xs font-bold text-yellow-700 bg-yellow-100 rounded-lg hover:bg-yellow-200" title="แก้ไขกลุ่ม">
                                แก้ไข
                            </a>
                            <form action="{{ route('management.groups.destroy', $group) }}" method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบกลุ่ม {{ $group->name }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-4 py-2 text-xs font-bold text-red-700 bg-red-100 rounded-lg hover:bg-red-200" title="ลบกลุ่ม">
                                    ลบ
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-12 text-center text-gray-500 md:col-span-2 lg:col-span-3 soft-card rounded-2xl">
                <i class="text-4xl fas fa-users-slash"></i>
                <p class="mt-4">ยังไม่มีกลุ่มผู้ใช้ในระบบ</p>
            </div>
        @endforelse
    </div>
</div>

{{-- Modal for Creating a New Group --}}
<div id="create-group-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 modal-backdrop-soft">
    <div class="w-full max-w-lg soft-card rounded-2xl modal-content-wrapper animate-slide-up-soft">
        <form action="{{ route('management.groups.store') }}" method="POST">
            @csrf
            <div class="p-5 border-b">
                <h3 class="text-lg font-bold gradient-text-soft">สร้างกลุ่มผู้ใช้ใหม่</h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label for="create-group-name" class="block mb-1 text-sm font-medium">ชื่อกลุ่ม *</label>
                    <input type="text" name="name" id="create-group-name" required class="w-full input-form" placeholder="เช่น Admin, Staff, Viewer">
                </div>
                <div>
                    <label for="create-group-slug" class="block mb-1 text-sm font-medium">Slug (ภาษาอังกฤษ ไม่มีเว้นวรรค) *</label>
                    <input type="text" name="slug" id="create-group-slug" required class="w-full input-form" placeholder="เช่น admin, staff, viewer">
                </div>
                <div>
                    <label for="create-group-level" class="block mb-1 text-sm font-medium">Hierarchy Level *</label>
                    <input type="number" name="hierarchy_level" id="create-group-level" required class="w-full input-form" value="10" placeholder="ยิ่งสูง สิทธิ์ยิ่งเยอะ">
                     <p class="mt-1 text-xs text-gray-500">แนะนำ: Owner=100, IT=90, Admin=50, User=10</p>
                </div>
                <div>
                    <label for="create-group-description" class="block mb-1 text-sm font-medium">คำอธิบาย</label>
                    <textarea name="description" id="create-group-description" rows="3" class="w-full input-form" placeholder="อธิบายหน้าที่ของกลุ่มนี้โดยย่อ"></textarea>
                </div>
            </div>
            <div class="flex justify-end p-4 space-x-2 bg-gray-50/50 rounded-b-2xl">
                <button type="button" onclick="closeModal('create-group-modal')" class="btn-secondary">ยกเลิก</button>
                <button type="submit" class="btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal for Managing Permissions --}}
<div id="permissions-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 modal-backdrop-soft">
    <div class="w-full max-w-4xl soft-card rounded-2xl modal-content-wrapper animate-slide-up-soft">
        <form id="permissions-form" method="POST">
            @csrf
            @method('PUT')
            <div class="p-5 border-b">
                <h3 class="text-lg font-bold">จัดการสิทธิ์ของกลุ่ม: <span id="group-name-placeholder" class="gradient-text-soft"></span></h3>
            </div>
            <div class="p-6 overflow-y-auto max-h-[60vh] scrollbar-soft">
                <div class="space-y-6">
                    @php
                        $groupedPermissions = $permissions->groupBy(function($item) {
                            return explode(':', $item->name)[0];
                        });
                    @endphp

                    @foreach ($groupedPermissions as $groupName => $permissionList)
                        <div>
                            <h4 class="mb-3 font-bold text-gray-700 capitalize border-b">{{ $groupName }} Management</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                                @foreach ($permissionList as $permission)
                                    <label class="flex items-start p-2 space-x-3 transition-colors rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="w-5 h-5 mt-1 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <div>
                                            <span class="font-medium text-gray-800">{{ $permission->name }}</span>
                                            <p class="text-xs text-gray-500">{{ $permission->description }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-end p-4 space-x-2 bg-gray-50/50 rounded-b-2xl">
                <button type="button" onclick="closeModal('permissions-modal')" class="btn-secondary">ยกเลิก</button>
                <button type="submit" class="btn-primary">บันทึกการเปลี่ยนแปลง</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function showModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            // Add 'flex' to enable centering via Tailwind CSS utility classes
            modal.classList.add('flex');
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            // Remove 'flex' when closing
            modal.classList.remove('flex');
        }
    }

    function openPermissionsModal(groupId, groupName, assignedPermissionIds) {
        const form = document.getElementById('permissions-form');
        const namePlaceholder = document.getElementById('group-name-placeholder');

        form.action = `/management/groups/${groupId}/permissions`;
        namePlaceholder.textContent = groupName;

        form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => checkbox.checked = false);

        assignedPermissionIds.forEach(permissionId => {
            const checkbox = form.querySelector(`input[value="${permissionId}"]`);
            if (checkbox) checkbox.checked = true;
        });

        showModal('permissions-modal');
    }
</script>
@endpush
