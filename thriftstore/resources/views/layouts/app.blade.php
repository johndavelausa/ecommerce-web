@php
    $showChatWidget = Auth::guard('web')->check()
        && !request()->is('admin/*')
        && !request()->is('seller/*')
        && !request()->is('profile*')
        && !request()->routeIs('customer.orders', 'customer.messages', 'customer.checkout', 'customer.reviews');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <title>Ukay HUB</title>
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
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('styles')
        <style>
            .user-bg {
                background-image: url('/background_img/userbg1.jpeg');
                background-size: cover;
                background-position: center top;
                background-attachment: fixed;
                background-repeat: no-repeat;
            }
        </style>

        @if($showChatWidget)
        <style>
            /* ── FAB ── */
            .fcw-fab {
                position: fixed; bottom: 24px; right: 24px; z-index: 9999;
                width: 50px; height: 50px; border-radius: 50%;
                background: #2D9F4E; border: none; cursor: pointer;
                display: flex; align-items: center; justify-content: center;
                box-shadow: 0 4px 18px rgba(45,159,78,0.45);
                transition: background .18s, transform .18s, box-shadow .18s;
                color: #fff; font-family: 'Inter', sans-serif;
            }
            .fcw-fab:hover { background: #1B7A37; transform: scale(1.07); box-shadow: 0 6px 24px rgba(45,159,78,0.55); }
            .fcw-fab-badge {
                position: absolute; top: -3px; right: -3px;
                min-width: 17px; height: 17px; padding: 0 4px;
                border-radius: 50%; background: #E53E3E; color: #fff;
                font-size: 9px; font-weight: 700;
                display: flex; align-items: center; justify-content: center;
                border: 2px solid #fff; line-height: 1; font-family: 'Inter', sans-serif;
            }
            /* ── Panel ── */
            .fcw-panel {
                position: fixed; bottom: 0; right: 24px; z-index: 9999;
                width: 460px; max-width: calc(100vw - 48px);
                height: 400px; max-height: calc(100vh - 56px);
                background: #ffffff; border-radius: 14px 14px 0 0;
                box-shadow: 0 -4px 32px rgba(0,0,0,.12), 0 0 0 1px rgba(0,0,0,.06);
                display: flex; flex-direction: column; overflow: hidden;
                font-family: 'Inter', sans-serif;
            }
            @@media (max-width: 640px) { .fcw-panel { width: calc(100vw - 24px); height: 380px; right: 12px; } }
            /* ── Header ── */
            .fcw-header {
                background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
                padding: 11px 14px; display: flex; align-items: center; justify-content: space-between;
                flex-shrink: 0; border-radius: 14px 14px 0 0;
            }
            .fcw-header-left { display: flex; align-items: center; gap: 9px; }
            .fcw-header-title { font-size: 0.9rem; font-weight: 700; color: #fff; margin: 0; letter-spacing: .01em; }
            .fcw-header-badge {
                background: rgba(183,228,199,.35); color: #d1fae5;
                font-size: 10px; font-weight: 700;
                padding: 1px 7px; border-radius: 20px; line-height: 1.5;
            }
            .fcw-header-actions { display: flex; align-items: center; gap: 4px; }
            .fcw-header-btn {
                background: rgba(255,255,255,.13); border: none; border-radius: 7px;
                width: 27px; height: 27px; display: flex; align-items: center; justify-content: center;
                color: rgba(255,255,255,.85); cursor: pointer; transition: background .13s; text-decoration: none;
            }
            .fcw-header-btn:hover { background: rgba(255,255,255,.25); color: #fff; }
            /* ── Body ── */
            .fcw-body { display: flex; flex: 1; overflow: hidden; }
            /* ── Sidebar ── */
            .fcw-sidebar {
                width: 170px; flex-shrink: 0;
                border-right: 1px solid #eef0f3;
                display: flex; flex-direction: column;
                background: #f8f9fb;
            }
            .fcw-search-wrap { padding: 10px; border-bottom: 1px solid #eef0f3; flex-shrink: 0; position: relative; }
            .fcw-search {
                width: 100%; padding: 6px 10px 6px 30px;
                font-size: .775rem; background: #fff;
                border: 1px solid #e2e8f0; border-radius: 20px;
                outline: none; color: #1a1a2e;
                font-family: 'Inter', sans-serif; transition: border-color .13s, box-shadow .13s;
                box-sizing: border-box;
            }
            .fcw-search:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,.12); }
            .fcw-search-icon { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: #aab; pointer-events: none; }
            .fcw-conv-list { flex: 1; overflow-y: auto; }
            .fcw-conv-list::-webkit-scrollbar { width: 4px; }
            .fcw-conv-list::-webkit-scrollbar-track { background: transparent; }
            .fcw-conv-list::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
            .fcw-conv-item {
                display: flex; align-items: center; gap: 9px;
                padding: 9px 10px; cursor: pointer;
                border-left: 3px solid transparent;
                transition: background .12s, border-color .12s;
                border-bottom: 1px solid #f1f3f5;
            }
            .fcw-conv-item:hover { background: #FFF9E3; border-left-color: #FFE17B; }
            .fcw-conv-item.active { background: #E8F5E9; border-left-color: #2D9F4E; }
            .fcw-conv-avatar {
                width: 34px; height: 34px; border-radius: 50%;
                background: linear-gradient(135deg, #FFE17B, #F9C74F);
                display: flex; align-items: center; justify-content: center;
                flex-shrink: 0; color: #424242;
            }
            .fcw-conv-info { flex: 1; min-width: 0; }
            .fcw-conv-row { display: flex; justify-content: space-between; align-items: baseline; gap: 4px; }
            .fcw-conv-name { font-size: .8rem; font-weight: 600; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .fcw-conv-time { font-size: .65rem; color: #9ca3af; flex-shrink: 0; }
            .fcw-conv-preview { font-size: .7rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
            .fcw-conv-dot { width: 7px; height: 7px; border-radius: 50%; background: #2D9F4E; flex-shrink: 0; }
            /* ── New chat section ── */
            .fcw-new-section { border-top: 1px solid #eef0f3; background: #fff; flex-shrink: 0; }
            .fcw-new-toggle {
                width: 100%; padding: 9px 12px;
                display: flex; align-items: center; justify-content: space-between;
                font-size: .7rem; font-weight: 600; color: #6b7280;
                text-transform: uppercase; letter-spacing: .06em;
                background: transparent; border: none; cursor: pointer; font-family: 'Inter', sans-serif;
                transition: background .12s;
            }
            .fcw-new-toggle:hover { background: #f8f9fb; }
            .fcw-seller-list { padding: 4px 0; }
            .fcw-seller-btn {
                display: flex; align-items: center; gap: 8px;
                padding: 7px 12px; width: 100%;
                font-size: .78rem; font-weight: 500; color: #2D9F4E;
                background: transparent; border: none;
                cursor: pointer; text-align: left; font-family: 'Inter', sans-serif;
                transition: background .12s;
            }
            .fcw-seller-btn:hover { background: #FFF9E3; }
            .fcw-seller-icon {
                width: 26px; height: 26px; border-radius: 50%;
                background: #E8F5E9; display: flex; align-items: center; justify-content: center;
                flex-shrink: 0; color: #2D9F4E;
            }
            /* ── Chat area ── */
            .fcw-chat { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #fff; }
            .fcw-chat-header {
                padding: 10px 14px; border-bottom: 1px solid #eef0f3;
                display: flex; align-items: center; gap: 9px;
                background: #fff; flex-shrink: 0;
            }
            .fcw-back-btn { display: none; background: transparent; border: none; padding: 3px 5px 3px 0; cursor: pointer; color: #6b7280; }
            @@media (max-width: 480px) { .fcw-back-btn { display: flex; } }
            .fcw-chat-avatar {
                width: 30px; height: 30px; border-radius: 50%;
                background: linear-gradient(135deg, #FFE17B, #F9C74F);
                display: flex; align-items: center; justify-content: center;
                flex-shrink: 0; color: #424242;
            }
            .fcw-chat-name { font-size: .875rem; font-weight: 600; color: #111827; line-height: 1.2; }
            .fcw-chat-sub  { font-size: .68rem; color: #9ca3af; }
            .fcw-messages {
                flex: 1; overflow-y: auto; padding: 14px;
                display: flex; flex-direction: column; gap: 10px;
                background: #f8f9fb;
            }
            .fcw-messages::-webkit-scrollbar { width: 4px; }
            .fcw-messages::-webkit-scrollbar-track { background: transparent; }
            .fcw-messages::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
            .fcw-date-divider {
                text-align: center; font-size: .65rem; color: #9ca3af; font-weight: 500;
                margin: 2px 0; display: flex; align-items: center; gap: 8px;
            }
            .fcw-date-divider::before, .fcw-date-divider::after {
                content: ''; flex: 1; height: 1px; background: #e5e7eb;
            }
            .fcw-bubble-row { display: flex; }
            .fcw-bubble-row.me   { justify-content: flex-end; }
            .fcw-bubble-row.them { justify-content: flex-start; }
            .fcw-bubble {
                max-width: 70%; padding: 8px 13px; border-radius: 16px;
                font-size: .8125rem; line-height: 1.5; word-break: break-word;
            }
            .fcw-bubble-row.me .fcw-bubble {
                background: #2D9F4E; color: #fff;
                border-bottom-right-radius: 4px;
            }
            .fcw-bubble-row.them .fcw-bubble {
                background: #fff; color: #111827;
                border: 1px solid #e5e7eb;
                border-bottom-left-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,.04);
            }
            .fcw-bubble-time { display: block; font-size: .6rem; margin-top: 3px; opacity: .55; }
            .fcw-bubble-row.me   .fcw-bubble-time { text-align: right; }
            .fcw-bubble-row.them .fcw-bubble-time { color: #6b7280; }
            /* ── Placeholder ── */
            .fcw-placeholder {
                flex: 1; display: flex; flex-direction: column;
                align-items: center; justify-content: center; gap: 10px;
                background: #f8f9fb; padding: 24px;
            }
            .fcw-placeholder-icon {
                width: 48px; height: 48px; border-radius: 50%;
                background: #E8F5E9; display: flex; align-items: center; justify-content: center;
                color: #2D9F4E;
            }
            .fcw-placeholder-text { font-size: .8rem; color: #9ca3af; text-align: center; margin: 0; line-height: 1.5; }
            /* ── Input ── */
            .fcw-input-bar {
                padding: 10px 12px; border-top: 1px solid #eef0f3;
                display: flex; gap: 8px; align-items: center; background: #fff; flex-shrink: 0;
            }
            .fcw-input {
                flex: 1; padding: 8px 14px; font-size: .8125rem; color: #111827;
                background: #f3f4f6; border: 1px solid transparent; border-radius: 20px;
                outline: none; font-family: 'Inter', sans-serif; transition: border-color .13s, background .13s;
            }
            .fcw-input:focus { border-color: #F9C74F; background: #fff; }
            .fcw-input::placeholder { color: #9ca3af; }
            .fcw-send-btn {
                width: 33px; height: 33px; border-radius: 50%;
                background: #2D9F4E; border: none; cursor: pointer;
                display: flex; align-items: center; justify-content: center;
                color: #fff; flex-shrink: 0; transition: background .13s, transform .13s;
            }
            .fcw-send-btn:hover    { background: #1B7A37; transform: scale(1.05); }
            .fcw-send-btn:disabled { opacity: .4; cursor: not-allowed; transform: none; }
            /* ── Empty ── */
            .fcw-empty-state { padding: 20px 12px; font-size: .75rem; color: #9ca3af; text-align: center; line-height: 1.6; }
        </style>
        @endif
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex flex-col @if(request()->is('seller*')) seller-bg @elseif(request()->is('admin*')) admin-bg @else user-bg @endif {{ request()->routeIs('seller.register', 'seller.login') ? 'bg-[#f2f5f4]' : '' }}">
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
            @if(!request()->is('admin/*', 'seller/*'))
                @include('layouts.footer')
            @endif


        </div>

        @livewireScripts
        <x-sweetalert />

        {{-- Floating chat widget: visible on shopping pages only --}}
        @if($showChatWidget)
            @livewire('customer.chat-widget')
        @endif
    </body>
</html>
