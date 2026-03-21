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

<div class="space-y-4">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900">Saved for later</h3>
        <p class="mt-1 text-sm text-gray-500">
            Items in your wishlist are not reserved and not in your cart.
        </p>
    </div>

    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        @php($items = $this->items)
        @if($items->isEmpty())
            <div class="py-8 text-center text-gray-500 text-sm">
                Your wishlist is empty. Browse the catalog and click “Save” to add items here.
            </div>
        @else
            <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                @foreach($items as $row)
                    @php($product = $row->product)
                    @if(!$product) @continue @endif
                    <div class="border border-gray-200 rounded-lg overflow-hidden flex flex-col">
                        <div class="relative h-40 bg-gray-100">
                            @if($product->image_path)
                                <img src="{{ asset('storage/'.$product->image_path) }}"
                                     alt="{{ $product->name }}"
                                     class="w-full h-full object-cover"
                                     loading="lazy">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                                    No image
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 flex flex-col p-3 space-y-1">
                            <div class="text-sm font-semibold text-gray-900 line-clamp-2">
                                {{ $product->name }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $product->seller->store_name ?? 'Thrift seller' }}
                            </div>
                            <div class="mt-1 flex items-baseline gap-2">
                                @if($product->sale_price)
                                    <span class="text-base font-semibold text-[#2D9F4E]">
                                        ₱{{ number_format($product->sale_price, 2) }}
                                    </span>
                                    <span class="text-xs text-gray-400 line-through">
                                        ₱{{ number_format($product->price, 2) }}
                                    </span>
                                @else
                                    <span class="text-base font-semibold text-[#2D9F4E]">
                                        ₱{{ number_format($product->price, 2) }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">
                                @if($product->stock > 0)
                                    {{ $product->stock }} in stock
                                @else
                                    <span class="text-rose-600 font-medium">Out of stock</span>
                                @endif
                            </div>
                        </div>
                        <div class="px-3 pb-3 space-y-2">
                            <button type="button"
                                    wire:click="remove({{ $product->id }})"
                                    class="w-full inline-flex justify-center items-center px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                Remove
                            </button>
                            @if($product->stock > 0)
                                <button type="button"
                                        wire:click="addToCart({{ $product->id }})"
                                        class="w-full inline-flex justify-center items-center px-3 py-2 bg-[#2D9F4E] border border-[#2D9F4E] rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-[#1B7A37] focus:outline-none focus:ring-2 focus:ring-[#2D9F4E] focus:ring-offset-2 transition-colors">
                                    Move to cart
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

