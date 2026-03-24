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
    public string $sort = 'latest';
    public string $availability = 'all';
    public array $categories_selected = [];
    public string $seller = '';
    public string $condition = '';
    public ?float $min_price = null;
    public ?float $max_price = null;
    public bool $on_sale_only = false;
    public string $viewMode = 'grid';

    protected $queryString = [
        'search' => ['except' => ''],
        'sort' => ['except' => 'latest'],
        'availability' => ['except' => 'all'],
        'categories_selected' => ['as' => 'category', 'except' => []],
        'seller' => ['except' => ''],
        'condition' => ['except' => ''],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingSort(): void { $this->resetPage(); }
    public function updatingAvailability(): void { $this->resetPage(); }
    public function updatingCategoriesSelected(): void { $this->resetPage(); }
    public function updatingSeller(): void { $this->resetPage(); }
    public function updatingCondition(): void { $this->resetPage(); }
    public function updatingMinPrice(): void { $this->resetPage(); }
    public function updatingMaxPrice(): void { $this->resetPage(); }
    public function updatedOnSaleOnly(): void { $this->resetPage(); }

    public function toggleCategory(string $cat): void
    {
        if (in_array($cat, $this->categories_selected)) {
            $this->categories_selected = array_values(array_filter($this->categories_selected, fn($c) => $c !== $cat));
        } else {
            $this->categories_selected[] = $cat;
        }
        $this->resetPage();
    }

    public function toggleCondition(string $cond): void
    {
        $this->condition = ($this->condition === $cond) ? '' : $cond;
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetAllFilters(): void
    {
        $this->search = '';
        $this->availability = 'all';
        $this->categories_selected = [];
        $this->seller = '';
        $this->condition = '';
        $this->min_price = null;
        $this->max_price = null;
        $this->on_sale_only = false;
        $this->sort = 'latest';
        $this->resetPage();
    }

    #[Computed]
    public function getProductsProperty()
    {
        $version = Cache::get('products.listing.version', 0);
        $params = [
            'search' => $this->search,
            'sort' => $this->sort,
            'availability' => $this->availability,
            'category' => $this->categories_selected,
            'seller' => $this->seller,
            'condition' => $this->condition,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'on_sale_only' => $this->on_sale_only,
            'page' => $this->getPage(),
        ];
        $key = 'products.listing.' . $version . '.' . md5(serialize($params));

        return Cache::remember($key, 300, fn () => $this->buildProductsPaginator());
    }

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

        if (!empty($this->categories_selected)) {
            $q->whereIn('category', $this->categories_selected);
        }

        if ($this->seller !== '') {
            $q->where('seller_id', (int) $this->seller);
        }

        if ($this->condition !== '') {
            $q->where('condition', $this->condition);
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
        $count = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cart));
        $this->dispatch('cart-updated', count: $count);
        $this->dispatch('toast', type: 'success', message: 'Added to Cart');
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
            $this->dispatch('toast', type: 'info', message: 'Removed from Wishlist');
        } else {
            Wishlist::firstOrCreate([
                'customer_id' => $customer->id,
                'product_id' => $productId,
            ]);
            $this->dispatch('toast', type: 'success', message: 'Added to Wishlist');
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

        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $productId));
        array_unshift($ids, (int) $productId);
        $ids = array_slice($ids, 0, 10);

        Session::put('recent_products', $ids);
    }
};
?>

@php
    $recent   = $this->recentProducts;
    $categories_selected = array_flip($this->categories_selected);
    $products = $this->products;
    $wishlist = array_flip($this->wishlistIds);
@endphp


