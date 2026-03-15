<?php

use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\Product;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\OrderDisputeUpdated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $status = ''; // '', awaiting_payment, paid, to_pack, ready_to_ship, processing, shipped, out_for_delivery, delivered, completed, cancelled

    protected $queryString = [
        'status' => ['except' => ''],
    ];

    public ?int $issueOrderId = null;
    public string $issueReason = 'item_not_as_described';
    public string $issueBody = '';
    public $issueEvidence = null;

    // Store rating modal state
    public ?int $rateOrderId = null;
    public int $storeRating = 5;
    public string $storeReview = '';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function openIssueModal(int $orderId): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return;
        }

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_COMPLETED])
            ->findOrFail($orderId);

        $this->issueOrderId = $order->id;
        $this->issueReason = 'item_not_as_described';
        $this->issueBody = '';
        $this->issueEvidence = null;
        $this->resetErrorBag();
    }

    public function closeIssueModal(): void
    {
        $this->issueOrderId = null;
        $this->issueReason = 'item_not_as_described';
        $this->issueBody = '';
        $this->issueEvidence = null;
        $this->resetErrorBag();
    }

    public function submitIssue(): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer || ! $this->issueOrderId) {
            return;
        }

        $this->validate([
            'issueReason' => ['required', 'string', 'in:item_not_as_described,damaged_item,wrong_item,missing_items,other'],
            'issueBody' => ['required', 'string', 'max:2000'],
            'issueEvidence' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        $order = Order::query()
            ->with('seller')
            ->where('customer_id', $customer->id)
            ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_COMPLETED])
            ->findOrFail($this->issueOrderId);

        $hasActiveDispute = $order->disputes()
            ->whereIn('status', OrderDispute::ACTIVE_STATUSES)
            ->exists();
        if ($hasActiveDispute) {
            $this->addError('issueBody', 'An active dispute already exists for this order.');
            return;
        }

        if (! $order->seller) {
            return;
        }

        $conv = Conversation::query()
            ->where('type', 'seller-customer')
            ->where('customer_id', $customer->id)
            ->where('seller_id', $order->seller_id)
            ->first();

        if (! $conv) {
            $conv = Conversation::create([
                'seller_id'   => $order->seller_id,
                'customer_id' => $customer->id,
                'type'        => 'seller-customer',
            ]);
        }

        $evidencePath = null;
        if ($this->issueEvidence) {
            $evidencePath = $this->issueEvidence->store('disputes', 'public');
        }

        $dispute = OrderDispute::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'seller_id' => $order->seller_id,
            'reason_code' => $this->issueReason,
            'description' => trim($this->issueBody),
            'evidence_path' => $evidencePath,
            'status' => OrderDispute::STATUS_OPEN,
        ]);

        $sellerUser = $order->seller->user;
        if ($sellerUser) {
            $sellerUser->notify(new OrderDisputeUpdated($dispute, 'opened'));
        }

        User::query()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })
            ->get()
            ->each(function (User $admin) use ($dispute) {
                $admin->notify(new OrderDisputeUpdated($dispute, 'opened'));
            });

        $body = "Return / issue request for Order #{$order->id} (Tracking: ".($order->tracking_number ?? 'N/A')."):\n\n"
              . "Reason: " . (OrderDispute::REASON_CODES[$this->issueReason] ?? 'Issue')
              . "\n"
              . trim($this->issueBody)
              . ($evidencePath ? "\n\nEvidence: " . asset('storage/' . $evidencePath) : '')
              . "\n\nDispute Ref: #{$dispute->id}";

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id'       => $customer->id,
            'sender_type'     => 'customer',
            'body'            => $body,
            'is_read'         => false,
        ]);

        $conv->update(['updated_at' => now()]);

        $this->closeIssueModal();
    }

    public function openRateModal(int $orderId): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return;
        }

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'delivered')
            ->whereNull('store_rating')
            ->findOrFail($orderId);

        $this->rateOrderId = $order->id;
        $this->storeRating = 5;
        $this->storeReview = '';
        $this->resetErrorBag();
    }

    public function closeRateModal(): void
    {
        $this->rateOrderId = null;
        $this->storeRating = 5;
        $this->storeReview = '';
        $this->resetErrorBag();
    }

    public function submitRating(): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer || ! $this->rateOrderId) {
            return;
        }

        $this->validate([
            'storeRating' => ['required', 'integer', 'min:1', 'max:5'],
            'storeReview' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'delivered')
            ->whereNull('store_rating')
            ->findOrFail($this->rateOrderId);

        $order->store_rating = $this->storeRating;
        $order->store_review = $this->storeReview !== '' ? $this->storeReview : null;
        $order->save();

        $this->closeRateModal();
    }

    public function reorder(int $id): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) abort(403);

        $order = Order::query()
            ->with('items.product.seller')
            ->where('customer_id', $customer->id)
            ->where('status', 'delivered')
            ->findOrFail($id);

        $cart = Session::get('cart', []);
        $maxCartItems = 50;

        foreach ($order->items as $item) {
            if (count($cart) >= $maxCartItems) {
                break;
            }

            $product = $item->product;
            if (! $product || ! $product->is_active || $product->stock <= 0) {
                continue;
            }

            $seller = $product->seller;
            if (! $seller || $seller->status !== 'approved' || ! $seller->is_open) {
                continue;
            }

            $key = (string) $product->id;
            $currentQty = $cart[$key]['quantity'] ?? 0;
            $desiredQty = $currentQty + $item->quantity;
            $finalQty = min($desiredQty, $product->stock);
            if ($finalQty <= 0) {
                continue;
            }

            if (! isset($cart[$key]) && count($cart) >= $maxCartItems) {
                break;
            }

            $cart[$key] = [
                'product_id' => $product->id,
                'seller_id'  => $product->seller_id,
                'name'       => $product->name,
                'price'      => (float) ($product->sale_price ?? $product->price),
                'image_path' => $product->image_path,
                'quantity'   => $finalQty,
            ];
        }

        Session::put('cart', $cart);
        $count = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cart));
        $this->dispatch('cart-updated', count: $count);
    }

    public function getOrdersProperty()
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return collect();
        }

        $q = Order::query()
            ->with([
                'items.product',
                'seller',
                'trackingEvents',
                'disputes' => fn ($dq) => $dq->latest(),
            ])
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        if ($this->status !== '') {
            $q->where('status', $this->status);
        }

        return $q->paginate(10);
    }

    public function markReceived(int $id): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) abort(403);

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        if (! $order->canTransitionTo(\App\Models\Order::STATUS_DELIVERED, 'customer')) {
            return;
        }

        $order->status = \App\Models\Order::STATUS_DELIVERED;
        $order->delivered_at = now();
        $order->save();
    }

    public function markNotReceived(int $id): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            abort(403);
        }

        $order = Order::query()
            ->with('seller.user')
            ->where('customer_id', $customer->id)
            ->where('status', Order::STATUS_OUT_FOR_DELIVERY)
            ->findOrFail($id);

        $hasActiveDispute = $order->disputes()
            ->whereIn('status', OrderDispute::ACTIVE_STATUSES)
            ->exists();
        if ($hasActiveDispute || ! $order->seller) {
            return;
        }

        $conv = Conversation::query()
            ->where('type', 'seller-customer')
            ->where('customer_id', $customer->id)
            ->where('seller_id', $order->seller_id)
            ->first();

        if (! $conv) {
            $conv = Conversation::create([
                'seller_id'   => $order->seller_id,
                'customer_id' => $customer->id,
                'type'        => 'seller-customer',
            ]);
        }

        $dispute = OrderDispute::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'seller_id' => $order->seller_id,
            'reason_code' => 'parcel_not_received',
            'description' => 'Customer reported that the parcel was not received while the order is marked out for delivery.',
            'status' => OrderDispute::STATUS_OPEN,
        ]);

        $sellerUser = $order->seller->user;
        if ($sellerUser) {
            $sellerUser->notify(new OrderDisputeUpdated($dispute, 'opened'));
        }

        User::query()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })
            ->get()
            ->each(function (User $admin) use ($dispute) {
                $admin->notify(new OrderDisputeUpdated($dispute, 'opened'));
            });

        $body = "Delivery issue reported for Order #{$order->id} (Tracking: ".($order->tracking_number ?? 'N/A')."):\n\n"
              . "Reason: Parcel not received\n"
              . "Customer indicates they have not received the parcel yet and needs delivery investigation.\n\n"
              . "Dispute Ref: #{$dispute->id}";

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id'       => $customer->id,
            'sender_type'     => 'customer',
            'body'            => $body,
            'is_read'         => false,
        ]);

        $conv->update(['updated_at' => now()]);

        // Basic risk flag for repeated parcel-not-received disputes.
        $recentNotReceivedCount = OrderDispute::query()
            ->where('customer_id', $customer->id)
            ->where('reason_code', 'parcel_not_received')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentNotReceivedCount >= 3) {
            $customer->update([
                'is_suspicious' => true,
                'suspicious_reason' => 'Repeated parcel-not-received claims in 30 days',
                'suspicious_flagged_at' => now(),
            ]);
        }
    }

    public function disputeProgressMeta(?OrderDispute $dispute): array
    {
        if (! $dispute) {
            return [
                'label' => 'No dispute',
                'step' => 0,
                'isResolved' => false,
            ];
        }

        $map = [
            OrderDispute::STATUS_OPEN => ['Dispute submitted', 1, false],
            OrderDispute::STATUS_SELLER_REVIEW => ['Seller reviewing', 2, false],
            OrderDispute::STATUS_UNDER_ADMIN_REVIEW => ['Admin reviewing', 3, false],
            OrderDispute::STATUS_RETURN_REQUESTED => ['Return requested', 3, false],
            OrderDispute::STATUS_RETURN_IN_TRANSIT => ['Return in transit', 3, false],
            OrderDispute::STATUS_RETURN_RECEIVED => ['Return received', 3, false],
            OrderDispute::STATUS_REFUND_PENDING => ['Refund pending', 4, false],
            OrderDispute::STATUS_REFUND_COMPLETED => ['Refund completed', 4, true],
            OrderDispute::STATUS_RESOLVED_APPROVED => ['Resolved (approved)', 4, true],
            OrderDispute::STATUS_RESOLVED_REJECTED => ['Resolved (rejected)', 4, true],
            OrderDispute::STATUS_CLOSED => ['Resolved (closed)', 4, true],
        ];

        [$label, $step, $isResolved] = $map[$dispute->status] ?? ['Dispute updated', 2, false];

        return [
            'label' => $label,
            'step' => $step,
            'isResolved' => $isResolved,
        ];
    }

    public function cancel(int $id): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) abort(403);

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        if (! $order->canTransitionTo(\App\Models\Order::STATUS_CANCELLED, 'customer')) {
            return;
        }

        // Enforce 30-minute cancellation window
        if ($order->created_at && now()->diffInMinutes($order->created_at) > 30) {
            return;
        }

        $fromStatus = (string) $order->status;
        $order->status = \App\Models\Order::STATUS_CANCELLED;
        $order->cancelled_at = now();
        $order->cancelled_by_type = 'customer';
        $order->cancellation_reason_code = 'buyer_changed_mind';
        $order->cancellation_reason_note = null;
        $order->applyCancellationRefundDecision($fromStatus);
        $order->save();
    }
};
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-lg font-medium text-gray-900">My Orders</h3>
            <p class="text-sm text-gray-500 mt-1">
                Track your orders and confirm when you have received them.
            </p>
        </div>
        <div>
            <select wire:model.live="status"
                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All statuses</option>
                <option value="awaiting_payment">Awaiting payment</option>
                <option value="paid">Paid</option>
                <option value="to_pack">To pack</option>
                <option value="ready_to_ship">Ready to ship</option>
                <option value="processing">Processing</option>
                <option value="shipped">Shipped</option>
                <option value="out_for_delivery">Out for delivery</option>
                <option value="delivered">Delivered</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php $orders = $this->orders; ?>
        <?php if($orders instanceof \Illuminate\Pagination\LengthAwarePaginator && $orders->count()): ?>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store rating</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php foreach($orders as $order): ?>
                        <tr>
                            <td class="px-4 py-3 text-xs text-gray-500 align-top">
                                {{ optional($order->created_at)->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 align-top">
                                {{ $order->seller->store_name ?? 'Seller #'.$order->seller_id }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700 align-top">
                                <ul class="space-y-1">
                                    <?php foreach($order->items as $item): ?>
                                        <li>
                                            {{ $item->quantity }} × {{ $item->product->name ?? 'Product #'.$item->product_id }}
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <?php
                                    $badge = match($order->status) {
                                        'awaiting_payment' => 'bg-orange-100 text-orange-800',
                                        'paid' => 'bg-cyan-100 text-cyan-800',
                                        'to_pack' => 'bg-sky-100 text-sky-800',
                                        'ready_to_ship' => 'bg-violet-100 text-violet-800',
                                        'processing' => 'bg-amber-100 text-amber-800',
                                        'shipped' => 'bg-blue-100 text-blue-800',
                                        'out_for_delivery' => 'bg-indigo-100 text-indigo-800',
                                        'delivered' => 'bg-green-100 text-green-800',
                                        'completed' => 'bg-emerald-100 text-emerald-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                    {{ ucwords(str_replace('_', ' ', $order->status)) }}
                                </span>
                                @if($order->status === 'cancelled')
                                    <div class="mt-1 text-xs text-gray-600">
                                        Cancelled by: {{ ucfirst((string) ($order->cancelled_by_type ?? 'system')) }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-600">
                                        Reason: {{ \App\Models\Order::CANCELLATION_REASONS[$order->cancellation_reason_code] ?? ucfirst(str_replace('_', ' ', (string) ($order->cancellation_reason_code ?? 'unspecified'))) }}
                                    </div>
                                @endif
                                @if($order->refund_status)
                                    <div class="mt-1 text-xs text-gray-600">
                                        Refund: {{ \App\Models\Order::refundStatusLabel($order->refund_status) }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-600">
                                        Refund reason: {{ \App\Models\Order::refundReasonLabel($order->refund_reason_code) }}
                                    </div>
                                    @if($order->refunded_at)
                                        <div class="mt-0.5 text-xs text-gray-600">
                                            Refunded at: {{ $order->refunded_at->format('M j, Y g:i A') }}
                                        </div>
                                    @endif
                                @endif
                                @if(in_array($order->status, ['shipped', 'out_for_delivery', 'delivered', 'completed']) && $order->tracking_number)
                                    <div class="mt-1 text-xs text-gray-600">
                                        Courier: {{ \App\Models\Order::COURIERS[$order->courier_name] ?? strtoupper((string) $order->courier_name ?: 'OTHER') }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-600">Tracking: {{ $order->tracking_number }}</div>
                                    @if($order->trackingEvents->isNotEmpty())
                                        <div class="mt-1 text-xs text-gray-500">Latest tracking events:</div>
                                        <ul class="mt-1 space-y-0.5">
                                            @foreach($order->trackingEvents->take(3) as $event)
                                                <li class="text-[11px] text-gray-600">
                                                    {{ optional($event->occurred_at ?? $event->created_at)->format('M j, g:i A') }} · {{ ucwords(str_replace('_', ' ', (string) $event->event_status)) }}
                                                    @if($event->location)
                                                        · {{ $event->location }}
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @endif
                                @if($order->status === 'out_for_delivery')
                                    <div class="mt-1 text-xs text-gray-600">
                                        If the parcel arrives, click "Mark received". If it does not arrive, click "Did not receive parcel" to open a delivery dispute for seller/admin review.
                                    </div>
                                @endif
                                @if($order->shipped_at)
                                    <div class="mt-0.5 text-xs text-gray-600">Shipped at: {{ $order->shipped_at->format('M j, Y g:i A') }}</div>
                                @endif
                                @if(in_array($order->status, ['shipped', 'out_for_delivery', 'delivered', 'completed']) && $order->estimated_delivery_date)
                                    <div class="mt-0.5 text-xs text-gray-600">Est. delivery: {{ $order->estimated_delivery_date->format('M j, Y') }}</div>
                                @endif
                                @if($order->delivered_at)
                                    <div class="mt-0.5 text-xs text-gray-600">Delivered at: {{ $order->delivered_at->format('M j, Y g:i A') }}</div>
                                @endif
                                @if($order->completed_at)
                                    <div class="mt-0.5 text-xs text-gray-600">Completed at: {{ $order->completed_at->format('M j, Y g:i A') }}</div>
                                @endif
                                @php
                                    $latestDispute = $order->disputes->first();
                                @endphp
                                @if($latestDispute)
                                    @php($progress = $this->disputeProgressMeta($latestDispute))
                                    <div class="mt-2 rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5">
                                        <div class="text-[11px] font-medium text-gray-700">
                                            Delivery dispute: {{ $progress['label'] }}
                                        </div>
                                        <div class="mt-1 flex items-center gap-1">
                                            @for($i = 1; $i <= 4; $i++)
                                                <span class="h-1.5 flex-1 rounded {{ $i <= $progress['step'] ? 'bg-indigo-500' : 'bg-gray-200' }}"></span>
                                            @endfor
                                        </div>
                                        <div class="mt-1 text-[10px] text-gray-500">
                                            Submitted → Seller review → Admin review → Resolution
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top text-xs text-gray-700">
                                <?php if($order->store_rating): ?>
                                    <div class="flex items-center gap-1">
                                        <span class="text-yellow-400">
                                            {{ str_repeat('★', $order->store_rating) }}{{ str_repeat('☆', 5 - $order->store_rating) }}
                                        </span>
                                        <span>{{ $order->store_rating }}/5</span>
                                    </div>
                                    <?php if($order->store_review): ?>
                                        <div class="mt-1 text-[11px] text-gray-500 line-clamp-2">
                                            {{ $order->store_review }}
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Not rated</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 font-medium align-top">
                                ₱{{ number_format($order->total_amount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-xs align-top space-y-1">
                                <?php if(in_array($order->status, ['awaiting_payment', 'paid', 'to_pack', 'ready_to_ship', 'processing'], true)): ?>
                                    <button type="button"
                                            wire:click="cancel({{ $order->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-50">
                                        Cancel
                                    </button>
                                <?php elseif($order->status === 'out_for_delivery'): ?>
                                    <button type="button"
                                            wire:click="markReceived({{ $order->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-emerald-500 text-emerald-700 rounded-md text-xs hover:bg-emerald-50">
                                        Mark received
                                    </button>
                                    <button type="button"
                                            wire:click="markNotReceived({{ $order->id }})"
                                            wire:confirm="Report this order as not received? This will open a delivery dispute with the seller and notify admin."
                                            class="inline-flex items-center px-2 py-1 border border-red-500 text-red-700 rounded-md text-xs hover:bg-red-50">
                                        Did not receive parcel
                                    </button>
                                <?php elseif($order->status === 'shipped'): ?>
                                    <button type="button"
                                            wire:click="markReceived({{ $order->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-indigo-500 text-indigo-600 rounded-md text-xs hover:bg-indigo-50">
                                        Mark received
                                    </button>
                                <?php elseif(in_array($order->status, ['delivered', 'completed'], true)): ?>
                                    <a href="{{ route('customer.orders.receipt', $order) }}"
                                       class="inline-flex items-center px-2 py-1 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-50"
                                       target="_blank" rel="noopener">
                                        Download receipt
                                    </a>
                                    <button type="button"
                                            wire:click="openIssueModal({{ $order->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-50">
                                        Return / issue
                                    </button>
                                    <button type="button"
                                            wire:click="reorder({{ $order->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-indigo-500 text-indigo-600 rounded-md text-xs hover:bg-indigo-50">
                                        Re-order
                                    </button>
                                    <?php if(!$order->store_rating): ?>
                                        <button type="button"
                                                wire:click="openRateModal({{ $order->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-amber-500 text-amber-700 rounded-md text-xs hover:bg-amber-50">
                                            Rate seller
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="px-4 py-3 border-t">
                {{ $orders->links() }}
            </div>
        <?php else: ?>
            <div class="py-12 text-center text-gray-500 text-sm">
                You have no orders yet.
            </div>
        <?php endif; ?>
    </div>
    @if($issueOrderId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-900">Return / Issue request</h3>
                <p class="text-xs text-gray-500">
                    This message will be sent to the seller of your order. Describe the problem (wrong item, damaged, missing pieces, etc.).
                </p>
                <div>
                    <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">Reason</label>
                    <select wire:model.defer="issueReason"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Models\OrderDispute::REASON_CODES as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('issueReason') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <textarea wire:model.defer="issueBody" rows="4"
                          class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Example: The shirt has a tear on the sleeve."></textarea>
                @error('issueBody') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                <div>
                    <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">Evidence (optional)</label>
                    <input type="file" wire:model="issueEvidence" class="mt-1 block w-full text-xs text-gray-600" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    @error('issueEvidence') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeIssueModal"
                            class="px-3 py-1.5 border rounded-md text-xs text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitIssue"
                            class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs hover:bg-indigo-700">
                        Send request
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($rateOrderId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-900">Rate this seller / store</h3>
                <p class="text-xs text-gray-500">
                    This rating is for the overall store experience (communication, speed, packaging), not a specific product.
                </p>
                <div>
                    <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">Rating</label>
                    <div class="mt-1 flex items-center gap-1">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button"
                                    wire:click="$set('storeRating', {{ $i }})"
                                    class="{{ $storeRating >= $i ? 'text-yellow-400' : 'text-gray-300' }}">
                                ★
                            </button>
                        @endfor
                        <span class="ml-1 text-xs text-gray-500">{{ $storeRating }}/5</span>
                    </div>
                    @error('storeRating') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">Comment <span class="text-gray-400">(optional)</span></label>
                    <textarea wire:model.defer="storeReview" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Example: Seller was very responsive and items were well packed."></textarea>
                    @error('storeReview') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeRateModal"
                            class="px-3 py-1.5 border rounded-md text-xs text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitRating"
                            class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs hover:bg-indigo-700">
                        Save rating
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

