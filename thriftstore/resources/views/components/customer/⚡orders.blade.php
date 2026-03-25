<?php

use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\Product;
use App\Models\Review;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\OrderDisputeUpdated;
use App\Notifications\OrderCancelledByBuyerNotification;
use App\Notifications\NewDisputeRaised;
use App\Notifications\ReviewReceivedNotification;
use App\Notifications\OrderReceivedByBuyerNotification;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $status = '';

    protected $queryString = [
        'status' => ['except' => ''],
    ];

    public ?int $issueOrderId = null;
    public string $issueReason = 'item_not_as_described';
    public string $issueBody = '';
    public $issueEvidence = null;

    public ?int $rateOrderId = null;
    public int $storeRating = 5;
    public string $storeReview = '';

    public ?int $trackingOrderId = null;

    public ?int $rateProductsOrderId = null;
    public array $productRatings = [];
    public array $productReviews = [];

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
            ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
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
            'issueReason'   => ['required', 'string', 'in:item_not_as_described,damaged_item,wrong_item,missing_items,parcel_not_received,other'],
            'issueBody'     => ['required', 'string', 'max:2000'],
            'issueEvidence' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        $order = Order::query()
            ->with('seller')
            ->where('customer_id', $customer->id)
            ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
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
            'order_id'      => $order->id,
            'customer_id'   => $customer->id,
            'seller_id'     => $order->seller_id,
            'reason_code'   => $this->issueReason,
            'description'   => trim($this->issueBody),
            'evidence_path' => $evidencePath,
            'status'        => OrderDispute::STATUS_OPEN,
        ]);

        $order->recordStatusHistory(null, 'dispute_opened', null, 'The customer has requested a return/refund for this order.');

        $sellerUser = $order->seller->user;
        if ($sellerUser) {
            $sellerUser->notify(new OrderDisputeUpdated($dispute, 'opened'));
        }

        // Notify all admins about new dispute
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new NewDisputeRaised($dispute));
        }

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

    public string $returnTrackingNumber = '';

    public function submitReturnTracking(int $disputeId): void
    {
        $this->validate([
            'returnTrackingNumber' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'returnTrackingNumber.regex' => 'Tracking number may only contain letters, numbers, and hyphens.',
        ]);

        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return;
        }

        $dispute = OrderDispute::query()
            ->where('customer_id', $customer->id)
            ->where('status', OrderDispute::STATUS_RETURN_REQUESTED)
            ->findOrFail($disputeId);

        $dispute->return_tracking_number = trim($this->returnTrackingNumber);
        $dispute->status = OrderDispute::STATUS_RETURN_IN_TRANSIT;
        $dispute->save();

        if ($dispute->order) {
            $dispute->order->recordStatusHistory(null, 'dispute_return_shipped', null, 'The customer has shipped the return item via courier (Tracking: ' . $dispute->return_tracking_number . ').');
        }

        $sellerUser = $dispute->seller?->user;
        if ($sellerUser) {
            $sellerUser->notify(new OrderDisputeUpdated($dispute, 'return_in_transit'));
        }

        $this->returnTrackingNumber = '';
        $this->resetErrorBag('returnTrackingNumber');
    }

    public function openRateModal(int $orderId): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return;
        }

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['delivered', 'received', 'completed'])
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
            ->whereIn('status', ['delivered', 'received', 'completed'])
            ->whereNull('store_rating')
            ->findOrFail($this->rateOrderId);

        $order->store_rating = $this->storeRating;
        $order->store_review = $this->storeReview !== '' ? $this->storeReview : null;
        $order->save();

        // Notify seller about store review
        $sellerUser = $order->seller?->user;
        if ($sellerUser) {
            $sellerUser->notify(new ReviewReceivedNotification($order));
        }

        $this->closeRateModal();
    }

    public function reorder(int $id): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) abort(403);

        $order = Order::query()
            ->with('items.product.seller')
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['delivered', 'received', 'completed'])
            ->findOrFail($id);

        $cart = Session::get('cart', []);
        $maxCartItems = 50;
        $itemsAdded = 0;
        $itemsSkipped = 0;
        $debugInfo = [];

        foreach ($order->items as $item) {
            if (count($cart) >= $maxCartItems) {
                $debugInfo[] = 'Cart full';
                break;
            }

            $product = $item->product;
            if (! $product) {
                $itemsSkipped++;
                $debugInfo[] = "Item {$item->id}: Product not found";
                continue;
            }
            if (! $product->is_active) {
                $itemsSkipped++;
                $debugInfo[] = "Product {$product->id}: Not active";
                continue;
            }
            if ($product->stock <= 0) {
                $itemsSkipped++;
                $debugInfo[] = "Product {$product->id}: Out of stock (stock: {$product->stock})";
                continue;
            }

            $seller = $product->seller;
            if (! $seller) {
                $itemsSkipped++;
                $debugInfo[] = "Product {$product->id}: No seller";
                continue;
            }
            if ($seller->status !== 'approved') {
                $itemsSkipped++;
                $debugInfo[] = "Product {$product->id}: Seller not approved (status: {$seller->status})";
                continue;
            }
            if (! $seller->is_open) {
                $itemsSkipped++;
                $debugInfo[] = "Product {$product->id}: Shop closed";
                continue;
            }

            $key = (string) $product->id;
            $currentQty = $cart[$key]['quantity'] ?? 0;
            $desiredQty = $currentQty + $item->quantity;
            $finalQty = min($desiredQty, $product->stock);
            if ($finalQty <= 0) {
                $itemsSkipped++;
                $debugInfo[] = "Product {$product->id}: Final qty <= 0";
                continue;
            }

            if (! isset($cart[$key]) && count($cart) >= $maxCartItems) {
                $debugInfo[] = 'Cart would exceed max';
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
            $itemsAdded++;
            $debugInfo[] = "Product {$product->id}: Added to cart";
        }

        Session::put('cart', $cart);
        $count = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cart));
        $this->dispatch('cart-updated', count: $count);
        $this->dispatch('show-reorder-toast', itemsAdded: $itemsAdded, itemsSkipped: $itemsSkipped, debug: $debugInfo);
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
                'statusHistory',
            ])
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        $statusGroups = [
            'to_ship'    => ['paid', 'to_pack', 'ready_to_ship', 'processing'],
            'in_transit' => ['shipped', 'out_for_delivery'],
            'completed'  => ['delivered', 'received', 'completed'],
        ];

        if ($this->status !== '') {
            if (isset($statusGroups[$this->status])) {
                $q->whereIn('status', $statusGroups[$this->status]);
            } else {
                $q->where('status', $this->status);
            }
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

        // Customer can only mark as received when order is delivered
        if (! $order->canTransitionTo(\App\Models\Order::STATUS_RECEIVED, 'customer')) {
            return;
        }

        $order->status = \App\Models\Order::STATUS_RECEIVED;
        $order->received_at = now();
        $order->save();

        // Notify seller about buyer marking as received
        $sellerUser = $order->seller?->user;
        if ($sellerUser) {
            $sellerUser->notify(new OrderReceivedByBuyerNotification($order));
        }
    }

    public function reportNotReceived(int $id): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            abort(403);
        }

        $order = Order::query()
            ->with('seller.user')
            ->where('customer_id', $customer->id)
            ->whereIn('status', [Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_DELIVERED])
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
            'order_id'    => $order->id,
            'customer_id' => $customer->id,
            'seller_id'   => $order->seller_id,
            'reason_code' => 'parcel_not_received',
            'description' => 'Customer reported that the parcel was not received while the order is marked out for delivery.',
            'status'      => OrderDispute::STATUS_OPEN,
        ]);

        $order->recordStatusHistory(null, 'dispute_opened', null, 'The customer has reported that the parcel was not received.');

        $sellerUser = $order->seller->user;
        if ($sellerUser) {
            $sellerUser->notify(new OrderDisputeUpdated($dispute, 'opened'));
        }

        // Notify all admins about new non-receipt dispute
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new NewDisputeRaised($dispute));
        }

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

        $recentNotReceivedCount = OrderDispute::query()
            ->where('customer_id', $customer->id)
            ->where('reason_code', 'parcel_not_received')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentNotReceivedCount >= 3) {
            $customer->update([
                'is_suspicious'          => true,
                'suspicious_reason'      => 'Repeated parcel-not-received claims in 30 days',
                'suspicious_flagged_at'  => now(),
            ]);
        }
    }

    public function disputeProgressMeta(?OrderDispute $dispute): array
    {
        if (! $dispute) {
            return [
                'label'      => 'No dispute',
                'step'       => 0,
                'isResolved' => false,
            ];
        }

        // Check if seller has responded
        $hasSellerResponse = !empty($dispute->seller_response_note);
        
        $map = [
            OrderDispute::STATUS_OPEN                => [$hasSellerResponse ? 'Seller responded' : 'Dispute submitted', $hasSellerResponse ? 2 : 1, false],
            OrderDispute::STATUS_SELLER_REVIEW       => ['Seller responded', 2, false],
            OrderDispute::STATUS_RETURN_REQUESTED    => ['Return requested', 2, false],
            OrderDispute::STATUS_RETURN_IN_TRANSIT   => ['Return in transit', 2, false],
            OrderDispute::STATUS_RETURN_RECEIVED     => ['Return received', 2, false],
            OrderDispute::STATUS_REFUND_PENDING      => ['Refund pending', 2, false],
            OrderDispute::STATUS_REFUND_COMPLETED    => ['Refund completed', 3, true],
            OrderDispute::STATUS_CLOSED              => ['Resolved', 3, true],
        ];

        [$label, $step, $isResolved] = $map[$dispute->status] ?? ['Dispute updated', 2, false];

        return [
            'label'      => $label,
            'step'       => $step,
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

        // Notify seller about buyer cancellation
        $sellerUser = $order->seller?->user;
        if ($sellerUser) {
            $sellerUser->notify(new OrderCancelledByBuyerNotification($order));
        }
    }

    public function openRateProductsModal(int $orderId): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return;
        }

        $order = Order::query()
            ->with(['items.product'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['delivered', 'received', 'completed'])
            ->findOrFail($orderId);

        $this->rateProductsOrderId = $order->id;
        $this->productRatings = [];
        $this->productReviews = [];

        foreach ($order->items as $item) {
            if ($item->product) {
                $this->productRatings[$item->product_id] = 5;
                $this->productReviews[$item->product_id] = '';
            }
        }

        $this->resetErrorBag();
    }

    public function closeRateProductsModal(): void
    {
        $this->rateProductsOrderId = null;
        $this->productRatings = [];
        $this->productReviews = [];
        $this->resetErrorBag();
    }

    public function submitProductRatings(): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer || ! $this->rateProductsOrderId) {
            return;
        }

        $order = Order::query()
            ->with(['items.product'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['delivered', 'received', 'completed'])
            ->findOrFail($this->rateProductsOrderId);

        $rules = [];
        foreach ($this->productRatings as $productId => $rating) {
            $rules["productRatings.{$productId}"] = ['required', 'integer', 'min:1', 'max:5'];
        }
        foreach ($this->productReviews as $productId => $review) {
            $rules["productReviews.{$productId}"] = ['nullable', 'string', 'max:2000'];
        }
        $this->validate($rules);

        foreach ($order->items as $item) {
            $productId = $item->product_id;
            if (! $item->product || ! isset($this->productRatings[$productId])) {
                continue;
            }

            $alreadyReviewed = Review::where('customer_id', $customer->id)
                ->where('product_id', $productId)
                ->where('order_id', $order->id)
                ->exists();

            if ($alreadyReviewed) {
                continue;
            }

            Review::create([
                'customer_id' => $customer->id,
                'product_id'  => $productId,
                'order_id'    => $order->id,
                'rating'      => (int) $this->productRatings[$productId],
                'body'        => trim($this->productReviews[$productId] ?? ''),
            ]);

            // Notify seller about product review
            $sellerUser = $item->product?->seller?->user;
            if ($sellerUser) {
                $sellerUser->notify(new ReviewReceivedNotification(null, Review::where('customer_id', $customer->id)->where('product_id', $productId)->where('order_id', $order->id)->latest()->first()));
            }
        }

        $this->closeRateProductsModal();
    }

    public function openTrackingModal(int $orderId): void
    {
        $this->trackingOrderId = $orderId;
    }

    public function closeTrackingModal(): void
    {
        $this->trackingOrderId = null;
    }
};
?>

