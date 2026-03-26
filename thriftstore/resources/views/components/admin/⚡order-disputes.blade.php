<?php

use App\Models\AdminAction;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\User;
use App\Notifications\OrderDisputeUpdated;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $status = '';
    public string $reason = '';
    public string $search = '';

    public bool $showResolutionModal = false;
    public ?int $resolutionDisputeId = null;
    public string $resolutionAction = '';
    public string $resolutionNote = '';

    protected $queryString = [
        'status' => ['except' => ''],
        'reason' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    #[Computed]
    public function disputes()
    {
        $q = OrderDispute::query()
            ->with(['order', 'customer', 'seller.user'])
            ->orderByDesc('created_at');

        if ($this->status !== '') {
            $q->where('status', $this->status);
        }

        if ($this->reason !== '') {
            $q->where('reason_code', $this->reason);
        }

        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $q->where(function ($query) use ($term) {
                $query->where('id', 'like', $term)
                    ->orWhere('order_id', 'like', $term)
                    ->orWhereHas('customer', function ($q2) use ($term) {
                        $q2->where('name', 'like', $term)
                           ->orWhere('email', 'like', $term);
                    })
                    ->orWhereHas('seller', function ($q2) use ($term) {
                        $q2->where('store_name', 'like', $term);
                    });
            });
        }

        return $q->paginate(20);
    }

    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingReason(): void { $this->resetPage(); }
    public function updatingSearch(): void { $this->resetPage(); }

    public function riskMeta(OrderDispute $dispute): array
    {
        $customerId = (int) $dispute->customer_id;

        $recentNotReceived = OrderDispute::query()
            ->where('customer_id', $customerId)
            ->where('reason_code', 'parcel_not_received')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $lifetimeNotReceived = OrderDispute::query()
            ->where('customer_id', $customerId)
            ->where('reason_code', 'parcel_not_received')
            ->count();

        return [
            'recent' => $recentNotReceived,
            'lifetime' => $lifetimeNotReceived,
            'isHighRisk' => $recentNotReceived >= 3,
        ];
    }

    public function closeResolutionModal(): void
    {
        $this->showResolutionModal = false;
        $this->resolutionDisputeId = null;
        $this->resolutionAction = '';
        $this->resolutionNote = '';
        $this->resetErrorBag();
    }



    protected function notifyDisputeParties(OrderDispute $dispute, string $event): void
    {
        if ($dispute->customer) {
            $dispute->customer->notify(new OrderDisputeUpdated($dispute, $event));
        }

        $sellerUser = $dispute->seller?->user;
        if ($sellerUser) {
            $sellerUser->notify(new OrderDisputeUpdated($dispute, $event));
        }

        User::query()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })
            ->where('id', '!=', auth('admin')->id())
            ->get()
            ->each(function (User $admin) use ($dispute, $event) {
                $admin->notify(new OrderDisputeUpdated($dispute, $event));
            });
    }

    protected function logAdminAction(OrderDispute $dispute, string $action, string $reason): void
    {
        AdminAction::query()->create([
            'admin_id' => auth('admin')->id(),
            'action' => $action,
            'target_type' => 'order_dispute',
            'target_id' => $dispute->id,
            'reason' => $reason,
            'details' => [
                'order_id' => $dispute->order_id,
                'status' => $dispute->status,
                'reason_code' => $dispute->reason_code,
            ],
        ]);
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search dispute/order/customer/seller..."
                   class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-72">

            <select wire:model.live="status"
                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All statuses</option>
                @foreach(\App\Models\OrderDispute::STATUSES as $statusValue)
                    <option value="{{ $statusValue }}">{{ \App\Models\OrderDispute::statusLabel($statusValue) }}</option>
                @endforeach
            </select>

            <select wire:model.live="reason"
                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All reasons</option>
                @foreach(\App\Models\OrderDispute::REASON_CODES as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="text-xs text-gray-500">
            Showing {{ $this->disputes->firstItem() ?? 0 }}-{{ $this->disputes->lastItem() ?? 0 }} of {{ $this->disputes->total() }} disputes
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dispute</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Risk</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($this->disputes as $dispute)
                    @php($risk = $this->riskMeta($dispute))
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">#{{ $dispute->id }}</div>
                            <div class="text-xs text-gray-500">{{ \App\Models\OrderDispute::REASON_CODES[$dispute->reason_code] ?? $dispute->reason_code }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $dispute->created_at?->format('Y-m-d H:i') }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            #{{ $dispute->order_id }}
                            <div class="text-xs text-gray-500">{{ $dispute->order?->tracking_number ?? 'No tracking' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            {{ $dispute->customer?->name ?? '—' }}
                            <div class="text-xs text-gray-500">{{ $dispute->customer?->email ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            {{ $dispute->seller?->store_name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                {{ \App\Models\OrderDispute::statusLabel($dispute->status) }}
                            </span>
                            @if($dispute->seller_responded_at)
                                <div class="mt-1 text-[11px] text-gray-500">Seller responded {{ $dispute->seller_responded_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <div class="{{ $risk['isHighRisk'] ? 'text-red-700 font-semibold' : 'text-gray-600' }}">
                                30d not-received: {{ $risk['recent'] }}
                            </div>
                            <div class="text-gray-500">Lifetime: {{ $risk['lifetime'] }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs space-y-1">
                            @if($dispute->evidence_path)
                                <a href="{{ $dispute->evidence_url }}" target="_blank"
                                   class="inline-block text-indigo-600 hover:text-indigo-800 underline font-medium">
                                    View Buyer Evidence
                                </a>
                            @else
                                <span class="text-gray-400 italic">No evidence provided</span>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No disputes found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t">
            {{ $this->disputes->links() }}
        </div>
    </div>


</div>
