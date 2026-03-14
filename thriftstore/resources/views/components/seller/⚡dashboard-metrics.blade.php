<?php

use App\Models\Order;
use App\Models\Product;
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
    public function stats()
    {
        $seller = $this->seller;
        if (! $seller) {
            return [
                'products_total' => 0,
                'products_active' => 0,
                'stock_total' => 0,
                'orders_total' => 0,
                'orders_processing' => 0,
                'orders_shipped' => 0,
                'orders_delivered' => 0,
                'orders_cancelled' => 0,
                'earnings_total' => 0,
                'earnings_month' => 0,
            ];
        }

        $productQuery = Product::query()->where('seller_id', $seller->id);

        $productsTotal = (clone $productQuery)->count();
        $productsActive = (clone $productQuery)->where('is_active', true)->count();
        $stockTotal = (clone $productQuery)->sum('stock');

        $orderBase = Order::query()
            ->where('seller_id', $seller->id);

        $ordersTotal = (clone $orderBase)->count();
        $ordersProcessing = (clone $orderBase)->where('status', 'processing')->count();
        $ordersShipped = (clone $orderBase)->where('status', 'shipped')->count();
        $ordersDelivered = (clone $orderBase)->where('status', 'delivered')->count();
        $ordersCancelled = (clone $orderBase)->where('status', 'cancelled')->count();

        $earningsTotal = (clone $orderBase)->where('status', 'delivered')->sum('total_amount');
        $earningsMonth = (clone $orderBase)
            ->where('status', 'delivered')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total_amount');

        return [
            'products_total' => $productsTotal,
            'products_active' => $productsActive,
            'stock_total' => $stockTotal,
            'orders_total' => $ordersTotal,
            'orders_processing' => $ordersProcessing,
            'orders_shipped' => $ordersShipped,
            'orders_delivered' => $ordersDelivered,
            'orders_cancelled' => $ordersCancelled,
            'earnings_total' => $earningsTotal,
            'earnings_month' => $earningsMonth,
        ];
    }
};
?>

<div class="space-y-6">
    <div class="grid gap-5 md:grid-cols-3">
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
    </div>

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
                <a href="{{ route('seller.message-admin') }}"
                   class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Message admin
                </a>
            </div>
        </div>
    </div>
</div>

