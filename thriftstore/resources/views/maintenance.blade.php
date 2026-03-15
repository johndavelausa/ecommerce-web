<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg p-8 max-w-lg w-full text-center">
        <div class="text-6xl mb-4">🔧</div>
        <h1 class="text-xl font-semibold text-gray-900 mb-2">Under maintenance</h1>
        <p class="text-gray-600 whitespace-pre-line">{{ $message }}</p>
        <p class="text-sm text-gray-500 mt-4">We'll be back shortly. Thank you for your patience.</p>
    </div>
</body>
</html>
