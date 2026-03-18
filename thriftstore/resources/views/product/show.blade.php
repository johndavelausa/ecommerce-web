@php
    $threshold = (int) ($product->low_stock_threshold ?? 10);
    $stockStatus = $product->stock === 0 ? 'out' : ($product->stock <= $threshold ? 'low' : 'in');
@endphp
<x-app-layout>

    {{-- <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Product') }}</h2>
    </x-slot> --}}

    <div class="py-8 lg:py-10" x-data="{ lightbox: false }">
        <div class="mx-auto w-full max-w-[1440px] px-4 md:px-8 lg:px-12">
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <a href="{{ route('catalog') }}" class="hover:text-[#2d6c50] transition-colors">Home</a>
                <span class="text-gray-300">›</span>
                <a href="{{ route('customer.dashboard') }}" class="hover:text-[#2d6c50] transition-colors">All Products</a>
                <span class="text-gray-300">›</span>
                <span class="font-semibold text-gray-800">{{ $product->name }}</span>
            </nav>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                    {{-- 1. Product image (zoomable) --}}
                    <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden cursor-zoom-in"
                         @click="lightbox = true">
                        @if($product->image_path)
                            <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" class="w-full h-full object-cover" loading="lazy">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-400">No image</div>
                        @endif
                    </div>

                    <div class="space-y-4">
                        {{-- 2. Product name --}}
                        <h1 class="text-2xl font-semibold text-gray-900">{{ $product->name }}</h1>

                        {{-- 3. Condition badge --}}
                        <div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-sm font-medium bg-indigo-100 text-indigo-800">
                                {{ \App\Models\Product::conditionOptions()[$product->condition] ?? $product->condition }}
                            </span>
                        </div>

                        {{-- 4. Size / variant if set --}}
                        @if($product->size_variant)
                            <div class="text-sm text-gray-700">
                                Size: {{ (\App\Models\Product::sizeVariantOptions())[$product->size_variant] ?? $product->size_variant }}
                            </div>
                        @endif

                        {{-- 5. Regular price (strikethrough if sale) | 6. Sale price (highlighted) --}}
                        <div class="text-2xl font-semibold text-gray-900">
                            @if($product->sale_price)
                                <span class="text-gray-400 line-through text-lg">₱{{ number_format($product->price, 2) }}</span>
                                <span class="text-rose-600 ml-2">₱{{ number_format($product->sale_price, 2) }}</span>
                            @else
                                ₱{{ number_format($product->price, 2) }}
                            @endif
                        </div>

                        {{-- 7. Stock status label --}}
                        <div class="text-sm font-medium">
                            @if($stockStatus === 'out')
                                <span class="text-red-600">Out of Stock</span>
                            @elseif($stockStatus === 'low')
                                <span class="text-amber-600">Low Stock</span>
                                <span class="text-gray-500 font-normal">({{ $product->stock }} left)</span>
                            @else
                                <span class="text-green-600">In Stock</span>
                                <span class="text-gray-500 font-normal">({{ $product->stock }} available)</span>
                            @endif
                        </div>

                        {{-- 8. Category / tag --}}
                        @if($product->category || $product->tags)
                            <div class="text-sm text-gray-600">
                                @if($product->category)
                                    <span>Category: {{ $product->category }}</span>
                                @endif
                                @if($product->tags)
                                    @if($product->category) <span class="mx-1">·</span> @endif
                                    <span>Tags: {{ $product->tags }}</span>
                                @endif
                            </div>
                        @endif

                        {{-- 9. Product description (full text) --}}
                        <div class="text-sm text-gray-600 whitespace-pre-wrap">{{ $product->description }}</div>

                        {{-- 10. Seller name (clickable) | 11. Verified badge --}}
                        <div class="text-sm text-gray-700">
                            <span class="font-medium">Seller:</span>
                            <a href="{{ route('store.show', $product->seller?->store_name ?? '') }}" class="text-indigo-600 hover:underline">{{ $product->seller?->store_name ?? '—' }}</a>
                            @if($product->seller?->is_verified ?? false)
                                <span class="inline-flex items-center text-blue-600 text-sm ml-1" title="Verified seller">✓ Verified</span>
                            @endif
                        </div>

                        {{-- 12. Add to Cart (disabled if out of stock) --}}
                        <div class="flex flex-wrap items-center gap-2">
                            @if($product->stock > 0)
                                @auth
                                    @if(auth()->user()->hasRole('customer'))
                                        <form method="post" action="{{ route('cart.add', ['id' => $product->id]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
                                                Add to cart
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <p class="text-sm text-gray-500"><a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Log in</a> to add to cart.</p>
                                @endauth
                            @else
                                <button type="button" disabled class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-500 rounded-md text-sm font-medium cursor-not-allowed">
                                    Add to cart
                                </button>
                            @endif

                            {{-- 13. Add to Wishlist (heart icon) --}}
                            @auth
                                @if(auth()->user()->hasRole('customer'))
                                    <form method="post" action="{{ route('wishlist.toggle', $product->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center justify-center w-10 h-10 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50" title="{{ isset($inWishlist) && $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' }}">
                                            @if(isset($inWishlist) && $inWishlist)
                                                <span class="text-rose-500">♥</span>
                                            @else
                                                <span class="text-gray-400">♡</span>
                                            @endif
                                        </button>
                                    </form>
                                @endif
                            @endauth
                        </div>
                        @if(session('status') === 'added-to-cart')
                            <p class="text-sm text-green-600">Added to cart.</p>
                        @endif
                        @if(session('status') === 'wishlist-added')
                            <p class="text-sm text-green-600">Added to wishlist.</p>
                        @endif
                        @if(session('status') === 'wishlist-removed')
                            <p class="text-sm text-gray-600">Removed from wishlist.</p>
                        @endif

                        {{-- 14. Share / Copy Link --}}
                        <button type="button" x-data="{ copied: false }"
                                @click="navigator.clipboard.writeText('{{ url()->current() }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 hover:text-indigo-600 hover:bg-gray-100 rounded-md">
                            <span x-show="!copied">Share / Copy link</span>
                            <span x-show="copied" x-cloak class="text-green-600">Copied!</span>
                        </button>

                        {{-- 15. Report this listing (small, bottom) --}}
                        @if(session('status') === 'report-submitted')
                            <p class="text-sm text-green-600">Report submitted. Thank you.</p>
                        @endif
                        <div class="pt-2 border-t border-gray-200">
                            @auth
                                @if(auth()->user()->hasRole('customer'))
                                    <button type="button" onclick="document.getElementById('report-modal').classList.remove('hidden')"
                                            class="text-xs text-gray-500 hover:text-rose-600">Report this listing</button>
                                @endif
                            @endauth
                        </div>
                    </div>
                </div>
            </div>

            {{-- E2 — Ratings and Reviews Section --}}
            <div class="mt-8 bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Ratings &amp; Reviews</h2>
                </div>
                <div class="p-6 space-y-6">
                    @if(session('status') === 'review-updated')
                        <p class="text-sm text-green-600">Your review has been updated.</p>
                    @endif
                    @if(session('status') === 'review-deleted')
                        <p class="text-sm text-gray-600">Your review has been deleted.</p>
                    @endif
                    @if($product->reviews_count > 0)
                        {{-- Overall rating + count + star breakdown --}}
                        <div class="flex flex-wrap gap-8">
                            <div class="flex items-center gap-3">
                                <span class="text-4xl font-bold text-gray-900">{{ number_format($product->reviews_avg_rating ?? 0, 1) }}</span>
                                <span class="text-gray-500">/ 5</span>
                            </div>
                            <div class="text-sm text-gray-600">{{ $product->reviews_count }} {{ $product->reviews_count === 1 ? 'review' : 'reviews' }}</div>
                            <div class="w-full max-w-xs space-y-1">
                                @foreach([5, 4, 3, 2, 1] as $stars)
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="w-8 text-gray-600">{{ $stars }} star{{ $stars !== 1 ? 's' : '' }}</span>
                                        <div class="flex-1 h-3 bg-gray-200 rounded overflow-hidden">
                                            <div class="h-full bg-amber-400 rounded" style="width: {{ $maxBar > 0 ? round(100 * ($starBreakdown[$stars] ?? 0) / $maxBar) : 0 }}%"></div>
                                        </div>
                                        <span class="w-6 text-gray-600">{{ $starBreakdown[$stars] ?? 0 }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Individual review cards (most recent first) --}}
                        <div class="space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">Reviews</h3>
                            @foreach($product->reviews as $review)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <span class="font-medium text-gray-900">{{ $review->customer->name ?? $review->customer->username ?? 'Customer' }}</span>
                                        <span class="text-xs text-gray-500">{{ $review->created_at->format('M j, Y') }}</span>
                                    </div>
                                    <div class="mt-1 text-amber-500 text-sm">
                                        {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                                    </div>
                                    <p class="mt-2 text-sm text-gray-700 whitespace-pre-wrap">{{ $review->body }}</p>
                                    @if($review->seller_reply)
                                        <div class="mt-3 pl-4 border-l-2 border-gray-200 bg-gray-50 rounded-r py-2 pr-3">
                                            <div class="text-xs font-medium text-gray-500 mb-0.5">Seller reply</div>
                                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $review->seller_reply }}</p>
                                        </div>
                                    @endif
                                    @auth
                                        @if(auth()->user()->hasRole('customer') && (int) $review->customer_id === (int) auth()->id())
                                            <div class="mt-3 flex gap-2">
                                                <button type="button" onclick="document.getElementById('edit-review-{{ $review->id }}').classList.toggle('hidden')"
                                                        class="text-xs text-indigo-600 hover:underline">Edit</button>
                                                <form method="post" action="{{ route('product.review.destroy', $review->id) }}" class="inline" onsubmit="return confirm('Delete this review?');">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" class="text-xs text-rose-600 hover:underline">Delete</button>
                                                </form>
                                            </div>
                                            <div id="edit-review-{{ $review->id }}" class="hidden mt-3 p-3 bg-gray-50 rounded border border-gray-200">
                                                <form method="post" action="{{ route('product.review.update', $review->id) }}">
                                                    @csrf
                                                    @method('patch')
                                                    <div class="space-y-2">
                                                        <label class="block text-xs font-medium text-gray-700">Rating</label>
                                                        <select name="rating" class="rounded border-gray-300 text-sm" required>
                                                            @for($i = 1; $i <= 5; $i++)
                                                                <option value="{{ $i }}" {{ (int)$review->rating === $i ? 'selected' : '' }}>{{ $i }} star{{ $i !== 1 ? 's' : '' }}</option>
                                                            @endfor
                                                        </select>
                                                        <label class="block text-xs font-medium text-gray-700">Review</label>
                                                        <textarea name="body" rows="3" maxlength="2000" class="w-full rounded border-gray-300 text-sm" required>{{ $review->body }}</textarea>
                                                        <div class="flex gap-2">
                                                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Save</button>
                                                            <button type="button" onclick="document.getElementById('edit-review-{{ $review->id }}').classList.add('hidden')" class="px-3 py-1.5 border border-gray-300 text-sm rounded hover:bg-gray-50">Cancel</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        @endif
                                    @endauth
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No reviews yet. Be the first to review this product.</p>
                    @endif

                    {{-- Review form (when customer is eligible) --}}
                    @if(isset($canReview) && $canReview && isset($eligibleOrderId))
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Write a review</h3>
                            @if(session('status') === 'review-submitted')
                                <p class="text-sm text-green-600 mb-3">Thank you! Your review has been submitted.</p>
                            @endif
                            <form method="post" action="{{ route('product.review.store', $product->id) }}" class="space-y-3 max-w-xl">
                                @csrf
                                <input type="hidden" name="order_id" value="{{ $eligibleOrderId }}">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                                    <select name="rating" required class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @for($i = 1; $i <= 5; $i++)
                                            <option value="{{ $i }}" {{ $i === 5 ? 'selected' : '' }}>{{ $i }} star{{ $i !== 1 ? 's' : '' }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Your review</label>
                                    <textarea name="body" rows="4" maxlength="2000" required placeholder="Share what you liked or didn't like about this item."
                                              class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">Submit review</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Lightbox --}}
        <div x-show="lightbox" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
             @click.self="lightbox = false">
            <button type="button" @click="lightbox = false" class="absolute top-4 right-4 text-white hover:text-gray-300 text-2xl">&times;</button>
            @if($product->image_path)
                <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}"
                     class="max-w-full max-h-full object-contain" @click.stop>
            @endif
        </div>

        {{-- Report modal --}}
        @auth
            @if(auth()->user()->hasRole('customer'))
                <div id="report-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('report-modal').classList.add('hidden')"></div>
                        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Report this listing</h3>
                            <form method="post" action="{{ route('product.report.store', $product->id) }}">
                                @csrf
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                                        <select name="reason" required class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach(\App\Models\ProductReport::reasonOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                                        <textarea name="description" rows="3" maxlength="500" class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Additional details..."></textarea>
                                    </div>
                                </div>
                                <div class="mt-4 flex gap-2 justify-end">
                                    <button type="button" onclick="document.getElementById('report-modal').classList.add('hidden')"
                                            class="px-3 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</button>
                                    <button type="submit" class="px-3 py-2 text-sm text-white bg-rose-600 rounded-md hover:bg-rose-700">Submit report</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endauth
    </div>
</x-app-layout>
