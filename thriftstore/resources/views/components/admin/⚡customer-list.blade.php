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
            $q->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('username', 'like', '%' . $this->search . '%');
            });
        }
        return $q->orderByDesc('created_at')->paginate(10);
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

<div>
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email..." class="rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-full max-w-md">
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($this->customers as $customer)
                    <tr class="{{ $customer->is_suspicious ? 'bg-amber-50' : '' }}">
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div class="flex items-center gap-2">
                                <span>{{ $customer->name }}</span>
                                @if($customer->is_suspicious)
                                    <span class="px-2 py-0.5 text-[11px] rounded bg-amber-100 text-amber-900 border border-amber-200">
                                        Suspicious
                                    </span>
                                @endif
                            </div>
                            @if($customer->is_suspicious && $customer->suspicious_reason)
                                <div class="text-xs text-amber-800 mt-1">
                                    Reason: {{ $customer->suspicious_reason }}
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $customer->username ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $customer->email }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $customer->contact_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $customer->created_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <button wire:click="viewOrders({{ $customer->id }})" class="text-indigo-600 hover:underline">Orders</button>
                            @if($customer->is_suspicious)
                                <button wire:click="unflagCustomer({{ $customer->id }})" class="ml-2 text-green-700 hover:underline">Unflag</button>
                            @else
                                <button wire:click="flagCustomer({{ $customer->id }})" class="ml-2 text-amber-700 hover:underline">Flag</button>
                            @endif
                            <button wire:click="confirmDelete({{ $customer->id }})" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No customers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-2 border-t">
            {{ $this->customers->links() }}
        </div>
    </div>

    @if($showDeleteConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm w-full">
                <p class="text-gray-700">Delete this customer account? This cannot be undone.</p>
                <div class="mt-4 flex gap-2 justify-end">
                    <button wire:click="cancelDelete" class="px-3 py-1 border rounded">Cancel</button>
                    <button wire:click="deleteCustomer" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
                </div>
            </div>
        </div>
    @endif

    @if($showOrdersModal && $ordersCustomerId)
        @php($cust = \App\Models\User::query()->find($ordersCustomerId))
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div wire:click="closeOrders" class="fixed inset-0 bg-black/50"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex justify-between items-start gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Customer Order History</h3>
                                <div class="text-sm text-gray-600">
                                    {{ $cust?->name ?? 'Customer' }} — {{ $cust?->email ?? '' }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Showing customer orders only (no seller analytics).
                                </div>
                            </div>
                            <button wire:click="closeOrders" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>

                        <div class="mt-4 space-y-3">
                            @forelse($this->orders as $order)
                                <div class="border rounded-lg p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="text-sm font-medium text-gray-800">
                                            Order #{{ $order->id }}
                                            <span class="ml-2 text-xs text-gray-500">{{ $order->created_at?->format('M d, Y g:i A') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-1 text-xs rounded
                                                {{ $order->status === 'delivered' ? 'bg-green-100 text-green-800' : ($order->status === 'cancelled' ? 'bg-red-100 text-red-800' : ($order->status === 'shipped' ? 'bg-indigo-100 text-indigo-800' : 'bg-yellow-100 text-yellow-800')) }}">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                            <span class="text-sm font-semibold text-gray-900">₱{{ number_format((float) $order->total_amount, 2) }}</span>
                                        </div>
                                    </div>

                                    <div class="mt-2 text-sm text-gray-700">
                                        <span class="text-gray-500">Seller:</span>
                                        {{ $order->seller?->store_name ?? '—' }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        <span class="text-gray-500">Tracking:</span>
                                        {{ $order->tracking_number ?: '—' }}
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500">
                                        <span class="text-gray-500">Ship to:</span>
                                        {{ \Illuminate\Support\Str::limit((string) $order->shipping_address, 140) }}
                                    </div>

                                    <div class="mt-3">
                                        <div class="text-xs font-semibold text-gray-600 uppercase">Items</div>
                                        <div class="mt-2 overflow-x-auto">
                                            <table class="min-w-full text-sm">
                                                <thead class="text-xs text-gray-500">
                                                    <tr>
                                                        <th class="text-left py-1 pr-3">Product</th>
                                                        <th class="text-right py-1 pr-3">Qty</th>
                                                        <th class="text-right py-1 pr-3">Price</th>
                                                        <th class="text-right py-1">Line total</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y">
                                                    @foreach($order->items as $item)
                                                        <tr>
                                                            <td class="py-2 pr-3 text-gray-800">{{ $item->product?->name ?? '—' }}</td>
                                                            <td class="py-2 pr-3 text-right text-gray-700">{{ $item->quantity }}</td>
                                                            <td class="py-2 pr-3 text-right text-gray-700">₱{{ number_format((float) $item->price_at_purchase, 2) }}</td>
                                                            <td class="py-2 text-right text-gray-900 font-medium">₱{{ number_format((float) ($item->price_at_purchase * $item->quantity), 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-sm text-gray-500">No orders found for this customer.</div>
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
                <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full">
                    <div class="p-6">
                        <div class="flex justify-between items-start gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Flag suspicious customer</h3>
                                <div class="text-sm text-gray-600">{{ $cust?->name ?? 'Customer' }} — {{ $cust?->email ?? '' }}</div>
                            </div>
                            <button wire:click="closeFlag" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm text-gray-600">Reason (optional)</label>
                            <input type="text" wire:model.defer="flagReason" class="mt-1 rounded border-gray-300 w-full" placeholder="e.g. repeated cancellations, abuse" />
                            @error('flagReason') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                            <div class="text-xs text-gray-500 mt-2">Flagged customers are highlighted in the list for quick review.</div>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <button wire:click="closeFlag" class="px-3 py-1 border rounded">Cancel</button>
                            <button wire:click="confirmFlag" class="px-3 py-1 bg-amber-600 text-white rounded hover:bg-amber-700">Flag customer</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
