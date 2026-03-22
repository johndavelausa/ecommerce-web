<?php

use App\Models\Conversation;
use App\Models\Message;
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

    public bool $showSuspendModal = false;
    public ?int $suspendSellerId = null;
    public string $suspensionReason = '';
    public string $customSuspensionNote = '';
    public array $suspensionReasonsList = [
        'Violation of Terms of Service',
        'Reported Fraudulent Activity',
        'Consistent Late Shipping',
        'Low User Rating',
        'Misleading Product Information',
        'Other'
    ];

    public bool $showUnsuspendModal = false;
    public ?int $unsuspendSellerId = null;
    public string $unsuspendReason = '';

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
            $updateData = [];

            if ($payment->type === 'registration') {
                $updateData['status'] = 'approved';
            } elseif ($payment->type === 'subscription') {
                // Base next period start on existing due date if in the future, otherwise from today.
                $startDate = $seller->subscription_due_date && $seller->subscription_due_date->isFuture()
                    ? $seller->subscription_due_date
                    : $now->copy()->startOfDay();

                $nextDue = $startDate->copy()->addMonth();

                $updateData['subscription_due_date'] = $nextDue->toDateString();
                $updateData['subscription_status']   = 'active';

                // Reactivate store if it was closed due to lapsed subscription
                if (! $seller->is_open && $seller->subscription_status === 'lapsed') {
                    $updateData['is_open'] = true;
                }
            }

            if (!empty($updateData)) {
                $seller->update($updateData);
            }
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
        $this->suspendSellerId = $sellerId;
        $this->suspensionReason = $this->suspensionReasonsList[0];
        $this->customSuspensionNote = '';
        $this->showSuspendModal = true;
    }

    public function cancelSuspension(): void
    {
        $this->showSuspendModal = false;
        $this->suspendSellerId = null;
        $this->resetErrorBag();
    }

    public function confirmSuspension(): void
    {
        if (!$this->suspendSellerId) return;

        $this->validate([
            'suspensionReason' => ['required', 'string'],
            'customSuspensionNote' => ['required_if:suspensionReason,Other', 'nullable', 'string', 'max:1000'],
        ]);

        $seller = Seller::query()->with('user')->findOrFail($this->suspendSellerId);
        
        $finalReason = $this->suspensionReason === 'Other' 
            ? $this->customSuspensionNote 
            : $this->suspensionReason;

        $seller->update([
            'status' => 'suspended',
            'suspension_reason' => $finalReason
        ]);

        // Log activity
        SellerActivityLog::log($seller->id, 'suspended', [
            'reason' => $finalReason,
            'admin_id' => Auth::guard('admin')->id(),
        ]);

        // Send Email/Database Notification
        $seller->user?->notify(new SellerSuspended($seller));

        // Send Automatic Message in Chat
        $this->sendSuspensionMessage($seller, $finalReason);

        $this->cancelSuspension();
        $this->dispatch('seller-updated');
    }

    private function sendSuspensionMessage(Seller $seller, string $reason): void
    {
        $conv = Conversation::firstOrCreate([
            'seller_id' => $seller->id,
            'type'      => 'seller-admin',
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id'       => Auth::guard('admin')->id(),
            'sender_type'     => 'admin',
            'body'            => "YOUR ACCOUNT HAS BEEN SUSPENDED.\n\nReason: {$reason}\n\nPlease contact support if you believe this is a mistake.",
            'is_read'         => false,
        ]);
    }

    public function unsuspendSeller(int $sellerId): void
    {
        $this->unsuspendSellerId = $sellerId;
        $this->unsuspendReason = '';
        $this->showUnsuspendModal = true;
    }

    public function cancelUnsuspension(): void
    {
        $this->showUnsuspendModal = false;
        $this->unsuspendSellerId = null;
        $this->resetErrorBag();
    }

    public function confirmUnsuspension(): void
    {
        if (!$this->unsuspendSellerId) return;

        $this->validate([
            'unsuspendReason' => ['required', 'string', 'max:1000'],
        ]);

        $seller = Seller::query()->with('user')->findOrFail($this->unsuspendSellerId);
        
        $seller->update([
            'status' => 'approved',
            'suspension_reason' => null
        ]);

        // Log activity
        SellerActivityLog::log($seller->id, 'unsuspended', [
            'resolution_reason' => $this->unsuspendReason,
            'admin_id' => Auth::guard('admin')->id(),
        ]);

        // Send Email/Database Notification
        $seller->user?->notify(new SellerUnsuspended($seller));

        // Send Automatic Message in Chat
        $this->sendUnsuspensionMessage($seller, $this->unsuspendReason);

        $this->cancelUnsuspension();
        $this->dispatch('seller-updated');
    }

    private function sendUnsuspensionMessage(Seller $seller, string $reason): void
    {
        $conv = Conversation::firstOrCreate([
            'seller_id' => $seller->id,
            'type'      => 'seller-admin',
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id'       => Auth::guard('admin')->id(),
            'sender_type'     => 'admin',
            'body'            => "YOUR ACCOUNT HAS BEEN REACTIVATED.\n\nNote: {$reason}\n\nYou can now resume your sales activities. Welcome back!",
            'is_read'         => false,
        ]);
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

<style>
    .sel-search { border-radius: 50px; border: 1.5px solid #D4E8DA; padding: 8px 16px; font-size: 0.8125rem; background: #fff; color: #424242; transition: all 0.15s; }
    .sel-search:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .sel-filter-btn { padding: 6px 14px; border-radius: 50px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid #D4E8DA; background: #fff; color: #424242; text-decoration: none; transition: all 0.15s; cursor: pointer; }
    .sel-filter-btn.active { background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #F9C74F; border-color: #2D9F4E; }
    .sel-filter-btn:hover { border-color: #2D9F4E; }
    .sel-label { font-size: 0.6875rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; font-style: italic; }
    .sel-table-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; overflow: hidden; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .sel-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .sel-table th { padding: 9px 16px; text-align: left; font-size: 0.6875rem; font-weight: 700; color: #1B7A37; text-transform: uppercase; letter-spacing: 0.05em; background: #F5FBF7; border-bottom: 1px solid #D4E8DA; }
    .sel-table td { padding: 9px 16px; color: #424242; border-bottom: 1px solid #F0F7F2; }
    .sel-table tr:last-child td { border-bottom: none; }
    .sel-table tr:hover td { background: #F5FBF7; }
    .sel-status-badge { padding: 5px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
    .sel-status-approved { background: #E8F5E9; color: #1B7A37; }
    .sel-status-pending { background: #FFF9E3; color: #F57C00; }
    .sel-status-rejected { background: #FFEBEE; color: #C0392B; }
    .sel-status-suspended { background: #FFF3E0; color: #E65100; }
    .sel-verified { background: #E3F2FD; color: #1565C0; padding: 4px 8px; border-radius: 50px; font-size: 0.7rem; font-weight: 600; margin-left: 6px; }
    .sel-action-btn { font-size: 0.8125rem; font-weight: 600; text-decoration: none; transition: all 0.15s; }
    .sel-action-view { color: #2D9F4E; }
    .sel-action-view:hover { color: #1B7A37; text-decoration: underline; }
    .sel-action-suspend { color: #F57C00; }
    .sel-action-suspend:hover { color: #E65100; text-decoration: underline; }
    .sel-action-unsuspend { color: #1B7A37; }
    .sel-action-unsuspend:hover { color: #0F3D22; text-decoration: underline; }
    .sel-action-delete { color: #C0392B; }
    .sel-action-delete:hover { color: #A02622; text-decoration: underline; }
    .sel-modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50; }
    .sel-modal { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; box-shadow: 0 10px 40px rgba(15,61,34,0.2); display: flex; flex-direction: column; max-height: 90vh; }
    .sel-modal-header { padding: 18px 20px; border-bottom: 1.5px solid #D4E8DA; flex-shrink: 0; background: #fff; border-radius: 20px 20px 0 0; }
    .sel-modal-body { flex: 1; overflow-y: auto; padding: 18px 20px; }
    .sel-modal-footer { padding: 14px 20px; border-top: 1.5px solid #D4E8DA; flex-shrink: 0; background: #F5FBF7; border-radius: 0 0 20px 20px; display: flex; gap: 8px; justify-content: flex-end; }
    .sel-modal-title { font-size: 1.125rem; font-weight: 800; color: #0F3D22; }
    .sel-modal-label { font-size: 0.8125rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; font-style: italic; margin-bottom: 6px; }
    .sel-section { margin-bottom: 16px; }
    .sel-section-title { font-size: 0.8125rem; font-weight: 700; color: #1B7A37; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1.5px solid #D4E8DA; }
    .sel-detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F0F7F2; }
    .sel-detail-row:last-child { border-bottom: none; }
    .sel-detail-row.compact { padding: 6px 0; }
    .sel-modal-input, .sel-modal-textarea, .sel-modal-select { border-radius: 12px; border: 1.5px solid #D4E8DA; padding: 8px 12px; font-size: 0.8125rem; color: #424242; transition: all 0.15s; }
    .sel-modal-input:focus, .sel-modal-textarea:focus, .sel-modal-select:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .sel-modal-btn { padding: 8px 16px; border-radius: 50px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid #D4E8DA; background: #fff; color: #424242; text-decoration: none; transition: all 0.15s; cursor: pointer; }
    .sel-modal-btn-primary { background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #fff; border-color: #2D9F4E; }
    .sel-modal-btn-primary:hover { box-shadow: 0 4px 14px rgba(15,61,34,0.2); }
    .sel-modal-btn-danger { background: #C0392B; color: #fff; border-color: #A02622; }
    .sel-modal-btn-danger:hover { background: #A02622; }
    .sel-detail-label { font-size: 0.8125rem; color: #757575; font-style: italic; }
    .sel-detail-value { font-size: 0.9375rem; font-weight: 600; color: #0F3D22; }
    .sel-payment-card { background: #F5FBF7; border-radius: 12px; border: 1px solid #D4E8DA; padding: 12px 14px; }
    .sel-note-card { background: #F5FBF7; border-radius: 12px; border: 1px solid #D4E8DA; padding: 10px 12px; }
    .sel-activity-log { background: #F5FBF7; border-radius: 12px; border: 1px solid #D4E8DA; padding: 10px 12px; }
</style>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div class="flex flex-wrap gap-2 items-center">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search store name, name, email, GCash…"
                   class="sel-search" style="width:240px;">
            <span class="sel-label">Filter by status:</span>
            <button wire:click="$set('statusFilter', '')" class="sel-filter-btn {{ $statusFilter === '' ? 'active' : '' }}">All</button>
            <button wire:click="$set('statusFilter', 'pending')" class="sel-filter-btn {{ $statusFilter === 'pending' ? 'active' : '' }}">Pending</button>
            <button wire:click="$set('statusFilter', 'approved')" class="sel-filter-btn {{ $statusFilter === 'approved' ? 'active' : '' }}">Approved</button>
            <button wire:click="$set('statusFilter', 'rejected')" class="sel-filter-btn {{ $statusFilter === 'rejected' ? 'active' : '' }}">Rejected</button>
            <button wire:click="$set('statusFilter', 'suspended')" class="sel-filter-btn {{ $statusFilter === 'suspended' ? 'active' : '' }}">Suspended</button>
        </div>
    </div>

    <div class="sel-table-card">
        <table class="sel-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Store</th>
                    <th>Status</th>
                    <th>Last Active</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->sellers as $seller)
                    <tr>
                        <td>{{ $seller->user->name ?? '—' }}</td>
                        <td>{{ $seller->store_name }}</td>
                        <td>
                            <span class="sel-status-badge {{ $seller->status === 'approved' ? 'sel-status-approved' : ($seller->status === 'rejected' ? 'sel-status-rejected' : ($seller->status === 'suspended' ? 'sel-status-suspended' : 'sel-status-pending')) }}">{{ ucfirst($seller->status) }}</span>
                            @if($seller->is_verified ?? false)
                                <span class="sel-verified" title="Verified seller">✓ Verified</span>
                            @endif
                        </td>
                        <td style="font-style:italic;color:#757575;">
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
                        <td style="font-style:italic;color:#757575;">{{ $seller->created_at?->format('M d, Y') }}</td>
                        <td>
                            <button wire:click="viewSeller({{ $seller->id }})" class="sel-action-btn sel-action-view">View</button>
                            @if($seller->status === 'approved')
                                <button wire:click="suspendSeller({{ $seller->id }})" class="sel-action-btn sel-action-suspend" style="margin-left:8px;">Suspend</button>
                            @elseif($seller->status === 'suspended')
                                <button wire:click="unsuspendSeller({{ $seller->id }})" class="sel-action-btn sel-action-unsuspend" style="margin-left:8px;">Unsuspend</button>
                            @endif
                            @if($seller->status === 'rejected')
                                <button wire:click="confirmDelete({{ $seller->id }})" class="sel-action-btn sel-action-delete" style="margin-left:8px;">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:32px 16px;color:#9E9E9E;font-style:italic;">No sellers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div style="padding:12px 16px;border-top:1px solid #D4E8DA;">
            {{ $this->sellers->links() }}
        </div>
    </div>

    @if($selectedSellerId)
        @php($seller = \App\Models\Seller::with(['user', 'payments', 'notes.admin', 'activityLogs'])->find($selectedSellerId))
        @if($seller)
            <div class="fixed inset-0 z-50" aria-modal="true">
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div wire:click="closeDetail" class="fixed inset-0 bg-black/50"></div>
                    <div class="sel-modal relative max-w-2xl w-full">
                        {{-- Header --}}
                        <div class="sel-modal-header">
                            <div class="flex justify-between items-start">
                                <div class="flex items-center gap-2 flex-1">
                                    <h3 class="sel-modal-title">{{ $seller->user->name }}</h3>
                                    @if($seller->is_verified ?? false)
                                        <span class="sel-verified">✓ Verified</span>
                                    @endif
                                </div>
                                <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600 ml-2" style="font-size:1.5rem;line-height:1;">&times;</button>
                            </div>
                            @if($seller->status === 'approved')
                                <button wire:click="toggleVerified({{ $seller->id }})" class="text-xs font-600 mt-2 hover:underline" style="color:{{ $seller->is_verified ? '#F57C00' : '#2D9F4E' }};">
                                    {{ $seller->is_verified ? '✓ Remove verified badge' : '○ Mark as verified' }}
                                </button>
                            @endif
                        </div>

                        {{-- Body --}}
                        <div class="sel-modal-body">
                            {{-- Basic Info --}}
                            <div class="sel-section">
                                <div class="sel-section-title">Account Details</div>
                                <div class="sel-detail-row compact">
                                    <span class="sel-detail-label">Store</span>
                                    <span class="sel-detail-value">{{ $seller->store_name }}</span>
                                </div>
                                <div class="sel-detail-row compact">
                                    <span class="sel-detail-label">Email</span>
                                    <span class="sel-detail-value">{{ $seller->user->email }}</span>
                                </div>
                                <div class="sel-detail-row compact">
                                    <span class="sel-detail-label">Contact</span>
                                    <span class="sel-detail-value">{{ $seller->user->contact_number ?? '—' }}</span>
                                </div>
                                <div class="sel-detail-row compact">
                                    <span class="sel-detail-label">GCash</span>
                                    <span class="sel-detail-value">{{ $seller->gcash_number }}</span>
                                </div>
                                <div class="sel-detail-row compact">
                                    <span class="sel-detail-label">Status</span>
                                    <span class="sel-detail-value">{{ ucfirst($seller->status) }}</span>
                                </div>
                                <div class="sel-detail-row compact">
                                    <span class="sel-detail-label">Subscription</span>
                                    <span class="sel-detail-value">
                                        @if($seller->subscription_due_date)
                                            {{ ucfirst($seller->subscription_status) }} · due {{ $seller->subscription_due_date->format('M d') }}
                                        @else
                                            Not set
                                        @endif
                                    </span>
                                </div>
                            </div>

                            {{-- Payments --}}
                            <div class="sel-section">
                                <div class="sel-section-title">Payments ({{ count($seller->payments) }})</div>
                                @forelse($seller->payments as $payment)
                                    <div class="sel-payment-card">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="flex-1">
                                                <div style="color:#0F3D22;font-weight:600;">{{ ucfirst($payment->type) }} — ₱{{ number_format($payment->amount, 2) }}</div>
                                                <div class="text-xs mt-1" style="color:#9E9E9E;font-style:italic;">Ref: {{ $payment->reference_number }}</div>
                                            </div>
                                            <span class="px-2 py-0.5 text-xs rounded flex-shrink-0" style="background:{{ $payment->status === 'approved' ? '#E8F5E9' : ($payment->status === 'rejected' ? '#FFEBEE' : '#FFF9E3') }};color:{{ $payment->status === 'approved' ? '#1B7A37' : ($payment->status === 'rejected' ? '#C0392B' : '#F57C00') }};">{{ $payment->status }}</span>
                                        </div>
                                        @if($payment->status === 'pending')
                                            <div class="flex gap-2 mt-2">
                                                <button wire:click="approvePayment({{ $payment->id }})" class="text-xs font-600 hover:underline flex-1" style="color:#1B7A37;">✓ Approve</button>
                                                <button wire:click="rejectPayment({{ $payment->id }})" class="text-xs font-600 hover:underline flex-1" style="color:#C0392B;">✕ Reject</button>
                                            </div>
                                        @endif
                                        @if($payment->screenshot_path)
                                            <img src="{{ asset('storage/' . $payment->screenshot_path) }}" alt="Screenshot" class="max-w-xs rounded border mt-2" style="border-color:#D4E8DA;max-height:120px;">
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs" style="color:#9E9E9E;font-style:italic;">No payments.</p>
                                @endforelse
                            </div>

                            {{-- Admin Notes --}}
                            <div class="sel-section">
                                <div class="sel-section-title">Admin Notes</div>
                                <div class="mb-2">
                                    <textarea wire:model.defer="newNote" rows="2" placeholder="Add a note…" class="sel-modal-textarea w-full"></textarea>
                                    @error('newNote') <span class="text-xs" style="color:#C0392B;">{{ $message }}</span> @enderror
                                    <button wire:click="addSellerNote" class="sel-modal-btn sel-modal-btn-primary mt-1 w-full">Add note</button>
                                </div>
                                <div class="space-y-2 max-h-32 overflow-y-auto">
                                    @forelse($seller->notes as $note)
                                        <div class="sel-note-card">
                                            <div style="color:#0F3D22;font-size:0.8125rem;">{{ $note->note }}</div>
                                            <div class="text-xs mt-1" style="color:#9E9E9E;font-style:italic;">{{ $note->created_at->format('M d, H:i') }} @if($note->admin) · {{ $note->admin->name }} @endif</div>
                                        </div>
                                    @empty
                                        <p class="text-xs" style="color:#9E9E9E;font-style:italic;">No notes yet.</p>
                                    @endforelse
                                </div>
                            </div>

                            {{-- Activity Log --}}
                            <div class="sel-section">
                                <div class="sel-section-title">Activity Log</div>
                                <div class="space-y-2 max-h-40 overflow-y-auto">
                                    @forelse($seller->activityLogs->take(20) as $log)
                                        <div class="sel-activity-log">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-xs" style="color:#9E9E9E;">{{ $log->created_at->format('M d H:i') }}</span>
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase" style="background:#E8F5E9;color:#1B7A37;">
                                                    {{ str_replace('_', ' ', $log->action) }}
                                                </span>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs" style="color:#9E9E9E;">No activity logged yet.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="sel-modal-footer">
                            @if($seller->status === 'approved')
                                <button wire:click="suspendSeller({{ $seller->id }})" class="sel-modal-btn" style="background:#FFF3E0;color:#E65100;border-color:#F57C00;">Suspend</button>
                            @elseif($seller->status === 'suspended')
                                <button wire:click="unsuspendSeller({{ $seller->id }})" class="sel-modal-btn" style="background:#E8F5E9;color:#1B7A37;border-color:#2D9F4E;">Unsuspend</button>
                            @endif
                            @if($seller->status === 'rejected')
                                <button wire:click="confirmDelete({{ $seller->id }})" class="sel-modal-btn sel-modal-btn-danger">Delete</button>
                            @endif
                            <button wire:click="closeDetail" class="sel-modal-btn">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    @if($showDeleteConfirm)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50">
            <div class="sel-modal max-w-sm w-full p-6">
                <p style="color:#0F3D22;">Delete this rejected seller and their user account? This cannot be undone.</p>
                <div class="mt-4 flex gap-2 justify-end">
                    <button wire:click="cancelDelete" class="sel-modal-btn">Cancel</button>
                    <button wire:click="deleteSeller" class="sel-modal-btn sel-modal-btn-danger">Delete</button>
                </div>
            </div>
        </div>
    @endif

    @if($showRejectModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/50">
            <div class="sel-modal max-w-md w-full p-6">
                <h3 class="sel-modal-title">Reject Payment</h3>
                <p class="mt-1 text-sm" style="color:#757575;font-style:italic;">
                    Please enter a reason. This will be shown to the seller.
                </p>
                <div class="mt-4">
                    <label class="sel-modal-label">Reason</label>
                    <textarea wire:model.defer="rejectReason" rows="3"
                              class="sel-modal-textarea w-full mt-2"
                              placeholder="e.g. Screenshot unclear, reference number mismatch..."></textarea>
                    @error('rejectReason') <div class="mt-1 text-xs" style="color:#C0392B;">{{ $message }}</div> @enderror
                </div>
                <div class="mt-4 flex gap-2 justify-end">
                    <button wire:click="cancelReject" class="sel-modal-btn">Cancel</button>
                    <button wire:click="confirmReject" class="sel-modal-btn sel-modal-btn-danger">Reject</button>
                </div>
            </div>
        </div>
    @endif

    @if($showSuspendModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/50">
            <div class="sel-modal max-w-md w-full p-6">
                <h3 class="sel-modal-title">Suspend Seller</h3>
                <p class="mt-1 text-sm" style="color:#757575;font-style:italic;">
                    Select a reason for suspension. This will be sent as a message to the seller.
                </p>
                
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="sel-modal-label">Reason</label>
                        <select wire:model.live="suspensionReason" 
                                class="sel-modal-select w-full mt-2">
                            @foreach($suspensionReasonsList as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($suspensionReason === 'Other')
                        <div>
                            <label class="sel-modal-label">Custom Reason/Note</label>
                            <textarea wire:model.defer="customSuspensionNote" rows="3"
                                      class="sel-modal-textarea w-full mt-2"
                                      placeholder="Explain the specific reason..."></textarea>
                            @error('customSuspensionNote') <div class="mt-1 text-xs" style="color:#C0392B;">{{ $message }}</div> @enderror
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex gap-2 justify-end">
                    <button wire:click="cancelSuspension" class="sel-modal-btn">Cancel</button>
                    <button wire:click="confirmSuspension" class="sel-modal-btn" style="background:#F57C00;color:#fff;border-color:#E65100;">Suspend Seller</button>
                </div>
            </div>
        </div>
    @endif

    @if($showUnsuspendModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/50">
            <div class="sel-modal max-w-md w-full p-6">
                <h3 class="sel-modal-title" style="color:#1B7A37;">Reactivate Seller Account</h3>
                <p class="mt-1 text-sm" style="color:#757575;font-style:italic;">
                    Please provide a reason or note for reactivating this account (e.g., issues resolved).
                </p>
                
                <div class="mt-4">
                    <label class="sel-modal-label">Resolution Note</label>
                    <textarea wire:model.defer="unsuspendReason" rows="3"
                                class="sel-modal-textarea w-full mt-2"
                                placeholder="e.g. Terms complied, Issue resolved..."></textarea>
                    @error('unsuspendReason') <div class="mt-1 text-xs" style="color:#C0392B;">{{ $message }}</div> @enderror
                </div>

                <div class="mt-6 flex gap-2 justify-end">
                    <button wire:click="cancelUnsuspension" class="sel-modal-btn">Cancel</button>
                    <button wire:click="confirmUnsuspension" class="sel-modal-btn" style="background:#1B7A37;color:#fff;border-color:#0F3D22;">Confirm Reactivation</button>
                </div>
            </div>
        </div>
    @endif
</div>
