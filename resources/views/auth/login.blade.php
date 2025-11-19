<x-guest-layout>
    @if (session('status'))
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- ✅ CORRECTED: Changed from 'email' to 'username' --}}
        <div>
            <label for="username" class="block font-medium text-sm text-gray-700">ชื่อผู้ใช้งาน</label>
            <div class="relative mt-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-user text-gray-400"></i>
                </div>
                <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus 
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>
             @error('username')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-4">
            <label for="password" class="block font-medium text-sm text-gray-700">รหัสผ่าน</label>
            <div class="relative mt-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-lock text-gray-400"></i>
                </div>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>
             @error('password')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" name="remember">
                <span class="ml-2 text-sm text-gray-600">จดจำฉันไว้</span>
            </label>
        </div>

        <div class="mt-6">
            <button type="submit" class="w-full flex justify-center items-center px-6 py-3 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-gradient-to-br from-blue-500 to-purple-600 hover:bg-gradient-to-bl focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
                <i class="fas fa-sign-in-alt mr-2"></i>
                <span>เข้าสู่ระบบ</span>
            </button>
        </div>
    </form>
</x-guest-layout>