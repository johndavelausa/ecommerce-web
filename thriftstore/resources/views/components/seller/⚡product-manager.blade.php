<?php

use App\Models\Product;
use App\Models\ProductHistory;
use App\Models\SellerActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;

    // list state
    public string $search = '';
    public string $filterStatus = '';   // '' | 'active' | 'inactive'
    /** B1 v1.4 — Condition filter (seller's own inventory) */
    public string $filterCondition = ''; // '' | new | like_new | good | fair | poor

    // form state
    public string $mode = 'list';       // list | create | edit
    public ?int $editingId = null;

    public string $name = '';
    public string $description = '';
    public string $category = '';
    public string $tags = '';
    public string $price = '';
    public string $sale_price = '';
    public string $condition = 'good';   // new, like_new, good, fair, poor (A1 - v1.3)
    public string $size_variant = '';    // C1 v1.4 — optional size/variant (xs,s,m,l,xl,xxl,free_size or custom)
    public string $size_custom = '';      // when size_variant is 'custom'
    public string $delivery_fee = '';    // optional; used when seller delivery_option is per_product (A2 - v1.3)
    public string $low_stock_threshold = '10'; // B1 v1.4 — per-product low stock warning level
    public int    $stock = 0;
    public bool   $is_active = true;
    public $image = null;               // Livewire temp upload

    // delete confirm
    public bool $showDeleteConfirm = false;
    public ?int $deleteId = null;

    // stock adjust
    public bool $showStockModal = false;
    public ?int $stockProductId = null;
    public int  $stockDelta = 0;
    public string $stockNote = '';

    // inventory history (B2 - v1.3: paginated)
    public bool $showHistoryModal = false;
    public ?int $historyProductId = null;
    public int $historyPage = 1;

    // bulk stock update
    public array $selected = [];
    public bool $showBulkStockModal = false;
    public int $bulkStockDelta = 0;
    public string $bulkStockNote = '';

    protected $queryString = [
        'search'          => ['except' => ''],
        'filterStatus'    => ['except' => ''],
        'filterCondition' => ['except' => ''],
    ];

    #[Computed]
    public function seller()
    {
        return Auth::guard('seller')->user()?->seller;
    }

    #[Computed]
    public function products()
    {
        $q = Product::query()
            ->where('seller_id', $this->seller?->id)
            ->orderByDesc('created_at');

        if ($this->search !== '') {
            $q->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->filterStatus === 'active') {
            $q->where('is_active', true);
        } elseif ($this->filterStatus === 'inactive') {
            $q->where('is_active', false);
        }

        if ($this->filterCondition !== '') {
            $q->where('condition', $this->filterCondition);
        }

        return $q->paginate(20);
    }

    #[Computed]
    public function sellerCategories(): array
    {
        $seller = $this->seller;
        if (! $seller) {
            return [];
        }

        return Product::query()
            ->where('seller_id', $seller->id)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterCondition(): void { $this->resetPage(); }

    /** C1 v1.4 — resolved size/variant value for DB (preset key or custom text). */
    protected function sizeVariantValue(): ?string
    {
        if ($this->size_variant === 'custom' && trim($this->size_custom) !== '') {
            return trim($this->size_custom);
        }
        if ($this->size_variant !== '' && $this->size_variant !== 'custom') {
            return $this->size_variant;
        }
        return null;
    }

    public function showCreate(): void
    {
        $this->reset(['name','description','category','tags','price','sale_price','condition','size_variant','size_custom','delivery_fee','stock','is_active','image','editingId','low_stock_threshold']);
        $this->condition = 'good';
        $this->size_variant = '';
        $this->size_custom = '';
        $this->low_stock_threshold = '10';
        $this->is_active = true;
        $this->mode = 'create';
    }

    public function showEdit(int $id): void
    {
        $product = Product::query()
            ->where('seller_id', $this->seller?->id)
            ->findOrFail($id);

        $this->editingId      = $product->id;
        $this->name           = $product->name;
        $this->description    = $product->description;
        $this->category       = (string) ($product->category ?? '');
        $this->tags           = (string) ($product->tags ?? '');
        $this->price          = (string) $product->price;
        $this->sale_price     = $product->sale_price !== null ? (string) $product->sale_price : '';
        $this->condition      = (string) ($product->condition ?? 'good');
        $preset = array_keys(\App\Models\Product::sizeVariantOptions());
        $this->size_variant  = $product->size_variant && in_array($product->size_variant, $preset, true) ? $product->size_variant : ($product->size_variant ? 'custom' : '');
        $this->size_custom   = $product->size_variant && !in_array($product->size_variant, $preset, true) ? (string) $product->size_variant : '';
        $this->delivery_fee   = $product->delivery_fee !== null ? (string) $product->delivery_fee : '';
        $this->stock          = $product->stock;
        $this->low_stock_threshold = (string) ($product->low_stock_threshold ?? 10);
        $this->is_active      = (bool) $product->is_active;
        $this->image          = null;
        $this->mode           = 'edit';
    }

    public function save(): void
    {
        $rules = [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category'    => ['required', 'string', 'max:50'],
            'tags'        => ['nullable', 'string', 'max:255'],
            'price'       => ['required', 'numeric', 'min:0'],
            'sale_price'  => ['nullable', 'numeric', 'min:0'],
            'condition'   => ['required', 'string', 'in:new,like_new,good,fair,poor'],
            'size_variant' => ['nullable', 'string', 'max:100'],
            'size_custom'  => ['nullable', 'string', 'max:100'],
            'delivery_fee'=> ['nullable', 'numeric', 'min:0'],
            'stock'       => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
            'image'       => $this->mode === 'create'
                ? ['required', 'image', 'max:5120']
                : ['nullable', 'image', 'max:5120'],
        ];

        $this->validate($rules);

        $seller = $this->seller;
        if (! $seller) abort(403);

        if ($this->mode === 'create') {
            // Stock inheritance: same seller + same name + same price → just add stock
            $existing = Product::query()
                ->where('seller_id', $seller->id)
                ->where('name', $this->name)
                ->where('price', $this->price)
                ->first();

            if ($existing) {
                $oldStock = $existing->stock;
                $newStock = $oldStock + $this->stock;
                $existing->update([
                    'stock'       => $newStock,
                    'description' => $this->description,
                    'category'    => $this->category,
                    'tags'        => $this->tags !== '' ? $this->tags : $existing->tags,
                    'sale_price'  => $this->sale_price !== '' ? $this->sale_price : $existing->sale_price,
                    'condition'   => $this->condition,
                    'size_variant' => $this->sizeVariantValue(),
                    'delivery_fee'=> $this->delivery_fee !== '' ? $this->delivery_fee : $existing->delivery_fee,
                    'low_stock_threshold' => (int) $this->low_stock_threshold ?: $existing->low_stock_threshold,
                    'is_active'   => $this->is_active,
                ]);

                ProductHistory::create([
                    'product_id' => $existing->id,
                    'action'     => 'stock_change',
                    'old_value'  => (string) $oldStock,
                    'new_value'  => (string) $newStock,
                    'note'       => 'Stock added via product form',
                    'created_at' => now(),
                ]);

                $existing->notifyWishlistLowStockIfNeeded($oldStock, $newStock);
            } else {
                $imagePath = $this->image->store('products', 'public');

                $product = Product::create([
                    'seller_id'   => $seller->id,
                    'name'        => $this->name,
                    'description' => $this->description,
                    'category'    => $this->category,
                    'tags'        => $this->tags !== '' ? $this->tags : null,
                    'price'       => $this->price,
                    'sale_price'  => $this->sale_price !== '' ? $this->sale_price : null,
                    'condition'   => $this->condition,
                    'size_variant' => $this->sizeVariantValue(),
                    'delivery_fee'=> $this->delivery_fee !== '' ? $this->delivery_fee : null,
                    'stock'       => $this->stock,
                    'low_stock_threshold' => (int) ($this->low_stock_threshold ?: 10),
                    'image_path'  => $imagePath,
                    'is_active'   => $this->is_active,
                ]);

                ProductHistory::create([
                    'product_id'  => $product->id,
                    'action'      => 'added',
                    'new_value'   => json_encode($product->only(['name','price','stock'])),
                    'note'        => 'Product created',
                    'created_at'  => now(),
                ]);
                SellerActivityLog::log($seller->id, 'product_added', ['product_id' => $product->id, 'name' => $product->name]);
            }
        } else {
            $product = Product::query()
                ->where('seller_id', $seller->id)
                ->findOrFail($this->editingId);

            $oldSnapshot = $product->only(['name','price','stock','is_active']);

            $data = [
                'name'        => $this->name,
                'description' => $this->description,
                'category'    => $this->category,
                'tags'        => $this->tags !== '' ? $this->tags : null,
                'price'       => $this->price,
                'sale_price'  => $this->sale_price !== '' ? $this->sale_price : null,
                'condition'   => $this->condition,
                'size_variant' => $this->sizeVariantValue(),
                'delivery_fee'=> $this->delivery_fee !== '' ? $this->delivery_fee : null,
                'stock'       => $this->stock,
                'low_stock_threshold' => (int) ($this->low_stock_threshold ?: 10),
                'is_active'   => $this->is_active,
            ];

            if ($this->image) {
                if ($product->image_path) {
                    Storage::disk('public')->delete($product->image_path);
                }
                $data['image_path'] = $this->image->store('products', 'public');
            }

            $oldStock = $product->stock;
            $product->update($data);
            $newStock = $product->stock;

            ProductHistory::create([
                'product_id' => $product->id,
                'action'     => 'updated',
                'old_value'  => json_encode($oldSnapshot),
                'new_value'  => json_encode($product->fresh()->only(['name','price','stock','is_active'])),
                'note'       => 'Product updated',
                'created_at' => now(),
            ]);
            SellerActivityLog::log($seller->id, 'product_updated', [
                'product_id' => $product->id,
                'name' => $product->name,
                'old' => $oldSnapshot,
                'new' => $product->fresh()->only(['name', 'price', 'stock', 'is_active']),
            ]);

            $product->notifyWishlistLowStockIfNeeded($oldStock, $newStock);
        }

        $this->mode = 'list';
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $product = Product::query()
            ->where('seller_id', $this->seller?->id)
            ->findOrFail($id);

        $product->update(['is_active' => ! $product->is_active]);

        ProductHistory::create([
            'product_id' => $product->id,
            'action'     => 'updated',
            'old_value'  => json_encode(['is_active' => ! $product->is_active]),
            'new_value'  => json_encode(['is_active' => $product->is_active]),
            'note'       => $product->is_active ? 'Listing activated' : 'Listing deactivated',
            'created_at' => now(),
        ]);
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->deleteId = null;
        $this->showDeleteConfirm = false;
    }

    public function deleteProduct(): void
    {
        if (! $this->deleteId) return;

        $product = Product::query()
            ->where('seller_id', $this->seller?->id)
            ->findOrFail($this->deleteId);

        $sellerId = $this->seller?->id;
        $productName = $product->name;
        $productId = $product->id;

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();
        if ($sellerId) {
            SellerActivityLog::log($sellerId, 'product_deleted', ['product_id' => $productId, 'name' => $productName]);
        }
        $this->cancelDelete();
        $this->resetPage();
    }

    public function openStockModal(int $id): void
    {
        $this->stockProductId = $id;
        $this->stockDelta = 0;
        $this->stockNote  = '';
        $this->showStockModal = true;
    }

    public function closeStockModal(): void
    {
        $this->stockProductId = null;
        $this->showStockModal = false;
    }

    public function adjustStock(): void
    {
        $this->validate([
            'stockDelta' => ['required', 'integer', 'not_in:0'],
            'stockNote'  => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::query()
            ->where('seller_id', $this->seller?->id)
            ->findOrFail($this->stockProductId);

        $oldStock = $product->stock;
        $newStock = max(0, $oldStock + $this->stockDelta);
        $product->update(['stock' => $newStock]);

        ProductHistory::create([
            'product_id' => $product->id,
            'action'     => 'stock_change',
            'old_value'  => (string) $oldStock,
            'new_value'  => (string) $newStock,
            'note'       => $this->stockNote ?: ($this->stockDelta > 0 ? 'Stock added' : 'Stock removed'),
            'created_at' => now(),
        ]);

        $product->notifyWishlistLowStockIfNeeded($oldStock, $newStock);

        $this->closeStockModal();
    }

    public function openBulkStockModal(): void
    {
        if (count($this->selected) === 0) {
            return;
        }
        $this->bulkStockDelta = 0;
        $this->bulkStockNote = '';
        $this->showBulkStockModal = true;
        $this->resetErrorBag();
    }

    public function closeBulkStockModal(): void
    {
        $this->showBulkStockModal = false;
        $this->bulkStockDelta = 0;
        $this->bulkStockNote = '';
        $this->resetErrorBag();
    }

    public function selectPage(): void
    {
        $this->selected = $this->products->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function applyBulkStock(): void
    {
        if (count($this->selected) === 0) {
            return;
        }

        $this->validate([
            'bulkStockDelta' => ['required', 'integer', 'not_in:0'],
            'bulkStockNote'  => ['nullable', 'string', 'max:255'],
        ]);

        $seller = $this->seller;
        if (! $seller) {
            abort(403);
        }

        $note = $this->bulkStockNote;
        $delta = $this->bulkStockDelta;

        Product::query()
            ->where('seller_id', $seller->id)
            ->whereIn('id', $this->selected)
            ->chunkById(100, function ($products) use ($delta, $note) {
                foreach ($products as $product) {
                    $oldStock = $product->stock;
                    $newStock = max(0, $oldStock + $delta);
                    if ($newStock === $oldStock) {
                        continue;
                    }

                    $product->update(['stock' => $newStock]);

                    ProductHistory::create([
                        'product_id' => $product->id,
                        'action'     => 'stock_change',
                        'old_value'  => (string) $oldStock,
                        'new_value'  => (string) $newStock,
                        'note'       => $note !== '' ? $note : ($delta > 0 ? 'Bulk stock added' : 'Bulk stock removed'),
                        'created_at' => now(),
                    ]);

                    $product->notifyWishlistLowStockIfNeeded($oldStock, $newStock);
                }
            });

        $this->closeBulkStockModal();
        $this->selected = [];
    }

    public function markSoldOut(int $id): void
    {
        $seller = $this->seller;
        if (! $seller) abort(403);

        $product = Product::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        if ($product->stock === 0) {
            return;
        }

        $oldStock = $product->stock;
        $product->update(['stock' => 0]);

        ProductHistory::create([
            'product_id' => $product->id,
            'action'     => 'stock_change',
            'old_value'  => (string) $oldStock,
            'new_value'  => '0',
            'note'       => 'Marked as sold out',
            'created_at' => now(),
        ]);
    }

    public function showHistory(int $id): void
    {
        $seller = $this->seller;
        if (! $seller) abort(403);

        $product = Product::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        $this->historyProductId = $product->id;
        $this->historyPage = 1;
        $this->showHistoryModal = true;
    }

    public function getHistoryRowsProperty()
    {
        if (! $this->historyProductId) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }
        return ProductHistory::query()
            ->where('product_id', $this->historyProductId)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'historyPage', $this->historyPage);
    }

    public function closeHistory(): void
    {
        $this->showHistoryModal = false;
        $this->historyProductId = null;
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->image = null;
        $this->resetValidation();
    }
};
?>

<div>
    {{-- ============================================================
         LIST VIEW
    ============================================================ --}}
    @if($mode === 'list')
    <div class="space-y-4">
        {{-- Toolbar --}}
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
            <div class="flex gap-2 flex-wrap">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search products…"
                       class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-56">
                <select wire:model.live="filterStatus"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select wire:model.live="filterCondition"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All conditions</option>
                    @foreach(\App\Models\Product::conditionOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="openBulkStockModal"
                        @disabled(count($selected) === 0)
                        class="inline-flex items-center gap-1 px-3 py-2 border rounded-md text-xs font-semibold uppercase tracking-widest
                               {{ count($selected ?? []) ? 'border-amber-600 text-amber-700 hover:bg-amber-50' : 'border-gray-300 text-gray-400 cursor-not-allowed' }}">
                    Bulk stock update
                </button>
                <button type="button" wire:click="showCreate"
                        class="inline-flex items-center gap-1 px-4 py-2 bg-indigo-600 border border-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    + Add product
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <button type="button" wire:click="selectPage" class="text-[11px] text-indigo-600 hover:underline">
                                Select page
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Condition</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sale Price</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Views</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($this->products as $product)
                    <tr>
                        <td class="px-4 py-3">
                            <input type="checkbox" wire:model="selected" value="{{ $product->id }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        <td class="px-4 py-3">
                            @if($product->image_path)
                                <img src="{{ asset('storage/' . $product->image_path) }}"
                                     alt="{{ $product->name }}"
                                     class="h-12 w-12 object-cover rounded">
                            @else
                                <div class="h-12 w-12 bg-gray-100 rounded flex items-center justify-center text-gray-400 text-xs">No img</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $product->name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                {{ \App\Models\Product::conditionOptions()[$product->condition] ?? $product->condition }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $product->category ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">₱{{ number_format($product->price, 2) }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $product->sale_price ? '₱' . number_format($product->sale_price, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <span class="{{ $product->stock === 0 ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                                    {{ $product->stock }}
                                </span>
                                <button type="button" wire:click="openStockModal({{ $product->id }})"
                                        class="text-xs text-indigo-600 hover:underline ml-1">adjust</button>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-sm">{{ $product->views ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <button type="button" wire:click="toggleActive({{ $product->id }})"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                           {{ $product->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap space-x-2">
                            <button type="button" wire:click="showEdit({{ $product->id }})"
                                    class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</button>
                            @if($product->stock > 0)
                                <button type="button" wire:click="markSoldOut({{ $product->id }})"
                                        class="text-xs font-medium text-amber-700 hover:text-amber-900">Mark as sold out</button>
                            @endif
                            <button type="button" wire:click="confirmDelete({{ $product->id }})"
                                    class="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>
                            <button type="button" wire:click="showHistory({{ $product->id }})"
                                    class="text-xs text-gray-500 hover:text-gray-700 font-medium">History</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-500">No products found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-3 border-t">
                {{ $this->products->links() }}
            </div>
        </div>
    </div>

    {{-- Delete confirm modal --}}
    @if($showDeleteConfirm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 w-80">
            <h3 class="font-semibold text-gray-900 mb-2">Delete product?</h3>
            <p class="text-sm text-gray-600 mb-4">This cannot be undone. The product image and history will also be removed.</p>
            <div class="flex gap-3 justify-end">
                <button type="button" wire:click="cancelDelete"
                        class="px-3 py-1.5 border rounded text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" wire:click="deleteProduct"
                        class="px-3 py-1.5 bg-red-600 text-white text-sm rounded hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Stock adjust modal --}}
    @if($showStockModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 w-80 space-y-4">
            <h3 class="font-semibold text-gray-900">Adjust stock</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700">Change amount <span class="text-gray-500">(use − for removal)</span></label>
                <input type="number" wire:model.defer="stockDelta"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('stockDelta') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Note <span class="text-gray-400">(optional)</span></label>
                <input type="text" wire:model.defer="stockNote"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" wire:click="closeStockModal"
                        class="px-3 py-1.5 border rounded text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" wire:click="adjustStock"
                        class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Save</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Bulk stock update modal --}}
    @if($showBulkStockModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 w-80 space-y-4">
            <h3 class="font-semibold text-gray-900">Bulk stock update</h3>
            <p class="text-xs text-gray-500">
                Applying to {{ count($selected ?? []) }} selected product(s).
            </p>
            <div>
                <label class="block text-sm font-medium text-gray-700">Change amount <span class="text-gray-500">(use − for removal)</span></label>
                <input type="number" wire:model.defer="bulkStockDelta"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('bulkStockDelta') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Note <span class="text-gray-400">(optional)</span></label>
                <input type="text" wire:model.defer="bulkStockNote"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('bulkStockNote') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" wire:click="closeBulkStockModal"
                        class="px-3 py-1.5 border rounded text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" wire:click="applyBulkStock"
                        class="px-3 py-1.5 bg-amber-600 text-white text-sm rounded hover:bg-amber-700">Apply</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Inventory history modal --}}
    @if($showHistoryModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-2xl max-h-[80vh] flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900 text-sm">Inventory & change history</h3>
                <button type="button" wire:click="closeHistory"
                        class="text-gray-400 hover:text-gray-600 text-sm">&times;</button>
            </div>

            @php($historyRows = $this->historyRows)
            @if($historyRows->isEmpty())
                <p class="text-sm text-gray-500">No history recorded yet for this product.</p>
            @else
                <div class="overflow-y-auto border rounded-md divide-y divide-gray-100 text-sm">
                    @foreach($historyRows as $row)
                        <div class="px-4 py-3 flex items-start gap-3">
                            <div class="w-28 text-xs text-gray-500 mt-0.5">
                                {{ optional($row->created_at)->format('Y-m-d H:i') }}
                            </div>
                            <div class="flex-1 space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                        @switch($row->action)
                                            @case('added') bg-green-100 text-green-800 @break
                                            @case('updated') bg-blue-100 text-blue-800 @break
                                            @case('deleted') bg-red-100 text-red-800 @break
                                            @case('stock_change') bg-amber-100 text-amber-800 @break
                                            @default bg-gray-100 text-gray-700
                                        @endswitch">
                                        {{ ucfirst(str_replace('_', ' ', $row->action)) }}
                                    </span>
                                    @if($row->note)
                                        <span class="text-xs text-gray-700">{{ $row->note }}</span>
                                    @endif
                                </div>
                                @if($row->action === 'stock_change')
                                    <div class="text-xs text-gray-600">
                                        Stock: {{ $row->old_value }} → {{ $row->new_value }}
                                    </div>
                                @elseif($row->old_value || $row->new_value)
                                    <div class="grid grid-cols-2 gap-3 text-[11px] text-gray-600">
                                        @if($row->old_value)
                                            <div>
                                                <div class="font-medium text-gray-700 mb-0.5">Before</div>
                                                <pre class="bg-gray-50 rounded p-1.5 overflow-x-auto whitespace-pre-wrap">{{ $row->old_value }}</pre>
                                            </div>
                                        @endif
                                        @if($row->new_value)
                                            <div>
                                                <div class="font-medium text-gray-700 mb-0.5">After</div>
                                                <pre class="bg-gray-50 rounded p-1.5 overflow-x-auto whitespace-pre-wrap">{{ $row->new_value }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($historyRows->hasPages())
                    <div class="mt-2 pt-2 border-t">
                        {{ $historyRows->links() }}
                    </div>
                @endif
            @endif

            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="closeHistory"
                        class="px-4 py-2 border rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         CREATE / EDIT VIEW
    ============================================================ --}}
    @else
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <button type="button" wire:click="backToList" class="text-indigo-600 text-sm hover:underline">&larr; Back to products</button>
            <div>
                <h3 class="font-semibold text-gray-900">{{ $mode === 'create' ? 'Add new product' : 'Edit product' }}</h3>
                @if($mode === 'edit' && $editingId)
                    @php($editProduct = \App\Models\Product::find($editingId))
                    @if($editProduct)
                        <p class="text-xs text-gray-500 mt-0.5">Views: {{ $editProduct->views ?? 0 }}</p>
                    @endif
                @endif
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 space-y-5 max-w-2xl">
            <div>
                <label class="block text-sm font-medium text-gray-700">Product name <span class="text-red-500">*</span></label>
                <input type="text" wire:model.defer="name"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Description <span class="text-red-500">*</span></label>
                <textarea wire:model.defer="description" rows="4"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                @error('description') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category <span class="text-red-500">*</span></label>
                    <input type="text" wire:model.defer="category"
                           list="seller-categories"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="e.g. Clothing, Bags, Gadgets">
                    <datalist id="seller-categories">
                        @foreach($this->sellerCategories as $cat)
                            <option value="{{ $cat }}"></option>
                        @endforeach
                    </datalist>
                    @error('category') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    @if(count($this->sellerCategories))
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach($this->sellerCategories as $cat)
                                <button type="button"
                                        wire:click="$set('category', '{{ $cat }}')"
                                        class="px-2 py-0.5 text-[11px] rounded-full border border-gray-300 text-gray-600 hover:bg-gray-50">
                                    {{ $cat }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tags <span class="text-gray-400 text-xs">(optional, comma separated)</span></label>
                    <input type="text" wire:model.defer="tags"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="e.g. vintage, denim, bundle">
                    @error('tags') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Condition <span class="text-red-500">*</span></label>
                <select wire:model.defer="condition"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach(\App\Models\Product::conditionOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('condition') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Size / variant <span class="text-gray-400 text-xs">optional</span></label>
                <select wire:model.defer="size_variant"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— None —</option>
                    @foreach(\App\Models\Product::sizeVariantOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                    <option value="custom">Custom</option>
                </select>
                @if($size_variant === 'custom')
                    <input type="text" wire:model.defer="size_custom" maxlength="100"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="e.g. One size, 28 waist">
                @endif
                @error('size_variant') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Price (₱) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" wire:model.defer="price"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('price') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Sale price (₱) <span class="text-gray-400">optional</span></label>
                    <input type="number" step="0.01" min="0" wire:model.defer="sale_price"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('sale_price') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Delivery fee (₱) <span class="text-gray-400 text-xs">optional — used when your store uses "Per product" delivery</span></label>
                <input type="number" step="0.01" min="0" wire:model.defer="delivery_fee"
                       class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="0">
                @error('delivery_fee') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Stock quantity <span class="text-red-500">*</span></label>
                    <input type="number" min="0" wire:model.defer="stock"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('stock') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Low stock threshold</label>
                    <input type="number" min="0" wire:model.defer="low_stock_threshold"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="10">
                    <p class="mt-0.5 text-xs text-gray-500">Wishlist notification when stock falls to this level or below.</p>
                    @error('low_stock_threshold') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="flex items-center gap-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.defer="is_active"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Active (visible to customers)</span>
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    Product image {{ $mode === 'create' ? '*' : '(leave blank to keep current)' }}
                </label>
                @if($mode === 'edit' && $editingId)
                    @php($existingProduct = \App\Models\Product::find($editingId))
                    @if($existingProduct && $existingProduct->image_path)
                        <img src="{{ asset('storage/' . $existingProduct->image_path) }}"
                             class="mt-2 h-24 w-24 object-cover rounded-md border" alt="Current image">
                    @endif
                @endif
                <input type="file" wire:model="image" accept="image/*"
                       class="mt-2 block text-sm text-gray-500">
                <div wire:loading wire:target="image" class="text-xs text-indigo-600 mt-1">Uploading…</div>
                @if($image)
                    <img src="{{ $image->temporaryUrl() }}" class="mt-2 h-24 w-24 object-cover rounded-md border" alt="Preview">
                @endif
                @error('image') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" wire:click="save" wire:loading.attr="disabled"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    {{ $mode === 'create' ? 'Create product' : 'Save changes' }}
                </button>
                <button type="button" wire:click="backToList"
                        class="px-4 py-2 border rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
