<?php

use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function seller()
    {
        return Auth::guard('seller')->user()?->seller;
    }

    #[Computed]
    public function slaMetrics(): array
    {
        $seller = $this->seller;
        if (! $seller) {
            return [
                'acceptance_rate' => 0.0,
                'accepted_orders' => 0,
                'acceptance_scope' => 0,
                'on_time_ship_rate' => 0.0,
                'on_time_shipments' => 0,
                'shipment_scope' => 0,
                'cancellation_rate' => 0.0,
                'cancelled_orders' => 0,
                'cancellation_scope' => 0,
                'return_rate' => 0.0,
                'returned_orders' => 0,
                'return_scope' => 0,
            ];
        }

        $orderBase = Order::query()->where('seller_id', $seller->id);

        $acceptanceScope = (clone $orderBase)
            ->whereIn('status', [
                Order::STATUS_PAID,
                Order::STATUS_TO_PACK,
                Order::STATUS_READY_TO_SHIP,
                Order::STATUS_PROCESSING,
                Order::STATUS_SHIPPED,
                Order::STATUS_OUT_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
                Order::STATUS_CANCELLED,
            ])
            ->count();

        $acceptedOrders = (clone $orderBase)
            ->whereIn('status', [
                Order::STATUS_SHIPPED,
                Order::STATUS_OUT_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->count();

        $shipmentScope = (clone $orderBase)
            ->whereIn('status', [
                Order::STATUS_SHIPPED,
                Order::STATUS_OUT_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->count();

        $onTimeShipments = (clone $orderBase)
            ->whereIn('status', [
                Order::STATUS_SHIPPED,
                Order::STATUS_OUT_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->whereNotNull('shipped_at')
            ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, shipped_at) <= 48')
            ->count();

        $cancellationScope = (clone $orderBase)
            ->whereIn('status', [
                Order::STATUS_PAID,
                Order::STATUS_TO_PACK,
                Order::STATUS_READY_TO_SHIP,
                Order::STATUS_PROCESSING,
                Order::STATUS_SHIPPED,
                Order::STATUS_OUT_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
                Order::STATUS_CANCELLED,
            ])
            ->count();

        $cancelledOrders = (clone $orderBase)
            ->where('status', Order::STATUS_CANCELLED)
            ->count();

        $returnScope = (clone $orderBase)
            ->whereIn('status', [
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->count();

        $returnedOrders = (int) OrderDispute::query()
            ->where('seller_id', $seller->id)
            ->whereIn('status', [
                OrderDispute::STATUS_RETURN_REQUESTED,
                OrderDispute::STATUS_RETURN_IN_TRANSIT,
                OrderDispute::STATUS_RETURN_RECEIVED,
                OrderDispute::STATUS_REFUND_PENDING,
                OrderDispute::STATUS_REFUND_COMPLETED,
            ])
            ->distinct('order_id')
            ->count('order_id');

        return [
            'acceptance_rate' => $acceptanceScope > 0 ? round(($acceptedOrders / $acceptanceScope) * 100, 1) : 0.0,
            'accepted_orders' => $acceptedOrders,
            'acceptance_scope' => $acceptanceScope,
            'on_time_ship_rate' => $shipmentScope > 0 ? round(($onTimeShipments / $shipmentScope) * 100, 1) : 0.0,
            'on_time_shipments' => $onTimeShipments,
            'shipment_scope' => $shipmentScope,
            'cancellation_rate' => $cancellationScope > 0 ? round(($cancelledOrders / $cancellationScope) * 100, 1) : 0.0,
            'cancelled_orders' => $cancelledOrders,
            'cancellation_scope' => $cancellationScope,
            'return_rate' => $returnScope > 0 ? round(($returnedOrders / $returnScope) * 100, 1) : 0.0,
            'returned_orders' => $returnedOrders,
            'return_scope' => $returnScope,
        ];
    }

    /** B4 v1.4 — Subscription renewal history (month-by-month timeline) */
    #[Computed]
    public function subscriptionPayments()
    {
        $seller = $this->seller;
        if (! $seller) {
            return collect();
        }
        return Payment::query()
            ->where('seller_id', $seller->id)
            ->where('type', 'subscription')
            ->orderByDesc('created_at')
            ->limit(24)
            ->get();
    }

    /** B4 v1.4 — Days remaining until subscription_due_date; null if no due date */
    #[Computed]
    public function subscriptionDaysRemaining(): ?int
    {
        $seller = $this->seller;
        if (! $seller || ! $seller->subscription_due_date) {
            return null;
        }
        $due = \Illuminate\Support\Carbon::parse($seller->subscription_due_date)->startOfDay();

        return (int) now()->startOfDay()->diffInDays($due, false);
    }

    #[Computed]
    public function stats()
    {
        $seller = $this->seller;
        if (! $seller) {
            return [
                'products_total' => 0,
                'products_active' => 0,
                'stock_total' => 0,
                'low_stock_count' => 0,
                'out_of_stock_count' => 0,
                'orders_total' => 0,
                'orders_processing' => 0,
                'orders_shipped' => 0,
                'orders_delivered' => 0,
                'orders_cancelled' => 0,
                'orders_ready_to_ship' => 0,
                'bad_orders_count' => 0,
                'bad_orders_percent' => 0.0,
                'earnings_total' => 0,
                'earnings_month' => 0,
                'net_profit' => 0,
                'store_rating_avg' => 0.0,
                'store_reviews_count' => 0,
            ];
        }

        $productQuery = Product::query()->where('seller_id', $seller->id);

        $productsTotal = (clone $productQuery)->count();
        $productsActive = (clone $productQuery)->where('is_active', true)->count();
        $stockTotal = (clone $productQuery)->sum('stock');
        $lowStockCount = (clone $productQuery)->where('stock', '<', 10)->count();
        $outOfStockCount = (clone $productQuery)->where('stock', 0)->count();

        $orderBase = Order::query()
            ->where('seller_id', $seller->id);

        $ordersTotal = (clone $orderBase)->count();
        $ordersProcessing = (clone $orderBase)
            ->whereIn('status', [
                Order::STATUS_PAID,
                Order::STATUS_TO_PACK,
                Order::STATUS_READY_TO_SHIP,
                Order::STATUS_PROCESSING,
            ])
            ->count();
        $ordersShipped = (clone $orderBase)->where('status', 'shipped')->count();
        $ordersDelivered = (clone $orderBase)->where('status', 'delivered')->count();
        $ordersCancelled = (clone $orderBase)->where('status', 'cancelled')->count();
        $ordersReadyToShip = (clone $orderBase)
            ->whereIn('status', [Order::STATUS_READY_TO_SHIP, Order::STATUS_PROCESSING])
            ->count();

        // Bad Orders: cancelled + reported problematic (v1.2 - Seller #7); for now cancelled only
        $badOrdersCount = $ordersCancelled;
        $badOrdersPercent = $ordersTotal > 0 ? round(($badOrdersCount / $ordersTotal) * 100, 1) : 0.0;

        $earningsTotal = (clone $orderBase)->where('status', 'delivered')->sum('total_amount');
        $earningsMonth = (clone $orderBase)
            ->where('status', 'delivered')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total_amount');

        // Store rating: average rating across all product reviews for this seller (v1.2 - Seller #9)
        $storeRatingAvg = (float) Review::query()
            ->join('products', 'products.id', '=', 'reviews.product_id')
            ->where('products.seller_id', $seller->id)
            ->avg('reviews.rating');
        $storeReviewsCount = (int) Review::query()
            ->join('products', 'products.id', '=', 'reviews.product_id')
            ->where('products.seller_id', $seller->id)
            ->count();

        // Net Profit: sum of (sale_price or price) × qty for delivered orders (v1.2 - Seller #2)
        $netProfit = (float) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.seller_id', $seller->id)
            ->where('orders.status', 'delivered')
            ->selectRaw('SUM(COALESCE(NULLIF(products.sale_price, 0), products.price) * order_items.quantity) as total')
            ->value('total') ?? 0;

        return [
            'products_total' => $productsTotal,
            'products_active' => $productsActive,
            'stock_total' => $stockTotal,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'orders_total' => $ordersTotal,
            'orders_processing' => $ordersProcessing,
            'orders_shipped' => $ordersShipped,
            'orders_delivered' => $ordersDelivered,
            'orders_cancelled' => $ordersCancelled,
            'orders_ready_to_ship' => $ordersReadyToShip,
            'bad_orders_count' => $badOrdersCount,
            'bad_orders_percent' => $badOrdersPercent,
            'earnings_total' => $earningsTotal,
            'earnings_month' => $earningsMonth,
            'net_profit' => $netProfit,
            'store_rating_avg' => $storeRatingAvg,
            'store_reviews_count' => $storeReviewsCount,
        ];
    }
};
?>

@push('styles')
@verbatim
<style>
    .dash-header {
        background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);
        border-radius: 16px;
        padding: 24px 28px;
        border: 1px solid #E8E8E8;
        margin-bottom: 24px;
    }
    .dash-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #212121;
        margin: 0 0 6px;
    }
    .dash-header p {
        font-size: 0.9375rem;
        color: #757575;
        margin: 0;
    }

    .dash-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #E8E8E8;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        overflow: hidden;
        transition: all 0.2s ease;
    }
    .dash-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    }
    .dash-card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 22px;
        border-bottom: 1px solid #F0F0F0;
        background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);
    }
    .dash-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .dash-card-icon.green {
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: #fff;
        box-shadow: 0 2px 10px rgba(45,159,78,0.3);
    }
    .dash-card-icon.yellow {
        background: linear-gradient(135deg, #F9C74F 0%, #F5A623 100%);
        color: #212121;
        box-shadow: 0 2px 10px rgba(249,199,79,0.3);
    }
    .dash-card-icon.blue {
        background: linear-gradient(135deg, #4A90D9 0%, #357ABD 100%);
        color: #fff;
        box-shadow: 0 2px 10px rgba(74,144,217,0.3);
    }
    .dash-card-icon.red {
        background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
        color: #fff;
        box-shadow: 0 2px 10px rgba(231,76,60,0.3);
    }
    .dash-card-icon.purple {
        background: linear-gradient(135deg, #9B59B6 0%, #8E44AD 100%);
        color: #fff;
        box-shadow: 0 2px 10px rgba(155,89,182,0.3);
    }
    .dash-card-title {
        font-size: 0.8125rem;
        font-weight: 700;
        color: #757575;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }
    .dash-card-body {
        padding: 20px 22px;
    }
    .dash-stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #212121;
        margin: 0 0 8px;
    }
    .dash-stat-value.green { color: #2D9F4E; }
    .dash-stat-value.yellow { color: #F5A623; }
    .dash-stat-value.red { color: #E74C3C; }
    .dash-stat-label {
        font-size: 0.8125rem;
        color: #9E9E9E;
        margin: 0;
    }
    .dash-stat-meta {
        display: flex;
        gap: 16px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #F0F0F0;
    }
    .dash-stat-meta-item {
        font-size: 0.75rem;
    }
    .dash-stat-meta-item .label { color: #9E9E9E; }
    .dash-stat-meta-item .value { 
        font-weight: 600; 
        color: #616161;
        margin-left: 4px;
    }

    .dash-alert {
        border-radius: 14px;
        padding: 18px 22px;
        margin-bottom: 24px;
        border: 2px solid transparent;
    }
    .dash-alert.success {
        background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
        border-color: #2D9F4E;
        color: #1B7A37;
    }
    .dash-alert.warning {
        background: linear-gradient(135deg, #FFF9E3 0%, #FFE082 100%);
        border-color: #F9C74F;
        color: #F57C00;
    }
    .dash-alert.danger {
        background: linear-gradient(135deg, #FFEBEE 0%, #FFCDD2 100%);
        border-color: #E74C3C;
        color: #C0392B;
    }
    .dash-alert-title {
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        opacity: 0.8;
        margin: 0 0 6px;
    }
    .dash-alert-text {
        font-size: 1.125rem;
        font-weight: 700;
        margin: 0;
    }

    .dash-grid {
        display: grid;
        gap: 20px;
        margin-bottom: 24px;
    }
    .dash-grid-4 { grid-template-columns: repeat(1, 1fr); }
    .dash-grid-3 { grid-template-columns: repeat(1, 1fr); }
    .dash-grid-5 { grid-template-columns: repeat(1, 1fr); }
    @media (min-width: 768px) {
        .dash-grid-4 { grid-template-columns: repeat(2, 1fr); }
        .dash-grid-3 { grid-template-columns: repeat(2, 1fr); }
        .dash-grid-5 { grid-template-columns: repeat(2, 1fr); }
    }
    @media (min-width: 1024px) {
        .dash-grid-4 { grid-template-columns: repeat(4, 1fr); }
        .dash-grid-3 { grid-template-columns: repeat(3, 1fr); }
        .dash-grid-5 { grid-template-columns: repeat(3, 1fr); }
    }
    @media (min-width: 1280px) {
        .dash-grid-5 { grid-template-columns: repeat(5, 1fr); }
    }

    .dash-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #2D9F4E;
        text-decoration: none;
        margin-top: 12px;
        transition: color 0.15s;
    }
    .dash-link:hover { color: #1B7A37; }

    .dash-subscription-list {
        max-height: 280px;
        overflow-y: auto;
    }
    .dash-subscription-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 0;
        border-bottom: 1px solid #F0F0F0;
    }
    .dash-subscription-item:last-child { border-bottom: none; }
    .dash-subscription-date {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #212121;
    }
    .dash-subscription-amount {
        font-size: 0.875rem;
        color: #616161;
        margin-left: 8px;
    }
    .dash-subscription-status {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .dash-subscription-status.approved {
        background: #E8F5E9;
        color: #2D9F4E;
    }
    .dash-subscription-status.pending {
        background: #FFF9E3;
        color: #F57C00;
    }
    .dash-subscription-status.rejected {
        background: #FFEBEE;
        color: #E74C3C;
    }

    .dash-quick-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .dash-quick-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: #fff;
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #616161;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .dash-quick-action:hover {
        background: linear-gradient(135deg, #E8F5E9 0%, #FFF9E3 100%);
        border-color: #2D9F4E;
        color: #2D9F4E;
    }
</style>
@endverbatim
@endpush

<div class="space-y-6">

    {{-- Header --}}
    <div class="dash-header">
        <h2>Dashboard</h2>
        <p>Welcome back, {{ Auth::guard('seller')->user()?->seller?->store_name ?? 'Seller' }}! Here's what's happening with your store.</p>
    </div>
    {{-- Subscription Alert --}}
    @if($this->seller && $this->seller->subscription_due_date)
        @php
            $days = $this->subscriptionDaysRemaining;
            $alertClass = $days === null ? '' : ($days > 14 ? 'success' : ($days >= 7 ? 'warning' : 'danger'));
        @endphp
        <div class="dash-alert {{ $alertClass }}">
            <div class="dash-alert-title">Subscription Status</div>
            <div class="dash-alert-text">
                @if($days !== null && $days > 0)
                    Expires in {{ $days }} {{ $days === 1 ? 'day' : 'days' }}
                @elseif($days !== null && $days === 0)
                    Expires today — renew now!
                @elseif($days !== null && $days < 0)
                    Expired {{ abs($days) }} {{ abs($days) === 1 ? 'day' : 'days' }} ago
                @else
                    Due {{ $this->seller->subscription_due_date->format('M j, Y') }}
                @endif
            </div>
            @if($days !== null && $days > 0 && $days <= 14)
                <p style="margin: 8px 0 0; font-size: 0.875rem; opacity: 0.9;">
                    @if($days <= 7)
                        Renew soon to avoid store closure during grace period.
                    @else
                        Consider renewing before the due date.
                    @endif
                </p>
            @endif
        </div>
    @endif

    {{-- Main Stats Row --}}
    <div class="dash-grid dash-grid-4">
        {{-- Products --}}
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-icon green">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Products</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value">{{ $this->stats['products_total'] }}</div>
                <p class="dash-stat-label">Total products in your store</p>
                <div class="dash-stat-meta">
                    <div class="dash-stat-meta-item">
                        <span class="label">Active</span>
                        <span class="value" style="color: #2D9F4E;">{{ $this->stats['products_active'] }}</span>
                    </div>
                    <div class="dash-stat-meta-item">
                        <span class="label">In Stock</span>
                        <span class="value">{{ $this->stats['stock_total'] }}</span>
                    </div>
                </div>
                <a href="{{ route('seller.products') }}" class="dash-link">Manage products →</a>
            </div>
        </div>

        {{-- Orders --}}
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-icon yellow">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Orders</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value">{{ $this->stats['orders_total'] }}</div>
                <p class="dash-stat-label">Total orders received</p>
                <div class="dash-stat-meta">
                    <div class="dash-stat-meta-item">
                        <span class="label">Processing</span>
                        <span class="value" style="color: #F57C00;">{{ $this->stats['orders_processing'] }}</span>
                    </div>
                    <div class="dash-stat-meta-item">
                        <span class="label">Shipped</span>
                        <span class="value" style="color: #4A90D9;">{{ $this->stats['orders_shipped'] }}</span>
                    </div>
                </div>
                <a href="{{ route('seller.orders') }}" class="dash-link">View all orders →</a>
            </div>
        </div>

        {{-- Earnings --}}
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-icon green">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Earnings</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value green">₱{{ number_format($this->stats['earnings_month'], 0) }}</div>
                <p class="dash-stat-label">This month's earnings</p>
                <div class="dash-stat-meta">
                    <div class="dash-stat-meta-item">
                        <span class="label">Total Lifetime</span>
                        <span class="value">₱{{ number_format($this->stats['earnings_total'], 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Net Profit --}}
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-icon purple">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Net Profit</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value">₱{{ number_format($this->stats['net_profit'], 0) }}</div>
                <p class="dash-stat-label">From delivered orders</p>
                <div class="dash-stat-meta">
                    <div class="dash-stat-meta-item">
                        <span class="label">Delivered</span>
                        <span class="value" style="color: #2D9F4E;">{{ $this->stats['orders_delivered'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Secondary Stats --}}
    <div class="dash-grid dash-grid-3">
        {{-- Low Stock --}}
        <a href="{{ route('seller.products') }}" class="dash-card" style="text-decoration: none;">
            <div class="dash-card-header">
                <div class="dash-card-icon yellow">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Low Stock Alert</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value yellow">{{ $this->stats['low_stock_count'] }}</div>
                <p class="dash-stat-label">Products with stock below 10</p>
                <span class="dash-link">Restock now →</span>
            </div>
        </a>

        {{-- Out of Stock --}}
        <a href="{{ route('seller.products') }}" class="dash-card" style="text-decoration: none;">
            <div class="dash-card-header">
                <div class="dash-card-icon red">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Out of Stock</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value red">{{ $this->stats['out_of_stock_count'] }}</div>
                <p class="dash-stat-label">Products completely sold out</p>
                <span class="dash-link">Update inventory →</span>
            </div>
        </a>

        {{-- Store Rating --}}
        <a href="{{ route('seller.reviews') }}" class="dash-card" style="text-decoration: none;">
            <div class="dash-card-header">
                <div class="dash-card-icon green">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Store Rating</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value" style="display: flex; align-items: center; gap: 8px;">
                    {{ number_format($this->stats['store_rating_avg'], 1) }}
                    <span style="font-size: 1rem; font-weight: 500; color: #9E9E9E;">/ 5</span>
                </div>
                <p class="dash-stat-label">Based on {{ $this->stats['store_reviews_count'] }} customer reviews</p>
                <span class="dash-link">View all reviews →</span>
            </div>
        </a>
    </div>

    {{-- SLA Metrics --}}
    @php($sla = $this->slaMetrics)
    <div class="dash-grid dash-grid-4">
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-icon blue">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Acceptance Rate</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value {{ $sla['acceptance_rate'] >= 90 ? 'green' : '' }}">{{ number_format($sla['acceptance_rate'], 1) }}%</div>
                <p class="dash-stat-label">{{ $sla['accepted_orders'] }} accepted / {{ $sla['acceptance_scope'] }} orders</p>
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-icon yellow">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">On-Time Shipping</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value {{ $sla['on_time_ship_rate'] >= 80 ? 'green' : 'yellow' }}">{{ number_format($sla['on_time_ship_rate'], 1) }}%</div>
                <p class="dash-stat-label">{{ $sla['on_time_shipments'] }} on-time / {{ $sla['shipment_scope'] }} shipped (48h SLA)</p>
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-icon red">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Cancellation Rate</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value {{ $sla['cancellation_rate'] > 20 ? 'red' : '' }}">{{ number_format($sla['cancellation_rate'], 1) }}%</div>
                <p class="dash-stat-label">{{ $sla['cancelled_orders'] }} cancelled / {{ $sla['cancellation_scope'] }} orders</p>
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-icon purple">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Return Rate</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value {{ $sla['return_rate'] > 15 ? 'red' : '' }}">{{ number_format($sla['return_rate'], 1) }}%</div>
                <p class="dash-stat-label">{{ $sla['returned_orders'] }} returned / {{ $sla['return_scope'] }} delivered</p>
            </div>
        </div>
    </div>

    {{-- Order Status Cards --}}
    <div class="dash-grid dash-grid-5">
        <a href="{{ route('seller.orders', ['status' => 'paid']) }}" class="dash-card" style="text-decoration: none;">
            <div class="dash-card-header" style="background: linear-gradient(135deg, #FFF9E3 0%, #FFE082 100%);">
                <div class="dash-card-icon yellow">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title" style="color: #F57C00;">Pending</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value yellow">{{ $this->stats['orders_processing'] }}</div>
                <p class="dash-stat-label">Awaiting processing</p>
            </div>
        </a>

        <a href="{{ route('seller.orders', ['status' => 'ready_to_ship']) }}" class="dash-card" style="text-decoration: none;">
            <div class="dash-card-header" style="background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%);">
                <div class="dash-card-icon blue">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                </div>
                <h3 class="dash-card-title" style="color: #1976D2;">To Ship</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value" style="color: #1976D2;">{{ $this->stats['orders_ready_to_ship'] }}</div>
                <p class="dash-stat-label">Ready for pickup</p>
            </div>
        </a>

        <a href="{{ route('seller.orders', ['status' => 'delivered']) }}" class="dash-card" style="text-decoration: none;">
            <div class="dash-card-header" style="background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);">
                <div class="dash-card-icon green">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="dash-card-title" style="color: #2D9F4E;">Completed</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value green">{{ $this->stats['orders_delivered'] }}</div>
                <p class="dash-stat-label">Successfully delivered</p>
            </div>
        </a>

        <a href="{{ route('seller.orders', ['status' => 'cancelled']) }}" class="dash-card" style="text-decoration: none;">
            <div class="dash-card-header" style="background: linear-gradient(135deg, #FFEBEE 0%, #FFCDD2 100%);">
                <div class="dash-card-icon red">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h3 class="dash-card-title" style="color: #C0392B;">Cancelled</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value red">{{ $this->stats['orders_cancelled'] }}</div>
                <p class="dash-stat-label">Cancelled orders</p>
            </div>
        </a>

        <a href="{{ route('seller.orders', ['status' => 'cancelled']) }}" class="dash-card" style="text-decoration: none;">
            <div class="dash-card-header" style="background: linear-gradient(135deg, #F3E5F5 0%, #E1BEE7 100%);">
                <div class="dash-card-icon purple">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title" style="color: #7B1FA2;">Bad Orders</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-stat-value" style="color: #7B1FA2;">{{ $this->stats['bad_orders_count'] }}</div>
                <p class="dash-stat-label">{{ number_format($this->stats['bad_orders_percent'], 1) }}% of total</p>
            </div>
        </a>
    </div>

    {{-- Subscription History & Quick Actions Row --}}
    <div class="dash-grid dash-grid-2" style="grid-template-columns: repeat(1, 1fr);">
        @if($this->seller)
            <div class="dash-card">
                <div class="dash-card-header" style="justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="dash-card-icon green">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <h3 class="dash-card-title">Subscription History</h3>
                    </div>
                    <a href="{{ route('seller.payments') }}" class="dash-link" style="margin: 0;">View all →</a>
                </div>
                <div class="dash-card-body" style="padding: 0;">
                    @if($this->subscriptionPayments->isNotEmpty())
                        <div class="dash-subscription-list">
                            @foreach($this->subscriptionPayments as $p)
                                <div class="dash-subscription-item" style="padding-left: 22px; padding-right: 22px;">
                                    <div>
                                        <span class="dash-subscription-date">{{ $p->created_at->format('F Y') }}</span>
                                        <span class="dash-subscription-amount">₱{{ number_format($p->amount, 2) }}</span>
                                    </div>
                                    <span class="dash-subscription-status {{ $p->status }}">
                                        {{ ucfirst($p->status) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div style="padding: 30px 22px;">
                            <p class="dash-stat-label">No subscription payments yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-icon yellow">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="dash-card-title">Quick Actions</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-quick-actions">
                    <a href="{{ route('seller.products') }}" class="dash-quick-action">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        Products
                    </a>
                    <a href="{{ route('seller.orders') }}" class="dash-quick-action">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        Orders
                    </a>
                    <a href="{{ route('seller.store') }}" class="dash-quick-action">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        Store
                    </a>
                    <a href="{{ route('seller.payments') }}" class="dash-quick-action">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Payments
                    </a>
                    <a href="{{ route('seller.messages') }}" class="dash-quick-action">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        Messages
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

