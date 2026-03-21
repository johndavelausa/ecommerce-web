@php
    $isSellerContext = request()->is('seller/*') || request()->query('intended') === 'seller';
    $dashboardLabel = __('Dashboard');
    $logoRoute = 'catalog';
    if (request()->is('admin/*')) {
        $user = Auth::guard('admin')->user();
        $dashboardRoute = 'admin.dashboard';
        $logoRoute = 'admin.dashboard';
        $logoutRoute = 'admin.logout';
        $notificationsRoute = 'admin.notifications.read-all';
    } elseif (request()->is('seller/*')) {
        $user = Auth::guard('seller')->user();
        $dashboardRoute = $user
            ? (($user->seller && $user->seller->status === 'approved') ? 'seller.dashboard' : 'seller.status')
            : 'seller.login';
        $logoRoute = $dashboardRoute;
        $logoutRoute = 'seller.logout';
        $notificationsRoute = 'seller.notifications.read-all';
    } else {
        $user = Auth::guard('web')->user();
        if (!$user && $isSellerContext) {
            $dashboardRoute = 'seller.login';
            $logoRoute = 'seller.login';
            $dashboardLabel = __('Dashboard');
        } else {
            $dashboardRoute = 'catalog';
            $dashboardLabel = __('Home');
        }
        $logoutRoute = 'logout';
        $notificationsRoute = 'customer.notifications.read-all';
    }

    $wishlistCount = 0;
    $cartCount = 0;
    $navCategories = collect();
    if ($user && !request()->is('admin/*') && !request()->is('seller/*')) {
        $wishlistCount = \App\Models\Wishlist::where('customer_id', $user->id)->count();
        $cartRows = session('cart', []);
        if (is_array($cartRows)) {
            $cartCount = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cartRows));
        }

        $navCategories = \App\Models\Product::query()
            ->selectRaw('category, COUNT(*) as product_count')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereHas('seller', function ($q) {
                $q->where('status', 'approved')
                  ->where('is_open', true);
            })
            ->groupBy('category')
            ->orderBy('category')
            ->get();
    }
