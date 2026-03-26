<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 | Server Error</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-[#fdfdfd] min-h-screen flex items-center justify-center p-6 select-none border-t-[6px] border-emerald-600">
    <div class="text-center">
        <!-- Big Emerald 500 -->
        <h1 class="text-[12rem] leading-none font-black text-emerald-600 opacity-[0.07] absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 select-none pointer-events-none">500</h1>

        <div class="relative z-10 max-w-lg mx-auto">
            <div class="flex items-center justify-center gap-4 mb-8">
                <h2 class="text-4xl font-extrabold text-gray-800 border-r-2 border-emerald-600 pr-5">500</h2>
                <div class="text-left">
                    <p class="text-xs font-bold text-emerald-600 uppercase tracking-widest mb-1">Critical System Error</p>
                    <p class="text-sm font-medium text-gray-400 uppercase tracking-wider">Internal Server Connection</p>
                </div>
            </div>
            
            <div class="bg-white/50 backdrop-blur-sm p-8 rounded-3xl border border-emerald-50/50 shadow-sm">
                <h3 class="text-2xl font-bold text-gray-900 mb-4 italic">"We'll be back shortly!"</h3>
                
                <p class="text-gray-600 text-lg mb-6 leading-relaxed">
                   {{ $message }}
                </p>

                <p class="text-gray-400 font-medium italic text-sm border-t border-emerald-50 pt-6">
                    Thank you for your patience while we recalibrate our systems.
                </p>
            </div>

            @if(config('app.debug'))
                <div class="mt-12 text-gray-300 text-[10px] uppercase font-bold tracking-widest opacity-40">
                    ERR_SYSTEM_RETRY — REF: {{ substr(md5($message), 0, 10) }}
                </div>
            @endif
        </div>
    </div>
</body>
</html>
