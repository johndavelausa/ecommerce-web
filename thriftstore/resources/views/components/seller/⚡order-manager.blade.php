<?php

use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\OrderTrackingEvent;
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

    // Dynamic properties for non-receipt responses (indexed by dispute ID)
    public array $sellerExplanationCodes = [];
    public array $sellerExplanationNotes = [];

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
            ->with(['customer', 'items.product', 'disputes.customer', 'trackingEvents', 'statusHistory'])
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

    public function respondToNonReceipt(int $disputeId, string $action): void
    {
        $seller = $this->seller;
        if (! $seller) {
            abort(403);
        }

        $dispute = OrderDispute::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($disputeId);

        if ($dispute->reason_code !== 'parcel_not_received') {
            return;
        }

        $explanationCode = $this->sellerExplanationCodes[$disputeId] ?? '';
        $explanationNote = $this->sellerExplanationNotes[$disputeId] ?? '';

        if (empty($explanationCode)) {
            $this->addError("sellerExplanationCodes.{$disputeId}", 'Please select a reason');
            return;
        }

        $explanationLabel = OrderDispute::SELLER_EXPLANATION_CODES[$explanationCode] ?? $explanationCode;
        $fullResponse = "Reason: {$explanationLabel}";
        if (!empty($explanationNote)) {
            $fullResponse .= "\n\nDetails: " . trim($explanationNote);
        }

        $dispute->seller_response_note = $fullResponse;
        $dispute->seller_responded_at = now();
        $dispute->seller_resolution_action = $action;
        $dispute->status = OrderDispute::STATUS_CLOSED;
        $dispute->resolved_at = now();
        $dispute->save();

        if ($action === 'refund') {
            $order = $dispute->order;
            if ($order) {
                $order->refund_status = 'pending';
                $order->save();
            }
        }

        $dispute->customer?->notify(new OrderDisputeUpdated($dispute, 'seller_responded'));

        session()->flash('message', $action === 'refund' ? 'Response submitted and refund initiated' : 'Response submitted to customer');
        
        // Force modal to reload with fresh data
        $orderId = $dispute->order_id;
        $this->closeDetails();
        $this->viewOrder($orderId);
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

    // Transit update properties
    public string $transitLocation = '';
    public string $transitNote = '';

    public function addTransitUpdate(int $orderId): void
    {
        $this->validate([
            'transitLocation' => ['required', 'string', 'max:255'],
            'transitNote' => ['nullable', 'string', 'max:500'],
        ], [], [
            'transitLocation' => 'location',
            'transitNote' => 'note',
        ]);

        $seller = $this->seller;
        if (! $seller) {
            abort(403);
        }

        $order = Order::query()
            ->where('seller_id', $seller->id)
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_OUT_FOR_DELIVERY])
            ->findOrFail($orderId);

        $description = trim($this->transitNote) !== ''
            ? trim($this->transitNote)
            : 'Parcel in transit';

        $location = trim($this->transitLocation);

        OrderTrackingEvent::create([
            'order_id'        => $order->id,
            'tracking_number' => $order->tracking_number,
            'courier_name'    => $order->courier_name,
            'provider'        => 'seller',
            'event_status'    => 'in_transit',
            'event_code'      => 'transit_update',
            'location'        => $location,
            'description'     => $description,
            'occurred_at'     => now(),
        ]);

        \App\Models\SellerActivityLog::log($seller->id, 'transit_update_added', [
            'order_id' => $order->id,
            'location' => $location,
        ]);

        $this->transitLocation = '';
        $this->transitNote = '';
        $this->resetErrorBag();

        // Refresh the modal
        if ($this->showDetails && $this->viewOrderId === $orderId) {
            $this->closeDetails();
            $this->viewOrder($orderId);
        }
    }

    public function updateStatus(int $id, string $status): void
    {
        // Sellers can manage full delivery flow: paid -> to_pack -> ready_to_ship -> shipped -> out_for_delivery -> delivered
        // Customer confirms receipt (received), system auto-completes
        if (! in_array($status, ['to_pack', 'ready_to_ship', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'], true)) {
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
        } elseif ($status === 'delivered') {
            if (! $order->canTransitionTo(Order::STATUS_DELIVERED, 'seller')) {
                return;
            }
            $order->status = Order::STATUS_DELIVERED;
            $order->delivered_at = now();
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

        if (in_array($status, ['to_pack', 'ready_to_ship', 'shipped', 'out_for_delivery', 'delivered'], true)) {
            \App\Models\SellerActivityLog::log($seller->id, 'order_status_updated', [
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

        // If modal is open for this order, keep it open but refresh the data
        if ($this->showDetails && $this->viewOrderId === $id) {
            // Modal will auto-refresh with new data on next render
            $this->dispatch('$refresh');
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
                        <td class="px-4 py-3 text-sm">
                            @php
                                $activeDispute = $order->disputes->first(function ($d) {
                                    return \App\Models\OrderDispute::isActiveStatus((string) $d->status);
                                });
                            @endphp
                            <button type="button" wire:click="viewOrder({{ $order->id }})"
                                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs font-medium hover:bg-indigo-700 transition">
                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View
                            </button>
                            @if($activeDispute)
                                <div class="mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Dispute
                                    </span>
                                </div>
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:key="order-modal-{{ $order->id }}-{{ $order->updated_at->timestamp }}">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-[85vh] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900 text-lg">
                                Order #{{ $order->id }}
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ optional($order->created_at)->format('M j, Y g:i A') }} ·
                                Tracking: {{ $order->tracking_number ?? '—' }}
                                @if($order->courier_name)
                                    · Courier: {{ \App\Models\Order::COURIERS[$order->courier_name] ?? strtoupper($order->courier_name) }}
                                @endif
                            </p>
                        </div>
                        <button type="button" wire:click="closeDetails"
                                class="text-gray-400 hover:text-gray-600 text-2xl font-light">&times;</button>
                    </div>
                </div>

                <div class="flex-1 overflow-hidden flex">
                    {{-- Left: Order Details --}}
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
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Delivery Issues</h4>
                            @if($order->disputes->isEmpty())
                                <div class="text-xs text-gray-500">No delivery issues reported.</div>
                            @else
                                <div class="space-y-2">
                                    @foreach($order->disputes as $dispute)
                                        <div class="rounded-md border border-gray-200 p-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="text-xs text-gray-700 font-semibold">Issue Report #{{ $dispute->id }}</div>
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
                                                @if($dispute->reason_code === 'parcel_not_received' && !$dispute->seller_response_note)
                                                    <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                                        <div class="text-xs font-semibold text-amber-900 mb-2">Customer reported: Item not received</div>
                                                        <div class="text-xs text-amber-700 mb-3">Provide explanation about what happened to the parcel:</div>
                                                        
                                                        <select wire:model.defer="sellerExplanationCodes.{{ $dispute->id }}" class="w-full text-xs rounded border-amber-300 mb-2">
                                                            <option value="">Select reason...</option>
                                                            @foreach(\App\Models\OrderDispute::SELLER_EXPLANATION_CODES as $code => $label)
                                                                <option value="{{ $code }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error("sellerExplanationCodes.{$dispute->id}") <p class="text-xs text-red-600 mb-2">{{ $message }}</p> @enderror
                                                        
                                                        <textarea wire:model.defer="sellerExplanationNotes.{{ $dispute->id }}" 
                                                                  placeholder="Additional details..."
                                                                  class="w-full text-xs rounded border-amber-300 mb-2" rows="2"></textarea>
                                                        
                                                        <div class="flex gap-2">
                                                            <button type="button" wire:click="respondToNonReceipt({{ $dispute->id }}, 'no_refund')"
                                                                    class="w-full px-3 py-1.5 bg-blue-600 text-white rounded text-xs font-medium hover:bg-blue-700">
                                                                Acknowledge & Respond
                                                            </button>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="mt-2">
                                                        <button type="button" wire:click="openSellerDisputeModal({{ $dispute->id }})"
                                                                class="px-2 py-1 text-[11px] font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded hover:bg-amber-100">
                                                            {{ $dispute->seller_response_note ? 'Update response' : 'Submit response' }}
                                                        </button>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Right: Order Actions Sidebar --}}
                    <div class="w-80 border-l bg-gray-50 overflow-y-auto">
                        <div class="p-4 space-y-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 mb-3">Order Actions</h4>
                                
                                {{-- Current Status Badge --}}
                                <div class="mb-4 p-3 bg-white rounded-lg border">
                                    <div class="text-xs text-gray-500 mb-1">Current Status</div>
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Total: ₱{{ number_format($order->total_amount, 2) }}
                                    </div>
                                </div>

                                {{-- Action Buttons --}}
                                <div class="space-y-2">
                                    <a href="{{ route('seller.orders.print', $order->id) }}" target="_blank"
                                       class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                        Print Slip
                                    </a>

                                    @if(in_array($order->status, ['awaiting_payment', 'paid'], true))
                                        <button type="button" wire:click="updateStatus({{ $order->id }}, 'to_pack')"
                                                class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-cyan-600 text-white rounded-lg text-sm font-medium hover:bg-cyan-700 transition shadow-sm">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Accept Order
                                        </button>
                                        <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')"
                                                class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-white border-2 border-red-300 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition">
                                            Cancel Order
                                        </button>
                                    @elseif($order->status === 'to_pack')
                                        <button type="button" wire:click="updateStatus({{ $order->id }}, 'ready_to_ship')"
                                                class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700 transition shadow-sm">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                            Mark as Packed
                                        </button>
                                        <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')"
                                                class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-white border-2 border-red-300 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition">
                                            Cancel Order
                                        </button>
                                    @elseif(in_array($order->status, ['ready_to_ship', 'processing'], true))
                                        <button type="button" wire:click="openMarkShippedModal({{ $order->id }})"
                                                class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                                            </svg>
                                            Mark as Shipped
                                        </button>
                                        <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')"
                                                class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-white border-2 border-red-300 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition">
                                            Cancel Order
                                        </button>
                                    @elseif($order->status === 'shipped')
                                        {{-- Transit Location Update --}}
                                        <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg mb-2">
                                            <div class="text-xs font-semibold text-blue-800 mb-2 flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                Add Transit Update
                                            </div>
                                            <input type="text" wire:model.defer="transitLocation"
                                                   class="w-full text-xs rounded-md border-blue-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 mb-1.5"
                                                   placeholder="Location (e.g. Manila Sorting Hub)">
                                            @error('transitLocation') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror
                                            <input type="text" wire:model.defer="transitNote"
                                                   class="w-full text-xs rounded-md border-blue-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 mb-2"
                                                   placeholder="Note (optional, e.g. Arrived at sorting facility)">
                                            @error('transitNote') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror
                                            <button type="button" wire:click="addTransitUpdate({{ $order->id }})"
                                                    class="w-full inline-flex items-center justify-center px-3 py-1.5 bg-blue-600 text-white rounded-md text-xs font-medium hover:bg-blue-700 transition">
                                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                Add Location Update
                                            </button>
                                        </div>

                                        <button type="button" wire:click="updateStatus({{ $order->id }}, 'out_for_delivery')"
                                                class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition shadow-sm">
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                                <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z"/>
                                            </svg>
                                            Mark Out for Delivery
                                        </button>
                                    @elseif($order->status === 'out_for_delivery')
                                        <button type="button" wire:click="updateStatus({{ $order->id }}, 'delivered')"
                                                class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition shadow-sm">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Mark as Delivered
                                        </button>
                                    @elseif(in_array($order->status, ['delivered', 'received'], true))
                                        <div class="w-full p-3 bg-green-50 border border-green-200 rounded-lg text-center">
                                            <div class="inline-flex items-center text-sm font-medium text-green-700">
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                Order {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                            </div>
                                            @if($order->status === 'delivered')
                                                <div class="mt-2 text-xs text-green-600">
                                                    ℹ️ Waiting for customer confirmation
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                    </div>
                </div>
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

