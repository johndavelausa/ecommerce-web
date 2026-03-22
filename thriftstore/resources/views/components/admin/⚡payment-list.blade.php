<?php

use App\Models\Payment;
use App\Models\Order;
use App\Models\SellerPayout;
use App\Notifications\SellerPayoutReleased;
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

    public function getPaymentsProperty()
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

    public function getPayoutsProperty()
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

    public function getPayoutSummaryProperty()
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

        if ($payout->seller && $payout->seller->user) {
            $payout->seller->user->notify(new SellerPayoutReleased($payout));
        }
    }
};
?>

<style>
    .pay-search { border-radius: 50px; border: 1.5px solid #D4E8DA; padding: 8px 16px; font-size: 0.8125rem; background: #fff; color: #424242; transition: all 0.15s; }
    .pay-search:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .pay-select { border-radius: 12px; border: 1.5px solid #D4E8DA; padding: 8px 12px; font-size: 0.8125rem; background: #fff; color: #424242; transition: all 0.15s; }
    .pay-select:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .pay-label { font-size: 0.6875rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; font-style: italic; }
    .pay-table-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; overflow: hidden; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .pay-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .pay-table th { padding: 9px 16px; text-align: left; font-size: 0.6875rem; font-weight: 700; color: #1B7A37; text-transform: uppercase; letter-spacing: 0.05em; background: #F5FBF7; border-bottom: 1px solid #D4E8DA; }
    .pay-table td { padding: 9px 16px; color: #424242; border-bottom: 1px solid #F0F7F2; }
    .pay-table tr:last-child td { border-bottom: none; }
    .pay-table tr:hover td { background: #F5FBF7; }
    .pay-badge { padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
    .pay-badge-approved { background: #E8F5E9; color: #1B7A37; }
    .pay-badge-rejected { background: #FFEBEE; color: #C0392B; }
    .pay-badge-pending { background: #FFF9E3; color: #F57C00; }
    .pay-modal { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; box-shadow: 0 10px 40px rgba(15,61,34,0.2); }
    .pay-modal-btn { padding: 8px 16px; border-radius: 50px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid #D4E8DA; background: #fff; color: #424242; cursor: pointer; transition: all 0.15s; }
    .pay-modal-btn-amber { background: #FFF3E0; color: #E65100; border-color: #F57C00; }
    .pay-modal-input { border-radius: 12px; border: 1.5px solid #D4E8DA; padding: 8px 12px; font-size: 0.8125rem; color: #424242; transition: all 0.15s; }
    .pay-modal-input:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
</style>

<div class="space-y-4">
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap gap-2 items-center">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search seller name, reference #, GCash..."
                   class="pay-search w-64">
            <span class="pay-label">From</span>
            <input type="date" wire:model.live="dateFrom" class="pay-select">
            <span style="color:#9E9E9E;">–</span>
            <input type="date" wire:model.live="dateTo" class="pay-select">
            <select wire:model.live="statusFilter" class="pay-select">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        <div class="text-xs" style="color:#9E9E9E;font-style:italic;">
            Showing {{ $this->payments->firstItem() ?? 0 }}–{{ $this->payments->lastItem() ?? 0 }} of {{ $this->payments->total() }} payments
        </div>
    </div>

    <div class="pay-table-card">
        <table class="pay-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Seller</th>
                    <th>Type</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Reference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->payments as $p)
                    <tr>
                        <td style="color:#9E9E9E;font-style:italic;font-size:0.8125rem;">{{ $p->created_at ? $p->created_at->format('M d, Y H:i') : '' }}</td>
                        <td>
                            <div style="color:#0F3D22;font-weight:600;">{{ $p->seller ? $p->seller->store_name : '—' }}</div>
                            <div style="font-size:0.75rem;color:#9E9E9E;font-style:italic;">{{ $p->seller && $p->seller->user ? $p->seller->user->name : '' }}</div>
                        </td>
                        <td style="color:#424242;">{{ ucfirst($p->type) }}</td>
                        <td style="text-align:right;color:#0F3D22;font-weight:700;">₱{{ number_format($p->amount, 2) }}</td>
                        <td style="font-family:monospace;font-size:0.75rem;color:#9E9E9E;font-style:italic;">{{ $p->reference_number }}</td>
                        <td>
                            @php
                                $badgeClass = match($p->status) {
                                    'approved' => 'pay-badge-approved',
                                    'rejected' => 'pay-badge-rejected',
                                    default => 'pay-badge-pending',
                                };
                            @endphp
                            <span class="pay-badge {{ $badgeClass }}">{{ ucfirst($p->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:32px 16px;color:#9E9E9E;font-style:italic;">No payments match your filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($this->payments->hasPages())
            <div style="padding:12px 16px;border-top:1px solid #D4E8DA;">
                {{ $this->payments->links() }}
            </div>
        @endif
    </div>

    @if($showHoldPayoutModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4">
            <div class="pay-modal w-full max-w-md p-6">
                <h3 style="font-size:1.125rem;font-weight:800;color:#0F3D22;margin-bottom:4px;">Put Payout on Hold</h3>
                <p class="text-sm" style="color:#757575;font-style:italic;margin-bottom:16px;">Provide a short hold reason for audit visibility.</p>
                <div>
                    <label class="pay-label">Hold reason code</label>
                    <input type="text" wire:model.defer="holdPayoutReason" maxlength="100"
                           class="pay-modal-input w-full mt-2"
                           placeholder="e.g. manual_risk_hold">
                    @error('holdPayoutReason') <span class="text-xs" style="color:#C0392B;">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" wire:click="closeHoldPayoutModal" class="pay-modal-btn">Cancel</button>
                    <button type="button" wire:click="confirmHoldPayout" class="pay-modal-btn pay-modal-btn-amber">Save hold</button>
                </div>
            </div>
        </div>
    @endif
</div>
