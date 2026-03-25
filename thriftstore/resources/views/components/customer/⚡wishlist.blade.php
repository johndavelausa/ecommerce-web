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
    public function customer()
    {
        return Auth::guard('web')->user();
    }

    #[Computed]
    public function items()
    {
        $customer = $this->customer;
        if (! $customer) {
            return collect();
        }

        return Wishlist::query()
            ->with(['product.seller'])
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function remove(int $productId): void
    {
        $customer = $this->customer;
        if (! $customer) {
            return;
        }

        Wishlist::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->delete();

        $count = Wishlist::where('customer_id', $customer->id)->count();
        $this->dispatch('wishlist-updated', count: $count);
    }

    public function addToCart(int $productId): void
    {
        $customer = $this->customer;
        if (! $customer) {
            return;
        }

        $product = Product::query()
            ->with('seller')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereHas('seller', function ($q) {
                $q->where('status', 'approved')
                  ->where('is_open', true);
            })
            ->findOrFail($productId);

        $cart = Session::get('cart', []);
        $key = (string) $product->id;
        if (! isset($cart[$key]) && count($cart) >= 50) {
            $this->addError('cart', __('Cart is full (max 50 items). Remove an item or checkout first.'));
            return;
        }
        $currentQty = $cart[$key]['quantity'] ?? 0;
        $newQty = min($currentQty + 1, $product->stock);

        $cart[$key] = [
            'product_id' => $product->id,
            'seller_id'  => $product->seller_id,
            'name'       => $product->name,
            'price'      => (float) ($product->sale_price ?? $product->price),
            'image_path' => $product->image_path,
            'quantity'   => $newQty,
        ];

        Session::put('cart', $cart);
        $count = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cart));
        $this->dispatch('cart-updated', count: $count);
    }
};
?>

<div>

