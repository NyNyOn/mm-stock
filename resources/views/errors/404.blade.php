<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f7fafc 50%, #edf2f7 100%);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-lg p-8 text-center">
        {{-- Icon --}}
        <div class="mb-8 text-center">
            <i class="text-gray-400 text-8xl fas fa-ghost"></i>
        </div>

        {{-- Error Code & Title --}}
        <h1 class="text-6xl font-extrabold text-gray-500">404</h1>
        <p class="mt-4 text-2xl font-bold tracking-tight text-gray-800 sm:text-3xl">
            Page Not Found
        </p>

        {{-- Message --}}
        <p class="mt-4 text-base text-gray-600">
            ขออภัย, เราไม่พบหน้าที่คุณกำลังค้นหา<br>
            URL อาจจะถูกลบ, เปลี่ยนชื่อ หรือไม่มีอยู่จริง
        </p>

        {{-- Action Button --}}
        <div class="mt-10">
            <a href="{{ Auth::check() ? route('dashboard') : url('/') }}" class="inline-block px-6 py-3 text-sm font-semibold text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                <i class="mr-2 fas fa-home"></i>
                กลับไปหน้าหลัก
            </a>
        </div>

        <p class="mt-8 text-xs text-gray-400">IT Stock Pro</p>
    </div>
</body>
</html>
