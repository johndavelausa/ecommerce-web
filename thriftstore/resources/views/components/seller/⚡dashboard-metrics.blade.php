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
                OrderDispute::STATUS_RESOLVED_APPROVED,
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

<div class="space-y-6">
    @if($this->seller && $this->seller->subscription_due_date)
        @php
            $days = $this->subscriptionDaysRemaining;
            $colorClass = $days === null ? 'bg-gray-100 text-gray-800' : ($days > 14 ? 'bg-green-100 text-green-800 border-green-200' : ($days >= 7 ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-red-100 text-red-800 border-red-200'));
        @endphp
        <div class="rounded-lg border-2 p-4 {{ $colorClass }}">
            <div class="text-sm font-semibold uppercase tracking-wide opacity-90">Subscription</div>
            <div class="mt-1 text-xl font-bold">
                @if($days !== null && $days > 0)
                    Your subscription expires in {{ $days }} {{ $days === 1 ? 'day' : 'days' }}
                @elseif($days !== null && $days === 0)
                    Your subscription expires today
                @elseif($days !== null && $days < 0)
                    Subscription expired {{ abs($days) }} {{ abs($days) === 1 ? 'day' : 'days' }} ago
                @else
                    Due {{ $this->seller->subscription_due_date->format('M j, Y') }}
                @endif
            </div>
            @if($days !== null && $days > 0 && $days <= 14)
                <p class="mt-1 text-sm opacity-90">
                    @if($days <= 7)
                        Renew soon to avoid store closure (grace period).
                    @else
                        Consider renewing before the due date.
                    @endif
                </p>
            @endif
        </div>
    @endif

    <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white rounded-lg shadow p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Products</div>
            <div class="mt-3 flex items-end justify-between">
                <div>
                    <div class="text-2xl font-semibold text-gray-900">
                        {{ $this->stats['products_total'] }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $this->stats['products_active'] }} active · {{ $this->stats['stock_total'] }} in stock
                    </div>
                </div>
                <a href="{{ route('seller.products') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                    Manage
                </a>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Orders</div>
            <div class="mt-3 flex items-end justify-between">
                <div>
                    <div class="text-2xl font-semibold text-gray-900">
                        {{ $this->stats['orders_total'] }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1 space-y-0.5">
                        <div>Processing: <span class="font-medium text-amber-700">{{ $this->stats['orders_processing'] }}</span></div>
                        <div>Shipped: <span class="font-medium text-blue-700">{{ $this->stats['orders_shipped'] }}</span></div>
                        <div>Delivered: <span class="font-medium text-green-700">{{ $this->stats['orders_delivered'] }}</span></div>
                    </div>
                </div>
                <a href="{{ route('seller.orders') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                    View orders
                </a>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Earnings</div>
            <div class="mt-3 flex items-end justify-between">
                <div>
                    <div class="text-2xl font-semibold text-gray-900">
                        ₱{{ number_format($this->stats['earnings_month'], 2) }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        This month · Total: ₱{{ number_format($this->stats['earnings_total'], 2) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Net Profit</div>
            <div class="mt-3">
                <div class="text-2xl font-semibold text-gray-900">
                    ₱{{ number_format($this->stats['net_profit'], 2) }}
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    From delivered orders (sale/regular price × qty)
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('seller.products') }}" class="bg-white rounded-lg shadow p-5 block hover:bg-gray-50 transition">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Low Stock</div>
            <div class="mt-3 flex items-end justify-between">
                <div class="text-2xl font-semibold text-amber-700">{{ $this->stats['low_stock_count'] }}</div>
                <span class="text-xs text-indigo-600 font-medium">Manage products →</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">products (stock below 10)</div>
        </a>
        <a href="{{ route('seller.products') }}" class="bg-white rounded-lg shadow p-5 block hover:bg-gray-50 transition">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Out of Stock</div>
            <div class="mt-3 flex items-end justify-between">
                <div class="text-2xl font-semibold text-red-700">{{ $this->stats['out_of_stock_count'] }}</div>
                <span class="text-xs text-indigo-600 font-medium">Manage products →</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">products (stock = 0)</div>
        </a>
        <a href="{{ route('seller.reviews') }}" class="bg-white rounded-lg shadow p-5 block hover:bg-gray-50 transition">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Store Rating</div>
            <div class="mt-3 flex items-end justify-between">
                <div class="text-2xl font-semibold text-gray-900">
                    {{ number_format($this->stats['store_rating_avg'], 1) }} <span class="text-base font-normal text-gray-500">/ 5</span>
                </div>
                <span class="text-xs text-indigo-600 font-medium">View reviews →</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">from {{ $this->stats['store_reviews_count'] }} reviews</div>
        </a>
    </div>

    @php($sla = $this->slaMetrics)
    <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white rounded-lg shadow p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Seller acceptance rate</div>
            <div class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($sla['acceptance_rate'], 1) }}%</div>
            <div class="text-xs text-gray-500 mt-1">{{ $sla['accepted_orders'] }} accepted / {{ $sla['acceptance_scope'] }} seller-workflow orders</div>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">On-time ship rate</div>
            <div class="mt-2 text-2xl font-semibold {{ $sla['on_time_ship_rate'] < 80 ? 'text-amber-700' : 'text-gray-900' }}">{{ number_format($sla['on_time_ship_rate'], 1) }}%</div>
            <div class="text-xs text-gray-500 mt-1">{{ $sla['on_time_shipments'] }} on-time / {{ $sla['shipment_scope'] }} shipped orders (48h SLA)</div>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Cancellation rate</div>
            <div class="mt-2 text-2xl font-semibold {{ $sla['cancellation_rate'] > 20 ? 'text-red-700' : 'text-gray-900' }}">{{ number_format($sla['cancellation_rate'], 1) }}%</div>
            <div class="text-xs text-gray-500 mt-1">{{ $sla['cancelled_orders'] }} cancelled / {{ $sla['cancellation_scope'] }} seller-workflow orders</div>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Return rate</div>
            <div class="mt-2 text-2xl font-semibold {{ $sla['return_rate'] > 15 ? 'text-red-700' : 'text-gray-900' }}">{{ number_format($sla['return_rate'], 1) }}%</div>
            <div class="text-xs text-gray-500 mt-1">{{ $sla['returned_orders'] }} returned / {{ $sla['return_scope'] }} delivered-completed orders</div>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('seller.orders', ['status' => 'paid']) }}" class="bg-white rounded-lg shadow p-5 block hover:bg-gray-50 transition">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Pending Orders</div>
            <div class="mt-3 flex items-end justify-between">
                <div class="text-2xl font-semibold text-amber-700">{{ $this->stats['orders_processing'] }}</div>
                <span class="text-xs text-indigo-600 font-medium">View list →</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">Statuses: paid, to_pack, ready_to_ship</div>
        </a>
        <a href="{{ route('seller.orders', ['status' => 'ready_to_ship']) }}" class="bg-white rounded-lg shadow p-5 block hover:bg-gray-50 transition">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Orders to Ship</div>
            <div class="mt-3 flex items-end justify-between">
                <div class="text-2xl font-semibold text-amber-700">{{ $this->stats['orders_ready_to_ship'] }}</div>
                <span class="text-xs text-indigo-600 font-medium">Needs Shipping →</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">Start from ready_to_ship</div>
        </a>
        <a href="{{ route('seller.orders', ['status' => 'delivered']) }}" class="bg-white rounded-lg shadow p-5 block hover:bg-gray-50 transition">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Completed Orders</div>
            <div class="mt-3 flex items-end justify-between">
                <div class="text-2xl font-semibold text-green-700">{{ $this->stats['orders_delivered'] }}</div>
                <span class="text-xs text-indigo-600 font-medium">View list →</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">Status: delivered</div>
        </a>
        <a href="{{ route('seller.orders', ['status' => 'cancelled']) }}" class="bg-white rounded-lg shadow p-5 block hover:bg-gray-50 transition">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Cancelled Orders</div>
            <div class="mt-3 flex items-end justify-between">
                <div class="text-2xl font-semibold text-red-700">{{ $this->stats['orders_cancelled'] }}</div>
                <span class="text-xs text-indigo-600 font-medium">View list →</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">Status: cancelled</div>
        </a>
        <a href="{{ route('seller.orders', ['status' => 'cancelled']) }}" class="bg-white rounded-lg shadow p-5 block hover:bg-gray-50 transition">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Bad Orders</div>
            <div class="mt-3 flex items-end justify-between">
                <div class="text-2xl font-semibold text-red-700">{{ $this->stats['bad_orders_count'] }}</div>
                <span class="text-xs text-indigo-600 font-medium">View list →</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">({{ number_format($this->stats['bad_orders_percent'], 1) }}% of your total orders)</div>
        </a>
    </div>

    {{-- B4 v1.4 — Subscription renewal history timeline --}}
    @if($this->seller)
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Subscription renewal history</h3>
                <a href="{{ route('seller.payments') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">View full payment history →</a>
            </div>
            @if($this->subscriptionPayments->isNotEmpty())
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach($this->subscriptionPayments as $p)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                            <div>
                                <span class="font-medium text-gray-900">{{ $p->created_at->format('F Y') }}</span>
                                <span class="text-gray-500 ml-2">₱{{ number_format($p->amount, 2) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-500">{{ $p->paid_at ? $p->paid_at->format('M j, Y') : $p->created_at->format('M j, Y') }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $p->status === 'approved' ? 'bg-green-100 text-green-800' : ($p->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ ucfirst($p->status) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500">No subscription payments yet.</p>
            @endif
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Quick actions</h3>
                <p class="text-xs text-gray-500 mt-0.5">Jump straight to your most common tasks.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('seller.products') }}"
                   class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Manage products
                </a>
                <a href="{{ route('seller.orders') }}"
                   class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                    View orders
                </a>
                <a href="{{ route('seller.store') }}"
                   class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Store settings
                </a>
                <a href="{{ route('seller.payments') }}"
                   class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Payment history
                </a>
                <a href="{{ route('seller.messages') }}"
                   class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Message admin
                </a>
            </div>
        </div>
    </div>
</div>

