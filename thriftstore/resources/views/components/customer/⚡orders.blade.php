<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $status = ''; // '', processing, shipped, delivered, cancelled

    protected $queryString = [
        'status' => ['except' => ''],
    ];

    public ?int $issueOrderId = null;
    public string $issueBody = '';

    // Store rating modal state
    public ?int $rateOrderId = null;
    public int $storeRating = 5;
    public string $storeReview = '';

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
            ->where('status', 'delivered')
            ->findOrFail($orderId);

        $this->issueOrderId = $order->id;
        $this->issueBody = '';
        $this->resetErrorBag();
    }

    public function closeIssueModal(): void
    {
        $this->issueOrderId = null;
        $this->issueBody = '';
        $this->resetErrorBag();
    }

    public function submitIssue(): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer || ! $this->issueOrderId) {
            return;
        }

        $this->validate([
            'issueBody' => ['required', 'string', 'max:2000'],
        ]);

        $order = Order::query()
            ->with('seller')
            ->where('customer_id', $customer->id)
            ->where('status', 'delivered')
            ->findOrFail($this->issueOrderId);

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

        $body = "Return / issue request for Order #{$order->id} (Tracking: ".($order->tracking_number ?? 'N/A')."):\n\n"
              . trim($this->issueBody);

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

    public function openRateModal(int $orderId): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return;
        }

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'delivered')
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
            ->where('status', 'delivered')
            ->whereNull('store_rating')
            ->findOrFail($this->rateOrderId);

        $order->store_rating = $this->storeRating;
        $order->store_review = $this->storeReview !== '' ? $this->storeReview : null;
        $order->save();

        $this->closeRateModal();
    }

    public function reorder(int $id): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) abort(403);

        $order = Order::query()
            ->with('items.product.seller')
            ->where('customer_id', $customer->id)
            ->where('status', 'delivered')
            ->findOrFail($id);

        $cart = Session::get('cart', []);

        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product || ! $product->is_active || $product->stock <= 0) {
                continue;
            }

            $seller = $product->seller;
            if (! $seller || $seller->status !== 'approved' || ! $seller->is_open) {
                continue;
            }

            $key = (string) $product->id;
            $currentQty = $cart[$key]['quantity'] ?? 0;
            $desiredQty = $currentQty + $item->quantity;
            $finalQty = min($desiredQty, $product->stock);
            if ($finalQty <= 0) {
                continue;
            }

            $cart[$key] = [
                'product_id' => $product->id,
                'seller_id'  => $product->seller_id,
                'name'       => $product->name,
                'price'      => (float) ($product->sale_price ?? $product->price),
                'image_path' => $product->image_path,
                'quantity'   => $finalQty,
            ];
        }

        Session::put('cart', $cart);
        $this->dispatch('cart-updated');
    }

    public function getOrdersProperty()
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return collect();
        }

        $q = Order::query()
            ->with(['items.product', 'seller'])
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        if ($this->status !== '') {
            $q->where('status', $this->status);
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

        if ($order->status !== 'shipped') {
            return;
        }

        $order->status = 'delivered';
        $order->save();
    }

    public function cancel(int $id): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) abort(403);

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        if ($order->status !== 'processing') {
            return;
        }

        // Enforce 30-minute cancellation window
        if ($order->created_at && now()->diffInMinutes($order->created_at) > 30) {
            return;
        }

        $order->status = 'cancelled';
        $order->cancelled_at = now();
        $order->save();
    }
};
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-lg font-medium text-gray-900">My Orders</h3>
            <p class="text-sm text-gray-500 mt-1">
                Track your orders and confirm when you have received them.
            </p>
        </div>
        <div>
            <select wire:model.live="status"
                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All statuses</option>
                <option value="processing">Processing</option>
                <option value="shipped">Shipped</option>
                <option value="delivered">Delivered</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php $orders = $this->orders; ?>
        <?php if($orders instanceof \Illuminate\Pagination\LengthAwarePaginator && $orders->count()): ?>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store rating</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php foreach($orders as $order): ?>
                        <tr>
                            <td class="px-4 py-3 text-xs text-gray-500 align-top">
                                {{ optional($order->created_at)->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 align-top">
                                {{ $order->seller->store_name ?? 'Seller #'.$order->seller_id }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700 align-top">
                                <ul class="space-y-1">
                                    <?php foreach($order->items as $item): ?>
                                        <li>
                                            {{ $item->quantity }} × {{ $item->product->name ?? 'Product #'.$item->product_id }}
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <?php
                                    $badge = match($order->status) {
                                        'processing' => 'bg-amber-100 text-amber-800',
                                        'shipped' => 'bg-blue-100 text-blue-800',
                                        'delivered' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 align-top text-xs text-gray-700">
                                <?php if($order->store_rating): ?>
                                    <div class="flex items-center gap-1">
                                        <span class="text-yellow-400">
                                            {{ str_repeat('★', $order->store_rating) }}{{ str_repeat('☆', 5 - $order->store_rating) }}
                                        </span>
                                        <span>{{ $order->store_rating }}/5</span>
                                    </div>
                                    <?php if($order->store_review): ?>
                                        <div class="mt-1 text-[11px] text-gray-500 line-clamp-2">
                                            {{ $order->store_review }}
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Not rated</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 font-medium align-top">
                                ₱{{ number_format($order->total_amount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-xs align-top space-y-1">
                                <?php if($order->status === 'processing'): ?>
                                    <button type="button"
                                            wire:click="cancel({{ $order->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-50">
                                        Cancel
                                    </button>
                                <?php elseif($order->status === 'shipped'): ?>
                                    <button type="button"
                                            wire:click="markReceived({{ $order->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-indigo-500 text-indigo-600 rounded-md text-xs hover:bg-indigo-50">
                                        Mark received
                                    </button>
                                <?php elseif($order->status === 'delivered'): ?>
                                    <button type="button"
                                            wire:click="openIssueModal({{ $order->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-50">
                                        Return / issue
                                    </button>
                                    <button type="button"
                                            wire:click="reorder({{ $order->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-indigo-500 text-indigo-600 rounded-md text-xs hover:bg-indigo-50">
                                        Re-order
                                    </button>
                                    <?php if(!$order->store_rating): ?>
                                        <button type="button"
                                                wire:click="openRateModal({{ $order->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-amber-500 text-amber-700 rounded-md text-xs hover:bg-amber-50">
                                            Rate seller
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="px-4 py-3 border-t">
                {{ $orders->links() }}
            </div>
        <?php else: ?>
            <div class="py-12 text-center text-gray-500 text-sm">
                You have no orders yet.
            </div>
        <?php endif; ?>
    </div>
    @if($issueOrderId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-900">Return / Issue request</h3>
                <p class="text-xs text-gray-500">
                    This message will be sent to the seller of your order. Describe the problem (wrong item, damaged, missing pieces, etc.).
                </p>
                <textarea wire:model.defer="issueBody" rows="4"
                          class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Example: The shirt has a tear on the sleeve."></textarea>
                @error('issueBody') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeIssueModal"
                            class="px-3 py-1.5 border rounded-md text-xs text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitIssue"
                            class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs hover:bg-indigo-700">
                        Send request
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($rateOrderId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-900">Rate this seller / store</h3>
                <p class="text-xs text-gray-500">
                    This rating is for the overall store experience (communication, speed, packaging), not a specific product.
                </p>
                <div>
                    <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">Rating</label>
                    <div class="mt-1 flex items-center gap-1">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button"
                                    wire:click="$set('storeRating', {{ $i }})"
                                    class="{{ $storeRating >= $i ? 'text-yellow-400' : 'text-gray-300' }}">
                                ★
                            </button>
                        @endfor
                        <span class="ml-1 text-xs text-gray-500">{{ $storeRating }}/5</span>
                    </div>
                    @error('storeRating') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">Comment <span class="text-gray-400">(optional)</span></label>
                    <textarea wire:model.defer="storeReview" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Example: Seller was very responsive and items were well packed."></textarea>
                    @error('storeReview') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeRateModal"
                            class="px-3 py-1.5 border rounded-md text-xs text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitRating"
                            class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs hover:bg-indigo-700">
                        Save rating
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