<style>
    /* ── Orders pagination ──────────────────────────────── */
    .orders-pag nav {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        padding: 8px 0 !important;
    }
    /* hide the "Showing X to Y" text */
    .orders-pag nav p { display: none !important; }
    /* wrapper divs — reset any framework defaults */
    .orders-pag nav > div { background: none !important; box-shadow: none !important; border-radius: 0 !important; padding: 0 !important; display: flex !important; align-items: center !important; gap: 4px !important; }
    .orders-pag nav > div > div { background: none !important; box-shadow: none !important; border-radius: 0 !important; padding: 0 !important; display: flex !important; align-items: center !important; gap: 4px !important; }
    /* prev/next and numbered links */
    .orders-pag nav a,
    .orders-pag nav button {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 34px !important;
        height: 34px !important;
        padding: 0 10px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        color: #424242 !important;
        background: #FFFFFF !important;
        border: 1px solid #E0E0E0 !important;
        cursor: pointer !important;
        text-decoration: none !important;
        transition: background 0.15s, color 0.15s, border-color 0.15s !important;
    }
    .orders-pag nav a:hover,
    .orders-pag nav button:hover {
        background: #E8F5E9 !important;
        color: #2D9F4E !important;
        border-color: #2D9F4E !important;
    }
    /* active page */
    .orders-pag nav [aria-current="page"] > span {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 34px !important;
        height: 34px !important;
        padding: 0 10px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        background: #2D9F4E !important;
        color: #FFFFFF !important;
        border: 1px solid #2D9F4E !important;
    }
    /* disabled prev/next */
    .orders-pag nav [aria-disabled="true"] > span {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 34px !important;
        height: 34px !important;
        padding: 0 10px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        background: #F5F5F5 !important;
        color: #BDBDBD !important;
        border: 1px solid #E0E0E0 !important;
        cursor: not-allowed !important;
    }
    /* mobile simple links */
    .orders-pag nav .flex.justify-between { display: none !important; }
