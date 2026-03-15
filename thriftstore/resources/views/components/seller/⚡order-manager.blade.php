<?php

use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\User;
use App\Notifications\OrderDisputeUpdated;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public int $acceptSlaHours = 12;

    public string $status = ''; // '', awaiting_payment, paid, to_pack, ready_to_ship, processing, shipped, out_for_delivery, delivered, completed, cancelled
    public string $search = ''; // by tracking number or customer name

    public bool $showDetails = false;
    public ?int $viewOrderId = null;

    public bool $showSellerDisputeModal = false;
    public ?int $sellerDisputeId = null;
    public string $sellerDisputeResponseNote = '';
    public $sellerDisputeEvidence = null;

    protected $queryString = [
        'status' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    #[Computed]
    public function seller()
    {
        return Auth::guard('seller')->user()?->seller;
    }

    #[Computed]
    public function orders()
    {
        $seller = $this->seller;
        if (! $seller) {
            return collect();
        }

        $q = Order::query()
            ->with([
                'customer',
                'items.product',
                'disputes' => function ($dq) {
                    $dq->latest('created_at');
                },
            ])
            ->where('seller_id', $seller->id)
            ->orderByDesc('created_at');

        if ($this->status !== '') {
            $q->where('status', $this->status);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(function ($query) use ($term) {
                $query->where('tracking_number', 'like', $term)
                      ->orWhereHas('customer', function ($q2) use ($term) {
                          $q2->where('name', 'like', $term)
                             ->orWhere('email', 'like', $term);
                      });
            });
        }

        return $q->paginate(10);
    }

    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingSearch(): void { $this->resetPage(); }

    public function viewOrder(int $id): void
    {
        $seller = $this->seller;
        if (! $seller) abort(403);

        $order = Order::query()
            ->with(['customer', 'items.product', 'disputes.customer', 'trackingEvents'])
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        $this->viewOrderId = $order->id;
        $this->showDetails = true;
    }

    public function closeDetails(): void
    {
        $this->showDetails = false;
        $this->viewOrderId = null;
    }

    public function openSellerDisputeModal(int $disputeId): void
    {
        $seller = $this->seller;
        if (! $seller) {
            abort(403);
        }

        $dispute = OrderDispute::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($disputeId);

        if (! in_array($dispute->status, [OrderDispute::STATUS_OPEN, OrderDispute::STATUS_SELLER_REVIEW], true)) {
            return;
        }

        $this->sellerDisputeId = $dispute->id;
        $this->sellerDisputeResponseNote = (string) ($dispute->seller_response_note ?? '');
        $this->showSellerDisputeModal = true;
        $this->resetErrorBag();
    }

    public function closeSellerDisputeModal(): void
    {
        $this->showSellerDisputeModal = false;
        $this->sellerDisputeId = null;
        $this->sellerDisputeResponseNote = '';
        $this->sellerDisputeEvidence = null;
        $this->resetErrorBag();
    }

    public function confirmSellerDisputeResponse(): void
    {
        $this->validate([
            'sellerDisputeResponseNote' => ['required', 'string', 'max:2000'],
            'sellerDisputeEvidence' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [], [
            'sellerDisputeResponseNote' => 'seller response',
            'sellerDisputeEvidence' => 'seller evidence',
        ]);

        $seller = $this->seller;
        if (! $seller || ! $this->sellerDisputeId) {
            $this->closeSellerDisputeModal();
            return;
        }

        $dispute = OrderDispute::query()
            ->where('seller_id', $seller->id)
            ->find($this->sellerDisputeId);

        if (! $dispute || ! in_array($dispute->status, [OrderDispute::STATUS_OPEN, OrderDispute::STATUS_SELLER_REVIEW], true)) {
            $this->closeSellerDisputeModal();
            return;
        }

        if (
            $dispute->status !== OrderDispute::STATUS_SELLER_REVIEW
            && ! $dispute->canTransitionTo(OrderDispute::STATUS_SELLER_REVIEW, 'seller')
        ) {
            $this->closeSellerDisputeModal();
            return;
        }

        $sellerResponse = trim($this->sellerDisputeResponseNote);
        if ($this->sellerDisputeEvidence) {
            $evidencePath = $this->sellerDisputeEvidence->store('disputes/seller', 'public');
            $sellerResponse .= "\n\nSeller evidence: " . asset('storage/' . $evidencePath);
        }

        $dispute->seller_response_note = $sellerResponse;
        $dispute->seller_responded_at = $dispute->freshTimestamp();
        $dispute->status = OrderDispute::STATUS_SELLER_REVIEW;
        $dispute->save();

        $dispute->customer?->notify(new OrderDisputeUpdated($dispute, 'seller_responded'));

        User::query()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })
            ->get()
            ->each(function (User $admin) use ($dispute) {
                $admin->notify(new OrderDisputeUpdated($dispute, 'seller_responded'));
            });

        \App\Models\SellerActivityLog::log($seller->id, 'order_dispute_reviewed', [
            'order_id' => $dispute->order_id,
            'dispute_id' => $dispute->id,
            'status' => $dispute->status,
        ]);

        $this->closeSellerDisputeModal();
    }

    /** B3 v1.4 — Mark shipped modal with optional estimated delivery date */
    public bool $showMarkShippedModal = false;
    public ?int $markShippedOrderId = null;
    public string $markShippedCourier = 'jnt';
    public string $markShippedManualTracking = '';
    public string $markShippedEstimatedDate = '';

    public function openMarkShippedModal(int $orderId): void
    {
        $this->markShippedOrderId = $orderId;
        $this->markShippedCourier = 'jnt';
        $this->markShippedManualTracking = '';
        $this->markShippedEstimatedDate = '';
        $this->showMarkShippedModal = true;
        $this->resetErrorBag();
    }

    public function closeMarkShippedModal(): void
    {
        $this->showMarkShippedModal = false;
        $this->markShippedOrderId = null;
        $this->markShippedCourier = 'jnt';
        $this->markShippedManualTracking = '';
        $this->markShippedEstimatedDate = '';
        $this->resetErrorBag();
    }

    public function confirmMarkShipped(): void
    {
        $this->validate([
            'markShippedCourier' => ['required', 'string', 'in:jnt,lbc,flash,ninjavan,xpost,other'],
            'markShippedManualTracking' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/'],
            'markShippedEstimatedDate' => ['nullable', 'date', 'after_or_equal:today'],
        ], [], [
            'markShippedCourier' => 'courier',
            'markShippedManualTracking' => 'manual tracking number',
            'markShippedEstimatedDate' => 'estimated delivery date',
        ]);

        $seller = $this->seller;
        if (! $seller || ! $this->markShippedOrderId) {
            $this->closeMarkShippedModal();
            return;
        }

        $order = Order::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($this->markShippedOrderId);

        if (! $order->canTransitionTo(Order::STATUS_SHIPPED, 'seller')) {
            $this->closeMarkShippedModal();
            return;
        }

        $order->status = Order::STATUS_SHIPPED;
        $order->courier_name = $this->markShippedCourier;
        $order->tracking_number = $this->markShippedManualTracking !== ''
            ? strtoupper(trim($this->markShippedManualTracking))
            : Order::generateTrackingNumber($this->markShippedCourier);
        $order->estimated_delivery_date = $this->markShippedEstimatedDate !== '' ? $this->markShippedEstimatedDate : null;
        $order->shipped_at = now();
        $order->save();

        \App\Models\SellerActivityLog::log($seller->id, 'order_marked_shipped', [
            'order_id' => $order->id,
            'courier_name' => $order->courier_name,
            'tracking_number' => $order->tracking_number,
            'estimated_delivery_date' => $order->estimated_delivery_date
                ? date('Y-m-d', strtotime((string) $order->estimated_delivery_date))
                : null,
        ]);

        $customer = $order->customer;
        if ($customer) {
            $customer->notify(new OrderStatusUpdated($order));
        }

        $this->closeMarkShippedModal();
    }

    public function updateStatus(int $id, string $status): void
    {
        // Sellers can move fulfillment from paid -> to_pack -> ready_to_ship -> shipped.
        // Delivered is controlled by the customer marking as received.
        if (! in_array($status, ['to_pack', 'ready_to_ship', 'shipped', 'out_for_delivery', 'cancelled'], true)) {
            return;
        }

        $seller = $this->seller;
        if (! $seller) abort(403);

        $order = Order::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        if ($status === 'to_pack') {
            if (! $order->canTransitionTo(Order::STATUS_TO_PACK, 'seller')) {
                return;
            }

            $order->status = Order::STATUS_TO_PACK;
        } elseif ($status === 'ready_to_ship') {
            if (! $order->canTransitionTo(Order::STATUS_READY_TO_SHIP, 'seller')) {
                return;
            }

            $order->status = Order::STATUS_READY_TO_SHIP;
        } elseif ($status === 'shipped') {
            // Use modal flow instead (openMarkShippedModal); this path kept for compatibility
            if (! $order->canTransitionTo(Order::STATUS_SHIPPED, 'seller')) {
                return;
            }
            $order->status = Order::STATUS_SHIPPED;
            if (! $order->tracking_number) {
                $order->courier_name = $order->courier_name ?: 'other';
                $order->tracking_number = Order::generateTrackingNumber($order->courier_name);
            }
            $order->shipped_at = $order->shipped_at ?: now();
        } elseif ($status === 'out_for_delivery') {
            if (! $order->canTransitionTo(Order::STATUS_OUT_FOR_DELIVERY, 'seller')) {
                return;
            }

            $order->status = Order::STATUS_OUT_FOR_DELIVERY;
        } elseif ($status === 'cancelled') {
            if (! $order->canTransitionTo(Order::STATUS_CANCELLED, 'seller')) {
                return;
            }
            $fromStatus = (string) $order->status;
            $order->status = Order::STATUS_CANCELLED;
            if (! $order->cancelled_at) {
                $order->cancelled_at = now();
            }
            $order->cancelled_by_type = 'seller';
            $order->cancellation_reason_code = 'out_of_stock';
            $order->cancellation_reason_note = null;
            $order->applyCancellationRefundDecision($fromStatus);
        }

        $order->save();

        if (in_array($status, ['to_pack', 'ready_to_ship', 'shipped', 'out_for_delivery'], true)) {
            \App\Models\SellerActivityLog::log($seller->id, 'order_marked_shipped', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'status' => $order->status,
            ]);
        }

        // Notify customer about status change
        $customer = $order->customer;
        if ($customer) {
            $customer->notify(new OrderStatusUpdated($order));
        }
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <div class="flex gap-2 flex-wrap">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search tracking # or customer…"
                   class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-64">

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

        <div class="text-xs text-gray-500">
            Showing {{ $this->orders->firstItem() ?? 0 }}–{{ $this->orders->lastItem() ?? 0 }}
            of {{ $this->orders->total() }} orders
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($this->orders as $order)
                    <tr>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ optional($order->created_at)->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-700">
                            {{ $order->tracking_number ?? '—' }}
                            @if($order->courier_name)
                                <div class="mt-0.5 font-sans text-[11px] text-gray-500">
                                    {{ \App\Models\Order::COURIERS[$order->courier_name] ?? strtoupper($order->courier_name) }}
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-900 text-sm">{{ $order->customer->name ?? 'Guest' }}</div>
                            <div class="text-xs text-gray-500">{{ $order->customer->email ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $activeDispute = $order->disputes->first(function ($d) {
                                    return \App\Models\OrderDispute::isActiveStatus((string) $d->status);
                                });
                                $latestDispute = $activeDispute ?: $order->disputes->first();
                            @endphp
                            @php
                                $statusColor = match($order->status) {
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
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                {{ ucwords(str_replace('_', ' ', $order->status)) }}
                            </span>
                            @if($latestDispute)
                                <div class="mt-1">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $activeDispute ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700' }}">
                                        Dispute: {{ \App\Models\OrderDispute::statusLabel((string) $latestDispute->status) }}
                                    </span>
                                </div>
                            @endif
                            @if($order->status === 'cancelled')
                                <div class="mt-1 text-xs text-gray-600">
                                    By: {{ ucfirst((string) ($order->cancelled_by_type ?? 'system')) }}
                                </div>
                                <div class="mt-0.5 text-xs text-gray-600">
                                    Reason: {{ \App\Models\Order::CANCELLATION_REASONS[$order->cancellation_reason_code] ?? ucfirst(str_replace('_', ' ', (string) ($order->cancellation_reason_code ?? 'unspecified'))) }}
                                </div>
                            @elseif($order->status === 'paid' && $order->created_at)
                                @php
                                    $acceptDeadline = $order->created_at->copy()->addHours($acceptSlaHours);
                                @endphp
                                <div class="mt-1 text-[11px] text-amber-700">
                                    Accept by: {{ $acceptDeadline->format('M j, Y g:i A') }}
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
                        </td>
                        <td class="px-4 py-3 text-right text-gray-900">
                            ₱{{ number_format($order->total_amount, 2) }}
                        </td>
                        <td class="px-4 py-3 text-sm space-x-2">
                            @php
                                $activeDispute = $order->disputes->first(function ($d) {
                                    return \App\Models\OrderDispute::isActiveStatus((string) $d->status);
                                });
                            @endphp
                            <button type="button" wire:click="viewOrder({{ $order->id }})"
                                    class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                View
                            </button>
                            <a href="{{ route('seller.orders.print', $order->id) }}" target="_blank"
                               class="text-xs font-medium text-gray-600 hover:text-gray-900">
                                Print slip
                            </a>

                            @if($order->status === 'paid')
                                <button type="button" wire:click="updateStatus({{ $order->id }}, 'to_pack')"
                                        class="text-xs text-cyan-700 hover:text-cyan-900 font-medium">
                                    Accept order
                                </button>
                                <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')"
                                        class="text-xs text-red-600 hover:text-red-800 font-medium">
                                    Cancel
                                </button>
                            @elseif($order->status === 'processing')
                                <button type="button" wire:click="updateStatus({{ $order->id }}, 'to_pack')"
                                        class="text-xs text-cyan-700 hover:text-cyan-900 font-medium">
                                    Move to pack
                                </button>
                                <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')"
                                        class="text-xs text-red-600 hover:text-red-800 font-medium">
                                    Cancel
                                </button>
                            @elseif($order->status === 'to_pack')
                                <button type="button" wire:click="updateStatus({{ $order->id }}, 'ready_to_ship')"
                                        class="text-xs text-violet-700 hover:text-violet-900 font-medium">
                                    Mark packed
                                </button>
                                <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')"
                                        class="text-xs text-red-600 hover:text-red-800 font-medium">
                                    Cancel
                                </button>
                            @elseif(in_array($order->status, ['ready_to_ship', 'processing'], true))
                                <button type="button" wire:click="openMarkShippedModal({{ $order->id }})"
                                        class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    Mark shipped
                                </button>
                                <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')"
                                        class="text-xs text-red-600 hover:text-red-800 font-medium">
                                    Cancel
                                </button>
                            @elseif($order->status === 'shipped')
                                <button type="button" wire:click="updateStatus({{ $order->id }}, 'out_for_delivery')"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                    Mark out for delivery
                                </button>
                            @endif

                            @if($activeDispute && in_array($activeDispute->status, [\App\Models\OrderDispute::STATUS_OPEN, \App\Models\OrderDispute::STATUS_SELLER_REVIEW], true))
                                <button type="button" wire:click="openSellerDisputeModal({{ $activeDispute->id }})"
                                        class="text-xs text-amber-700 hover:text-amber-900 font-medium">
                                    {{ $activeDispute->seller_response_note ? 'Update dispute response' : 'Respond to dispute' }}
                                </button>
                            @elseif($activeDispute)
                                <span class="text-[11px] text-gray-500">Dispute in admin review</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                            No orders found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3 border-t">
            {{ $this->orders->links() }}
        </div>
    </div>

    {{-- Order details modal --}}
    @if($showDetails && $viewOrderId)
        @php
            $order = \App\Models\Order::with(['customer', 'items.product', 'disputes.customer', 'trackingEvents'])->find($viewOrderId);
        @endphp
        @if($order)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[80vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b">
                    <div>
                        <h3 class="font-semibold text-gray-900 text-sm">
                            Order #{{ $order->id }}
                        </h3>
                        <p class="text-xs text-gray-500">
                            {{ optional($order->created_at)->format('Y-m-d H:i') }} ·
                            Tracking: {{ $order->tracking_number ?? '—' }}
                            @if($order->courier_name)
                                · Courier: {{ \App\Models\Order::COURIERS[$order->courier_name] ?? strtoupper($order->courier_name) }}
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="closeDetails"
                            class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4 text-sm">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2 space-y-2">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Shipping address</h4>
                            <p class="text-gray-800 whitespace-pre-wrap">{{ $order->shipping_address }}</p>
                            @if($order->customer_note)
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mt-3">Customer note</h4>
                                <p class="text-gray-800 whitespace-pre-wrap">{{ $order->customer_note }}</p>
                            @endif
                        </div>
                        <div class="space-y-2">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Customer</h4>
                            <p class="text-gray-800">
                                {{ $order->customer->name ?? 'Guest' }}<br>
                                <span class="text-xs text-gray-500">{{ $order->customer->email ?? '' }}</span>
                            </p>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Items</h4>
                        <div class="border rounded-md divide-y divide-gray-100">
                            @foreach($order->items as $item)
                                <div class="px-3 py-2 flex items-center justify-between gap-4">
                                    <div>
                                        <div class="text-gray-900 text-sm">
                                            {{ $item->product->name ?? 'Product #'.$item->product_id }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Qty: {{ $item->quantity }} × ₱{{ number_format($item->price_at_purchase, 2) }}
                                        </div>
                                    </div>
                                    <div class="text-sm font-medium text-gray-900">
                                        ₱{{ number_format($item->quantity * $item->price_at_purchase, 2) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Tracking timeline</h4>
                        @if($order->trackingEvents->isEmpty())
                            <div class="text-xs text-gray-500">No courier events yet.</div>
                        @else
                            <div class="rounded-md border border-gray-200 divide-y divide-gray-100">
                                @foreach($order->trackingEvents->take(10) as $event)
                                    <div class="px-3 py-2 text-xs">
                                        <div class="font-medium text-gray-800">
                                            {{ ucwords(str_replace('_', ' ', (string) $event->event_status)) }}
                                        </div>
                                        <div class="text-gray-500 mt-0.5">
                                            {{ optional($event->occurred_at ?? $event->created_at)->format('Y-m-d H:i') }}
                                            @if($event->location)
                                                · {{ $event->location }}
                                            @endif
                                            @if($event->description)
                                                · {{ $event->description }}
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Refund audit</h4>
                        @if($order->refund_status)
                            <div class="rounded-md border border-gray-200 p-3 space-y-1 text-xs">
                                <div class="text-gray-700">Status: <span class="font-medium">{{ \App\Models\Order::refundStatusLabel($order->refund_status) }}</span></div>
                                <div class="text-gray-700">Reason: <span class="font-medium">{{ \App\Models\Order::refundReasonLabel($order->refund_reason_code) }}</span></div>
                                @if($order->refunded_at)
                                    <div class="text-gray-700">Refunded at: <span class="font-medium">{{ $order->refunded_at->format('Y-m-d H:i') }}</span></div>
                                @endif
                            </div>
                        @else
                            <div class="text-xs text-gray-500">No refund audit data for this order.</div>
                        @endif
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Disputes</h4>
                        @if($order->disputes->isEmpty())
                            <div class="text-xs text-gray-500">No disputes for this order.</div>
                        @else
                            <div class="space-y-2">
                                @foreach($order->disputes as $dispute)
                                    <div class="rounded-md border border-gray-200 p-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="text-xs text-gray-700 font-semibold">Dispute #{{ $dispute->id }}</div>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-gray-100 text-gray-700">{{ \App\Models\OrderDispute::statusLabel($dispute->status) }}</span>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-600">Reason: {{ \App\Models\OrderDispute::REASON_CODES[$dispute->reason_code] ?? ucfirst(str_replace('_', ' ', (string) $dispute->reason_code)) }}</div>
                                        <div class="mt-1 text-xs text-gray-700 whitespace-pre-wrap">{{ $dispute->description }}</div>
                                        @if($dispute->evidence_path)
                                            <div class="mt-1 text-xs"><a href="{{ asset('storage/' . $dispute->evidence_path) }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline">View buyer evidence</a></div>
                                        @endif
                                        @if($dispute->seller_response_note)
                                            <div class="mt-2 text-xs text-emerald-700 whitespace-pre-wrap">Your response: {{ $dispute->seller_response_note }}</div>
                                            @if($dispute->seller_responded_at)
                                                <div class="mt-1 text-[11px] text-gray-500">Responded at {{ optional($dispute->seller_responded_at)->format('Y-m-d H:i') }}</div>
                                            @endif
                                        @endif
                                        @if(in_array($dispute->status, [\App\Models\OrderDispute::STATUS_OPEN, \App\Models\OrderDispute::STATUS_SELLER_REVIEW], true))
                                            <div class="mt-2">
                                                <button type="button" wire:click="openSellerDisputeModal({{ $dispute->id }})"
                                                        class="px-2 py-1 text-[11px] font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded hover:bg-amber-100">
                                                    {{ $dispute->seller_response_note ? 'Update response' : 'Submit response' }}
                                                </button>
                                            </div>
                                        @elseif(\App\Models\OrderDispute::isActiveStatus($dispute->status))
                                            <div class="mt-2 text-[11px] text-gray-500">
                                                Waiting for admin/customer action.
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end">
                        <div class="text-right">
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Total</div>
                            <div class="text-lg font-semibold text-gray-900">
                                ₱{{ number_format($order->total_amount, 2) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3 border-t flex justify-end">
                    <button type="button" wire:click="closeDetails"
                            class="px-4 py-2 border rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        Close
                    </button>
                </div>
            </div>
        </div>
        @endif
    @endif

    {{-- B3 v1.4 — Mark shipped modal (estimated delivery date) --}}
    @if($showMarkShippedModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Mark order as shipped</h3>
                    <p class="text-sm text-gray-500 mt-1">Set courier info. Tracking number can be auto-generated or manually entered.</p>
                </div>
                <div class="px-6 py-4 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Courier</label>
                        <select wire:model.defer="markShippedCourier"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach(\App\Models\Order::COURIERS as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('markShippedCourier') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Manual tracking number <span class="text-gray-400">(optional)</span></label>
                        <input type="text" wire:model.defer="markShippedManualTracking"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="Leave blank to auto-generate">
                        <p class="mt-1 text-xs text-gray-500">Allowed: letters, numbers, hyphen.</p>
                        @error('markShippedManualTracking') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <label class="block text-sm font-medium text-gray-700">Estimated delivery date (optional)</label>
                    <input type="date" wire:model.defer="markShippedEstimatedDate"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           min="{{ now()->format('Y-m-d') }}">
                    @error('markShippedEstimatedDate') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div class="px-6 py-3 border-t flex justify-end gap-2">
                    <button type="button" wire:click="closeMarkShippedModal"
                            class="px-4 py-2 border rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" wire:click="confirmMarkShipped"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
                        Mark shipped
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showSellerDisputeModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Respond to dispute</h3>
                    <p class="text-sm text-gray-500 mt-1">Your response will be visible to admin for final arbitration.</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Seller response</label>
                        <textarea wire:model.defer="sellerDisputeResponseNote" rows="4"
                                  class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Describe your side (timeline, packing proof, courier context)."></textarea>
                        @error('sellerDisputeResponseNote') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Evidence file <span class="text-gray-400">(optional)</span></label>
                        <input type="file" wire:model="sellerDisputeEvidence"
                               class="mt-1 block w-full text-sm text-gray-600"
                               accept=".jpg,.jpeg,.png,.webp,.pdf">
                        @error('sellerDisputeEvidence') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="px-6 py-3 border-t flex justify-end gap-2">
                    <button type="button" wire:click="closeSellerDisputeModal"
                            class="px-4 py-2 border rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" wire:click="confirmSellerDisputeResponse"
                            class="px-4 py-2 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700">
                        Save response
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

