<x-app-layout>
    <div class="flex min-h-screen">
        @include('layouts.admin-sidebar')
        <main class="flex-1 p-6 bg-gray-50">
            <div class="max-w-7xl mx-auto">
                <livewire:admin.order-list />
            </div>
        </main>
    </div>
</x-app-layout>
