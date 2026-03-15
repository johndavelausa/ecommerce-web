<?php

use App\Models\Payment;
use App\Models\Order;
use App\Models\SellerPayout;
use App\Notifications\SellerPayoutReleased;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $payoutStatusFilter = '';

    public bool $showHoldPayoutModal = false;
    public ?int $holdPayoutId = null;
    public string $holdPayoutReason = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function mount(): void
    {
        if ($this->dateFrom === '' && $this->dateTo === '') {
            $this->dateFrom = now()->subDays(30)->toDateString();
            $this->dateTo = now()->toDateString();
        }
    }

    #[Computed]
    public function payments()
    {
        $q = Payment::query()
            ->with('seller.user')
            ->orderByDesc('created_at');

        if ($this->statusFilter !== '') {
            $q->where('status', $this->statusFilter);
        }

        if ($this->dateFrom !== '') {
            $q->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $q->whereDate('created_at', '<=', $this->dateTo);
        }

        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $q->where(function ($query) use ($term) {
                $query->where('reference_number', 'like', $term)
                    ->orWhere('gcash_number', 'like', $term)
                    ->orWhereHas('seller', function ($s) use ($term) {
                        $s->where('store_name', 'like', $term)
                            ->orWhereHas('user', function ($u) use ($term) {
                                $u->where('name', 'like', $term)->orWhere('email', 'like', $term);
                            });
                    });
            });
        }

        return $q->paginate(20);
    }

    #[Computed]
    public function payouts()
    {
        $q = SellerPayout::query()
            ->with(['seller.user', 'order'])
            ->orderByDesc('created_at');

        if ($this->payoutStatusFilter !== '') {
            $q->where('status', $this->payoutStatusFilter);
        }

        if ($this->dateFrom !== '') {
            $q->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $q->whereDate('created_at', '<=', $this->dateTo);
        }

        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $q->where(function ($query) use ($term) {
                $query->where('order_id', 'like', $term)
                    ->orWhere('hold_reason', 'like', $term)
                    ->orWhereHas('seller', function ($s) use ($term) {
                        $s->where('store_name', 'like', $term)
                            ->orWhereHas('user', function ($u) use ($term) {
                                $u->where('name', 'like', $term)->orWhere('email', 'like', $term);
                            });
                    });
            });
        }

        return $q->paginate(20, ['*'], 'payouts_page');
    }

    #[Computed]
    public function payoutSummary()
    {
        $released = SellerPayout::query()->where('status', SellerPayout::STATUS_RELEASED);
        $onHold = SellerPayout::query()->where('status', SellerPayout::STATUS_ON_HOLD);

        return (object) [
            'released_total' => (float) $released->sum('net_amount'),
            'on_hold_total' => (float) $onHold->sum('net_amount'),
            'released_count' => (int) $released->count(),
            'on_hold_count' => (int) $onHold->count(),
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->resetPage('payouts_page');
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPayoutStatusFilter(): void
    {
        $this->resetPage('payouts_page');
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
        $this->resetPage('payouts_page');
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
        $this->resetPage('payouts_page');
    }

    public function openHoldPayoutModal(int $payoutId): void
    {
        $this->holdPayoutId = $payoutId;
        $this->holdPayoutReason = '';
        $this->showHoldPayoutModal = true;
        $this->resetErrorBag();
    }

    public function closeHoldPayoutModal(): void
    {
        $this->holdPayoutId = null;
        $this->holdPayoutReason = '';
        $this->showHoldPayoutModal = false;
        $this->resetErrorBag();
    }

    public function confirmHoldPayout(): void
    {
        $this->validate([
            'holdPayoutReason' => ['required', 'string', 'max:100'],
        ]);

        $payout = SellerPayout::query()->find($this->holdPayoutId);
        if (! $payout) {
            $this->closeHoldPayoutModal();
            return;
        }

        $payout->status = SellerPayout::STATUS_ON_HOLD;
        $payout->hold_reason = trim($this->holdPayoutReason);
        $payout->released_at = null;
        $payout->save();

        $this->closeHoldPayoutModal();
    }

    public function releasePayout(int $payoutId): void
    {
        $payout = SellerPayout::query()->with(['seller.user', 'order.disputes'])->find($payoutId);
        if (! $payout) {
            return;
        }

        $order = $payout->order;
        if ($order) {
            $decision = SellerPayout::decisionFromDisputes(collect($order->disputes));
            if ($decision['status'] === SellerPayout::STATUS_ON_HOLD) {
                $this->addError('payoutStatusFilter', 'Payout cannot be released while dispute outcome requires hold.');
                return;
            }

            if ($order->refund_status === Order::REFUND_STATUS_COMPLETED) {
                $this->addError('payoutStatusFilter', 'Payout cannot be released for fully refunded orders.');
                return;
            }
        }

        $payout->status = SellerPayout::STATUS_RELEASED;
        $payout->hold_reason = null;
        $payout->released_at = now();
        $payout->save();

        $payout->seller?->user?->notify(new SellerPayoutReleased($payout));
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap gap-2 items-end">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search seller name, reference #, GCash..."
                   class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-64">
            <div class="flex gap-2 items-center text-sm">
                <label class="text-gray-600">From</label>
                <input type="date" wire:model.live="dateFrom"
                       class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="flex gap-2 items-center text-sm">
                <label class="text-gray-600">To</label>
                <input type="date" wire:model.live="dateTo"
                       class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <select wire:model.live="statusFilter"
                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        <div class="text-xs text-gray-500">
            Showing {{ $this->payments->firstItem() ?? 0 }}–{{ $this->payments->lastItem() ?? 0 }} of {{ $this->payments->total() }} payments
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($this->payments as $p)
                    <tr>
                        <td class="px-4 py-3 text-gray-700">{{ $p->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-gray-900">{{ $p->seller?->store_name ?? '—' }}<br><span class="text-xs text-gray-500">{{ $p->seller?->user?->name ?? '' }}</span></td>
                        <td class="px-4 py-3 text-gray-700">{{ ucfirst($p->type) }}</td>
                        <td class="px-4 py-3 text-right font-medium">₱{{ number_format($p->amount, 2) }}</td>
                        <td class="px-4 py-3 text-gray-700 font-mono text-xs">{{ $p->reference_number }}</td>
                        <td class="px-4 py-3">
                            @php
                                $badge = match($p->status) {
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'pending' => 'bg-amber-100 text-amber-800',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <span class="px-2 py-1 rounded text-xs font-medium {{ $badge }}">{{ ucfirst($p->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No payments match your filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($this->payments->hasPages())
            <div class="px-4 py-2 border-t">
                {{ $this->payments->links() }}
            </div>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow p-4 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Seller payouts</h3>
                <p class="text-xs text-gray-500 mt-0.5">Manual hold/release controls for payout records.</p>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600">Payout status</label>
                <select wire:model.live="payoutStatusFilter"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    <option value="released">Released</option>
                    <option value="on_hold">On hold</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
        </div>

        @php($payoutSummary = $this->payoutSummary)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="rounded-lg border border-green-200 bg-green-50 p-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-green-700">Released payouts</div>
                <div class="mt-1 text-xl font-semibold text-green-800">₱{{ number_format($payoutSummary->released_total, 2) }}</div>
                <div class="text-xs text-green-700 mt-1">{{ $payoutSummary->released_count }} records</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">On hold payouts</div>
                <div class="mt-1 text-xl font-semibold text-amber-800">₱{{ number_format($payoutSummary->on_hold_total, 2) }}</div>
                <div class="text-xs text-amber-700 mt-1">{{ $payoutSummary->on_hold_count }} records</div>
            </div>
        </div>

        <div class="bg-white rounded-lg border overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($this->payouts as $sp)
                        <tr>
                            <td class="px-4 py-3 text-gray-700">{{ $sp->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $sp->seller?->store_name ?? '—' }}<br><span class="text-xs text-gray-500">{{ $sp->seller?->user?->name ?? '' }}</span></td>
                            <td class="px-4 py-3 text-gray-700 font-medium">
                                #{{ $sp->order_id }}
                                @if($sp->order?->refund_status)
                                    <div class="mt-1 text-xs text-gray-500">
                                        Refund: {{ \App\Models\Order::refundStatusLabel($sp->order->refund_status) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900">₱{{ number_format((float) $sp->net_amount, 2) }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $badge = match($sp->status) {
                                        'released' => 'bg-green-100 text-green-800',
                                        'on_hold' => 'bg-amber-100 text-amber-800',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $sp->status)) }}</span>
                                @if($sp->status === 'on_hold' && $sp->hold_reason)
                                    <div class="text-xs text-gray-500 mt-1">{{ ucfirst(str_replace('_', ' ', $sp->hold_reason)) }}</div>
                                @endif
                                @if($sp->order?->refunded_at)
                                    <div class="text-xs text-gray-500 mt-1">Order refunded at {{ $sp->order->refunded_at->format('Y-m-d H:i') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if($sp->status === 'released')
                                    <button type="button" wire:click="openHoldPayoutModal({{ $sp->id }})"
                                            class="px-2 py-1 bg-amber-50 border border-amber-200 text-amber-700 rounded hover:bg-amber-100">Put on hold</button>
                                @elseif($sp->status === 'on_hold')
                                    <button type="button" wire:click="releasePayout({{ $sp->id }})"
                                            class="px-2 py-1 bg-green-50 border border-green-200 text-green-700 rounded hover:bg-green-100">Release now</button>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No payout records match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if($this->payouts->hasPages())
                <div class="px-4 py-2 border-t">
                    {{ $this->payouts->links() }}
                </div>
            @endif
        </div>
    </div>

    @if($showHoldPayoutModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Put payout on hold</h3>
                    <p class="text-sm text-gray-500 mt-1">Provide a short hold reason for audit visibility.</p>
                </div>
                <div class="px-6 py-4 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Hold reason code</label>
                        <input type="text" wire:model.defer="holdPayoutReason" maxlength="100"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. manual_risk_hold">
                        @error('holdPayoutReason') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="px-6 py-3 border-t flex justify-end gap-2">
                    <button type="button" wire:click="closeHoldPayoutModal"
                            class="px-4 py-2 border rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" wire:click="confirmHoldPayout"
                            class="px-4 py-2 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700">Save hold</button>
                </div>
            </div>
        </div>
    @endif
</div>
