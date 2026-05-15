<!DOCTYPE html>
<html>
<head>
    <!-- 測試不同的 CSS 載入方式 -->
    <style>
        .inline-test { color: green; font-weight: bold; }
    </style>
    
    <!-- 測試1：Vite -->
    @vite(['resources/css/app.css'])
    
    <!-- 測試2：直接連結 -->
    <!-- <link rel="stylesheet" href="{{ asset('build/assets/app.css') }}"> -->
    
    <!-- 測試3：CDN -->
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
</head>
<body>
    <div class="inline-test p-4">
        內聯樣式測試（綠色文字）
    </div>
    
    <div class="bg-red-500 text-white p-4 mt-4">
        Tailwind 測試（紅色背景）
    </div>
    
    <div class="bg-blue-500 text-white p-4 mt-4">
        Tailwind 測試（藍色背景）
    </div>
    
    <div class="max-w-md mx-auto mt-8 p-6 bg-white shadow-lg rounded-lg">
        <h2 class="text-2xl font-bold mb-4">表單測試</h2>
        <input type="text" class="w-full p-2 border rounded mb-2" placeholder="測試輸入">
        <button class="w-full bg-indigo-600 text-white p-2 rounded">測試按鈕</button>
    </div>
</body>
</html>