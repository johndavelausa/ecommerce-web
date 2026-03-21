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

    public function formatHistoryValue(ProductHistory $row): array
    {
        $labels = [
            'is_active'  => 'Status',
            'stock'      => 'Stock',
            'price'      => 'Price',
            'sale_price' => 'Sale price',
            'name'       => 'Name',
        ];

        $render = function (?string $json) use ($labels): string {
            $decoded = json_decode((string) $json, true);
            $items   = is_array($decoded) ? $decoded : ($json ? ['value' => $json] : []);
            $parts   = [];
            foreach ($items as $key => $value) {
                $label = $labels[$key] ?? ucwords(str_replace('_', ' ', (string) $key));
                if ($key === 'is_active') {
                    $val = $value ? 'Active' : 'Inactive';
                } elseif (in_array($key, ['price', 'sale_price'], true) && is_numeric($value)) {
                    $val = '₱' . number_format((float) $value, 2);
                } elseif ($value === null || $value === '') {
                    $val = '—';
                } else {
                    $val = (string) $value;
                }
                $parts[] = "{$label}: {$val}";
            }
            return implode(' · ', $parts);
        };

        return [
            'old' => $render($row->old_value),
            'new' => $render($row->new_value),
        ];
    }
};
?>

@push('styles')
@verbatim
<style>
    /* Brand-styled Product Manager CSS */
    .prod-container {
        background: linear-gradient(135deg, #FFFEF5 0%, #F8FDF9 100%);
        border: 1px solid #E0E0E0;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    }
    .prod-header {
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 16px rgba(45,159,78,0.25);
    }
    .prod-header h2 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }
    .prod-header p {
        font-size: 0.875rem;
        color: rgba(255,255,255,0.85);
        margin: 4px 0 0;
    }
    .prod-search {
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 0.875rem;
        background: #fff;
        color: #212121;
        transition: all 0.2s;
    }
    .prod-search:focus {
        outline: none;
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(45,159,78,0.15);
    }
    .prod-select {
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 0.875rem;
        background: #fff;
        color: #424242;
        cursor: pointer;
        transition: all 0.2s;
    }
    .prod-select:focus {
        outline: none;
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(45,159,78,0.15);
    }
    .prod-btn-primary {
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 10px 18px;
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        cursor: pointer;
        transition: all 0.15s;
        box-shadow: 0 2px 8px rgba(45,159,78,0.25);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .prod-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(45,159,78,0.35);
    }
    .prod-btn-secondary {
        background: linear-gradient(135deg, #F9C74F 0%, #F5A623 100%);
        color: #212121;
        border: none;
        border-radius: 10px;
        padding: 10px 18px;
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        cursor: pointer;
        transition: all 0.15s;
        box-shadow: 0 2px 8px rgba(249,199,79,0.25);
    }
    .prod-btn-secondary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(249,199,79,0.35);
    }
    .prod-btn-ghost {
        background: #fff;
        color: #F57C00;
        border: 1px solid #F9C74F;
        border-radius: 10px;
        padding: 10px 18px;
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        cursor: pointer;
        transition: all 0.15s;
    }
    .prod-btn-ghost:hover {
        background: #FFF9E6;
        border-color: #F5A623;
    }
    .prod-btn-ghost:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .prod-table-wrap {
        background: #fff;
        border: 1px solid #E0E0E0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }
    .prod-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }
    .prod-table thead {
        background: #FFFEF5;
    }
    .prod-table th {
        padding: 14px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #424242;
        border-bottom: 2px solid #FFE17B;
    }
    .prod-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #F5F5F5;
        color: #424242;
    }
    .prod-table tr:last-child td {
        border-bottom: none;
    }
    .prod-table tr:hover {
        background: linear-gradient(90deg, #F8FDF9 0%, #FFFEF5 100%);
    }
    .prod-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.6875rem;
        font-weight: 600;
    }
    .prod-badge.active {
        background: #E8F5E9;
        color: #2D9F4E;
        border: 1px solid #2D9F4E;
    }
    .prod-badge.inactive {
        background: #FFF9E6;
        color: #F57C00;
        border: 1px solid #F9C74F;
    }
    .prod-badge.soldout {
        background: #FFEBEE;
        color: #E53935;
        border: 1px solid #EF5350;
    }
    .prod-badge.condition {
        background: #FFF9E6;
        color: #F57C00;
        border: 1px solid #F9C74F;
    }
    .prod-btn-view {
        color: #2D9F4E;
        font-size: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: color 0.15s;
    }
    .prod-btn-view:hover {
        color: #1B7A37;
        text-decoration: underline;
    }
    .prod-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }
    .prod-modal {
        background: #fff;
        border-radius: 16px;
        width: 90%;
        max-width: 520px;
        max-height: 85vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    }
    .prod-modal-header {
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        padding: 20px 24px;
        position: relative;
    }
    .prod-modal-header h3 {
        font-size: 1.125rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }
    .prod-modal-header p {
        font-size: 0.8125rem;
        color: rgba(255,255,255,0.85);
        margin: 4px 0 0;
    }
    .prod-modal-close {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: rgba(255,255,255,0.2);
        border: none;
        color: #fff;
        font-size: 1.25rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
    }
    .prod-modal-close:hover {
        background: rgba(255,255,255,0.3);
    }
    .prod-modal-body {
        padding: 24px;
        overflow-y: auto;
        max-height: calc(85vh - 80px);
    }
    .prod-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 24px;
        border-top: 1px solid #F0F0F0;
        background: #FAFAFA;
    }
    .prod-form-group {
        margin-bottom: 20px;
    }
    .prod-label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #424242;
        margin-bottom: 6px;
    }
    .prod-input {
        width: 100%;
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 0.875rem;
        color: #212121;
        background: #fff;
        transition: all 0.2s;
    }
    .prod-input:focus {
        outline: none;
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(45,159,78,0.15);
    }
    .prod-textarea {
        min-height: 100px;
        resize: vertical;
    }
    .prod-section-box {
        background: #fff;
        border: 1px solid #E0E0E0;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 16px;
    }
    .prod-section-title {
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #9E9E9E;
        margin-bottom: 8px;
    }
    .prod-price {
        font-weight: 700;
        color: #2D9F4E;
    }
    .prod-stock-low {
        color: #E53935;
        font-weight: 600;
    }
    .prod-gradient-line {
        height: 3px;
        width: 120px;
        border-radius: 2px;
        background: linear-gradient(90deg, #2D9F4E, #F9C74F);
    }
    .prod-btn-cancel {
        background: #fff;
        color: #F57C00;
        border: 1px solid #F9C74F;
        border-radius: 10px;
        padding: 10px 18px;
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        cursor: pointer;
        transition: all 0.15s;
    }
    .prod-btn-cancel:hover {
        background: #FFF9E6;
        border-color: #F5A623;
    }
    .prod-btn-danger {
        background: #EF5350;
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 10px 18px;
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }
    .prod-btn-danger:hover {
        background: #C62828;
    }
    .prod-empty-state {
        text-align: center;
        padding: 48px 24px;
        color: #9E9E9E;
    }
    .prod-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.6875rem;
        background: #FFF9E6;
        color: #F57C00;
        border: 1px solid #F9C74F;
        cursor: pointer;
        transition: all 0.15s;
    }
    .prod-tag:hover {
        background: #E8F5E9;
        border-color: #2D9F4E;
        color: #2D9F4E;
    }
