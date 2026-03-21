<?php

use App\Models\Product;
use App\Models\Review;
use App\Models\Seller;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /** @var int From parent: store page seller id */
    public int $sellerId;

    public string $activeTab = "products"; // products | categories | reviews
    public string $sort = "newest"; // newest, price_asc, price_desc, most_reviewed
    public string $category = ""; // for categories tab: selected category or '' = All

    protected $queryString = [
        "activeTab" => ["except" => "products"],
        "sort" => ["except" => "newest"],
        "category" => ["except" => ""],
    ];

    public function mount(int $sellerId): void
    {
        $this->sellerId = $sellerId;
    }

    public function updatingActiveTab(): void
    {
        $this->resetPage("products_page");
        $this->resetPage("reviews_page");
    }

    public function updatingSort(): void
    {
        $this->resetPage("products_page");
    }

    public function updatingCategory(): void
    {
        $this->resetPage("products_page");
    }

    #[Computed]
    public function seller()
    {
        return Seller::query()
            ->with("user")
            ->where("id", $this->sellerId)
            ->where("status", "approved")
            ->firstOrFail();
    }

    #[Computed]
    public function getProductsProperty()
    {
        $q = Product::query()
            ->with("seller")
            ->withCount("reviews")
            ->where("seller_id", $this->sellerId)
            ->where("is_active", true);

        if ($this->activeTab === "categories" && $this->category !== "") {
            $q->where("category", $this->category);
        }

        switch ($this->sort) {
            case "price_asc":
                $q->orderBy(DB::raw("COALESCE(sale_price, price)"), "asc");
                break;
            case "price_desc":
                $q->orderBy(DB::raw("COALESCE(sale_price, price)"), "desc");
                break;
            case "most_reviewed":
                $q->orderBy("reviews_count", "desc")->orderBy(
                    DB::raw("COALESCE(sale_price, price)"),
                    "asc"
                );
                break;
            default:
                $q->latest();
        }

        return $q->paginate(24, ["*"], "products_page");
    }

    /** Categories with product counts for this seller (Tab 2). */
    #[Computed]
    public function getCategoriesWithCountsProperty(): array
    {
        $rows = Product::query()
            ->where("seller_id", $this->sellerId)
            ->where("is_active", true)
            ->whereNotNull("category")
            ->where("category", "!=", "")
            ->select("category", DB::raw("COUNT(*) as count"))
            ->groupBy("category")
            ->orderBy("category")
            ->get();

        return $rows
            ->mapWithKeys(fn($r) => [$r->category => (int) $r->count])
            ->all();
    }

    #[Computed]
    public function getReviewsProperty()
    {
        return Review::query()
            ->whereHas(
                "product",
                fn($q) => $q->where("seller_id", $this->sellerId)
            )
            ->with(["customer", "product"])
            ->orderByDesc("created_at")
            ->paginate(10, ["*"], "reviews_page");
    }

    /** Store rating and star breakdown for Reviews tab. */
    #[Computed]
    public function getStoreReviewsSummaryProperty(): array
    {
        $reviews = Review::query()
            ->join("products", "products.id", "=", "reviews.product_id")
            ->where("products.seller_id", $this->sellerId)
            ->select("reviews.rating")
            ->get();

        $total = $reviews->count();
        $avg = $total > 0 ? round($reviews->avg("rating"), 1) : 0;
        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($reviews as $r) {
            $star = (int) $r->rating;
            if (isset($breakdown[$star])) {
                $breakdown[$star]++;
            }
        }
        $maxBar = $total > 0 ? max($breakdown) : 0;

        return [
            "avg" => $avg,
            "total" => $total,
            "breakdown" => $breakdown,
            "maxBar" => $maxBar,
        ];
    }

    #[Computed]
    public function wishlistIds(): array
    {
        $user = Auth::guard("web")->user();
        if (!$user) {
            return [];
        }
        return Wishlist::query()
            ->where("customer_id", $user->id)
            ->pluck("product_id")
            ->all();
    }

    public function addToCart(int $productId): void
    {
        $product = Product::query()
            ->with("seller")
            ->where("id", $productId)
            ->where("seller_id", $this->sellerId)
            ->where("is_active", true)
            ->where("stock", ">", 0)
            ->whereHas("seller", fn($q) => $q->where("status", "approved"))
            ->firstOrFail();

        $cart = Session::get("cart", []);
        $key = (string) $product->id;
        if (!isset($cart[$key]) && count($cart) >= 50) {
            $this->dispatch('toast', type: 'error', message: 'Cart is full (max 50 items). Remove an item or checkout first.');
            return;
        }
        $currentQty = $cart[$key]["quantity"] ?? 0;
        $newQty = min($currentQty + 1, $product->stock);
        $cart[$key] = [
            "product_id" => $product->id,
            "seller_id" => $product->seller_id,
            "name" => $product->name,
            "price" => (float) ($product->sale_price ?? $product->price),
            "image_path" => $product->image_path,
            "quantity" => $newQty,
        ];
        Session::put("cart", $cart);
        $count = array_sum(array_map(fn($row) => (int) ($row['quantity'] ?? 0), $cart));
        $this->dispatch('cart-updated', count: $count);
        $this->dispatch('toast', type: 'success', message: 'Added to Cart');
    }

    public function toggleWishlist(int $productId): void
    {
        $customer = Auth::guard("web")->user();
        if (!$customer) {
            return;
        }
        $existing = Wishlist::query()
            ->where("customer_id", $customer->id)
            ->where("product_id", $productId)
            ->first();
        if ($existing) {
            $existing->delete();
            $this->dispatch('toast', type: 'info', message: 'Removed from Wishlist');
        } else {
            Wishlist::create([
                "customer_id" => $customer->id,
                "product_id" => $productId,
            ]);
            $this->dispatch('toast', type: 'success', message: 'Added to Wishlist');
        }
        $cart = Session::get('cart', []);
        $count = array_sum(array_map(fn($row) => (int) ($row['quantity'] ?? 0), $cart));
        $this->dispatch('cart-updated', count: $count);
        unset($this->wishlistIds);
    }

    public function setCategory(string $value): void
    {
        $this->category = $value;
        $this->resetPage("products_page");
    }
};
?>
<div class="max-w-5xl mx-auto sm:px-6 lg:px-8 mt-6">


    {{-- Tab navigation --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6" aria-label="Tabs">
            <button type="button" wire:click="$set('activeTab', 'products')"
                class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'products' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                All Products
            </button>
            <button type="button" wire:click="$set('activeTab', 'categories')"
                class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'categories' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Categories
            </button>
            <button type="button" wire:click="$set('activeTab', 'reviews')"
                class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'reviews' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Reviews
            </button>
        </nav>
    </div>

    {{-- Tab 1: All Products --}}
    @if ($activeTab === 'products')
        <div class="bg-white rounded-lg shadow p-4 sm:p-6 mt-4">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <h3 class="text-lg font-semibold text-gray-900">All products</h3>
                <div class="flex items-center gap-2">
                    <label for="sort-store" class="text-sm text-gray-600">Sort:</label>
                    <select id="sort-store" wire:model.live="sort" class="rounded border-gray-300 text-sm">
                        <option value="newest">Newest</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="most_reviewed">Most Reviewed</option>
                    </select>
                </div>
            </div>
            @php
                $products = $this->products;
            @endphp
            @if ($products->count())
                @php
                    $wishlist = array_flip($this->wishlistIds);
                @endphp
                <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($products as $product)
                        @php
                            $threshold = (int) ($product->low_stock_threshold ?? 10);
                            $isOutOfStock = $product->stock === 0;
                            $isLowStock = !$isOutOfStock && $product->stock <= $threshold;
                        @endphp
                        <div
                            class="border border-gray-200 rounded-lg overflow-hidden flex flex-col relative {{ $isOutOfStock ? 'opacity-75' : '' }}">
                            @if ($isOutOfStock)
                                <div
                                    class="absolute inset-0 bg-gray-100/70 z-10 flex items-center justify-center pointer-events-none">
                                    <span
                                        class="bg-gray-700 text-white text-xs font-semibold px-3 py-1.5 rounded-full uppercase">Out
                                        of Stock</span>
                                </div>
                            @endif
                            <div class="relative h-44 bg-gray-100">
                                <a href="{{ route('product.show', $product->id) }}" class="block w-full h-full">
                                    @if ($product->image_path)
                                        <img src="{{ asset('storage/' . $product->image_path) }}"
                                            alt="{{ $product->name }}" class="w-full h-full object-cover"
                                            loading="lazy">
                                    @else
                                        <div
                                            class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                                            No image</div>
                                    @endif
                                </a>
                                <button type="button" wire:click="toggleWishlist({{ $product->id }})"
                                    class="absolute top-2 right-2 inline-flex items-center justify-center w-7 h-7 rounded-full bg-white/80 hover:bg-white text-xs z-20">
                                    @if (isset($wishlist[$product->id]))
                                       <span class="text-rose-500">♥</span>
                                    @else
                                        <span class="text-gray-400">♡</span>
                                    @endif
                                </button>
                                @if ($product->sale_price)
                                    <span
                                        class="absolute top-2 left-2 bg-rose-600 text-white text-2xs px-2 py-0.5 rounded-full uppercase tracking-wide z-20">Sale</span>
                                @endif
                                @if ($isLowStock)
                                    <span
                                        class="absolute bottom-2 left-2 bg-amber-500 text-white text-2xs px-2 py-0.5 rounded z-20">Low
                                        Stock</span>
                                @endif
                            </div>
                            <div class="flex-1 flex flex-col p-3 space-y-1">
                                <a href="{{ route('product.show', $product->id) }}"
                                    class="text-sm font-semibold text-gray-900 line-clamp-2 hover:text-indigo-600">{{ $product->name }}</a>
                                <div class="text-xs flex flex-wrap gap-1 items-center">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded font-medium bg-indigo-100 text-indigo-800">{{ \App\Models\Product::conditionOptions()[$product->condition] ?? ucfirst(str_replace('_', ' ', $product->condition ?? 'Good')) }}</span>
                                    @if ($product->size_variant)
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded font-medium bg-gray-100 text-gray-700">{{ \App\Models\Product::sizeVariantOptions()[$product->size_variant] ?? $product->size_variant }}</span>
                                    @endif
                                    @if ($product->seller?->is_verified ?? false)
                                        <span class="inline-flex items-center text-blue-600"
                                            title="Verified seller">✓</span>
                                    @endif
                                </div>
                                <div class="mt-1 flex items-baseline gap-2">
                                    @if ($product->sale_price)
                                        <span
                                            class="text-base font-semibold text-rose-600">₱{{ number_format($product->sale_price, 2) }}</span>
                                        <span
                                            class="text-xs text-gray-400 line-through">₱{{ number_format($product->price, 2) }}</span>
                                    @else
                                        <span
                                            class="text-base font-semibold text-gray-900">₱{{ number_format($product->price, 2) }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="px-3 pb-3">
                                @if ($product->stock > 0)
                                    <button type="button" wire:click="addToCart({{ $product->id }})"
                                        class="w-full inline-flex justify-center items-center px-3 py-2 bg-indigo-600 border border-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-indigo-500">Add
                                        to cart</button>
                                @else
                                    <button type="button" disabled
                                        class="w-full inline-flex justify-center items-center px-3 py-2 bg-gray-300 border border-gray-300 rounded-md text-xs font-semibold text-gray-600 uppercase tracking-widest cursor-not-allowed">Add
                                        to cart</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    {{ $products->withQueryString()->links() }}
                </div>
            @else
                <p class="py-12 text-center text-gray-500 text-sm">No products found.</p>
            @endif
        </div>
    @endif





    {{-- Tab 2: Categories --}}
    @if ($activeTab === 'categories')
        <div class="bg-white rounded-lg shadow p-4 sm:p-6 mt-4">
            @php
                $categoriesWithCounts = $this->categoriesWithCounts;
            @endphp
            <div class="flex flex-wrap gap-2 mb-4">
                <button type="button" wire:click="$set('category', '')"
                    class="px-3 py-1.5 rounded-full text-sm font-medium {{ $category === '' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    All
                </button>
                @foreach ($categoriesWithCounts as $cat => $count)
                    <button type="button" wire:click="setCategory({{ json_encode($cat) }})"
                        class="px-3 py-1.5 rounded-full text-sm font-medium {{ $category === $cat ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ $cat }} ({{ $count }})
                    </button>
                @endforeach
            </div>
            @if (empty($categoriesWithCounts))
                <p class="py-8 text-center text-gray-500 text-sm">No categories yet.</p>
            @else
                @php
                    $products = $this->products;
                @endphp
                @if ($products->count())
                    @php
                        $wishlist = array_flip($this->wishlistIds);
                    @endphp
                    <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        @foreach ($products as $product)
                            @php
                                $threshold = (int) ($product->low_stock_threshold ?? 10);
                                $isOutOfStock = $product->stock === 0;
                                $isLowStock = !$isOutOfStock && $product->stock <= $threshold;
                            @endphp
                            <div
                                class="border border-gray-200 rounded-lg overflow-hidden flex flex-col relative {{ $isOutOfStock ? 'opacity-75' : '' }}">
                                @if ($isOutOfStock)
                                    <div
                                        class="absolute inset-0 bg-gray-100/70 z-10 flex items-center justify-center pointer-events-none">
                                        <span
                                            class="bg-gray-700 text-white text-xs font-semibold px-3 py-1.5 rounded-full uppercase">Out
                                            of Stock</span>
                                    </div>
                                @endif
                                <div class="relative h-44 bg-gray-100">
                                    <a href="{{ route('product.show', $product->id) }}" class="block w-full h-full">
                                        @if ($product->image_path)
                                            <img src="{{ asset('storage/' . $product->image_path) }}"
                                                alt="{{ $product->name }}" class="w-full h-full object-cover"
                                                loading="lazy">
                                        @else
                                            <div
                                                class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                                                No image</div>
                                        @endif
                                    </a>
                                    <button type="button" wire:click="toggleWishlist({{ $product->id }})"
                                        class="absolute top-2 right-2 inline-flex items-center justify-center w-7 h-7 rounded-full bg-white/80 hover:bg-white text-xs z-20">
                                        @if (isset($wishlist[$product->id]))
                                            <span class="text-rose-500">♥</span>
                                        @else
                                            <span class="text-gray-400">♡</span>
                                        @endif
                                    </button>
                                    @if ($product->sale_price)
                                        <span
                                            class="absolute top-2 left-2 bg-rose-600 text-white text-2xs px-2 py-0.5 rounded-full uppercase tracking-wide z-20">Sale</span>
                                    @endif
                                    @if ($isLowStock)
                                        <span
                                            class="absolute bottom-2 left-2 bg-amber-500 text-white text-2xs px-2 py-0.5 rounded z-20">Low
                                            Stock</span>
                                    @endif
                                </div>
                                <div class="flex-1 flex flex-col p-3 space-y-1">
                                    <a href="{{ route('product.show', $product->id) }}"
                                        class="text-sm font-semibold text-gray-900 line-clamp-2 hover:text-indigo-600">{{ $product->name }}</a>
                                    <div class="text-xs flex flex-wrap gap-1 items-center">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded font-medium bg-indigo-100 text-indigo-800">{{ \App\Models\Product::conditionOptions()[$product->condition] ?? ucfirst(str_replace('_', ' ', $product->condition ?? 'Good')) }}</span>
                                        @if ($product->size_variant)
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded font-medium bg-gray-100 text-gray-700">{{ \App\Models\Product::sizeVariantOptions()[$product->size_variant] ?? $product->size_variant }}</span>
                                        @endif
                                        @if ($product->seller?->is_verified ?? false)
                                            <span class="inline-flex items-center text-blue-600"
                                                title="Verified seller">✓</span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex items-baseline gap-2">
                                        @if ($product->sale_price)
                                            <span
                                                class="text-base font-semibold text-rose-600">₱{{ number_format($product->sale_price, 2) }}</span>
                                            <span
                                                class="text-xs text-gray-400 line-through">₱{{ number_format($product->price, 2) }}</span>
                                        @else
                                            <span
                                                class="text-base font-semibold text-gray-900">₱{{ number_format($product->price, 2) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="px-3 pb-3">
                                    @if ($product->stock > 0)
                                        <button type="button" wire:click="addToCart({{ $product->id }})"
                                            class="w-full inline-flex justify-center items-center px-3 py-2 bg-indigo-600 border border-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-indigo-500">Add
                                            to cart</button>
                                    @else
                                        <button type="button" disabled
                                            class="w-full inline-flex justify-center items-center px-3 py-2 bg-gray-300 border border-gray-300 rounded-md text-xs font-semibold text-gray-600 uppercase tracking-widest cursor-not-allowed">Add
                                            to cart</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        {{ $products->withQueryString()->links() }}
                    </div>
                @else
                    <p class="py-12 text-center text-gray-500 text-sm">No products in this category.</p>
                @endif
            @endif
        </div>
    @endif

    {{-- Tab 3: Reviews --}}
    @if ($activeTab === 'reviews')
        <div class="bg-white rounded-lg shadow p-4 sm:p-6 mt-4">
            @php
                $summary = $this->storeReviewsSummary;
            @endphp
            @if ($summary['total'] > 0)
                <div class="flex flex-wrap gap-8 mb-6 pb-6 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <span class="text-4xl font-bold text-gray-900">{{ number_format($summary['avg'], 1) }}</span>
                        <span class="text-gray-500">/ 5</span>
                    </div>
                    <div class="text-sm text-gray-600">{{ $summary['total'] }}
                        {{ $summary['total'] === 1 ? 'review' : 'reviews' }}</div>
                    <div class="w-full max-w-xs space-y-1">
                        @foreach ([5, 4, 3, 2, 1] as $stars)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="w-8 text-gray-600">{{ $stars }}
                                    star{{ $stars !== 1 ? 's' : '' }}</span>
                                <div class="flex-1 h-3 bg-gray-200 rounded overflow-hidden">
                                    <div class="h-full bg-amber-400 rounded"
                                        style="width: {{ $summary['maxBar'] > 0 ? round((100 * ($summary['breakdown'][$stars] ?? 0)) / $summary['maxBar']) : 0 }}%">
                                    </div>
                                </div>
                                <span class="w-6 text-gray-600">{{ $summary['breakdown'][$stars] ?? 0 }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @php
                $reviews = $this->reviews;
            @endphp
            @if ($reviews->count())
                <div class="space-y-4">
                    @foreach ($reviews as $review)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span
                                    class="font-medium text-gray-900">{{ $review->customer->name ?? ($review->customer->username ?? 'Customer') }}</span>
                                <span class="text-xs text-gray-500">{{ $review->created_at->format('M j, Y') }}</span>
                            </div>
                            <div class="mt-1 text-amber-500 text-sm">
                                {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                            </div>
                            <p class="mt-2 text-sm text-gray-700 whitespace-pre-wrap">{{ $review->body }}</p>
                            <div class="mt-2 text-sm">
                                <span class="text-gray-500">Product:</span>
                                <a href="{{ route('product.show', $review->product_id) }}"
                                    class="text-indigo-600 hover:underline">{{ $review->product->name ?? '—' }}</a>
                            </div>
                            @if ($review->seller_reply)
                                <div class="mt-3 pl-4 border-l-2 border-gray-200 bg-gray-50 rounded-r py-2 pr-3">
                                    <div class="text-xs font-medium text-gray-500 mb-0.5">Seller reply</div>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $review->seller_reply }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    {{ $reviews->withQueryString()->links() }}
                </div>
            @else
                <p class="py-12 text-center text-gray-500 text-sm">No reviews yet.</p>
            @endif
        </div>
    @endif
</div>
