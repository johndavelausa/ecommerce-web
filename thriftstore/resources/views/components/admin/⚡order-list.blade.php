<?php

use App\Models\AdminAction;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\SellerPayout;
use App\Notifications\OrderDisputeUpdated;
use App\Notifications\SellerPayoutReleased;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $status = ''; // '', awaiting_payment, paid, to_pack, ready_to_ship, processing, shipped, out_for_delivery, delivered, completed, cancelled
    public string $search = '';
    /** A3 v1.4 — Date range filter (platform-wide orders) */
    public string $dateFrom = '';
    public string $dateTo = '';

    public bool $showDetails = false;
    public ?int $viewOrderId = null;

    /** A3 v1.4 — Admin order status override */
    public bool $showOverrideModal = false;
    public ?int $overrideOrderId = null;
    public string $overrideNewStatus = '';
    public string $overrideReason = '';

    public bool $showResolveDisputeModal = false;
    public ?int $resolveDisputeId = null;
    public string $resolveDisputeStatus = '';
    public string $resolveDisputeNote = '';

    protected $queryString = [
        'status' => ['except' => ''],
        'search' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    #[Computed]
    public function orders()
    {
        $q = Order::query()
            ->with(['customer', 'seller', 'items.product'])
            ->orderByDesc('created_at');

        if ($this->status !== '') {
            $q->where('status', $this->status);
        }

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $q->where(function ($query) use ($term) {
                $query->where('tracking_number', 'like', $term)
                    ->orWhere('id', 'like', $term)
                    ->orWhereHas('customer', function ($q2) use ($term) {
                        $q2->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    })
                    ->orWhereHas('seller', function ($q2) use ($term) {
                        $q2->where('store_name', 'like', $term);
                    });
            });
        }

        if ($this->dateFrom !== '') {
            $q->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $q->whereDate('created_at', '<=', $this->dateTo);
        }

        return $q->paginate(20);
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function viewOrder(int $id): void
    {
        $order = Order::query()->with(['customer', 'seller', 'items.product', 'disputes.customer', 'disputes.resolvedByAdmin', 'trackingEvents'])->find($id);
        if ($order) {
            $this->viewOrderId = $order->id;
            $this->showDetails = true;
        }
    }

    public function closeDetails(): void
    {
        $this->showDetails = false;
        $this->viewOrderId = null;
    }

    public function openOverrideModal(int $orderId): void
    {
        $this->overrideOrderId = $orderId;
        $this->overrideNewStatus = '';
        $this->overrideReason = '';
        $this->showOverrideModal = true;
    }

    public function closeOverrideModal(): void
    {
        $this->showOverrideModal = false;
        $this->overrideOrderId = null;
        $this->overrideNewStatus = '';
        $this->overrideReason = '';
        $this->resetErrorBag();
    }

    public function openResolveDisputeModal(int $disputeId): void
    {
        $this->resolveDisputeId = $disputeId;
        $this->resolveDisputeStatus = OrderDispute::STATUS_UNDER_ADMIN_REVIEW;
        $this->resolveDisputeNote = '';
        $this->showResolveDisputeModal = true;
        $this->resetErrorBag();
    }

    public function closeResolveDisputeModal(): void
    {
        $this->showResolveDisputeModal = false;
        $this->resolveDisputeId = null;
        $this->resolveDisputeStatus = '';
        $this->resolveDisputeNote = '';
        $this->resetErrorBag();
    }

    public function confirmResolveDispute(): void
    {
        $allowedStatuses = implode(',', OrderDispute::STATUSES);

        $this->validate([
            'resolveDisputeStatus' => ['required', 'string', 'in:'.$allowedStatuses],
            'resolveDisputeNote' => ['required', 'string', 'max:2000'],
        ]);

        $dispute = OrderDispute::query()->find($this->resolveDisputeId);
        if (! $dispute) {
            $this->closeResolveDisputeModal();
            return;
        }

        if (! $dispute->canTransitionTo($this->resolveDisputeStatus, 'admin')) {
            $this->addError('resolveDisputeStatus', 'Invalid dispute transition for current stage.');
            return;
        }

        $dispute->status = $this->resolveDisputeStatus;
        $dispute->admin_resolution_note = trim($this->resolveDisputeNote);
        $dispute->resolved_by_admin_id = auth('admin')->id();
        if (OrderDispute::isTerminalStatus($this->resolveDisputeStatus)) {
            $dispute->resolved_at = \Illuminate\Support\Carbon::now();
        }
        $dispute->save();

        $order = $dispute->order()->with(['disputes', 'payout', 'seller.user'])->first();
        if ($order) {
            $order->applyDisputeRefundDecision($this->resolveDisputeStatus);
            $order->save();

            if ($order->status === Order::STATUS_COMPLETED && $order->payout) {
                $decision = SellerPayout::decisionFromDisputes(collect($order->disputes));
                $payout = $order->payout;

                if (! (
                    $payout->status === SellerPayout::STATUS_ON_HOLD
                    && SellerPayout::isManualHoldReason($payout->hold_reason)
                    && $decision['status'] === SellerPayout::STATUS_RELEASED
                )) {
                    $previousStatus = $payout->status;
                    $payout->status = $decision['status'];
                    $payout->hold_reason = $decision['status'] === SellerPayout::STATUS_ON_HOLD
                        ? $decision['hold_reason']
                        : null;
                    $payout->released_at = $decision['status'] === SellerPayout::STATUS_RELEASED
                        ? now()
                        : null;
                    $payout->save();

                    if ($previousStatus !== SellerPayout::STATUS_RELEASED && $payout->status === SellerPayout::STATUS_RELEASED) {
                        $payout->seller?->user?->notify(new SellerPayoutReleased($payout));
                    }
                }
            }
        }

        $dispute->customer?->notify(new OrderDisputeUpdated($dispute, 'resolved'));
        $dispute->seller?->user?->notify(new OrderDisputeUpdated($dispute, 'resolved'));

        $this->closeResolveDisputeModal();
        $this->dispatch('order-updated');
    }

    public function confirmStatusOverride(): void
    {
        $this->validate([
            'overrideNewStatus' => ['required', 'string', 'in:awaiting_payment,paid,to_pack,ready_to_ship,processing,shipped,out_for_delivery,delivered,completed,cancelled'],
            'overrideReason' => ['required', 'string', 'max:2000'],
        ]);

        $order = Order::query()->find($this->overrideOrderId);
        if (! $order) {
            $this->closeOverrideModal();
            return;
        }

        $oldStatus = $order->status;
        $newStatus = $this->overrideNewStatus;

        $order->status = $newStatus;
        if ($newStatus === 'cancelled' && ! $order->cancelled_at) {
            $order->cancelled_at = \Illuminate\Support\Carbon::now();
            $order->applyCancellationRefundDecision($oldStatus);
        }
        if ($newStatus === 'delivered' && ! $order->delivered_at) {
            $order->delivered_at = \Illuminate\Support\Carbon::now();
        }
        if ($newStatus === 'completed' && ! $order->completed_at) {
            $order->completed_at = \Illuminate\Support\Carbon::now();
        }
        $order->save();

        AdminAction::logOrderStatusOverride(
            $order->id,
            $oldStatus,
            $newStatus,
            $this->overrideReason
        );

        $this->closeOverrideModal();
        if ($this->viewOrderId === $order->id) {
            $this->viewOrderId = null;
            $this->showDetails = false;
        }
        $this->dispatch('order-updated');
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <div class="flex gap-2 flex-wrap items-center">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search by ID, tracking #, customer, or seller…"
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

            <span class="text-xs text-gray-500">Date range:</span>
            <input type="date" wire:model.live="dateFrom"
                   class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <span class="text-gray-400">–</span>
            <input type="date" wire:model.live="dateTo"
                   class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID / Tracking</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
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
                        <td class="px-4 py-3">
                            <div class="text-gray-900 text-xs font-medium">#{{ $order->id }}</div>
                            <div class="font-mono text-xs text-gray-500">{{ $order->tracking_number ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-900 text-sm">{{ $order->customer->name ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $order->customer->email ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            {{ $order->seller->store_name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $statusColor = match ($order->status) {
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
                            @if($order->refund_status)
                                <div class="mt-1 text-xs text-gray-600">
                                    Refund: {{ \App\Models\Order::refundStatusLabel($order->refund_status) }}
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
                        <td class="px-4 py-3 text-sm">
                            <button type="button" wire:click="viewOrder({{ $order->id }})"
                                    class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                View
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
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
            $order = \App\Models\Order::with(['customer', 'seller', 'items.product', 'disputes.customer', 'disputes.resolvedByAdmin', 'trackingEvents'])->find($viewOrderId);
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
                                Tracking: {{ $order->tracking_number ?? '—' }} ·
                                {{ $order->seller->store_name ?? '—' }}
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
                            </div>
                            <div class="space-y-2">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Customer</h4>
                                <p class="text-gray-800">
                                    {{ $order->customer->name ?? '—' }}<br>
                                    <span class="text-xs text-gray-500">{{ $order->customer->email ?? '' }}</span>
                                </p>
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mt-2">Seller</h4>
                                <p class="text-gray-800">{{ $order->seller->store_name ?? '—' }}</p>
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
                                    @foreach($order->trackingEvents->take(12) as $event)
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
                                    @foreach($order->disputes as $d)
                                        <div class="rounded-md border border-gray-200 p-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="text-xs text-gray-700 font-semibold">Dispute #{{ $d->id }}</div>
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-gray-100 text-gray-700">{{ \App\Models\OrderDispute::statusLabel($d->status) }}</span>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-600">Reason: {{ \App\Models\OrderDispute::REASON_CODES[$d->reason_code] ?? ucfirst(str_replace('_', ' ', (string) $d->reason_code)) }}</div>
                                            <div class="mt-1 text-xs text-gray-700 whitespace-pre-wrap">{{ $d->description }}</div>
                                            @if($d->evidence_path)
                                                <div class="mt-1 text-xs"><a href="{{ asset('storage/' . $d->evidence_path) }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline">View evidence</a></div>
                                            @endif
                                            @if($d->seller_response_note)
                                                <div class="mt-2 text-xs text-emerald-700 whitespace-pre-wrap">Seller response: {{ $d->seller_response_note }}</div>
                                                @if($d->seller_responded_at)
                                                    <div class="mt-1 text-[11px] text-gray-500">Seller responded at {{ optional($d->seller_responded_at)->format('Y-m-d H:i') }}</div>
                                                @endif
                                            @endif
                                            @if($d->admin_resolution_note)
                                                <div class="mt-2 text-xs text-gray-600">Admin note: {{ $d->admin_resolution_note }}</div>
                                            @endif
                                            @if(!\App\Models\OrderDispute::isTerminalStatus($d->status))
                                                <div class="mt-2">
                                                    <button type="button" wire:click="openResolveDisputeModal({{ $d->id }})"
                                                            class="px-2 py-1 text-[11px] font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded hover:bg-amber-100">
                                                        Advance dispute stage
                                                    </button>
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

                    <div class="px-6 py-3 border-t flex justify-between items-center">
                        <div>
                            <button type="button" wire:click="openOverrideModal({{ $order->id }})"
                                    class="px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded hover:bg-amber-100">
                                Change status (admin override)
                            </button>
                        </div>
                        <button type="button" wire:click="closeDetails"
                                class="px-4 py-2 border rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- A3 v1.4 — Admin order status override modal --}}
    @if($showOverrideModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Override order status</h3>
                    <p class="text-sm text-gray-500 mt-1">Use only in exceptional cases (e.g. seller unavailable, dispute, system error). A reason is required.</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">New status</label>
                        <select wire:model.defer="overrideNewStatus"
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select status…</option>
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
                        @error('overrideNewStatus') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reason <span class="text-red-500">*</span></label>
                        <textarea wire:model.defer="overrideReason" rows="3"
                                  class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="e.g. Seller unresponsive; customer refunded via support."></textarea>
                        @error('overrideReason') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="px-6 py-3 border-t flex justify-end gap-2">
                    <button type="button" wire:click="closeOverrideModal"
                            class="px-4 py-2 border rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" wire:click="confirmStatusOverride"
                            class="px-4 py-2 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700">
                        Save & log
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showResolveDisputeModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Resolve dispute</h3>
                    <p class="text-sm text-gray-500 mt-1">Set final resolution status and add a required admin note.</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Resolution status</label>
                        <select wire:model.defer="resolveDisputeStatus"
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="under_admin_review">Under admin review</option>
                            <option value="return_requested">Return requested</option>
                            <option value="return_in_transit">Return in transit</option>
                            <option value="return_received">Return received</option>
                            <option value="refund_pending">Refund pending</option>
                            <option value="refund_completed">Refund completed</option>
                            <option value="resolved_approved">Resolved approved</option>
                            <option value="resolved_rejected">Resolved rejected</option>
                            <option value="closed">Closed</option>
                        </select>
                        @error('resolveDisputeStatus') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Admin note</label>
                        <textarea wire:model.defer="resolveDisputeNote" rows="3"
                                  class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Summarize your decision."></textarea>
                        @error('resolveDisputeNote') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="px-6 py-3 border-t flex justify-end gap-2">
                    <button type="button" wire:click="closeResolveDisputeModal"
                            class="px-4 py-2 border rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" wire:click="confirmResolveDispute"
                            class="px-4 py-2 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700">
                        Save resolution
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
