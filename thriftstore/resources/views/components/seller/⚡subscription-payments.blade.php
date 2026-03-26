<?php

use App\Models\Payment;
use App\Models\SellerPayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;

    public string $type = 'subscription';
    public string $amount = '';
    public string $gcash_number = '';
    public string $reference_number = '';
    public $screenshot = null;

    public bool $justSubmitted = false;

    public string $payoutStatusFilter = '';

    #[Computed]
    public function seller()
    {
        return Auth::guard('seller')->user()?->seller;
    }

    #[Computed]
    public function payments()
    {
        $seller = $this->seller;
        if (! $seller) {
            return collect();
        }

        return Payment::query()
            ->where('seller_id', $seller->id)
            ->where('type', 'subscription')
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    #[Computed]
    public function payouts()
    {
        $seller = $this->seller;
        if (! $seller) {
            return collect();
        }

        $q = SellerPayout::query()
            ->with('order')
            ->where('seller_id', $seller->id)
            ->orderByDesc('created_at');

        if ($this->payoutStatusFilter !== '') {
            $q->where('status', $this->payoutStatusFilter);
        }

        return $q->paginate(20, ['*'], 'payouts_page');
    }

    #[Computed]
    public function payoutSummary()
    {
        $seller = $this->seller;
        if (! $seller) {
            return (object) [
                'released_total' => 0.0,
                'on_hold_total' => 0.0,
                'released_count' => 0,
                'on_hold_count' => 0,
            ];
        }

        $released = SellerPayout::query()
            ->where('seller_id', $seller->id)
            ->where('status', SellerPayout::STATUS_RELEASED);

        $onHold = SellerPayout::query()
            ->where('seller_id', $seller->id)
            ->where('status', SellerPayout::STATUS_ON_HOLD);

        return (object) [
            'released_total' => (float) $released->sum('net_amount'),
            'on_hold_total' => (float) $onHold->sum('net_amount'),
            'released_count' => (int) $released->count(),
            'on_hold_count' => (int) $onHold->count(),
        ];
    }

    public function updatingPayoutStatusFilter(): void
    {
        $this->resetPage('payouts_page');
    }

    public function mount(): void
    {
        $seller = $this->seller;
        if ($seller && $seller->gcash_number) {
            $this->gcash_number = $seller->gcash_number;
        }
    }

    public function submit(): void
    {
        $seller = $this->seller;
        if (! $seller) {
            abort(403);
        }

        $this->validate([
            'type'             => ['required', 'in:subscription'],
            'amount'           => ['required', 'numeric', 'min:1'],
            'gcash_number'     => ['required', 'string', 'max:50'],
            'reference_number' => ['required', 'string', 'max:100', 'unique:payments,reference_number'],
            'screenshot'       => ['required', 'image', 'max:5120'],
        ]);

        $path = $this->screenshot->store('payments', 'public');
 
        Payment::create([
            'seller_id'        => $seller->id,
            'type'             => $this->type,
            'amount'           => $this->amount,
            'gcash_number'     => $this->gcash_number,
            'reference_number' => $this->reference_number,
            'screenshot_path'  => $path,
            'status'           => 'pending',
        ]);

        $this->reset(['amount', 'reference_number', 'screenshot']);
        $this->type = 'subscription';
        $this->justSubmitted = true;
    }
};
?>

