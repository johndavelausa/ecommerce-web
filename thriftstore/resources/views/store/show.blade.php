@php
    $shortDescription = $seller->store_description ? \Illuminate\Support\Str::limit($seller->store_description, 200) : '';
    $hasMoreDescription = $seller->store_description && strlen($seller->store_description) > 200;
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $seller->store_name }}</h2>
    </x-slot>

    <div class="py-0">
        {{-- G1 — Store Header --}}
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            {{-- Banner (full width within container) --}}
            <div class="relative h-40 sm:h-52 bg-gray-200 rounded-t-lg overflow-hidden">
                @if($seller->banner_path ?? null)
                    <img src="{{ asset('storage/' . $seller->banner_path) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                @else
                    <div class="w-full h-full flex items-center justify-center text-gray-400 text-sm">Store banner</div>
                @endif
                {{-- Logo/avatar overlapping bottom left --}}
                <div class="absolute bottom-0 left-4 transform translate-y-1/2">
                    @if($seller->logo_path ?? null)
                        <img src="{{ asset('storage/' . $seller->logo_path) }}" alt="" class="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-4 border-white bg-white object-cover shadow">
                    @elseif($seller->user && $seller->user->avatar)
                        <img src="{{ asset('storage/' . $seller->user->avatar) }}" alt="" class="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-4 border-white bg-white object-cover shadow">
                    @else
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-4 border-white bg-gray-300 flex items-center justify-center text-gray-500 text-2xl font-semibold shadow">
                            {{ strtoupper(substr($seller->store_name, 0, 1)) }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-b-lg shadow overflow-hidden border border-t-0 border-gray-200">
                <div class="pl-4 pr-6 pt-2 pb-6 sm:pl-28 sm:pt-6">
                    {{-- Store name (large) + Verified --}}
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center gap-2 flex-wrap">
                        {{ $seller->store_name }}
                        @if($seller->is_verified ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-sm font-medium bg-blue-100 text-blue-800" title="Verified seller">✓ Verified</span>
                        @endif
                    </h1>

                    {{-- Rating, review count, member since, Open/Closed --}}
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-sm text-gray-600">
                        @if($reviewCount > 0)
                            <span class="flex items-center gap-1">
                                <span class="text-amber-500">★</span>
                                <span class="font-medium text-gray-900">{{ number_format($storeRating, 1) }}</span>
                                <span>/ 5</span>
                            </span>
                            <span>{{ $reviewCount }} {{ $reviewCount === 1 ? 'review' : 'reviews' }}</span>
                        @else
                            <span class="text-gray-400">No reviews yet</span>
                        @endif
                        @if($memberSince)
                            <span>Member since {{ $memberSince->format('F Y') }}</span>
                        @endif
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $seller->is_open ? 'bg-[#E8F5E9] text-[#1B7A37]' : 'bg-gray-100 text-gray-600' }}">
                            {{ $seller->is_open ? 'Open' : 'Closed' }}
                        </span>
                    </div>

                    {{-- Store description (short, expandable) --}}
                    @if($seller->store_description)
                        <div class="mt-4" x-data="{ expanded: false }">
                            <p class="text-sm text-gray-600 whitespace-pre-wrap" x-show="!expanded" x-transition>
                                {{ $shortDescription }}{{ $hasMoreDescription ? '…' : '' }}
                            </p>
                            @if($hasMoreDescription)
                                <div x-show="expanded" x-transition x-cloak>
                                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $seller->store_description }}</p>
                                </div>
                                <button type="button" @click="expanded = ! expanded" class="mt-1 text-sm text-[#2D9F4E] hover:underline">
                                    <span x-text="expanded ? 'Show less' : 'Show more'">Show more</span>
                                </button>
                            @endif
                        </div>
                    @endif

                    {{-- Contact Seller --}}
                    <div class="mt-4 flex flex-wrap gap-2">
                        @auth
                            @if(auth()->user()->hasRole('customer'))
                                <a href="{{ route('customer.messages', ['seller' => $seller->id]) }}"
                                   class="inline-flex items-center px-4 py-2 bg-[#2D9F4E] text-white rounded-md text-sm font-medium hover:bg-[#1B7A37] transition-colors">
                                    Contact Seller
                                </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}?intended={{ urlencode(route('customer.messages', ['seller' => $seller->id])) }}"
                               class="inline-flex items-center px-4 py-2 bg-[#2D9F4E] text-white rounded-md text-sm font-medium hover:bg-[#1B7A37] transition-colors">
                                Log in to contact seller
                            </a>
                        @endauth
                        <a href="{{ $catalogUrl }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50">
                            Browse products
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- G2 — Store Stats (below store header) --}}
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 mt-6">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-6">
                    <div class="text-center sm:text-left">
                        <div class="text-2xl font-bold text-gray-900">{{ number_format($activeProductsCount) }}</div>
                        <div class="text-sm text-gray-500">Active products</div>
                    </div>
                    <div class="text-center sm:text-left">
                        <div class="text-2xl font-bold text-gray-900">{{ number_format($completedOrdersCount) }}</div>
                        <div class="text-sm text-gray-500">Orders completed</div>
                    </div>
                    <div class="text-center sm:text-left">
                        <div class="text-2xl font-bold text-gray-900">
                            @if($reviewCount > 0)
                                {{ number_format($storeRating, 1) }} <span class="text-amber-500 text-lg">★</span> / 5
                            @else
                                —
                            @endif
                        </div>
                        <div class="text-sm text-gray-500">Average rating</div>
                    </div>
                    <div class="text-center sm:text-left">
                        <div class="text-2xl font-bold text-gray-900">
                            @if($memberSince)
                                {{ $memberSince->format('M Y') }}
                            @else
                                —
                            @endif
                        </div>
                        <div class="text-sm text-gray-500">Member since</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Business hours (below header card) --}}
        @if($seller->business_hours)
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 mt-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Business hours</h3>
                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $seller->business_hours }}</p>
                </div>
            </div>
        @endif

        {{-- G3 — Store Tabs: All Products | Categories | Reviews --}}
        <livewire:store.store-tabs :seller-id="$seller->id" />
    </div>
</x-app-layout>
