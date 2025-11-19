@extends('layouts.app')

@section('header', 'จัดการผู้ใช้งาน')
@section('subtitle', 'แก้ไขและกำหนดสิทธิ์ผู้ใช้ในระบบ')

@section('content')
<div class="soft-card p-6">

    @if (session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 animate-fade-in" role="alert">
            <p class="font-bold">สำเร็จ!</p>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y-2 divide-gray-200 bg-white text-sm">
            <thead class="text-left bg-gray-50">
                <tr>
                    <th class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">User</th>
                    <th class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 w-1/4">Group/Role</th>
                    <th class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 w-1/4 text-center">Action</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">
                @php
                    $groups = \App\Models\UserGroup::all();
                @endphp

                @forelse ($users as $user)
                    <tr id="user-row-{{ $user->id }}" class="hover:bg-gray-50/50 transition-colors duration-300">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">
                            <div class="font-bold">{{ $user->name }}</div>
                            <div class="text-xs text-gray-500">{{ $user->username }}</div>
                        </td>
                        
                        <form action="{{ route('users.update', $user->id) }}" method="POST" class="contents">
                            @csrf
                            @method('PUT')
                            
                            <td class="whitespace-nowrap px-4 py-3">
                                <select name="user_group_id" onchange="enableUpdateButton({{ $user->id }})" 
                                        class="w-full px-3 py-2 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 border-0 text-sm font-medium gentle-shadow">
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}" {{ $user->user_group_id == $group->id ? 'selected' : '' }}>
                                            {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <button type="submit" id="update-button-{{ $user->id }}" disabled
                                   class="inline-block rounded-xl bg-indigo-600 px-5 py-2 text-xs font-medium text-white transition hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed gentle-shadow">
                                    <i class="fas fa-save mr-1"></i> Update
                                </button>
                            </td>
                        </form>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                            <div class="mx-auto w-fit">
                                <i class="fas fa-user-slash fa-4x text-gray-300 mb-4"></i>
                                <h3 class="text-xl font-semibold">ไม่พบข้อมูลผู้ใช้งาน</h3>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>

</div>
@endsection

@push('scripts')
<script>
    function enableUpdateButton(userId) {
        const button = document.getElementById(`update-button-${userId}`);
        const row = document.getElementById(`user-row-${userId}`);
        if (button) {
            button.disabled = false;
            // เพิ่มลูกเล่น: ไฮไลท์แถวที่มีการเปลี่ยนแปลง
            row.classList.add('bg-yellow-50');
        }
    }
</script>
@endpush