@push('styles')
<style>
    /* ── Subscription Payments — Brand Palette ─────────────────── */
    .pay-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #D4E8DA;
        box-shadow: 0 2px 12px rgba(15,61,34,0.07);
        overflow: hidden;
        margin-bottom: 24px;
    }
    .pay-card-header {
        background: linear-gradient(135deg, #0F3D22 0%, #1a5c35 100%);
        padding: 18px 24px;
        border-bottom: 2px solid #F9C74F;
    }
    .pay-card-header h3 {
        font-size: 1.0625rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 4px;
    }
    .pay-card-header p {
        font-size: 0.8125rem;
        color: rgba(255,255,255,0.6);
        margin: 0;
    }
    .pay-card-body {
        padding: 24px;
    }
    .pay-label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #1B7A37;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .pay-input {
        width: 100%;
        padding: 10px 14px;
        border: 1.5px solid #D4E8DA;
        border-radius: 10px;
        font-size: 0.875rem;
        color: #212121;
        background: #fff;
        transition: all 0.15s ease;
        outline: none;
    }
    .pay-input:focus {
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(45,159,78,0.1);
    }
    .pay-error {
        font-size: 0.75rem;
        color: #E53935;
        margin-top: 4px;
    }
    .pay-hint {
        font-size: 0.75rem;
        color: #9E9E9E;
        margin-top: 4px;
    }
    .pay-form-row {
        margin-bottom: 18px;
    }
    .pay-form-row:last-child { margin-bottom: 0; }
    .pay-alert-success {
        display: flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
        border: 1px solid #A5D6A7;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 0.875rem;
        color: #1B7A37;
        font-weight: 600;
        margin-bottom: 20px;
    }
    .pay-upload-wrap {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 16px;
        background: #F5FBF7;
        border: 1.5px dashed #A8D5B5;
        border-radius: 12px;
    }
    .pay-uploading {
        font-size: 0.75rem;
        color: #2D9F4E;
        font-weight: 600;
    }
    .pay-preview {
        width: 96px;
        height: 96px;
        object-fit: cover;
        border-radius: 10px;
        border: 2px solid #2D9F4E;
    }
    .pay-btn-submit {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 24px;
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 2px 8px rgba(45,159,78,0.25);
    }
    .pay-btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(45,159,78,0.35);
    }
    .pay-btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    /* Recent payments list */
    .pay-list {
        border: 1px solid #D4E8DA;
        border-radius: 12px;
        overflow: hidden;
    }
    .pay-list-title {
        font-size: 0.8125rem;
        font-weight: 700;
        color: #0F3D22;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 10px;
    }
    .pay-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        background: #fff;
        border-bottom: 1px solid #F0F7F2;
        font-size: 0.875rem;
    }
    .pay-list-item:last-child { border-bottom: none; }
    .pay-list-item:hover { background: #F5FBF7; }
    .pay-list-amount {
        font-weight: 700;
        color: #212121;
        font-size: 0.9375rem;
    }
    .pay-list-meta {
        font-size: 0.75rem;
        color: #9E9E9E;
        margin-top: 2px;
    }
    .pay-empty {
        padding: 32px 16px;
        text-align: center;
        color: #9E9E9E;
        font-size: 0.875rem;
    }
    /* Status badges */
    .pay-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        flex-shrink: 0;
    }
    .pay-badge.approved  { background: #C8E6C9; color: #1B7A37; }
    .pay-badge.pending   { background: #FFF9E3; color: #F57C00; }
    .pay-badge.rejected  { background: #FFEBEE; color: #C0392B; }
    .pay-badge.default   { background: #F5F5F5; color: #757575; }
    /* Paginator */
    .pay-list-wrap nav[role="navigation"] {
        display: flex;
        justify-content: center;
        padding: 10px;
        border-top: 1px solid #D4E8DA;
        background: #F5FBF7;
    }
    .pay-list-wrap nav .flex { display: flex; align-items: center; gap: 4px; }
    .pay-list-wrap nav .flex > span,
    .pay-list-wrap nav .flex > a {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 30px; height: 30px; padding: 0 8px;
        border-radius: 7px; font-size: 0.8125rem; font-weight: 600;
        text-decoration: none; transition: all 0.15s;
    }
    .pay-list-wrap nav .flex > a[rel] { border: 1px solid #D4E8DA; background: #fff; color: #424242; }
    .pay-list-wrap nav .flex > a[rel]:hover { border-color: #2D9F4E; background: #F5FBF7; color: #1B7A37; }
    .pay-list-wrap nav .flex > span[aria-disabled="true"] { border: 1px solid #D4E8DA; background: #fff; color: #424242; opacity: 0.5; }
    .pay-list-wrap nav .flex > a:not([rel]) { border: 1px solid transparent; background: transparent; color: #424242; }
    .pay-list-wrap nav .flex > a:not([rel]):hover { border-color: #D4E8DA; background: #F5FBF7; color: #1B7A37; }
    .pay-list-wrap nav .flex > span[aria-current="page"] { background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%); border: 1px solid transparent; color: #fff; box-shadow: 0 2px 6px rgba(45,159,78,0.3); }
    .pay-list-wrap nav > .flex:first-child { display: none; }
</style>
@endpush

<div class="space-y-6">

    {{-- Subscription Payments Card --}}
    <div class="pay-card">
        <div class="pay-card-header">
            <h3>Subscription Payments</h3>
            <p>Upload your GCash payment for your monthly subscription. Admin will review and approve.</p>
        </div>
        <div class="pay-card-body">

            @if($justSubmitted)
                <div class="pay-alert-success">
                    <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Payment submitted. Please wait for admin approval.
                </div>
            @endif

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;">

                {{-- Left: Form --}}
                <div class="space-y-6">
                    {{-- Info Row --}}
                    <div class="flex items-start gap-4 p-4 rounded-xl bg-[#F5FBF7] border border-[#D4E8DA]">
                        <div class="shrink-0">
                            @php($gcashQr = \App\Models\SystemSetting::get_url('gcash_qr_path', asset('defaults/gcash-qr.png')))
                            <img src="{{ $gcashQr }}" 
                                 class="w-32 h-32 object-contain rounded-lg border-2 border-white shadow-sm" alt="GCash QR">
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-[#0F3D22] mb-1">GCash Payment QR</h4>
                            <p class="text-xs text-[#1B7A37] leading-relaxed mb-3">Scan this QR code to pay your subscription fee. After paying, please upload the screenshot below.</p>
                            
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white border border-[#D4E8DA]">
                                <svg class="w-4 h-4 text-[#F9C74F]" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                                <span class="text-xs font-bold text-[#424242]">Next Payment Due:</span>
                                <span class="text-xs font-black text-[#2D9F4E]">
                                    @if($this->seller?->subscription_due_date)
                                        {{ $this->seller->subscription_due_date->format('M j, Y') }}
                                    @else
                                        N/A
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="pay-form-row">
                        <label class="pay-label">Payment Type</label>
                        <select wire:model.defer="type" class="pay-input">
                            <option value="subscription">Subscription</option>
                        </select>
                        @error('type') <div class="pay-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="pay-form-row">
                        <label class="pay-label">Amount (₱)</label>
                        <input type="number" min="1" step="0.01" wire:model.defer="amount" class="pay-input" placeholder="e.g. 299.00">
                        @error('amount') <div class="pay-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="pay-form-row">
                        <label class="pay-label">Your GCash Number</label>
                        <input type="text" wire:model.defer="gcash_number" class="pay-input" placeholder="09XXXXXXXXX">
                        @error('gcash_number') <div class="pay-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="pay-form-row">
                        <label class="pay-label">GCash Reference Number</label>
                        <input type="text" wire:model.defer="reference_number" class="pay-input" placeholder="13-digit reference">
                        @error('reference_number') <div class="pay-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="pay-form-row">
                        <label class="pay-label">Screenshot of Payment</label>
                        <div class="pay-upload-wrap">
                            <input type="file" wire:model="screenshot" accept="image/*" style="font-size:0.875rem;color:#424242;">
                            <div wire:loading wire:target="screenshot" class="pay-uploading">Uploading…</div>
                            @if($screenshot)
                                <img src="{{ $screenshot->temporaryUrl() }}" class="pay-preview" alt="Preview">
                            @endif
                        </div>
                        <p class="pay-hint">Max 5 MB · JPG, PNG accepted</p>
                        @error('screenshot') <div class="pay-error">{{ $message }}</div> @enderror
                    </div>

                    <button type="button" wire:click="submit" wire:loading.attr="disabled" class="pay-btn-submit">
                        <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Submit Payment
                    </button>
                </div>

                {{-- Right: Recent Payments --}}
                <div>
                    <div class="pay-list-title">Recent Payments</div>
                    <div class="pay-list-wrap">
                        <div class="pay-list">
                            @if($this->payments->isNotEmpty())
                                @foreach($this->payments as $payment)
                                    <div class="pay-list-item">
                                        <div>
                                            <div class="pay-list-amount">₱{{ number_format($payment->amount, 2) }}</div>
                                            <div class="pay-list-meta">
                                                Ref: {{ $payment->reference_number }} ·
                                                {{ optional($payment->created_at)->format('M j, Y H:i') }}
                                            </div>
                                            @if($payment->approved_at)
                                                <div class="pay-list-meta" style="color:#2D9F4E;">
                                                    Approved: {{ $payment->approved_at->format('M j, Y H:i') }}
                                                </div>
                                            @endif
                                        </div>
                                        @php
                                            $badgeClass = match($payment->status) {
                                                'approved' => 'approved',
                                                'rejected' => 'rejected',
                                                'pending'  => 'pending',
                                                default    => 'default',
                                            };
                                        @endphp
                                        <span class="pay-badge {{ $badgeClass }}">{{ ucfirst($payment->status) }}</span>
                                    </div>
                                @endforeach
                            @else
                                <div class="pay-empty">No payments submitted yet.</div>
                            @endif
                        </div>
                        @if($this->payments->hasPages())
                            {{ $this->payments->links() }}
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

