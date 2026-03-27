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
        $dispute = OrderDispute::query()->find($disputeId);
        if (! $dispute) {
            return;
        }

        $this->resolveDisputeId = $disputeId;
        $options = $this->availableResolveDisputeStatuses($dispute);
        $this->resolveDisputeStatus = $options[0] ?? '';
        $this->resolveDisputeNote = '';
        $this->showResolveDisputeModal = true;
        $this->resetErrorBag();
    }

    protected function availableResolveDisputeStatuses(OrderDispute $dispute): array
    {
        $currentStatus = (string) $dispute->status;
        $options = [];

        foreach (OrderDispute::STATUSES as $status) {
            if ($status === $currentStatus) {
                continue;
            }

            if (! $dispute->canTransitionTo($status, 'admin')) {
                continue;
            }

            // Parcel-not-received cases typically do not require a physical return flow.
            if (
                $dispute->reason_code === 'parcel_not_received'
                && in_array($status, [
                    OrderDispute::STATUS_RETURN_REQUESTED,
                    OrderDispute::STATUS_RETURN_IN_TRANSIT,
                    OrderDispute::STATUS_RETURN_RECEIVED,
                ], true)
            ) {
                continue;
            }

            $options[] = $status;
        }

        return $options;
    }

    #[Computed]
    public function resolveDisputeStatusOptions(): array
    {
        if (! $this->resolveDisputeId) {
            return [];
        }

        $dispute = OrderDispute::query()->find($this->resolveDisputeId);
        if (! $dispute) {
            return [];
        }

        return $this->availableResolveDisputeStatuses($dispute);
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
        $dispute = OrderDispute::query()->find($this->resolveDisputeId);
        if (! $dispute) {
            $this->closeResolveDisputeModal();
            return;
        }

        $allowedStatuses = $this->availableResolveDisputeStatuses($dispute);
        if ($allowedStatuses === []) {
            $this->addError('resolveDisputeStatus', 'No valid next stage is available for this dispute.');
            return;
        }

        $allowedStatusesList = implode(',', $allowedStatuses);

        $this->validate([
            'resolveDisputeStatus' => ['required', 'string', 'in:'.$allowedStatusesList],
            'resolveDisputeNote' => ['required', 'string', 'max:2000'],
        ]);

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

<style>
    .ord-search { border-radius: 50px; border: 1.5px solid #D4E8DA; padding: 8px 16px; font-size: 0.8125rem; background: #fff; color: #424242; transition: all 0.15s; }
    .ord-search:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .ord-select { border-radius: 12px; border: 1.5px solid #D4E8DA; padding: 8px 12px; font-size: 0.8125rem; background: #fff; color: #424242; transition: all 0.15s; }
    .ord-select:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .ord-table-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; overflow: hidden; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .ord-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .ord-table th { padding: 9px 16px; text-align: left; font-size: 0.6875rem; font-weight: 700; color: #1B7A37; text-transform: uppercase; letter-spacing: 0.05em; background: #F5FBF7; border-bottom: 1px solid #D4E8DA; }
    .ord-table td { padding: 9px 16px; color: #424242; border-bottom: 1px solid #F0F7F2; }
    .ord-table tr:last-child td { border-bottom: none; }
    .ord-table tr:hover td { background: #F5FBF7; }
    .ord-status-badge { padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
    .ord-status-awaiting { background: #FFF3E0; color: #E65100; }
    .ord-status-paid { background: #E0F7FA; color: #00838F; }
    .ord-status-to-pack { background: #E1F5FE; color: #01579B; }
    .ord-status-ready { background: #F3E5F5; color: #6A1B9A; }
    .ord-status-processing { background: #FFF9E3; color: #F57C00; }
    .ord-status-shipped { background: #E3F2FD; color: #1565C0; }
    .ord-status-delivery { background: #E8EAF6; color: #283593; }
    .ord-status-delivered { background: #E8F5E9; color: #1B7A37; }
    .ord-status-completed { background: #C8E6C9; color: #1B5E20; }
    .ord-status-cancelled { background: #FFEBEE; color: #C0392B; }
    .ord-action-btn { font-size: 0.8125rem; font-weight: 600; color: #2D9F4E; text-decoration: none; transition: all 0.15s; }
    .ord-action-btn:hover { color: #1B7A37; text-decoration: underline; }
    .ord-label { font-size: 0.6875rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; font-style: italic; }
    .ord-modal { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; box-shadow: 0 10px 40px rgba(15,61,34,0.2); }
    .ord-modal-title { font-size: 1.125rem; font-weight: 800; color: #0F3D22; }
    .ord-modal-input, .ord-modal-textarea, .ord-modal-select { border-radius: 12px; border: 1.5px solid #D4E8DA; padding: 8px 12px; font-size: 0.8125rem; color: #424242; transition: all 0.15s; }
    .ord-modal-input:focus, .ord-modal-textarea:focus, .ord-modal-select:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .ord-modal-btn { padding: 8px 16px; border-radius: 50px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid #D4E8DA; background: #fff; color: #424242; text-decoration: none; transition: all 0.15s; cursor: pointer; }
    .ord-modal-btn-primary { background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #fff; border-color: #2D9F4E; }
    .ord-modal-btn-primary:hover { box-shadow: 0 4px 14px rgba(15,61,34,0.2); }
    .ord-modal-btn-amber { background: #FFF3E0; color: #E65100; border-color: #F57C00; }
    .ord-modal-btn-amber:hover { background: #FFE0B2; }
</style>

<div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <div class="flex gap-2 flex-wrap items-center">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search by ID, tracking #, customer, or seller…"
                   class="ord-search w-64">

            <!-- <select wire:model.live="status" class="ord-select">
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
            </select> -->

            <span class="ord-label">Date range:</span>
            <input type="date" wire:model.live="dateFrom" class="ord-select">
            <span style="color:#9E9E9E;">–</span>
            <input type="date" wire:model.live="dateTo" class="ord-select">
        </div>

        <div class="text-xs" style="color:#9E9E9E;font-style:italic;">
            Showing {{ $this->orders->firstItem() ?? 0 }}–{{ $this->orders->lastItem() ?? 0 }}
            of {{ $this->orders->total() }} orders
        </div>
    </div>

    <div class="ord-table-card">
        <table class="ord-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>ID / Tracking</th>
                    <th>Customer</th>
                    <th>Seller</th>
                    <th>Status</th>
                    <th style="text-align:right;">Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->orders as $order)
                    <tr>
                        <td style="color:#9E9E9E;font-style:italic;font-size:0.8125rem;">
                            {{ optional($order->created_at)->format('Y-m-d H:i') }}
                        </td>
                        <td>
                            <div style="color:#0F3D22;font-weight:600;font-size:0.8125rem;">#{{ $order->id }}</div>
                            <div style="font-family:monospace;font-size:0.75rem;color:#9E9E9E;font-style:italic;">{{ $order->tracking_number ?? '—' }}</div>
                        </td>
                        <td>
                            <div style="color:#0F3D22;font-weight:600;">{{ $order->customer->name ?? '—' }}</div>
                            <div class="text-xs" style="color:#9E9E9E;font-style:italic;">{{ $order->customer->email ?? '' }}</div>
                        </td>
                        <td style="color:#424242;">
                            {{ $order->seller->store_name ?? '—' }}
                        </td>
                        <td>
                            @php
                                $statusClass = match ($order->status) {
                                    'awaiting_payment' => 'ord-status-awaiting',
                                    'paid' => 'ord-status-paid',
                                    'to_pack' => 'ord-status-to-pack',
                                    'ready_to_ship' => 'ord-status-ready',
                                    'processing' => 'ord-status-processing',
                                    'shipped' => 'ord-status-shipped',
                                    'out_for_delivery' => 'ord-status-delivery',
                                    'delivered' => 'ord-status-delivered',
                                    'completed' => 'ord-status-completed',
                                    'cancelled' => 'ord-status-cancelled',
                                    default => 'ord-status-awaiting',
                                };
                            @endphp
                            <span class="ord-status-badge {{ $statusClass }}">
                                {{ ucwords(str_replace('_', ' ', $order->status)) }}
                            </span>
                            @if($order->refund_status)
                                <div class="text-xs mt-1" style="color:#9E9E9E;font-style:italic;">
                                    Refund: {{ \App\Models\Order::refundStatusLabel($order->refund_status) }}
                                </div>
                                @if($order->refunded_at)
                                    <div class="text-xs" style="color:#9E9E9E;font-style:italic;">
                                        {{ $order->refunded_at->format('M j, Y g:i A') }}
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td style="text-align:right;color:#0F3D22;font-weight:700;">
                            ₱{{ number_format($order->total_amount, 2) }}
                        </td>
                        <td>
                            <button type="button" wire:click="viewOrder({{ $order->id }})" class="ord-action-btn">
                                View
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:32px 16px;color:#9E9E9E;font-style:italic;">
                            No orders found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="padding:12px 16px;border-top:1px solid #D4E8DA;">
            {{ $this->orders->links() }}
        </div>
    </div>

    {{-- Order details modal (receipt style) --}}
    @if($showDetails && $viewOrderId)
        @php
            $order = \App\Models\Order::with(['customer', 'seller', 'items.product', 'disputes.customer', 'disputes.resolvedByAdmin', 'trackingEvents'])->find($viewOrderId);
        @endphp
        @if($order)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="ord-modal w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
                    {{-- Receipt Header --}}
                    <div style="background:linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%);color:#fff;padding:20px;border-radius:20px 20px 0 0;">
                        <div class="flex justify-between items-start">
                            <div>
                                <div style="font-size:1.25rem;font-weight:800;letter-spacing:0.05em;">ORDER RECEIPT</div>
                                <div style="font-size:0.75rem;opacity:0.9;margin-top:4px;font-style:italic;">Order #{{ $order->id }}</div>
                            </div>
                            <button type="button" wire:click="closeDetails" style="background:rgba(255,255,255,0.2);border:none;color:#fff;font-size:1.5rem;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;">&times;</button>
                        </div>
                    </div>

                    {{-- Receipt Body --}}
                    <div class="flex-1 overflow-y-auto" style="padding:20px;background:#fff;">
                        {{-- Order Info Row --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #D4E8DA;">
                            <div>
                                <div style="font-size:0.6875rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.05em;font-style:italic;margin-bottom:4px;">Date & Time</div>
                                <div style="color:#0F3D22;font-weight:600;">{{ optional($order->created_at)->format('M d, Y') }}</div>
                                <div style="font-size:0.75rem;color:#9E9E9E;font-style:italic;">{{ optional($order->created_at)->format('H:i A') }}</div>
                            </div>
                            <div>
                                <div style="font-size:0.6875rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.05em;font-style:italic;margin-bottom:4px;">Tracking Number</div>
                                <div style="color:#0F3D22;font-weight:600;font-family:monospace;">{{ $order->tracking_number ?? '—' }}</div>
                            </div>
                        </div>

                        {{-- Customer & Seller Info --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #D4E8DA;">
                            <div>
                                <div style="font-size:0.6875rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.05em;font-style:italic;margin-bottom:6px;">Customer</div>
                                <div style="color:#0F3D22;font-weight:600;font-size:0.9375rem;">{{ $order->customer->name ?? '—' }}</div>
                                <div style="font-size:0.75rem;color:#9E9E9E;font-style:italic;margin-top:2px;">{{ $order->customer->email ?? '' }}</div>
                            </div>
                            <div>
                                <div style="font-size:0.6875rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.05em;font-style:italic;margin-bottom:6px;">Seller</div>
                                <div style="color:#0F3D22;font-weight:600;font-size:0.9375rem;">{{ $order->seller->store_name ?? '—' }}</div>
                            </div>
                        </div>

                        {{-- Shipping Address --}}
                        <div style="margin-bottom:20px;padding:12px;background:#F5FBF7;border-radius:12px;border:1px solid #D4E8DA;">
                            <div style="font-size:0.6875rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.05em;font-style:italic;margin-bottom:6px;">Shipping Address</div>
                            <div style="color:#0F3D22;font-size:0.8125rem;line-height:1.5;white-space:pre-wrap;">{{ $order->shipping_address }}</div>
                        </div>

                        {{-- Items Table (Receipt Style) --}}
                        <div style="margin-bottom:20px;">
                            <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;padding:10px 0;border-bottom:2px solid #0F3D22;margin-bottom:8px;font-size:0.75rem;font-weight:700;color:#1B7A37;text-transform:uppercase;letter-spacing:0.05em;">
                                <div>Item</div>
                                <div style="text-align:center;">Qty</div>
                                <div style="text-align:right;">Amount</div>
                            </div>
                            @foreach($order->items as $item)
                                <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;padding:10px 0;border-bottom:1px solid #F0F7F2;align-items:start;">
                                    <div>
                                        <div style="color:#0F3D22;font-weight:600;font-size:0.8125rem;">{{ $item->product->name ?? 'Product #'.$item->product_id }}</div>
                                        <div style="font-size:0.75rem;color:#9E9E9E;font-style:italic;margin-top:2px;">₱{{ number_format($item->price_at_purchase, 2) }} each</div>
                                    </div>
                                    <div style="text-align:center;color:#424242;font-weight:600;">{{ $item->quantity }}</div>
                                    <div style="text-align:right;color:#0F3D22;font-weight:700;">₱{{ number_format($item->quantity * $item->price_at_purchase, 2) }}</div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Total Section --}}
                        <div style="background:#F5FBF7;padding:14px;border-radius:12px;border:1.5px solid #D4E8DA;margin-bottom:20px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div style="font-size:0.9375rem;font-weight:700;color:#0F3D22;text-transform:uppercase;letter-spacing:0.05em;">Total Amount</div>
                                <div style="font-size:1.5rem;font-weight:800;color:#1B7A37;">₱{{ number_format($order->total_amount, 2) }}</div>
                            </div>
                        </div>

                        {{-- Status & Refund Info --}}
                        @php
                            $statusClass = match ($order->status) {
                                'awaiting_payment' => 'ord-status-awaiting',
                                'paid' => 'ord-status-paid',
                                'to_pack' => 'ord-status-to-pack',
                                'ready_to_ship' => 'ord-status-ready',
                                'processing' => 'ord-status-processing',
                                'shipped' => 'ord-status-shipped',
                                'out_for_delivery' => 'ord-status-delivery',
                                'delivered' => 'ord-status-delivered',
                                'completed' => 'ord-status-completed',
                                'cancelled' => 'ord-status-cancelled',
                                default => 'ord-status-awaiting',
                            };
                        @endphp
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div style="padding:12px;background:#F5FBF7;border-radius:12px;border:1px solid #D4E8DA;">
                                <div style="font-size:0.6875rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.05em;font-style:italic;margin-bottom:6px;">Order Status</div>
                                <span class="ord-status-badge {{ $statusClass }}" style="display:inline-block;">{{ ucwords(str_replace('_', ' ', $order->status)) }}</span>
                            </div>
                            @if($order->refund_status)
                                <div style="padding:12px;background:#FFF3E0;border-radius:12px;border:1px solid #FFE0B2;">
                                    <div style="font-size:0.6875rem;font-weight:700;color:#E65100;text-transform:uppercase;letter-spacing:0.05em;font-style:italic;margin-bottom:6px;">Refund Status</div>
                                    <div style="color:#E65100;font-weight:600;font-size:0.8125rem;">{{ \App\Models\Order::refundStatusLabel($order->refund_status) }}</div>
                                </div>
                            @endif
                        </div>

                        {{-- Tracking Timeline (if exists) --}}
                        @if(!$order->trackingEvents->isEmpty())
                            <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #D4E8DA;">
                                <div style="font-size:0.6875rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.05em;font-style:italic;margin-bottom:8px;">Recent Tracking Events</div>
                                <div style="space-y:2;">
                                    @foreach($order->trackingEvents->take(5) as $event)
                                        <div style="padding:8px 0;border-bottom:1px solid #F0F7F2;font-size:0.75rem;">
                                            <div style="color:#0F3D22;font-weight:600;">{{ ucwords(str_replace('_', ' ', (string) $event->event_status)) }}</div>
                                            <div style="color:#9E9E9E;font-style:italic;margin-top:2px;">{{ optional($event->occurred_at ?? $event->created_at)->format('M d, Y H:i') }}@if($event->location) · {{ $event->location }}@endif</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    </div>

                    {{-- Receipt Footer --}}
                    <div style="padding:14px 20px;border-top:1px solid #D4E8DA;background:#F5FBF7;border-radius:0 0 20px 20px;display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" wire:click="closeDetails" class="ord-modal-btn">
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
                    <p class="text-sm text-gray-500 mt-1">Choose the next valid action for this dispute and add a required admin note.</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Next action</label>
                        @if(!empty($this->resolveDisputeStatusOptions))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($this->resolveDisputeStatusOptions as $statusOption)
                                    @php
                                        $actionLabel = match ($statusOption) {
                                            \App\Models\OrderDispute::STATUS_UNDER_ADMIN_REVIEW => 'Move to admin review',
                                            \App\Models\OrderDispute::STATUS_RETURN_REQUESTED => 'Request item return',
                                            \App\Models\OrderDispute::STATUS_RETURN_IN_TRANSIT => 'Mark return in transit',
                                            \App\Models\OrderDispute::STATUS_RETURN_RECEIVED => 'Confirm return received',
                                            \App\Models\OrderDispute::STATUS_REFUND_PENDING => 'Approve refund',
                                            \App\Models\OrderDispute::STATUS_REFUND_COMPLETED => 'Mark refund completed',
                                            \App\Models\OrderDispute::STATUS_RESOLVED_APPROVED => 'Resolve in buyer favor',
                                            \App\Models\OrderDispute::STATUS_RESOLVED_REJECTED => 'Reject buyer claim',
                                            \App\Models\OrderDispute::STATUS_CLOSED => 'Close dispute',
                                            default => \App\Models\OrderDispute::statusLabel($statusOption),
                                        };
                                    @endphp
                                    <button type="button"
                                            wire:click="$set('resolveDisputeStatus', '{{ $statusOption }}')"
                                            class="px-2.5 py-1.5 text-xs rounded-md border font-medium {{ $resolveDisputeStatus === $statusOption ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                        {{ $actionLabel }}
                                    </button>
                                @endforeach
                            </div>
                            @if($resolveDisputeStatus !== '')
                                <div class="mt-2 text-xs text-gray-600">
                                    Selected stage: <span class="font-medium">{{ \App\Models\OrderDispute::statusLabel($resolveDisputeStatus) }}</span>
                                </div>
                            @endif
                        @else
                            <div class="mt-2 text-xs text-gray-500">No valid next stage available for this dispute.</div>
                        @endif
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
                            @disabled(empty($this->resolveDisputeStatusOptions) || $resolveDisputeStatus === '')
                            class="px-4 py-2 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700">
                        Confirm action
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
