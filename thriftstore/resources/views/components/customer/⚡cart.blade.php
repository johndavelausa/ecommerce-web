<?php

use App\Models\Product;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

new class extends Component
{
    public function getItemsProperty(): array
    {
        return Session::get('cart', []);
    }

    public function increment(string $key): void
    {
        $cart = Session::get('cart', []);
        if (! isset($cart[$key])) return;

        $product = Product::find($cart[$key]['product_id']);
        if (! $product || $product->stock <= $cart[$key]['quantity']) {
            return;
        }

        $cart[$key]['quantity']++;
        Session::put('cart', $cart);
        $count = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cart));
        $this->dispatch('cart-updated', count: $count);
    }

    public function decrement(string $key): void
    {
        $cart = Session::get('cart', []);
        if (! isset($cart[$key])) return;

        $cart[$key]['quantity']--;
        if ($cart[$key]['quantity'] <= 0) {
            unset($cart[$key]);
        }

        Session::put('cart', $cart);
        $count = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cart));
        $this->dispatch('cart-updated', count: $count);
    }

    public function remove(string $key): void
    {
        $cart = Session::get('cart', []);
        unset($cart[$key]);
        Session::put('cart', $cart);
        $count = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cart));
        $this->dispatch('cart-updated', count: $count);
    }

    public function clear(): void
    {
        Session::forget('cart');
        $this->dispatch('cart-updated', count: 0);
    }
};
?>

@php
    $items = $this->items;
    $subtotal = 0;
    foreach ($items as $row) {
        $subtotal += $row['price'] * $row['quantity'];
    }
    $deliveryTotal = 0.0;
    $sellerCount = 0;
    if (!empty($items)) {
        $sellerIds = array_unique(array_column($items, 'seller_id'));
        $sellerCount = count($sellerIds);
        $sellers = \App\Models\Seller::whereIn('id', $sellerIds)->get()->keyBy('id');
        $productIds = array_unique(array_column($items, 'product_id'));
        $productsForDelivery = \App\Models\Product::whereIn('id', $productIds)->get()->keyBy('id');
        foreach ($sellerIds as $sid) {
            $groupRows = array_values(array_filter($items, fn($r) => (int)$r['seller_id'] === (int)$sid));
            $sellerModel = $sellers->get($sid);
            if ($sellerModel) {
                $deliveryTotal += $sellerModel->computeDeliveryFee($groupRows, $productsForDelivery);
            }
        }
    }
    $grandTotal = $subtotal + $deliveryTotal;
@endphp

