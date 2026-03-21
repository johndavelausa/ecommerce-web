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

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6 flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900">My Cart</h3>
        <div class="flex items-center gap-3">
            @if(count($this->items))
                <button type="button" wire:click="clear"
                        class="text-xs text-red-600 hover:text-red-800 font-medium">
                    Clear cart
                </button>
                <a href="{{ route('customer.checkout') }}"
                   class="inline-flex items-center px-3 py-1.5 bg-[#2D9F4E] border border-[#2D9F4E] rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-[#1B7A37] transition-colors">
                    Checkout
                </a>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
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

        @if(!empty($items) && $sellerCount > 1)
            <div class="px-4 py-2 bg-amber-50 border-b border-amber-100 text-xs text-amber-800">
                Items from <strong>{{ $sellerCount }} sellers</strong> — at checkout you will place {{ $sellerCount }} separate orders (one per seller, separate deliveries).
            </div>
        @endif

        @if(empty($items))
            <div class="py-12 text-center text-gray-500 text-sm">
                Your cart is empty. Browse products and click “Add to cart”.
            </div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach($items as $key => $row)
                        <tr>
                            <td class="px-4 py-3 flex items-center gap-3">
                                @if(!empty($row['image_path']))
                                    <img src="{{ asset('storage/'.$row['image_path']) }}"
                                     loading="lazy"
                                         alt="{{ $row['name'] }}"
                                         class="h-12 w-12 rounded object-cover">
                                @else
                                    <div class="h-12 w-12 rounded bg-gray-100 flex items-center justify-center text-xs text-gray-400">
                                        No img
                                    </div>
                                @endif
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $row['name'] }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700">
                                ₱{{ number_format($row['price'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="inline-flex items-center rounded border border-gray-300 overflow-hidden">
                                    <button type="button" wire:click="decrement('{{ $key }}')"
                                            class="px-2 py-1 text-xs text-gray-600 hover:bg-gray-50">−</button>
                                    <span class="px-3 text-sm text-gray-900">{{ $row['quantity'] }}</span>
                                    <button type="button" wire:click="increment('{{ $key }}')"
                                            class="px-2 py-1 text-xs text-gray-600 hover:bg-gray-50">+</button>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 font-medium">
                                ₱{{ number_format($row['price'] * $row['quantity'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="remove('{{ $key }}')"
                                        class="text-xs text-red-600 hover:text-red-800 font-medium">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="px-4 py-3 border-t space-y-1 text-sm">
                <div class="flex justify-between text-gray-700">
                    <span>Subtotal</span>
                    <span>₱{{ number_format($subtotal, 2) }}</span>
                </div>
                @if($deliveryTotal > 0)
                    <div class="flex justify-between text-gray-700">
                        <span>Delivery (est.)</span>
                        <span>₱{{ number_format($deliveryTotal, 2) }}</span>
                    </div>
                @else
                    <div class="flex justify-between text-gray-500 text-xs">
                        <span>Delivery</span>
                        <span>Free</span>
                    </div>
                @endif
            </div>
            <div class="px-4 py-4 border-t flex items-center justify-between">
                <div class="text-sm font-medium text-gray-900">
                    Total
                </div>
                <div class="text-lg font-semibold text-[#2D9F4E]">
                    ₱{{ number_format($grandTotal, 2) }}
                </div>
            </div>
        @endif
    </div>
</div>