@endphp

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    /* ── Design Tokens ───────────────────────────────────────── */
    :root {
        --c-primary:   #2D6A4F;
        --c-secondary: #52B788;
        --c-accent:    #B7E4C7;
        --c-bg:        #F9FAFB;
        --c-surface:   #ffffff;
        --c-text:      #1A1A2E;
        --c-muted:     #4A5568;
        --c-border:    #E2E8F0;
        --c-danger:    #E53E3E;
    }

    /* ── Navbar shell ────────────────────────────────────────── */
    .ts-nav {
        background-color: var(--c-primary);
        box-shadow: 0 2px 10px rgba(45,106,79,0.3);
        font-family: 'Inter', sans-serif;
        position: relative;
    }
    /* subtle bottom accent line */
    .ts-nav::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent 0%, var(--c-accent) 30%, var(--c-secondary) 70%, transparent 100%);
        opacity: 0.5;
    }

    /* ── Desktop nav links ───────────────────────────────────── */
    .ts-nav-link {
        position: relative;
        display: inline-flex;
        align-items: center;
        height: 100%;
        padding: 0 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: rgba(255,255,255,0.78);
        text-decoration: none;
        white-space: nowrap;
        transition: color 0.15s;
    }
    .ts-nav-link::after {
        content: '';
        position: absolute;
        bottom: 2px; left: 8px; right: 8px;
        height: 2px;
        background: var(--c-accent);
        border-radius: 2px;
        transform: scaleX(0);
        transform-origin: center;
        transition: transform 0.2s ease;
    }
    .ts-nav-link:hover            { color: #fff; }
    .ts-nav-link:hover::after     { transform: scaleX(1); }
    .ts-nav-link.ts-active        { color: #fff; font-weight: 600; }
    .ts-nav-link.ts-active::after { transform: scaleX(1); }

    /* ── Categories trigger ──────────────────────────────────── */
    .ts-cat-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 100%;
        padding: 0 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: rgba(255,255,255,0.78);
        background: transparent;
        border: none;
        cursor: pointer;
        transition: color 0.15s;
        font-family: 'Inter', sans-serif;
    }
    .ts-cat-btn:hover { color: #fff; }

    /* ── Dropdown panel (white card) ─────────────────────────── */
    .ts-dropdown-panel {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: 5px;
        box-shadow: 0 10px 36px rgba(0,0,0,0.11), 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    .ts-dropdown-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 9px 16px;
        font-size: 0.8125rem;
        font-weight: 400;
        color: var(--c-text);
        text-decoration: none;
        border-bottom: 1px solid var(--c-border);
        transition: background 0.12s;
        font-family: 'Inter', sans-serif;
    }
    .ts-dropdown-item:last-child { border-bottom: none; }
    .ts-dropdown-item:hover { background: var(--c-bg); }
    .ts-dropdown-item:hover .ts-cat-pill { background: var(--c-primary); color: #fff; }
    .ts-cat-pill {
        font-size: 10px;
        font-weight: 600;
        color: var(--c-primary);
        background: var(--c-accent);
        padding: 2px 8px;
        border-radius: 9999px;
        transition: background 0.12s, color 0.12s;
    }

    /* ── Icon buttons ────────────────────────────────────────── */
    .ts-icon-btn {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px; height: 38px;
        border-radius: 8px;
        color: rgba(255,255,255,0.78);
        background: transparent;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.15s, color 0.15s;
    }
    .ts-icon-btn:hover         { background: rgba(255,255,255,0.13); color: #fff; }
    .ts-icon-btn.ts-icon-on   { background: rgba(183,228,199,0.18); color: #fff; }

    /* ── Badges ──────────────────────────────────────────────── */
    .ts-badge {
        position: absolute;
        top: 2px; right: 2px;
        min-width: 17px; height: 17px;
        padding: 0 4px;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--c-primary);
        line-height: 1;
    }
    .ts-badge-red   { background: var(--c-danger); color: #fff; }
    .ts-badge-green { background: var(--c-accent); color: #1A3C2E; }

    /* ── Notifications panel ─────────────────────────────────── */
    .ts-notif-panel {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: 10px;
        box-shadow: 0 10px 36px rgba(0,0,0,0.11);
        overflow: hidden;
        font-family: 'Inter', sans-serif;
    }
    .ts-notif-header {
        padding: 10px 16px;
        background: var(--c-bg);
        border-bottom: 1px solid var(--c-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .ts-notif-label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--c-muted);
    }
    .ts-notif-mark {
        font-size: 11px;
        font-weight: 500;
        color: var(--c-primary);
        background: var(--c-accent);
        padding: 3px 9px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: background 0.12s, color 0.12s;
        font-family: 'Inter', sans-serif;
    }
    .ts-notif-mark:hover { background: var(--c-secondary); color: #fff; }
    .ts-notif-row {
        padding: 10px 16px;
        border-bottom: 1px solid var(--c-border);
        transition: background 0.12s;
    }
    .ts-notif-row:last-child { border-bottom: none; }
    .ts-notif-row:hover { background: var(--c-bg); }
    .ts-notif-row-title { font-size: 12px; font-weight: 600; color: var(--c-text); }
    .ts-notif-row-body  { font-size: 11px; color: var(--c-muted); margin-top: 2px; }
    .ts-notif-row-time  { font-size: 10px; color: var(--c-secondary); margin-top: 3px; font-weight: 500; }
    .ts-notif-empty     { padding: 20px 16px; font-size: 12px; color: var(--c-muted); text-align: center; }

    /* ── User trigger button ─────────────────────────────────── */
    .ts-user-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px 6px 10px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: rgba(255,255,255,0.88);
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s, color 0.15s;
        font-family: 'Inter', sans-serif;
    }
    .ts-user-btn:hover {
        background: rgba(255,255,255,0.18);
        border-color: rgba(255,255,255,0.35);
        color: #fff;
    }

    /* ── User dropdown menu ──────────────────────────────────── */
    .ts-user-menu {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: 10px;
        box-shadow: 0 10px 36px rgba(0,0,0,0.11);
        overflow: hidden;
        font-family: 'Inter', sans-serif;
    }
    .ts-user-menu-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 10px 16px;
        font-size: 0.875rem;
        font-weight: 400;
        color: var(--c-text);
        background: transparent;
        border: none;
        border-bottom: 1px solid var(--c-border);
        text-align: left;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.12s, color 0.12s;
        font-family: 'Inter', sans-serif;
    }
    .ts-user-menu-item:last-child   { border-bottom: none; }
    .ts-user-menu-item:hover        { background: var(--c-bg); color: var(--c-primary); }
    .ts-user-menu-item.ts-logout    { color: var(--c-danger); }
    .ts-user-menu-item.ts-logout:hover { background: #FFF5F5; color: var(--c-danger); }

    /* ── Auth links ──────────────────────────────────────────── */
    .ts-btn-ghost {
        font-size: 0.875rem;
        font-weight: 500;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        padding: 7px 14px;
        border-radius: 7px;
        border: 1px solid rgba(255,255,255,0.25);
        transition: background 0.15s, color 0.15s, border-color 0.15s;
        font-family: 'Inter', sans-serif;
    }
    .ts-btn-ghost:hover { background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.4); }
    .ts-btn-solid {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--c-primary);
        background: #fff;
        text-decoration: none;
        padding: 7px 16px;
        border-radius: 7px;
        border: 1px solid #fff;
        transition: background 0.15s, border-color 0.15s;
        font-family: 'Inter', sans-serif;
    }
    .ts-btn-solid:hover { background: var(--c-accent); border-color: var(--c-accent); }

    /* ── Hamburger ───────────────────────────────────────────── */
    .ts-hamburger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px;
        border-radius: 8px;
        color: rgba(255,255,255,0.85);
        background: transparent;
        border: 1px solid rgba(255,255,255,0.22);
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
    }
    .ts-hamburger:hover { background: rgba(255,255,255,0.12); color: #fff; }

    /* ── Mobile menu ─────────────────────────────────────────── */
    .ts-mobile-wrap {
        background: var(--c-surface);
        border-top: 3px solid var(--c-accent);
        font-family: 'Inter', sans-serif;
    }
    .ts-mob-link {
        display: flex;
        align-items: center;
        padding: 11px 20px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--c-muted);
        text-decoration: none;
        border-left: 3px solid transparent;
        transition: background 0.12s, color 0.12s, border-color 0.12s;
        cursor: pointer;
        background: none;
        border-top: none;
        border-right: none;
        border-bottom: none;
        width: 100%;
        text-align: left;
        font-family: 'Inter', sans-serif;
    }
    .ts-mob-link:hover         { background: var(--c-bg); color: var(--c-primary); border-left-color: var(--c-secondary); }
    .ts-mob-link.ts-active     { background: #F0FFF4; color: var(--c-primary); border-left-color: var(--c-primary); font-weight: 600; }
    .ts-mob-link.ts-mob-danger { color: var(--c-danger); }
    .ts-mob-link.ts-mob-danger:hover { background: #FFF5F5; border-left-color: var(--c-danger); }
    .ts-mob-user {
        padding: 14px 20px;
        background: var(--c-bg);
        border-top: 1px solid var(--c-border);
        border-bottom: 1px solid var(--c-border);
    }
    .ts-mob-user-name  { font-size: 14px; font-weight: 600; color: var(--c-text); }
    .ts-mob-user-email { font-size: 12px; color: var(--c-muted); margin-top: 2px; }

    /* misc */
    .ts-vdivider { width:1px; height:22px; background:rgba(255,255,255,0.18); margin:0 4px; }
    .ts-logo { display:flex; align-items:center; transition: opacity 0.15s; }
    .ts-logo:hover { opacity: 0.85; }
</style>

<nav x-data="{ open: false, wishlistCount: {{ (int) $wishlistCount }}, cartCount: {{ (int) $cartCount }} }"
     x-on:wishlist-updated.window="if ($event.detail && typeof $event.detail.count !== 'undefined') wishlistCount = Number($event.detail.count) || 0"
     x-on:cart-updated.window="if ($event.detail && typeof $event.detail.count !== 'undefined') cartCount = Number($event.detail.count) || 0"
     class="ts-nav">

    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8" style="width:100%;">
        <div class="flex justify-between h-16">

            {{-- ── Left: Logo + Links ───────────────────────── --}}
            <div class="flex items-center gap-6">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route($logoRoute) }}" class="ts-logo">
                        <x-application-logo class="block h-9 w-auto" style="filter:brightness(0) invert(1);" />
                    </a>
                </div>

                

				<div class="hidden sm:flex items-center h-full gap-0.5">
                    {{-- Removed Home link as logo already redirects to homepage --}}

                    @if($user && !request()->is('admin/*') && !request()->is('seller/*'))
                        <a href="{{ route('customer.dashboard') }}"
                           class="ts-nav-link {{ request()->routeIs('customer.dashboard') ? 'ts-active' : '' }}">
                            {{ __('Shop') }}
                        </a>
                        {{-- All support/legal links removed from navbar --}}
                    @endif

                    @if($user && request()->is('admin/*'))
                        {{-- Admin: Links moved to sidebar --}}

                    @elseif($user && request()->is('seller/*'))
                        {{-- Seller: Only show notification and profile, no nav links --}}

                    @elseif($user && !request()->is('admin/*') && !request()->is('seller/*'))
                        {{-- Categories --}}
                        <div x-data="{ openCategories: false }"
                             @mouseenter="openCategories = true"
                             @mouseleave="openCategories = false"
                             class="relative h-full flex items-center">
                            <button type="button" class="ts-cat-btn">
                                Categories
                                <svg class="h-3.5 w-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-cloak x-show="openCategories"
                                 x-transition:enter="transition ease-out duration-120"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="absolute left-1/2 top-full -translate-x-1/2 pt-3 -mt-2 w-44 z-50">
                                <div class="ts-dropdown-panel">
                                    <div style="max-height:280px;overflow-y:auto;">
                                        @forelse($navCategories as $cat)
                                            <a href="{{ route('customer.dashboard') }}?category={{ urlencode((string) $cat->category) }}"
                                               class="ts-dropdown-item">
                                                <span>{{ $cat->category }}</span>
                                            </a>
                                        @empty
                                            <div style="padding:14px 16px;font-size:12px;color:#4A5568;text-align:center;">No categories available.</div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Removed My Orders, Reviews, and Messages from navbar --}}
                    @endif
                </div>
            </div>
    <!-- Search Bar (Desktop) -->
    @if (!request()->is('admin/*') && !request()->is('seller/*'))
        <div class="hidden sm:flex items-center justify-center w-full relative" style="max-width:420px;">
            <div x-data="{
                query: '',
                suggestions: [],
                show: false,
                highlight: -1,
                loading: false,
                fetchSuggestions() {
                    if (this.query.length < 2) { this.suggestions = []; this.show = false; return; }
                    this.loading = true;
                    fetch('/search/suggest?q=' + encodeURIComponent(this.query))
                        .then(r => r.json())
                        .then(data => {
                            this.suggestions = data;
                            this.show = true;
                            this.loading = false;
                        });
                },
                select(idx) {
                    if (this.suggestions[idx]) {
                        window.location.href = this.suggestions[idx].url;
                    }
                },
                move(dir) {
                    if (!this.show) return;
                    this.highlight = (this.highlight + dir + this.suggestions.length) % this.suggestions.length;
                },
                complete(idx) {
                    if (this.suggestions[idx]) {
                        this.query = this.suggestions[idx].name;
                    }
                }
            }" class="w-full relative">
                <input type="text"
                    class="block w-full rounded-full border border-gray-300 px-5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-400 shadow-sm"
                    placeholder="Search products, sellers..."
                    x-model="query"
                    @input.debounce.250ms="fetchSuggestions()"
                    @focus="fetchSuggestions(); show = suggestions.length > 0"
                    @keydown.arrow-down.prevent="move(1)"
                    @keydown.arrow-up.prevent="move(-1)"
                    @keydown.enter.prevent="select(highlight >= 0 ? highlight : 0)"
                    @keydown.esc="show = false"
                    autocomplete="off"
                    style="height:38px; min-height:38px;"
                >
                <!-- Suggestions Dropdown -->
                <div x-show="show" x-cloak 
                    class="absolute left-0 mt-2 w-full bg-white border border-gray-200 rounded-full shadow-2xl z-50 flex flex-col"
                    style="top:100%; min-width:0; height: auto; max-height: 140px; overflow-y: auto; font-size: 13px;">
                    <template x-if="loading">
                        <div class="px-4 py-2 text-center text-gray-400 text-sm">Searching...</div>
                    </template>
                    <template x-if="!loading && suggestions.length === 0">
                        <div class="px-4 py-2 text-center text-gray-400 text-sm">No Results found</div>
                    </template>
                    <template x-for="(item, idx) in suggestions" :key="item.url">
                        <div @mousedown.prevent="select(idx)"
                             @mouseenter="highlight = idx"
                             :class="'flex items-center gap-2 px-4 py-2 cursor-pointer transition-all text-sm ' + (highlight === idx ? 'bg-green-50' : 'hover:bg-gray-50')">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full overflow-hidden flex-shrink-0"
                                  :style="item.type === 'seller' && !item.logo_path ? 'background:#B7E4C7;' : 'background:#f3f4f6;'">
                                {{-- Product: show image or green shopping-bag icon --}}
                                <template x-if="item.type === 'product' && item.image_path">
                                    <img :src="item.image_path" alt="Product" class="object-cover w-6 h-6" loading="lazy"
                                         x-on:error="$el.style.display='none'">
                                </template>
                                <template x-if="item.type === 'product' && !item.image_path">
                                    <svg style="width:13px;height:13px;color:#9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                </template>
                                {{-- Seller: show logo image or letter initial --}}
                                <template x-if="item.type === 'seller' && item.logo_path">
                                    <img :src="item.logo_path" alt="Seller" class="object-cover w-6 h-6" loading="lazy"
                                         x-on:error="$el.style.display='none'">
                                </template>
                                <template x-if="item.type === 'seller' && !item.logo_path">
                                    <span style="font-size:10px;font-weight:700;color:#2D6A4F;line-height:1;"
                                          x-text="item.name.charAt(0).toUpperCase()"></span>
                                </template>
                            </span>
                        <span class="font-semibold text-gray-900" x-text="item.prefix"></span><span class="text-gray-400" x-text="item.suffix"></span>
                        <span class="ml-auto text-xs px-2 py-0.5 rounded-full" :class="item.type === 'product' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'" x-text="item.type === 'product' ? 'Product' : 'Seller'"></span>
                    </div>
                    </template>
                </div>
            </div>
        </div>
    @endif
            {{-- ── Right: Icons + User ─────────────────────── --}}
            <div class="hidden sm:flex items-center gap-1.5">

                @if($user && !request()->is('admin/*') && !request()->is('seller/*'))
                    {{-- Wishlist --}}
                    <a href="{{ route('customer.wishlist') }}"
                       class="ts-icon-btn {{ request()->routeIs('customer.wishlist') ? 'ts-icon-on' : '' }}"
                       title="Wishlist" aria-label="Wishlist">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <span x-cloak x-show="wishlistCount > 0" class="ts-badge ts-badge-red" x-text="wishlistCount"></span>
                    </a>

                    {{-- Cart --}}
                    <a href="{{ route('customer.cart') }}"
                       class="ts-icon-btn {{ request()->routeIs('customer.cart') ? 'ts-icon-on' : '' }}"
                       title="Cart" aria-label="Cart">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 3h2l.4 2M7 13h10l4-8H5.4m1.6 8L5.4 5m1.6 8l-1 5h12m-9 0a1 1 0 100 2 1 1 0 000-2zm8 0a1 1 0 100 2 1 1 0 000-2z"/>
                        </svg>
                        <span x-cloak x-show="cartCount > 0" class="ts-badge ts-badge-green" x-text="cartCount"></span>
                    </a>
                @endif

                @if($user)
                    @php
                        $unreadCount = $user->unreadNotifications()->count();
                        $latestNotifications = $user->unreadNotifications()->latest()->limit(5)->get();
                    @endphp

                    {{-- Notifications --}}
                    <div x-data="{ openNotif: false }" class="relative">
                        <button @click="openNotif = !openNotif" class="ts-icon-btn" aria-label="Notifications">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            @if($unreadCount > 0)
                                <span class="ts-badge ts-badge-red">{{ $unreadCount }}</span>
                            @endif
                        </button>

                        <div x-cloak x-show="openNotif"
                             @click.outside="openNotif = false"
                             x-transition:enter="transition ease-out duration-120"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="ts-notif-panel absolute right-0 mt-2 z-50" style="width:300px;">
                            <div class="ts-notif-header">
                                <span class="ts-notif-label">Notifications</span>
                                @if($unreadCount > 0)
                                    <form method="POST" action="{{ route($notificationsRoute) }}">
                                        @csrf
                                        <button type="submit" class="ts-notif-mark">Mark all read</button>
                                    </form>
                                @endif
                            </div>
                            <div style="max-height:260px;overflow-y:auto;">
                                @forelse($latestNotifications as $note)
                                    @php $data = $note->data; @endphp
                                    <div class="ts-notif-row">
                                        <div class="ts-notif-row-title">
                                            @if(($data['type'] ?? null) === 'new_order')
                                                New order #{{ $data['order_id'] ?? '' }}
                                            @elseif(($data['type'] ?? null) === 'order_status_updated')
                                                Order #{{ $data['order_id'] ?? '' }} status updated
                                            @elseif(($data['type'] ?? null) === 'payment_rejected')
                                                {{ ucfirst($data['payment_type'] ?? 'payment') }} payment rejected
                                            @elseif(($data['type'] ?? null) === 'broadcast_announcement')
                                                Announcement: {{ $data['title'] ?? 'Update' }}
                                            @elseif(($data['type'] ?? null) === 'wishlist_low_stock')
                                                Wishlist item low stock
                                            @elseif(($data['type'] ?? null) === 'order_dispute_updated')
                                                @if(($data['event'] ?? null) === 'opened')
                                                    New dispute #{{ $data['dispute_id'] ?? '' }} on order #{{ $data['order_id'] ?? '' }}
                                                @elseif(($data['event'] ?? null) === 'seller_responded')
                                                    Seller responded to dispute #{{ $data['dispute_id'] ?? '' }}
                                                @elseif(($data['event'] ?? null) === 'resolved')
                                                    Dispute #{{ $data['dispute_id'] ?? '' }} resolved
                                                @else
                                                    Dispute #{{ $data['dispute_id'] ?? '' }} updated
                                                @endif
                                            @elseif(($data['type'] ?? null) === 'order_sla_alert')
                                                SLA alert on order #{{ $data['order_id'] ?? '' }}
                                            @elseif(($data['type'] ?? null) === 'seller_payout_released')
                                                Payout released for order #{{ $data['order_id'] ?? '' }}
                                            @else
                                                Notification
                                            @endif
                                        </div>
                                        <div class="ts-notif-row-body">
                                            @if(($data['type'] ?? null) === 'new_order')
                                                From {{ $data['customer_name'] ?? 'customer' }} · ₱{{ number_format($data['total_amount'] ?? 0, 2) }}
                                            @elseif(($data['type'] ?? null) === 'order_status_updated')
                                                Status: {{ ucfirst($data['status'] ?? '') }}
                                            @elseif(($data['type'] ?? null) === 'payment_rejected')
                                                Reason: {{ $data['reason'] ?? '—' }}
                                            @elseif(($data['type'] ?? null) === 'broadcast_announcement')
                                                {{ \Illuminate\Support\Str::limit((string) ($data['body'] ?? ''), 90) }}
                                            @elseif(($data['type'] ?? null) === 'wishlist_low_stock')
                                                {{ $data['product_name'] ?? 'Product' }} is almost sold out ({{ $data['stock'] ?? 0 }} left)
                                            @elseif(($data['type'] ?? null) === 'order_dispute_updated')
                                                {{ ucfirst(str_replace('_', ' ', (string) ($data['status'] ?? 'updated'))) }} · {{ $data['reason_label'] ?? 'Dispute update' }}
                                            @elseif(($data['type'] ?? null) === 'order_sla_alert')
                                                {{ ucfirst((string) ($data['alert_type'] ?? 'alert')) }} · {{ ucfirst((string) ($data['stage'] ?? 'sla')) }} delayed by {{ (int) ($data['delay_hours'] ?? 0) }}h+
                                            @elseif(($data['type'] ?? null) === 'seller_payout_released')
                                                Net payout: ₱{{ number_format((float) ($data['net_amount'] ?? 0), 2) }}
                                            @endif
                                        </div>
                                        <div class="ts-notif-row-time">{{ $note->created_at?->diffForHumans() }}</div>
                                    </div>
                                @empty
                                    <div class="ts-notif-empty">You're all caught up!</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="ts-vdivider"></div>

                    {{-- User Dropdown --}}
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="ts-user-btn">
                                <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span>{{ $user->name }}</span>
                                <svg class="h-3.5 w-3.5 opacity-50" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="ts-user-menu">
                                @if($logoutRoute === 'logout')
                                    <a href="{{ route('profile.edit') }}" class="ts-user-menu-item">
                                        <svg class="h-4 w-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        {{ __('Profile') }}
                                    </a>
                                @endif
                                <a href="{{ route('customer.orders') }}" class="ts-user-menu-item">
                                    <svg class="h-4 w-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                    {{ __('My Orders') }}
                                </a>
                                <a href="{{ route('customer.messages') }}" class="ts-user-menu-item">
                                    <svg class="h-4 w-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                    </svg>
                                    {{ __('Messages') }}
                                </a>
                                <form method="POST" action="{{ route($logoutRoute) }}">
                                    @csrf
                                    <button type="submit" class="ts-user-menu-item ts-logout">
                                        <svg class="h-4 w-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                        </svg>
                                        {{ __('Log Out') }}
                                    </button>
                                </form>
                            </div>
                        </x-slot>
                    </x-dropdown>
                @else
                    @if(!request()->is('admin/*') && !request()->is('seller/*') && !$isSellerContext)
                        <a href="{{ route('login') }}" class="ts-btn-ghost">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" class="ts-btn-solid">{{ __('Register') }}</a>
                    @endif
                @endif
            </div>

            {{-- ── Hamburger ────────────────────────────────── --}}
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = !open" class="ts-hamburger">
                    <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': !open}" class="inline-flex"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16"/>
                        <path :class="{'hidden': !open, 'inline-flex': open}" class="hidden"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Mobile Menu ──────────────────────────────────────── --}}
    <div :class="{'block': open, 'hidden': !open}" class="hidden sm:hidden ts-mobile-wrap">
        <div class="py-1">
            <a href="{{ route($dashboardRoute) }}"
               class="ts-mob-link {{ request()->routeIs($dashboardRoute) ? 'ts-active' : '' }}">
                {{ $dashboardLabel }}
            </a>

            @if($user && request()->is('admin/*'))
                {{-- Admin: Links moved to sidebar --}}
            @elseif($user && request()->is('seller/*'))
                <a href="{{ route('customer.orders') }}" class="ts-mob-link {{ request()->routeIs('customer.orders') ? 'ts-active' : '' }}">{{ __('My Orders') }}</a>

            @elseif($user && !request()->is('admin/*') && !request()->is('seller/*'))
                <a href="{{ route('customer.orders') }}"   class="ts-mob-link {{ request()->routeIs('customer.orders')   ? 'ts-active' : '' }}">{{ __('My Orders') }}</a>
                <a href="{{ route('customer.wishlist') }}" class="ts-mob-link {{ request()->routeIs('customer.wishlist') ? 'ts-active' : '' }}">
                    {{ __('Wishlist') }}
                    <span x-cloak x-show="wishlistCount > 0"
                          style="margin-left:8px;font-size:10px;font-weight:700;background:#E53E3E;color:#fff;padding:1px 7px;border-radius:9999px;"
                          x-text="wishlistCount"></span>
                </a>
                <a href="{{ route('customer.reviews') }}"  class="ts-mob-link {{ request()->routeIs('customer.reviews')  ? 'ts-active' : '' }}">{{ __('Reviews') }}</a>
                <a href="{{ route('customer.messages') }}" class="ts-mob-link {{ request()->routeIs('customer.messages') ? 'ts-active' : '' }}">{{ __('Messages') }}</a>
                <a href="{{ route('customer.cart') }}"     class="ts-mob-link {{ request()->routeIs('customer.cart')     ? 'ts-active' : '' }}">
                    {{ __('Cart') }}
                    <span x-cloak x-show="cartCount > 0"
                          style="margin-left:8px;font-size:10px;font-weight:700;background:#B7E4C7;color:#1A3C2E;padding:1px 7px;border-radius:9999px;"
                          x-text="cartCount"></span>
                </a>
            @endif
        </div>

        @if($user)
            <div class="ts-mob-user">
                <div class="ts-mob-user-name">{{ $user->name }}</div>
                <div class="ts-mob-user-email">{{ $user->email }}</div>
            </div>
            <div class="py-1">
                @if($logoutRoute === 'logout')
                    <a href="{{ route('profile.edit') }}" class="ts-mob-link">{{ __('Profile') }}</a>
                @endif
                <a href="{{ route('customer.orders') }}" class="ts-mob-link {{ request()->routeIs('customer.orders') ? 'ts-active' : '' }}">{{ __('My Orders') }}</a>
                <a href="{{ route('customer.messages') }}" class="ts-mob-link {{ request()->routeIs('customer.messages') ? 'ts-active' : '' }}">{{ __('Messages') }}</a>
                <form method="POST" action="{{ route($logoutRoute) }}">
                    @csrf
                    <button type="submit" class="ts-mob-link ts-mob-danger">{{ __('Log Out') }}</button>
                </form>
            </div>
        @else
            @if(!request()->is('admin/*') && !request()->is('seller/*') && !$isSellerContext)
                <div style="padding:12px 20px;border-top:1px solid #E2E8F0;display:flex;gap:10px;">
                    <a href="{{ route('login') }}"    class="ts-btn-ghost" style="color:#2D6A4F;border-color:#2D6A4F;">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="ts-btn-solid" style="background:#2D6A4F;color:#fff;border-color:#2D6A4F;">{{ __('Register') }}</a>
                </div>
            @endif
        @endif
    </div>
</nav>