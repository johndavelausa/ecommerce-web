<x-app-layout>
    <div class="flex min-h-screen">
        @include('layouts.admin-sidebar')
        <main class="flex-1 p-6 bg-gray-50">
            <div class="max-w-7xl mx-auto space-y-8">
                <livewire:admin.system-settings-form />
                <livewire:admin.platform-announcements />
                <livewire:admin.broadcast-announcement />
            </div>
        </main>
    </div>
</x-app-layout>
