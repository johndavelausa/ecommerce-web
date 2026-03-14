<?php

use App\Models\Order;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public ?int $editingProductId = null;
    public ?int $editingOrderId = null;
    public int $rating = 5;
    public string $body = '';

    public function getCustomerProperty()
    {
        return Auth::guard('web')->user();
    }

    public function getPendingItemsProperty()
    {
        $customer = $this->customer;
        if (! $customer) {
            return collect();
        }

        $orders = Order::query()
            ->with(['items.product', 'seller'])
            ->where('customer_id', $customer->id)
            ->where('status', 'delivered')
            ->orderByDesc('created_at')
            ->get();

        $items = collect();

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $hasReview = Review::where('customer_id', $customer->id)
                    ->where('order_id', $order->id)
                    ->where('product_id', $item->product_id)
                    ->exists();

                if (! $hasReview) {
                    $items->push([
                        'order_id'   => $order->id,
                        'order_date' => optional($order->created_at)->format('Y-m-d'),
                        'seller'     => $order->seller?->store_name ?? 'Seller #'.$order->seller_id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name ?? 'Product #'.$item->product_id,
                    ]);
                }
            }
        }

        return $items;
    }

    public function getMyReviewsProperty()
    {
        $customer = $this->customer;
        if (! $customer) {
            return collect();
        }

        return Review::query()
            ->with(['product', 'order'])
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    public function startReview(int $orderId, int $productId): void
    {
        $this->editingOrderId = $orderId;
        $this->editingProductId = $productId;
        $this->rating = 5;
        $this->body = '';
    }

    public function cancelReview(): void
    {
        $this->editingOrderId = null;
        $this->editingProductId = null;
        $this->rating = 5;
        $this->body = '';
        $this->resetErrorBag();
    }

    public function submit(): void
    {
        $customer = $this->customer;
        if (! $customer) {
            abort(403);
        }

        if (! $this->editingOrderId || ! $this->editingProductId) {
            return;
        }

        $this->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body'   => ['required', 'string', 'max:2000'],
        ]);

        $order = Order::query()
            ->where('id', $this->editingOrderId)
            ->where('customer_id', $customer->id)
            ->where('status', 'delivered')
            ->firstOrFail();

        $hasItem = $order->items()->where('product_id', $this->editingProductId)->exists();
        if (! $hasItem) {
            return;
        }

        Review::firstOrCreate(
            [
                'customer_id' => $customer->id,
                'order_id'    => $order->id,
                'product_id'  => $this->editingProductId,
            ],
            [
                'rating' => $this->rating,
                'body'   => $this->body,
            ]
        );

        $this->cancelReview();
    }
};
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900">Product reviews</h3>
        <p class="mt-1 text-sm text-gray-500">
            You can only review items from delivered orders.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="bg-white rounded-lg shadow p-4 sm:p-6 space-y-4">
            <h4 class="text-sm font-semibold text-gray-900">Pending reviews</h4>
            @php($pending = $this->pendingItems)
            @if($pending->isEmpty())
                <div class="py-4 text-sm text-gray-500">
                    No items waiting for a review.
                </div>
            @else
                <ul class="divide-y divide-gray-200 text-sm">
                    @foreach($pending as $row)
                        <li class="py-3 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-medium text-gray-900">
                                    {{ $row['product_name'] }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Order #{{ $row['order_id'] }} · {{ $row['order_date'] }} · {{ $row['seller'] }}
                                </div>
                            </div>
                            <button type="button"
                                    wire:click="startReview({{ $row['order_id'] }}, {{ $row['product_id'] }})"
                                    class="inline-flex items-center px-3 py-1.5 border border-indigo-500 text-indigo-600 rounded-md text-xs hover:bg-indigo-50">
                                Write review
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow p-4 sm:p-6 space-y-4">
            <h4 class="text-sm font-semibold text-gray-900">
                {{ $editingProductId ? 'Write a review' : 'Your latest reviews' }}
            </h4>

            @if($editingProductId)
                <form wire:submit.prevent="submit" class="space-y-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">Rating</label>
                        <div class="mt-1 flex items-center gap-1">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button"
                                        wire:click="$set('rating', {{ $i }})"
                                        class="{{ $rating >= $i ? 'text-yellow-400' : 'text-gray-300' }}">
                                    ★
                                </button>
                            @endfor
                            <span class="ml-1 text-xs text-gray-500">{{ $rating }}/5</span>
                        </div>
                        @error('rating') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">Comment</label>
                        <textarea wire:model.defer="body" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Share what you liked or didn’t like about this item."></textarea>
                        @error('body') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Submit review
                        </button>
                        <button type="button" wire:click="cancelReview"
                                class="inline-flex items-center px-4 py-2 border rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            @else
                @php($reviews = $this->myReviews)
                @if($reviews->isEmpty())
                    <div class="py-4 text-sm text-gray-500">
                        You haven’t written any reviews yet.
                    </div>
                @else
                    <ul class="divide-y divide-gray-200 text-sm">
                        @foreach($reviews as $review)
                            <li class="py-3 space-y-1">
                                <div class="flex items-center justify-between">
                                    <div class="font-medium text-gray-900">
                                        {{ $review->product->name ?? 'Product #'.$review->product_id }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ optional($review->created_at)->format('Y-m-d') }}
                                    </div>
                                </div>
                                <div class="text-xs text-yellow-400">
                                    {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                                </div>
                                <p class="text-sm text-gray-700">
                                    {{ $review->body }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </div>
</div>

