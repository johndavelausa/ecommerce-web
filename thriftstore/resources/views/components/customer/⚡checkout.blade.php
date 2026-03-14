<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Address;
use App\Notifications\NewOrderForSeller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

new class extends Component
{
    public string $shipping_address = '';
    public bool $placed = false;
    public array $createdOrders = [];

    public function mount(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::guard('web')->user();
        if ($user && $user->address) {
            $this->shipping_address = (string) $user->address;
        }
    }

    public function getItemsProperty(): array
    {
        return Session::get('cart', []);
    }

    public function placeOrder(): void
    {
        $this->validate([
            'shipping_address' => ['required', 'string', 'max:5000'],
        ]);

        $items = $this->items;
        if (empty($items)) {
            $this->addError('shipping_address', 'Your cart is empty.');
            return;
        }

        /** @var \App\Models\User|null $customer */
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            abort(403);
        }

        $grouped = [];
        foreach ($items as $row) {
            $grouped[$row['seller_id']][] = $row;
        }

        $createdOrders = [];

        DB::transaction(function () use ($grouped, $customer, &$createdOrders) {
            foreach ($grouped as $sellerId => $rows) {
                $orderTotal = 0;

                // Re-check stock and price from DB
                $products = Product::whereIn('id', array_column($rows, 'product_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($rows as $row) {
                    $product = $products[$row['product_id']] ?? null;
                    if (! $product || $product->stock < $row['quantity']) {
                        continue;
                    }
                    $linePrice = (float) ($product->sale_price ?? $product->price);
                    $orderTotal += $linePrice * $row['quantity'];
                }

                if ($orderTotal <= 0) {
                    continue;
                }

                $order = Order::create([
                    'customer_id'      => $customer->id,
                    'seller_id'        => $sellerId,
                    'tracking_number'  => null,
                    'status'           => 'processing',
                    'total_amount'     => $orderTotal,
                    'shipping_address' => $this->shipping_address,
                ]);

                foreach ($rows as $row) {
                    $product = $products[$row['product_id']] ?? null;
                    if (! $product || $product->stock < $row['quantity']) {
                        continue;
                    }

                    $linePrice = (float) ($product->sale_price ?? $product->price);

                    OrderItem::create([
                        'order_id'         => $order->id,
                        'product_id'       => $product->id,
                        'quantity'         => $row['quantity'],
                        'price_at_purchase'=> $linePrice,
                    ]);

                    $oldStock = $product->stock;
                    $product->decrement('stock', $row['quantity']);
                    $product->refresh();
                    $product->notifyWishlistLowStockIfNeeded($oldStock, $product->stock);
                }

                $createdOrders[] = $order->id;

                // Notify seller user about new order
                $sellerUser = $order->seller?->user;
                if ($sellerUser) {
                    $sellerUser->notify(new NewOrderForSeller($order));
                }
            }
        });

        if (empty($createdOrders)) {
            $this->addError('shipping_address', 'Could not place order. Please check product availability.');
            return;
        }

        Session::forget('cart');
        $this->placed = true;
        $this->createdOrders = $createdOrders;
        $this->dispatch('cart-updated');
    }
};
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900">Checkout (Cash on Delivery)</h3>
        <p class="mt-1 text-sm text-gray-500">
            Confirm your shipping address and review your items. Orders will be created per seller.
        </p>
    </div>

    @php
        $items = $this->items;
        $subtotal = 0;
        foreach ($items as $row) {
            $subtotal += $row['price'] * $row['quantity'];
        }
        $sellerIds = array_unique(array_column($items, 'seller_id'));
        $sellers = \App\Models\Seller::whereIn('id', $sellerIds)->get()->keyBy('id');
    @endphp

    @if($this->placed)
        <div class="bg-white rounded-lg shadow p-6 space-y-3">
            <div class="rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">
                Your order has been placed. You can track it in your orders section (to be implemented).
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 bg-white rounded-lg shadow p-4 sm:p-6 space-y-4">
            <h4 class="text-sm font-semibold text-gray-900">Shipping address</h4>
            @php
                /** @var \App\Models\User|null $checkoutUser */
                $checkoutUser = \Illuminate\Support\Facades\Auth::guard('web')->user();
                $savedAddresses = $checkoutUser
                    ? $checkoutUser->addresses()->orderByDesc('is_default')->orderBy('created_at')->get()
                    : collect();
            @endphp
            @if($savedAddresses->isNotEmpty())
                <div class="space-y-2 mb-3">
                    <p class="text-xs text-gray-600">
                        Select one of your saved addresses or edit the text area below.
                    </p>
                    <div class="grid gap-2">
                        @foreach($savedAddresses as $addr)
                            @php
                                $full = trim($addr->recipient_name ?: $checkoutUser->name)."\n"
                                      . $addr->line1.($addr->line2 ? ', '.$addr->line2 : '')."\n"
                                      . trim($addr->city.' '.$addr->region.' '.$addr->postal_code)."\n"
                                      . ($addr->phone ? 'Phone: '.$addr->phone : '');
                            @endphp
                            <label class="flex items-start gap-2 text-xs cursor-pointer border rounded-md px-3 py-2 hover:border-indigo-400">
                                <input type="radio"
                                       name="selected_address"
                                       value="{{ $full }}"
                                       class="mt-1 h-3 w-3 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                       onclick="@this.set('shipping_address', '{{ str_replace(["\r", "\n"], [' ', ' '], addslashes($full)) }}')">
                                <span>
                                    <span class="font-semibold text-gray-900">
                                        {{ $addr->label }}
                                        @if($addr->is_default)
                                            <span class="ml-1 text-[10px] text-green-700">(Default)</span>
                                        @endif
                                    </span>
                                    <span class="block text-gray-700">
                                        {{ $full }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
            <div>
                <textarea wire:model.defer="shipping_address" rows="4"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Full address including barangay, city, and contact details"></textarea>
                @error('shipping_address') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <h4 class="text-sm font-semibold text-gray-900 mt-4">Items</h4>
            @if(empty($items))
                <div class="py-8 text-center text-gray-500 text-sm">
                    Your cart is empty. Add items before checking out.
                </div>
            @else
                <div class="divide-y divide-gray-200">
                    @foreach($items as $row)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                @if(!empty($row['image_path']))
                                    <img src="{{ asset('storage/'.$row['image_path']) }}"
                                         alt="{{ $row['name'] }}"
                                         class="h-10 w-10 rounded object-cover">
                                @else
                                    <div class="h-10 w-10 rounded bg-gray-100 flex items-center justify-center text-xs text-gray-400">
                                        No img
                                    </div>
                                @endif
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $row['name'] }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Qty: {{ $row['quantity'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-sm font-medium text-gray-900">
                                ₱{{ number_format($row['price'] * $row['quantity'], 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow p-4 sm:p-6 space-y-4">
            <h4 class="text-sm font-semibold text-gray-900">Summary</h4>
            <div class="space-y-1 text-sm text-gray-700">
                <div class="flex justify-between">
                    <span>Subtotal</span>
                    <span>₱{{ number_format($subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-500 text-xs">
                    <span>Shipping</span>
                    <span>To be confirmed (COD)</span>
                </div>
            </div>
            @if($sellers->isNotEmpty())
                <div class="mt-3 space-y-1 text-xs text-gray-600 border-t pt-3">
                    <div class="font-semibold text-gray-800">Seller GCash numbers</div>
                    @foreach($sellers as $seller)
                        @if($seller->gcash_number)
                            <div class="flex justify-between">
                                <span>{{ $seller->store_name }}</span>
                                <span class="font-mono">{{ $seller->gcash_number }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
            <div class="border-t pt-3 flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Total (items only)
                </div>
                <div class="text-xl font-semibold text-gray-900">
                    ₱{{ number_format($subtotal, 2) }}
                </div>
            </div>

            <button type="button"
                    wire:click="placeOrder"
                    wire:loading.attr="disabled"
                    class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Place order (COD)
            </button>
        </div>
    </div>
</div>

