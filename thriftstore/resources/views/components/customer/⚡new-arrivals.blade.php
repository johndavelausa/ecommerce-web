<?php

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function products()
    {
        return Product::query()
            ->with('seller')
            ->where('is_active', true)
            ->where('condition', 'new')
            ->whereHas('seller', fn ($q) => $q->where('status', 'approved'))
            ->where('stock', '>', 0)
            ->latest()
            ->limit(4)
            ->get();
    }

    #[Computed]
    public function saleProducts()
    {
        return Product::query()
            ->with('seller')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereNotNull('sale_price')
            ->whereColumn('sale_price', '<', 'price')
            ->whereHas('seller', fn ($q) => $q->where('status', 'approved'))
            ->orderByRaw('((price - sale_price) / NULLIF(price, 0)) desc')
            ->latest('updated_at')
            ->limit(4)
            ->get();
    }

    #[Computed]
    public function wishlistIds(): array
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return [];
        }

        return Wishlist::where('customer_id', $customer->id)
            ->whereIn('product_id', $this->products->pluck('id'))
            ->pluck('product_id')
            ->flip()
            ->all();
    }

    public function addToCart(int $productId): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            $this->dispatch('wishlist-toast', message: 'Please log in to use wishlist.', level: 'error');
            $this->redirect(route('login'));
            return;
        }

        $product = Product::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereHas('seller', fn ($q) => $q->where('status', 'approved'))
            ->findOrFail($productId);

        $cart = Session::get('cart', []);
        $key  = (string) $product->id;

        if (! isset($cart[$key]) && count($cart) >= 50) {
            return;
        }

        $currentQty = $cart[$key]['quantity'] ?? 0;
        $cart[$key] = [
            'product_id' => $product->id,
            'seller_id'  => $product->seller_id,
            'name'       => $product->name,
            'price'      => (float) ($product->sale_price ?? $product->price),
            'image_path' => $product->image_path,
            'quantity'   => min($currentQty + 1, $product->stock),
        ];

        Session::put('cart', $cart);
        $count = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cart));
        $this->dispatch('cart-updated', count: $count);
    }

    public function toggleWishlist(int $productId): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            $this->redirect(route('login'));
            return;
        }

        try {
            $existing = Wishlist::where('customer_id', $customer->id)
                ->where('product_id', $productId)
                ->first();

            $wished = false;
            if ($existing) {
                $existing->delete();
            } else {
                Wishlist::firstOrCreate([
                    'customer_id' => $customer->id,
                    'product_id'  => $productId,
                ]);
                $wished = true;
            }

            $count = Wishlist::where('customer_id', $customer->id)->count();
            $this->dispatch('wishlist-updated', count: $count);
            $this->dispatch('wishlist-sync', productId: $productId, wished: $wished);
            $this->dispatch(
                'wishlist-toast',
                message: $wished ? 'Added to wishlist.' : 'Removed from wishlist.',
                level: 'success'
            );
        } catch (\Throwable $e) {
            report($e);

            $actual = Wishlist::where('customer_id', $customer->id)
                ->where('product_id', $productId)
                ->exists();
            $count = Wishlist::where('customer_id', $customer->id)->count();

            $this->dispatch('wishlist-updated', count: $count);
            $this->dispatch('wishlist-sync', productId: $productId, wished: $actual);
            $this->dispatch('wishlist-toast', message: 'Could not update wishlist. Please try again.', level: 'error');
        }

        unset($this->wishlistIds);
    }
};
?>

