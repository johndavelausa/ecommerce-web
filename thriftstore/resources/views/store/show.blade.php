@php
    $shortDescription = $seller->store_description ? \Illuminate\Support\Str::limit($seller->store_description, 200) : '';
    $hasMoreDescription = $seller->store_description && strlen($seller->store_description) > 200;
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $seller->store_name }}</h2>
    </x-slot>

    <div class="py-10">
        {{-- G1 — Store Header --}}
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="relative">
                {{-- Banner (full width within container) --}}
                <div class="h-48 sm:h-64 bg-gray-200 rounded-t-xl overflow-hidden relative z-10">
                    @if($seller->banner_path ?? null)
                        <img src="{{ asset('storage/' . $seller->banner_path) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-400 text-sm italic">Store banner</div>
                    @endif
                </div>

                {{-- Logo/avatar: Moved OUTSIDE the banner's overflow-hidden container to allow overlap --}}
                <div class="absolute bottom-[-40px] left-6 sm:left-10 z-30">
                    <div class="relative">
                        @if($seller->logo_path ?? null)
                            <img src="{{ asset('storage/' . $seller->logo_path) }}" alt="" class="w-24 h-24 sm:w-32 sm:h-32 rounded-full border-4 border-white bg-white object-cover shadow-md">
                        @elseif($seller->user && $seller->user->avatar)
                            <img src="{{ asset('storage/' . $seller->user->avatar) }}" alt="" class="w-24 h-24 sm:w-32 sm:h-32 rounded-full border-4 border-white bg-white object-cover shadow-md">
                        @else
                            <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-full border-4 border-white bg-gray-300 flex items-center justify-center text-gray-500 text-3xl font-bold shadow-md">
                                {{ strtoupper(substr($seller->store_name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-b-xl shadow-sm border border-gray-100 overflow-hidden relative z-20">
                {{-- Increase top padding on mobile (pt-24) to avoid avatar overlap, and left padding --}}
                <div class="px-6 pb-8 pt-16 sm:px-10 sm:py-6 sm:pl-48 lg:pl-56">
                    {{-- Store name (large) --}}
                    <h1 class="text-3xl font-bold text-gray-900 mb-1">
                        {{ $seller->store_name }}
                    </h1>

                    {{-- Rating, review count, member since, Open/Closed --}}
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-4 text-sm text-gray-600">
                        @if($reviewCount > 0)
                            <span class="flex items-center gap-1.5">
                                <span class="text-amber-500">★</span>
                                <span class="font-semibold text-gray-900">{{ number_format($storeRating, 1) }} / 5</span>
                            </span>
                            <span>{{ $reviewCount }} {{ $reviewCount === 1 ? 'review' : 'reviews' }}</span>
                        @else
                            <span class="text-gray-400">No reviews yet</span>
                        @endif
                        @if($memberSince)
                            <span>Member since {{ $memberSince->format('F Y') }}</span>
                        @endif
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $seller->is_open ? 'bg-[#E8F5E9] text-[#1B7A37]' : 'bg-green-100 text-green-800' }}">
                            {{ $seller->is_open ? 'Open' : 'Closed' }}
                        </span>
                    </div>

                    {{-- Store description with 'View More' toggle (Alpine.js) --}}
                    @if($seller->store_description)
                        <div x-data="{ expanded: false, limit: 200 }" class="mb-6">
                            <div class="text-sm text-gray-500 leading-relaxed overflow-hidden">
                                <p x-show="!expanded" class="whitespace-pre-wrap">
                                    {{ Str::limit($seller->store_description, 200, '') }}<span x-show="{{ strlen($seller->store_description) }} > limit">...</span>
                                </p>
                                <p x-show="expanded" x-cloak class="whitespace-pre-wrap">{{ $seller->store_description }}</p>
                            </div>
                            @if(strlen($seller->store_description) > 200)
                                <button @click="expanded = !expanded" 
                                        type="button" 
                                        class="mt-1 text-xs font-bold text-[#2D9F4E] hover:text-[#1B7A37] transition-colors focus:outline-none">
                                    <span x-text="expanded ? 'View less' : 'View more'">View more</span>
                                </button>
                            @endif
                        </div>
                    @endif

                    {{-- Contact Seller + Browse --}}
                    <div class="flex flex-wrap gap-2">
                        @auth
                            @if(auth()->user()->hasRole('customer'))
                                <a href="{{ route('customer.messages', ['seller' => $seller->id]) }}"
                                   class="inline-flex items-center px-6 py-2 bg-[#2D9F4E] text-white rounded-lg text-sm font-semibold hover:bg-[#1B7A37] transition-colors shadow-sm">
                                    Contact Seller
                                </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}?intended={{ urlencode(route('customer.messages', ['seller' => $seller->id])) }}"
                               class="inline-flex items-center px-6 py-2 bg-[#2D9F4E] text-white rounded-lg text-sm font-semibold hover:bg-[#1B7A37] transition-colors shadow-sm">
                                Contact Seller
                            </a>
                        @endauth
                        <a href="{{ $catalogUrl }}"
                           class="inline-flex items-center px-6 py-2 border border-gray-200 text-gray-600 bg-white rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors shadow-sm">
                            Browse products
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- G2 — Store Stats (below store header) --}}
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 mt-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="grid grid-cols-2 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-gray-100 py-8">
                    <div class="px-6 text-center md:text-left py-4 md:py-0">
                        <div class="text-3xl font-bold text-gray-900">{{ number_format($activeProductsCount) }}</div>
                        <div class="text-xs text-gray-400 font-medium uppercase tracking-wider mt-1">Active products</div>
                    </div>
                    <div class="px-6 text-center md:text-left py-4 md:py-0">
                        <div class="text-3xl font-bold text-gray-900">{{ number_format($completedOrdersCount) }}</div>
                        <div class="text-xs text-gray-400 font-medium uppercase tracking-wider mt-1">Orders completed</div>
                    </div>
                    <div class="px-6 text-center md:text-left py-4 md:py-0">
                        <div class="text-3xl font-bold text-gray-900">
                            @if($reviewCount > 0)
                                {{ number_format($storeRating, 1) }} <span class="text-amber-500 text-2xl font-normal">★ / 5</span>
                            @else
                                —
                            @endif
                        </div>
                        <div class="text-xs text-gray-400 font-medium uppercase tracking-wider mt-1">Average rating</div>
                    </div>
                    <div class="px-6 text-center md:text-left py-4 md:py-0">
                        <div class="text-3xl font-bold text-gray-900">
                            {{ $memberSince->format('M Y') }}
                        </div>
                        <div class="text-xs text-gray-400 font-medium uppercase tracking-wider mt-1">Member since</div>
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
