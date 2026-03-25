<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Address;
use App\Notifications\NewOrderForSeller;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public string $shipping_address = '';
    public string $payment_method = 'cod';
    /** B3 v1.4 — Order notes from customer (e.g. delivery instructions) */
    public string $customer_note = '';
    public ?string $checkoutSnapshotToken = null;
    public ?string $checkoutSnapshotVersion = null;
    public bool $snapshotMismatch = false;
    public bool $placed = false;
    public array $createdOrders = [];

    public function mount(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::guard('web')->user();
        if ($user && $user->address) {
            $this->shipping_address = (string) $user->address;
        }

        $this->createCheckoutSnapshot(true);
    }

    public function getItemsProperty(): array
    {
        return Session::get('cart', []);
    }

    protected function normalizeCartItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $row) {
            $normalized[] = [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'seller_id' => (int) ($row['seller_id'] ?? 0),
                'quantity' => (int) ($row['quantity'] ?? 0),
            ];
        }

        usort($normalized, function (array $a, array $b): int {
            return [$a['seller_id'], $a['product_id']] <=> [$b['seller_id'], $b['product_id']];
        });

        return $normalized;
    }

    protected function computeCartHash(array $items): string
    {
        return hash('sha256', json_encode($this->normalizeCartItems($items)));
    }

    protected function buildCheckoutSnapshot(array $items): array
    {
        $normalized = $this->normalizeCartItems($items);
        if (empty($normalized)) {
            return [
                'cart_hash' => $this->computeCartHash([]),
                'version_hash' => hash('sha256', 'empty'),
                'snapshot' => [
                    'sellers' => [],
                    'grand_total' => 0.0,
                ],
            ];
        }

        $sellerIds = array_values(array_unique(array_column($normalized, 'seller_id')));
        $productIds = array_values(array_unique(array_column($normalized, 'product_id')));

        $sellers = Seller::query()->whereIn('id', $sellerIds)->get()->keyBy('id');
        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $sellersPayload = [];
        $grandTotal = 0.0;

        foreach ($sellerIds as $sellerId) {
            $rows = array_values(array_filter($normalized, fn ($r) => $r['seller_id'] === (int) $sellerId));
            $seller = $sellers->get($sellerId);

            $itemsPayload = [];
            $subtotal = 0.0;
            foreach ($rows as $row) {
                $product = $products->get($row['product_id']);
                if (! $product) {
                    continue;
                }

                $unitPrice = (float) ($product->sale_price ?? $product->price);
                $lineTotal = $unitPrice * $row['quantity'];
                $subtotal += $lineTotal;

                $itemsPayload[] = [
                    'product_id' => (int) $product->id,
                    'quantity' => (int) $row['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $rowsForDelivery = array_map(function (array $r): array {
                return [
                    'product_id' => $r['product_id'],
                    'seller_id' => $r['seller_id'],
                    'quantity' => $r['quantity'],
                ];
            }, $rows);

            $deliveryFee = $seller ? (float) $seller->computeDeliveryFee($rowsForDelivery, $products) : 0.0;
            $sellerTotal = $subtotal + $deliveryFee;
            $grandTotal += $sellerTotal;

            $sellersPayload[(string) $sellerId] = [
                'items' => $itemsPayload,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $sellerTotal,
            ];
        }

        $snapshot = [
            'sellers' => $sellersPayload,
            'grand_total' => $grandTotal,
        ];

        return [
            'cart_hash' => hash('sha256', json_encode($normalized)),
            'version_hash' => hash('sha256', json_encode($snapshot)),
            'snapshot' => $snapshot,
        ];
    }

    protected function createCheckoutSnapshot(bool $force = false): void
    {
        $items = $this->items;
        if (empty($items)) {
            $this->checkoutSnapshotToken = null;
            $this->checkoutSnapshotVersion = null;

            return;
        }

        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return;
        }

        $snapshotData = $this->buildCheckoutSnapshot($items);

        if (! $force && $this->checkoutSnapshotVersion === $snapshotData['version_hash']) {
            return;
        }

        DB::table('checkout_snapshots')
            ->where('customer_id', $customer->id)
            ->whereNull('consumed_at')
            ->delete();

        $token = (string) Str::uuid();
        DB::table('checkout_snapshots')->insert([
            'customer_id' => $customer->id,
            'snapshot_token' => $token,
            'cart_hash' => $snapshotData['cart_hash'],
            'snapshot_version' => $snapshotData['version_hash'],
            'snapshot_payload' => json_encode($snapshotData['snapshot']),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->checkoutSnapshotToken = $token;
        $this->checkoutSnapshotVersion = $snapshotData['version_hash'];
    }

    public function refreshCheckoutSnapshotAction(): void
    {
        $this->snapshotMismatch = false;
        $this->resetErrorBag();
        $this->createCheckoutSnapshot(true);
    }

    public function placeOrder(): void
    {
        $this->validate([
            'shipping_address' => ['required', 'string', 'max:5000'],
            'payment_method' => ['required', 'in:cod'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
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

        $this->createCheckoutSnapshot();
        $currentSnapshot = $this->buildCheckoutSnapshot($items);

        $snapshotRow = null;
        if ($this->checkoutSnapshotToken) {
            $snapshotRow = DB::table('checkout_snapshots')
                ->where('customer_id', $customer->id)
                ->where('snapshot_token', $this->checkoutSnapshotToken)
                ->whereNull('consumed_at')
                ->first();
        }

        $isStaleSnapshot = ! $snapshotRow
            || $snapshotRow->expires_at < now()
            || $snapshotRow->cart_hash !== $currentSnapshot['cart_hash']
            || $snapshotRow->snapshot_version !== $currentSnapshot['version_hash'];

        if ($isStaleSnapshot) {
            $this->snapshotMismatch = true;
            $this->createCheckoutSnapshot(true);
            $this->addError('shipping_address', 'Checkout pricing changed or session expired. Please review updated totals, then place order again.');

            return;
        }

        $snapshotPayload = json_decode((string) $snapshotRow->snapshot_payload, true);
        if (! is_array($snapshotPayload) || ! isset($snapshotPayload['sellers'])) {
            $this->snapshotMismatch = true;
            $this->createCheckoutSnapshot(true);
            $this->addError('shipping_address', 'Checkout snapshot invalid. Please retry checkout.');

            return;
        }

        $createdOrders = [];

        try {
            DB::transaction(function () use ($snapshotPayload, $customer, &$createdOrders) {
                foreach ($snapshotPayload['sellers'] as $sellerId => $sellerData) {
                    $rows = $sellerData['items'] ?? [];
                    if (empty($rows)) {
                        continue;
                    }

                    $sellerId = (int) $sellerId;
                    $seller = Seller::find($sellerId);
                    $products = Product::whereIn('id', array_column($rows, 'product_id'))
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    $subtotal = 0.0;
                    foreach ($rows as $row) {
                        $product = $products[$row['product_id']] ?? null;
                        if (! $product || $product->stock < (int) $row['quantity']) {
                            throw new \RuntimeException('snapshot_stock_mismatch');
                        }

                        $linePrice = (float) ($product->sale_price ?? $product->price);
                        if (abs($linePrice - (float) $row['unit_price']) > 0.0001) {
                            throw new \RuntimeException('snapshot_price_mismatch');
                        }

                        $subtotal += $linePrice * (int) $row['quantity'];
                    }

                    $rowsForDelivery = array_map(function (array $r) use ($sellerId): array {
                        return [
                            'product_id' => (int) $r['product_id'],
                            'seller_id' => $sellerId,
                            'quantity' => (int) $r['quantity'],
                        ];
                    }, $rows);

                    $deliveryFee = $seller ? (float) $seller->computeDeliveryFee($rowsForDelivery, $products) : 0.0;
                    $expectedDeliveryFee = (float) ($sellerData['delivery_fee'] ?? 0);
                    if (abs($deliveryFee - $expectedDeliveryFee) > 0.009) {
                        throw new \RuntimeException('snapshot_delivery_mismatch');
                    }

                    $orderTotal = $subtotal + $deliveryFee;
                    $expectedTotal = (float) ($sellerData['total'] ?? 0);
                    if (abs($orderTotal - $expectedTotal) > 0.009) {
                        throw new \RuntimeException('snapshot_total_mismatch');
                    }

                    $order = Order::create([
                        'customer_id'      => $customer->id,
                        'seller_id'        => $sellerId,
                        'tracking_number'  => null,
                        'status'           => Order::STATUS_AWAITING_PAYMENT,
                        'total_amount'     => $orderTotal,
                        'shipping_address' => $this->shipping_address,
                        'customer_note'    => $this->customer_note !== '' ? trim($this->customer_note) : null,
                    ]);

                    foreach ($rows as $row) {
                        $product = $products[$row['product_id']] ?? null;
                        if (! $product || $product->stock < (int) $row['quantity']) {
                            throw new \RuntimeException('snapshot_stock_mismatch');
                        }

                        $linePrice = (float) ($product->sale_price ?? $product->price);

                        OrderItem::create([
                            'order_id'          => $order->id,
                            'product_id'        => (int) $product->id,
                            'quantity'          => (int) $row['quantity'],
                            'price_at_purchase' => $linePrice,
                        ]);

                        $oldStock = $product->stock;
                        $product->decrement('stock', (int) $row['quantity']);
                        $product->refresh();
                        $product->notifyWishlistLowStockIfNeeded($oldStock, $product->stock);
                    }

                    $createdOrders[] = $order->id;

                    $sellerUser = $order->seller?->user;
                    if ($sellerUser) {
                        $sellerUser->notify(new NewOrderForSeller($order));
                    }
                    
                    // Notify customer about each order placed
                    $customer->notify(new OrderPlacedNotification($order));
                }
            });
        } catch (\RuntimeException $e) {
            $this->snapshotMismatch = true;
            $this->createCheckoutSnapshot(true);
            $this->addError('shipping_address', 'Checkout snapshot no longer matches current product pricing/stock. Please review updated totals, then place order again.');

            return;
        }

        if (empty($createdOrders)) {
            $this->addError('shipping_address', 'Could not place order. Please check product availability.');
            return;
        }

        if ($snapshotRow?->id) {
            DB::table('checkout_snapshots')
                ->where('id', $snapshotRow->id)
                ->update([
                    'consumed_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        Session::forget('cart');
        $this->checkoutSnapshotToken = null;
        $this->checkoutSnapshotVersion = null;
        $this->placed = true;
        $this->createdOrders = $createdOrders;
        $this->dispatch('cart-updated', count: 0);
        $this->dispatch('order-placed', count: count($createdOrders));
    }
};
?>

@php
    $items = $this->items;
    $subtotal = 0;
    foreach ($items as $row) {
        $subtotal += $row['price'] * $row['quantity'];
    }
    $sellerIds = array_unique(array_column($items, 'seller_id'));
    $sellers = \App\Models\Seller::whereIn('id', $sellerIds)->get()->keyBy('id');
    $productIds = array_unique(array_column($items, 'product_id'));
    $productsForDelivery = $productIds ? \App\Models\Product::whereIn('id', $productIds)->get()->keyBy('id') : collect();
    $deliveryTotal = 0.0;
    $deliveryBySeller = [];
    foreach ($sellerIds as $sid) {
        $groupRows = array_values(array_filter($items, fn($r) => (int)$r['seller_id'] === (int)$sid));
        $sellerModel = $sellers->get($sid);
        if ($sellerModel && !empty($groupRows)) {
            $fee = $sellerModel->computeDeliveryFee($groupRows, $productsForDelivery);
            $deliveryBySeller[$sid] = ['seller' => $sellerModel, 'fee' => $fee];
            $deliveryTotal += $fee;
        }
    }
    $grandTotal = $subtotal + $deliveryTotal;
    $sellerCount = count($sellerIds);
    $itemsBySeller = [];
    foreach ($sellerIds as $sid) {
        $itemsBySeller[$sid] = array_values(array_filter($items, fn($r) => (int)$r['seller_id'] === (int)$sid));
    }
    /** @var \App\Models\User|null $checkoutUser */
    $checkoutUser = \Illuminate\Support\Facades\Auth::guard('web')->user();
    $savedAddresses = $checkoutUser
        ? $checkoutUser->addresses()->orderByDesc('is_default')->orderBy('created_at')->get()
        : collect();
@endphp

<div>

{{-- ── Page header ── --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold" style="color:#212121;">Checkout</h1>
        <p class="text-sm mt-0.5" style="color:#9E9E9E;">Cash on Delivery · Review and confirm your order</p>
    </div>
    <a href="{{ route('customer.cart') }}"
       class="text-xs font-medium px-3 py-1.5 rounded-lg transition"
       style="color:#2D9F4E; background:#E8F5E9; border:1px solid #C8E6C9;"
       onmouseover="this.style.background='#C8E6C9';" onmouseout="this.style.background='#E8F5E9';">
        ← Back to Cart
    </a>
</div>

{{-- ── Order placed: success screen (hides entire form) ── --}}
@if($this->placed)
    <div class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 rounded-full flex items-center justify-center mb-6" style="background:#E8F5E9;">
            <svg class="w-10 h-10" style="color:#2D9F4E;" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
        </div>
        <h2 class="text-2xl font-bold mb-2" style="color:#212121;">
            @if(count($this->createdOrders) > 1) {{ count($this->createdOrders) }} Orders Placed! @else Order Placed! @endif
        </h2>
        <p class="text-sm mb-8" style="color:#9E9E9E;">
            @if(count($this->createdOrders) > 1)
                {{ count($this->createdOrders) }} separate orders have been created — one per seller.
            @else
                Your order has been placed successfully. The seller will process it shortly.
            @endif
        </p>
        <a href="{{ route('customer.orders') }}"
           class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-sm text-white"
           style="background:#2D9F4E;"
           onmouseover="this.style.background='#1B7A37';" onmouseout="this.style.background='#2D9F4E';">
            View My Orders
        </a>
    </div>

@else

{{-- ── Alerts ── --}}
@if($snapshotMismatch)
    <div class="mb-4 flex items-start gap-3 px-4 py-3 rounded-xl text-sm" style="background:#FFEBEE; border:1px solid #FFCDD2; color:#EF5350;">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        Pricing changed while reviewing. <button type="button" wire:click="refreshCheckoutSnapshotAction" class="ml-1 font-bold underline">Refresh & review totals</button> before placing.
    </div>
@endif
@if(!empty($items) && $sellerCount > 1)
    <div class="mb-4 flex items-start gap-3 px-4 py-3 rounded-xl text-sm" style="background:#FFF3E0; border:1px solid #FFE0B2; color:#F57C00;">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        Items from <strong class="mx-1">{{ $sellerCount }} sellers</strong> — this creates {{ $sellerCount }} separate orders with individual deliveries.
    </div>
@endif

{{-- ── TOP ROW: Order Items (left) | Order Summary/Place Order (right) ── --}}
<div class="flex flex-col lg:flex-row gap-6 lg:items-stretch mb-6">

    {{-- Order Items --}}
    <div class="flex-1 min-w-0 flex flex-col">
        <div class="bg-white rounded-2xl p-5 flex-1" style="border:1px solid #F5F5F5; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#FFF3E0;">
                    <svg class="w-4 h-4" style="color:#FF6F00;" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"/>
                        <path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h4 class="font-bold text-sm" style="color:#212121;">Order Items</h4>
            </div>

            @if(empty($items))
                <div class="py-8 text-center text-sm" style="color:#9E9E9E;">
                    Your cart is empty. <a href="{{ route('customer.cart') }}" class="font-medium underline" style="color:#2D9F4E;">Add items</a> before checking out.
                </div>
            @else
                <div class="space-y-3">
                    @foreach($itemsBySeller as $sid => $sellerItems)
                        @php $sellerObj = $sellers->get($sid); @endphp
                        <div class="rounded-xl overflow-hidden" style="border:1px solid #F5F5F5;">
                            <div class="flex items-center gap-2 px-3 py-2.5" style="background:#F8F9FA; border-bottom:1px solid #F5F5F5;">
                                <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0" style="background:#FF6F00;">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"/>
                                        <path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-semibold" style="color:#212121;">{{ $sellerObj ? $sellerObj->store_name : 'Seller' }}</span>
                                <span class="text-xs ml-auto" style="color:#9E9E9E;">{{ count($sellerItems) }} {{ count($sellerItems) === 1 ? 'item' : 'items' }}</span>
                            </div>
                            <div class="divide-y" style="border-color:#F5F5F5;">
                                @foreach($sellerItems as $row)
                                    <div class="flex items-center gap-3 px-3 py-3">
                                        @if(!empty($row['image_path']))
                                            @php
                                                $checkoutImg = trim((string)($row['image_path'] ?? ''));
                                                $checkoutUrl = str_starts_with($checkoutImg, 'data:') ? $checkoutImg : asset('storage/'.$checkoutImg);
                                            @endphp
                                            <img src="{{ $checkoutUrl }}" alt="{{ $row['name'] }}"
                                                 class="w-12 h-12 rounded-xl object-cover flex-shrink-0" style="border:1px solid #F5F5F5;">
                                        @else
                                            <div class="w-12 h-12 rounded-xl flex-shrink-0 flex items-center justify-center" style="background:#F8F9FA; border:1px solid #F5F5F5;">
                                                <svg class="w-6 h-6" style="color:#E0E0E0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                        @endif
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium line-clamp-1" style="color:#212121;">{{ $row['name'] }}</p>
                                            <p class="text-xs mt-0.5" style="color:#9E9E9E;">Qty: {{ $row['quantity'] }} · ₱{{ number_format($row['price'], 2) }} each</p>
                                        </div>
                                        <p class="text-sm font-bold flex-shrink-0" style="color:#212121;">₱{{ number_format($row['price'] * $row['quantity'], 2) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Order Summary / Place Order --}}
    <div class="w-full lg:w-80 flex-shrink-0 flex flex-col">
        <div class="bg-white rounded-2xl p-5 flex-1" style="border:1px solid #F5F5F5; box-shadow:0 2px 8px rgba(0,0,0,0.04); border-top:3px solid #F9C74F;">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#FFFDE7;">
                    <svg class="w-4 h-4" style="color:#F9C74F;" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                        <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h3 class="font-bold text-base" style="color:#212121;">Order Summary</h3>
            </div>

            <div class="flex items-center gap-2 px-3 py-2 rounded-xl mb-4" style="background:#F8F9FA; border:1px solid #E0E0E0;">
                <svg class="w-4 h-4" style="color:#2D9F4E;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span class="text-xs font-semibold" style="color:#212121;">Cash on Delivery (COD)</span>
            </div>

            <div class="space-y-3 text-sm">
                <div class="flex justify-between" style="color:#616161;">
                    <span>Subtotal</span>
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
                @if($deliveryTotal > 0 && count($deliveryBySeller) > 1)
                    @foreach($deliveryBySeller as $sid => $data)
                        @if($data['fee'] > 0)
                            <div class="flex justify-between pl-3 text-xs" style="color:#9E9E9E;">
                                <span>{{ $data['seller']->store_name }}</span>
                                <span>₱{{ number_format($data['fee'], 2) }}</span>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>

            <div class="my-4" style="border-top:1px solid #F5F5F5;"></div>

            <div class="flex justify-between items-center mb-5">
                <span class="font-bold text-base" style="color:#212121;">Total</span>
                <span class="font-bold text-xl" style="color:#F57C00;">₱{{ number_format($grandTotal, 2) }}</span>
            </div>

            @if($sellerCount > 1)
                <p class="text-xs mb-3" style="color:#9E9E9E;">{{ $sellerCount }} separate orders will be placed (one per seller).</p>
            @endif

            <button type="button" wire:click="placeOrder" wire:loading.attr="disabled"
                    class="flex items-center justify-center gap-2 w-full py-3 rounded-xl font-bold text-sm text-white transition"
                    style="background:#2D9F4E;"
                    onmouseover="this.style.background='#1B7A37';" onmouseout="this.style.background='#2D9F4E';">
                <svg class="w-4 h-4" wire:loading.remove wire:target="placeOrder" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <svg class="w-4 h-4 animate-spin" wire:loading wire:target="placeOrder" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span wire:loading.remove wire:target="placeOrder">Place Order (COD)</span>
                <span wire:loading wire:target="placeOrder">Placing order…</span>
            </button>
        </div>
    </div>

</div>

{{-- ── BOTTOM ROW: Shipping Address (left) | Order Note (right) ── --}}
<div class="flex flex-col lg:flex-row gap-6 items-start">

    {{-- Shipping address --}}
    <div class="flex-1 min-w-0 bg-white rounded-2xl p-5" style="border:1px solid #F5F5F5; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#E8F5E9;">
                <svg class="w-4 h-4" style="color:#2D9F4E;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h4 class="font-bold text-sm" style="color:#212121;">Shipping Address</h4>
        </div>

        @if($savedAddresses->isNotEmpty())
            <p class="text-xs mb-3" style="color:#9E9E9E;">Select a saved address or type one below.</p>
            <div class="grid gap-2 mb-4">
                @foreach($savedAddresses as $addr)
                    @php
                        $full = trim($addr->recipient_name ?: $checkoutUser->name)."\n"
                              . $addr->line1.($addr->line2 ? ', '.$addr->line2 : '')."\n"
                              . trim($addr->city.' '.$addr->region.' '.$addr->postal_code)."\n"
                              . ($addr->phone ? 'Phone: '.$addr->phone : '');
                    @endphp
                    <label class="flex items-start gap-3 cursor-pointer rounded-xl px-3 py-2.5 transition"
                           style="border:1px solid #E0E0E0;"
                           onmouseover="this.style.borderColor='#2D9F4E';" onmouseout="this.style.borderColor='#E0E0E0';">
                        <input type="radio"
                               name="selected_address"
                               value="{{ $full }}"
                               class="mt-1 h-3.5 w-3.5 flex-shrink-0"
                               style="accent-color:#2D9F4E;"
                               onclick="(function(v){ document.getElementById('checkout_addr_input').value = v; @this.set('shipping_address', v); })({{ Illuminate\Support\Js::from($full) }})">
                        <span class="text-xs">
                            <span class="font-semibold" style="color:#212121;">
                                {{ $addr->label }}
                                @if($addr->is_default)
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold" style="background:#E8F5E9; color:#2D9F4E;">Default</span>
                                @endif
                            </span>
                            <span class="block mt-0.5 whitespace-pre-line" style="color:#616161;">{{ $full }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        <textarea wire:model.defer="shipping_address" id="checkout_addr_input" rows="3"
                  class="block w-full rounded-xl text-sm px-3 py-2.5 transition"
                  style="border:1px solid #E0E0E0; color:#212121; outline:none; resize:none;"
                  onfocus="this.style.borderColor='#F9C74F';" onblur="this.style.borderColor='#E0E0E0';"
                  placeholder="Full address — barangay, city, province, contact number"></textarea>
        @error('shipping_address') <p class="mt-1.5 text-xs font-medium" style="color:#EF5350;">{{ $message }}</p> @enderror
    </div>

    {{-- Order note --}}
    <div class="w-full lg:w-80 flex-shrink-0 bg-white rounded-2xl p-5" style="border:1px solid #F5F5F5; box-shadow:0 2px 8px rgba(0,0,0,0.04); border-top:3px solid #2D9F4E;">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#FFF3E0;">
                <svg class="w-4 h-4" style="color:#F57C00;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </div>
            <h4 class="font-bold text-sm" style="color:#212121;">Order Note <span class="text-xs font-normal" style="color:#9E9E9E;">(optional)</span></h4>
        </div>
        <textarea wire:model.blur="customer_note" rows="4"
                  class="block w-full rounded-xl text-sm px-3 py-2.5 transition"
                  style="border:1px solid #E0E0E0; color:#212121; outline:none; resize:none;"
                  onfocus="this.style.borderColor='#F9C74F';" onblur="this.style.borderColor='#E0E0E0';"
                  placeholder="e.g. Please wrap fragile items, leave at the door…"></textarea>
        <p class="mt-1.5 text-xs" style="color:#9E9E9E;">Visible to the seller when processing your order.</p>
        @error('customer_note') <p class="mt-1 text-xs font-medium" style="color:#EF5350;">{{ $message }}</p> @enderror
    </div>

</div>

@endif
</div>

@script
<script>
    $wire.on('order-placed', (event) => {
        const count = event.count || 1;
        Swal.fire({
            icon: 'success',
            title: count > 1 ? `${count} Orders Placed!` : 'Order Placed!',
            text: count > 1
                ? `${count} separate orders have been created — one per seller.`
                : 'Your order has been placed successfully. The seller will process it shortly.',
            confirmButtonText: 'View My Orders',
            confirmButtonColor: '#2D9F4E',
            showCancelButton: true,
            cancelButtonText: 'Stay here',
            cancelButtonColor: '#9E9E9E',
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '{{ route('customer.orders') }}';
            }
        });
    });
</script>
@endscript

