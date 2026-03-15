<?php

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sort = 'latest'; // latest, price_asc, price_desc, most_reviewed, on_sale
    public string $availability = 'all'; // in_stock, all
    public string $category = ''; // dynamic categories
    public string $seller = '';   // seller_id or ''
    public string $condition = ''; // new, like_new, good, fair, poor (A1 - v1.3)
    public string $size_variant = ''; // C1 v1.4 — filter by size (xs,s,m,l,xl,xxl,free_size or custom text)
    public ?float $min_price = null;
    public ?float $max_price = null;
    public bool $on_sale_only = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'sort' => ['except' => 'latest'],
        'availability' => ['except' => 'all'],
        'category' => ['except' => ''],
        'seller' => ['except' => ''],
        'condition' => ['except' => ''],
        'size_variant' => ['except' => ''],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingSort(): void { $this->resetPage(); }
    public function updatingAvailability(): void { $this->resetPage(); }
    public function updatingCategory(): void { $this->resetPage(); }
    public function updatingSeller(): void { $this->resetPage(); }
    public function updatingCondition(): void { $this->resetPage(); }
    public function updatingSizeVariant(): void { $this->resetPage(); }
    public function updatingMinPrice(): void { $this->resetPage(); }
    public function updatingMaxPrice(): void { $this->resetPage(); }
    public function updatedOnSaleOnly(): void { $this->resetPage(); }

    #[Computed]
    public function getProductsProperty()
    {
        $version = Cache::get('products.listing.version', 0);
        $params = [
            'search' => $this->search,
            'sort' => $this->sort,
            'availability' => $this->availability,
            'category' => $this->category,
            'seller' => $this->seller,
            'condition' => $this->condition,
            'size_variant' => $this->size_variant,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'on_sale_only' => $this->on_sale_only,
            'page' => $this->getPage(),
        ];
        $key = 'products.listing.' . $version . '.' . md5(serialize($params));

        return Cache::remember($key, 300, fn () => $this->buildProductsPaginator());
    }

    /** D4 v1.4 — Build listing query for cache or direct use. */
    protected function buildProductsPaginator()
    {
        $q = Product::query()
            ->with('seller')
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->whereHas('seller', function ($q) {
                $q->where('status', 'approved')
                  ->where('is_open', true);
            });

        if ($this->availability === 'in_stock') {
            $q->where('stock', '>', 0);
        }

        if ($this->category !== '') {
            $q->where('category', $this->category);
        }

        if ($this->seller !== '') {
            $q->where('seller_id', (int) $this->seller);
        }

        if ($this->condition !== '') {
            $q->where('condition', $this->condition);
        }

        if ($this->size_variant !== '') {
            $q->where('size_variant', $this->size_variant);
        }

        if ($this->min_price !== null) {
            $q->where(DB::raw('COALESCE(sale_price, price)'), '>=', $this->min_price);
        }

        if ($this->max_price !== null) {
            $q->where(DB::raw('COALESCE(sale_price, price)'), '<=', $this->max_price);
        }

        if ($this->on_sale_only) {
            $q->whereNotNull('sale_price')
              ->where('sale_price', '<', DB::raw('price'));
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                      ->orWhere('description', 'like', $term);
            });
        }

        switch ($this->sort) {
            case 'price_asc':
                $q->orderBy(DB::raw('COALESCE(sale_price, price)'), 'asc');
                break;
            case 'price_desc':
                $q->orderBy(DB::raw('COALESCE(sale_price, price)'), 'desc');
                break;
            case 'most_reviewed':
                $q->orderBy('reviews_count', 'desc')
                  ->orderBy(DB::raw('COALESCE(sale_price, price)'), 'asc');
                break;
            case 'on_sale':
                $q->orderByRaw('sale_price IS NULL')
                  ->orderBy(DB::raw('COALESCE(sale_price, price)'), 'asc');
                break;
            default:
                $q->latest();
        }

        return $q->paginate(24);
    }

    #[Computed]
    public function categories()
    {
        $q = Product::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereHas('seller', function ($q) {
                $q->where('status', 'approved')
                  ->where('is_open', true);
            });

        return $q->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    #[Computed]
    public function sellers()
    {
        return Product::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereHas('seller', function ($q) {
                $q->where('status', 'approved')
                  ->where('is_open', true);
            })
            ->with('seller')
            ->select('seller_id')
            ->distinct()
            ->get()
            ->pluck('seller.store_name', 'seller_id')
            ->filter()
            ->all();
    }

    #[Computed]
    public function wishlistIds(): array
    {
        $user = Auth::guard('web')->user();
        if (! $user) {
            return [];
        }

        return Wishlist::query()
            ->where('customer_id', $user->id)
            ->pluck('product_id')
            ->all();
    }

    #[Computed]
    public function recentProducts()
    {
        $ids = Session::get('recent_products', []);
        if (! is_array($ids) || count($ids) === 0) {
            return collect();
        }

        $products = Product::query()
            ->with('seller')
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->withCount('reviews')
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('seller', function ($q) {
                $q->where('status', 'approved')
                  ->where('is_open', true);
            })
            ->get()
            ->keyBy('id');

        // Preserve session order
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($products[$id])) {
                $ordered[] = $products[$id];
            }
        }

        return collect($ordered);
    }

    public function addToCart(int $productId): void
    {
        $product = Product::query()
            ->with('seller')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereHas('seller', function ($q) {
                $q->where('status', 'approved');
            })
            ->findOrFail($productId);

        $this->recordView($product->id);

        $cart = Session::get('cart', []);
        $key = (string) $product->id;
        if (! isset($cart[$key]) && count($cart) >= 50) {
            $this->addError('cart', __('Cart is full (max 50 items). Remove an item or checkout first.'));
            return;
        }
        $currentQty = $cart[$key]['quantity'] ?? 0;
        $newQty = min($currentQty + 1, $product->stock);

        $cart[$key] = [
            'product_id' => $product->id,
            'seller_id'  => $product->seller_id,
            'name'       => $product->name,
            'price'      => (float) ($product->sale_price ?? $product->price),
            'image_path' => $product->image_path,
            'quantity'   => $newQty,
        ];

        Session::put('cart', $cart);
        $this->dispatch('cart-updated');
    }

    public function toggleWishlist(int $productId): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return;
        }

        $existing = Wishlist::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            Wishlist::firstOrCreate([
                'customer_id' => $customer->id,
                'product_id' => $productId,
            ]);
        }

        $count = Wishlist::where('customer_id', $customer->id)->count();
        $this->dispatch('wishlist-updated', count: $count);

        $this->recordView($productId);
    }

    public function recordView(int $productId): void
    {
        $ids = Session::get('recent_products', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        // Remove if already present
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $productId));

        array_unshift($ids, (int) $productId);
        $ids = array_slice($ids, 0, 10);

        Session::put('recent_products', $ids);
    }
};
?>

