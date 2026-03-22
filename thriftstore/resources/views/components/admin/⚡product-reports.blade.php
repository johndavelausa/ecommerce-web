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

<style>
    .rpt-search { border-radius: 50px; border: 1.5px solid #D4E8DA; padding: 8px 16px; font-size: 0.8125rem; background: #fff; color: #424242; transition: all 0.15s; }
    .rpt-search:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .rpt-table-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; overflow: hidden; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .rpt-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .rpt-table th { padding: 9px 16px; text-align: left; font-size: 0.6875rem; font-weight: 700; color: #1B7A37; text-transform: uppercase; letter-spacing: 0.05em; background: #F5FBF7; border-bottom: 1px solid #D4E8DA; }
    .rpt-table td { padding: 9px 16px; color: #424242; border-bottom: 1px solid #F0F7F2; vertical-align: top; }
    .rpt-table tr:last-child td { border-bottom: none; }
    .rpt-table tr:hover td { background: #F5FBF7; }
    .rpt-reason-badge { background: #FFF3E0; color: #E65100; padding: 3px 8px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
    .rpt-product-link { color: #2D9F4E; font-weight: 600; text-decoration: none; }
    .rpt-product-link:hover { color: #1B7A37; text-decoration: underline; }
</style>

<div class="space-y-4">
    <div class="flex gap-2">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="Search by product, customer, reason…"
               class="rpt-search w-full max-w-md">
    </div>

    <div class="rpt-table-card">
        <table class="rpt-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Reason</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->reports as $report)
                    <tr>
                        <td style="color:#9E9E9E;font-style:italic;font-size:0.8125rem;white-space:nowrap;">{{ $report->created_at->format('M j, Y H:i') }}</td>
                        <td>
                            <a href="{{ route('product.show', $report->product_id) }}" class="rpt-product-link" target="_blank">{{ $report->product->name ?? '—' }}</a>
                            @if($report->product->seller)
                                <div style="font-size:0.75rem;color:#9E9E9E;font-style:italic;margin-top:2px;">{{ $report->product->seller->store_name }}</div>
                            @endif
                        </td>
                        <td>
                            <div style="color:#0F3D22;font-weight:600;">{{ $report->customer->name ?? '—' }}</div>
                            <div style="font-size:0.75rem;color:#9E9E9E;font-style:italic;">{{ $report->customer->email ?? '' }}</div>
                        </td>
                        <td><span class="rpt-reason-badge">{{ \App\Models\ProductReport::reasonOptions()[$report->reason] ?? $report->reason }}</span></td>
                        <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#757575;font-style:italic;font-size:0.8125rem;">{{ $report->description ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;padding:32px 16px;color:#9E9E9E;font-style:italic;">No product reports yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div style="padding:12px 16px;border-top:1px solid #D4E8DA;">
            {{ $this->reports->links() }}
        </div>
    </div>
</div>