</style>

<div class="min-h-screen bg-[#F8F9FA]">

    {{-- Tab navigation --}}
    <div class="bg-white sticky top-0 z-20" style="border-bottom: 1px solid #F5F5F5; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <div class="flex overflow-x-auto" style="scrollbar-width:none;-ms-overflow-style:none;">
            @php
            $tabs = [
                ''           => 'All',
                'awaiting_payment' => 'To Pay',
                'to_ship'    => 'To Ship',
                'in_transit' => 'Shipping',
                'completed'  => 'Completed',
                'cancelled'  => 'Cancelled',
            ];
            @endphp
            @foreach($tabs as $tabVal => $tabLabel)
                <button wire:click="$set('status', '{{ $tabVal }}')"
                        class="flex-shrink-0 px-5 py-3.5 text-sm font-medium whitespace-nowrap transition-colors"
                        style="
                            border-bottom: 3px solid {{ $status === $tabVal ? '#F9C74F' : 'transparent' }};
                            color: {{ $status === $tabVal ? '#2D9F4E' : '#9E9E9E' }};
                            background: #FFFFFF;
                        "
                        onmouseover="if('{{ $status }}' !== '{{ $tabVal }}') { this.style.color='#2D9F4E'; }"
                        onmouseout="if('{{ $status }}' !== '{{ $tabVal }}') { this.style.color='#9E9E9E'; }">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Orders list --}}
    <div class="max-w-2xl mx-auto px-3 sm:px-4 py-4 space-y-3">

        @php $orders = $this->orders; @endphp

        @if ($orders instanceof \Illuminate\Pagination\LengthAwarePaginator && $orders->count())

            @foreach ($orders as $order)
                @php
                    $badgeText = match($order->status) {
                        'awaiting_payment' => 'WAITING FOR PAYMENT',
                        'paid'             => 'PAYMENT CONFIRMED',
                        'to_pack'          => 'PREPARING ORDER',
                        'ready_to_ship'    => 'READY TO SHIP',
                        'processing'       => 'PROCESSING',
                        'shipped'          => 'SHIPPED',
                        'out_for_delivery' => 'OUT FOR DELIVERY',
                        'delivered'        => 'ORDER DELIVERED',
                        'received'         => 'ORDER RECEIVED',
                        'completed'        => 'COMPLETED',
                        'cancelled'        => 'CANCELLED',
                        default            => strtoupper(str_replace('_', ' ', $order->status)),
                    };
                    $badgeClass = match($order->status) {
                        'awaiting_payment'                    => 'text-[#FFA726] bg-[#FFF3E0] border border-[#FFA726]',
                        'paid'                               => 'text-[#42A5F5] bg-[#E3F2FD] border border-[#42A5F5]',
                        'to_pack', 'ready_to_ship',
                        'processing'                         => 'text-[#42A5F5] bg-[#E3F2FD] border border-[#42A5F5]',
                        'shipped'                            => 'text-[#42A5F5] bg-[#E3F2FD] border border-[#42A5F5]',
                        'out_for_delivery'                   => 'text-[#42A5F5] bg-[#E3F2FD] border border-[#42A5F5]',
                        'delivered'                          => 'text-[#2D9F4E] bg-[#E8F5E9] border border-[#2D9F4E]',
                        'received'                           => 'text-[#2D9F4E] bg-[#E8F5E9] border border-[#2D9F4E]',
                        'completed'                          => 'text-[#2D9F4E] bg-[#E8F5E9] border border-[#2D9F4E]',
                        'cancelled'                          => 'text-[#EF5350] bg-[#FFEBEE] border border-[#EF5350]',
                        default                              => 'text-gray-600 bg-gray-100 border border-gray-300',
                    };
                @endphp

                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-[#F5F5F5]">

                    {{-- Store header --}}
                    <div class="px-4 py-3 flex items-center justify-between border-b border-[#F5F5F5]">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0" style="background:#FF6F00">
                                <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"/>
                                    <path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-[#212121]">
                                {{ $order->seller->store_name ?? 'Seller #'.$order->seller_id }}
                            </span>
                        </div>
                        <span class="text-[11px] font-bold tracking-wide px-2 py-0.5 rounded-full {{ $badgeClass }}">{{ $badgeText }}</span>
                    </div>

                    {{-- Product items --}}
                    @foreach ($order->items as $item)
                        <div class="px-4 py-3 flex items-start gap-3 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                            @if($item->product?->image_path)
                                <img src="{{ asset('storage/'.$item->product->image_path) }}"
                                     alt="{{ $item->product->name }}"
                                     class="w-16 h-16 object-cover rounded-lg flex-shrink-0 border border-gray-100">
                            @else
                                <div class="w-16 h-16 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="line-clamp-2 leading-snug" style="font-size:14px; color:#212121;">
                                    {{ $item->product->name ?? 'Product #'.$item->product_id }}
                                </p>
                                <p class="mt-1" style="font-size:13px; color:#9E9E9E;">Qty: {{ $item->quantity }}</p>
                            </div>
                            <p class="font-semibold flex-shrink-0" style="font-size:14px; color:#212121;">
                                ₱{{ number_format($item->price_at_purchase * $item->quantity, 2) }}
                            </p>
                        </div>
                    @endforeach

                    {{-- Order total --}}
                    <div class="px-4 py-2.5 border-t border-[#E0E0E0] flex items-center justify-end gap-2" style="background:#FAFAFA;">
                        <span class="text-xs" style="color:#9E9E9E;">
                            Order Total ({{ $order->items->sum('quantity') }} {{ \Illuminate\Support\Str::plural('item', $order->items->sum('quantity')) }}):
                        </span>
                        <span class="font-bold" style="font-size:16px; color:#F57C00; font-weight:500;">₱{{ number_format($order->total_amount, 2) }}</span>
                    </div>

                    {{-- Courier / tracking summary --}}
                    @if(in_array($order->status, ['shipped', 'out_for_delivery', 'delivered', 'received', 'completed']) && $order->tracking_number)
                        <div class="mx-4 mb-2 mt-1 px-3.5 py-2.5 rounded-xl flex items-center justify-between gap-3" style="background:#FFF3E0; border:1px solid #FFE0B2;">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#FF6F00;">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest" style="color:#F57C00;">
                                        {{ \App\Models\Order::COURIERS[$order->courier_name] ?? strtoupper($order->courier_name ?: 'Courier') }}
                                    </p>
                                    <p class="text-xs font-mono font-semibold" style="color:#212121;">{{ $order->tracking_number }}</p>
                                </div>
                            </div>
                            @if($order->estimated_delivery_date)
                                <div class="text-right">
                                    <p class="text-[10px] text-gray-400">Est. Delivery</p>
                                    <p class="text-xs font-bold text-gray-700">{{ $order->estimated_delivery_date->format('M j, Y') }}</p>
                                </div>
                            @endif
                        </div>
                    @endif


                    {{-- Cancellation info --}}
                    @if($order->status === 'cancelled')
                        <div class="mx-4 mb-2 mt-1 px-3.5 py-2 rounded-xl bg-gray-50 border border-gray-100">
                            <p class="text-xs text-gray-500">
                                Cancelled by <span class="font-semibold text-gray-700">{{ ucfirst($order->cancelled_by_type ?? 'system') }}</span>
                                @if($order->cancellation_reason_code)
                                    · {{ \App\Models\Order::CANCELLATION_REASONS[$order->cancellation_reason_code] ?? ucfirst(str_replace('_', ' ', $order->cancellation_reason_code)) }}
                                @endif
                            </p>
                            @if($order->refund_status && $order->refund_status !== \App\Models\Order::REFUND_STATUS_NOT_REQUIRED)
                                <p class="text-xs text-[#2D9F4E] mt-0.5">
                                    Refund: {{ \App\Models\Order::refundStatusLabel($order->refund_status) }}
                                </p>
                            @endif
                        </div>
                    @endif

                    {{-- Store rating (if rated) --}}
                    @if($order->store_rating)
                        <div class="mx-4 mb-2 mt-1 flex items-center gap-2 px-3.5 py-2 rounded-xl" style="background:#FFFEF5; border:1px solid #FFF3CD;">
                            <div class="flex items-center gap-0.5">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-3.5 h-3.5" style="color: {{ $i <= $order->store_rating ? '#F9C74F' : '#E0E0E0' }};" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endfor
                            </div>
                            @if($order->store_review)
                                <p class="text-[11px] line-clamp-1" style="color:#F57C00;">{{ $order->store_review }}</p>
                            @else
                                <p class="text-[11px] font-medium" style="color:#F57C00;">Seller rated {{ $order->store_rating }}/5</p>
                            @endif
                        </div>
                    @endif

                    {{-- Action buttons --}}
                    <div class="px-4 py-3 border-t border-[#E0E0E0] flex items-center justify-end gap-2 flex-wrap">
                        <button type="button"
                                wire:click="openTrackingModal({{ $order->id }})"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold transition" style="background:#FFFFFF; border:1px solid #E0E0E0; color:#424242;" onmouseover="this.style.background='#F5F5F5';this.style.borderColor='#2D9F4E';" onmouseout="this.style.background='#FFFFFF';this.style.borderColor='#E0E0E0';">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            Track Package
                        </button>

                        @if (in_array($order->status, ['awaiting_payment', 'paid', 'to_pack', 'ready_to_ship', 'processing'], true))
                            <button type="button"
                                    wire:click="cancel({{ $order->id }})"
                                    class="inline-flex items-center px-3.5 py-2 rounded-lg text-xs font-semibold transition" style="background:#FFFFFF; border:1px solid #E0E0E0; color:#424242;" onmouseover="this.style.background='#FFEBEE';this.style.borderColor='#EF5350';this.style.color='#EF5350';" onmouseout="this.style.background='#FFFFFF';this.style.borderColor='#E0E0E0';this.style.color='#424242';">
                                Cancel Order
                            </button>

                        @elseif ($order->status === 'delivered')
                            @php
                                $hasActiveIssue = $order->disputes()
                                    ->whereIn('status', \App\Models\OrderDispute::ACTIVE_STATUSES)
                                    ->exists();
                            @endphp
                            
                            @if($hasActiveIssue)
                                <div class="inline-flex items-center px-3.5 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs font-medium text-amber-700">
                                    <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Delivery issue reported - Check tracking for seller response
                                </div>
                            @else
                                <button type="button"
                                        wire:click="reportNotReceived({{ $order->id }})"
                                        wire:confirm="Report that you didn't receive this parcel? Seller will be notified."
                                        class="inline-flex items-center px-3.5 py-2 border border-red-300 rounded-lg text-xs font-semibold text-red-600 hover:bg-red-50 transition">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    Item Didn't Receive
                                </button>
                                <button type="button"
                                        wire:click="markReceived({{ $order->id }})"
                                        class="inline-flex items-center px-4 py-2 bg-[#2D9F4E] text-white rounded-lg text-xs font-bold hover:bg-[#1B7A37] transition shadow-sm">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Order Received
                                </button>
                            @endif

                        @elseif (in_array($order->status, ['received', 'completed'], true))
                            <a href="{{ route('customer.orders.receipt', $order) }}"
                               class="inline-flex items-center px-3.5 py-2 rounded-lg text-xs font-semibold transition" style="background:#FFFFFF; border:1px solid #E0E0E0; color:#424242;" onmouseover="this.style.background='#F5F5F5';this.style.borderColor='#2D9F4E';" onmouseout="this.style.background='#FFFFFF';this.style.borderColor='#E0E0E0';"
                               target="_blank" rel="noopener">
                                Receipt
                            </a>
                            <button type="button"
                                    wire:click="openIssueModal({{ $order->id }})"
                                    class="inline-flex items-center px-3.5 py-2 rounded-lg text-xs font-semibold transition" style="background:#FFFFFF; border:1px solid #EF5350; color:#EF5350;" onmouseover="this.style.background='#FFEBEE';" onmouseout="this.style.background='#FFFFFF';">
                                Return / Issue
                            </button>
                            <button type="button"
                                    wire:click="reorder({{ $order->id }})"
                                    class="inline-flex items-center px-3.5 py-2 rounded-lg text-xs font-semibold transition" style="background:#FFFFFF; border:1px solid #2D9F4E; color:#2D9F4E;" onmouseover="this.style.background='#E8F5E9';this.style.color='#1B7A37';" onmouseout="this.style.background='#FFFFFF';this.style.color='#2D9F4E';">
                                Re-order
                            </button>
                            @php
                                $reviewedProductIds = \App\Models\Review::where('customer_id', Auth::guard('web')->id())
                                    ->where('order_id', $order->id)
                                    ->pluck('product_id')
                                    ->all();
                                $unreviewedItems = $order->items->filter(fn($i) => $i->product && !in_array($i->product_id, $reviewedProductIds));
                            @endphp
                            @if ($unreviewedItems->isNotEmpty())
                                <button type="button"
                                        wire:click="openRateProductsModal({{ $order->id }})"
                                        class="inline-flex items-center gap-1 px-4 py-2 bg-[#F9C74F] text-[#212121] rounded-lg text-xs font-bold hover:bg-[#FFE17B] transition shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    Rate Products
                                </button>
                            @endif
                            @if (!$order->store_rating)
                                <button type="button"
                                        wire:click="openRateModal({{ $order->id }})"
                                        class="inline-flex items-center gap-1 px-4 py-2 bg-[#F9C74F] text-[#212121] rounded-lg text-xs font-bold hover:bg-[#FFE17B] transition shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    Rate Seller
                                </button>
                            @endif
                        @endif
                    </div>

                </div>
            @endforeach

            {{-- Pagination --}}
            <div class="py-2 orders-pag">
                {{ $orders->links() }}
            </div>

        @else
            <div class="py-20 flex flex-col items-center text-center">
                <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <p class="text-gray-600 font-semibold text-sm">No orders yet</p>
                <p class="text-gray-400 text-xs mt-1">Orders you place will appear here</p>
            </div>
        @endif

    </div>

    {{-- ══════════════════════════════════════════════════════════════
         ISSUE / RETURN MODAL
    ══════════════════════════════════════════════════════════════ --}}
    @if($issueOrderId)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900">Return / Issue Request</h3>
                    <button wire:click="closeIssueModal" class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200 text-lg leading-none">×</button>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Describe the issue with your order. This message will be sent to the seller for review.
                    </p>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Reason</label>
                        <select wire:model.defer="issueReason"
                                class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-[#F9C74F] focus:ring-[#F9C74F]">
                            @foreach(\App\Models\OrderDispute::REASON_CODES as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('issueReason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Description</label>
                        <textarea wire:model.defer="issueBody" rows="4"
                                  class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-[#F9C74F] focus:ring-[#F9C74F]"
                                  placeholder="Describe the issue (e.g. the shirt has a tear on the sleeve)…"></textarea>
                        @error('issueBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Evidence <span class="text-gray-400 font-normal">(optional photo or PDF)</span></label>
                        <input type="file" wire:model="issueEvidence"
                               class="block w-full text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#E8F5E9] file:text-[#2D9F4E] hover:file:bg-[#d4edda]"
                               accept=".jpg,.jpeg,.png,.webp,.pdf">
                        @error('issueEvidence') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-gray-50 flex gap-2 justify-end">
                    <button wire:click="closeIssueModal"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-100 transition">
                        Cancel
                    </button>
                    <button wire:click="submitIssue"
                            class="px-4 py-2 bg-[#2D9F4E] text-white rounded-lg text-xs font-bold hover:bg-[#1B7A37] transition shadow-sm">
                        Submit Request
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         RATE SELLER MODAL
    ══════════════════════════════════════════════════════════════ --}}
    @if($rateOrderId)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900">Rate this Seller</h3>
                    <button wire:click="closeRateModal" class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200 text-lg leading-none">×</button>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-xs text-gray-500">Rate your overall store experience — communication, packaging, and delivery speed.</p>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-2">Your Rating</label>
                        <div class="flex items-center gap-2">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button"
                                        wire:click="$set('storeRating', {{ $i }})"
                                        class="transition-transform hover:scale-110">
                                    <svg class="w-8 h-8 {{ $storeRating >= $i ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                </button>
                            @endfor
                            <span class="text-sm font-bold text-amber-500 ml-1">{{ $storeRating }}/5</span>
                        </div>
                        @error('storeRating') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Comment <span class="text-gray-400 font-normal">(optional)</span></label>
                        <textarea wire:model.defer="storeReview" rows="3"
                                  class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-[#F9C74F] focus:ring-[#F9C74F]"
                                  placeholder="Share your experience with this seller…"></textarea>
                        @error('storeReview') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-gray-50 flex gap-2 justify-end">
                    <button wire:click="closeRateModal"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-100 transition">
                        Cancel
                    </button>
                    <button wire:click="submitRating"
                            class="px-4 py-2 bg-[#F9C74F] text-[#212121] rounded-lg text-xs font-bold hover:bg-[#FFE17B] transition shadow-sm">
                        Submit Rating
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         RATE PRODUCTS MODAL
    ══════════════════════════════════════════════════════════════ --}}
    @if($rateProductsOrderId)
        @php
            $rateOrder = \App\Models\Order::with(['items.product'])->find($rateProductsOrderId);
            $alreadyReviewedIds = $rateOrder
                ? \App\Models\Review::where('customer_id', Auth::guard('web')->id())
                    ->where('order_id', $rateOrder->id)
                    ->pluck('product_id')
                    ->all()
                : [];
            $itemsToRate = $rateOrder ? $rateOrder->items->filter(fn($i) => $i->product && !in_array($i->product_id, $alreadyReviewedIds)) : collect();
        @endphp
        @if($rateOrder && $itemsToRate->isNotEmpty())
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col" style="max-height: min(90vh, 640px);">
                <div class="px-6 py-4 border-b flex items-center justify-between flex-shrink-0">
                    <h3 class="text-sm font-bold text-gray-900">Rate Products</h3>
                    <button wire:click="closeRateProductsModal" class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200 text-lg leading-none">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    @foreach($itemsToRate as $item)
                        @php $pid = $item->product_id; @endphp
                        <div class="pb-5 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="flex items-start gap-3 mb-3">
                                @if($item->product->image_path)
                                    <img src="{{ asset('storage/'.$item->product->image_path) }}"
                                         class="w-14 h-14 rounded-lg object-cover border border-gray-100 flex-shrink-0">
                                @else
                                    <div class="w-14 h-14 rounded-lg bg-gray-100 flex-shrink-0"></div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 line-clamp-2">{{ $item->product->name }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Qty: {{ $item->quantity }}</p>
                                </div>
                            </div>
                            <div class="mb-2">
                                <p class="text-xs font-semibold text-gray-600 mb-1.5">Your Rating</p>
                                <div class="flex items-center gap-1.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        <button type="button"
                                                wire:click="$set('productRatings.{{ $pid }}', {{ $i }})"
                                                class="transition-transform hover:scale-110 focus:outline-none">
                                            <svg class="w-7 h-7 {{ isset($productRatings[$pid]) && $productRatings[$pid] >= $i ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        </button>
                                    @endfor
                                    <span class="text-sm font-bold text-amber-500 ml-1">{{ $productRatings[$pid] ?? 5 }}/5</span>
                                </div>
                                @error("productRatings.{$pid}") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <textarea wire:model.defer="productReviews.{{ $pid }}" rows="2"
                                          class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-[#F9C74F] focus:ring-[#F9C74F]"
                                          placeholder="Share your thoughts about this item (optional)…"></textarea>
                                @error("productReviews.{$pid}") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="px-6 py-4 border-t bg-gray-50 flex gap-2 justify-end flex-shrink-0">
                    <button wire:click="closeRateProductsModal"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-100 transition">
                        Cancel
                    </button>
                    <button wire:click="submitProductRatings"
                            class="px-4 py-2 bg-[#F9C74F] text-[#212121] rounded-lg text-xs font-bold hover:bg-[#FFE17B] transition shadow-sm">
                        Submit Reviews
                    </button>
                </div>
            </div>
        </div>
        @endif
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         SHOPEE-STYLE TRACKING MODAL
    ══════════════════════════════════════════════════════════════ --}}
    @if($trackingOrderId)
        @php
            $orderObj = \App\Models\Order::with(['statusHistory', 'trackingEvents'])->find($trackingOrderId);
        @endphp

        @if($orderObj)
            @php
                $trackingStep = match($orderObj->status) {
                    'awaiting_payment'                       => 1,
                    'paid'                                   => 2,
                    'to_pack', 'ready_to_ship', 'processing' => 3,
                    'shipped', 'out_for_delivery'            => 4,
                    'delivered'                              => 5,
                    'received', 'completed'                  => 6,
                    default                                  => 1,
                };
                $isCancelled = $orderObj->status === 'cancelled';
                $trackingSteps = [
                    1 => ['label' => 'Placed'],
                    2 => ['label' => 'Confirmed'],
                    3 => ['label' => 'Packed'],
                    4 => ['label' => 'Shipped'],
                    5 => ['label' => 'Delivered'],
                    6 => ['label' => 'Received'],
                ];
            @endphp

            <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm"
                 x-data
                 x-on:keydown.escape.window="$wire.closeTrackingModal()">
                <div class="bg-gray-100 rounded-t-3xl sm:rounded-2xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden"
                     style="max-height: min(94vh, 720px);">

                    {{-- Header --}}
                    <div class="bg-white flex items-center gap-3 px-4 py-3.5 flex-shrink-0 border-b border-gray-100">
                        <button wire:click="closeTrackingModal"
                                class="w-8 h-8 flex items-center justify-center text-gray-600 hover:text-gray-900 transition -ml-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <h3 class="flex-1 text-center text-base font-bold text-gray-900 -ml-8">Track package</h3>
                        <div class="w-8 h-8 flex items-center justify-center text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Scrollable body --}}
                    <div class="flex-1 overflow-y-auto">

                        {{-- Estimated delivery + cancel banner --}}
                        @if($isCancelled)
                            <div class="bg-white mx-3 mt-3 rounded-xl px-4 py-3 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-red-600">Order Cancelled</p>
                                    @if($orderObj->cancelled_at)
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $orderObj->cancelled_at->format('M j, Y · g:i A') }}</p>
                                    @endif
                                </div>
                            </div>
                        @elseif($orderObj->estimated_delivery_date)
                            <div class="bg-white px-4 py-3 flex-shrink-0">
                                <p class="text-sm text-gray-700">
                                    Estimated delivery:
                                    <span class="font-bold text-teal-600">
                                        {{ $orderObj->estimated_delivery_date->subDays(2)->format('M j') }}
                                        –
                                        {{ $orderObj->estimated_delivery_date->format('M j') }}
                                    </span>
                                </p>
                            </div>
                        @endif

                        {{-- Courier card --}}
                        @if($orderObj->tracking_number)
                            <div class="bg-white mx-3 mt-3 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm">
                                <div class="w-10 h-10 rounded-lg bg-red-600 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-gray-900">
                                        {{ \App\Models\Order::COURIERS[$orderObj->courier_name] ?? ucwords(str_replace('_', ' ', $orderObj->courier_name ?: 'Courier')) }}
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">Standard shipping</p>
                                    <p class="text-xs font-mono text-gray-600 mt-0.5">{{ $orderObj->tracking_number }}</p>
                                </div>
                                <button onclick="navigator.clipboard.writeText('{{ $orderObj->tracking_number }}')"
                                        class="w-7 h-7 rounded-md bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition flex-shrink-0"
                                        title="Copy tracking number">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </button>
                            </div>
                        @endif

                        {{-- Timeline --}}
                        @php 
                            $timeline = $orderObj->full_tracking_timeline;
                            $latestIssue = $orderObj->disputes->first();
                        @endphp

                        <div class="bg-white mx-3 mt-3 mb-4 rounded-xl overflow-hidden shadow-sm">
                            {{-- Dispute / Return Status Panel --}}
                            @if($latestIssue)
                                <div class="px-4 pt-4 pb-3 border-b border-gray-100">
                                    {{-- Header row --}}
                                    <div class="flex items-center gap-2 mb-3">
                                        <div class="w-6 h-6 rounded-full bg-red-500 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-bold text-red-700">Return / Issue Submitted</p>
                                            <p class="text-[11px] text-gray-400 mt-0.5">
                                                {{ \App\Models\OrderDispute::REASON_CODES[$latestIssue->reason_code] ?? ucfirst(str_replace('_', ' ', $latestIssue->reason_code)) }}
                                                · {{ $latestIssue->created_at->format('M j, g:i A') }}
                                            </p>
                                        </div>
                                        <span class="text-[10px] font-bold px-2 py-1 rounded-full
                                            {{ in_array($latestIssue->status, ['refund_completed','closed']) ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' }}">
                                            {{ \App\Models\OrderDispute::statusLabel($latestIssue->status) }}
                                        </span>
                                    </div>

                                    {{-- Description --}}
                                    <p class="text-xs text-gray-600 leading-relaxed mb-2">{{ $latestIssue->description }}</p>

                                    {{-- Evidence --}}
                                    @if($latestIssue->evidence_path)
                                        <a href="{{ asset('storage/' . $latestIssue->evidence_path) }}" target="_blank"
                                           class="inline-flex items-center gap-1 text-xs text-blue-600 underline mb-2">
                                            View your evidence
                                        </a>
                                    @endif

                                    {{-- ── Status-specific panels ── --}}

                                    @if($latestIssue->seller_response_note)
                                        <div class="mt-2 px-3 py-3 bg-green-50 rounded-lg border-l-4 border-green-500 mb-2 shadow-sm">
                                            <p class="text-[10px] font-bold text-green-700 uppercase tracking-wider mb-1">Seller's Response:</p>
                                            <p class="text-xs text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $latestIssue->seller_response_note }}</p>
                                        </div>
                                    @endif

                                    {{-- open: waiting for seller --}}
                                    @if($latestIssue->status === \App\Models\OrderDispute::STATUS_OPEN && !$latestIssue->seller_response_note)
                                        <div class="mt-2 px-3 py-2 bg-yellow-50 rounded-lg border border-yellow-200">
                                            <p class="text-xs text-yellow-800">⏳ Waiting for seller to respond to your request...</p>
                                        </div>

                                    {{-- seller_review: seller responded, choosing resolution --}}
                                    @elseif($latestIssue->status === \App\Models\OrderDispute::STATUS_SELLER_REVIEW)
                                        <div class="mt-2 px-3 py-2 bg-yellow-50 rounded-lg border border-yellow-200">
                                            <p class="text-xs text-yellow-800">⏳ Seller is reviewing your request and choosing a resolution...</p>
                                        </div>

                                    {{-- return_requested: customer must ship item back --}}
                                    @elseif($latestIssue->status === \App\Models\OrderDispute::STATUS_RETURN_REQUESTED)
                                        <div class="mt-3 px-3 py-3 bg-orange-50 rounded-lg border border-orange-200">
                                            <p class="text-xs font-bold text-orange-800 mb-1">📦 Courier Pickup: Prepare your item</p>
                                            <p class="text-xs text-gray-600">The seller has approved your return. Please prepare the items; a courier is coming to your house to collect the parcel. You will be notified once the item is picked up.</p>
                                        </div>

                                    {{-- return_in_transit: waiting for seller to confirm receipt --}}
                                    @elseif($latestIssue->status === \App\Models\OrderDispute::STATUS_RETURN_IN_TRANSIT)
                                        <div class="mt-2 px-3 py-2 bg-yellow-50 rounded-lg border border-yellow-200 text-center">
                                            <p class="text-xs text-yellow-800 font-bold">🚚 Item has been picked up by courier</p>
                                            <p class="text-[10px] text-yellow-700 mt-1">The item is on its way back to the seller. You will be notified once it is received.</p>
                                        </div>

                                    {{-- refund_pending: seller processing refund --}}
                                    @elseif($latestIssue->status === \App\Models\OrderDispute::STATUS_REFUND_PENDING)
                                        <div class="mt-2 px-3 py-2 bg-yellow-50 rounded-lg border border-yellow-200">
                                            <p class="text-xs font-bold text-yellow-800 mb-1">💳 Refund in progress</p>
                                            <p class="text-xs text-yellow-700">The seller is processing your refund. You will be notified once it is sent.</p>
                                        </div>

                                    {{-- refund_completed: done --}}
                                    @elseif($latestIssue->status === \App\Models\OrderDispute::STATUS_REFUND_COMPLETED)
                                        <div class="mt-2 px-3 py-2 bg-green-50 rounded-lg border border-green-200 text-center">
                                            <p class="text-sm font-bold text-green-700">✅ Refund Completed</p>
                                            <p class="text-xs text-green-600 mt-1">The seller has sent your refund. If you have not received it, please contact the seller.</p>
                                        </div>

                                    {{-- closed --}}
                                    @elseif($latestIssue->status === \App\Models\OrderDispute::STATUS_CLOSED)
                                        <div class="mt-2 px-3 py-2 bg-gray-50 rounded-lg border border-gray-200 text-center">
                                            <p class="text-sm font-bold text-gray-600">Dispute Closed</p>
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
                                <div class="flex items-stretch px-4 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">

                                    {{-- Left: date/time --}}
                                    <div class="w-16 flex-shrink-0 flex flex-col justify-start pt-4 pr-2 text-right">
                                        <span class="text-xs font-semibold {{ $isLatest ? 'text-gray-800' : 'text-gray-400' }}">
                                            {{ $dateLabel }}
                                        </span>
                                        <span class="text-[11px] {{ $isLatest ? 'text-gray-500' : 'text-gray-400' }} mt-0.5">
                                            {{ $timeLabel }}
                                        </span>
                                    </div>

                                    {{-- Centre: dot + line --}}
                                    <div class="flex flex-col items-center w-8 flex-shrink-0">
                                        <div class="mt-4 flex-shrink-0">
                                            @if($isLatest)
                                                <div class="w-5 h-5 rounded-full bg-teal-500 flex items-center justify-center shadow-sm">
                                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </div>
                                            @else
                                                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 bg-white"></div>
                                            @endif
                                        </div>
                                        @if(!$loop->last)
                                            <div class="flex-1 w-px bg-gray-200 mt-1.5 mb-0 min-h-[24px]"></div>
                                        @endif
                                    </div>

                                    {{-- Right: event content --}}
                                    <div class="flex-1 min-w-0 py-4 pl-2">
                                        <p class="text-sm leading-snug {{ $isLatest ? 'font-bold text-gray-900' : 'text-gray-500' }}">
                                            {{ $step['title'] }}
                                        </p>
                                        @if(!empty($step['description']))
                                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ $step['description'] }}</p>
                                        @endif
                                        @if(!empty($step['location']) && $isLatest)
                                            <div class="mt-2 px-3 py-2 bg-teal-50 rounded-lg border border-teal-100">
                                                <p class="text-xs text-teal-600 leading-relaxed">{{ $step['location'] }}</p>
                                            </div>
                                        @elseif(!empty($step['location']))
                                            <p class="text-xs text-gray-400 mt-1">{{ $step['location'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="py-12 flex flex-col items-center text-center">
                                    <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                                        <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-500">No tracking info yet</p>
                                    <p class="text-xs text-gray-400 mt-1">Updates will appear here once the seller ships your order</p>
                                </div>
                            @endforelse
                        </div>

                    </div>{{-- end scrollable body --}}

                </div>
            </div>
        @endif
    @endif

</div>

<script>
function initReorderToast() {
    if (typeof Livewire === 'undefined' || typeof Swal === 'undefined') {
        return;
    }
    
    // Use the custom Toast configuration from sweetalert.blade.php
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: '#2d6c50',
        color: '#ffffff',
        iconColor: '#ffffff',
        width: 'auto',
        customClass: {
            popup: '!p-3 !py-2 rounded-xl shadow-lg mt-14 mr-4',
            title: '!text-sm !font-semibold !mt-0 !mb-0 text-white',
            timerProgressBar: '!bg-green-300'
        },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    
    Livewire.on('show-reorder-toast', (eventData) => {
        const itemsAdded = eventData.itemsAdded || 0;
        const itemsSkipped = eventData.itemsSkipped || 0;
        
        let title, icon;
        if (itemsAdded > 0 && itemsSkipped === 0) {
            title = `${itemsAdded} item${itemsAdded > 1 ? 's' : ''} added to cart`;
            icon = 'success';
        } else if (itemsAdded > 0 && itemsSkipped > 0) {
            title = `${itemsAdded} added, ${itemsSkipped} unavailable`;
            icon = 'warning';
        } else {
            title = 'All items unavailable';
            icon = 'error';
        }
        
        Toast.fire({
            icon: icon,
            title: title
        });
    });
}

// Try to initialize immediately
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReorderToast);
} else {
    initReorderToast();
}

// Also try after Livewire is ready
document.addEventListener('livewire:initialized', initReorderToast);
</script>
