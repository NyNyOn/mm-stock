<div class="fixed inset-0 z-50 items-center justify-center hidden bg-black bg-opacity-50" id="permissionsModal{{ $group->id }}">
    <div class="w-full max-w-lg p-5 mx-auto bg-white rounded-2xl soft-card">
        <form action="{{ route('management.groups.updatePermissions', $group->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="flex items-start justify-between pb-3 border-b">
                <h3 class="text-xl font-bold">แก้ไขสิทธิ์: {{ $group->name }}</h3>
                <button type="button" class="text-black" onclick="closeModal('permissionsModal{{ $group->id }}')">&times;</button>
            </div>
            <div class="grid grid-cols-1 gap-4 py-4 overflow-y-auto md:grid-cols-2 max-h-96">
                @foreach($permissions as $permission)
                    <div class="flex items-center">
                        <input class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm-{{ $group->id }}-{{ $permission->id }}" @if($group->permissions->contains($permission->id)) checked @endif>
                        <label class="ml-2 text-sm text-gray-700" for="perm-{{ $group->id }}-{{ $permission->id }}">
                            {{ $permission->name }} <span class="text-gray-500">({{ $permission->description }})</span>
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end pt-3 border-t">
                <button type="button" class="px-4 py-2 mr-2 text-white bg-gray-500 rounded-lg hover:bg-gray-700" onclick="closeModal('permissionsModal{{ $group->id }}')">ปิด</button>
                <button type="submit" class="px-4 py-2 text-white bg-blue-500 rounded-lg hover:bg-blue-700">บันทึกการเปลี่ยนแปลง</button>
            </div>
        </form>
    </div>
</div>
