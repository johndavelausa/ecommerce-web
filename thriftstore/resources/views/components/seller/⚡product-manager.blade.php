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
    public string $mode = 'list';       // list | create | view | edit
    public ?int $editingId = null;
    public ?int $viewingId = null;

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

    public function showView(int $id): void
    {
        $product = Product::query()
            ->where('seller_id', $this->seller?->id)
            ->findOrFail($id);

        $this->viewingId = $product->id;
        $this->mode = 'view';
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
        $this->viewingId = null;
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
    <div class="space-y-4 rounded-xl border border-[#E0E0E0] bg-[#FFFEF5] p-4 sm:p-5">
        {{-- Toolbar --}}
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
            <div class="flex gap-2 flex-wrap">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search products…"
                       class="w-56 rounded-md border border-[#E0E0E0] bg-white text-sm text-[#212121] placeholder-[#9E9E9E] shadow-sm focus:border-[#2D9F4E] focus:ring-[#2D9F4E]">
                <select wire:model.live="filterStatus"
                        class="rounded-md border border-[#E0E0E0] bg-white text-sm text-[#424242] shadow-sm focus:border-[#2D9F4E] focus:ring-[#2D9F4E]">
                    <option value="">All status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select wire:model.live="filterCondition"
                        class="rounded-md border border-[#E0E0E0] bg-white text-sm text-[#424242] shadow-sm focus:border-[#2D9F4E] focus:ring-[#2D9F4E]">
                    <option value="">All conditions</option>
                    @foreach(\App\Models\Product::conditionOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="openBulkStockModal"
                        @disabled(count($selected) === 0)
                        class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-xs font-semibold uppercase tracking-widest
                               {{ count($selected ?? []) ? 'border-[#F9C74F] bg-white text-[#F9C74F] hover:bg-[#FFF9E6] hover:text-[#E6B340]' : 'cursor-not-allowed border-[#E0E0E0] bg-white text-[#9E9E9E]' }}">
                    Bulk stock update
                </button>
                <button type="button" wire:click="showCreate"
                        class="inline-flex items-center gap-1 rounded-md border-2 border-[#F9C74F] bg-[#2D9F4E] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-[0_2px_4px_rgba(45,159,78,0.2)] hover:bg-[#1B7A37] focus:outline-none focus:ring-2 focus:ring-[#2D9F4E] focus:ring-offset-2">
                    + Add product
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-lg border border-[#E0E0E0] bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-[#FFFEF5]">
                    <tr>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">
                            <button type="button" wire:click="selectPage" class="text-[11px] text-[#F9C74F] hover:text-[#E6B340] hover:underline">
                                Select page
                            </button>
                        </th>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">Image</th>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">Name</th>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">Condition</th>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">Category</th>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">Price</th>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">Sale Price</th>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">Stock</th>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">Views</th>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">Status</th>
                        <th class="border-b border-[#FFE17B] px-4 py-3 text-left text-[13px] font-medium uppercase text-[#424242]">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @forelse($this->products as $product)
                    <tr class="border-b border-[#F5F5F5] transition hover:bg-gradient-to-r hover:from-[#F8FDF9] hover:to-[#FFFEF5]">
                        <td class="px-4 py-3">
                            <input type="checkbox" wire:model="selected" value="{{ $product->id }}"
                                   class="rounded border-[#E0E0E0] text-[#2D9F4E] focus:ring-[#2D9F4E]">
                        </td>
                        <td class="px-4 py-3">
                            @if($product->image_path)
                                <img src="{{ asset('storage/' . $product->image_path) }}"
                                     alt="{{ $product->name }}"
                                     class="h-12 w-12 object-cover rounded">
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded bg-[#FFF9E6] text-xs text-[#9E9E9E]">No img</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-[#212121]">{{ $product->name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded border border-[#F9C74F] bg-[#FFF9E6] px-2 py-0.5 text-xs font-medium text-[#F57C00]">
                                {{ \App\Models\Product::conditionOptions()[$product->condition] ?? $product->condition }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-[#9E9E9E]">{{ $product->category ?? '—' }}</td>
                        <td class="px-4 py-3 font-medium text-[#2D9F4E]">₱{{ number_format($product->price, 2) }}</td>
                        <td class="px-4 py-3 text-[#2D9F4E]">
                            {{ $product->sale_price ? '₱' . number_format($product->sale_price, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="{{ $product->stock === 0 ? 'font-semibold text-[#EF5350]' : 'text-[#212121]' }}">
                                {{ $product->stock }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-[#424242]">{{ $product->views ?? 0 }}</td>
                        <td class="px-4 py-3">
                            @if($product->stock === 0)
                                <span class="inline-flex items-center rounded-full border border-[#EF5350] bg-[#FFEBEE] px-2 py-0.5 text-xs font-medium text-[#EF5350]">Sold out</span>
                            @elseif($product->is_active)
                                <span class="inline-flex items-center gap-1 rounded-full border border-[#2D9F4E] bg-[#E8F5E9] px-2 py-0.5 text-xs font-medium text-[#2D9F4E]">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#F9C74F]"></span> Active
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full border border-[#F9C74F] bg-[#FFF9E6] px-2 py-0.5 text-xs font-medium text-[#F57C00]">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button type="button" wire:click="showView({{ $product->id }})"
                                    class="text-xs font-semibold text-[#2D9F4E] hover:text-[#1B7A37] hover:underline">View</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-[#9E9E9E]">No products found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t border-[#E0E0E0] px-4 py-3">
                {{ $this->products->links() }}
            </div>
        </div>
    </div>

    {{-- Delete confirm modal --}}
    @if($showDeleteConfirm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="w-80 rounded-xl border border-[#E0E0E0] bg-white p-6 shadow-xl">
            <h3 class="mb-2 font-semibold text-[#212121]">Delete product?</h3>
            <p class="mb-4 text-sm text-[#424242]">This cannot be undone. The product image and history will also be removed.</p>
            <div class="flex gap-3 justify-end">
                <button type="button" wire:click="cancelDelete"
                        class="rounded border border-[#F9C74F] bg-white px-3 py-1.5 text-sm text-[#F57C00] hover:border-[#E6B340] hover:bg-[#FFF9E6]">Cancel</button>
                <button type="button" wire:click="deleteProduct"
                        class="rounded px-3 py-1.5 text-sm text-white bg-[#EF5350] hover:bg-[#C62828]">Delete</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Stock adjust modal --}}
    @if($showStockModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="w-80 space-y-4 rounded-xl border border-[#E0E0E0] bg-white p-6 shadow-xl">
            <h3 class="font-semibold text-[#212121]">Adjust stock</h3>
            <div>
                <label class="block text-sm font-medium text-[#424242]">Change amount <span class="text-[#9E9E9E]">(use − for removal)</span></label>
                <input type="number" wire:model.defer="stockDelta"
                       class="mt-1 block w-full rounded-md border border-[#E0E0E0] px-3 py-2 text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]">
                @error('stockDelta') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[#424242]">Note <span class="text-[#9E9E9E]">(optional)</span></label>
                <input type="text" wire:model.defer="stockNote"
                       class="mt-1 block w-full rounded-md border border-[#E0E0E0] px-3 py-2 text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]">
            </div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" wire:click="closeStockModal"
                        class="rounded border border-[#F9C74F] bg-white px-3 py-1.5 text-sm text-[#F57C00] hover:border-[#E6B340] hover:bg-[#FFF9E6]">Cancel</button>
                <button type="button" wire:click="adjustStock"
                        class="rounded bg-[linear-gradient(135deg,#2D9F4E,#F9C74F)] px-3 py-1.5 text-sm text-white shadow-[0_2px_8px_rgba(45,159,78,0.3)] hover:brightness-95">Save</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Bulk stock update modal --}}
    @if($showBulkStockModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="w-80 space-y-4 rounded-xl border border-[#E0E0E0] bg-white p-6 shadow-xl">
            <h3 class="font-semibold text-[#212121]">Bulk stock update</h3>
            <p class="text-xs text-[#9E9E9E]">
                Applying to {{ count($selected ?? []) }} selected product(s).
            </p>
            <div>
                <label class="block text-sm font-medium text-[#424242]">Change amount <span class="text-[#9E9E9E]">(use − for removal)</span></label>
                <input type="number" wire:model.defer="bulkStockDelta"
                       class="mt-1 block w-full rounded-md border border-[#E0E0E0] px-3 py-2 text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]">
                @error('bulkStockDelta') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[#424242]">Note <span class="text-[#9E9E9E]">(optional)</span></label>
                <input type="text" wire:model.defer="bulkStockNote"
                       class="mt-1 block w-full rounded-md border border-[#E0E0E0] px-3 py-2 text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]">
                @error('bulkStockNote') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" wire:click="closeBulkStockModal"
                        class="rounded border border-[#F9C74F] bg-white px-3 py-1.5 text-sm text-[#F57C00] hover:border-[#E6B340] hover:bg-[#FFF9E6]">Cancel</button>
                <button type="button" wire:click="applyBulkStock"
                        class="rounded bg-[linear-gradient(135deg,#2D9F4E,#F9C74F)] px-3 py-1.5 text-sm text-white shadow-[0_2px_8px_rgba(45,159,78,0.3)] hover:brightness-95">Apply</button>
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
    @elseif($mode === 'view')
    @php($viewProduct = $viewingId ? \App\Models\Product::query()->where('seller_id', $this->seller?->id)->find($viewingId) : null)
    <div class="space-y-4 rounded-xl border border-[#E0E0E0] bg-[#FFFEF5] p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <button type="button" wire:click="backToList" class="inline-flex items-center gap-1 text-sm text-[#2D9F4E] hover:text-[#1B7A37]">
                <span class="text-[#F9C74F]">&larr;</span> Back to products
            </button>
            @if($viewProduct)
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="showEdit({{ $viewProduct->id }})" class="rounded-md px-3 py-1.5 text-xs font-semibold text-[#2D9F4E] hover:bg-[#E8F5E9] hover:text-[#1B7A37]">Edit</button>
                    <button type="button" wire:click="openStockModal({{ $viewProduct->id }})" class="rounded-md px-3 py-1.5 text-xs font-semibold text-[#F9C74F] hover:bg-[#FFF9E6] hover:text-[#E6B340]">Adjust stock</button>
                    <button type="button" wire:click="toggleActive({{ $viewProduct->id }})" class="rounded-md px-3 py-1.5 text-xs font-semibold text-[#F57C00] hover:bg-[#FFF3E0] hover:text-[#E65100]">{{ $viewProduct->is_active ? 'Set inactive' : 'Set active' }}</button>
                    @if($viewProduct->stock > 0)
                        <button type="button" wire:click="markSoldOut({{ $viewProduct->id }})" class="rounded-md px-3 py-1.5 text-xs font-semibold text-[#F57C00] hover:bg-[#FFF3E0] hover:text-[#E65100]">Mark as sold out</button>
                    @endif
                    <button type="button" wire:click="showHistory({{ $viewProduct->id }})" class="rounded-md px-3 py-1.5 text-xs font-semibold text-[#F9C74F] hover:bg-[#FFF9E6] hover:text-[#E6B340]">History</button>
                    <button type="button" wire:click="confirmDelete({{ $viewProduct->id }})" class="rounded-md px-3 py-1.5 text-xs font-semibold text-[#EF5350] hover:text-[#C62828]">Delete</button>
                </div>
            @endif
        </div>

        @if($viewProduct)
            <div class="rounded-lg border border-[#E0E0E0] bg-white p-5">
                <div>
                    <h3 class="text-lg font-semibold text-[#212121]">{{ $viewProduct->name }}</h3>
                    <p class="mt-1 text-xs text-[#9E9E9E]">Views: {{ $viewProduct->views ?? 0 }}</p>
                    <div class="mt-2 h-[3px] w-40 rounded bg-gradient-to-r from-[#2D9F4E] to-[#F9C74F]"></div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 text-sm text-[#424242] sm:grid-cols-2">
                    <p><span class="font-medium">Category:</span> {{ $viewProduct->category ?? '—' }}</p>
                    <p><span class="font-medium">Condition:</span> {{ \App\Models\Product::conditionOptions()[$viewProduct->condition] ?? $viewProduct->condition }}</p>
                    <p><span class="font-medium text-[#2D9F4E]">Price:</span> ₱{{ number_format($viewProduct->price, 2) }}</p>
                    <p><span class="font-medium text-[#2D9F4E]">Sale Price:</span> {{ $viewProduct->sale_price ? '₱' . number_format($viewProduct->sale_price, 2) : '—' }}</p>
                    <p><span class="font-medium">Stock:</span> {{ $viewProduct->stock }}</p>
                    <p><span class="font-medium">Status:</span> {{ $viewProduct->stock === 0 ? 'Sold out' : ($viewProduct->is_active ? 'Active' : 'Inactive') }}</p>
                </div>
                <div class="mt-4">
                    <p class="mb-1 text-xs font-medium uppercase tracking-widest text-[#9E9E9E]">Description</p>
                    <p class="text-sm text-[#424242]">{{ $viewProduct->description }}</p>
                </div>
            </div>
        @else
            <div class="rounded-lg border border-[#E0E0E0] bg-white p-5 text-sm text-[#9E9E9E]">
                Product not found.
            </div>
        @endif
    </div>
    @else
    <div class="space-y-4 rounded-xl border border-[#E0E0E0] bg-[#FFFEF5] p-4 sm:p-5">
        <div class="flex items-center gap-3">
            <button type="button" wire:click="backToList" class="inline-flex items-center gap-1 text-sm text-[#2D9F4E] hover:text-[#1B7A37]"><span class="text-[#F9C74F]">&larr;</span> Back to products</button>
            <div>
                <h3 class="font-semibold text-[#212121]">{{ $mode === 'create' ? 'Add new product' : 'Edit product' }}</h3>
                @if($mode === 'edit' && $editingId)
                    @php($editProduct = \App\Models\Product::find($editingId))
                    @if($editProduct)
                        <p class="mt-0.5 text-xs text-[#9E9E9E]">Views: {{ $editProduct->views ?? 0 }}</p>
                    @endif
                @endif
            </div>
        </div>

        <div class="max-w-2xl space-y-5 rounded-lg border border-[#E0E0E0] bg-white p-6 shadow-sm">
            <div class="h-[3px] w-44 rounded bg-gradient-to-r from-[#2D9F4E] to-[#F9C74F]"></div>
            <div>
                <label class="block text-sm font-medium text-[#424242]">Product name <span class="text-[#F57C00]">*</span></label>
                <input type="text" wire:model.defer="name"
                       class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white px-3 py-2 text-sm text-[#212121] placeholder-[#9E9E9E] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]">
                @error('name') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-[#424242]">Description <span class="text-[#F57C00]">*</span></label>
                <textarea wire:model.defer="description" rows="4"
                          class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white px-3 py-2 text-sm text-[#212121] placeholder-[#9E9E9E] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]"></textarea>
                @error('description') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#424242]">Category <span class="text-[#F57C00]">*</span></label>
                    <input type="text" wire:model.defer="category"
                           list="seller-categories"
                           class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white px-3 py-2 text-sm text-[#212121] placeholder-[#9E9E9E] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]"
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
                                        class="rounded-full border border-[#F9C74F] bg-[#FFF9E6] px-2 py-0.5 text-[11px] text-[#F57C00] hover:border-[#2D9F4E]">
                                    {{ $cat }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#424242]">Tags <span class="text-xs text-[#9E9E9E]">(optional, comma separated)</span></label>
                    <input type="text" wire:model.defer="tags"
                           class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white px-3 py-2 text-sm text-[#212121] placeholder-[#9E9E9E] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]"
                           placeholder="e.g. vintage, denim, bundle">
                    @error('tags') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-[#424242]">Condition <span class="text-[#F57C00]">*</span></label>
                <select wire:model.defer="condition"
                        class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]">
                    @foreach(\App\Models\Product::conditionOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('condition') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-[#424242]">Size / variant <span class="text-xs text-[#9E9E9E]">optional</span></label>
                <select wire:model.defer="size_variant"
                        class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]">
                    <option value="">— None —</option>
                    @foreach(\App\Models\Product::sizeVariantOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                    <option value="custom">Custom</option>
                </select>
                @if($size_variant === 'custom')
                    <input type="text" wire:model.defer="size_custom" maxlength="100"
                           class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white px-3 py-2 text-sm text-[#212121] placeholder-[#9E9E9E] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]"
                           placeholder="e.g. One size, 28 waist">
                @endif
                @error('size_variant') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#424242]">Price (₱) <span class="text-[#F57C00]">*</span></label>
                    <input type="number" step="0.01" min="0" wire:model.defer="price"
                           class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white px-3 py-2 text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]">
                    @error('price') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#424242]">Sale price (₱) <span class="text-[#9E9E9E]">optional</span></label>
                    <input type="number" step="0.01" min="0" wire:model.defer="sale_price"
                           class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white px-3 py-2 text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]">
                    @error('sale_price') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-[#424242]">Delivery fee (₱) <span class="text-xs text-[#9E9E9E]">optional - used when your store uses "Per product" delivery</span></label>
                <input type="number" step="0.01" min="0" wire:model.defer="delivery_fee"
                       class="mt-1 block w-full max-w-xs rounded-md border border-[#E0E0E0] bg-white px-3 py-2 text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]"
                       placeholder="0">
                @error('delivery_fee') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#424242]">Stock quantity <span class="text-[#F57C00]">*</span></label>
                    <input type="number" min="0" wire:model.defer="stock"
                           class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white px-3 py-2 text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]">
                    @error('stock') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#424242]">Low stock threshold</label>
                    <input type="number" min="0" wire:model.defer="low_stock_threshold"
                           class="mt-1 block w-full rounded-md border border-[#E0E0E0] bg-white px-3 py-2 text-sm text-[#212121] focus:border-2 focus:border-[#2D9F4E] focus:ring-[0_0_0_3px_rgba(249,199,79,0.15)]"
                           placeholder="10">
                    <p class="mt-0.5 text-xs text-[#9E9E9E]">Wishlist notification when stock falls to this level or below.</p>
                    @error('low_stock_threshold') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="flex items-center gap-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.defer="is_active"
                           class="rounded border-[#E0E0E0] text-[#2D9F4E] shadow-sm focus:ring-[#2D9F4E]">
                    <span class="text-sm text-[#424242]">Active (visible to customers)</span>
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-[#424242]">
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
                       class="mt-2 block rounded-md border border-[#F9C74F] bg-[#FFF9E6] px-3 py-2 text-sm text-[#F57C00] file:mr-3 file:rounded file:border-0 file:bg-white file:px-3 file:py-1 file:text-[#2D9F4E] hover:border-[#2D9F4E]">
                <div wire:loading wire:target="image" class="mt-1 text-xs text-[#2D9F4E]">Uploading...</div>
                @if($image)
                    <img src="{{ $image->temporaryUrl() }}" class="mt-2 h-24 w-24 object-cover rounded-md border" alt="Preview">
                @endif
                @error('image') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" wire:click="save" wire:loading.attr="disabled"
                        class="inline-flex items-center rounded-md border-0 bg-[linear-gradient(135deg,#2D9F4E,#F9C74F)] px-6 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-[0_2px_8px_rgba(45,159,78,0.3)] hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[#2D9F4E] focus:ring-offset-2">
                    {{ $mode === 'create' ? 'Create product' : 'Save changes' }}
                </button>
                <button type="button" wire:click="backToList"
                        class="rounded-md border border-[#F9C74F] bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-[#F57C00] hover:border-[#E6B340] hover:bg-[#FFF9E6]">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
