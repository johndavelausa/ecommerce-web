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
            'type'             => ['required', 'in:registration,subscription'],
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

<div class="space-y-8">
    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium text-gray-900">Subscription & registration payments</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Upload your GCash payment for registration or monthly subscription. Admin will review and approve.
                </p>
            </div>
        </div>

        @if($justSubmitted)
            <div class="rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">
                Payment submitted. Please wait for admin approval.
            </div>
        @endif

        <div class="grid md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Payment type</label>
                    <select wire:model.defer="type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="registration">Registration</option>
                        <option value="subscription">Subscription</option>
                    </select>
                    @error('type') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Amount (₱)</label>
                    <input type="number" min="1" step="0.01" wire:model.defer="amount"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('amount') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Your GCash number</label>
                    <input type="text" wire:model.defer="gcash_number"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('gcash_number') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">GCash reference number</label>
                    <input type="text" wire:model.defer="reference_number"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('reference_number') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Screenshot of payment</label>
                    <input type="file" wire:model="screenshot" accept="image/*"
                           class="mt-1 block text-sm text-gray-500">
                    <div wire:loading wire:target="screenshot" class="text-xs text-indigo-600 mt-1">Uploading…</div>
                    @if($screenshot)
                        <img src="{{ $screenshot->temporaryUrl() }}" class="mt-2 h-24 w-24 object-cover rounded-md border" alt="Preview">
                    @endif
                    @error('screenshot') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>

                <div class="pt-2">
                    <button type="button" wire:click="submit" wire:loading.attr="disabled"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Submit payment
                    </button>
                </div>
            </div>

            <div class="space-y-3">
                <h4 class="text-sm font-semibold text-gray-900">Recent payments</h4>
                <div class="border rounded-lg divide-y divide-gray-100 bg-gray-50/60">
                    @if ($this->payments->isNotEmpty())
                        @foreach ($this->payments as $payment)
                            <div class="px-3 py-2 text-sm bg-white first:rounded-t-lg last:rounded-b-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            {{ ucfirst($payment->type) }} · ₱{{ number_format($payment->amount, 2) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Ref: {{ $payment->reference_number }} ·
                                            {{ optional($payment->created_at)->format('Y-m-d H:i') }}
                                        </div>
                                    </div>
                                    @php
                                        $badge = match($payment->status) {
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'pending' => 'bg-amber-100 text-amber-800',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                </div>
                                @if ($payment->approved_at)
                                    <div class="mt-1 text-xs text-gray-500">
                                        Approved: {{ $payment->approved_at->format('Y-m-d H:i') }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <div class="px-3 py-4 text-sm text-gray-500">
                            No payments submitted yet.
                        </div>
                    @endif
                </div>
                @if ($this->payments->hasPages())
                    <div class="mt-2">
                        {{ $this->payments->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-lg font-medium text-gray-900">Payout releases</h3>
                <p class="mt-1 text-sm text-gray-500">Track released and on-hold payouts generated from completed orders.</p>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600">Status</label>
                <select wire:model.live="payoutStatusFilter"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    <option value="released">Released</option>
                    <option value="on_hold">On hold</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
        </div>

        @php
            $summary = $this->payoutSummary;
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-green-700">Released payouts</div>
                <div class="mt-1 text-2xl font-semibold text-green-800">₱{{ number_format($summary->released_total, 2) }}</div>
                <div class="text-xs text-green-700 mt-1">{{ $summary->released_count }} payout records</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">On hold payouts</div>
                <div class="mt-1 text-2xl font-semibold text-amber-800">₱{{ number_format($summary->on_hold_total, 2) }}</div>
                <div class="text-xs text-amber-700 mt-1">{{ $summary->on_hold_count }} payout records</div>
            </div>
        </div>

        <div class="bg-white rounded-lg border overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gross</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Platform fee</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @if ($this->payouts->isNotEmpty())
                        @foreach ($this->payouts as $payout)
                            <tr>
                                <td class="px-4 py-3 text-gray-700">{{ $payout->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 text-gray-700 font-medium">
                                    #{{ $payout->order_id }}
                                    @if ($payout->order?->refund_status)
                                        <div class="mt-1 text-xs text-gray-500">
                                            Refund: {{ \App\Models\Order::refundStatusLabel($payout->order->refund_status) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">₱{{ number_format((float) $payout->gross_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">₱{{ number_format((float) $payout->platform_fee_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900">₱{{ number_format((float) $payout->net_amount, 2) }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $badge = match($payout->status) {
                                            'released' => 'bg-green-100 text-green-800',
                                            'on_hold' => 'bg-amber-100 text-amber-800',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                        {{ ucfirst(str_replace('_', ' ', $payout->status)) }}
                                    </span>
                                    @if ($payout->status === 'on_hold' && $payout->hold_reason)
                                        <div class="mt-1 text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', $payout->hold_reason)) }}</div>
                                    @endif
                                    @if ($payout->order?->refunded_at)
                                        <div class="mt-1 text-xs text-gray-500">Order refunded at {{ $payout->order->refunded_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No payout records yet.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @if ($this->payouts->hasPages())
                <div class="px-4 py-2 border-t">
                    {{ $this->payouts->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

