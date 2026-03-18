<?php

use App\Models\Address;
use Illuminate\Support\Facades\Auth;

/** @var \App\Models\User|null $user */
$user = Auth::user();
$addresses = $user ? $user->addresses()->orderByDesc('is_default')->orderBy('created_at')->get() : collect();
?>

<section x-data="{ showAddressForm: false }">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-5 mb-5">
        <div>
            <h2 class="text-xl font-bold text-gray-900">
                Saved Addresses
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Manage your shipping and billing locations.
            </p>
        </div>
        <button type="button" @click="showAddressForm = !showAddressForm" class="flex shrink-0 items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-bold text-[#1a3c2e] hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm">
            <svg class="h-4 w-4 text-[#2d6c50]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Add New
        </button>
    </header>

    <div class="space-y-4 mb-6">
        @if($addresses->isEmpty())
            <div class="rounded-xl border border-gray-200/80 bg-gray-50/50 p-6 text-center text-sm text-gray-500">
                You have no saved addresses yet.
            </div>
        @else
            @foreach($addresses as $addr)
                @php
                    $isHome = str_contains(strtolower($addr->label), 'home');
                    $isOffice = str_contains(strtolower($addr->label), 'office');
                @endphp
                <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4 transition-all hover:border-[#2d6c50]/40 hover:shadow-sm">
                    <div class="flex flex-1 items-start gap-4">
                        {{-- Icon Box --}}
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $isHome ? 'bg-[#E3EBE7] text-[#2d6c50]' : ($isOffice ? 'bg-[#F1F5F9] text-[#475569]' : 'bg-gray-100 text-gray-500') }}">
                            @if($isHome)
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M11.47 3.84a.75.75 0 011.06 0l8.99 9a.75.75 0 11-1.06 1.06l-1.46-1.46V20.25a.75.75 0 01-.75.75h-4.5a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-2.5a.75.75 0 00-.75.75v4.5a.75.75 0 01-.75.75H4.5a.75.75 0 01-.75-.75v-7.81L2.28 13.9a.75.75 0 11-1.06-1.06l10.25-10z"/>
                                </svg>
                            @elseif($isOffice)
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M7.5 3.375c0-1.036.84-1.875 1.875-1.875h5.25c1.036 0 1.875.84 1.875 1.875v1.125h2.25c1.036 0 1.875.84 1.875 1.875v12.75c0 1.036-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 011.5 19.125V6.375c0-1.036.84-1.875 1.875-1.875h2.25V3.375zm1.5 1.125v1.125h6V4.5a.375.375 0 00-.375-.375h-5.25a.375.375 0 00-.375.375zM3 6.375v4.86c.642.502 1.488.948 2.508 1.295A20.306 20.306 0 0012 13.5c2.618 0 5.068-.337 7.492-.97.1-.03.201-.061.3-.092v-6.063a.375.375 0 00-.375-.375H3.375a.375.375 0 00-.375.375zM12 15c-2.454 0-4.755.289-6.85.803a18.8 18.8 0 00-2.15.65v2.672c0 .207.168.375.375.375h17.25a.375.375 0 00.375-.375v-2.67c-.66.27-1.378.508-2.15.65C16.892 14.71 14.72 15 12 15z"/>
                                </svg>
                            @else
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>

                        {{-- Details --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="text-[15px] font-bold text-gray-900">{{ $addr->label }}</h3>
                                @if($addr->is_default)
                                    <span class="rounded bg-[#E3EBE7] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#2d6c50]">Default</span>
                                @endif
                            </div>
                            <p class="truncate text-[14px] text-gray-500 leading-snug">
                                {{ $addr->line1 }}@if($addr->line2), {{ $addr->line2 }}@endif
                            </p>
                            <p class="truncate text-[14px] text-gray-500 leading-snug">
                                {{ $addr->city }}@if($addr->region), {{ $addr->region }}@endif @if($addr->postal_code) {{ $addr->postal_code }}@endif
                            </p>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 shrink-0 self-center">
                        <button class="flex h-8 w-8 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition" aria-label="Edit address">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                            </svg>
                        </button>
                        <form method="POST" action="">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="flex h-8 w-8 items-center justify-center rounded text-gray-400 hover:bg-red-50 hover:text-red-500 transition" aria-label="Delete address">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Form Dropdown --}}
    <div x-cloak x-show="showAddressForm" x-collapse>
        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 mt-2 shadow-inner">
            <h4 class="mb-4 text-sm font-bold text-gray-800">Add New Address</h4>
            <form method="POST" action="{{ route('profile.addresses.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="label" value="Label" />
                        <x-text-input id="label" name="label" type="text" class="mt-1 block w-full bg-white" required placeholder="e.g. Home, Office" />
                        <x-input-error class="mt-2" :messages="$errors->get('label')" />
                    </div>
                    <div>
                        <x-input-label for="recipient_name" value="Recipient name" />
                        <x-text-input id="recipient_name" name="recipient_name" type="text" class="mt-1 block w-full bg-white" placeholder="{{ $user?->name }}" />
                        <x-input-error class="mt-2" :messages="$errors->get('recipient_name')" />
                    </div>
                </div>
                <div>
                    <x-input-label for="line1" value="Address line 1" />
                    <x-text-input id="line1" name="line1" type="text" class="mt-1 block w-full bg-white" required />
                    <x-input-error class="mt-2" :messages="$errors->get('line1')" />
                </div>
                <div>
                    <x-input-label for="line2" value="Address line 2" />
                    <x-text-input id="line2" name="line2" type="text" class="mt-1 block w-full bg-white" />
                    <x-input-error class="mt-2" :messages="$errors->get('line2')" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="city" value="City / Municipality" />
                        <x-text-input id="city" name="city" type="text" class="mt-1 block w-full bg-white" />
                        <x-input-error class="mt-2" :messages="$errors->get('city')" />
                    </div>
                    <div>
                        <x-input-label for="region" value="Province / Region" />
                        <x-text-input id="region" name="region" type="text" class="mt-1 block w-full bg-white" />
                        <x-input-error class="mt-2" :messages="$errors->get('region')" />
                    </div>
                    <div>
                        <x-input-label for="postal_code" value="Postal code" />
                        <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full bg-white" />
                        <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full bg-white" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>
                    <div class="flex items-center gap-2 mt-4 md:mt-6">
                        <input id="is_default" name="is_default" type="checkbox" value="1"
                               class="rounded border-gray-300 text-[#2d6c50] shadow-sm focus:ring-[#2d6c50]">
                        <label for="is_default" class="text-sm font-medium text-gray-700">
                            Set as default shipping address
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-4">
                    <button type="submit" class="rounded-xl bg-[#2d6c50] px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#245840] transition">
                        Save address
                    </button>
                    <button type="button" @click="showAddressForm = false" class="rounded-xl border border-gray-200 bg-white px-5 py-2 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

