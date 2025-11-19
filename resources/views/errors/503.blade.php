<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - ปิดปรับปรุงระบบ</title>
    {{-- Include Tailwind CSS --}}
    <script src="https://cdn.tailwindcss.com"></script>
    {{-- Include Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;700&display=swap');
        body {
            font-family: 'Kanit', sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center min-h-screen">
    <div class="text-center p-8 bg-white rounded-2xl shadow-xl max-w-lg mx-auto border border-gray-200">
        <div class="mb-6 text-yellow-500">
            <i class="fas fa-tools fa-4x animate-bounce"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mb-3">ปิดปรับปรุงระบบชั่วคราว</h1>
        <p class="text-gray-600 mb-6">
            ขออภัยในความไม่สะดวก ขณะนี้ระบบ {{ config('app.name', 'IT Stock') }} กำลังอยู่ในระหว่างการปิดปรับปรุง
            เพื่อให้การบริการมีประสิทธิภาพยิ่งขึ้น กรุณาลองเข้าใช้งานใหม่อีกครั้งภายหลัง
        </p>
        <p class="text-sm text-gray-500">
            ขอขอบคุณสำหรับความเข้าใจ
        </p>
    </div>
</body>
</html>

