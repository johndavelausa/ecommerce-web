<?php

use App\Models\Address;
use Illuminate\Support\Facades\Auth;

/** @var \App\Models\User|null $user */
$user = Auth::user();
$addresses = $user ? $user->addresses()->orderByDesc('is_default')->orderBy('created_at')->get() : collect();
?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Saved addresses') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Manage your delivery addresses and choose a default for faster checkout.') }}
        </p>
    </header>

    <div class="mt-4 space-y-4">
        @if($addresses->isEmpty())
            <p class="text-sm text-gray-500">
                You have no saved addresses yet. Your current single address field will still be used at checkout.
            </p>
        @else
            <div class="grid gap-3">
                @foreach($addresses as $addr)
                    <div class="border rounded-md p-3 text-sm flex justify-between gap-3">
                        <div>
                            <div class="font-semibold text-gray-900">
                                {{ $addr->label }}
                                @if($addr->is_default)
                                    <span class="ml-1 text-xs text-green-700">(Default)</span>
                                @endif
                            </div>
                            <div class="text-gray-700">
                                {{ $addr->recipient_name ?: $user->name }}
                            </div>
                            <div class="text-gray-700">
                                {{ $addr->line1 }}@if($addr->line2), {{ $addr->line2 }}@endif
                            </div>
                            <div class="text-gray-700">
                                {{ $addr->city }}@if($addr->region), {{ $addr->region }}@endif @if($addr->postal_code) {{ $addr->postal_code }}@endif
                            </div>
                            @if($addr->phone)
                                <div class="text-gray-700">
                                    Phone: {{ $addr->phone }}
                                </div>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 whitespace-nowrap">
                            Created {{ optional($addr->created_at)->diffForHumans() }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('profile.addresses.store') }}" class="mt-6 space-y-4">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="label" value="Label" />
                <x-text-input id="label" name="label" type="text" class="mt-1 block w-full" required placeholder="e.g. Home, Office" />
                <x-input-error class="mt-2" :messages="$errors->get('label')" />
            </div>
            <div>
                <x-input-label for="recipient_name" value="Recipient name" />
                <x-text-input id="recipient_name" name="recipient_name" type="text" class="mt-1 block w-full" placeholder="{{ $user?->name }}" />
                <x-input-error class="mt-2" :messages="$errors->get('recipient_name')" />
            </div>
        </div>
        <div>
            <x-input-label for="line1" value="Address line 1" />
            <x-text-input id="line1" name="line1" type="text" class="mt-1 block w-full" required />
            <x-input-error class="mt-2" :messages="$errors->get('line1')" />
        </div>
        <div>
            <x-input-label for="line2" value="Address line 2" />
            <x-text-input id="line2" name="line2" type="text" class="mt-1 block w-full" />
            <x-input-error class="mt-2" :messages="$errors->get('line2')" />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="city" value="City / Municipality" />
                <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('city')" />
            </div>
            <div>
                <x-input-label for="region" value="Province / Region" />
                <x-text-input id="region" name="region" type="text" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('region')" />
            </div>
            <div>
                <x-input-label for="postal_code" value="Postal code" />
                <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
            <div>
                <x-input-label for="phone" value="Phone" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>
            <div class="flex items-center gap-2 mt-4 md:mt-6">
                <input id="is_default" name="is_default" type="checkbox" value="1"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <label for="is_default" class="text-sm text-gray-700">
                    Set as default shipping address
                </label>
            </div>
        </div>
        <div class="flex items-center gap-4 mt-2">
            <x-primary-button>{{ __('Save address') }}</x-primary-button>
        </div>
    </form>
</section>

