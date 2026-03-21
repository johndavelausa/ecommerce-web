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

    /** Track Package Modal */
    public ?int $trackingOrderId = null;

    public function openTrackingModal(int $orderId): void
    {
        $this->trackingOrderId = $orderId;
    }

    public function closeTrackingModal(): void
    {
        $this->trackingOrderId = null;
    }

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

@push('styles')
@verbatim
<style>
    /* Order Manager Brand Styles */
    .ord-header {
        background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);
        border-radius: 16px;
        padding: 20px 24px;
        border: 1px solid #E8E8E8;
        margin-bottom: 20px;
    }
    .ord-header h2 {
        font-size: 1.375rem;
        font-weight: 700;
        color: #212121;
        margin: 0 0 4px;
    }
    .ord-header p {
        font-size: 0.875rem;
        color: #757575;
        margin: 0;
    }

    .ord-search {
        padding: 10px 16px;
        border: 2px solid #E0E0E0;
        border-radius: 10px;
        font-size: 0.875rem;
        color: #212121;
        background: #fff;
        transition: all 0.15s ease;
        outline: none;
    }
    .ord-search:focus {
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(45,159,78,0.1);
    }
    .ord-select {
        padding: 10px 14px;
        border: 2px solid #E0E0E0;
        border-radius: 10px;
        font-size: 0.875rem;
        color: #212121;
        background: #fff;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .ord-select:focus {
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(45,159,78,0.1);
    }

    .ord-table-wrap {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #E8E8E8;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    .ord-table {
        width: 100%;
        border-collapse: collapse;
    }
    .ord-table th {
        background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);
        padding: 14px 16px;
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 700;
        color: #757575;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        border-bottom: 1px solid #E8E8E8;
    }
    .ord-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #F0F0F0;
        font-size: 0.875rem;
        color: #424242;
    }
    .ord-table tr:hover td {
        background: #FAFAFA;
    }
    .ord-table tr:last-child td {
        border-bottom: none;
    }

    .ord-status {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .ord-status.awaiting_payment { background: #FFF3E0; color: #E65100; }
    .ord-status.paid { background: #E3F2FD; color: #1565C0; }
    .ord-status.to_pack { background: #E1F5FE; color: #0277BD; }
    .ord-status.ready_to_ship { background: #F3E5F5; color: #7B1FA2; }
    .ord-status.processing { background: #FFF9E3; color: #F57C00; }
    .ord-status.shipped { background: #E8EAF6; color: #3949AB; }
    .ord-status.out_for_delivery { background: #EDE7F6; color: #5E35B1; }
    .ord-status.delivered { background: #E8F5E9; color: #2D9F4E; }
    .ord-status.completed { background: #C8E6C9; color: #1B7A37; }
    .ord-status.cancelled { background: #FFEBEE; color: #C0392B; }

    .ord-dispute-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.625rem;
        font-weight: 700;
        margin-top: 6px;
    }
    .ord-dispute-badge.active { background: #FFF9E3; color: #F57C00; }
    .ord-dispute-badge.resolved { background: #F5F5F5; color: #757575; }

    .ord-btn-view {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 2px 8px rgba(45,159,78,0.25);
    }
    .ord-btn-view:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(45,159,78,0.35);
    }

    .ord-empty {
        padding: 48px 24px;
        text-align: center;
        color: #9E9E9E;
        font-size: 0.9375rem;
    }

    /* Modal Styles */
    .ord-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }
    .ord-modal {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        width: 100%;
        max-width: 1000px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .ord-modal-sm {
        max-width: 420px;
    }
    .ord-modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #F0F0F0;
        background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);
    }
    .ord-modal-header h3 {
        font-size: 1.125rem;
        font-weight: 700;
        color: #212121;
        margin: 0 0 4px;
    }
    .ord-modal-header p {
        font-size: 0.8125rem;
        color: #757575;
        margin: 0;
    }
    .ord-modal-close {
        position: absolute;
        top: 16px;
        right: 20px;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        color: #9E9E9E;
        font-size: 1.5rem;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        background: transparent;
    }
    .ord-modal-close:hover {
        background: #F5F5F5;
        color: #616161;
    }
    .ord-modal-body {
        flex: 1;
        overflow: hidden;
        display: flex;
    }
    .ord-modal-content {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
    }
    .ord-modal-sidebar {
        width: 280px;
        border-left: 1px solid #F0F0F0;
        background: #FAFAFA;
        padding: 20px;
        overflow-y: auto;
    }
    .ord-modal-footer {
        padding: 16px 24px;
        border-top: 1px solid #F0F0F0;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: #FAFAFA;
    }

    .ord-section-title {
        font-size: 0.6875rem;
        font-weight: 700;
        color: #757575;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0 0 10px;
    }
    .ord-section-box {
        background: #fff;
        border: 1px solid #E8E8E8;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 16px;
    }
    .ord-section-box p {
        font-size: 0.875rem;
        color: #424242;
        margin: 0;
        line-height: 1.5;
    }

    .ord-item-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #F0F0F0;
    }
    .ord-item-row:last-child {
        border-bottom: none;
    }
    .ord-item-name {
        font-size: 0.9375rem;
        font-weight: 500;
        color: #212121;
    }
    .ord-item-meta {
        font-size: 0.75rem;
        color: #9E9E9E;
        margin-top: 2px;
    }
    .ord-item-price {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #212121;
    }

    .ord-timeline {
        border: 1px solid #E8E8E8;
        border-radius: 12px;
        overflow: hidden;
    }
    .ord-timeline-item {
        padding: 12px 16px;
        border-bottom: 1px solid #F0F0F0;
        background: #fff;
    }
    .ord-timeline-item:last-child {
        border-bottom: none;
    }
    .ord-timeline-status {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #424242;
    }
    .ord-timeline-meta {
        font-size: 0.75rem;
        color: #9E9E9E;
        margin-top: 2px;
    }

    .ord-action-btn {
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 0.8125rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.15s ease;
        margin-bottom: 10px;
    }
    .ord-action-btn.primary {
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: #fff;
        box-shadow: 0 2px 8px rgba(45,159,78,0.25);
    }
    .ord-action-btn.primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(45,159,78,0.35);
    }
    .ord-action-btn.secondary {
        background: linear-gradient(135deg, #F9C74F 0%, #F5A623 100%);
        color: #212121;
        box-shadow: 0 2px 8px rgba(249,199,79,0.25);
    }
    .ord-action-btn.secondary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(249,199,79,0.35);
    }
    .ord-action-btn.danger {
        background: #fff;
        color: #E74C3C;
        border: 2px solid #FFCDD2;
    }
    .ord-action-btn.danger:hover {
        background: #FFEBEE;
    }
    .ord-action-btn.ghost {
        background: #fff;
        color: #616161;
        border: 1px solid #E0E0E0;
    }
    .ord-action-btn.ghost:hover {
        background: #F5F5F5;
    }

    .ord-status-card {
        background: #fff;
        border: 1px solid #E8E8E8;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 16px;
    }
    .ord-status-card .label {
        font-size: 0.6875rem;
        color: #757575;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .ord-status-card .value {
        font-size: 1rem;
        font-weight: 700;
        color: #212121;
        margin-top: 4px;
    }
    .ord-status-card .total {
        font-size: 0.8125rem;
        color: #9E9E9E;
        margin-top: 2px;
    }

    .ord-input {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #E0E0E0;
        border-radius: 10px;
        font-size: 0.875rem;
        color: #212121;
        transition: all 0.15s ease;
        outline: none;
    }
    .ord-input:focus {
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(45,159,78,0.1);
    }
    .ord-label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #424242;
        margin-bottom: 6px;
    }
    .ord-hint {
        font-size: 0.75rem;
        color: #9E9E9E;
        margin-top: 4px;
    }

    .ord-dispute-box {
        border: 1px solid #E8E8E8;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
        background: #fff;
    }
    .ord-dispute-box .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .ord-dispute-box .title {
        font-size: 0.8125rem;
        font-weight: 700;
        color: #424242;
    }
    .ord-dispute-box .desc {
        font-size: 0.8125rem;
        color: #616161;
        line-height: 1.5;
    }

    .ord-transit-box {
        background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%);
        border: 1px solid #90CAF9;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
    }
    .ord-transit-box .title {
        font-size: 0.75rem;
        font-weight: 700;
        color: #1565C0;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .ord-success-box {
        background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
        border: 1px solid #A5D6A7;
        border-radius: 12px;
        padding: 16px;
        text-align: center;
    }
    .ord-success-box .icon {
        color: #2D9F4E;
        margin-bottom: 8px;
    }
    .ord-success-box .text {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #1B7A37;
    }

    .ord-btn-cancel {
        padding: 10px 20px;
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        background: #fff;
        color: #616161;
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }
    .ord-btn-cancel:hover {
        background: #F5F5F5;
    }
    .ord-btn-confirm {
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: #fff;
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
        box-shadow: 0 2px 8px rgba(45,159,78,0.25);
    }
    .ord-btn-confirm:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(45,159,78,0.35);
    }
    .ord-btn-confirm.secondary {
        background: linear-gradient(135deg, #F9C74F 0%, #F5A623 100%);
        color: #212121;
        box-shadow: 0 2px 8px rgba(249,199,79,0.25);
    }
    .ord-btn-confirm.secondary:hover {
        box-shadow: 0 4px 12px rgba(249,199,79,0.35);
    }

    .ord-form-group {
        margin-bottom: 16px;
    }

    /* Pulse ring animation - rings expand outward from current step */
    @keyframes ord-pulse-ring {
        0%   { transform: scale(1);   opacity: 0.75; }
        100% { transform: scale(2.8); opacity: 0;    }
    }
    .ord-pulse-wrap {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .ord-pulse-wrap .ord-ring {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: rgba(249, 199, 79, 0.65);
        pointer-events: none;
        animation: ord-pulse-ring 1s ease-out infinite;
    }
    .ord-pulse-wrap .ord-ring.ord-ring-b {
        animation-delay: 0.5s;
    }
</style>
@endverbatim
@endpush

<div class="space-y-5">
    <div class="ord-header">
        <h2>Orders</h2>
        <p>Manage and track all your customer orders</p>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <div class="flex gap-2 flex-wrap">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search tracking # or customer…"
                   class="ord-search w-64">

            <select wire:model.live="status" class="ord-select">
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

        <div class="text-xs" style="color: #757575;">
            Showing {{ $this->orders->firstItem() ?? 0 }}–{{ $this->orders->lastItem() ?? 0 }}
            of {{ $this->orders->total() }} orders
        </div>
    </div>

    <div class="ord-table-wrap">
        <table class="ord-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Tracking #</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th style="text-align: right;">Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->orders as $order)
                    <tr>
                        <td style="font-size: 0.8125rem; color: #757575;">
                            {{ optional($order->created_at)->format('Y-m-d H:i') }}
                        </td>
                        <td>
                            <span style="font-family: monospace; font-size: 0.8125rem; color: #424242;">
                                {{ $order->tracking_number ?? '—' }}
                            </span>
                            @if($order->courier_name)
                                <div style="font-size: 0.6875rem; color: #9E9E9E; margin-top: 2px;">
                                    {{ \App\Models\Order::COURIERS[$order->courier_name] ?? strtoupper($order->courier_name) }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight: 500; color: #212121;">{{ $order->customer->name ?? 'Guest' }}</div>
                            <div style="font-size: 0.75rem; color: #9E9E9E;">{{ $order->customer->email ?? '' }}</div>
                        </td>
                        <td>
                            @php
                                $activeDispute = $order->disputes->first(function ($d) {
                                    return \App\Models\OrderDispute::isActiveStatus((string) $d->status);
                                });
                            @endphp
                            <span class="ord-status {{ $order->status }}">
                                {{ ucwords(str_replace('_', ' ', $order->status)) }}
                            </span>
                            @if($activeDispute)
                                <div class="ord-dispute-badge active">
                                    Dispute: {{ \App\Models\OrderDispute::statusLabel((string) $activeDispute->status) }}
                                </div>
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: 600; color: #212121;">
                            ₱{{ number_format($order->total_amount, 2) }}
                        </td>
                        <td>
                            <button type="button" wire:click="viewOrder({{ $order->id }})" class="ord-btn-view">
                                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="ord-empty">
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
        <div class="ord-modal-overlay" wire:key="order-modal-{{ $order->id }}-{{ $order->updated_at->timestamp }}">
            <div class="ord-modal">
                <div class="ord-modal-header" style="position: relative;">
                    <div>
                        <h3>Order #{{ $order->id }}</h3>
                        <p>
                            {{ optional($order->created_at)->format('M j, Y g:i A') }} ·
                            Tracking: {{ $order->tracking_number ?? '—' }}
                            @if($order->courier_name)
                                · Courier: {{ \App\Models\Order::COURIERS[$order->courier_name] ?? strtoupper($order->courier_name) }}
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="closeDetails" class="ord-modal-close">&times;</button>
                </div>

                <div class="ord-modal-body">
                    {{-- Left: Order Details --}}
                    <div class="ord-modal-content">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <div class="ord-section-box">
                                    <div class="ord-section-title">Shipping Address</div>
                                    <p>{{ $order->shipping_address }}</p>
                                </div>
                                @if($order->customer_note)
                                    <div class="ord-section-box">
                                        <div class="ord-section-title">Customer Note</div>
                                        <p>{{ $order->customer_note }}</p>
                                    </div>
                                @endif
                            </div>
                            <div>
                                <div class="ord-section-box">
                                    <div class="ord-section-title">Customer</div>
                                    <p>
                                        <strong>{{ $order->customer->name ?? 'Guest' }}</strong><br>
                                        <span style="color: #9E9E9E; font-size: 0.8125rem;">{{ $order->customer->email ?? '' }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="ord-section-title">Order Items</div>
                        <div class="ord-section-box" style="padding: 0; overflow: hidden;">
                            @foreach($order->items as $item)
                                <div class="ord-item-row" style="padding: 12px 16px;">
                                    <div>
                                        <div class="ord-item-name">{{ $item->product->name ?? 'Product #'.$item->product_id }}</div>
                                        <div class="ord-item-meta">Qty: {{ $item->quantity }} × ₱{{ number_format($item->price_at_purchase, 2) }}</div>
                                    </div>
                                    <div class="ord-item-price">₱{{ number_format($item->quantity * $item->price_at_purchase, 2) }}</div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Track Package Section --}}
                        <div class="ord-section-title">Track Package</div>
                        @php
                            $trackingStep = match($order->status) {
                                'awaiting_payment'                       => 1,
                                'paid'                                   => 2,
                                'to_pack', 'ready_to_ship', 'processing' => 3,
                                'shipped', 'out_for_delivery'            => 4,
                                'delivered'                              => 5,
                                'received', 'completed'                  => 6,
                                default                                  => 1,
                            };
                            $trackingSteps = [
                                1 => ['label' => 'Placed', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                                2 => ['label' => 'Confirmed', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                                3 => ['label' => 'Packed', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                                4 => ['label' => 'Shipped', 'icon' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0'],
                                5 => ['label' => 'Delivered', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                                6 => ['label' => 'Received', 'icon' => 'M5 13l4 4L19 7'],
                            ];
                        @endphp

                        {{-- Visual Step Tracker --}}
                        <div class="ord-section-box" style="padding: 20px 16px;">
                            <div style="display: flex; justify-content: space-between; position: relative;">
                                {{-- Progress line --}}
                                <div style="position: absolute; top: 16px; left: 36px; right: 36px; height: 3px; background: #E0E0E0; z-index: 0;">
                                    <div style="width: {{ min(100, max(0, ($trackingStep - 1) / 5 * 100)) }}%; height: 100%; background: linear-gradient(90deg, #2D9F4E, #1B7A37); transition: width 0.3s;"></div>
                                </div>
                                
                                @foreach($trackingSteps as $stepNum => $stepData)
                                    @php
                                        $isCompleted = $stepNum < $trackingStep;
                                        $isCurrent = $stepNum === $trackingStep;
                                    @endphp
                                    <div style="display: flex; flex-direction: column; align-items: center; z-index: 1; flex: 1;">
                                        <div class="{{ $isCurrent ? 'ord-pulse-wrap' : '' }}" style="width: 32px; height: 32px;">
                                            @if($isCurrent)
                                                <div class="ord-ring"></div>
                                                <div class="ord-ring ord-ring-b"></div>
                                            @endif
                                            <div style="
                                                width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
                                                background: {{ $isCompleted ? 'linear-gradient(135deg, #2D9F4E, #1B7A37)' : ($isCurrent ? 'linear-gradient(135deg, #F9C74F, #F5A623)' : '#F5F5F5') }};
                                                border: 3px solid {{ $isCompleted || $isCurrent ? 'transparent' : '#E0E0E0' }};
                                                position: relative; z-index: 1;
                                            ">
                                                <svg style="width: 14px; height: 14px; color: {{ $isCompleted || $isCurrent ? '#fff' : '#9E9E9E' }};" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stepData['icon'] }}"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <span style="
                                            font-size: 0.5625rem; font-weight: 700; margin-top: 6px; text-transform: uppercase;
                                            color: {{ $isCompleted ? '#2D9F4E' : ($isCurrent ? '#F57C00' : '#9E9E9E') }};
                                            letter-spacing: 0.02em;
                                        ">{{ $stepData['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Courier Card --}}
                        @if($order->tracking_number)
                            <div class="ord-section-box" style="background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%); border-color: #FFCC80; margin-bottom: 16px;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #FF6F00; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <svg style="width: 22px; height: 22px; color: #fff;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1"/>
                                        </svg>
                                    </div>
                                    <div style="flex: 1;">
                                        <p style="font-size: 0.9375rem; font-weight: 700; color: #212121; margin: 0;">
                                            {{ \App\Models\Order::COURIERS[$order->courier_name] ?? ucwords(str_replace('_', ' ', $order->courier_name ?: 'Courier')) }}
                                        </p>
                                        <p style="font-size: 0.8125rem; color: #616161; margin: 2px 0 0; font-family: monospace;">{{ $order->tracking_number }}</p>
                                    </div>
                                    <button onclick="navigator.clipboard.writeText('{{ $order->tracking_number }}')" style="width: 32px; height: 32px; border-radius: 8px; background: #fff; border: 1px solid #FFCC80; color: #FF6F00; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Copy tracking number">
                                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- Timeline --}}
                        @php 
                            $timeline = $order->full_tracking_timeline;
                            $latestIssue = $order->disputes->first();
                        @endphp

                        <div class="ord-section-box" style="padding: 0;">
                            {{-- Show delivery issue at top if exists --}}
                            @if($latestIssue)
                                <div style="background: #FFEBEE; border-bottom: 1px solid #FFCDD2; padding: 12px 16px;">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <div style="width: 20px; height: 20px; border-radius: 50%; background: #E53935; display: flex; align-items: center; justify-content: center;">
                                            <svg style="width: 12px; height: 12px; color: #fff;" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <span style="font-size: 0.8125rem; font-weight: 700; color: #C62828;">Delivery Issue Reported</span>
                                    </div>
                                    <p style="font-size: 0.75rem; color: #616161; margin: 0;">{{ $latestIssue->description }}</p>
                                    @if($latestIssue->seller_response_note)
                                        <div style="margin-top: 8px; padding: 8px 12px; background: #E8F5E9; border-radius: 8px; border-left: 3px solid #2D9F4E;">
                                            <p style="font-size: 0.6875rem; font-weight: 700; color: #2D9F4E; margin: 0 0 2px;">YOUR RESPONSE:</p>
                                            <p style="font-size: 0.75rem; color: #424242; margin: 0;">{{ $latestIssue->seller_response_note }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            
                            @forelse($timeline as $index => $step)
                                @php
                                    $occurredAt  = $step['occurred_at'] ? \Illuminate\Support\Carbon::parse($step['occurred_at']) : null;
                                    $isToday     = $occurredAt && $occurredAt->isToday();
                                    $dateLabel   = $occurredAt ? ($isToday ? 'Today' : $occurredAt->format('M j')) : '';
                                    $timeLabel   = $occurredAt ? $occurredAt->format('g:i A') : '';
                                    $isLatest    = $index === 0;
                                @endphp
                                <div style="display: flex; {{ !$loop->last ? 'border-bottom: 1px solid #F5F5F5;' : '' }} padding: 14px 16px;">
                                    {{-- Left: date/time --}}
                                    <div style="width: 56px; flex-shrink: 0; text-align: right; padding-right: 12px;">
                                        <span style="font-size: 0.6875rem; font-weight: 700; color: {{ $isLatest ? '#424242' : '#9E9E9E' }};">{{ $dateLabel }}</span>
                                        <span style="font-size: 0.625rem; color: {{ $isLatest ? '#9E9E9E' : '#BDBDBD' }}; display: block;">{{ $timeLabel }}</span>
                                    </div>

                                    {{-- Centre: dot + line --}}
                                    <div style="display: flex; flex-direction: column; align-items: center; width: 24px; flex-shrink: 0;">
                                        <div style="
                                            width: {{ $isLatest ? '12px' : '8px' }}; height: {{ $isLatest ? '12px' : '8px' }}; border-radius: 50%; flex-shrink: 0;
                                            background: {{ $isLatest ? '#2D9F4E' : '#BDBDBD' }};
                                            border: 2px solid #fff;
                                            box-shadow: 0 0 0 2px {{ $isLatest ? '#2D9F4E' : '#BDBDBD' }};
                                        "></div>
                                        @if(!$loop->last)
                                            <div style="flex: 1; width: 2px; background: #E0E0E0; margin-top: 4px;"></div>
                                        @endif
                                    </div>

                                    {{-- Right: event content --}}
                                    <div style="flex: 1; padding-left: 12px;">
                                        <p style="font-size: 0.8125rem; font-weight: {{ $isLatest ? '600' : '400' }}; color: {{ $isLatest ? '#212121' : '#757575' }}; margin: 0;">
                                            {{ $step['title'] }}
                                        </p>
                                        @if(!empty($step['description']))
                                            <p style="font-size: 0.75rem; color: #9E9E9E; margin: 2px 0 0;">{{ $step['description'] }}</p>
                                        @endif
                                        @if(!empty($step['location']))
                                            <div style="margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: {{ $isLatest ? '#E8F5E9' : '#F5F5F5' }}; border-radius: 6px;">
                                                <svg style="width: 12px; height: 12px; color: {{ $isLatest ? '#2D9F4E' : '#9E9E9E' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                <span style="font-size: 0.6875rem; color: {{ $isLatest ? '#2D9F4E' : '#757575' }};">{{ $step['location'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div style="padding: 24px; text-align: center;">
                                    <div style="width: 48px; height: 48px; border-radius: 50%; background: #F5F5F5; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                                        <svg style="width: 24px; height: 24px; color: #9E9E9E;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                        </svg>
                                    </div>
                                    <p style="font-size: 0.8125rem; font-weight: 600; color: #616161; margin: 0;">No tracking info yet</p>
                                    <p style="font-size: 0.6875rem; color: #9E9E9E; margin: 4px 0 0;">Updates will appear once you ship the order</p>
                                </div>
                            @endforelse
                        </div>

                        <div class="ord-section-title">Refund Audit</div>
                        <div class="ord-section-box">
                            @if($order->refund_status)
                                <p><strong>Status:</strong> {{ \App\Models\Order::refundStatusLabel($order->refund_status) }}</p>
                                <p style="margin-top: 4px;"><strong>Reason:</strong> {{ \App\Models\Order::refundReasonLabel($order->refund_reason_code) }}</p>
                                @if($order->refunded_at)
                                    <p style="margin-top: 4px; color: #9E9E9E; font-size: 0.8125rem;">Refunded at: {{ $order->refunded_at->format('Y-m-d H:i') }}</p>
                                @endif
                            @else
                                <p style="color: #9E9E9E;">No refund audit data for this order.</p>
                            @endif
                        </div>

                        <div class="ord-section-title">Delivery Issues</div>
                        @if($order->disputes->isEmpty())
                            <div class="ord-section-box">
                                <p style="color: #9E9E9E;">No delivery issues reported.</p>
                            </div>
                        @else
                            @foreach($order->disputes as $dispute)
                                <div class="ord-dispute-box">
                                    <div class="header">
                                        <div class="title">Issue Report #{{ $dispute->id }}</div>
                                        <span class="ord-status {{ $dispute->status === 'open' || $dispute->status === 'seller_review' ? 'processing' : 'completed' }}" style="font-size: 0.625rem; padding: 3px 10px;">
                                            {{ \App\Models\OrderDispute::statusLabel($dispute->status) }}
                                        </span>
                                    </div>
                                    <p style="font-size: 0.8125rem; color: #616161; margin: 0 0 8px;">
                                        <strong>Reason:</strong> {{ \App\Models\OrderDispute::REASON_CODES[$dispute->reason_code] ?? ucfirst(str_replace('_', ' ', (string) $dispute->reason_code)) }}
                                    </p>
                                    <p class="desc">{{ $dispute->description }}</p>
                                    @if($dispute->evidence_path)
                                        <a href="{{ asset('storage/' . $dispute->evidence_path) }}" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.8125rem; color: #2D9F4E; text-decoration: none; margin-top: 8px;">
                                            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View buyer evidence
                                        </a>
                                    @endif
                                    @if($dispute->seller_response_note)
                                        <div style="margin-top: 12px; padding: 10px; background: #E8F5E9; border-radius: 8px; font-size: 0.8125rem; color: #1B7A37;">
                                            <strong>Your response:</strong> {{ $dispute->seller_response_note }}
                                            @if($dispute->seller_responded_at)
                                                <div style="font-size: 0.6875rem; color: #9E9E9E; margin-top: 4px;">Responded at {{ optional($dispute->seller_responded_at)->format('Y-m-d H:i') }}</div>
                                            @endif
                                        </div>
                                    @endif
                                    @if(in_array($dispute->status, [\App\Models\OrderDispute::STATUS_OPEN, \App\Models\OrderDispute::STATUS_SELLER_REVIEW], true))
                                        @if($dispute->reason_code === 'parcel_not_received' && !$dispute->seller_response_note)
                                            <div class="ord-transit-box" style="margin-top: 12px; background: linear-gradient(135deg, #FFF9E3 0%, #FFECB3 100%); border-color: #F9C74F;">
                                                <div class="title" style="color: #F57C00;">
                                                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                    </svg>
                                                    Customer reported: Item not received
                                                </div>
                                                <p style="font-size: 0.8125rem; color: #616161; margin-bottom: 10px;">Provide explanation about what happened:</p>
                                                <select wire:model.defer="sellerExplanationCodes.{{ $dispute->id }}" class="ord-input" style="margin-bottom: 8px; font-size: 0.8125rem;">
                                                    <option value="">Select reason...</option>
                                                    @foreach(\App\Models\OrderDispute::SELLER_EXPLANATION_CODES as $code => $label)
                                                        <option value="{{ $code }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @error("sellerExplanationCodes.{$dispute->id}") <p style="font-size: 0.75rem; color: #E53935; margin: 4px 0;">{{ $message }}</p> @enderror
                                                <textarea wire:model.defer="sellerExplanationNotes.{{ $dispute->id }}" placeholder="Additional details..." class="ord-input" rows="2" style="margin-bottom: 10px; font-size: 0.8125rem;"></textarea>
                                                <button type="button" wire:click="respondToNonReceipt({{ $dispute->id }}, 'no_refund')" class="ord-action-btn primary" style="margin-bottom: 0;">
                                                    Acknowledge & Respond
                                                </button>
                                            </div>
                                        @else
                                            <button type="button" wire:click="openSellerDisputeModal({{ $dispute->id }})" class="ord-btn-view" style="margin-top: 10px;">
                                                {{ $dispute->seller_response_note ? 'Update Response' : 'Submit Response' }}
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>

                    {{-- Right: Order Actions Sidebar --}}
                    <div class="ord-modal-sidebar">
                        <div class="ord-status-card">
                            <div class="label">Current Status</div>
                            <div class="value">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</div>
                            <div class="total">Total: ₱{{ number_format($order->total_amount, 2) }}</div>
                        </div>

                        <a href="{{ route('seller.orders.print', $order->id) }}" target="_blank" class="ord-action-btn ghost">
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Print Slip
                        </a>

                        @if(in_array($order->status, ['awaiting_payment', 'paid'], true))
                            <button type="button" wire:click="updateStatus({{ $order->id }}, 'to_pack')" class="ord-action-btn primary">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Accept Order
                            </button>
                            <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')" class="ord-action-btn danger">
                                Cancel Order
                            </button>
                        @elseif($order->status === 'to_pack')
                            <button type="button" wire:click="updateStatus({{ $order->id }}, 'ready_to_ship')" class="ord-action-btn secondary">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                Mark as Packed
                            </button>
                            <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')" class="ord-action-btn danger">
                                Cancel Order
                            </button>
                        @elseif(in_array($order->status, ['ready_to_ship', 'processing'], true))
                            <button type="button" wire:click="openMarkShippedModal({{ $order->id }})" class="ord-action-btn primary">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                                </svg>
                                Mark as Shipped
                            </button>
                            <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')" class="ord-action-btn danger">
                                Cancel Order
                            </button>
                        @elseif($order->status === 'shipped')
                            <div class="ord-transit-box">
                                <div class="title">
                                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Add Transit Update
                                </div>
                                <input type="text" wire:model.defer="transitLocation" class="ord-input" style="margin-bottom: 8px; font-size: 0.8125rem;" placeholder="Location (e.g. Manila Hub)">
                                @error('transitLocation') <p style="font-size: 0.75rem; color: #E53935; margin: 0 0 6px;">{{ $message }}</p> @enderror
                                <input type="text" wire:model.defer="transitNote" class="ord-input" style="margin-bottom: 10px; font-size: 0.8125rem;" placeholder="Note (optional)">
                                @error('transitNote') <p style="font-size: 0.75rem; color: #E53935; margin: 0 0 6px;">{{ $message }}</p> @enderror
                                <button type="button" wire:click="addTransitUpdate({{ $order->id }})" class="ord-action-btn primary" style="margin-bottom: 0; font-size: 0.75rem;">
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Add Location Update
                                </button>
                            </div>
                            <button type="button" wire:click="updateStatus({{ $order->id }}, 'out_for_delivery')" class="ord-action-btn secondary">
                                <svg style="width: 16px; height: 16px;" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                    <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z"/>
                                </svg>
                                Mark Out for Delivery
                            </button>
                        @elseif($order->status === 'out_for_delivery')
                            <button type="button" wire:click="updateStatus({{ $order->id }}, 'delivered')" class="ord-action-btn primary">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Mark as Delivered
                            </button>
                        @elseif(in_array($order->status, ['delivered', 'received'], true))
                            <div class="ord-success-box">
                                <svg class="icon" style="width: 32px; height: 32px; margin: 0 auto 8px;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <div class="text">Order {{ ucfirst(str_replace('_', ' ', $order->status)) }}</div>
                                @if($order->status === 'delivered')
                                    <div style="font-size: 0.75rem; color: #757575; margin-top: 8px;">Waiting for customer confirmation</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="ord-modal-footer">
                    <button type="button" wire:click="closeDetails" class="ord-btn-cancel">Close</button>
                </div>
            </div>
        </div>
        @endif
    @endif

    {{-- Mark shipped modal --}}
    @if($showMarkShippedModal)
        <div class="ord-modal-overlay" style="z-index: 60;">
            <div class="ord-modal ord-modal-sm">
                <div class="ord-modal-header">
                    <h3>Mark Order as Shipped</h3>
                    <p>Set courier info. Tracking number can be auto-generated or manually entered.</p>
                </div>
                <div style="padding: 20px 24px;">
                    <div class="ord-form-group">
                        <label class="ord-label">Courier</label>
                        <select wire:model.defer="markShippedCourier" class="ord-input">
                            @foreach(\App\Models\Order::COURIERS as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('markShippedCourier') <p style="font-size: 0.75rem; color: #E53935; margin-top: 4px;">{{ $message }}</p> @enderror
                    </div>

                    <div class="ord-form-group">
                        <label class="ord-label">Manual Tracking Number <span style="color: #9E9E9E;">(optional)</span></label>
                        <input type="text" wire:model.defer="markShippedManualTracking" class="ord-input" placeholder="Leave blank to auto-generate">
                        <p class="ord-hint">Allowed: letters, numbers, hyphen.</p>
                        @error('markShippedManualTracking') <p style="font-size: 0.75rem; color: #E53935; margin-top: 4px;">{{ $message }}</p> @enderror
                    </div>

                    <div class="ord-form-group">
                        <label class="ord-label">Estimated Delivery Date <span style="color: #9E9E9E;">(optional)</span></label>
                        <input type="date" wire:model.defer="markShippedEstimatedDate" class="ord-input" min="{{ now()->format('Y-m-d') }}">
                        @error('markShippedEstimatedDate') <p style="font-size: 0.75rem; color: #E53935; margin-top: 4px;">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="ord-modal-footer">
                    <button type="button" wire:click="closeMarkShippedModal" class="ord-btn-cancel">Cancel</button>
                    <button type="button" wire:click="confirmMarkShipped" class="ord-btn-confirm">Mark Shipped</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Tracking Modal --}}
    @if($trackingOrderId)
        @php
            $trackOrder = \App\Models\Order::with(['statusHistory', 'trackingEvents', 'disputes'])->find($trackingOrderId);
        @endphp
        @if($trackOrder)
            @php
                $trackingStep = match($trackOrder->status) {
                    'awaiting_payment'                       => 1,
                    'paid'                                   => 2,
                    'to_pack', 'ready_to_ship', 'processing' => 3,
                    'shipped', 'out_for_delivery'            => 4,
                    'delivered'                              => 5,
                    'received', 'completed'                  => 6,
                    default                                  => 1,
                };
                $isCancelled = $trackOrder->status === 'cancelled';
                $trackingSteps = [
                    1 => ['label' => 'Placed', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                    2 => ['label' => 'Confirmed', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    3 => ['label' => 'Packed', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                    4 => ['label' => 'Shipped', 'icon' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0'],
                    5 => ['label' => 'Delivered', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    6 => ['label' => 'Received', 'icon' => 'M5 13l4 4L19 7'],
                ];
            @endphp
            <div class="ord-modal-overlay" style="z-index: 80;" wire:key="tracking-modal-{{ $trackingOrderId }}">
                <div class="ord-modal" style="max-width: 520px;">
                    <div class="ord-modal-header" style="position: relative;">
                        <h3>Track Package</h3>
                        <p>Order #{{ $trackOrder->id }} · {{ $trackOrder->tracking_number ?? 'No tracking number' }}</p>
                        <button type="button" wire:click="closeTrackingModal" class="ord-modal-close">&times;</button>
                    </div>
                    <div style="padding: 0; overflow-y: auto; max-height: 70vh;">
                        
                        {{-- Visual Step Tracker --}}
                        <div style="background: #fff; padding: 20px 24px; border-bottom: 1px solid #F0F0F0;">
                            <div style="display: flex; justify-content: space-between; position: relative;">
                                {{-- Progress line --}}
                                <div style="position: absolute; top: 20px; left: 40px; right: 40px; height: 2px; background: #E0E0E0; z-index: 0;">
                                    <div style="width: {{ min(100, max(0, ($trackingStep - 1) / 5 * 100)) }}%; height: 100%; background: linear-gradient(90deg, #2D9F4E, #1B7A37); transition: width 0.3s;"></div>
                                </div>
                                
                                @foreach($trackingSteps as $stepNum => $stepData)
                                    @php
                                        $isCompleted = $stepNum < $trackingStep;
                                        $isCurrent = $stepNum === $trackingStep;
                                        $isPending = $stepNum > $trackingStep;
                                    @endphp
                                    <div style="display: flex; flex-direction: column; align-items: center; z-index: 1; flex: 1;">
                                        <div class="{{ $isCurrent ? 'ord-pulse-wrap' : '' }}" style="width: 40px; height: 40px;">
                                            @if($isCurrent)
                                                <div class="ord-ring"></div>
                                                <div class="ord-ring ord-ring-b"></div>
                                            @endif
                                            <div style="
                                                width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
                                                background: {{ $isCompleted ? 'linear-gradient(135deg, #2D9F4E, #1B7A37)' : ($isCurrent ? 'linear-gradient(135deg, #F9C74F, #F5A623)' : '#F5F5F5') }};
                                                border: 3px solid {{ $isCompleted || $isCurrent ? 'transparent' : '#E0E0E0' }};
                                                position: relative; z-index: 1;
                                            ">
                                                <svg style="width: 18px; height: 18px; color: {{ $isCompleted || $isCurrent ? '#fff' : '#9E9E9E' }};" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stepData['icon'] }}"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <span style="
                                            font-size: 0.625rem; font-weight: 700; margin-top: 8px; text-transform: uppercase;
                                            color: {{ $isCompleted ? '#2D9F4E' : ($isCurrent ? '#F57C00' : '#9E9E9E') }};
                                            letter-spacing: 0.03em;
                                        ">{{ $stepData['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Courier Card --}}
                        @if($trackOrder->tracking_number)
                            <div style="margin: 16px; padding: 16px; background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%); border: 1px solid #FFCC80; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                                <div style="width: 48px; height: 48px; border-radius: 12px; background: #FF6F00; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <svg style="width: 24px; height: 24px; color: #fff;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1"/>
                                    </svg>
                                </div>
                                <div style="flex: 1;">
                                    <p style="font-size: 0.9375rem; font-weight: 700; color: #212121; margin: 0;">
                                        {{ \App\Models\Order::COURIERS[$trackOrder->courier_name] ?? ucwords(str_replace('_', ' ', $trackOrder->courier_name ?: 'Courier')) }}
                                    </p>
                                    <p style="font-size: 0.8125rem; color: #616161; margin: 2px 0 0; font-family: monospace;">{{ $trackOrder->tracking_number }}</p>
                                </div>
                                <button onclick="navigator.clipboard.writeText('{{ $trackOrder->tracking_number }}')" style="width: 32px; height: 32px; border-radius: 8px; background: #fff; border: 1px solid #FFCC80; color: #FF6F00; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Copy tracking number">
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </button>
                            </div>
                        @endif

                        {{-- Timeline --}}
                        @php 
                            $timeline = $trackOrder->full_tracking_timeline;
                            $latestIssue = $trackOrder->disputes->first();
                        @endphp

                        <div style="padding: 0 16px 16px;">
                            {{-- Show delivery issue at top if exists --}}
                            @if($latestIssue)
                                <div style="background: #FFEBEE; border: 1px solid #FFCDD2; border-radius: 12px; padding: 12px 16px; margin-bottom: 12px;">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <div style="width: 20px; height: 20px; border-radius: 50%; background: #E53935; display: flex; align-items: center; justify-content: center;">
                                            <svg style="width: 12px; height: 12px; color: #fff;" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <span style="font-size: 0.8125rem; font-weight: 700; color: #C62828;">Delivery Issue Reported</span>
                                    </div>
                                    <p style="font-size: 0.75rem; color: #616161; margin: 0;">{{ $latestIssue->description }}</p>
                                    @if($latestIssue->seller_response_note)
                                        <div style="margin-top: 8px; padding: 8px 12px; background: #E8F5E9; border-radius: 8px; border-left: 3px solid #2D9F4E;">
                                            <p style="font-size: 0.6875rem; font-weight: 700; color: #2D9F4E; margin: 0 0 2px;">YOUR RESPONSE:</p>
                                            <p style="font-size: 0.75rem; color: #424242; margin: 0;">{{ $latestIssue->seller_response_note }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            
                            @forelse($timeline as $index => $step)
                                @php
                                    $occurredAt  = $step['occurred_at'] ? \Illuminate\Support\Carbon::parse($step['occurred_at']) : null;
                                    $isToday     = $occurredAt && $occurredAt->isToday();
                                    $dateLabel   = $occurredAt ? ($isToday ? 'Today' : $occurredAt->format('M j')) : '';
                                    $timeLabel   = $occurredAt ? $occurredAt->format('g:i A') : '';
                                    $isLatest    = $index === 0;
                                @endphp
                                <div style="display: flex; {{ !$loop->last ? 'border-bottom: 1px solid #F5F5F5;' : '' }} padding: 14px 0;">
                                    {{-- Left: date/time --}}
                                    <div style="width: 56px; flex-shrink: 0; text-align: right; padding-right: 12px;">
                                        <span style="font-size: 0.6875rem; font-weight: 700; color: {{ $isLatest ? '#424242' : '#9E9E9E' }};">{{ $dateLabel }}</span>
                                        <span style="font-size: 0.625rem; color: {{ $isLatest ? '#9E9E9E' : '#BDBDBD' }}; display: block;">{{ $timeLabel }}</span>
                                    </div>

                                    {{-- Centre: dot + line --}}
                                    <div style="display: flex; flex-direction: column; align-items: center; width: 24px; flex-shrink: 0;">
                                        <div style="
                                            width: {{ $isLatest ? '12px' : '8px' }}; height: {{ $isLatest ? '12px' : '8px' }}; border-radius: 50%; flex-shrink: 0;
                                            background: {{ $isLatest ? '#2D9F4E' : '#BDBDBD' }};
                                            border: 2px solid #fff;
                                            box-shadow: 0 0 0 2px {{ $isLatest ? '#2D9F4E' : '#BDBDBD' }};
                                        "></div>
                                        @if(!$loop->last)
                                            <div style="flex: 1; width: 2px; background: #E0E0E0; margin-top: 4px;"></div>
                                        @endif
                                    </div>

                                    {{-- Right: event content --}}
                                    <div style="flex: 1; padding-left: 12px;">
                                        <p style="font-size: 0.8125rem; font-weight: {{ $isLatest ? '600' : '400' }}; color: {{ $isLatest ? '#212121' : '#757575' }}; margin: 0;">
                                            {{ $step['title'] }}
                                        </p>
                                        @if(!empty($step['description']))
                                            <p style="font-size: 0.75rem; color: #9E9E9E; margin: 2px 0 0;">{{ $step['description'] }}</p>
                                        @endif
                                        @if(!empty($step['location']))
                                            <div style="margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: {{ $isLatest ? '#E8F5E9' : '#F5F5F5' }}; border-radius: 6px;">
                                                <svg style="width: 12px; height: 12px; color: {{ $isLatest ? '#2D9F4E' : '#9E9E9E' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                <span style="font-size: 0.6875rem; color: {{ $isLatest ? '#2D9F4E' : '#757575' }};">{{ $step['location'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div style="padding: 32px; text-align: center;">
                                    <div style="width: 56px; height: 56px; border-radius: 50%; background: #F5F5F5; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                                        <svg style="width: 28px; height: 28px; color: #9E9E9E;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                        </svg>
                                    </div>
                                    <p style="font-size: 0.875rem; font-weight: 600; color: #616161; margin: 0;">No tracking info yet</p>
                                    <p style="font-size: 0.75rem; color: #9E9E9E; margin: 4px 0 0;">Updates will appear here once you ship the order</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                    <div class="ord-modal-footer">
                        <button type="button" wire:click="closeTrackingModal" class="ord-btn-cancel">Close</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>

