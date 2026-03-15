<x-app-layout>
    @php
        $homepageBackgroundPath = \App\Models\SystemSetting::get('background_path');
        $homepageBackgroundUrl  = $homepageBackgroundPath
            ? asset('storage/' . $homepageBackgroundPath)
            : asset('background_img/gela.jpeg');
    @endphp

    {{-- Hero background image --}}
    <div class="w-full h-[calc(100vh-4rem)] bg-cover bg-top bg-no-repeat"
         style="background-image: url('{{ $homepageBackgroundUrl }}');">
    </div>

    {{-- New Arrivals (Livewire — no page reload) --}}
    <livewire:customer.new-arrivals />
</x-app-layout>
