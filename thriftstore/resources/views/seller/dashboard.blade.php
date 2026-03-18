<x-app-layout>
    <div class="flex min-h-screen">
        @include('layouts.seller-sidebar')
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <livewire:seller.dashboard-metrics />
            </div>
        </main>
    </div>
</x-app-layout>
