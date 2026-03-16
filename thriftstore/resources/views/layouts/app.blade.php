<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $platformLogo = \App\Models\SystemSetting::get('logo_path');
            $faviconUrl = $platformLogo ? asset('storage/' . $platformLogo) : asset('favicon.ico');
        @endphp

        <link rel="icon" type="image/png" href="{{ $faviconUrl }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('styles')
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen {{ request()->routeIs('seller.register', 'seller.login') ? 'bg-[#f2f5f4]' : 'bg-gray-100' }} flex flex-col">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1 {{ request()->routeIs('seller.register') ? 'overflow-hidden flex items-center' : '' }} {{ request()->routeIs('seller.login') ? 'overflow-auto flex items-center justify-center seller-login-main-bg' : '' }}">
                @if(isset($slot))
                    {{ $slot }}
                @else
                    @yield('content')
                @endif
                        
            </main>

            @include('layouts.footer')
        </div>

        @livewireScripts
    </body>
</html>
