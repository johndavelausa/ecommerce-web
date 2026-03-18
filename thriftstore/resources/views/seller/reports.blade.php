<x-app-layout>
    <div class="flex min-h-screen">
        @include('layouts.seller-sidebar')
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <livewire:seller.reports />
            </div>
        </main>
    </div>
</x-app-layout>

