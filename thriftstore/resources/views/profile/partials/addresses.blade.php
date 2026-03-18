<?php

use App\Models\Address;
use Illuminate\Support\Facades\Auth;

/** @var \App\Models\User|null $user */
$user = Auth::user();
$addresses = $user ? $user->addresses()->orderByDesc('is_default')->orderBy('created_at')->get() : collect();
?>

<section x-data="{ 
    showAddressForm: false, 
    editingAddress: null,
    addressData: {
        label: '',
        recipient_name: '',
        line1: '',
        line2: '',
        city: '',
        region: '',
        postal_code: '',
        phone: '',
        is_default: false
    },
    editAddress(addr) {
        this.editingAddress = addr;
        this.addressData = {
            label: addr.label || '',
            recipient_name: addr.recipient_name || '',
            line1: addr.line1 || '',
            line2: addr.line2 || '',
            city: addr.city || '',
            region: addr.region || '',
            postal_code: addr.postal_code || '',
            phone: addr.phone || '',
            is_default: !!addr.is_default
        };
        this.showAddressForm = true;
        // Scroll to form
        $nextTick(() => {
            document.getElementById('address-form-container').scrollIntoView({ behavior: 'smooth' });
        });
    },
    resetForm() {
        this.editingAddress = null;
        this.addressData = {
            label: '',
            recipient_name: '',
            line1: '',
            line2: '',
            city: '',
            region: '',
            postal_code: '',
            phone: '',
            is_default: false
        };
        this.showAddressForm = false;
    }
}">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-6 mb-8 mt-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 leading-none">
                Saved Addresses
            </h2>
            <p class="mt-2 text-sm text-gray-500">
                Manage your shipping and billing locations.
            </p>
        </div>
        <button type="button" @click="resetForm(); showAddressForm = true" class="profile-secondary-button !py-2 !px-4 text-xs">
            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Add New
        </button>
    </header>

    <div class="flex flex-col gap-4 mb-8">
        @if($addresses->isEmpty())
            <div class="col-span-full rounded-xl border-2 border-dashed border-gray-100 bg-gray-50/50 p-8 text-center text-sm text-gray-400">
                You have no saved addresses yet.
            </div>
        @else
            @foreach($addresses as $addr)
                @php
                    $isHome = str_contains(strtolower($addr->label), 'home');
                    $isOffice = str_contains(strtolower($addr->label), 'office');
                @endphp
                <div class="flex flex-col rounded-xl border border-gray-100 bg-white p-4 transition-all hover:border-[#2d6c50]/20 hover:shadow-md relative group">
                    <div class="flex items-start gap-4 mb-3">
                        {{-- Icon Box --}}
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $isHome ? 'bg-[#E3EBE7] text-[#2d6c50]' : ($isOffice ? 'bg-[#F1F5F9] text-[#475569]' : 'bg-gray-50 text-gray-400') }}">
                            @if($isHome)
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M11.47 3.84a.75.75 0 011.06 0l8.99 9a.75.75 0 11-1.06 1.06l-1.46-1.46V20.25a.75.75 0 01-.75.75h-4.5a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-2.5a.75.75 0 00-.75.75v4.5a.75.75 0 01-.75.75H4.5a.75.75 0 01-.75-.75v-7.81L2.28 13.9a.75.75 0 11-1.06-1.06l10.25-10z"/>
                                </svg>
                            @elseif($isOffice)
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M7.5 3.375c0-1.036.84-1.875 1.875-1.875h5.25c1.036 0 1.875.84 1.875 1.875v1.125h2.25c1.036 0 1.875.84 1.875 1.875v12.75c0 1.036-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 011.5 19.125V6.375c0-1.036.84-1.875 1.875-1.875h2.25V3.375zm1.5 1.125v1.125h6V4.5a.375.375 0 00-.375-.375h-5.25a.375.375 0 00-.375.375zM3 6.375v4.86c.642.502 1.488.948 2.508 1.295A20.306 20.306 0 0012 13.5c2.618 0 5.068-.337 7.492-.97.1-.03.201-.061.3-.092v-6.063a.375.375 0 00-.375-.375H3.375a.375.375 0 00-.375.375zM12 15c-2.454 0-4.755.289-6.85.803a18.8 18.8 0 00-2.15.65v2.672c0 .207.168.375.375.375h17.25a.375.375 0 00.375-.375v-2.67c-.66.27-1.378.508-2.15.65C16.892 14.71 14.72 15 12 15z"/>
                                </svg>
                            @else
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>

                        {{-- Details --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="text-sm font-bold text-gray-900">{{ $addr->label }}</h3>
                                @if($addr->is_default)
                                    <span class="rounded-lg bg-green-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-green-700 border border-green-100">Default</span>
                                @endif
                            </div>
                            <p class="truncate text-xs font-semibold text-gray-700">
                                {{ $addr->recipient_name ?: $user?->name }}
                            </p>
                        </div>

                        {{-- Actions Floating --}}
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                             <button 
                                @click="editAddress({{ json_encode($addr) }})"
                                class="h-8 w-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-50 hover:text-gray-600 transition" 
                                aria-label="Edit address"
                            >
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                </svg>
                            </button>

                            <form method="POST" action="{{ route('profile.addresses.destroy', $addr->id) }}" onsubmit="return confirm('Are you sure you want to delete this address?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="h-8 w-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500 transition" aria-label="Delete address">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="text-[13px] text-gray-500 space-y-0.5 mb-4">
                        <p class="leading-relaxed">{{ $addr->line1 }}@if($addr->line2), {{ $addr->line2 }}@endif</p>
                        <p class="leading-relaxed">{{ $addr->city }}@if($addr->region), {{ $addr->region }}@endif @if($addr->postal_code) {{ $addr->postal_code }}@endif</p>
                        @if($addr->phone)
                            <p class="flex items-center gap-1.5 mt-2 text-gray-400">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                {{ $addr->phone }}
                            </p>
                        @endif
                    </div>

                    @if(!$addr->is_default)
                        <div class="mt-auto pt-4 border-t border-gray-50 flex items-center justify-between">
                            <form method="POST" action="{{ route('profile.addresses.set-default', $addr->id) }}" class="w-full">
                                @csrf
                                <button type="submit" class="w-full text-center text-xs font-bold text-[#2d6c50] hover:bg-[#2d6c50] hover:text-white py-2 rounded-lg border border-[#2d6c50]/20 transition-all duration-200">
                                    Set as Default
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    {{-- Form Dropdown --}}
    <div id="address-form-container" x-cloak x-show="showAddressForm" x-collapse>
        <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-6 mt-4 shadow-inner relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4">
                 <button type="button" @click="resetForm()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <h4 class="mb-6 text-base font-bold text-gray-800" x-text="editingAddress ? 'Edit Address Details' : 'Register New Address'"></h4>
            
            <form method="POST" :action="editingAddress ? `/profile/addresses/${editingAddress.id}` : '{{ route('profile.addresses.store') }}'" class="space-y-6">
                @csrf
                <template x-if="editingAddress">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-input-label for="label" value="Address Label" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                        <x-text-input id="label" name="label" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-white" required placeholder="e.g. Home, Office" x-model="addressData.label" />
                        <x-input-error class="mt-2" :messages="$errors->get('label')" />
                    </div>
                    <div>
                        <x-input-label for="recipient_name" value="Recipient Full Name" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                        <x-text-input id="recipient_name" name="recipient_name" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-white" placeholder="{{ $user?->name }}" x-model="addressData.recipient_name" />
                        <x-input-error class="mt-2" :messages="$errors->get('recipient_name')" />
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="line1" value="Street Address" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                        <x-text-input id="line1" name="line1" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-white" required placeholder="House number, street name" x-model="addressData.line1" />
                        <x-input-error class="mt-2" :messages="$errors->get('line1')" />
                    </div>
                    <div>
                        <x-input-label for="line2" value="Apartment / Suite / Unit (Optional)" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                        <x-text-input id="line2" name="line2" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-white" x-model="addressData.line2" />
                        <x-input-error class="mt-2" :messages="$errors->get('line2')" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <x-input-label for="city" value="City / Municipality" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                        <x-text-input id="city" name="city" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-white" x-model="addressData.city" />
                        <x-input-error class="mt-2" :messages="$errors->get('city')" />
                    </div>
                    <div>
                        <x-input-label for="region" value="Province / Region" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                        <x-text-input id="region" name="region" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-white" x-model="addressData.region" />
                        <x-input-error class="mt-2" :messages="$errors->get('region')" />
                    </div>
                    <div>
                        <x-input-label for="postal_code" value="Postal Code" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                        <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-white" x-model="addressData.postal_code" />
                        <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                    <div>
                        <x-input-label for="phone" value="Phone Number" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-white" x-model="addressData.phone" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>
                    <div class="flex items-center h-[42px] px-2">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input id="is_default" name="is_default" type="checkbox" value="1" x-model="addressData.is_default" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#2d6c50]/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2d6c50]"></div>
                            <span class="ml-3 text-sm font-semibold text-gray-700">Set as default shipping address</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-8 pt-6 border-t border-gray-100">
                    <button type="submit" class="profile-primary-button !py-2.5 !px-8">
                        <span x-text="editingAddress ? 'Update Address' : 'Register Address'"></span>
                    </button>
                    <button type="button" @click="resetForm()" class="profile-secondary-button !py-2.5 !px-8">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