<div class="min-h-screen">
{{-- ── Page header ── --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold" style="color:#212121;">My Cart</h1>
        <p class="text-sm mt-0.5" style="color:#9E9E9E;">
            {{ count($items) }} {{ \Illuminate\Support\Str::plural('item', count($items)) }}
        </p>
    </div>
    @if(count($items))
        <button type="button" wire:click="clear"
                class="text-xs font-medium px-3 py-1.5 rounded-lg transition"
                style="color:#EF5350; background:#FFEBEE; border:1px solid #FFCDD2;"
                onmouseover="this.style.background='#FFCDD2';" onmouseout="this.style.background='#FFEBEE';">
            Clear all
        </button>
    @endif
</div>

@if(empty($items))
    {{-- ── Empty state ── --}}
    <div class="bg-white rounded-2xl flex flex-col items-center justify-center py-24 text-center" style="border:1px solid #F5F5F5;">
        <div class="w-20 h-20 rounded-full flex items-center justify-center mb-5" style="background:#F8F9FA;">
            <svg class="w-10 h-10" style="color:#E0E0E0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </div>
        <p class="font-semibold text-base" style="color:#424242;">Your cart is empty</p>
        <p class="text-sm mt-1 mb-6" style="color:#9E9E9E;">Browse products and add items to get started.</p>
        <a href="{{ route('customer.dashboard') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition"
           style="background:#2D9F4E;"
           onmouseover="this.style.background='#1B7A37';" onmouseout="this.style.background='#2D9F4E';">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
            </svg>
            Shop Now
        </a>
    </div>

@else
    {{-- ── Two-column layout ── --}}
    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ── Left: Items ── --}}
        <div class="flex-1 min-w-0 space-y-3">

            @if($sellerCount > 1)
                <div class="flex items-center gap-2 px-4 py-3 rounded-xl text-sm" style="background:#FFF3E0; border:1px solid #FFE0B2; color:#F57C00;">
                    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Items from <strong class="mx-1">{{ $sellerCount }} sellers</strong> — you'll place {{ $sellerCount }} separate orders at checkout.
                </div>
            @endif

            @foreach($items as $key => $row)
                <div class="bg-white rounded-2xl p-4 flex items-center gap-4 transition" style="border:1px solid #F5F5F5;">
                    {{-- Image --}}
                    @if(!empty($row['image_path']))
                        <img src="{{ str_starts_with((string)$row['image_path'], 'data:') ? $row['image_path'] : asset('storage/'.$row['image_path']) }}" loading="lazy"
                             alt="{{ $row['name'] }}"
                             class="w-20 h-20 rounded-xl object-cover flex-shrink-0" style="border:1px solid #F5F5F5;">
                    @else
                        <div class="w-20 h-20 rounded-xl flex-shrink-0 flex items-center justify-center" style="background:#F8F9FA; border:1px solid #F5F5F5;">
                            <svg class="w-8 h-8" style="color:#E0E0E0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    @endif

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm leading-snug line-clamp-2" style="color:#212121;">{{ $row['name'] }}</p>
                        <p class="text-sm mt-1 font-medium" style="color:#F57C00;">₱{{ number_format($row['price'], 2) }}</p>
                    </div>

                    {{-- Qty stepper --}}
                    <div class="flex items-center gap-1 flex-shrink-0" style="background:#F8F9FA; border:1px solid #E0E0E0; border-radius:10px; padding:3px;">
                        <button type="button" wire:click="decrement('{{ $key }}')"
                                class="w-7 h-7 flex items-center justify-center rounded-lg text-base font-bold transition"
                                style="color:#424242;"
                                onmouseover="this.style.background='#E8F5E9';this.style.color='#2D9F4E';" onmouseout="this.style.background='transparent';this.style.color='#424242';">−</button>
                        <span class="w-8 text-center text-sm font-semibold" style="color:#212121;">{{ $row['quantity'] }}</span>
                        <button type="button" wire:click="increment('{{ $key }}')"
                                class="w-7 h-7 flex items-center justify-center rounded-lg text-base font-bold transition"
                                style="color:#424242;"
                                onmouseover="this.style.background='#E8F5E9';this.style.color='#2D9F4E';" onmouseout="this.style.background='transparent';this.style.color='#424242';">+</button>
                    </div>

                    {{-- Line total --}}
                    <div class="text-right flex-shrink-0 w-20">
                        <p class="font-bold text-sm" style="color:#212121;">₱{{ number_format($row['price'] * $row['quantity'], 2) }}</p>
                    </div>

                    {{-- Remove --}}
                    <button type="button" wire:click="remove('{{ $key }}')"
                            class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-lg transition"
                            style="color:#BDBDBD;"
                            onmouseover="this.style.color='#EF5350';this.style.background='#FFEBEE';" onmouseout="this.style.color='#BDBDBD';this.style.background='transparent';"
                            title="Remove">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>

        {{-- ── Right: Order Summary ── --}}
        <div class="w-full lg:w-80 flex-shrink-0 lg:sticky lg:top-24">
            <div class="bg-white rounded-2xl p-5" style="border:1px solid #F5F5F5;">
                <h3 class="font-bold text-base mb-4" style="color:#212121;">Order Summary</h3>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between" style="color:#616161;">
                        <span>Subtotal ({{ count($items) }} {{ \Illuminate\Support\Str::plural('item', count($items)) }})</span>
                        <span class="font-medium" style="color:#212121;">₱{{ number_format($subtotal, 2) }}</span>
                    </div>

                    <div class="flex justify-between" style="color:#616161;">
                        <span>Delivery (est.)</span>
                        @if($deliveryTotal > 0)
                            <span class="font-medium" style="color:#212121;">₱{{ number_format($deliveryTotal, 2) }}</span>
                        @else
                            <span class="font-semibold" style="color:#2D9F4E;">Free</span>
                        @endif
                    </div>
                </div>

                <div class="my-4" style="border-top:1px solid #F5F5F5;"></div>

                <div class="flex justify-between items-center mb-5">
                    <span class="font-bold text-base" style="color:#212121;">Total</span>
                    <span class="font-bold text-xl" style="color:#F57C00;">₱{{ number_format($grandTotal, 2) }}</span>
                </div>

                <a href="{{ route('customer.checkout') }}"
                   class="flex items-center justify-center gap-2 w-full py-3 rounded-xl font-bold text-sm text-white transition"
                   style="background:#2D9F4E;"
                   onmouseover="this.style.background='#1B7A37';" onmouseout="this.style.background='#2D9F4E';">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Proceed to Checkout
                </a>

                <a href="{{ route('customer.dashboard') }}"
                   class="flex items-center justify-center gap-1 w-full py-2.5 mt-2 rounded-xl text-sm font-medium transition"
                   style="color:#2D9F4E; background:transparent;"
                   onmouseover="this.style.background='#E8F5E9';" onmouseout="this.style.background='transparent';">
                    ← Continue Shopping
                </a>
            </div>
        </div>

    </div>
@endif
</div>
