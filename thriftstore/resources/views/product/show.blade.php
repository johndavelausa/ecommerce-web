@php
    $threshold = (int) ($product->low_stock_threshold ?? 10);
    $stockStatus = $product->stock === 0 ? 'out' : ($product->stock <= $threshold ? 'low' : 'in');
@endphp
<x-app-layout>

    <div class="py-8 lg:py-10 bg-gray-50 min-h-screen"
         x-data="{ lightbox: false, qty: 1, ratingFilter: 'all' }">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6 flex-wrap">
                <a href="{{ route('catalog') }}" class="hover:text-[#2D9F4E] transition-colors">Home</a>
                <span class="text-gray-300">›</span>
                @if($product->category)
                    <span class="hover:text-[#2D9F4E] cursor-default transition-colors">{{ $product->category }}</span>
                    <span class="text-gray-300">›</span>
                @endif
                <a href="{{ route('customer.dashboard') }}" class="hover:text-[#2D9F4E] transition-colors">All Products</a>
                <span class="text-gray-300">›</span>
                <span class="font-semibold text-gray-800 truncate max-w-xs">{{ $product->name }}</span>
            </nav>

            {{-- ══ PRODUCT MAIN CARD ══ --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden mb-3">
                <div class="flex flex-col lg:flex-row">

                    {{-- ── Image Panel ── --}}
                    <div class="lg:w-[400px] xl:w-[440px] flex-shrink-0 p-5 border-b lg:border-b-0 lg:border-r border-gray-100">
                        <div class="aspect-square rounded-lg overflow-hidden bg-gray-50 cursor-zoom-in group relative mb-3"
                             @click="lightbox = true">
                            @if($product->image_path)
                                <img src="{{ $product->image_url }}"
                                     alt="{{ $product->name }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                     loading="lazy">
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-gray-300 gap-2">
                                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-sm">No image</span>
                                </div>
                            @endif
                            <div class="absolute bottom-2 right-2 bg-black/40 text-white text-xs px-2 py-0.5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                Click to zoom
                            </div>
                        </div>

                        {{-- Thumbnail strip --}}
                        @if($product->image_path)
                            <div class="flex gap-2 mb-4">
                                <div class="w-14 h-14 rounded border-2 border-[#2D9F4E] overflow-hidden flex-shrink-0 cursor-pointer">
                                    <img src="{{ $product->image_url }}" alt="" class="w-full h-full object-cover">
                                </div>
                            </div>
                        @endif

                        {{-- Share + Wishlist row --}}
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400">Share:</span>
                                <button type="button" x-data="{ copied: false }"
                                        @click="navigator.clipboard.writeText('{{ url()->current() }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                        class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-[#2D9F4E] transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                    <span x-show="!copied">Copy Link</span>
                                    <span x-show="copied" x-cloak class="text-[#2D9F4E]">Copied!</span>
                                </button>
                            </div>
                            @auth
                                @if(auth()->user()->hasRole('customer'))
                                    <form method="post" action="{{ route('wishlist.toggle', $product->id) }}">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 text-xs transition-colors {{ isset($inWishlist) && $inWishlist ? 'text-rose-500' : 'text-gray-400 hover:text-rose-400' }}">
                                            <svg class="w-4 h-4" fill="{{ isset($inWishlist) && $inWishlist ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                                            </svg>
                                            {{ isset($inWishlist) && $inWishlist ? 'Wishlisted' : 'Wishlist' }}
                                        </button>
                                    </form>
                                @endif
                            @endauth
                        </div>
                    </div>

                    {{-- ── Info Panel ── --}}
                    <div class="flex-1 p-6 lg:p-8 flex flex-col">

                        {{-- Name + Report --}}
                        <div class="flex items-start justify-between gap-4 mb-3">
                            <h1 class="text-lg lg:text-xl font-semibold text-gray-800 leading-snug flex-1">{{ $product->name }}</h1>
                            @auth
                                @if(auth()->user()->hasRole('customer'))
                                    @if(session('status') === 'report-submitted')
                                        <span class="text-xs text-[#2D9F4E] flex-shrink-0">Report submitted</span>
                                    @else
                                        <button type="button"
                                                onclick="document.getElementById('report-modal').classList.remove('hidden')"
                                                class="text-xs text-gray-400 hover:text-rose-500 transition-colors flex-shrink-0 mt-0.5">
                                            Report
                                        </button>
                                    @endif
                                @endif
                            @endauth
                        </div>

                        {{-- Ratings + Sold row --}}
                        <div class="flex items-center gap-3 flex-wrap pb-4 border-b border-gray-100 mb-4">
                            @if($product->reviews_count > 0)
                                @php $avgRating = $product->reviews_avg_rating ?? 0; @endphp
                                <span class="text-sm font-semibold text-amber-500 underline underline-offset-2 cursor-pointer">{{ number_format($avgRating, 1) }}</span>
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-3.5 h-3.5 {{ $i <= round($avgRating) ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <a href="#ratings" class="text-xs text-gray-400 hover:text-[#2D9F4E] underline underline-offset-2">{{ $product->reviews_count }} Ratings</a>
                                <span class="text-gray-200">|</span>
                            @else
                                <span class="text-xs text-gray-400">No Ratings Yet</span>
                                <span class="text-gray-200">|</span>
                            @endif
                            <span class="text-xs text-gray-500">
                                <span class="font-semibold text-gray-700">{{ $product->orders_count ?? 0 }}</span> Sold
                            </span>
                        </div>

                        {{-- Price block --}}
                        <div class="bg-gray-50 rounded-lg px-5 py-4 mb-5">
                            @if($product->sale_price)
                                @php $discount = round((1 - $product->sale_price / $product->price) * 100); @endphp
                                <div class="flex items-baseline gap-3 flex-wrap">
                                    <span class="text-2xl font-bold text-[#2D9F4E]">₱{{ number_format($product->sale_price, 2) }}</span>
                                    <span class="text-sm text-gray-400 line-through">₱{{ number_format($product->price, 2) }}</span>
                                    <span class="px-2 py-0.5 text-xs font-bold bg-rose-100 text-rose-600 rounded">-{{ $discount }}% OFF</span>
                                </div>
                            @else
                                <span class="text-2xl font-bold text-[#2D9F4E]">₱{{ number_format($product->price, 2) }}</span>
                            @endif
                        </div>

                        {{-- Info rows --}}
                        <div class="space-y-3 mb-5 text-sm">

                            @if($product->category)
                                <div class="flex items-center gap-4">
                                    <span class="w-28 text-gray-400 flex-shrink-0">Category</span>
                                    <span class="text-gray-700">{{ $product->category }}</span>
                                </div>
                            @endif
                            @if($product->tags)
                                <div class="flex items-start gap-4">
                                    <span class="w-28 text-gray-400 flex-shrink-0 pt-0.5">Tags</span>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach(explode(',', $product->tags) as $tag)
                                            <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-500 rounded">{{ trim($tag) }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="border-t border-gray-100 mb-5"></div>

                        {{-- Quantity + Stock --}}
                        <div class="flex items-center gap-4 text-sm mb-5">
                            <span class="w-28 text-gray-400 flex-shrink-0">Quantity</span>
                            <div class="flex items-center gap-3">
                                @if($stockStatus !== 'out')
                                    <div class="flex items-center border border-gray-300 rounded overflow-hidden select-none">
                                        <button type="button"
                                                @click="qty = Math.max(1, qty - 1)"
                                                class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors border-r border-gray-300">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                        </button>
                                        <span class="w-10 text-center text-sm font-semibold text-gray-800" x-text="qty"></span>
                                        <button type="button"
                                                @click="qty = Math.min({{ $product->stock }}, qty + 1)"
                                                class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors border-l border-gray-300">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        </button>
                                    </div>
                                @endif
                                @if($stockStatus === 'out')
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-red-500">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span> Out of Stock
                                    </span>
                                @elseif($stockStatus === 'low')
                                    <span class="inline-flex items-center gap-1.5 text-xs text-amber-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"></span> Only {{ $product->stock }} left
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">({{ $product->stock }} available)</span>
                                @endif
                            </div>
                        </div>

                        {{-- Flash messages --}}
                        @if(session('status') === 'added-to-cart')
                            <div class="flex items-center gap-2 text-sm text-[#2D9F4E] bg-[#E8F5E9] rounded-lg px-4 py-2.5 mb-3">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Added to cart.
                            </div>
                        @endif
                        @if(session('status') === 'wishlist-added')
                            <div class="flex items-center gap-2 text-sm text-[#2D9F4E] bg-[#E8F5E9] rounded-lg px-4 py-2.5 mb-3">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Saved to wishlist.
                            </div>
                        @endif
                        @if(session('status') === 'wishlist-removed')
                            <div class="text-sm text-gray-500 bg-gray-50 rounded-lg px-4 py-2.5 mb-3">Removed from wishlist.</div>
                        @endif

                        {{-- CTA Buttons --}}
                        <div class="flex gap-3 mt-auto">
                            @if($product->stock > 0)
                                @auth
                                    @if(auth()->user()->hasRole('customer'))
                                        <form method="post" action="{{ route('cart.add', ['id' => $product->id]) }}" class="flex-1">
                                            @csrf
                                            <input type="hidden" name="qty" x-bind:value="qty">
                                            <button type="submit"
                                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 border-2 border-[#2D9F4E] text-[#2D9F4E] bg-white rounded-lg font-semibold text-sm hover:bg-[#E8F5E9] active:scale-95 transition-all">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                Add To Cart
                                            </button>
                                        </form>
                                        <a href="{{ route('customer.checkout') }}"
                                           class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 bg-[#2D9F4E] text-white rounded-lg font-semibold text-sm hover:bg-[#1B7A37] active:scale-95 transition-all shadow-sm">
                                            Buy Now
                                        </a>
                                    @endif
                                @else
                                    <a href="{{ route('login') }}"
                                       class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 border-2 border-[#2D9F4E] text-[#2D9F4E] bg-white rounded-lg font-semibold text-sm hover:bg-[#E8F5E9] transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        Add To Cart
                                    </a>
                                @endauth
                            @else
                                <button type="button" disabled
                                        class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 bg-gray-100 text-gray-400 rounded-lg font-semibold text-sm cursor-not-allowed">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                    Out of Stock
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══ SELLER CARD ══ --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 mb-3">
                <div class="flex flex-col sm:flex-row gap-5 sm:gap-0">
                    {{-- Seller identity --}}
                    <div class="flex items-start gap-4 sm:w-72 sm:border-r sm:border-gray-100 sm:pr-6 flex-shrink-0">
                        @if($product->seller?->logo_url)
                            <img src="{{ $product->seller->logo_url }}" alt=""
                                 class="w-14 h-14 rounded-full object-cover border border-gray-200 flex-shrink-0">
                        @elseif($product->seller?->user?->avatar_url)
                            <img src="{{ $product->seller->user->avatar_url }}" alt=""
                                 class="w-14 h-14 rounded-full object-cover border border-gray-200 flex-shrink-0">
                        @else
                            <div class="w-14 h-14 rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center flex-shrink-0 text-gray-400">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 mb-0.5 flex-wrap">
                                <a href="{{ route('store.show', $product->seller?->store_name ?? '') }}"
                                   class="font-semibold text-gray-900 text-sm hover:text-[#2D9F4E] transition-colors">
                                    {{ $product->seller?->store_name ?? '—' }}
                                </a>
                                @if($product->seller?->is_verified ?? false)
                                    <svg class="w-3.5 h-3.5 text-blue-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </div>
                            <p class="text-xs text-gray-400 mb-3">
                                {{ ($product->seller?->is_open ?? false) ? 'Active · Open now' : 'Currently inactive' }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @auth
                                    @if(auth()->user()->hasRole('customer'))
                                        <button type="button"
                                                onclick="Livewire.dispatch('open-chat-with-seller', { sellerId: {{ $product->seller?->id ?? 'null' }} })"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#2D9F4E] text-[#2D9F4E] text-xs font-medium rounded hover:bg-[#E8F5E9] transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                            Chat Now
                                        </button>
                                    @endif
                                @endauth
                                <a href="{{ route('store.show', $product->seller?->store_name ?? '') }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 text-gray-600 text-xs font-medium rounded hover:bg-gray-50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                    View Shop
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Seller stats grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-10 gap-y-3 sm:pl-8 flex-1 text-sm">
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">Ratings</span>
                            <span class="font-medium text-[#2D9F4E]">{{ $product->reviews_count }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">Response Rate</span>
                            <span class="font-medium text-[#2D9F4E]">{{ $product->seller?->response_rate ? $product->seller->response_rate . '%' : '—' }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">Joined</span>
                            <span class="font-medium text-[#2D9F4E]">{{ $product->seller?->created_at?->diffForHumans() ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">Products</span>
                            <span class="font-medium text-[#2D9F4E]">{{ $product->seller?->products_count ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">Response Time</span>
                            <span class="font-medium text-[#2D9F4E]">{{ $product->seller?->response_time ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">Followers</span>
                            <span class="font-medium text-[#2D9F4E]">{{ $product->seller?->followers_count ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══ PRODUCT DETAILS ══ --}}
            @if($product->description)
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 mb-3">
                    <h2 class="text-sm font-bold text-gray-700 uppercase tracking-widest mb-4">Product Details</h2>
                    <div class="text-sm text-gray-600 leading-7 whitespace-pre-wrap">{{ $product->description }}</div>
                </div>
            @endif

            {{-- ══ PRODUCT RATINGS ══ --}}
            <div id="ratings" class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-base font-bold text-gray-800">Product Ratings</h2>
                </div>

                @if(session('status') === 'review-updated' || session('status') === 'review-deleted')
                    <div class="px-6 py-3 border-b border-gray-100 text-sm {{ session('status') === 'review-updated' ? 'text-[#2D9F4E] bg-[#E8F5E9]' : 'text-gray-500 bg-gray-50' }}">
                        {{ session('status') === 'review-updated' ? 'Your review has been updated.' : 'Your review has been deleted.' }}
                    </div>
                @endif

                <div class="p-6">
                    @if($product->reviews_count > 0)
                        {{-- Summary + filter tabs --}}
                        <div class="bg-amber-50/40 border border-amber-100 rounded-lg p-5 mb-6">
                            <div class="flex flex-wrap items-center gap-6">
                                {{-- Big score --}}
                                @php $avgInt = round($product->reviews_avg_rating ?? 0); @endphp
                                <div class="text-center min-w-[80px]">
                                    <div class="text-4xl font-bold text-[#2D9F4E] leading-none tabular-nums">
                                        {{ number_format($product->reviews_avg_rating ?? 0, 1) }}
                                    </div>
                                    <div class="flex items-center justify-center gap-0.5 mt-1.5">
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg class="w-4 h-4 {{ $i <= $avgInt ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">out of 5</p>
                                </div>

                                {{-- Filter tabs --}}
                                <div class="flex flex-wrap gap-2">
                                    <button type="button"
                                            @click="ratingFilter = 'all'"
                                            :class="ratingFilter === 'all' ? 'border-[#2D9F4E] text-[#2D9F4E] bg-[#E8F5E9]' : 'border-gray-200 text-gray-500 hover:border-gray-400 bg-white'"
                                            class="px-3 py-1.5 text-xs border rounded transition-colors">
                                        All
                                    </button>
                                    @foreach([5, 4, 3, 2, 1] as $s)
                                        <button type="button"
                                                @click="ratingFilter = '{{ $s }}'"
                                                :class="ratingFilter === '{{ $s }}' ? 'border-[#2D9F4E] text-[#2D9F4E] bg-[#E8F5E9]' : 'border-gray-200 text-gray-500 hover:border-gray-400 bg-white'"
                                                class="px-3 py-1.5 text-xs border rounded transition-colors">
                                            {{ $s }} Star ({{ $starBreakdown[$s] ?? 0 }})
                                        </button>
                                    @endforeach
                                    @php $withComments = $product->reviews->filter(fn($r) => !empty($r->body))->count(); @endphp
                                    <button type="button"
                                            @click="ratingFilter = 'comments'"
                                            :class="ratingFilter === 'comments' ? 'border-[#2D9F4E] text-[#2D9F4E] bg-[#E8F5E9]' : 'border-gray-200 text-gray-500 hover:border-gray-400 bg-white'"
                                            class="px-3 py-1.5 text-xs border rounded transition-colors">
                                        With Comments ({{ $withComments }})
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Review list --}}
                        <div>
                            @foreach($product->reviews as $review)
                                @php
                                    $reviewerName = $review->customer->name ?? $review->customer->username ?? 'Customer';
                                    $len = strlen($reviewerName);
                                    $masked = $len > 2
                                        ? substr($reviewerName, 0, 1) . str_repeat('*', max(1, $len - 2)) . substr($reviewerName, -1)
                                        : $reviewerName;
                                    $hasBody = !empty($review->body);
                                @endphp
                                <div class="{{ !$loop->first ? 'border-t border-gray-100 pt-5 mt-5' : '' }} flex gap-3"
                                     x-show="ratingFilter === 'all' || ratingFilter === '{{ $review->rating }}' || (ratingFilter === 'comments' && {{ $hasBody ? 'true' : 'false' }})">
                                    <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0 text-gray-500">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-gray-700 mb-1">{{ $masked }}</p>
                                        <div class="flex items-center gap-0.5 mb-1">
                                            @for($i = 1; $i <= 5; $i++)
                                                <svg class="w-3 h-3 {{ $i <= $review->rating ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @endfor
                                        </div>
                                        <p class="text-xs text-gray-400 mb-2">{{ $review->created_at->format('Y-m-d H:i') }}</p>
                                        @if($hasBody)
                                            <p class="text-sm text-gray-700 leading-6 mb-3">{{ $review->body }}</p>
                                        @endif
                                        @if($review->seller_reply)
                                            <div class="bg-gray-50 border border-gray-100 rounded-lg p-3 mt-2">
                                                <div class="flex items-center gap-1.5 mb-1">
                                                    <div class="w-4 h-4 rounded-full bg-[#2D9F4E] flex items-center justify-center flex-shrink-0">
                                                        <svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </div>
                                                    <span class="text-xs font-semibold text-gray-700">Seller's Reply</span>
                                                </div>
                                                <p class="text-xs text-gray-600 leading-relaxed">{{ $review->seller_reply }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                    @else
                        <div class="py-10 text-center">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <p class="text-sm text-gray-500 font-medium">No ratings yet</p>
                            <p class="text-xs text-gray-400 mt-1">Be the first to rate this product after purchase.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Lightbox --}}
        <div x-show="lightbox" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
             @click.self="lightbox = false"
             @keydown.escape.window="lightbox = false">
            <button type="button" @click="lightbox = false"
                    class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded-full transition-colors text-xl leading-none">
                &times;
            </button>
            @if($product->image_path)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                     class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" @click.stop>
            @endif
        </div>

        {{-- Report modal --}}
        @auth
            @if(auth()->user()->hasRole('customer'))
                <div id="report-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('report-modal').classList.add('hidden')"></div>
                        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                            <h3 class="text-base font-bold text-gray-900 mb-4">Report this listing</h3>
                            <form method="post" action="{{ route('product.report.store', $product->id) }}">
                                @csrf
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Reason</label>
                                        <select name="reason" required class="w-full rounded-lg border border-gray-200 text-sm focus:border-[#F9C74F] focus:ring-[#F9C74F]">
                                            @foreach(\App\Models\ProductReport::reasonOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description <span class="text-gray-400 font-normal">(optional)</span></label>
                                        <textarea name="description" rows="3" maxlength="500"
                                                  class="w-full rounded-lg border border-gray-200 text-sm focus:border-[#F9C74F] focus:ring-[#F9C74F]"
                                                  placeholder="Additional details…"></textarea>
                                    </div>
                                </div>
                                <div class="mt-5 flex gap-2 justify-end">
                                    <button type="button" onclick="document.getElementById('report-modal').classList.add('hidden')"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-rose-500 rounded-lg hover:bg-rose-600 transition-colors">Submit report</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endauth
    </div>
</x-app-layout>