{{-- ── Page header ── --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold" style="color:#212121;">My Wishlist</h1>
        <p class="text-sm mt-0.5" style="color:#9E9E9E;">Items saved for later · not reserved or in your cart</p>
    </div>
    <a href="{{ route('customer.dashboard') }}"
       class="text-xs font-medium px-3 py-1.5 rounded-lg transition"
       style="color:#2D9F4E; background:#E8F5E9; border:1px solid #C8E6C9;"
       onmouseover="this.style.background='#C8E6C9';" onmouseout="this.style.background='#E8F5E9';">
        ← Browse Products
    </a>
</div>

@error('cart')
    <div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-xl text-sm" style="background:#FFEBEE; border:1px solid #FFCDD2; color:#EF5350;">
        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        {{ $message }}
    </div>
@enderror

@php($items = $this->items)

@if($items->isEmpty())
    {{-- Empty state --}}
    <div class="bg-white rounded-2xl p-16 flex flex-col items-center text-center" style="border:1px solid #F5F5F5; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4" style="background:#FFF3E0;">
            <svg class="w-8 h-8" style="color:#F57C00;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
        </div>
        <h3 class="text-base font-bold mb-1" style="color:#212121;">Your wishlist is empty</h3>
        <p class="text-sm mb-6" style="color:#9E9E9E;">Browse the catalog and tap the heart icon to save items here.</p>
        <a href="{{ route('customer.dashboard') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-white"
           style="background:#2D9F4E;"
           onmouseover="this.style.background='#1B7A37';" onmouseout="this.style.background='#2D9F4E';">
            Browse Products
        </a>
    </div>
@else
    {{-- Item count badge --}}
    <div class="mb-4 flex items-center gap-2">
        <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="background:#E8F5E9; color:#2D9F4E;">
            {{ $items->count() }} {{ $items->count() === 1 ? 'item' : 'items' }} saved
        </span>
    </div>

    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
        @foreach($items as $row)
            @php($product = $row->product)
            @if(!$product) @continue @endif

            <div class="bg-white rounded-2xl overflow-hidden flex flex-col transition"
                 style="border:1px solid #F5F5F5; border-top:3px solid {{ $product->stock <= 0 ? '#FFCDD2' : '#A5D6A7' }}; box-shadow:0 2px 8px rgba(0,0,0,0.04);">

                {{-- Product image --}}
                <div class="relative h-44" style="background:#F8F9FA;">
                    @if($product->image_path)
                        <img src="{{ $product->image_url }}"
                             alt="{{ $product->name }}"
                             class="w-full h-full object-cover"
                             loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <svg class="w-12 h-12" style="color:#E0E0E0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    @endif

                    {{-- Out of stock overlay --}}
                    @if($product->stock <= 0)
                        <div class="absolute inset-0 flex items-center justify-center" style="background:rgba(0,0,0,0.35);">
                            <span class="px-3 py-1 rounded-full text-xs font-bold text-white" style="background:#EF5350;">Out of Stock</span>
                        </div>
                    @endif

                    {{-- Sale badge --}}
                    @if($product->sale_price)
                        <div class="absolute top-2 left-2">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold text-white" style="background:#F57C00;">SALE</span>
                        </div>
                    @endif
                </div>

                {{-- Product info --}}
                <div class="flex-1 flex flex-col p-4 gap-1">
                    <p class="text-sm font-semibold line-clamp-2 leading-snug" style="color:#212121;">{{ $product->name }}</p>
                    <p class="text-xs" style="color:#9E9E9E;">{{ $product->seller->store_name ?? 'Thrift Seller' }}</p>

                    <div class="mt-2 flex items-baseline gap-2">
                        @if($product->sale_price)
                            <span class="text-base font-bold" style="color:#2D9F4E;">₱{{ number_format($product->sale_price, 2) }}</span>
                            <span class="text-xs line-through" style="color:#BDBDBD;">₱{{ number_format($product->price, 2) }}</span>
                        @else
                            <span class="text-base font-bold" style="color:#2D9F4E;">₱{{ number_format($product->price, 2) }}</span>
                        @endif
                    </div>

                    @if($product->stock > 0)
                        <p class="text-xs" style="color:#9E9E9E;">{{ $product->stock }} in stock</p>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="px-4 pb-4 flex flex-col gap-2">
                    @if($product->stock > 0)
                        <button type="button" wire:click="addToCart({{ $product->id }})"
                                class="group w-full flex items-center justify-center gap-2 rounded-xl text-white transition-all duration-200"
                                style="background:#2D9F4E; height:36px; padding:0 12px;"
                                onmouseover="this.style.background='#1B7A37';" onmouseout="this.style.background='#2D9F4E';">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span class="text-xs font-bold whitespace-nowrap max-w-0 overflow-hidden group-hover:max-w-xs transition-all duration-300">Move to Cart</span>
                        </button>
                    @else
                        <button type="button" disabled
                                class="w-full flex items-center justify-center gap-2 rounded-xl text-white cursor-not-allowed"
                                style="background:#E0E0E0; height:36px; padding:0 12px;">
                            <svg class="w-4 h-4 flex-shrink-0" style="color:#9E9E9E;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span class="text-xs font-semibold" style="color:#9E9E9E;">Out of Stock</span>
                        </button>
                    @endif
                    <button type="button" wire:click="remove({{ $product->id }})"
                            class="group w-full flex items-center justify-center gap-2 rounded-xl transition-all duration-200"
                            style="color:#9E9E9E; border:1px solid #E0E0E0; height:36px; padding:0 12px;"
                            onmouseover="this.style.borderColor='#EF9A9A'; this.style.color='#EF5350'; this.style.background='#FFF5F5';"
                            onmouseout="this.style.borderColor='#E0E0E0'; this.style.color='#9E9E9E'; this.style.background='';">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span class="text-xs font-semibold whitespace-nowrap max-w-0 overflow-hidden group-hover:max-w-xs transition-all duration-300">Remove</span>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
@endif

</div>

