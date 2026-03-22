<?php

use App\Models\User;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showDeleteConfirm = false;
    public ?int $deleteUserId = null;

    public bool $showOrdersModal = false;
    public ?int $ordersCustomerId = null;

    public bool $showFlagModal = false;
    public ?int $flagCustomerId = null;
    public string $flagReason = '';

    protected $queryString = ['search' => ['except' => '']];

    #[Computed]
    public function customers()
    {
        $q = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'customer'));
        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $q->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('username', 'like', $term)
                    ->orWhere('contact_number', 'like', $term);
            });
        }
        return $q->orderByDesc('created_at')->paginate(20);
    }

    public function confirmDelete(int $id): void
    {
        $user = User::query()->findOrFail($id);
        if (!$user->hasRole('customer')) {
            return;
        }
        $this->deleteUserId = $id;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deleteUserId = null;
    }

    public function viewOrders(int $customerId): void
    {
        $user = User::query()->findOrFail($customerId);
        if (! $user->hasRole('customer')) {
            return;
        }
        $this->ordersCustomerId = $customerId;
        $this->showOrdersModal = true;
    }

    public function closeOrders(): void
    {
        $this->showOrdersModal = false;
        $this->ordersCustomerId = null;
    }

    public function flagCustomer(int $customerId): void
    {
        $user = User::query()->findOrFail($customerId);
        if (! $user->hasRole('customer')) {
            return;
        }
        $this->flagCustomerId = $customerId;
        $this->flagReason = (string) ($user->suspicious_reason ?? '');
        $this->showFlagModal = true;
        $this->resetErrorBag();
    }

    public function closeFlag(): void
    {
        $this->showFlagModal = false;
        $this->flagCustomerId = null;
        $this->flagReason = '';
        $this->resetErrorBag();
    }

    public function confirmFlag(): void
    {
        if (! $this->flagCustomerId) {
            return;
        }

        $this->validate([
            'flagReason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->findOrFail($this->flagCustomerId);
        if (! $user->hasRole('customer')) {
            $this->closeFlag();
            return;
        }

        $user->forceFill([
            'is_suspicious' => true,
            'suspicious_reason' => $this->flagReason !== '' ? $this->flagReason : null,
            'suspicious_flagged_at' => now(),
        ])->save();

        $this->closeFlag();
    }

    public function unflagCustomer(int $customerId): void
    {
        $user = User::query()->findOrFail($customerId);
        if (! $user->hasRole('customer')) {
            return;
        }
        $user->forceFill([
            'is_suspicious' => false,
            'suspicious_reason' => null,
            'suspicious_flagged_at' => null,
        ])->save();
    }

    #[Computed]
    public function orders()
    {
        if (! $this->ordersCustomerId) {
            return collect();
        }

        return Order::query()
            ->where('customer_id', $this->ordersCustomerId)
            ->with(['seller', 'items.product'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function deleteCustomer(): void
    {
        if ($this->deleteUserId === null) {
            return;
        }
        $user = User::query()->findOrFail($this->deleteUserId);
        if ($user->hasRole('customer')) {
            try {
                $user->delete();
                $this->dispatch('customer-deleted');
            } catch (\Throwable $e) {
                $this->dispatch('notify', message: 'Cannot delete: customer may have orders.', type: 'error');
            }
        }
        $this->cancelDelete();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
};
?>

<style>
    .cus-search { border-radius: 50px; border: 1.5px solid #D4E8DA; padding: 8px 16px; font-size: 0.8125rem; background: #fff; color: #424242; transition: all 0.15s; }
    .cus-search:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .cus-table-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; overflow: hidden; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .cus-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .cus-table th { padding: 9px 16px; text-align: left; font-size: 0.6875rem; font-weight: 700; color: #1B7A37; text-transform: uppercase; letter-spacing: 0.05em; background: #F5FBF7; border-bottom: 1px solid #D4E8DA; }
    .cus-table td { padding: 9px 16px; color: #424242; border-bottom: 1px solid #F0F7F2; }
    .cus-table tr:last-child td { border-bottom: none; }
    .cus-table tr:hover td { background: #F5FBF7; }
    .cus-table tr.suspicious td { background: #FFFBF0; }
    .cus-table tr.suspicious:hover td { background: #FFF3E0; }
    .cus-suspicious-badge { background: #FFF3E0; color: #E65100; padding: 4px 8px; border-radius: 50px; font-size: 0.7rem; font-weight: 600; }
    .cus-action-btn { font-size: 0.8125rem; font-weight: 600; text-decoration: none; transition: all 0.15s; }
    .cus-action-orders { color: #2D9F4E; }
    .cus-action-orders:hover { color: #1B7A37; text-decoration: underline; }
    .cus-action-flag { color: #F57C00; }
    .cus-action-flag:hover { color: #E65100; text-decoration: underline; }
    .cus-action-unflag { color: #1B7A37; }
    .cus-action-unflag:hover { color: #0F3D22; text-decoration: underline; }
    .cus-action-delete { color: #C0392B; }
    .cus-action-delete:hover { color: #A02622; text-decoration: underline; }
    .cus-modal { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; box-shadow: 0 10px 40px rgba(15,61,34,0.2); }
    .cus-modal-title { font-size: 1.125rem; font-weight: 800; color: #0F3D22; }
    .cus-modal-label { font-size: 0.8125rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; font-style: italic; margin-bottom: 6px; }
    .cus-modal-input, .cus-modal-textarea { border-radius: 12px; border: 1.5px solid #D4E8DA; padding: 8px 12px; font-size: 0.8125rem; color: #424242; transition: all 0.15s; }
    .cus-modal-input:focus, .cus-modal-textarea:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .cus-modal-btn { padding: 8px 16px; border-radius: 50px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid #D4E8DA; background: #fff; color: #424242; text-decoration: none; transition: all 0.15s; cursor: pointer; }
    .cus-modal-btn-primary { background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #fff; border-color: #2D9F4E; }
    .cus-modal-btn-primary:hover { box-shadow: 0 4px 14px rgba(15,61,34,0.2); }
    .cus-modal-btn-danger { background: #C0392B; color: #fff; border-color: #A02622; }
    .cus-modal-btn-danger:hover { background: #A02622; }
    .cus-order-card { background: #fff; border-radius: 12px; border: 1px solid #D4E8DA; padding: 12px 14px; }
    .cus-order-status { padding: 4px 8px; border-radius: 50px; font-size: 0.7rem; font-weight: 600; }
    .cus-order-status-delivered { background: #E8F5E9; color: #1B7A37; }
    .cus-order-status-cancelled { background: #FFEBEE; color: #C0392B; }
    .cus-order-status-shipped { background: #E3F2FD; color: #1565C0; }
    .cus-order-status-pending { background: #FFF9E3; color: #F57C00; }
</style>

<div>
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, username, email, or contact number…" class="cus-search w-full max-w-md">
    </div>

    <div class="cus-table-card">
        <table class="cus-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->customers as $customer)
                    <tr class="{{ $customer->is_suspicious ? 'suspicious' : '' }}">
                        <td>
                            <div class="flex items-center gap-2">
                                <span style="color:#0F3D22;font-weight:600;">{{ $customer->name }}</span>
                                @if($customer->is_suspicious)
                                    <span class="cus-suspicious-badge">⚠ Suspicious</span>
                                @endif
                            </div>
                            @if($customer->is_suspicious && $customer->suspicious_reason)
                                <div class="text-xs mt-1" style="color:#E65100;font-style:italic;">
                                    {{ $customer->suspicious_reason }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $customer->username ?? '—' }}</td>
                        <td>{{ $customer->email }}</td>
                        <td style="color:#757575;font-style:italic;">{{ $customer->contact_number ?? '—' }}</td>
                        <td style="color:#757575;font-style:italic;">{{ $customer->created_at?->format('M d, Y') }}</td>
                        <td>
                            <button wire:click="viewOrders({{ $customer->id }})" class="cus-action-btn cus-action-orders">Orders</button>
                            @if($customer->is_suspicious)
                                <button wire:click="unflagCustomer({{ $customer->id }})" class="cus-action-btn cus-action-unflag" style="margin-left:8px;">Unflag</button>
                            @else
                                <button wire:click="flagCustomer({{ $customer->id }})" class="cus-action-btn cus-action-flag" style="margin-left:8px;">Flag</button>
                            @endif
                            <button wire:click="confirmDelete({{ $customer->id }})" class="cus-action-btn cus-action-delete" style="margin-left:8px;">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:32px 16px;color:#9E9E9E;font-style:italic;">No customers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div style="padding:12px 16px;border-top:1px solid #D4E8DA;">
            {{ $this->customers->links() }}
        </div>
    </div>

    @if($showDeleteConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="cus-modal max-w-sm w-full p-6">
                <p style="color:#0F3D22;">Delete this customer account? This cannot be undone.</p>
                <div class="mt-4 flex gap-2 justify-end">
                    <button wire:click="cancelDelete" class="cus-modal-btn">Cancel</button>
                    <button wire:click="deleteCustomer" class="cus-modal-btn cus-modal-btn-danger">Delete</button>
                </div>
            </div>
        </div>
    @endif

    @if($showOrdersModal && $ordersCustomerId)
        @php($cust = \App\Models\User::query()->find($ordersCustomerId))
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div wire:click="closeOrders" class="fixed inset-0 bg-black/50"></div>
                <div class="cus-modal relative max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex justify-between items-start gap-4">
                            <div>
                                <h3 class="cus-modal-title">Customer Order History</h3>
                                <div class="text-sm mt-1" style="color:#757575;font-style:italic;">
                                    {{ $cust?->name ?? 'Customer' }} — {{ $cust?->email ?? '' }}
                                </div>
                                <div class="text-xs mt-1" style="color:#9E9E9E;font-style:italic;">
                                    Showing customer orders only (no seller analytics).
                                </div>
                            </div>
                            <button wire:click="closeOrders" class="text-gray-400 hover:text-gray-600" style="font-size:1.5rem;line-height:1;">&times;</button>
                        </div>

                        <div class="mt-4 space-y-3">
                            @forelse($this->orders as $order)
                                <div class="cus-order-card">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div style="color:#0F3D22;font-weight:600;">
                                            Order #{{ $order->id }}
                                            <span class="ml-2 text-xs" style="color:#9E9E9E;font-style:italic;">{{ $order->created_at?->format('M d, Y g:i A') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="cus-order-status {{ $order->status === 'delivered' ? 'cus-order-status-delivered' : ($order->status === 'cancelled' ? 'cus-order-status-cancelled' : ($order->status === 'shipped' ? 'cus-order-status-shipped' : 'cus-order-status-pending')) }}">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                            <span style="font-size:0.9375rem;font-weight:700;color:#0F3D22;">₱{{ number_format((float) $order->total_amount, 2) }}</span>
                                        </div>
                                    </div>

                                    <div class="mt-2 text-sm" style="color:#424242;">
                                        <span class="cus-modal-label">Seller:</span>
                                        {{ $order->seller?->store_name ?? '—' }}
                                    </div>
                                    <div class="mt-1 text-xs" style="color:#9E9E9E;font-style:italic;">
                                        <span class="cus-modal-label">Tracking:</span>
                                        {{ $order->tracking_number ?: '—' }}
                                    </div>
                                    <div class="mt-2 text-xs" style="color:#9E9E9E;font-style:italic;">
                                        <span class="cus-modal-label">Ship to:</span>
                                        {{ \Illuminate\Support\Str::limit((string) $order->shipping_address, 140) }}
                                    </div>

                                    <div class="mt-3">
                                        <div class="cus-modal-label">Items</div>
                                        <div class="mt-2 overflow-x-auto">
                                            <table class="min-w-full text-sm">
                                                <thead style="color:#1B7A37;font-size:0.6875rem;font-weight:700;text-transform:uppercase;background:#F5FBF7;">
                                                    <tr>
                                                        <th class="text-left py-1 pr-3">Product</th>
                                                        <th class="text-right py-1 pr-3">Qty</th>
                                                        <th class="text-right py-1 pr-3">Price</th>
                                                        <th class="text-right py-1">Line total</th>
                                                    </tr>
                                                </thead>
                                                <tbody style="border-top:1px solid #D4E8DA;">
                                                    @foreach($order->items as $item)
                                                        <tr style="border-bottom:1px solid #F0F7F2;">
                                                            <td class="py-2 pr-3" style="color:#0F3D22;">{{ $item->product?->name ?? '—' }}</td>
                                                            <td class="py-2 pr-3 text-right" style="color:#424242;">{{ $item->quantity }}</td>
                                                            <td class="py-2 pr-3 text-right" style="color:#424242;">₱{{ number_format((float) $item->price_at_purchase, 2) }}</td>
                                                            <td class="py-2 text-right" style="color:#0F3D22;font-weight:700;">₱{{ number_format((float) ($item->price_at_purchase * $item->quantity), 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-sm" style="color:#9E9E9E;font-style:italic;">No orders found for this customer.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showFlagModal && $flagCustomerId)
        @php($cust = \App\Models\User::query()->find($flagCustomerId))
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div wire:click="closeFlag" class="fixed inset-0 bg-black/50"></div>
                <div class="cus-modal relative max-w-lg w-full p-6">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h3 class="cus-modal-title">Flag Suspicious Customer</h3>
                            <div class="text-sm mt-1" style="color:#757575;font-style:italic;">{{ $cust?->name ?? 'Customer' }} — {{ $cust?->email ?? '' }}</div>
                        </div>
                        <button wire:click="closeFlag" class="text-gray-400 hover:text-gray-600" style="font-size:1.5rem;line-height:1;">&times;</button>
                    </div>

                    <div class="mt-4">
                        <label class="cus-modal-label">Reason (optional)</label>
                        <input type="text" wire:model.defer="flagReason" class="cus-modal-input w-full mt-2" placeholder="e.g. repeated cancellations, abuse" />
                        @error('flagReason') <div class="text-sm mt-1" style="color:#C0392B;">{{ $message }}</div> @enderror
                        <div class="text-xs mt-2" style="color:#9E9E9E;font-style:italic;">Flagged customers are highlighted in the list for quick review.</div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button wire:click="closeFlag" class="cus-modal-btn">Cancel</button>
                        <button wire:click="confirmFlag" class="cus-modal-btn" style="background:#F57C00;color:#fff;border-color:#E65100;">Flag customer</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
