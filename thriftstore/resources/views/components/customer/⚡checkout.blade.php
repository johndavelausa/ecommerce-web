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
                        'status'           => Order::STATUS_PAID,
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
        $this->dispatch('cart-updated');
    }
};
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900">Checkout (Cash on Delivery)</h3>
        <p class="mt-1 text-sm text-gray-500">
            Confirm your shipping address and review your items. Payment is COD only.
        </p>
        @if($snapshotMismatch)
            <div class="mt-3 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800">
                Checkout pricing changed while you were reviewing. Please refresh snapshot and review totals before placing your order.
                <button type="button" wire:click="refreshCheckoutSnapshotAction" class="ml-2 underline font-medium">Refresh snapshot</button>
            </div>
        @endif
        @if(!empty($items) && $sellerCount > 1)
            <div class="mt-3 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                <strong>Multi-seller cart:</strong> Your cart contains items from <strong>{{ $sellerCount }} sellers</strong>. This will create <strong>{{ $sellerCount }} separate orders</strong> with {{ $sellerCount }} separate deliveries. Each seller will ship their items independently.
            </div>
        @endif
    </div>

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
    @endphp

    @if($this->placed)
        <div class="bg-white rounded-lg shadow p-6 space-y-3">
            <div class="rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">
                @if(count($this->createdOrders) > 1)
                    {{ count($this->createdOrders) }} separate orders have been placed (one per seller). You can track them in <a href="{{ route('customer.orders') }}" class="font-medium underline">My orders</a>.
                @else
                    Your order has been placed. You can track it in <a href="{{ route('customer.orders') }}" class="font-medium underline">My orders</a>.
                @endif
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
            <div>
                <label class="block text-sm font-medium text-gray-700">Order note <span class="text-gray-400">(optional)</span></label>
                <textarea wire:model.defer="customer_note" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="e.g. Please wrap fragile, Leave at the door"></textarea>
                <p class="mt-0.5 text-xs text-gray-500">Visible to the seller when processing your order.</p>
                @error('customer_note') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <h4 class="text-sm font-semibold text-gray-900 mt-4">Items by seller</h4>
            @if(empty($items))
                <div class="py-8 text-center text-gray-500 text-sm">
                    Your cart is empty. Add items before checking out.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($itemsBySeller as $sid => $sellerItems)
                        @php $sellerObj = $sellers->get($sid); @endphp
                        <div class="rounded-lg border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide">
                                Order from {{ $sellerObj ? $sellerObj->store_name : 'Seller' }} — {{ count($sellerItems) }} {{ count($sellerItems) === 1 ? 'item' : 'items' }}
                            </div>
                            <div class="divide-y divide-gray-100">
                                @foreach($sellerItems as $row)
                                    <div class="py-3 px-3 flex items-center justify-between gap-3">
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
                                                    Qty: {{ $row['quantity'] }} · ₱{{ number_format($row['price'], 2) }} each
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900">
                                            ₱{{ number_format($row['price'] * $row['quantity'], 2) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow p-4 sm:p-6 space-y-4">
            <h4 class="text-sm font-semibold text-gray-900">Summary</h4>
            @if(!empty($items) && $sellerCount > 1)
                <p class="text-xs text-gray-600">You will place <strong>{{ $sellerCount }} separate orders</strong> (one per seller).</p>
            @endif
            <div class="space-y-1 text-sm text-gray-700">
                <div class="flex justify-between">
                    <span>Subtotal</span>
                    <span>₱{{ number_format($subtotal, 2) }}</span>
                </div>
                @if($deliveryTotal > 0)
                    <div class="flex justify-between">
                        <span>Delivery</span>
                        <span>₱{{ number_format($deliveryTotal, 2) }}</span>
                    </div>
                    @foreach($deliveryBySeller as $sid => $data)
                        @if($data['fee'] > 0)
                            <div class="flex justify-between text-xs text-gray-500 pl-2">
                                <span>{{ $data['seller']->store_name }}</span>
                                <span>₱{{ number_format($data['fee'], 2) }}</span>
                            </div>
                        @endif
                    @endforeach
                @else
                    <div class="flex justify-between text-gray-500 text-xs">
                        <span>Delivery</span>
                        <span>Free</span>
                    </div>
                @endif
            </div>
            <div class="mt-3 text-xs text-gray-600 border-t pt-3">
                Payment method: <span class="font-semibold text-gray-800">Cash on Delivery (COD)</span>
            </div>
            <div class="border-t pt-3 flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Total
                </div>
                <div class="text-xl font-semibold text-gray-900">
                    ₱{{ number_format($grandTotal, 2) }}
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

