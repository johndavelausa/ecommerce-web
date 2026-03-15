<?php

use App\Models\ProductReport;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    protected $queryString = ['search' => ['except' => '']];

    #[Computed]
    public function reports()
    {
        $q = ProductReport::query()
            ->with(['product.seller', 'customer'])
            ->orderByDesc('created_at');

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $q->where(function ($query) use ($term) {
                $query->where('reason', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('product', fn ($q) => $q->where('name', 'like', $term))
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });
        }

        return $q->paginate(20);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div class="space-y-4">
    <div class="flex gap-2">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="Search by product, customer, reason…"
               class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-full max-w-md">
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($this->reports as $report)
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $report->created_at->format('M j, Y H:i') }}</td>
                        <td class="px-4 py-2 text-sm">
                            <a href="{{ route('product.show', $report->product_id) }}" class="text-indigo-600 hover:underline" target="_blank">{{ $report->product->name ?? '—' }}</a>
                            @if($report->product->seller)
                                <span class="text-gray-400 text-xs">({{ $report->product->seller->store_name }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-sm">{{ $report->customer->name ?? '—' }} <span class="text-gray-400 text-xs">{{ $report->customer->email ?? '' }}</span></td>
                        <td class="px-4 py-2 text-sm">{{ \App\Models\ProductReport::reasonOptions()[$report->reason] ?? $report->reason }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600 max-w-xs truncate">{{ $report->description ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 text-sm">No product reports yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-2 border-t">
            {{ $this->reports->links() }}
        </div>
    </div>
</div>