</style>
@endverbatim
@endpush

<div>
    {{-- ============================================================
         LIST VIEW
    ============================================================ --}}
    @if($mode === 'list')
    <div class="prod-container">
        {{-- Header --}}
        <div class="prod-header">
            <h2>Products</h2>
            <p>Manage your product listings and inventory</p>
        </div>

        {{-- Toolbar --}}
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between mb-5">
            <div class="flex gap-2 flex-wrap">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search products…"
                       class="prod-search w-56">
                <select wire:model.live="filterStatus" class="prod-select">
                    <option value="">All status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select wire:model.live="filterCondition" class="prod-select">
                    <option value="">All conditions</option>
                    @foreach(\App\Models\Product::conditionOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="openBulkStockModal"
                        @disabled(count($selected) === 0)
                        class="prod-btn-ghost" style="{{ count($selected ?? []) ? '' : 'opacity:0.5;cursor:not-allowed;' }}">
                    Bulk stock update
                </button>
                <button type="button" wire:click="showCreate" class="prod-btn-primary">
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add product
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="prod-table-wrap">
            <table class="prod-table">
                <thead>
                    <tr>
                        <th>
                            <button type="button" wire:click="selectPage" class="text-[11px] text-[#F9C74F] hover:text-[#E6B340] hover:underline">
                                Select page
                            </button>
                        </th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Condition</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Sale Price</th>
                        <th>Stock</th>
                        <th>Views</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->products as $product)
                    <tr>
                        <td>
                            <input type="checkbox" wire:model="selected" value="{{ $product->id }}"
                                   class="rounded border-[#E0E0E0] text-[#2D9F4E] focus:ring-[#2D9F4E]">
                        </td>
                        <td>
                            @if($product->image_path)
                                <img src="{{ asset('storage/' . $product->image_path) }}"
                                     alt="{{ $product->name }}"
                                     class="h-12 w-12 object-cover rounded-lg">
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-[#FFF9E6] text-xs text-[#9E9E9E]">No img</div>
                            @endif
                        </td>
                        <td class="font-medium text-[#212121]">{{ $product->name }}</td>
                        <td>
                            <span class="prod-badge condition">
                                {{ \App\Models\Product::conditionOptions()[$product->condition] ?? $product->condition }}
                            </span>
                        </td>
                        <td class="text-xs text-[#9E9E9E]">{{ $product->category ?? '—' }}</td>
                        <td class="prod-price">₱{{ number_format($product->price, 2) }}</td>
                        <td class="text-[#2D9F4E]">
                            {{ $product->sale_price ? '₱' . number_format($product->sale_price, 2) : '—' }}
                        </td>
                        <td>
                            <span class="{{ $product->stock === 0 ? 'prod-stock-low' : '' }}">
                                {{ $product->stock }}
                            </span>
                        </td>
                        <td class="text-sm text-[#424242]">{{ $product->views ?? 0 }}</td>
                        <td>
                            @if($product->stock === 0)
                                <span class="prod-badge soldout">Sold out</span>
                            @elseif($product->is_active)
                                <span class="prod-badge active">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#F9C74F]"></span> Active
                                </span>
                            @else
                                <span class="prod-badge inactive">Inactive</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            <button type="button" wire:click="showView({{ $product->id }})" class="prod-btn-view">View</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="prod-empty-state">No products found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t border-[#E0E0E0] px-4 py-3">
                {{ $this->products->links() }}
            </div>
        </div>
    </div>
    @endif

    {{-- Delete confirm modal --}}
    @if($showDeleteConfirm)
    <div class="prod-modal-overlay">
        <div class="prod-modal" style="max-width: 360px;">
            <div class="prod-modal-header">
                <h3>Delete product?</h3>
                <p>This action cannot be undone</p>
                <button type="button" wire:click="cancelDelete" class="prod-modal-close">&times;</button>
            </div>
            <div class="prod-modal-body text-center">
                <div class="w-16 h-16 rounded-full bg-[#FFEBEE] flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-[#E53935]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <p class="text-[#424242] text-sm">The product image and history will also be removed permanently.</p>
            </div>
            <div class="prod-modal-footer" style="justify-content: center;">
                <button type="button" wire:click="cancelDelete" class="prod-btn-cancel">Cancel</button>
                <button type="button" wire:click="deleteProduct" class="prod-btn-danger">Delete</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Stock adjust modal --}}
    @if($showStockModal)
    <div class="prod-modal-overlay">
        <div class="prod-modal">
            <div class="prod-modal-header">
                <h3>Adjust Stock</h3>
                <p>Update inventory quantity</p>
                <button type="button" wire:click="closeStockModal" class="prod-modal-close">&times;</button>
            </div>
            <div class="prod-modal-body">
                <div class="prod-form-group">
                    <label class="prod-label">Change amount <span class="text-[#9E9E9E]">(use − for removal)</span></label>
                    <input type="number" wire:model.defer="stockDelta" class="prod-input">
                    @error('stockDelta') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                </div>
                <div class="prod-form-group">
                    <label class="prod-label">Note <span class="text-[#9E9E9E]">(optional)</span></label>
                    <input type="text" wire:model.defer="stockNote" class="prod-input" placeholder="e.g., New shipment arrived">
                </div>
            </div>
            <div class="prod-modal-footer">
                <button type="button" wire:click="closeStockModal" class="prod-btn-cancel">Cancel</button>
                <button type="button" wire:click="adjustStock" class="prod-btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Bulk stock update modal --}}
    @if($showBulkStockModal)
    <div class="prod-modal-overlay">
        <div class="prod-modal">
            <div class="prod-modal-header">
                <h3>Bulk Stock Update</h3>
                <p>Apply to {{ count($selected ?? []) }} selected product(s)</p>
                <button type="button" wire:click="closeBulkStockModal" class="prod-modal-close">&times;</button>
            </div>
            <div class="prod-modal-body">
                <div class="prod-form-group">
                    <label class="prod-label">Change amount <span class="text-[#9E9E9E]">(use − for removal)</span></label>
                    <input type="number" wire:model.defer="bulkStockDelta" class="prod-input">
                    @error('bulkStockDelta') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                </div>
                <div class="prod-form-group">
                    <label class="prod-label">Note <span class="text-[#9E9E9E]">(optional)</span></label>
                    <input type="text" wire:model.defer="bulkStockNote" class="prod-input" placeholder="e.g., Seasonal restock">
                    @error('bulkStockNote') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="prod-modal-footer">
                <button type="button" wire:click="closeBulkStockModal" class="prod-btn-cancel">Cancel</button>
                <button type="button" wire:click="applyBulkStock" class="prod-btn-primary">Apply Changes</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Inventory history modal --}}
    @if($showHistoryModal)
    <div class="prod-modal-overlay">
        <div class="prod-modal" style="max-width: 640px;">
            <div class="prod-modal-header">
                <h3>Inventory History</h3>
                <p>Product changes and stock movements</p>
                <button type="button" wire:click="closeHistory" class="prod-modal-close">&times;</button>
            </div>
            <div class="prod-modal-body">
                @php($historyRows = $this->historyRows)
                @if($historyRows->isEmpty())
                    <div class="text-center py-8">
                        <div class="w-16 h-16 rounded-full bg-[#F5F5F5] flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-[#9E9E9E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-[#9E9E9E] text-sm">No history recorded yet for this product.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($historyRows as $row)
                            <div class="prod-section-box" style="margin-bottom: 0;">
                                <div class="flex items-start gap-3">
                                    <div class="w-20 text-xs text-[#9E9E9E] mt-0.5">
                                        {{ optional($row->created_at)->format('M j, Y') }}
                                        <div class="text-[10px]">{{ optional($row->created_at)->format('g:i A') }}</div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="prod-badge
                                                @switch($row->action)
                                                    @case('added') active @break
                                                    @case('updated') condition @break
                                                    @case('deleted') soldout @break
                                                    @case('stock_change') inactive @break
                                                    @default condition
                                                @endswitch">
                                                {{ ucfirst(str_replace('_', ' ', $row->action)) }}
                                            </span>
                                            @if($row->note)
                                                <span class="text-xs text-[#616161]">{{ $row->note }}</span>
                                            @endif
                                        </div>
                                        @if($row->action === 'stock_change')
                                            <div class="text-sm text-[#424242]">
                                                Stock: <span class="font-semibold">{{ $row->old_value }}</span> → <span class="font-semibold text-[#2D9F4E]">{{ $row->new_value }}</span>
                                            </div>
                                        @elseif($row->old_value || $row->new_value)
                                            @php($hf = $this->formatHistoryValue($row))
                                            <div class="grid grid-cols-1 gap-3 text-xs mt-2 sm:grid-cols-2">
                                                <div class="bg-[#F5F5F5] rounded-lg p-2">
                                                    <div class="text-[#9E9E9E] mb-1">Before</div>
                                                    <div class="text-[#616161]">{{ $hf['old'] ?: '—' }}</div>
                                                </div>
                                                <div class="bg-[#E8F5E9] rounded-lg p-2">
                                                    <div class="text-[#2D9F4E] mb-1">After</div>
                                                    <div class="text-[#424242]">{{ $hf['new'] ?: '—' }}</div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($historyRows->hasPages())
                        <div class="mt-4 pt-3 border-t border-[#E0E0E0]">
                            {{ $historyRows->links() }}
                        </div>
                    @endif
                @endif
            </div>
            <div class="prod-modal-footer">
                <button type="button" wire:click="closeHistory" class="prod-btn-cancel">Close</button>
            </div>
        </div>
    </div>
    @endif
    {{-- ============================================================
         PRODUCT VIEW
    ============================================================ --}}
    @if($mode === 'view')
    @php($viewProduct = $viewingId ? \App\Models\Product::query()->where('seller_id', $this->seller?->id)->find($viewingId) : null)
    <div class="prod-container">
        <div class="mb-5 flex items-center justify-between gap-3">
            <button type="button" wire:click="backToList" class="prod-btn-ghost inline-flex items-center self-center" style="padding: 8px 12px; font-size: 0.75rem; line-height: 1;">
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to products
            </button>
            @if($viewProduct)
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="showEdit({{ $viewProduct->id }})" class="prod-btn-secondary" style="padding: 8px 14px; font-size: 0.75rem;">Edit</button>
                    <button type="button" wire:click="openStockModal({{ $viewProduct->id }})" class="prod-btn-ghost" style="padding: 8px 14px; font-size: 0.75rem;">Adjust stock</button>
                    <button type="button" wire:click="toggleActive({{ $viewProduct->id }})" class="prod-btn-ghost" style="padding: 8px 14px; font-size: 0.75rem;">{{ $viewProduct->is_active ? 'Set inactive' : 'Set active' }}</button>
                    @if($viewProduct->stock > 0)
                        <button type="button" wire:click="markSoldOut({{ $viewProduct->id }})" class="prod-btn-ghost" style="padding: 8px 14px; font-size: 0.75rem;">Mark sold out</button>
                    @endif
                    <button type="button" wire:click="showHistory({{ $viewProduct->id }})" class="prod-btn-ghost" style="padding: 8px 14px; font-size: 0.75rem;">History</button>
                    <button type="button" wire:click="confirmDelete({{ $viewProduct->id }})" class="prod-btn-danger" style="padding: 8px 14px; font-size: 0.75rem;">Delete</button>
                </div>
            @endif
        </div>

        @if($viewProduct)
            <div class="prod-section-box" style="padding: 24px;">
                <div class="flex items-start gap-4 mb-4">
                    @if($viewProduct->image_path)
                        <img src="{{ asset('storage/' . $viewProduct->image_path) }}" alt="{{ $viewProduct->name }}" class="w-24 h-24 object-cover rounded-xl border border-[#E0E0E0]">
                    @else
                        <div class="w-24 h-24 rounded-xl bg-[#FFF9E6] flex items-center justify-center text-[#9E9E9E] text-xs">No image</div>
                    @endif
                    <div class="flex-1">
                        <h3 class="text-xl font-bold text-[#212121]">{{ $viewProduct->name }}</h3>
                        <div class="flex items-center gap-2 mt-2">
                            @if($viewProduct->stock === 0)
                                <span class="prod-badge soldout">Sold out</span>
                            @elseif($viewProduct->is_active)
                                <span class="prod-badge active">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#F9C74F]"></span> Active
                                </span>
                            @else
                                <span class="prod-badge inactive">Inactive</span>
                            @endif
                            <span class="prod-badge condition">{{ \App\Models\Product::conditionOptions()[$viewProduct->condition] ?? $viewProduct->condition }}</span>
                        </div>
                        <p class="mt-2 text-xs text-[#9E9E9E]">Views: {{ $viewProduct->views ?? 0 }}</p>
                    </div>
                </div>
                <div class="prod-gradient-line mb-4"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div class="prod-section-box" style="margin-bottom: 0;">
                        <div class="prod-section-title">Category</div>
                        <p class="text-[#424242]">{{ $viewProduct->category ?? '—' }}</p>
                    </div>
                    <div class="prod-section-box" style="margin-bottom: 0;">
                        <div class="prod-section-title">Stock</div>
                        <p class="{{ $viewProduct->stock === 0 ? 'prod-stock-low' : 'text-[#424242]' }} font-semibold">{{ $viewProduct->stock }} units</p>
                    </div>
                    <div class="prod-section-box" style="margin-bottom: 0;">
                        <div class="prod-section-title">Price</div>
                        <p class="prod-price text-lg">₱{{ number_format($viewProduct->price, 2) }}</p>
                    </div>
                    <div class="prod-section-box" style="margin-bottom: 0;">
                        <div class="prod-section-title">Sale Price</div>
                        <p class="text-[#2D9F4E] font-semibold text-lg">{{ $viewProduct->sale_price ? '₱' . number_format($viewProduct->sale_price, 2) : '—' }}</p>
                    </div>
                </div>
                <div class="mt-4 prod-section-box" style="margin-bottom: 0;">
                    <div class="prod-section-title">Description</div>
                    <p class="text-sm text-[#424242] leading-relaxed">{{ $viewProduct->description }}</p>
                </div>
            </div>
        @else
            <div class="prod-empty-state">
                <div class="w-16 h-16 rounded-full bg-[#F5F5F5] flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-[#9E9E9E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <p>Product not found.</p>
            </div>
        @endif
    </div>
    {{-- ============================================================
         CREATE / EDIT FORM
    ============================================================ --}}
    @elseif($mode === 'create' || $mode === 'edit')
    <div class="prod-container">
        <div class="flex items-center gap-3 mb-5">
            <button type="button" wire:click="backToList" class="prod-btn-ghost" style="padding: 8px 12px; font-size: 0.75rem;">
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to products
            </button>
            <div class="prod-gradient-line"></div>
            <div>
                <h3 class="font-bold text-[#212121]">{{ $mode === 'create' ? 'Add New Product' : 'Edit Product' }}</h3>
                @if($mode === 'edit' && $editingId)
                    @php($editProduct = \App\Models\Product::find($editingId))
                    @if($editProduct)
                        <p class="text-xs text-[#9E9E9E]">Views: {{ $editProduct->views ?? 0 }}</p>
                    @endif
                @endif
            </div>
        </div>

        <div class="max-w-2xl">
            <div class="prod-section-box" style="padding: 24px;">
                <div class="prod-form-group">
                    <label class="prod-label">Product name <span class="text-[#F57C00]">*</span></label>
                    <input type="text" wire:model.defer="name" class="prod-input" placeholder="Enter product name">
                    @error('name') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                </div>

                <div class="prod-form-group">
                    <label class="prod-label">Description <span class="text-[#F57C00]">*</span></label>
                    <textarea wire:model.defer="description" rows="4" class="prod-input prod-textarea" placeholder="Describe your product..."></textarea>
                    @error('description') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="prod-form-group">
                        <label class="prod-label">Category <span class="text-[#F57C00]">*</span></label>
                        <input type="text" wire:model.defer="category" list="seller-categories" class="prod-input" placeholder="e.g. Clothing, Bags">
                        <datalist id="seller-categories">
                            @foreach($this->sellerCategories as $cat)
                                <option value="{{ $cat }}"></option>
                            @endforeach
                        </datalist>
                        @error('category') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                        @if(count($this->sellerCategories))
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach($this->sellerCategories as $cat)
                                    <button type="button" wire:click="$set('category', '{{ $cat }}')" class="prod-tag">{{ $cat }}</button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="prod-form-group">
                        <label class="prod-label">Tags <span class="text-[#9E9E9E]">(optional)</span></label>
                        <input type="text" wire:model.defer="tags" class="prod-input" placeholder="vintage, denim, bundle">
                        @error('tags') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="prod-form-group">
                    <label class="prod-label">Condition <span class="text-[#F57C00]">*</span></label>
                    <select wire:model.defer="condition" class="prod-input">
                        @foreach(\App\Models\Product::conditionOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('condition') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                </div>

                <div class="prod-form-group">
                    <label class="prod-label">Size / Variant <span class="text-[#9E9E9E]">(optional)</span></label>
                    <select wire:model.defer="size_variant" class="prod-input">
                        <option value="">— None —</option>
                        @foreach(\App\Models\Product::sizeVariantOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                        <option value="custom">Custom</option>
                    </select>
                    @if($size_variant === 'custom')
                        <input type="text" wire:model.defer="size_custom" maxlength="100" class="prod-input mt-2" placeholder="e.g. One size, 28 waist">
                    @endif
                    @error('size_variant') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="prod-form-group">
                        <label class="prod-label">Price (₱) <span class="text-[#F57C00]">*</span></label>
                        <input type="number" step="0.01" min="0" wire:model.defer="price" class="prod-input">
                        @error('price') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                    </div>
                    <div class="prod-form-group">
                        <label class="prod-label">Sale Price (₱) <span class="text-[#9E9E9E]">(optional)</span></label>
                        <input type="number" step="0.01" min="0" wire:model.defer="sale_price" class="prod-input">
                        @error('sale_price') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="prod-form-group">
                    <label class="prod-label">Delivery Fee (₱) <span class="text-[#9E9E9E]">(optional)</span></label>
                    <input type="number" step="0.01" min="0" wire:model.defer="delivery_fee" class="prod-input max-w-xs" placeholder="0">
                    <p class="mt-1 text-xs text-[#9E9E9E]">Used when your store uses "Per product" delivery</p>
                    @error('delivery_fee') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="prod-form-group">
                        <label class="prod-label">Stock Quantity <span class="text-[#F57C00]">*</span></label>
                        <input type="number" min="0" wire:model.defer="stock" class="prod-input">
                        @error('stock') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                    </div>
                    <div class="prod-form-group">
                        <label class="prod-label">Low Stock Threshold</label>
                        <input type="number" min="0" wire:model.defer="low_stock_threshold" class="prod-input" placeholder="10">
                        <p class="mt-1 text-xs text-[#9E9E9E]">Notification when stock falls to this level</p>
                        @error('low_stock_threshold') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="prod-form-group">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.defer="is_active" class="rounded border-[#E0E0E0] text-[#2D9F4E] focus:ring-[#2D9F4E]">
                        <span class="text-sm text-[#424242]">Active (visible to customers)</span>
                    </label>
                </div>

                <div class="prod-form-group">
                    <label class="prod-label">
                        Product Image {{ $mode === 'create' ? '*' : '(leave blank to keep current)' }}
                    </label>
                    @if($mode === 'edit' && $editingId)
                        @php($existingProduct = \App\Models\Product::find($editingId))
                        @if($existingProduct && $existingProduct->image_path)
                            <img src="{{ asset('storage/' . $existingProduct->image_path) }}" class="mt-2 h-24 w-24 object-cover rounded-lg border border-[#E0E0E0]" alt="Current image">
                        @endif
                    @endif
                    <input type="file" wire:model="image" accept="image/*" class="prod-input mt-2" style="padding: 8px;">
                    <div wire:loading wire:target="image" class="mt-1 text-xs text-[#2D9F4E]">Uploading...</div>
                    @if($image)
                        <img src="{{ $image->temporaryUrl() }}" class="mt-2 h-24 w-24 object-cover rounded-lg border border-[#E0E0E0]" alt="Preview">
                    @endif
                    @error('image') <div class="mt-1 text-xs text-[#E53935]">{{ $message }}</div> @enderror
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" wire:click="save" wire:loading.attr="disabled" class="prod-btn-primary">
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $mode === 'create' ? 'Create Product' : 'Save Changes' }}
                    </button>
                    <button type="button" wire:click="backToList" class="prod-btn-cancel">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
