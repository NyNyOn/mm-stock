<div>
    <label for="name">Name</label>
    <input type="text" name="name" id="name" value="{{ old('name', $group->name) }}" class="w-full input-form @error('name') border-red-500 @enderror" required>
    @error('name')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mt-4">
    <label for="slug">Slug</label>
    <input type="text" name="slug" id="slug" value="{{ old('slug', $group->slug) }}" class="w-full input-form @error('slug') border-red-500 @enderror" required>
    @error('slug')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mt-4">
    <label for="description">Description</label>
    <textarea name="description" id="description" class="w-full input-form">{{ old('description', $group->description) }}</textarea>
</div>

<div class="mt-4">
    <label for="hierarchy_level">Hierarchy Level</label>
    <input type="number" name="hierarchy_level" id="hierarchy_level" value="{{ old('hierarchy_level', $group->hierarchy_level) }}" class="w-full input-form @error('hierarchy_level') border-red-500 @enderror" required>
    @error('hierarchy_level')
         <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
    <p class="text-xs text-gray-500">Owner=100, IT=90, Admin=50, User=10. ยิ่งสูง ยิ่งมีสิทธิ์เยอะ</p>
</div>
