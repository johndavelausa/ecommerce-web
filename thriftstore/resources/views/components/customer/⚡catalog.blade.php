<?php

use App\Models\Product;
use App\Models\Wishlist;
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
    public string $availability = 'in_stock'; // in_stock, all
    public string $category = ''; // dynamic categories
    public string $seller = '';   // seller_id or ''
    public ?float $min_price = null;
    public ?float $max_price = null;
    public bool $on_sale_only = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'sort' => ['except' => 'latest'],
        'availability' => ['except' => 'in_stock'],
        'category' => ['except' => ''],
        'seller' => ['except' => ''],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingSort(): void { $this->resetPage(); }
    public function updatingAvailability(): void { $this->resetPage(); }
    public function updatingCategory(): void { $this->resetPage(); }
    public function updatingSeller(): void { $this->resetPage(); }
    public function updatingMinPrice(): void { $this->resetPage(); }
    public function updatingMaxPrice(): void { $this->resetPage(); }
    public function updatedOnSaleOnly(): void { $this->resetPage(); }

    #[Computed]
    public function getProductsProperty()
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
                $q->orderByRaw('sale_price IS NULL') // on-sale (0) first, then non-sale (1)
                  ->orderBy(DB::raw('COALESCE(sale_price, price)'), 'asc');
                break;
            default:
                $q->latest();
        }

        return $q->paginate(12);
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
        $currentQty = $cart[$key]['quantity'] ?? 0;
        $newQty = $currentQty + 1;

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

<div class="space-y-6">
    @php($recent = $this->recentProducts)
    @if($recent->count())
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Recently viewed</h3>
            <div class="flex gap-3 overflow-x-auto pb-1">
                @foreach($recent as $product)
                    <div class="min-w-[180px] max-w-[200px] border border-gray-200 rounded-lg overflow-hidden flex-shrink-0">
                        <div class="h-28 bg-gray-100">
                            @if($product->image_path)
                                <img src="{{ asset('storage/'.$product->image_path) }}"
                                     alt="{{ $product->name }}"
                                     class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                                    No image
                                </div>
                            @endif
                        </div>
                        <div class="p-2 space-y-1">
                            <div class="text-xs font-semibold text-gray-900 line-clamp-2">
                                {{ $product->name }}
                            </div>
                            <div class="text-[11px] text-gray-500">
                                {{ $product->seller->store_name ?? 'Thrift seller' }}
                            </div>
                            <div class="text-xs font-semibold text-gray-900">
                                ₱{{ number_format((float) ($product->sale_price ?? $product->price), 2) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search products…"
                       class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-full sm:w-64">

                <select wire:model.live="availability"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="in_stock">In stock only</option>
                    <option value="all">All availability</option>
                </select>

                <select wire:model.live="category"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All categories</option>
                    @foreach($this->categories as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>

                <select wire:model.live="seller"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All sellers</option>
                    @foreach($this->sellers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>

                <div class="flex items-center gap-1 text-xs text-gray-700">
                    <input type="checkbox" wire:model.live="on_sale_only"
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span>On sale only</span>
                </div>

                <div class="flex items-center gap-2 text-xs text-gray-700">
                    <span>Price:</span>
                    <input type="number" wire:model.live="min_price" step="0.01" min="0" placeholder="Min"
                           class="w-20 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <span>–</span>
                    <input type="number" wire:model.live="max_price" step="0.01" min="0" placeholder="Max"
                           class="w-20 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <select wire:model.live="sort"
                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="latest">Latest</option>
                    <option value="price_asc">Price: Low to high</option>
                    <option value="price_desc">Price: High to low</option>
                    <option value="most_reviewed">Most reviewed</option>
                    <option value="on_sale">On sale first</option>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        @php($products = $this->products)
        @if($products->count())
            @php($wishlist = array_flip($this->wishlistIds))
            <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                @foreach($products as $product)
                    <div class="border border-gray-200 rounded-lg overflow-hidden flex flex-col">
                        <div class="relative h-44 bg-gray-100">
                            @if($product->image_path)
                                <img src="{{ asset('storage/'.$product->image_path) }}"
                                     alt="{{ $product->name }}"
                                     class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                                    No image
                                </div>
                            @endif
                            <button type="button"
                                    wire:click="toggleWishlist({{ $product->id }})"
                                    class="absolute top-2 right-2 inline-flex items-center justify-center w-7 h-7 rounded-full bg-white/80 hover:bg-white text-xs">
                                @if(isset($wishlist[$product->id]))
                                    <span class="text-rose-500">♥</span>
                                @else
                                    <span class="text-gray-400">♡</span>
                                @endif
                            </button>
                            @if($product->sale_price)
                                <span class="absolute top-2 left-2 bg-rose-600 text-white text-2xs px-2 py-0.5 rounded-full uppercase tracking-wide">
                                    Sale
                                </span>
                            @endif
                        </div>
                        <div class="flex-1 flex flex-col p-3 space-y-1">
                            <div class="text-sm font-semibold text-gray-900 line-clamp-2">
                                {{ $product->name }}
                            </div>
                            <div class="text-xs text-gray-500 flex items-center justify-between">
                                <span>{{ $product->seller?->store_name ?? 'Thrift seller' }}</span>
                                @if($product->reviews_count > 0)
                                    <span class="flex items-center gap-1">
                                        <span class="text-yellow-400 text-xs">★</span>
                                        <span class="text-xs text-gray-700">
                                            {{ number_format($product->reviews_avg_rating, 1) }}
                                        </span>
                                        <span class="text-[11px] text-gray-400">
                                            ({{ $product->reviews_count }})
                                        </span>
                                    </span>
                                @endif
                            </div>
                            <div class="mt-1 flex items-baseline gap-2">
                                @if($product->sale_price)
                                    <span class="text-base font-semibold text-rose-600">
                                        ₱{{ number_format($product->sale_price, 2) }}
                                    </span>
                                    <span class="text-xs text-gray-400 line-through">
                                        ₱{{ number_format($product->price, 2) }}
                                    </span>
                                @else
                                    <span class="text-base font-semibold text-gray-900">
                                        ₱{{ number_format($product->price, 2) }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">
                                @if($product->stock > 0)
                                    {{ $product->stock }} in stock
                                @else
                                    <span class="text-rose-600 font-medium">Out of stock</span>
                                @endif
                            </div>
                        </div>
                        <div class="px-3 pb-3">
                            @if($product->stock > 0)
                                <button type="button"
                                        wire:click="addToCart({{ $product->id }})"
                                        class="w-full inline-flex justify-center items-center px-3 py-2 bg-indigo-600 border border-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Add to cart
                                </button>
                            @else
                                <button type="button" disabled
                                        class="w-full inline-flex justify-center items-center px-3 py-2 bg-gray-300 border border-gray-300 rounded-md text-xs font-semibold text-gray-600 uppercase tracking-widest cursor-not-allowed">
                                    Sold out
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $products->links() }}
            </div>
        @else
            <div class="py-12 text-center text-gray-500 text-sm">
                No products found. Try adjusting your filters or search term.
            </div>
        @endif
    </div>
</div>

