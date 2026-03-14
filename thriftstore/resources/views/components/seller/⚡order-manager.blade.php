<?php

use App\Models\Order;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $status = ''; // '', processing, shipped, delivered, cancelled
    public string $search = ''; // by tracking number or customer name

    public bool $showDetails = false;
    public ?int $viewOrderId = null;

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
            ->with(['customer', 'items.product'])
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
            ->with(['customer', 'items.product'])
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

    public function updateStatus(int $id, string $status): void
    {
        // Sellers can move orders from processing → shipped or cancelled only.
        // Delivered is controlled by the customer marking as received.
        if (! in_array($status, ['processing', 'shipped', 'cancelled'], true)) {
            return;
        }

        $seller = $this->seller;
        if (! $seller) abort(403);

        $order = Order::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        if ($status === 'shipped') {
            $order->status = 'shipped';
            if (! $order->tracking_number) {
                $order->tracking_number = strtoupper(Str::random(10));
            }
        } elseif ($status === 'cancelled') {
            if ($order->status !== 'processing') {
                return;
            }
            $order->status = 'cancelled';
            if (! $order->cancelled_at) {
                $order->cancelled_at = now();
            }
        }

        $order->save();

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
                <option value="processing">Processing</option>
                <option value="shipped">Shipped</option>
                <option value="delivered">Delivered</option>
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
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-900 text-sm">{{ $order->customer->name ?? 'Guest' }}</div>
                            <div class="text-xs text-gray-500">{{ $order->customer->email ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $statusColor = match($order->status) {
                                    'processing' => 'bg-amber-100 text-amber-800',
                                    'shipped' => 'bg-blue-100 text-blue-800',
                                    'delivered' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                {{ ucfirst($order->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-900">
                            ₱{{ number_format($order->total_amount, 2) }}
                        </td>
                        <td class="px-4 py-3 text-sm space-x-2">
                            <button type="button" wire:click="viewOrder({{ $order->id }})"
                                    class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                View
                            </button>
                            <a href="{{ route('seller.orders.print', $order->id) }}" target="_blank"
                               class="text-xs font-medium text-gray-600 hover:text-gray-900">
                                Print slip
                            </a>

                            @if($order->status === 'processing')
                                <button type="button" wire:click="updateStatus({{ $order->id }}, 'shipped')"
                                        class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    Mark shipped
                                </button>
                                <button type="button" wire:click="updateStatus({{ $order->id }}, 'cancelled')"
                                        class="text-xs text-red-600 hover:text-red-800 font-medium">
                                    Cancel
                                </button>
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
            $order = \App\Models\Order::with(['customer', 'items.product'])->find($viewOrderId);
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
</div>

