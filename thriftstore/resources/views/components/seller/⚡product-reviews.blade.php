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

@push('styles')
@verbatim
<style>
    /* Reviews Brand Styles */
    .rev-container {
        background: linear-gradient(135deg, #FFFEF5 0%, #F8FDF9 100%);
        border: 1px solid #E0E0E0;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .rev-header {
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: white;
        padding: 18px 22px;
        border-radius: 12px;
        margin-bottom: 16px;
        box-shadow: 0 4px 12px rgba(45,159,78,0.25);
    }
    .rev-header h2 {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.01em;
    }
    .rev-header p {
        font-size: 0.8125rem;
        opacity: 0.92;
        margin: 4px 0 0;
    }

    .rev-search {
        background: white;
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 0.8125rem;
        color: #212121;
        transition: all 0.2s;
        width: 280px;
    }
    .rev-search:focus {
        outline: none;
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(249,199,79,0.15);
    }

    .rev-table-wrap {
        background: white;
        border: 1px solid #E0E0E0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .rev-table {
        width: 100%;
        font-size: 0.8125rem;
        border-collapse: collapse;
    }
    .rev-table thead {
        background: #FFFEF5;
    }
    .rev-table th {
        padding: 14px 16px;
        text-align: left;
        font-weight: 600;
        color: #424242;
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 2px solid #FFE17B;
    }
    .rev-table td {
        padding: 16px;
        border-bottom: 1px solid #F5F5F5;
        vertical-align: top;
    }
    .rev-table tr:hover {
        background: linear-gradient(90deg, #F8FDF9 0%, #FFFEF5 100%);
    }
    .rev-table tr:last-child td {
        border-bottom: none;
    }

    .rev-product-name {
        font-weight: 600;
        color: #212121;
    }
    .rev-order-info {
        font-size: 0.6875rem;
        color: #9E9E9E;
        margin-top: 4px;
    }
    .rev-customer-name {
        font-weight: 500;
        color: #212121;
    }
    .rev-customer-email {
        font-size: 0.6875rem;
        color: #9E9E9E;
    }
    .rev-rating-stars {
        color: #F9C74F;
        font-size: 0.875rem;
        letter-spacing: 1px;
    }
    .rev-rating-num {
        font-size: 0.6875rem;
        color: #9E9E9E;
        margin-top: 2px;
    }
    .rev-review-body {
        color: #424242;
        line-height: 1.5;
        max-width: 300px;
    }

    /* Reply section */
    .rev-reply-box {
        background: #FAFAFA;
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        padding: 12px;
        font-size: 0.75rem;
        color: #424242;
        line-height: 1.5;
    }
    .rev-reply-meta {
        font-size: 0.625rem;
        color: #9E9E9E;
        margin-top: 6px;
    }

    /* Form styles */
    .rev-textarea {
        width: 100%;
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 0.75rem;
        resize: vertical;
        transition: all 0.2s;
    }
    .rev-textarea:focus {
        outline: none;
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(249,199,79,0.15);
    }

    /* Buttons */
    .rev-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, #2D9F4E 0%, #F9C74F 100%);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 16px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 2px 8px rgba(45,159,78,0.25);
    }
    .rev-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(45,159,78,0.35);
    }

    .rev-btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: white;
        color: #F57C00;
        border: 1px solid #F9C74F;
        border-radius: 8px;
        padding: 8px 16px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        cursor: pointer;
        transition: all 0.2s;
    }
    .rev-btn-secondary:hover {
        background: #FFF9E6;
        border-color: #E6B340;
    }

    .rev-btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: transparent;
        color: #2D9F4E;
        border: 1px solid #2D9F4E;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 0.6875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .rev-btn-ghost:hover {
        background: #E8F5E9;
    }

    .rev-empty-state {
        padding: 48px 24px;
        text-align: center;
        color: #9E9E9E;
    }
</style>
@endverbatim
@endpush

<div class="rev-container">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
        <div class="rev-header" style="margin-bottom: 0; flex: 1;">
            <h2>Product Reviews</h2>
            <p>See what customers say about your products and reply to their reviews</p>
        </div>
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="Search by product or customer…"
               class="rev-search">
    </div>

    <div class="rev-table-wrap">
        @php($reviews = $this->reviews)
        <table class="rev-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Rating</th>
                    <th>Review</th>
                    <th>Your Reply</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reviews as $review)
                    <tr>
                        <td style="width: 200px;">
                            <div class="rev-product-name">
                                {{ $review->product->name ?? 'Product #'.$review->product_id }}
                            </div>
                            <div class="rev-order-info">
                                Order #{{ $review->order_id }} · {{ optional($review->created_at)->format('M j, Y') }}
                            </div>
                        </td>
                        <td style="width: 160px;">
                            <div class="rev-customer-name">
                                {{ $review->customer->name ?? 'Customer' }}
                            </div>
                            <div class="rev-customer-email">
                                {{ $review->customer->email ?? '' }}
                            </div>
                        </td>
                        <td style="width: 70px;">
                            <div class="rev-rating-stars">
                                {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                            </div>
                            <div class="rev-rating-num">{{ $review->rating }}/5</div>
                        </td>
                        <td>
                            <div class="rev-review-body">
                                {{ $review->body }}
                            </div>
                        </td>
                        <td style="width: 280px;">
                            @if($replyingId === $review->id)
                                <div class="space-y-3">
                                    <textarea wire:model.defer="replyBody" rows="3"
                                              class="rev-textarea"
                                              placeholder="Write a public reply visible to the customer..."></textarea>
                                    @error('replyBody') <div class="text-xs text-[#E53935]">{{ $message }}</div> @enderror
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="saveReply" wire:loading.attr="disabled"
                                                class="rev-btn-primary">
                                            <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Save
                                        </button>
                                        <button type="button" wire:click="cancelReply"
                                                class="rev-btn-secondary">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            @else
                                @if($review->seller_reply)
                                    <div class="rev-reply-box">
                                        {{ $review->seller_reply }}
                                    </div>
                                    <div class="flex items-center justify-between mt-2">
                                        <div class="rev-reply-meta">
                                            Replied {{ optional($review->seller_replied_at)->diffForHumans() }}
                                        </div>
                                        <button type="button" wire:click="startReply({{ $review->id }})"
                                                class="rev-btn-ghost">
                                            Edit
                                        </button>
                                    </div>
                                @else
                                    <button type="button" wire:click="startReply({{ $review->id }})"
                                            class="rev-btn-ghost">
                                        <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                        </svg>
                                        Reply
                                    </button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="rev-empty-state">
                            <div class="w-16 h-16 rounded-full bg-[#F5F5F5] flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-[#9E9E9E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                </svg>
                            </div>
                            <p>No reviews yet for your products.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3 border-t border-[#E0E0E0]">
            {{ $reviews->links() }}
        </div>
    </div>
</div>

