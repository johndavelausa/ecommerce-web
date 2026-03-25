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
    public function announcements()
    {
        return \App\Models\Announcement::query()
            ->where(fn($q) => $q->where('target_role', 'seller')->orWhere('target_role', 'all'))
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();
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
                'orders_completed' => 0,
                'bad_orders_percent' => 0.0,
                'earnings_total' => 0,
                'earnings_month' => 0,
                'net_profit' => 0,
                'store_rating_avg' => 0.0,
                'store_reviews_count' => 0,
                'avg_order_value' => 0.0,
                'top_product_name' => null,
                'top_product_sold' => 0,
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

        // Bad Orders: cancelled + refunded orders (v1.2 - Seller #7)
        $refundedOrdersCount = (clone $orderBase)
            ->where('refund_status', Order::REFUND_STATUS_COMPLETED)
            ->count();
        $badOrdersCount = $ordersCancelled + $refundedOrdersCount;
        $badOrdersPercent = $ordersTotal > 0 ? round(($badOrdersCount / $ordersTotal) * 100, 1) : 0.0;

        $ordersCompleted = (clone $orderBase)
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->count();

        $earningsTotal = (float) Order::query()
            ->where('seller_id', $seller->id)
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where('refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
            ->sum('total_amount');

        $earningsMonth = (float) Order::query()
            ->where('seller_id', $seller->id)
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
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

        // Net Profit: total_amount of received/completed orders (includes delivery fees)
        $netProfit = $earningsTotal;

        $avgOrderValue = $ordersCompleted > 0 ? round($earningsTotal / $ordersCompleted, 2) : 0.0;

        $topProductData = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.seller_id', $seller->id)
            ->whereIn('orders.status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where('orders.refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as total_sold')
            ->groupBy('order_items.product_id')
            ->orderByDesc('total_sold')
            ->first();
        $topProductName = $topProductData ? (Product::find($topProductData->product_id)?->name ?? 'N/A') : null;
        $topProductSold = (int) ($topProductData?->total_sold ?? 0);

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
            'avg_order_value' => $avgOrderValue,
            'top_product_name' => $topProductName,
            'top_product_sold' => $topProductSold,
            'orders_completed' => $ordersCompleted,
        ];
    }

    #[Computed]
    public function salesTrend(): array
    {
        $seller = $this->seller;
        if (! $seller) {
            return ['labels' => [], 'data' => []];
        }
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $revenues = Order::query()
            ->where('seller_id', $seller->id)
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');
        return [
            'labels' => $days->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M j'))->values()->toArray(),
            'data'   => $days->map(fn ($d) => (float) ($revenues[$d] ?? 0))->values()->toArray(),
        ];
    }

    #[Computed]
    public function salesByProduct(): array
    {
        $seller = $this->seller;
        if (! $seller) {
            return ['labels' => [], 'data' => []];
        }
        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.seller_id', $seller->id)
            ->whereIn('orders.status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where('orders.refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
            ->selectRaw('products.name, SUM(order_items.price_at_purchase * order_items.quantity) as total_revenue, SUM(order_items.quantity) as total_qty')
            ->groupBy('order_items.product_id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();
        return [
            'labels'   => $items->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::limit($n, 24))->values()->toArray(),
            'revenue'  => $items->pluck('total_revenue')->map(fn ($v) => round((float) $v, 2))->values()->toArray(),
            'qty'      => $items->pluck('total_qty')->map(fn ($v) => (int) $v)->values()->toArray(),
        ];
    }

    #[Computed]
    public function productHistory(): array
    {
        $seller = $this->seller;
        if (! $seller) {
            return ['chart' => ['labels' => [], 'datasets' => []], 'recent' => []];
        }

        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        $chartData = \App\Models\ProductHistory::query()
            ->join('products', 'products.id', '=', 'product_histories.product_id')
            ->where('products.seller_id', $seller->id)
            ->whereBetween('product_histories.created_at', [now()->subDays(29)->startOfDay(), now()->endOfDay()])
            ->selectRaw('DATE(product_histories.created_at) as date, product_histories.action, COUNT(*) as count')
            ->groupBy('date', 'product_histories.action')
            ->get();

        $actionColors = [
            'added'        => 'rgba(45,159,78,0.8)',
            'updated'      => 'rgba(74,144,217,0.8)',
            'deleted'      => 'rgba(231,76,60,0.8)',
            'stock_change' => 'rgba(249,199,79,0.8)',
        ];

        $datasets = [];
        foreach ($actionColors as $action => $color) {
            $counts = $chartData->where('action', $action)->pluck('count', 'date');
            $data = $days->map(fn ($d) => (int) ($counts[$d] ?? 0))->values()->toArray();
            if (array_sum($data) > 0) {
                $datasets[] = [
                    'label'           => ucfirst(str_replace('_', ' ', $action)),
                    'data'            => $data,
                    'backgroundColor' => $color,
                    'borderRadius'    => 4,
                    'borderSkipped'   => false,
                ];
            }
        }

        $badgeBg    = ['added' => '#E8F5E9', 'updated' => '#E3F2FD', 'deleted' => '#FFEBEE', 'stock_change' => '#FFF9E3'];
        $badgeColor = ['added' => '#1B7A37', 'updated' => '#1565C0', 'deleted' => '#C0392B', 'stock_change' => '#F57C00'];
        $badgeLabel = ['added' => 'Added',   'updated' => 'Updated', 'deleted' => 'Deleted', 'stock_change' => 'Stock'];

        $recent = \App\Models\ProductHistory::query()
            ->join('products', 'products.id', '=', 'product_histories.product_id')
            ->where('products.seller_id', $seller->id)
            ->orderByDesc('product_histories.created_at')
            ->limit(15)
            ->select('product_histories.*', 'products.name as product_name')
            ->get()
            ->map(fn ($r) => [
                'product' => $r->product_name,
                'action'  => $r->action,
                'note'    => $r->note,
                'date'    => $r->created_at?->format('M j, Y g:i A'),
                'bg'      => $badgeBg[$r->action]    ?? '#F5F5F5',
                'color'   => $badgeColor[$r->action] ?? '#9E9E9E',
                'label'   => $badgeLabel[$r->action] ?? ucfirst($r->action),
            ])->toArray();

        return [
            'chart'  => [
                'labels'   => $days->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M j'))->values()->toArray(),
                'datasets' => $datasets,
            ],
            'recent' => $recent,
        ];
    }

    #[Computed]
    public function stockLevels(): array
    {
        $seller = $this->seller;
        if (! $seller) {
            return ['labels' => [], 'stock' => [], 'thresholds' => [], 'colors' => []];
        }
        $products = \App\Models\Product::query()
            ->where('seller_id', $seller->id)
            ->where('is_active', true)
            ->orderBy('stock')
            ->limit(15)
            ->get(['name', 'stock', 'low_stock_threshold']);

        $colors = $products->map(function ($p) {
            if ($p->stock <= 0) return 'rgba(231,76,60,0.85)';
            if ($p->stock <= ($p->low_stock_threshold ?? 10)) return 'rgba(249,199,79,0.85)';
            return 'rgba(45,159,78,0.75)';
        })->values()->toArray();

        return [
            'labels'     => $products->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::limit($n, 24))->values()->toArray(),
            'stock'      => $products->pluck('stock')->map(fn ($v) => (int) $v)->values()->toArray(),
            'thresholds' => $products->pluck('low_stock_threshold')->map(fn ($v) => (int) ($v ?? 10))->values()->toArray(),
            'colors'     => $colors,
        ];
    }

    #[Computed]
    public function topProducts(): array
    {
        $seller = $this->seller;
        if (! $seller) {
            return ['labels' => [], 'data' => []];
        }
        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.seller_id', $seller->id)
            ->whereIn('orders.status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where('orders.refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
            ->selectRaw('products.name, SUM(order_items.quantity) as total_sold')
            ->groupBy('order_items.product_id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();
        return [
            'labels' => $items->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::limit($n, 22))->values()->toArray(),
            'data'   => $items->pluck('total_sold')->map(fn ($v) => (int) $v)->values()->toArray(),
        ];
    }
};
?>

@push('styles')
@verbatim
<style>
    .dash-header {
        background: linear-gradient(135deg, #0F3D22 0%, #1a5c35 100%);
        border-radius: 12px;
        padding: 14px 18px;
        border: none;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .dash-header h2 {
        font-size: 1rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 2px;
    }
    .dash-header p {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.6);
        margin: 0;
    }
    .dash-header-date {
        font-size: 0.6875rem;
        color: rgba(255,255,255,0.45);
        white-space: nowrap;
        flex-shrink: 0;
    }

    .dash-card {
        background: #ffffff;
        border-radius: 10px;
        border: 1px solid #EBEBEB;
        box-shadow: 0 1px 6px rgba(0,0,0,0.05);
        overflow: hidden;
        transition: all 0.15s ease;
    }
    .dash-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    }
    .dash-card-header {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 11px 14px;
        border-bottom: 1px solid #F0F0F0;
        background: #FAFAFA;
    }
    .dash-card-icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .dash-card-icon.green {
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: #fff;
        box-shadow: 0 1px 6px rgba(45,159,78,0.25);
    }
    .dash-card-icon.yellow {
        background: linear-gradient(135deg, #F9C74F 0%, #F5A623 100%);
        color: #212121;
        box-shadow: 0 1px 6px rgba(249,199,79,0.25);
    }
    .dash-card-icon.blue {
        background: linear-gradient(135deg, #4A90D9 0%, #357ABD 100%);
        color: #fff;
        box-shadow: 0 1px 6px rgba(74,144,217,0.25);
    }
    .dash-card-icon.red {
        background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
        color: #fff;
        box-shadow: 0 1px 6px rgba(231,76,60,0.25);
    }
    .dash-card-icon.purple {
        background: linear-gradient(135deg, #9B59B6 0%, #8E44AD 100%);
        color: #fff;
        box-shadow: 0 1px 6px rgba(155,89,182,0.25);
    }
    .dash-card-icon.teal {
        background: linear-gradient(135deg, #00897B 0%, #00695C 100%);
        color: #fff;
        box-shadow: 0 1px 6px rgba(0,137,123,0.25);
    }
    .dash-card-icon svg { width: 15px; height: 15px; }
    .dash-card-title {
        font-size: 0.6875rem;
        font-weight: 700;
        color: #757575;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }
    .dash-card-body {
        padding: 13px 14px;
    }
    .dash-stat-value {
        font-size: 1.375rem;
        font-weight: 700;
        color: #212121;
        margin: 0 0 3px;
        line-height: 1.2;
    }
    .dash-stat-value.green { color: #2D9F4E; }
    .dash-stat-value.yellow { color: #F5A623; }
    .dash-stat-value.red { color: #E74C3C; }
    .dash-stat-label {
        font-size: 0.7rem;
        color: #9E9E9E;
        margin: 0;
        line-height: 1.3;
    }
    .dash-stat-meta {
        display: flex;
        gap: 12px;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #F0F0F0;
    }
    .dash-stat-meta-item { font-size: 0.6875rem; }
    .dash-stat-meta-item .label { color: #9E9E9E; }
    .dash-stat-meta-item .value {
        font-weight: 600;
        color: #616161;
        margin-left: 3px;
    }

    .dash-alert {
        border-radius: 10px;
        padding: 11px 14px;
        margin-bottom: 14px;
        border: 1px solid transparent;
    }
    .dash-alert.success {
        background: #E8F5E9;
        border-color: #A5D6A7;
        color: #1B7A37;
    }
    .dash-alert.warning {
        background: #FFF9E3;
        border-color: #FFE082;
        color: #F57C00;
    }
    .dash-alert.danger {
        background: #FFEBEE;
        border-color: #FFCDD2;
        color: #C0392B;
    }
    .dash-alert-title {
        font-size: 0.625rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        opacity: 0.75;
        margin: 0 0 3px;
    }
    .dash-alert-text {
        font-size: 0.875rem;
        font-weight: 700;
        margin: 0;
    }

    .dash-grid {
        display: grid;
        gap: 12px;
        margin-bottom: 12px;
    }
    .dash-grid-4 { grid-template-columns: repeat(1, 1fr); }
    .dash-grid-3 { grid-template-columns: repeat(1, 1fr); }
    .dash-grid-5 { grid-template-columns: repeat(1, 1fr); }
    .dash-grid-2 { grid-template-columns: repeat(1, 1fr); }
    @media (min-width: 768px) {
        .dash-grid-4 { grid-template-columns: repeat(2, 1fr); }
        .dash-grid-3 { grid-template-columns: repeat(2, 1fr); }
        .dash-grid-5 { grid-template-columns: repeat(2, 1fr); }
        .dash-grid-2 { grid-template-columns: repeat(2, 1fr); }
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
        gap: 3px;
        font-size: 0.6875rem;
        font-weight: 600;
        color: #2D9F4E;
        text-decoration: none;
        margin-top: 7px;
        transition: color 0.15s;
    }
    .dash-link:hover { color: #1B7A37; }

    .dash-subscription-list { max-height: 220px; overflow-y: auto; }
    .dash-subscription-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 9px 0;
        border-bottom: 1px solid #F5F5F5;
    }
    .dash-subscription-item:last-child { border-bottom: none; }
    .dash-subscription-date {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #212121;
    }
    .dash-subscription-amount {
        font-size: 0.75rem;
        color: #616161;
        margin-left: 6px;
    }
    .dash-subscription-status {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.625rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .dash-subscription-status.approved { background: #E8F5E9; color: #2D9F4E; }
    .dash-subscription-status.pending  { background: #FFF9E3; color: #F57C00; }
    .dash-subscription-status.rejected { background: #FFEBEE; color: #E74C3C; }

    .dash-quick-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .dash-quick-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 12px;
        background: #fff;
        border: 1px solid #E0E0E0;
        border-radius: 8px;
        font-size: 0.75rem;
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

    .dash-analytics-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 7px;
        border-radius: 20px;
        font-size: 0.625rem;
        font-weight: 700;
        background: #E8F5E9;
        color: #2D9F4E;
        margin-top: 5px;
    }

    /* ── KPI Strip ── */
    .dash-kpi-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        background: #fff;
        border-radius: 14px;
        border: 1px solid #EBEBEB;
        box-shadow: 0 1px 8px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    @media(max-width:1023px) { .dash-kpi-strip { grid-template-columns: repeat(2,1fr); } }
    @media(max-width:639px)  { .dash-kpi-strip { grid-template-columns: 1fr; } }
    .dash-kpi-item {
        padding: 15px 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-right: 1px solid #F0F0F0;
        text-decoration: none;
        transition: background 0.15s;
        box-shadow: inset 0 3px 0 var(--top-c, transparent);
    }
    .dash-kpi-item:last-child { border-right: none; }
    .dash-kpi-item:hover { background: #F8FFF9; }
    .dash-kpi-circle {
        width: 42px; height: 42px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .dash-kpi-circle svg { width: 20px; height: 20px; }
    .dash-kpi-body { min-width: 0; }
    .dash-kpi-label { font-size: 0.6rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 3px; }
    .dash-kpi-value { font-size: 1.5rem; font-weight: 800; color: #212121; line-height: 1; margin-bottom: 4px; }
    .dash-kpi-sub { font-size: 0.6875rem; color: #BDBDBD; }

    /* ── Dark Info Band ── */
    .dash-info-band {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        background: linear-gradient(135deg, #0F3D22 0%, #1a5c35 100%);
        border-radius: 14px; overflow: hidden;
        box-shadow: 0 2px 12px rgba(15,61,34,0.2);
    }
    @media(max-width:767px) { .dash-info-band { grid-template-columns: 1fr; } }
    .dash-info-item {
        display: flex; align-items: center; gap: 12px;
        padding: 14px 18px;
        border-right: 1px solid rgba(255,255,255,0.08);
        text-decoration: none; transition: background 0.15s;
    }
    .dash-info-item:last-child { border-right: none; }
    .dash-info-item:hover { background: rgba(255,255,255,0.05); }
    .dash-info-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .dash-info-icon svg { width: 18px; height: 18px; }
    .dash-info-value { font-size: 1.375rem; font-weight: 800; color: #fff; line-height: 1; }
    .dash-info-value.text-yellow { color: #F9C74F; }
    .dash-info-value.text-red { color: #FF7675; }
    .dash-info-label { font-size: 0.6875rem; font-weight: 600; color: rgba(255,255,255,0.55); margin-top: 2px; }
    .dash-info-sublabel { font-size: 0.6rem; color: rgba(255,255,255,0.3); margin-top: 1px; }

    /* ── Analytics Feature Cards ── */
    .dash-analytics-card { background: #fff; border-radius: 14px; border: 1px solid #EBEBEB; box-shadow: 0 1px 6px rgba(0,0,0,0.04); overflow: hidden; }
    .dash-analytics-accent { height: 4px; }
    .dash-analytics-body { padding: 14px 16px; }

    /* ── SLA Circles ── */
    .dash-sla-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; }
    @media(max-width:1023px) { .dash-sla-grid { grid-template-columns: repeat(2,1fr); } }
    .dash-sla-card {
        background: #fff; border-radius: 14px; border: 1px solid #EBEBEB;
        padding: 16px 12px; display: flex; flex-direction: column; align-items: center;
        text-align: center; box-shadow: 0 1px 5px rgba(0,0,0,0.04); transition: box-shadow 0.15s;
    }
    .dash-sla-card:hover { box-shadow: 0 3px 14px rgba(0,0,0,0.09); }
    .dash-sla-ring { width: 76px; height: 76px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
    .dash-sla-ring-inner { width: 56px; height: 56px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #212121; }
    .dash-sla-title { font-size: 0.6375rem; font-weight: 700; color: #424242; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 3px; }
    .dash-sla-sub { font-size: 0.6rem; color: #9E9E9E; }

    /* ── Order Pipeline ── */
    .dash-pipeline { background: #fff; border-radius: 14px; border: 1px solid #EBEBEB; box-shadow: 0 1px 5px rgba(0,0,0,0.04); overflow: hidden; }
    .dash-pipeline-header { padding: 9px 16px; background: #FAFAFA; border-bottom: 1px solid #F0F0F0; font-size: 0.6rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.08em; }
    .dash-pipeline-track { display: grid; grid-template-columns: repeat(5,1fr); }
    @media(max-width:767px) { .dash-pipeline-track { grid-template-columns: repeat(3,1fr); } }
    .dash-pipeline-step { padding: 13px 10px; text-align: center; border-right: 1px solid #F5F5F5; display: block; transition: background 0.15s; position: relative; }
    .dash-pipeline-step:last-child { border-right: none; }
    .dash-pipeline-step:hover { background: #FAFAFA; }
    .dash-pipeline-num { font-size: 1.375rem; font-weight: 800; line-height: 1; margin-bottom: 3px; }
    .dash-pipeline-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 2px; }
    .dash-pipeline-sub { font-size: 0.6rem; color: #BDBDBD; }
</style>
@endverbatim
@endpush

<div class="space-y-3">

    {{-- ── Header ── --}}
    <div class="dash-header">
        <div>
            <h2>Seller Dashboard</h2>
            <p>Welcome back, {{ Auth::guard('seller')->user()?->seller?->store_name ?? 'Seller' }}! Here's what's happening with your store.</p>
        </div>
        <div class="dash-header-date">{{ now()->format('M j, Y') }}</div>
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

    {{-- ── KPI Strip: 4-column unified card ── --}}
    <div class="dash-kpi-strip">
        {{-- Products --}}
        <a href="{{ route('seller.products') }}" class="dash-kpi-item" style="--top-c:#2D9F4E;">
            <div class="dash-kpi-circle" style="background:linear-gradient(135deg,#2D9F4E,#1B7A37);">
                <svg fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-label">Products</div>
                <div class="dash-kpi-value">{{ $this->stats['products_total'] }}</div>
                <div class="dash-kpi-sub">Active <b style="color:#2D9F4E;">{{ $this->stats['products_active'] }}</b> &middot; Stock {{ $this->stats['stock_total'] }}</div>
            </div>
        </a>
        {{-- Orders --}}
        <a href="{{ route('seller.orders') }}" class="dash-kpi-item" style="--top-c:#F9C74F;">
            <div class="dash-kpi-circle" style="background:linear-gradient(135deg,#F9C74F,#F5A623);">
                <svg fill="none" stroke="#212121" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-label">Orders</div>
                <div class="dash-kpi-value">{{ $this->stats['orders_total'] }}</div>
                <div class="dash-kpi-sub">Processing <b style="color:#F57C00;">{{ $this->stats['orders_processing'] }}</b> &middot; Shipped {{ $this->stats['orders_shipped'] }}</div>
            </div>
        </a>
        {{-- Earnings --}}
        <div class="dash-kpi-item" style="--top-c:#2D9F4E;">
            <div class="dash-kpi-circle" style="background:linear-gradient(135deg,#2D9F4E,#1B7A37);">
                <svg fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-label">Earnings</div>
                <div class="dash-kpi-value" style="color:#2D9F4E;">{{ number_format($this->stats['earnings_month'], 0) }}</div>
                <div class="dash-kpi-sub">Lifetime {{ number_format($this->stats['earnings_total'], 0) }}</div>
            </div>
        </div>
        {{-- Net Profit --}}
        <div class="dash-kpi-item" style="--top-c:#9B59B6;">
            <div class="dash-kpi-circle" style="background:linear-gradient(135deg,#9B59B6,#8E44AD);">
                <svg fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-label">Net Profit</div>
                <div class="dash-kpi-value">{{ number_format($this->stats['net_profit'], 0) }}</div>
                <div class="dash-kpi-sub">{{ $this->stats['orders_completed'] }} completed orders</div>
            </div>
        </div>
    </div>

    {{-- ── Dark Info Band: Low Stock · Out of Stock · Rating ── --}}
    <div class="dash-info-band">
        <a href="{{ route('seller.products') }}" class="dash-info-item">
            <div class="dash-info-icon" style="background:rgba(249,199,79,0.18);">
                <svg fill="none" stroke="#F9C74F" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <div class="dash-info-value {{ $this->stats['low_stock_count'] > 0 ? 'text-yellow' : '' }}">{{ $this->stats['low_stock_count'] }}</div>
                <div class="dash-info-label">Low Stock Alert</div>
                <div class="dash-info-sublabel">Below 10 units</div>
            </div>
        </a>
        <a href="{{ route('seller.products') }}" class="dash-info-item">
            <div class="dash-info-icon" style="background:rgba(231,76,60,0.18);">
                <svg fill="none" stroke="#E74C3C" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="dash-info-value {{ $this->stats['out_of_stock_count'] > 0 ? 'text-red' : '' }}">{{ $this->stats['out_of_stock_count'] }}</div>
                <div class="dash-info-label">Out of Stock</div>
                <div class="dash-info-sublabel">Needs restock</div>
            </div>
        </a>
        <a href="{{ route('seller.reviews') }}" class="dash-info-item">
            <div class="dash-info-icon" style="background:rgba(249,199,79,0.18);">
                <svg fill="#F9C74F" viewBox="0 0 24 24"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
            </div>
            <div>
                <div class="dash-info-value">{{ number_format($this->stats['store_rating_avg'], 1) }}<span style="font-size:0.75rem;color:rgba(255,255,255,0.4);font-weight:500;"> / 5</span></div>
                <div class="dash-info-label">Store Rating</div>
                <div class="dash-info-sublabel">{{ $this->stats['store_reviews_count'] }} reviews</div>
            </div>
        </a>
    </div>

    {{-- ── Analytics: Sales Trend Chart + Top Products Chart ── --}}
    @once
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @endonce
    <div class="dash-grid dash-grid-2">
        {{-- Sales Trend: line chart --}}
        <div class="dash-analytics-card">
            <div class="dash-analytics-accent" style="background:linear-gradient(90deg,#00897B,#2D9F4E);"></div>
            <div class="dash-analytics-body">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#00897B,#00695C);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg style="width:13px;height:13px;" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                        <span style="font-size:0.6rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.07em;">7-Day Sales Trend</span>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:1rem;font-weight:800;color:#2D9F4E;line-height:1;">{{ number_format($this->stats['avg_order_value'], 0) }}</div>
                        <div style="font-size:0.6rem;color:#BDBDBD;">avg / order</div>
                    </div>
                </div>
                <div x-data="{
                    init() {
                        const ctx = this.$refs.salesChart.getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: {{ Js::from($this->salesTrend['labels']) }},
                                datasets: [{
                                    data: {{ Js::from($this->salesTrend['data']) }},
                                    borderColor: '#2D9F4E',
                                    backgroundColor: 'rgba(45,159,78,0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.45,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointBackgroundColor: '#2D9F4E',
                                    pointBorderColor: '#fff',
                                    pointBorderWidth: 2,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => '\u20b1' + ctx.parsed.y.toLocaleString() } } },
                                scales: {
                                    x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#BDBDBD' }, border: { display: false } },
                                    y: { grid: { color: '#F5F5F5' }, ticks: { font: { size: 9 }, color: '#BDBDBD', callback: v => '\u20b1'+v.toLocaleString() }, border: { display: false } }
                                }
                            }
                        });
                    }
                }">
                    <canvas x-ref="salesChart" style="height:130px;"></canvas>
                </div>
                <div style="display:flex;gap:16px;margin-top:10px;padding-top:10px;border-top:1px solid #F5F5F5;">
                    <div><div style="font-size:0.6rem;color:#BDBDBD;">Completed</div><div style="font-size:0.8125rem;font-weight:700;color:#2D9F4E;">{{ $this->stats['orders_completed'] }}</div></div>
                    <div><div style="font-size:0.6rem;color:#BDBDBD;">Total Revenue</div><div style="font-size:0.8125rem;font-weight:700;color:#212121;">{{ number_format($this->stats['earnings_total'], 0) }}</div></div>
                </div>
            </div>
        </div>

        {{-- Top Products: horizontal bar chart --}}
        <div class="dash-analytics-card">
            <div class="dash-analytics-accent" style="background:linear-gradient(90deg,#F9C74F,#F5A623);"></div>
            <div class="dash-analytics-body">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                    <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#F9C74F,#F5A623);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:13px;height:13px;" fill="none" stroke="#212121" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <span style="font-size:0.6rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.07em;">Top 5 Products by Units Sold</span>
                </div>
                @if(!empty($this->topProducts['data']))
                <div x-data="{
                    init() {
                        const ctx = this.$refs.topChart.getContext('2d');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: {{ Js::from($this->topProducts['labels']) }},
                                datasets: [{
                                    data: {{ Js::from($this->topProducts['data']) }},
                                    backgroundColor: ['rgba(45,159,78,0.85)','rgba(249,199,79,0.85)','rgba(74,144,217,0.85)','rgba(231,76,60,0.85)','rgba(155,89,182,0.85)'],
                                    borderRadius: 6,
                                    borderSkipped: false,
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.parsed.x + ' units' } } },
                                scales: {
                                    x: { grid: { color: '#F5F5F5' }, ticks: { font: { size: 9 }, color: '#BDBDBD', stepSize: 1 }, border: { display: false } },
                                    y: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#616161' }, border: { display: false } }
                                }
                            }
                        });
                    }
                }">
                    <canvas x-ref="topChart" style="height:{{ count($this->topProducts['data']) * 30 + 20 }}px;min-height:100px;max-height:160px;"></canvas>
                </div>
                @else
                    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:120px;">
                        <svg style="width:32px;height:32px;color:#E0E0E0;margin-bottom:8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <div style="font-size:0.75rem;color:#BDBDBD;font-weight:500;">No sales data yet</div>
                        <div style="font-size:0.6rem;color:#E0E0E0;margin-top:2px;">Complete orders to see top products</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Sales by Product ── --}}
    <div class="dash-analytics-card">
        <div class="dash-analytics-accent" style="background:linear-gradient(90deg,#4A90D9,#357ABD);"></div>
        <div class="dash-analytics-body">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#4A90D9,#357ABD);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:13px;height:13px;" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span style="font-size:0.6rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.07em;">Total Sales per Product</span>
                </div>
                <span style="font-size:0.6rem;color:#BDBDBD;">Top 10 by revenue &middot; received/completed orders only</span>
            </div>
            @if(!empty($this->salesByProduct['labels']))
            <div x-data="{
                init() {
                    const ctx = this.$refs.salesByProductChart.getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: {{ Js::from($this->salesByProduct['labels']) }},
                            datasets: [
                                {
                                    label: 'Revenue (₱)',
                                    data: {{ Js::from($this->salesByProduct['revenue']) }},
                                    backgroundColor: 'rgba(74,144,217,0.82)',
                                    borderRadius: 6,
                                    borderSkipped: false,
                                    yAxisID: 'yRevenue',
                                },
                                {
                                    label: 'Units Sold',
                                    data: {{ Js::from($this->salesByProduct['qty']) }},
                                    backgroundColor: 'rgba(45,159,78,0.75)',
                                    borderRadius: 6,
                                    borderSkipped: false,
                                    yAxisID: 'yQty',
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'top', labels: { font: { size: 10 }, boxWidth: 12 } },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => ctx.dataset.label === 'Revenue (₱)'
                                            ? '₱' + ctx.parsed.y.toLocaleString()
                                            : ctx.parsed.y + ' units'
                                    }
                                }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#616161', maxRotation: 30 }, border: { display: false } },
                                yRevenue: {
                                    position: 'left',
                                    grid: { color: '#F5F5F5' },
                                    ticks: { font: { size: 9 }, color: '#4A90D9', callback: v => '₱'+v.toLocaleString() },
                                    border: { display: false }
                                },
                                yQty: {
                                    position: 'right',
                                    grid: { display: false },
                                    ticks: { font: { size: 9 }, color: '#2D9F4E', stepSize: 1 },
                                    border: { display: false }
                                }
                            }
                        }
                    });
                }
            }">
                <canvas x-ref="salesByProductChart" style="height:220px;"></canvas>
            </div>
            @else
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:160px;">
                    <svg style="width:32px;height:32px;color:#E0E0E0;margin-bottom:8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <div style="font-size:0.75rem;color:#BDBDBD;font-weight:500;">No sales data yet</div>
                    <div style="font-size:0.6rem;color:#E0E0E0;margin-top:2px;">Complete orders to see per-product sales</div>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Current Stock Levels per Product ── --}}
    <div class="dash-analytics-card">
        <div class="dash-analytics-accent" style="background:linear-gradient(90deg,#E74C3C,#F39C12);"></div>
        <div class="dash-analytics-body">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#E74C3C,#F39C12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:13px;height:13px;" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <span style="font-size:0.6rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.07em;">Current Stock Levels per Product</span>
                </div>
                <div style="display:flex;align-items:center;gap:12px;font-size:0.6rem;color:#9E9E9E;">
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:rgba(231,76,60,0.85);margin-right:3px;"></span>Out of stock</span>
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:rgba(249,199,79,0.85);margin-right:3px;"></span>Low stock</span>
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:rgba(45,159,78,0.75);margin-right:3px;"></span>OK</span>
                </div>
            </div>
            @if(!empty($this->stockLevels['labels']))
            <div x-data="{
                init() {
                    const ctx = this.$refs.stockChart.getContext('2d');
                    const thresholds = {{ Js::from($this->stockLevels['thresholds']) }};
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: {{ Js::from($this->stockLevels['labels']) }},
                            datasets: [
                                {
                                    label: 'Stock',
                                    data: {{ Js::from($this->stockLevels['stock']) }},
                                    backgroundColor: {{ Js::from($this->stockLevels['colors']) }},
                                    borderRadius: 5,
                                    borderSkipped: false,
                                    yAxisID: 'y',
                                },
                                {
                                    label: 'Low Stock Threshold',
                                    data: thresholds,
                                    type: 'line',
                                    borderColor: 'rgba(231,76,60,0.5)',
                                    borderWidth: 1.5,
                                    borderDash: [4,3],
                                    pointRadius: 0,
                                    fill: false,
                                    yAxisID: 'y',
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => ctx.dataset.label === 'Stock'
                                            ? ctx.parsed.y + ' units in stock'
                                            : 'Threshold: ' + ctx.parsed.y
                                    }
                                }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#616161', maxRotation: 30 }, border: { display: false } },
                                y: { grid: { color: '#F5F5F5' }, ticks: { font: { size: 9 }, color: '#BDBDBD', stepSize: 1 }, border: { display: false }, beginAtZero: true }
                            }
                        }
                    });
                }
            }">
                <canvas x-ref="stockChart" style="height:220px;"></canvas>
            </div>
            @else
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:160px;">
                    <svg style="width:32px;height:32px;color:#E0E0E0;margin-bottom:8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <div style="font-size:0.75rem;color:#BDBDBD;font-weight:500;">No active products</div>
                    <div style="font-size:0.6rem;color:#E0E0E0;margin-top:2px;">Add products to see stock levels</div>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Product History ── --}}
    <div class="dash-analytics-card">
        <div class="dash-analytics-accent" style="background:linear-gradient(90deg,#9B59B6,#8E44AD);"></div>
        <div class="dash-analytics-body">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#9B59B6,#8E44AD);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:13px;height:13px;" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span style="font-size:0.6rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.07em;">Product Activity History</span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;font-size:0.6rem;color:#9E9E9E;">
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:rgba(45,159,78,0.8);margin-right:3px;"></span>Added</span>
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:rgba(74,144,217,0.8);margin-right:3px;"></span>Updated</span>
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:rgba(231,76,60,0.8);margin-right:3px;"></span>Deleted</span>
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:rgba(249,199,79,0.8);margin-right:3px;"></span>Stock change</span>
                </div>
            </div>

            @php($ph = $this->productHistory)
            @if(!empty($ph['chart']['datasets']))
            <div x-data="{
                init() {
                    const ctx = this.$refs.phChart.getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: {{ Js::from($ph['chart']['labels']) }},
                            datasets: {{ Js::from($ph['chart']['datasets']) }}
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'top', labels: { font: { size: 9 }, boxWidth: 10, padding: 10 } },
                                tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y } }
                            },
                            scales: {
                                x: { stacked: true, grid: { display: false }, ticks: { font: { size: 9 }, color: '#BDBDBD', maxTicksLimit: 10 }, border: { display: false } },
                                y: { stacked: true, grid: { color: '#F5F5F5' }, ticks: { font: { size: 9 }, color: '#BDBDBD', stepSize: 1 }, border: { display: false }, beginAtZero: true }
                            }
                        }
                    });
                }
            }">
                <canvas x-ref="phChart" style="height:160px;"></canvas>
            </div>
            @endif

            {{-- Recent Activity Feed --}}
            @if(!empty($ph['recent']))
            <div style="margin-top:14px;border-top:1px solid #F5F5F5;padding-top:10px;">
                <div style="font-size:0.6rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:8px;">Recent Activity</div>
                <div style="display:flex;flex-direction:column;gap:5px;max-height:220px;overflow-y:auto;">
                    @foreach($ph['recent'] as $entry)
                    <div style="display:flex;align-items:center;gap:8px;padding:5px 8px;border-radius:8px;background:#FAFAFA;">
                        <span style="font-size:0.6rem;font-weight:700;padding:2px 7px;border-radius:10px;background:{{ $entry['bg'] }};color:{{ $entry['color'] }};flex-shrink:0;min-width:48px;text-align:center;">{{ $entry['label'] }}</span>
                        <span style="font-size:0.75rem;color:#212121;font-weight:600;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $entry['product'] }}</span>
                        @if(!empty($entry['note']))
                        <span style="font-size:0.6875rem;color:#9E9E9E;font-style:italic;flex-shrink:0;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $entry['note'] }}</span>
                        @endif
                        <span style="font-size:0.6rem;color:#BDBDBD;flex-shrink:0;">{{ $entry['date'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @else
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:120px;">
                    <svg style="width:32px;height:32px;color:#E0E0E0;margin-bottom:8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div style="font-size:0.75rem;color:#BDBDBD;font-weight:500;">No product activity yet</div>
                    <div style="font-size:0.6rem;color:#E0E0E0;margin-top:2px;">Add, update, or remove products to see history</div>
                </div>
            @endif
        </div>
    </div>

    {{-- ── SLA: Circular ring indicators ── --}}
    @php($sla = $this->slaMetrics)
    <div class="dash-sla-grid">
        <div class="dash-sla-card">
            <div class="dash-sla-ring" style="background:conic-gradient({{ ($sla['acceptance_rate'] ?? 0) >= 90 ? '#2D9F4E' : (($sla['acceptance_rate'] ?? 0) >= 70 ? '#F9C74F' : '#E74C3C') }} {{ min(($sla['acceptance_rate'] ?? 0),100) }}%, #EEEEEE 0);">
                <div class="dash-sla-ring-inner" style="color:{{ ($sla['acceptance_rate'] ?? 0) >= 90 ? '#2D9F4E' : (($sla['acceptance_rate'] ?? 0) >= 70 ? '#F9C74F' : '#E74C3C') }};">{{ number_format(($sla['acceptance_rate'] ?? 0),1) }}%</div>
            </div>
            <div class="dash-sla-title">Acceptance</div>
            <div class="dash-sla-sub">{{ $sla['accepted_orders'] }} / {{ $sla['acceptance_scope'] }} orders</div>
        </div>

        <div class="dash-sla-card">
            <div class="dash-sla-ring" style="background:conic-gradient({{ ($sla['on_time_ship_rate'] ?? 0) >= 80 ? '#2D9F4E' : (($sla['on_time_ship_rate'] ?? 0) >= 60 ? '#F9C74F' : '#E74C3C') }} {{ min(($sla['on_time_ship_rate'] ?? 0),100) }}%, #EEEEEE 0);">
                <div class="dash-sla-ring-inner" style="color:{{ ($sla['on_time_ship_rate'] ?? 0) >= 80 ? '#2D9F4E' : (($sla['on_time_ship_rate'] ?? 0) >= 60 ? '#F9C74F' : '#E74C3C') }};">{{ number_format(($sla['on_time_ship_rate'] ?? 0),1) }}%</div>
            </div>
            <div class="dash-sla-title">On-Time Ship</div>
            <div class="dash-sla-sub">48h SLA &middot; {{ $sla['on_time_shipments'] }} / {{ $sla['shipment_scope'] }}</div>
        </div>

        <div class="dash-sla-card">
            <div class="dash-sla-ring" style="background:conic-gradient({{ ($sla['cancellation_rate'] ?? 0) <= 5 ? '#2D9F4E' : (($sla['cancellation_rate'] ?? 0) <= 20 ? '#F9C74F' : '#E74C3C') }} {{ min(($sla['cancellation_rate'] ?? 0),100) }}%, #EEEEEE 0);">
                <div class="dash-sla-ring-inner" style="color:{{ ($sla['cancellation_rate'] ?? 0) <= 5 ? '#2D9F4E' : (($sla['cancellation_rate'] ?? 0) <= 20 ? '#F9C74F' : '#E74C3C') }};">{{ number_format(($sla['cancellation_rate'] ?? 0),1) }}%</div>
            </div>
            <div class="dash-sla-title">Cancellation</div>
            <div class="dash-sla-sub">{{ $sla['cancelled_orders'] }} / {{ $sla['cancellation_scope'] }} orders</div>
        </div>

        <div class="dash-sla-card">
            <div class="dash-sla-ring" style="background:conic-gradient({{ ($sla['return_rate'] ?? 0) <= 5 ? '#2D9F4E' : (($sla['return_rate'] ?? 0) <= 15 ? '#F9C74F' : '#E74C3C') }} {{ min(($sla['return_rate'] ?? 0),100) }}%, #EEEEEE 0);">
                <div class="dash-sla-ring-inner" style="color:{{ ($sla['return_rate'] ?? 0) <= 5 ? '#2D9F4E' : (($sla['return_rate'] ?? 0) <= 15 ? '#F9C74F' : '#E74C3C') }};">{{ number_format(($sla['return_rate'] ?? 0),1) }}%</div>
            </div>
            <div class="dash-sla-title">Return Rate</div>
            <div class="dash-sla-sub">{{ $sla['returned_orders'] }} / {{ $sla['return_scope'] }} delivered</div>
        </div>
    </div>

    {{-- ── Order Pipeline: single unified card ── --}}
    <div class="dash-pipeline">
        <div class="dash-pipeline-header">Order Pipeline</div>
        <div class="dash-pipeline-track">
            <a href="{{ route('seller.orders', ['status' => 'paid']) }}" class="dash-pipeline-step" style="text-decoration:none;">
                <div style="position:absolute;top:0;left:0;right:0;height:3px;background:#F9C74F;"></div>
                <div class="dash-pipeline-label" style="color:#F57C00;">Pending</div>
                <div class="dash-pipeline-num" style="color:#F57C00;">{{ $this->stats['orders_processing'] }}</div>
                <div class="dash-pipeline-sub">Awaiting processing</div>
            </a>
            <a href="{{ route('seller.orders', ['status' => 'ready_to_ship']) }}" class="dash-pipeline-step" style="text-decoration:none;">
                <div style="position:absolute;top:0;left:0;right:0;height:3px;background:#4A90D9;"></div>
                <div class="dash-pipeline-label" style="color:#1976D2;">To Ship</div>
                <div class="dash-pipeline-num" style="color:#1976D2;">{{ $this->stats['orders_ready_to_ship'] }}</div>
                <div class="dash-pipeline-sub">Ready for pickup</div>
            </a>
            <a href="{{ route('seller.orders', ['status' => 'completed']) }}" class="dash-pipeline-step" style="text-decoration:none;">
                <div style="position:absolute;top:0;left:0;right:0;height:3px;background:#2D9F4E;"></div>
                <div class="dash-pipeline-label" style="color:#2D9F4E;">Completed</div>
                <div class="dash-pipeline-num" style="color:#2D9F4E;">{{ $this->stats['orders_completed'] }}</div>
                <div class="dash-pipeline-sub">Order received by buyer</div>
            </a>
            <a href="{{ route('seller.orders', ['status' => 'cancelled']) }}" class="dash-pipeline-step" style="text-decoration:none;">
                <div style="position:absolute;top:0;left:0;right:0;height:3px;background:#E74C3C;"></div>
                <div class="dash-pipeline-label" style="color:#E74C3C;">Cancelled</div>
                <div class="dash-pipeline-num" style="color:#E74C3C;">{{ $this->stats['orders_cancelled'] }}</div>
                <div class="dash-pipeline-sub">Cancelled orders</div>
            </a>
            <a href="{{ route('seller.orders', ['status' => 'cancelled']) }}" class="dash-pipeline-step" style="text-decoration:none;">
                <div style="position:absolute;top:0;left:0;right:0;height:3px;background:#9B59B6;"></div>
                <div class="dash-pipeline-label" style="color:#7B1FA2;">Bad Orders</div>
                <div class="dash-pipeline-num" style="color:#7B1FA2;">{{ $this->stats['bad_orders_count'] }}</div>
                <div class="dash-pipeline-sub">{{ number_format($this->stats['bad_orders_percent'], 1) }}% of total</div>
            </a>
        </div>
    </div>



    {{-- ── Bottom Row: Subscription + Quick Actions ── --}}
    <div class="dash-grid dash-grid-2">
        @if($this->seller)
            <div class="dash-card">
                <div class="dash-card-header" style="justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:9px;">
                        <div class="dash-card-icon green">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <h3 class="dash-card-title">Subscription History</h3>
                    </div>
                    <a href="{{ route('seller.payments') }}" class="dash-link" style="margin:0;">View all &rarr;</a>
                </div>
                <div class="dash-card-body" style="padding:0;">
                    @if($this->subscriptionPayments->isNotEmpty())
                        <div class="dash-subscription-list">
                            @foreach($this->subscriptionPayments as $p)
                                <div class="dash-subscription-item" style="padding-left:14px;padding-right:14px;">
                                    <div>
                                        <span class="dash-subscription-date">{{ $p->created_at->format('F Y') }}</span>
                                        <span class="dash-subscription-amount">{{ number_format($p->amount, 2) }}</span>
                                    </div>
                                    <span class="dash-subscription-status {{ $p->status }}">{{ ucfirst($p->status) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div style="padding:18px 14px;"><p class="dash-stat-label">No subscription payments yet.</p></div>
                    @endif
                </div>
            </div>
        @endif
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-icon yellow">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3 class="dash-card-title">Quick Actions</h3>
            </div>
            <div class="dash-card-body">
                <div class="dash-quick-actions">
                    <a href="{{ route('seller.products') }}" class="dash-quick-action"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>Products</a>
                    <a href="{{ route('seller.orders') }}" class="dash-quick-action"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Orders</a>
                    <a href="{{ route('seller.store') }}" class="dash-quick-action"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>Store</a>
                    <a href="{{ route('seller.payments') }}" class="dash-quick-action"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Payments</a>
                    <a href="{{ route('seller.messages') }}" class="dash-quick-action"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>Messages</a>
                </div>
            </div>
        </div>
    </div>

</div>

