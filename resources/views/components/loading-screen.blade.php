<!-- Loading Screen Component -->
<div id="loading-screen" class="loading-overlay">
    <div class="text-center">
        <div class="mb-8">
            <div class="w-24 h-24 bg-gradient-to-br from-blue-100 to-blue-200 rounded-3xl flex items-center justify-center mx-auto mb-6 gentle-shadow animate-bounce-gentle">
                <i class="fas fa-rocket text-blue-500 text-4xl"></i>
            </div>
            <h1 class="text-4xl font-bold gradient-text-soft mb-4">
                🌸 IT Stock Pro
            </h1>
            <p class="text-gray-600 text-xl">กำลังโหลดระบบ...</p>
        </div>
        <div class="loading-spinner mx-auto mb-4"></div>
        <div class="loading-dots">
            <div class="loading-dot"></div>
            <div class="loading-dot"></div>
            <div class="loading-dot"></div>
        </div>
        <div class="mt-8">
            <div class="w-80 h-2 bg-gray-200 rounded-full mx-auto overflow-hidden">
                <div id="loading-progress" class="h-full bg-gradient-to-r from-blue-300 to-purple-300 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <p id="loading-text" class="text-gray-500 mt-4 text-lg">เตรียมข้อมูล...</p>
        </div>
    </div>
</div>
