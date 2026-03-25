<x-app-layout>
    @php
        $homepageBackgroundUrl = \App\Models\SystemSetting::get_url('background_path', asset('background_img/gela.jpeg'));
    @endphp

    {{-- Hero background image --}}
    <div
        class="h-[calc(100vh-4rem)] w-full bg-cover bg-top bg-no-repeat"
        style="background-image: url('{{ $homepageBackgroundUrl }}');"
    ></div>

    {{-- Announcements + New Arrivals --}}
    <div class="py-8 lg:py-10">
        <div class="mx-auto w-full max-w-[1440px] px-4 md:px-8 lg:px-12">
            {{-- New Arrivals (Livewire — no page reload) --}}
            <livewire:customer.new-arrivals />
        </div>
    </div>
</x-app-layout>
