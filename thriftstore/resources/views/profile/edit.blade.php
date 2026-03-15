<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(isset($purchaseStats))
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">{{ __('Purchase history') }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="text-sm text-gray-500">{{ __('Total orders placed') }}</div>
                            <div class="text-xl font-semibold text-gray-900">{{ number_format($purchaseStats['total_orders']) }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="text-sm text-gray-500">{{ __('Total amount spent') }}</div>
                            <div class="text-xl font-semibold text-gray-900">₱{{ number_format($purchaseStats['total_spent'], 2) }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="text-sm text-gray-500">{{ __('Favorite category') }}</div>
                            <div class="text-xl font-semibold text-gray-900">{{ $purchaseStats['favorite_category'] ?? __('—') }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.addresses')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
