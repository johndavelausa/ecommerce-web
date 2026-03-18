<x-app-layout>

    <div class="flex min-h-screen">
        @include('layouts.seller-sidebar')
        <main class="flex-1 p-6">
            <div class="max-w-2xl mx-auto">
                @if(session('status'))
                    <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">{{ session('status') }}</div>
                @endif
                <div class="bg-white rounded-lg shadow p-6">
                    <form method="POST" action="{{ route('seller.message-admin.store') }}">
                        @csrf
                        <label class="block text-sm font-medium text-gray-700">Message</label>
                        <textarea name="body" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        <div class="mt-4">
                            <x-primary-button type="submit">Send to Admin</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</x-app-layout>
