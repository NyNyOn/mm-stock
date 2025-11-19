<div class="relative flex-shrink-0">
    {{-- Container หลักสำหรับรูป --}}
    <div class="flex items-center justify-center overflow-hidden bg-gray-200 rounded-2xl gentle-shadow {{ $sizeClass }}">
        <img src="{{ $user->photo_url }}" alt="{{ $user->fullname }}" class="object-cover w-full h-full">
    </div>

    {{-- ✅ วงแหวนและแสงเรืองรอง จะถูกควบคุมโดย $ringClasses --}}
    <div class="absolute inset-0 rounded-2xl {{ $ringClasses }}"></div>

    {{-- 👑 ไอคอนพิเศษสำหรับผู้สร้าง --}}
    @if($isCreator)
        <div class="absolute -bottom-1 -right-1">
            <div class="flex items-center justify-center w-6 h-6 bg-yellow-400 border-2 border-white rounded-full shadow-lg">
                <i class="text-xs text-white fas fa-crown"></i>
            </div>
        </div>
    @endif
</div>
