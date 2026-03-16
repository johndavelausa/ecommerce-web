<?php

use App\Models\Payment;
use App\Models\Seller;
use App\Models\SellerActivityLog;
use App\Models\SellerNote;
use App\Notifications\PaymentRejectedForSeller;
use App\Notifications\SellerSuspended;
use App\Notifications\SellerUnsuspended;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $statusFilter = '';
    public string $search = '';
    public ?int $selectedSellerId = null;
    public bool $showDeleteConfirm = false;
    public ?int $deleteSellerId = null;

    public bool $showRejectModal = false;
    public ?int $rejectPaymentId = null;
    public string $rejectReason = '';

    /** A2 v1.4 — Admin notes on seller profile */
    public string $newNote = '';

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    #[Computed]
    public function sellers()
    {
        $q = Seller::query()->with(['user', 'payments' => fn ($q) => $q->orderByDesc('created_at')]);
        if ($this->statusFilter !== '') {
            $q->where('status', $this->statusFilter);
        }
        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $q->where(function ($query) use ($term) {
                $query->where('store_name', 'like', $term)
                    ->orWhere('gcash_number', 'like', $term)
                    ->orWhereHas('user', function ($q2) use ($term) {
                        $q2->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
            });
        }
        return $q->orderByDesc('created_at')->paginate(20);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function viewSeller(int $id): void
    {
        $this->selectedSellerId = $id;
    }

    public function closeDetail(): void
    {
        $this->selectedSellerId = null;
    }

    public function approvePayment(int $paymentId): void
    {
        $payment = Payment::query()->with('seller')->findOrFail($paymentId);
        if ($payment->status !== 'pending') {
            return;
        }
        $now = now();
        $payment->update(['status' => 'approved', 'approved_at' => $now, 'paid_at' => $now]);

        $seller = $payment->seller;
        if ($seller) {
            // Base next period start on existing due date if in the future, otherwise from today.
            $startDate = $seller->subscription_due_date && $seller->subscription_due_date->isFuture()
                ? $seller->subscription_due_date
                : $now->copy()->startOfDay();

            $nextDue = $startDate->copy()->addMonth();

            $updateData = [
                'subscription_due_date' => $nextDue->toDateString(),
                'subscription_status'   => 'active',
            ];

            if ($payment->type === 'registration') {
                $updateData['status'] = 'approved';
            }

            // Reactivate store if it was closed due to lapsed subscription
            if (! $seller->is_open && $seller->subscription_status === 'lapsed') {
                $updateData['is_open'] = true;
            }

            $seller->update($updateData);
        }

        $this->dispatch('payment-updated');
    }

    public function rejectPayment(int $paymentId): void
    {
        $this->rejectPaymentId = $paymentId;
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    public function cancelReject(): void
    {
        $this->showRejectModal = false;
        $this->rejectPaymentId = null;
        $this->rejectReason = '';
        $this->resetErrorBag();
    }

    public function confirmReject(): void
    {
        $this->validate([
            'rejectReason' => ['required', 'string', 'max:255'],
        ]);

        $payment = Payment::query()->findOrFail($this->rejectPaymentId);
        if ($payment->status !== 'pending') {
            $this->cancelReject();
            return;
        }

        $updates = ['status' => 'rejected'];
        if (Schema::hasColumn('payments', 'rejection_reason')) {
            $updates['rejection_reason'] = $this->rejectReason;
        }
        $payment->update($updates);

        if ($payment->type === 'registration') {
            $payment->seller->update(['status' => 'rejected']);
        }

        // notify seller with rejection reason
        $sellerUser = $payment->seller?->user;
        if ($sellerUser) {
            $sellerUser->notify(new PaymentRejectedForSeller($payment, $this->rejectReason));
        }

        $this->cancelReject();
        $this->dispatch('payment-updated');
    }

    public function confirmDelete(int $id): void
    {
        $seller = Seller::query()->findOrFail($id);
        if ($seller->status !== 'rejected') {
            return;
        }
        $this->deleteSellerId = $id;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deleteSellerId = null;
    }

    public function deleteSeller(): void
    {
        if ($this->deleteSellerId === null) {
            return;
        }
        $seller = Seller::query()->findOrFail($this->deleteSellerId);
        if ($seller->status !== 'rejected') {
            $this->cancelDelete();
            return;
        }
        $seller->user->delete();
        $this->cancelDelete();
        $this->selectedSellerId = null;
        $this->dispatch('seller-deleted');
    }

    public function suspendSeller(int $sellerId): void
    {
        $seller = Seller::query()->with('user')->findOrFail($sellerId);
        if ($seller->status === 'approved') {
            $seller->update(['status' => 'suspended']);
            $seller->user?->notify(new SellerSuspended($seller));
            $this->dispatch('seller-updated');
        }
    }

    public function unsuspendSeller(int $sellerId): void
    {
        $seller = Seller::query()->with('user')->findOrFail($sellerId);
        if ($seller->status === 'suspended') {
            $seller->update(['status' => 'approved']);
            $seller->user?->notify(new SellerUnsuspended($seller));
            $this->dispatch('seller-updated');
        }
    }

    /** A2 v1.4 — Verified seller badge: admin can toggle */
    public function toggleVerified(int $sellerId): void
    {
        $seller = Seller::query()->findOrFail($sellerId);
        $seller->update(['is_verified' => ! $seller->is_verified]);
        SellerActivityLog::log($sellerId, 'verified_toggled', [
            'is_verified' => $seller->is_verified,
            'admin_id' => Auth::guard('admin')->id(),
        ]);
        $this->dispatch('seller-updated');
    }

    /** A2 v1.4 — Add admin note (internal only) */
    public function addSellerNote(): void
    {
        $this->validate(['newNote' => ['required', 'string', 'max:5000']]);
        if (! $this->selectedSellerId) {
            return;
        }
        SellerNote::query()->create([
            'seller_id' => $this->selectedSellerId,
            'note' => trim($this->newNote),
            'admin_id' => Auth::guard('admin')->id(),
        ]);
        $this->newNote = '';
        $this->dispatch('seller-updated');
    }

};
?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div class="flex flex-wrap gap-2 items-center">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search store name, name, email, GCash…"
                   class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-64">
            <span class="text-sm text-gray-600">Filter by status:</span>
        <button wire:click="$set('statusFilter', '')" class="px-3 py-1 rounded text-sm {{ $statusFilter === '' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">All</button>
        <button wire:click="$set('statusFilter', 'pending')" class="px-3 py-1 rounded text-sm {{ $statusFilter === 'pending' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">Pending</button>
        <button wire:click="$set('statusFilter', 'approved')" class="px-3 py-1 rounded text-sm {{ $statusFilter === 'approved' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">Approved</button>
        <button wire:click="$set('statusFilter', 'rejected')" class="px-3 py-1 rounded text-sm {{ $statusFilter === 'rejected' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">Rejected</button>
        <button wire:click="$set('statusFilter', 'suspended')" class="px-3 py-1 rounded text-sm {{ $statusFilter === 'suspended' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">Suspended</button>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Last Active</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Registered</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($this->sellers as $seller)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $seller->user->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $seller->store_name }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded {{ $seller->status === 'approved' ? 'bg-green-100 text-green-800' : ($seller->status === 'rejected' ? 'bg-red-100 text-red-800' : ($seller->status === 'suspended' ? 'bg-amber-100 text-amber-800' : 'bg-yellow-100 text-yellow-800')) }}">{{ ucfirst($seller->status) }}</span>
                            @if($seller->is_verified ?? false)
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800" title="Verified seller">✓ Verified</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            @php($last = $seller->user?->last_active_at)
                            @if(!$last)
                                —
                            @else
                                @php($diff = now()->diffInMinutes($last))
                                @if($diff < 5)
                                    Active now
                                @elseif($diff < 60)
                                    {{ $diff }} min ago
                                @elseif($diff < 60 * 24)
                                    {{ now()->diffInHours($last) }} hrs ago
                                @elseif($diff < 60 * 24 * 7)
                                    {{ now()->diffInDays($last) }} days ago
                                @else
                                    {{ $last->format('M d, Y') }}
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $seller->created_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <button wire:click="viewSeller({{ $seller->id }})" class="text-indigo-600 hover:underline">View</button>
                            @if($seller->status === 'approved')
                                <button wire:click="suspendSeller({{ $seller->id }})" class="ml-2 text-amber-700 hover:underline">Suspend</button>
                            @elseif($seller->status === 'suspended')
                                <button wire:click="unsuspendSeller({{ $seller->id }})" class="ml-2 text-green-700 hover:underline">Unsuspend</button>
                            @endif
                            @if($seller->status === 'rejected')
                                <button wire:click="confirmDelete({{ $seller->id }})" class="ml-2 text-red-600 hover:underline">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No sellers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-2 border-t">
            {{ $this->sellers->links() }}
        </div>
    </div>

    @if($selectedSellerId)
        @php($seller = \App\Models\Seller::with(['user', 'payments', 'notes.admin', 'activityLogs'])->find($selectedSellerId))
        @if($seller)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div wire:click="closeDetail" class="fixed inset-0 bg-black/50"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                        <div class="p-6">
                            <div class="flex justify-between items-start">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-semibold">Seller: {{ $seller->user->name }}</h3>
                                    @if($seller->is_verified ?? false)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">✓ Verified</span>
                                    @endif
                                </div>
                                <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600">&times;</button>
                            </div>
                            @if($seller->status === 'approved')
                                <div class="mt-2">
                                    <button wire:click="toggleVerified({{ $seller->id }})" class="text-sm {{ $seller->is_verified ? 'text-amber-600 hover:underline' : 'text-indigo-600 hover:underline' }}">
                                        {{ $seller->is_verified ? 'Remove verified badge' : 'Mark as verified' }}
                                    </button>
                                </div>
                            @endif
                            <dl class="mt-4 grid grid-cols-1 gap-2 text-sm">
                                <div><span class="text-gray-500">Store:</span> {{ $seller->store_name }}</div>
                                <div><span class="text-gray-500">Email:</span> {{ $seller->user->email }}</div>
                                <div><span class="text-gray-500">Contact:</span> {{ $seller->user->contact_number ?? '—' }}</div>
                                <div><span class="text-gray-500">GCash:</span> {{ $seller->gcash_number }}</div>
                                <div><span class="text-gray-500">Status:</span> {{ ucfirst($seller->status) }}</div>
                                <div>
                                    <span class="text-gray-500">Subscription:</span>
                                    @if($seller->subscription_due_date)
                                        {{ ucfirst($seller->subscription_status) }}
                                        · due {{ $seller->subscription_due_date->format('Y-m-d') }}
                                    @else
                                        Not set
                                    @endif
                                </div>
                            </dl>
                            <div class="mt-6 border-t pt-4">
                                <h4 class="font-medium text-gray-700">Payments</h4>
                                @forelse($seller->payments as $payment)
                                    <div class="mt-3 p-3 bg-gray-50 rounded">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <span class="font-medium">{{ ucfirst($payment->type) }}</span> — ₱{{ number_format($payment->amount, 2) }}
                                                <span class="ml-2 px-2 py-0.5 text-xs rounded {{ $payment->status === 'approved' ? 'bg-green-100' : ($payment->status === 'rejected' ? 'bg-red-100' : 'bg-yellow-100') }}">{{ $payment->status }}</span>
                                            </div>
                                            @if($payment->status === 'pending')
                                                <div>
                                                    <button wire:click="approvePayment({{ $payment->id }})" class="text-green-600 text-sm hover:underline">Approve</button>
                                                    <button wire:click="rejectPayment({{ $payment->id }})" class="ml-2 text-red-600 text-sm hover:underline">Reject</button>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">Ref: {{ $payment->reference_number }}</div>
                                        @if($payment->screenshot_path)
                                            <div class="mt-2">
                                                <img src="{{ asset('storage/' . $payment->screenshot_path) }}" alt="Screenshot" class="max-w-xs rounded border">
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-gray-500 text-sm">No payments.</p>
                                @endforelse
                            </div>
                            {{-- A2 v1.4 — Admin notes (internal only) --}}
                            <div class="mt-6 border-t pt-4">
                                <h4 class="font-medium text-gray-700">Admin notes</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Internal only — not visible to seller.</p>
                                <div class="mt-2">
                                    <textarea wire:model.defer="newNote" rows="2" placeholder="Add a note…"
                                              class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                    @error('newNote') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                    <button wire:click="addSellerNote" class="mt-1 px-3 py-1 bg-gray-700 text-white rounded text-sm hover:bg-gray-800">Add note</button>
                                </div>
                                <ul class="mt-3 space-y-2 max-h-40 overflow-y-auto">
                                    @forelse($seller->notes as $note)
                                        <li class="p-2 bg-gray-50 rounded text-sm">
                                            <span class="text-gray-700">{{ $note->note }}</span>
                                            <span class="block text-xs text-gray-400 mt-1">{{ $note->created_at->format('M d, Y H:i') }} @if($note->admin) · {{ $note->admin->name }} @endif</span>
                                        </li>
                                    @empty
                                        <li class="text-gray-500 text-sm">No notes yet.</li>
                                    @endforelse
                                </ul>
                            </div>
                            {{-- A2 v1.4 — Seller activity log --}}
                            <div class="mt-6 border-t pt-4">
                                <h4 class="font-medium text-gray-700">Seller activity log</h4>
                                <ul class="mt-2 space-y-2 max-h-56 overflow-y-auto text-sm">
                                    @forelse($seller->activityLogs->take(50) as $log)
                                        <li class="flex flex-col gap-1 text-gray-600 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-gray-400 text-xs">{{ $log->created_at->format('Y-m-d H:i') }}</span>
                                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-700">
                                                    {{ str_replace('_', ' ', $log->action) }}
                                                </span>
                                            </div>
                                            @if(!empty($log->details))
                                                @php($details = $log->details)
                                                <div class="mt-1 border-t border-dashed border-gray-200 pt-1.5 text-gray-500 text-xs space-y-0.5">
                                                    @if(is_array($details))
                                                        @foreach($details as $key => $value)
                                                            <div>
                                                                <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                                                @if(is_array($value))
                                                                    @php($isAssoc = \Illuminate\Support\Arr::isAssoc($value))
                                                                    @if($isAssoc)
                                                                        <div class="mt-0.5 ml-4 space-y-0.5">
                                                                            @foreach($value as $subKey => $subValue)
                                                                                <div>
                                                                                    <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $subKey)) }}:</span>
                                                                                    <span>{{ is_bool($subValue) ? ($subValue ? 'Yes' : 'No') : $subValue }}</span>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    @else
                                                                        <span class="whitespace-pre-wrap font-mono">
                                                                            {{ implode(', ', $value) }}
                                                                        </span>
                                                                    @endif
                                                                @else
                                                                    <span>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</span>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div>{{ $details }}</div>
                                                    @endif
                                                </div>
                                            @endif
                                        </li>
                                    @empty
                                        <li class="text-gray-500">No activity logged yet.</li>
                                    @endforelse
                                </ul>
                            </div>
                            @if($seller->status === 'rejected')
                                <div class="mt-4 pt-4 border-t">
                                    <button wire:click="confirmDelete({{ $seller->id }})" class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">Delete seller</button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    @if($showDeleteConfirm)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm w-full">
                <p class="text-gray-700">Delete this rejected seller and their user account? This cannot be undone.</p>
                <div class="mt-4 flex gap-2 justify-end">
                    <button wire:click="cancelDelete" class="px-3 py-1 border rounded">Cancel</button>
                    <button wire:click="deleteSeller" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
                </div>
            </div>
        </div>
    @endif

    @if($showRejectModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold text-gray-900">Reject payment</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Please enter a reason. This will be shown to the seller.
                </p>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Reason</label>
                    <textarea wire:model.defer="rejectReason" rows="3"
                              class="mt-1 w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="e.g. Screenshot unclear, reference number mismatch..."></textarea>
                    @error('rejectReason') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div class="mt-4 flex gap-2 justify-end">
                    <button wire:click="cancelReject" class="px-3 py-1 border rounded">Cancel</button>
                    <button wire:click="confirmReject" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">Reject</button>
                </div>
            </div>
        </div>
    @endif
</div>