@php
    $recent = $this->recentProducts;
    $products = $this->products;
    $wishlist = array_flip($this->wishlistIds);
@endphp

<div class="space-y-8">
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('catalog') }}" class="hover:text-[#2d6c50]">Home</a>
        <span>›</span>
        <span class="font-semibold text-[#2d6c50]">All Products</span>
    </div>

    <div class="flex flex-col gap-8 lg:flex-row">
        <aside class="w-full shrink-0 space-y-6 rounded-2xl border border-[#d6e3dc] bg-white p-5 lg:w-72">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900">Filters</h3>
                <button type="button" wire:click="$set('search', ''); $set('availability', 'all'); $set('category', ''); $set('seller', ''); $set('condition', ''); $set('size_variant', ''); $set('min_price', null); $set('max_price', null); $set('on_sale_only', false); $set('sort', 'latest')" class="text-xs font-semibold text-[#2d6c50] hover:underline">
                    Reset
                </button>
            </div>

            <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-[0.08em] text-gray-500">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search products..." class="w-full rounded-lg border-[#cfe0d7] bg-[#f7fbf9] text-sm focus:border-[#2d6c50] focus:ring-[#2d6c50]">
            </div>

            <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-[0.08em] text-gray-500">Category</label>
                <select wire:model.live="category" class="w-full rounded-lg border-[#cfe0d7] bg-[#f7fbf9] text-sm focus:border-[#2d6c50] focus:ring-[#2d6c50]">
                    <option value="">All categories</option>
                    @foreach($this->categories as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-[0.08em] text-gray-500">Seller</label>
                <select wire:model.live="seller" class="w-full rounded-lg border-[#cfe0d7] bg-[#f7fbf9] text-sm focus:border-[#2d6c50] focus:ring-[#2d6c50]">
                    <option value="">All sellers</option>
                    @foreach($this->sellers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-[0.08em] text-gray-500">Condition</label>
                <select wire:model.live="condition" class="w-full rounded-lg border-[#cfe0d7] bg-[#f7fbf9] text-sm focus:border-[#2d6c50] focus:ring-[#2d6c50]">
                    <option value="">All conditions</option>
                    @foreach(\App\Models\Product::conditionOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-[0.08em] text-gray-500">Size</label>
                <select wire:model.live="size_variant" class="w-full rounded-lg border-[#cfe0d7] bg-[#f7fbf9] text-sm focus:border-[#2d6c50] focus:ring-[#2d6c50]">
                    <option value="">All sizes</option>
                    @foreach(\App\Models\Product::sizeVariantOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-[0.08em] text-gray-500">Availability</label>
                <select wire:model.live="availability" class="w-full rounded-lg border-[#cfe0d7] bg-[#f7fbf9] text-sm focus:border-[#2d6c50] focus:ring-[#2d6c50]">
                    <option value="in_stock">In stock only</option>
                    <option value="all">All availability</option>
                </select>
            </div>

            <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-[0.08em] text-gray-500">Price range</label>
                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                    <input type="number" wire:model.live="min_price" step="0.01" min="0" placeholder="Min" class="rounded-lg border-[#cfe0d7] bg-[#f7fbf9] text-sm focus:border-[#2d6c50] focus:ring-[#2d6c50]">
                    <span class="text-gray-400">to</span>
                    <input type="number" wire:model.live="max_price" step="0.01" min="0" placeholder="Max" class="rounded-lg border-[#cfe0d7] bg-[#f7fbf9] text-sm focus:border-[#2d6c50] focus:ring-[#2d6c50]">
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model.live="on_sale_only" class="rounded border-[#cfe0d7] text-[#2d6c50] focus:ring-[#2d6c50]">
                <span>On sale only</span>
            </label>
        </aside>

        <section class="min-w-0 flex-1 space-y-6">
            <div class="rounded-2xl border border-[#d6e3dc] bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-600">
                        <span class="font-bold text-gray-900">{{ $products->total() }}</span> items found
                    </p>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">Sort by:</span>
                        <select wire:model.live="sort" class="rounded-lg border-[#cfe0d7] bg-[#f7fbf9] text-sm focus:border-[#2d6c50] focus:ring-[#2d6c50]">
                            <option value="latest">Newest arrival</option>
                            <option value="price_asc">Price: Low to high</option>
                            <option value="price_desc">Price: High to low</option>
                            <option value="most_reviewed">Most reviewed</option>
                            <option value="on_sale">On sale first</option>
                        </select>
                    </div>
                </div>
            </div>

            @if($products->count())
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($products as $product)
                        @php
                            $threshold = (int) ($product->low_stock_threshold ?? 10);
                            $isOutOfStock = $product->stock === 0;
                            $isLowStock = !$isOutOfStock && $product->stock <= $threshold;
                        @endphp
                        <article class="group relative flex flex-col overflow-hidden rounded-2xl border border-[#dfe8e4] bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl {{ $isOutOfStock ? 'opacity-75' : '' }}">
                            <div class="relative aspect-[3/4] overflow-hidden bg-gray-100">
                                <a href="{{ route('product.show', $product->id) }}" class="block h-full w-full">
                                    @if($product->image_path)
                                        <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">No image</div>
                                    @endif
                                </a>

                                @if($isOutOfStock)
                                    <span class="absolute inset-0 z-20 flex items-center justify-center bg-gray-100/70 text-xs font-semibold uppercase tracking-wider text-gray-700">Out of Stock</span>
                                @endif

                                <button type="button" wire:click="toggleWishlist({{ $product->id }})" class="absolute right-3 top-3 z-30 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-sm shadow-sm transition-colors hover:bg-white">
                                    @if(isset($wishlist[$product->id]))
                                        <span class="text-rose-500">♥</span>
                                    @else
                                        <span class="text-gray-400">♡</span>
                                    @endif
                                </button>

                                <div class="absolute left-3 top-3 z-20 flex flex-col gap-1.5">
                                    <span class="rounded bg-[#2d6c50] px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-white">
                                        {{ \App\Models\Product::conditionOptions()[$product->condition] ?? ucfirst(str_replace('_', ' ', $product->condition ?? 'Good')) }}
                                    </span>
                                    @if($product->sale_price)
                                        <span class="rounded bg-amber-400 px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-gray-900">Sale</span>
                                    @endif
                                </div>

                                @if($isLowStock)
                                    <span class="absolute bottom-3 left-3 z-20 rounded bg-amber-500 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-white">Low Stock</span>
                                @endif

                                @if($product->seller?->is_verified ?? false)
                                    <span class="absolute bottom-3 right-3 z-20 rounded-full bg-white/90 px-2 py-1 text-[10px] font-semibold text-[#2d6c50] shadow-sm">Verified</span>
                                @endif
                            </div>

                            <div class="flex flex-1 flex-col p-4">
                                <p class="mb-1 text-[11px] uppercase tracking-wider text-gray-500">{{ $product->seller?->store_name ?? 'Ukay Hub Seller' }}</p>
                                <a href="{{ route('product.show', $product->id) }}" class="line-clamp-2 text-sm font-bold text-gray-900 transition-colors group-hover:text-[#2d6c50]">{{ $product->name }}</a>

                                @if($product->size_variant)
                                    <p class="mt-1 text-[11px] font-medium text-gray-500">Size: {{ (\App\Models\Product::sizeVariantOptions())[$product->size_variant] ?? $product->size_variant }}</p>
                                @endif

                                <div class="mt-auto flex items-end justify-between pt-4">
                                    <div class="flex flex-col">
                                        @if($product->sale_price)
                                            <span class="text-xs text-gray-400 line-through">₱{{ number_format((float) $product->price, 2) }}</span>
                                            <span class="text-lg font-bold text-[#2d6c50]">₱{{ number_format((float) $product->sale_price, 2) }}</span>
                                        @else
                                            <span class="text-lg font-bold text-[#2d6c50]">₱{{ number_format((float) $product->price, 2) }}</span>
                                        @endif
                                    </div>

                                    @if($product->stock > 0)
                                        <button type="button" wire:click="addToCart({{ $product->id }})" class="inline-flex items-center justify-center rounded-lg bg-[#2d6c50]/10 p-2.5 text-[#2d6c50] transition-all hover:bg-[#2d6c50] hover:text-white" aria-label="Add to cart">
                                            +
                                        </button>
                                    @else
                                        <button type="button" disabled class="inline-flex items-center justify-center rounded-lg bg-gray-200 p-2.5 text-gray-500 cursor-not-allowed" aria-label="Out of stock">
                                            +
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="rounded-2xl border border-[#d6e3dc] bg-white p-4">
                    {{ $products->links() }}
                </div>
            @else
                <div class="rounded-2xl border border-[#d6e3dc] bg-white px-6 py-16 text-center text-sm text-gray-500">
                    No products found. Try adjusting your filters or search term.
                </div>
            @endif
        </section>
    </div>

    @if($recent->count())
        <section class="rounded-2xl border border-[#d6e3dc] bg-white p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-[0.08em] text-gray-500">Recently viewed</h3>
            <div class="flex gap-4 overflow-x-auto pb-2">
                @foreach($recent as $product)
                    @php
                        $rThreshold = (int) ($product->low_stock_threshold ?? 10);
                        $rOut = $product->stock === 0;
                        $rLow = !$rOut && $product->stock <= $rThreshold;
                    @endphp
                    <a href="{{ route('product.show', $product->id) }}" class="block min-w-[190px] max-w-[210px] overflow-hidden rounded-xl border border-[#dfe8e4] {{ $rOut ? 'opacity-75' : '' }}">
                        <div class="relative h-28 bg-gray-100">
                            @if($product->image_path)
                                <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">No image</div>
                            @endif
                            @if($rOut)
                                <span class="absolute inset-0 flex items-center justify-center bg-gray-100/70 text-[10px] font-semibold uppercase tracking-widest text-gray-700">Out of Stock</span>
                            @elseif($rLow)
                                <span class="absolute bottom-1 left-1 rounded bg-amber-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">Low Stock</span>
                            @endif
                        </div>
                        <div class="space-y-1.5 p-2.5">
                            <p class="line-clamp-2 text-xs font-semibold text-gray-900">{{ $product->name }}</p>
                            <p class="text-xs font-bold text-[#2d6c50]">
                                @if($product->sale_price)
                                    ₱{{ number_format((float) $product->sale_price, 2) }}
                                @else
                                    ₱{{ number_format((float) $product->price, 2) }}
                                @endif
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>

