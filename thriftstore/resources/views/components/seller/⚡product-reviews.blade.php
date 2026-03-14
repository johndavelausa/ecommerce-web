<?php

use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $replyingId = null;
    public string $replyBody = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    #[Computed]
    public function seller()
    {
        return Auth::guard('seller')->user()?->seller;
    }

    #[Computed]
    public function reviews()
    {
        $seller = $this->seller;
        if (! $seller) {
            return collect();
        }

        $q = Review::query()
            ->with(['product', 'customer', 'order'])
            ->whereHas('product', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->orderByDesc('created_at');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(function ($query) use ($term) {
                $query->whereHas('product', function ($q2) use ($term) {
                    $q2->where('name', 'like', $term);
                })->orWhereHas('customer', function ($q3) use ($term) {
                    $q3->where('name', 'like', $term)
                       ->orWhere('email', 'like', $term);
                });
            });
        }

        return $q->paginate(10);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function startReply(int $reviewId): void
    {
        $seller = $this->seller;
        if (! $seller) abort(403);

        $review = Review::query()
            ->whereHas('product', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->findOrFail($reviewId);

        $this->replyingId = $review->id;
        $this->replyBody = (string) ($review->seller_reply ?? '');
        $this->resetErrorBag();
    }

    public function cancelReply(): void
    {
        $this->replyingId = null;
        $this->replyBody = '';
        $this->resetErrorBag();
    }

    public function saveReply(): void
    {
        $seller = $this->seller;
        if (! $seller) abort(403);

        if (! $this->replyingId) {
            return;
        }

        $this->validate([
            'replyBody' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = Review::query()
            ->whereHas('product', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->findOrFail($this->replyingId);

        $review->seller_reply = $this->replyBody !== '' ? $this->replyBody : null;
        $review->seller_replied_at = $this->replyBody !== '' ? now() : null;
        $review->save();

        $this->cancelReply();
    }
};
?>

<div class="space-y-4">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-lg font-medium text-gray-900">Product reviews</h3>
            <p class="mt-1 text-sm text-gray-500">
                See what customers say about your products and reply to their reviews.
            </p>
        </div>
        <div class="flex gap-2 items-center">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search by product or customer…"
                   class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-64">
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        @php($reviews = $this->reviews)
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rating</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Review</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Your reply</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($reviews as $review)
                    <tr class="align-top">
                        <td class="px-4 py-3 w-48">
                            <div class="font-medium text-gray-900">
                                {{ $review->product->name ?? 'Product #'.$review->product_id }}
                            </div>
                            <div class="text-xs text-gray-500">
                                Order #{{ $review->order_id }} · {{ optional($review->created_at)->format('Y-m-d') }}
                            </div>
                        </td>
                        <td class="px-4 py-3 w-40">
                            <div class="text-sm text-gray-900">
                                {{ $review->customer->name ?? 'Customer' }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $review->customer->email ?? '' }}
                            </div>
                        </td>
                        <td class="px-4 py-3 w-24">
                            <div class="text-xs text-yellow-400">
                                {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $review->rating }}/5
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-xs text-gray-700 whitespace-pre-wrap">
                                {{ $review->body }}
                            </div>
                        </td>
                        <td class="px-4 py-3 w-64">
                            @if($replyingId === $review->id)
                                <div class="space-y-2">
                                    <textarea wire:model.defer="replyBody" rows="3"
                                              class="block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                              placeholder="Write a public reply visible to the customer."></textarea>
                                    @error('replyBody') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="saveReply" wire:loading.attr="disabled"
                                                class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-md hover:bg-indigo-500">
                                            Save reply
                                        </button>
                                        <button type="button" wire:click="cancelReply"
                                                class="px-3 py-1.5 border rounded-md text-xs text-gray-700 hover:bg-gray-50">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            @else
                                @if($review->seller_reply)
                                    <div class="text-xs text-gray-700 whitespace-pre-wrap border border-gray-200 rounded-md p-2 bg-gray-50">
                                        {{ $review->seller_reply }}
                                    </div>
                                    <div class="mt-1 flex items-center justify-between">
                                        <div class="text-[11px] text-gray-400">
                                            Replied {{ optional($review->seller_replied_at)->diffForHumans() }}
                                        </div>
                                        <button type="button" wire:click="startReply({{ $review->id }})"
                                                class="text-[11px] text-indigo-600 hover:text-indigo-800">
                                            Edit reply
                                        </button>
                                    </div>
                                @else
                                    <button type="button" wire:click="startReply({{ $review->id }})"
                                            class="inline-flex items-center px-3 py-1.5 border border-indigo-500 text-indigo-600 rounded-md text-xs hover:bg-indigo-50">
                                        Reply
                                    </button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 text-sm">
                            No reviews yet for your products.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3 border-t">
            {{ $reviews->links() }}
        </div>
    </div>
</div>

