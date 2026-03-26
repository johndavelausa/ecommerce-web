<x-app-layout>
    <div class="flex min-h-screen">
        @include('layouts.admin-sidebar')
        <main class="flex-1 p-6 bg-gray-50">
            <div class="max-w-7xl mx-auto">
                <div class="mb-6">
                    <h1 class="text-2xl font-extrabold text-[#0F3D22]">Returns & Refunds</h1>
                    <p class="text-sm text-gray-500 mt-1">Review and resolve customer disputes, return requests, and refund processes.</p>
                </div>
                <livewire:admin.order-disputes />
            </div>
        </main>
    </div>
</x-app-layout>
