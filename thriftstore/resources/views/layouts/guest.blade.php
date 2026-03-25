<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $platformLogo = \App\Models\SystemSetting::get('logo_path');
            $faviconUrl = ($platformLogo && !str_starts_with($platformLogo, 'data:')) ? asset('storage/' . $platformLogo) : ($platformLogo ?: asset('favicon.ico'));
        @endphp

        <link rel="icon" type="image/png" href="{{ $faviconUrl }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-[#f2f5f4]">
        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            <main class="flex-1 flex items-center justify-center px-4 py-8 sm:px-6">
                <div class="w-full sm:max-w-md px-6 py-5 bg-white shadow-md overflow-hidden sm:rounded-lg border border-[#d9e1df]">
                    {{ $slot }}
                </div>
            </main>

            @include('layouts.footer')
        </div>
    </body>
</html>
