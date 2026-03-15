<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'ThriftStore') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="m-0 p-0 overflow-hidden">
        @php
            $bgPath = \App\Models\SystemSetting::get('background_path');
            $bgUrl  = $bgPath
                ? asset('storage/' . $bgPath)
                : asset('background_img/gela.jpeg');
        @endphp
        <div class="w-screen h-screen bg-cover bg-center bg-no-repeat"
             style="background-image: url('{{ $bgUrl }}');">
        </div>
    </body>
</html>