<div class="space-y-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('catalog') }}" class="hover:text-[#2D9F4E] transition-colors">Home</a>
        <span class="text-gray-300">›</span>
        <span class="font-semibold text-gray-800">All Products</span>
    </nav>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        {{-- ============================================================
             SIDEBAR FILTERS
             ============================================================ --}}
        <aside class="w-full shrink-0 lg:w-64 xl:w-72">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">

                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-gray-700" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M7 8h10M11 12h2M9 16h6"/>
                        </svg>
                        <h3 class="text-base font-bold text-gray-900">Filters</h3>
                    </div>
                    <button type="button" wire:click="resetAllFilters" class="text-sm font-semibold text-[#2D9F4E] hover:underline transition-colors">
                        Reset
                    </button>
                </div>

                <div class="space-y-6 px-5 py-5">

                    {{-- Search --}}
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search products…"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm placeholder-gray-400 focus:border-[#2D9F4E] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#2D9F4E]"
                        >
                    </div>

                    {{-- CATEGORY --}}
                    @if(count($this->categories) > 0)
                    <div>
                        <h4 class="mb-3 text-[11px] font-bold uppercase tracking-[0.1em] text-gray-500">Category</h4>
                        <div class="space-y-2">
                            @foreach($this->categories as $cat)
                                <label class="flex cursor-pointer items-center gap-2.5 group">
                                    <div class="relative flex items-center">
                                        <input
                                            type="checkbox"
                                            wire:click="toggleCategory('{{ $cat }}')"
                                            {{ isset($categories_selected[$cat]) ? 'checked' : '' }}
                                            class="peer h-4 w-4 cursor-pointer appearance-none rounded border-2 border-gray-300 bg-white transition checked:border-[#2d6c50] checked:bg-[#2d6c50] focus:outline-none focus:ring-2 focus:ring-[#2d6c50]/30"
                                        >
                                        <svg class="pointer-events-none absolute left-0.5 top-0.5 h-3 w-3 text-white opacity-0 peer-checked:opacity-100" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 12 12">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 6l3 3 5-5"/>
                                        </svg>
                                    </div>
                                    <span class="text-sm text-gray-700 group-hover:text-gray-900 {{ isset($categories_selected[$cat]) ? 'font-semibold text-gray-900' : '' }}">{{ $cat }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- CONDITION --}}
                    <div>
                        <h4 class="mb-3 text-[11px] font-bold uppercase tracking-[0.1em] text-gray-500">Condition</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach(\App\Models\Product::conditionOptions() as $value => $label)
                                <button
                                    type="button"
                                    wire:click="toggleCondition('{{ $value }}')"
                                    class="rounded-full border px-3.5 py-1.5 text-xs font-medium transition-all
                                        {{ $condition === $value
                                            ? 'border-[#2D9F4E] bg-[#2D9F4E] text-white shadow-sm'
                                            : 'border-gray-300 bg-white text-gray-600 hover:border-[#2D9F4E] hover:text-[#2D9F4E]' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- PRICE RANGE --}}
                    <div
                        x-data="{
                            minVal: {{ (int) ($this->min_price ?? 0) }},
                            maxVal: {{ (int) ($this->max_price ?? 5000) }},
                            maxLimit: 10000
                        }"
                    >
                        <h4 class="mb-3 text-[11px] font-bold uppercase tracking-[0.1em] text-gray-500">Price Range</h4>

                        {{-- Slider track --}}
                        <div class="relative mb-4">
                            <input
                                type="range"
                                x-model.number="maxVal"
                                :min="0"
                                :max="maxLimit"
                                step="50"
                                @change="$wire.set('max_price', $event.target.value > 0 ? Number($event.target.value) : null)"
                                class="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-gray-200 accent-[#2D9F4E]"
                                style="background: linear-gradient(to right, #2D9F4E 0%, #2D9F4E calc(var(--val, 50%) * 100%), #e5e7eb calc(var(--val, 50%) * 100%), #e5e7eb 100%)"
                                x-init="$el.style.setProperty('--val', maxVal / maxLimit)"
                                @input="$el.style.setProperty('--val', $event.target.value / maxLimit)"
                            >
                        </div>

                        {{-- Min / Max inputs --}}
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-medium text-gray-500">₱</span>
                                <input
                                    type="number"
                                    x-model.number="minVal"
                                    @change="$wire.set('min_price', $event.target.value > 0 ? Number($event.target.value) : null)"
                                    min="0"
                                    placeholder="0"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-6 pr-2 text-sm focus:border-[#2D9F4E] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#2D9F4E]"
                                >
                            </div>
                            <span class="shrink-0 text-xs text-gray-400">to</span>
                            <div class="relative flex-1">
                                <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-medium text-gray-500">₱</span>
                                <input
                                    type="number"
                                    x-model.number="maxVal"
                                    @change="
                                        $wire.set('max_price', $event.target.value > 0 ? Number($event.target.value) : null);
                                        $el.closest('[x-data]').__x.$data.maxVal = Number($event.target.value);
                                    "
                                    min="0"
                                    placeholder="Max"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-6 pr-2 text-sm focus:border-[#2D9F4E] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#2D9F4E]"
                                >
                            </div>
                        </div>
                    </div>

                    {{-- On Sale toggle --}}
                    <label class="flex cursor-pointer items-center gap-2.5">
                        <div class="relative">
                            <input type="checkbox" wire:model.live="on_sale_only" class="peer sr-only">
                            <div class="h-5 w-9 rounded-full border-2 border-gray-300 bg-white transition peer-checked:border-[#2D9F4E] peer-checked:bg-[#2D9F4E]"></div>
                            <div class="absolute left-0.5 top-0.5 h-3.5 w-3.5 rounded-full bg-gray-400 shadow transition peer-checked:translate-x-4 peer-checked:bg-white"></div>
                        </div>
                        <span class="text-sm text-gray-700">On sale only</span>
                    </label>

                    {{-- Apply Filters button --}}
                    <button
                        type="button"
                        wire:click="applyFilters"
                        class="w-full rounded-xl bg-[#2D9F4E] py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-[#1B7A37] active:scale-[0.98]"
                    >
                        Apply Filters
                    </button>

                </div>
            </div>
        </aside>

        {{-- ============================================================
             MAIN CONTENT
             ============================================================ --}}
        <section class="min-w-0 flex-1 space-y-4">

            {{-- Header bar --}}
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-sm text-gray-600">
                    <span class="font-bold text-gray-900">{{ $products->total() }}</span> items found
                </p>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm text-gray-500">Sort by:</span>
                        <select wire:model.live="sort" class="rounded-lg border border-gray-200 bg-gray-50 py-1.5 pl-2.5 pr-7 text-sm text-gray-700 focus:border-[#2D9F4E] focus:outline-none focus:ring-1 focus:ring-[#2D9F4E]">
                            <option value="latest">Newest Arrival</option>
                            <option value="price_asc">Price: Low to High</option>
                            <option value="price_desc">Price: High to Low</option>
                            <option value="most_reviewed">Most Reviewed</option>
                            <option value="on_sale">On Sale First</option>
                        </select>
                    </div>

                    {{-- Grid / List toggle --}}
                    <div class="flex overflow-hidden rounded-lg border border-gray-200">
                        <button
                            type="button"
                            wire:click="$set('viewMode', 'grid')"
                            class="flex items-center justify-center p-2 transition-colors {{ $viewMode === 'grid' ? 'bg-[#2D9F4E] text-white' : 'bg-white text-gray-400 hover:text-gray-600' }}"
                            title="Grid view"
                        >
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 16 16">
                                <rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/>
                                <rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/>
                            </svg>
                        </button>
                        <button
                            type="button"
                            wire:click="$set('viewMode', 'list')"
                            class="flex items-center justify-center p-2 transition-colors {{ $viewMode === 'list' ? 'bg-[#2D9F4E] text-white' : 'bg-white text-gray-400 hover:text-gray-600' }}"
                            title="List view"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Cart error --}}
            @error('cart')
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700">{{ $message }}</div>
            @enderror

            @if($products->count())

                {{-- ========== GRID VIEW ========== --}}
                @if($viewMode === 'grid')
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                    @foreach($products as $product)
                        @php
                            $isOutOfStock  = $product->stock === 0;
                            $isLowStock    = !$isOutOfStock && $product->stock <= (int) ($product->low_stock_threshold ?? 10);
                            $discountPct   = ($product->sale_price && $product->price > 0)
                                             ? (int) round((1 - $product->sale_price / $product->price) * 100)
                                             : 0;
                            $conditionLabel = \App\Models\Product::conditionOptions()[$product->condition]
                                             ?? ucfirst(str_replace('_', ' ', $product->condition ?? ''));
                            $isWishlisted  = isset($wishlist[$product->id]);
                        @endphp

                        <article class="group relative flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md {{ $isOutOfStock ? 'opacity-70' : '' }}">

                            {{-- Image area --}}
                            <div class="relative overflow-hidden bg-gray-50" style="padding-top: 115%;">

                                <a href="{{ route('product.show', $product->id) }}" class="absolute inset-0 block">
                                    @if($product->image_path)
                                        <img
                                            src="{{ asset('storage/'.$product->image_path) }}"
                                            alt="{{ $product->name }}"
                                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                            loading="lazy"
                                        >
                                    @else
                                        <div class="flex h-full w-full items-center justify-center bg-gray-100">
                                            <svg class="h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </a>

                                {{-- Out of stock overlay --}}
                                @if($isOutOfStock)
                                    <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/60 backdrop-blur-[1px]">
                                        <span class="rounded-lg bg-gray-800/80 px-3 py-1.5 text-[11px] font-bold uppercase tracking-widest text-white">Out of Stock</span>
                                    </div>
                                @endif

                                {{-- Top-left badges --}}
                                <div class="absolute left-2.5 top-2.5 z-10 flex flex-col gap-1.5">
                                    @if($conditionLabel)
                                        <span class="rounded px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest bg-[#2D9F4E] text-white shadow-sm">
                                            {{ $conditionLabel }}
                                        </span>
                                    @endif
                                    @if($product->sale_price && $discountPct > 0)
                                        <span class="rounded px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest bg-[#F9C74F] text-[#212121] shadow-sm">
                                            {{ $discountPct }}% OFF
                                        </span>
                                    @endif
                                </div>

                                {{-- Wishlist button --}}
                                <button
                                    type="button"
                                    wire:click="toggleWishlist({{ $product->id }})"
                                    class="absolute right-2.5 top-2.5 z-30 flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-md transition-all hover:scale-110 active:scale-95"
                                    aria-label="Toggle wishlist"
                                >
                                    @if($isWishlisted)
                                        <svg class="h-4 w-4 fill-rose-500 text-rose-500" viewBox="0 0 24 24">
                                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                        </svg>
                                    @endif
                                </button>

                                {{-- Verified / Low stock (bottom of image) --}}
                                <div class="absolute bottom-2.5 left-2.5 right-2.5 z-10 flex items-end justify-between">
                                    @if($product->seller?->is_verified ?? false)
                                        <div class="flex items-center gap-1 rounded-full bg-white/90 px-2 py-0.5 shadow-sm backdrop-blur-sm">
                                            <svg class="h-3 w-3 text-[#2D9F4E]" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-[9px] font-bold uppercase tracking-widest text-[#2D9F4E]">Verified</span>
                                        </div>
                                    @else
                                        <span></span>
                                    @endif
                                    @if($isLowStock)
                                        <span class="rounded bg-amber-500 px-1.5 py-0.5 text-[9px] font-bold uppercase text-white shadow-sm">Low Stock</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Card body --}}
                            <div class="flex flex-1 flex-col p-3">
                                <p class="mb-0.5 text-[10px] uppercase tracking-[0.1em] text-gray-400">
                                    {{ $product->seller?->store_name ?? 'Ukay Hub Seller' }}
                                </p>
                                <a
                                    href="{{ route('product.show', $product->id) }}"
                                    class="line-clamp-2 text-[13px] font-bold leading-snug text-gray-900 transition-colors group-hover:text-[#2D9F4E]"
                                >
                                    {{ $product->name }}
                                </a>

                                <div class="mt-auto flex items-end justify-between pt-3">
                                    <div>
                                        @if($product->sale_price)
                                            <p class="text-[11px] text-gray-400 line-through">₱{{ number_format((float) $product->price, 0) }}</p>
                                            <p class="text-lg font-extrabold leading-none text-[#2D9F4E]">₱{{ number_format((float) $product->sale_price, 0) }}</p>
                                        @else
                                            <p class="text-lg font-extrabold leading-none text-[#2D9F4E]">₱{{ number_format((float) $product->price, 0) }}</p>
                                        @endif
                                    </div>

                                    @if($product->stock > 0)
                                        <button
                                            type="button"
                                            wire:click="addToCart({{ $product->id }})"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#2D9F4E] text-white shadow-sm transition-all hover:bg-[#1B7A37] active:scale-95"
                                            aria-label="Add to cart"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            disabled
                                            class="flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg bg-gray-200 text-gray-400"
                                            aria-label="Out of stock"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- ========== LIST VIEW ========== --}}
                @else
                <div class="flex flex-col gap-3">
                    @foreach($products as $product)
                        @php
                            $isOutOfStock  = $product->stock === 0;
                            $isLowStock    = !$isOutOfStock && $product->stock <= (int) ($product->low_stock_threshold ?? 10);
                            $discountPct   = ($product->sale_price && $product->price > 0)
                                             ? (int) round((1 - $product->sale_price / $product->price) * 100)
                                             : 0;
                            $conditionLabel = \App\Models\Product::conditionOptions()[$product->condition]
                                             ?? ucfirst(str_replace('_', ' ', $product->condition ?? ''));
                            $isWishlisted  = isset($wishlist[$product->id]);
                        @endphp

                        <article class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-3 shadow-sm transition-all hover:shadow-md {{ $isOutOfStock ? 'opacity-70' : '' }}">

                            {{-- Image --}}
                            <div class="relative h-20 w-20 flex-shrink-0 overflow-hidden rounded-lg bg-gray-100">
                                <a href="{{ route('product.show', $product->id) }}" class="block h-full w-full">
                                    @if($product->image_path)
                                        <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">No image</div>
                                    @endif
                                </a>
                                @if($conditionLabel)
                                    <span class="absolute left-1 top-1 rounded px-1.5 py-0.5 text-[8px] font-bold uppercase bg-[#2D9F4E] text-white">{{ $conditionLabel }}</span>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="flex min-w-0 flex-1 items-center gap-4">
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ $product->seller?->store_name ?? 'Ukay Hub Seller' }}</p>
                                    <a href="{{ route('product.show', $product->id) }}" class="line-clamp-1 text-sm font-bold text-gray-900 hover:text-[#2D9F4E] transition-colors">{{ $product->name }}</a>
                                    @if($product->seller?->is_verified ?? false)
                                        <div class="mt-1 flex items-center gap-1">
                                            <svg class="h-3 w-3 text-[#2D9F4E]" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-[10px] font-semibold text-[#2D9F4E]">Verified</span>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-shrink-0 items-center gap-2">
                                    <div class="text-right">
                                        @if($product->sale_price)
                                            <p class="text-xs text-gray-400 line-through">₱{{ number_format((float) $product->price, 0) }}</p>
                                            <p class="text-base font-extrabold text-[#2D9F4E]">₱{{ number_format((float) $product->sale_price, 0) }}</p>
                                        @else
                                            <p class="text-base font-extrabold text-[#2D9F4E]">₱{{ number_format((float) $product->price, 0) }}</p>
                                        @endif
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="toggleWishlist({{ $product->id }})"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 transition hover:bg-gray-200"
                                    >
                                        @if($isWishlisted)
                                            <svg class="h-4 w-4 fill-rose-500" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                        @else
                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                        @endif
                                    </button>

                                    @if($product->stock > 0)
                                        <button
                                            type="button"
                                            wire:click="addToCart({{ $product->id }})"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#2D9F4E] text-white shadow-sm transition-all hover:bg-[#1B7A37] active:scale-95"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                        </button>
                                    @else
                                        <button type="button" disabled class="flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg bg-gray-200 text-gray-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                @endif

                {{-- Pagination --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    {{ $products->links() }}
                </div>

            @else
                <div class="rounded-2xl border border-gray-200 bg-white px-6 py-20 text-center">
                    <svg class="mx-auto mb-4 h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm font-medium text-gray-500">No products found.</p>
                    <p class="mt-1 text-xs text-gray-400">Try adjusting your filters or search term.</p>
                    <button type="button" wire:click="resetAllFilters" class="mt-4 rounded-lg bg-[#2D9F4E] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1B7A37] transition-colors">
                        Clear Filters
                    </button>
                </div>
            @endif

        </section>
    </div>

    {{-- ============================================================
         RECENTLY VIEWED
         ============================================================ --}}
    @if($recent->count())
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-[0.1em] text-gray-500">Recently Viewed</h3>
            <div class="flex gap-3 overflow-x-auto pb-2">
                @foreach($recent as $product)
                    @php
                        $rOut = $product->stock === 0;
                        $rLow = !$rOut && $product->stock <= (int) ($product->low_stock_threshold ?? 10);
                    @endphp
                    <a
                        href="{{ route('product.show', $product->id) }}"
                        class="group block min-w-[160px] max-w-[180px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md {{ $rOut ? 'opacity-70' : '' }}"
                    >
                        <div class="relative h-28 overflow-hidden bg-gray-100">
                            @if($product->image_path)
                                <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">No image</div>
                            @endif
                            @if($rOut)
                                <div class="absolute inset-0 flex items-center justify-center bg-white/60">
                                    <span class="text-[10px] font-semibold uppercase tracking-widest text-gray-700">Out of Stock</span>
                                </div>
                            @elseif($rLow)
                                <span class="absolute bottom-1.5 left-1.5 rounded bg-amber-500 px-1.5 py-0.5 text-[9px] font-bold text-white">Low Stock</span>
                            @endif
                        </div>
                        <div class="space-y-1 p-2.5">
                            <p class="line-clamp-2 text-xs font-semibold text-gray-900 group-hover:text-[#2D9F4E]">{{ $product->name }}</p>
                            <p class="text-xs font-bold text-[#2D9F4E]">
                                ₱{{ number_format((float) ($product->sale_price ?? $product->price), 0) }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

</div>
