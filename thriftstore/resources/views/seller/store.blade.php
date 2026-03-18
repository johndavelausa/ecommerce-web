<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Store & Profile Settings') }}
        </h2>
    </x-slot>

    <div class="flex min-h-screen">
        @include('layouts.seller-sidebar')
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <livewire:seller.store-settings />
            </div>
        </main>
    </div>
</x-app-layout>