<div x-data="{
        toastOpen: false,
        toastMessage: '',
        toastLevel: 'success',
        toastTimer: null,
        showToast(message, level) {
            this.toastMessage = message || '';
            this.toastLevel = level || 'success';
            this.toastOpen = true;
            if (this.toastTimer) {
                clearTimeout(this.toastTimer);
            }
            this.toastTimer = setTimeout(() => { this.toastOpen = false; }, 1800);
        }
    }"
    x-on:wishlist-toast.window="showToast($event.detail.message, $event.detail.level)">
    @if($this->products->isNotEmpty())
        <section class="mx-auto w-full max-w-[1440px] px-4 md:px-8 lg:px-12 py-12">
            <div class="flex items-end justify-between mb-6 border-b border-[#d6e3dc] pb-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">New Arrivals</h2>
                    <p class="text-sm text-gray-500 mt-1">Freshly picked treasures from our community sellers.</p>
                </div>
                <a href="{{ route('catalog') }}?condition=new"
                   class="flex items-center gap-1 text-sm font-semibold text-[#2d6c50] hover:underline">
                    View All <span class="text-base">›</span>
                </a>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($this->products as $product)
                    @php
                        $threshold  = (int) ($product->low_stock_threshold ?? 10);
                        $isLowStock = $product->stock <= $threshold;
                        $inWishlist = isset($this->wishlistIds[$product->id]);
                    @endphp
                    <article x-data="{
                                id: {{ $product->id }},
                                hovering: false,
                                wished: {{ $inWishlist ? 'true' : 'false' }},
                                pending: false,
                                cartPending: false,
                                async toggleAjax(event) {
                                    if (this.pending) return;
                                    this.pending = true;
                                    const form = event.currentTarget;
                                    const formData = new FormData(form);

                                    try {
                                        const response = await fetch(form.action, {
                                            method: 'POST',
                                            headers: {
                                                'X-Requested-With': 'XMLHttpRequest',
                                                'Accept': 'application/json'
                                            },
                                            body: formData,
                                            credentials: 'same-origin'
                                        });

                                        if (response.redirected) {
                                            window.location.href = response.url;
                                            return;
                                        }

                                        const payload = await response.json();
                                        if (!response.ok) {
                                            throw new Error('Wishlist update failed');
                                        }

                                        this.wished = !!payload.wished;
                                        window.dispatchEvent(new CustomEvent('wishlist-updated', { detail: { count: Number(payload.count || 0) } }));
                                        window.dispatchEvent(new CustomEvent('wishlist-toast', {
                                            detail: {
                                                message: payload.status === 'wishlist-added' ? 'Added to wishlist.' : 'Removed from wishlist.',
                                                level: 'success'
                                            }
                                        }));
                                    } catch (e) {
                                        window.dispatchEvent(new CustomEvent('wishlist-toast', {
                                            detail: { message: 'Could not update wishlist. Please try again.', level: 'error' }
                                        }));
                                    } finally {
                                        this.pending = false;
                                    }
                                },
                                async addToCartAjax(event) {
                                    if (this.cartPending) return;
                                    this.cartPending = true;

                                    const form = event.currentTarget;
                                    const formData = new FormData(form);

                                    try {
                                        const response = await fetch(form.action, {
                                            method: 'POST',
                                            headers: {
                                                'X-Requested-With': 'XMLHttpRequest',
                                                'Accept': 'application/json'
                                            },
                                            body: formData,
                                            credentials: 'same-origin'
                                        });

                                        if (response.redirected) {
                                            window.location.href = response.url;
                                            return;
                                        }

                                        const payload = await response.json();
                                        if (!response.ok) {
                                            throw new Error(payload.message || 'Add to cart failed');
                                        }

                                        window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: Number(payload.count || 0) } }));
                                        window.dispatchEvent(new CustomEvent('wishlist-toast', {
                                            detail: { message: 'Added to cart.', level: 'success' }
                                        }));
                                    } catch (e) {
                                        window.dispatchEvent(new CustomEvent('wishlist-toast', {
                                            detail: { message: 'Could not add to cart. Please try again.', level: 'error' }
                                        }));
                                    } finally {
                                        this.cartPending = false;
                                    }
                                }
                            }"
                             @mouseenter="hovering = true"
                             @mouseleave="hovering = false"
                             class="group relative flex flex-col overflow-hidden rounded-2xl border border-[#dfe8e4] bg-white shadow-sm transition-shadow duration-300 hover:shadow-xl">
                        <div class="relative h-52 sm:h-56 md:h-60 overflow-hidden bg-gray-100">
                            <a href="{{ route('product.show', $product->id) }}" class="block h-full w-full">
                                @if($product->image_path)
                                    <img src="{{ asset('storage/' . $product->image_path) }}"
                                         alt="{{ $product->name }}"
                                         class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                         loading="lazy">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">No image</div>
                                @endif
                            </a>

                                    {{-- Wishlist heart: always visible when wishlisted, hover-only otherwise --}}
                                    <form method="POST"
                                          action="{{ route('wishlist.toggle', $product->id) }}"
                                          @submit.prevent="toggleAjax($event)">
                                        @csrf
                                        <button type="submit"
                                                :disabled="pending"
                                                :class="(hovering || wished) ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'"
                                                class="absolute right-3 top-3 z-30 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/90 shadow-sm transition-opacity duration-150"
                                                title="{{ $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' }}">
                                            <span x-show="wished" x-cloak class="text-rose-500 text-sm leading-none">♥</span>
                                            <span x-show="!wished" x-cloak class="text-gray-400 text-sm leading-none">♡</span>
                                        </button>
                                    </form>

                            {{-- Condition + sale badges --}}
                            <div class="absolute left-3 top-3 z-20 flex flex-col gap-1.5">
                                <span class="rounded-full bg-[#2d6c50] px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-white">New</span>
                                @if($product->sale_price)
                                    <span class="rounded bg-amber-400 px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-gray-900">Sale</span>
                                @endif
                            </div>

                            @if($isLowStock)
                                <span class="absolute bottom-3 left-3 z-20 rounded bg-amber-500 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-white">Low Stock</span>
                            @endif

                            @if($product->seller?->is_verified ?? false)
                                <span class="absolute bottom-3 right-3 z-20 rounded-full bg-white/90 px-2 py-1 text-[10px] font-semibold text-[#2d6c50] shadow-sm">Verified</span>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col p-3">
                            <p class="mb-1 text-[11px] uppercase tracking-wider text-gray-500">
                                {{ $product->seller?->store_name ?? 'Ukay Hub Seller' }}
                            </p>
                            <a href="{{ route('product.show', $product->id) }}"
                               class="line-clamp-2 text-sm font-bold text-gray-900 transition-colors group-hover:text-[#2d6c50]">
                                {{ $product->name }}
                            </a>

                            @if($product->size_variant)
                                <p class="mt-1 text-[11px] font-medium text-gray-500">
                                    Size: {{ (\App\Models\Product::sizeVariantOptions())[$product->size_variant] ?? $product->size_variant }}
                                </p>
                            @endif

                            <div class="mt-auto flex items-end justify-between pt-3">
                                <div class="flex flex-col">
                                    @if($product->sale_price)
                                        <span class="text-xs text-gray-400 line-through">₱{{ number_format((float) $product->price, 2) }}</span>
                                        <span class="text-lg font-bold text-[#2d6c50]">₱{{ number_format((float) $product->sale_price, 2) }}</span>
                                    @else
                                        <span class="text-lg font-bold text-[#2d6c50]">₱{{ number_format((float) $product->price, 2) }}</span>
                                    @endif
                                </div>

                                <form method="POST"
                                      action="{{ route('cart.add', ['id' => $product->id]) }}"
                                      @submit.prevent="addToCartAjax($event)">
                                    @csrf
                                    <button type="submit"
                                            :disabled="cartPending"
                                            class="inline-flex items-center justify-center rounded-lg bg-[#2d6c50]/10 px-3 py-2 text-xs font-semibold text-[#2d6c50] transition-all hover:bg-[#2d6c50] hover:text-white disabled:opacity-60"
                                            title="Add to cart">
                                        + Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto w-full max-w-[1440px] px-4 pb-14 md:px-8 lg:px-12">
        <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#236c4c] via-[#2b7a57] to-[#2f815d] px-6 py-8 shadow-[0_24px_55px_rgba(13,45,30,0.25)] md:px-10 md:py-10"
             style="background-color:#2b7a57;background-image:linear-gradient(135deg,#236c4c 0%,#2b7a57 48%,#2f815d 100%);border:1px solid rgba(255,255,255,0.12);box-shadow:0 24px 55px rgba(13,45,30,0.25);">
            <div class="pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full bg-white/10 blur-2xl"></div>
            <div class="pointer-events-none absolute -left-16 bottom-0 h-40 w-40 rounded-full bg-[#9fd4ba]/20 blur-2xl"></div>

            <div class="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div class="max-w-xl">
                    <span class="inline-flex rounded-full bg-rose-500 px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.24em] text-white">
                        Flash Sale
                    </span>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-white md:text-5xl">On Sale Now</h2>
                    <p class="mt-3 text-sm text-emerald-50/90 md:text-2xl">
                        Limited stock clearance. Grab your favorites before they disappear.
                    </p>
                </div>

                <a href="{{ route('catalog') }}?sort=on_sale"
                   class="inline-flex w-fit items-center gap-2 rounded-2xl bg-white px-6 py-3 text-sm font-bold text-[#1f6a49] shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">
                    Shop All Sale
                    <span aria-hidden="true" class="text-base">→</span>
                </a>
            </div>

            <div class="relative mt-8">
                @if($this->saleProducts->isNotEmpty())
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach($this->saleProducts as $saleProduct)
                            @php
                                $originalPrice = (float) $saleProduct->price;
                                $salePrice = (float) $saleProduct->sale_price;
                                $discountPercent = $originalPrice > 0
                                    ? (int) round((($originalPrice - $salePrice) / $originalPrice) * 100)
                                    : 0;
                                $discountPercent = max($discountPercent, 1);
                            @endphp

                            <article class="group overflow-hidden rounded-2xl bg-white shadow-[0_16px_30px_rgba(5,20,12,0.18)] transition-transform duration-300 hover:-translate-y-1">
                                <a href="{{ route('product.show', $saleProduct->id) }}" class="block">
                                    <div class="relative aspect-[4/5] overflow-hidden bg-gray-100">
                                        @if($saleProduct->image_path)
                                            <img src="{{ asset('storage/' . $saleProduct->image_path) }}"
                                                 alt="{{ $saleProduct->name }}"
                                                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                 loading="lazy">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">No image</div>
                                        @endif

                                        <span class="absolute right-3 top-3 rounded-xl bg-rose-600 px-2.5 py-1 text-xs font-bold text-white">
                                            -{{ $discountPercent }}%
                                        </span>
                                    </div>

                                    <div class="p-4">
                                        <h3 class="line-clamp-2 text-xl font-extrabold text-slate-800">{{ $saleProduct->name }}</h3>
                                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                            {{ $saleProduct->seller?->store_name ?? 'Ukay Hub Seller' }}
                                        </p>

                                        <div class="mt-3 flex items-baseline gap-2">
                                            <span class="text-3xl font-extrabold text-rose-600">₱{{ number_format($salePrice, 0) }}</span>
                                            <span class="text-sm font-semibold text-slate-400 line-through">₱{{ number_format($originalPrice, 0) }}</span>
                                        </div>
                                    </div>
                                </a>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-white/40 bg-white/10 px-5 py-12 text-center backdrop-blur-sm">
                        <p class="text-xl font-bold text-white">No sale products right now.</p>
                        <p class="mt-2 text-sm text-emerald-50/85">Check back soon for fresh markdowns from our sellers.</p>
                        <a href="{{ route('catalog') }}"
                           class="mt-5 inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-[#1f6a49] transition hover:bg-emerald-100">
                            Browse Catalog
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div x-cloak x-show="toastOpen" x-transition.opacity.duration.150ms
         class="fixed right-4 top-20 z-[80]">
        <div class="rounded-md px-3 py-2 text-xs font-medium shadow-lg"
             :class="toastLevel === 'error' ? 'bg-red-600 text-white' : 'bg-gray-900 text-white'"
             x-text="toastMessage"></div>
    </div>
</div>
