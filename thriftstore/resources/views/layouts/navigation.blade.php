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
            $dashboardRoute = $user ? 'customer.dashboard' : 'catalog';
            $dashboardLabel = $user ? __('Shop') : __('Home');
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

<nav x-data="{ open: false, wishlistCount: {{ (int) $wishlistCount }}, cartCount: {{ (int) $cartCount }} }"
     x-on:wishlist-updated.window="if ($event.detail && typeof $event.detail.count !== 'undefined') wishlistCount = Number($event.detail.count) || 0"
     x-on:cart-updated.window="if ($event.detail && typeof $event.detail.count !== 'undefined') cartCount = Number($event.detail.count) || 0"
     class="bg-white">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route($logoRoute) }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route($dashboardRoute)" :active="request()->routeIs($dashboardRoute)">
                        {{ $dashboardLabel }}
                    </x-nav-link>
                    @if($user && request()->is('admin/*'))
                        <x-nav-link :href="route('admin.sellers')" :active="request()->routeIs('admin.sellers')">
                            {{ __('Sellers') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.customers')" :active="request()->routeIs('admin.customers')">
                            {{ __('Customers') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.messages')" :active="request()->routeIs('admin.messages')">
                            {{ __('Messages') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.product-reports')" :active="request()->routeIs('admin.product-reports')">
                            {{ __('Product reports') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.deletion-requests')" :active="request()->routeIs('admin.deletion-requests')">
                            {{ __('Deletion requests') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.orders')" :active="request()->routeIs('admin.orders')">
                            {{ __('Orders') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.disputes')" :active="request()->routeIs('admin.disputes')">
                            {{ __('Disputes') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.payments')" :active="request()->routeIs('admin.payments')">
                            {{ __('Payments') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.reports')" :active="request()->routeIs('admin.reports')">
                            {{ __('Reports') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.settings')" :active="request()->routeIs('admin.settings')">
                            {{ __('Settings') }}
                        </x-nav-link>
                    @elseif($user && request()->is('seller/*'))
                        <x-nav-link :href="route('seller.messages')" :active="request()->routeIs('seller.messages')">
                            {{ __('Messages') }}
                        </x-nav-link>
                        <x-nav-link :href="route('seller.reviews')" :active="request()->routeIs('seller.reviews')">
                            {{ __('Reviews') }}
                        </x-nav-link>
                        <x-nav-link :href="route('seller.payments')" :active="request()->routeIs('seller.payments')">
                            {{ __('Payments') }}
                        </x-nav-link>
                        <x-nav-link :href="route('seller.orders')" :active="request()->routeIs('seller.orders')">
                            {{ __('Orders') }}
                        </x-nav-link>
                        <x-nav-link :href="route('seller.products')" :active="request()->routeIs('seller.products')">
                            {{ __('Products') }}
                        </x-nav-link>
                        <x-nav-link :href="route('seller.store')" :active="request()->routeIs('seller.store')">
                            {{ __('Store Settings') }}
                        </x-nav-link>
                    @elseif($user && !request()->is('admin/*') && !request()->is('seller/*'))
                        <div x-data="{ openCategories: false }"
                             @mouseenter="openCategories = true"
                             @mouseleave="openCategories = false"
                                class="relative h-full flex items-center">
                            <button type="button"
                                    class="inline-flex items-center h-full text-sm font-medium text-gray-500 hover:text-gray-700">
                                Categories
                                <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div x-cloak x-show="openCategories"
                                 x-transition.opacity.duration.120ms
                                   class="absolute left-1/2 top-full -translate-x-1/2 mt-1 w-72 rounded-xl border border-gray-200 bg-white shadow-xl z-50">
                                <div class="max-h-72 overflow-y-auto py-1">
                                    @forelse($navCategories as $cat)
                                        <a href="{{ route('customer.dashboard') }}?category={{ urlencode((string) $cat->category) }}"
                                           class="flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            <span>{{ $cat->category }}</span>
                                            <span class="text-xs font-semibold text-gray-500">{{ (int) $cat->product_count }}</span>
                                        </a>
                                    @empty
                                        <div class="px-3 py-2 text-sm text-gray-500">No categories available.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <x-nav-link :href="route('customer.orders')" :active="request()->routeIs('customer.orders')">
                            {{ __('My Orders') }}
                        </x-nav-link>
                        <x-nav-link :href="route('customer.reviews')" :active="request()->routeIs('customer.reviews')">
                            {{ __('Reviews') }}
                        </x-nav-link>
                        <x-nav-link :href="route('customer.messages')" :active="request()->routeIs('customer.messages')">
                            {{ __('Messages') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Notifications + Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-4">
                @if($user && !request()->is('admin/*') && !request()->is('seller/*'))
                    <a href="{{ route('customer.wishlist') }}"
                       class="relative inline-flex items-center justify-center p-2 rounded-full {{ request()->routeIs('customer.wishlist') ? 'text-rose-600 bg-rose-50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100' }} focus:outline-none"
                       title="Wishlist"
                       aria-label="Wishlist">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span x-cloak x-show="wishlistCount > 0"
                              class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-semibold"
                              x-text="wishlistCount"></span>
                    </a>

                    <a href="{{ route('customer.cart') }}"
                       class="relative inline-flex items-center justify-center p-2 rounded-full {{ request()->routeIs('customer.cart') ? 'text-indigo-600 bg-indigo-50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100' }} focus:outline-none"
                       title="Cart"
                       aria-label="Cart">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m1.6 8L5.4 5m1.6 8l-1 5h12m-9 0a1 1 0 100 2 1 1 0 000-2zm8 0a1 1 0 100 2 1 1 0 000-2z" />
                        </svg>
                        <span x-cloak x-show="cartCount > 0"
                              class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-indigo-600 text-white text-[10px] font-semibold"
                              x-text="cartCount"></span>
                    </a>
                @endif

                @if($user)
                    @php($unreadCount = $user->unreadNotifications()->count())
                    @php($latestNotifications = $user->unreadNotifications()->latest()->limit(5)->get())
                    <div x-data="{ openNotif: false }" class="relative">
                        <button @click="openNotif = ! openNotif"
                                class="relative inline-flex items-center justify-center p-2 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            @if($unreadCount > 0)
                                <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-600 text-white">
                                    {{ $unreadCount }}
                                </span>
                            @endif
                        </button>
                        <div x-cloak x-show="openNotif"
                             @click.outside="openNotif = false"
                             class="origin-top-right absolute right-0 mt-2 w-72 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                            <div class="py-2 px-3 border-b flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-700">Notifications</span>
                                @if($unreadCount > 0)
                                    <form method="POST" action="{{ route($notificationsRoute) }}">
                                        @csrf
                                        <button type="submit" class="text-[11px] text-indigo-600 hover:text-indigo-800">
                                            Mark all read
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <div class="max-h-64 overflow-y-auto text-xs">
                                @forelse($latestNotifications as $note)
                                    @php($data = $note->data)
                                    <div class="px-3 py-2 border-b last:border-b-0">
                                        <div class="font-medium text-gray-800">
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
                                        <div class="text-gray-600">
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
                                        <div class="text-[10px] text-gray-400 mt-0.5">
                                            {{ $note->created_at?->diffForHumans() }}
                                        </div>
                                    </div>
                                @empty
                                    <div class="px-3 py-3 text-gray-500 text-xs">
                                        No new notifications.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                @if($user)
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                <div>{{ $user->name }}</div>

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @if($logoutRoute === 'logout')
                                <x-dropdown-link :href="route('profile.edit')">
                                    {{ __('Profile') }}
                                </x-dropdown-link>
                            @endif

                            <!-- Authentication (guard-specific logout) -->
                            <form method="POST" action="{{ route($logoutRoute) }}">
                                @csrf

                                <x-dropdown-link :href="route($logoutRoute)"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    @if(!request()->is('admin/*') && !request()->is('seller/*') && !$isSellerContext)
                        <a href="{{ route('login') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Register') }}</a>
                    @endif
                @endif
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route($dashboardRoute)" :active="request()->routeIs($dashboardRoute)">
                {{ $dashboardLabel }}
            </x-responsive-nav-link>
            @if($user && request()->is('admin/*'))
                <x-responsive-nav-link :href="route('admin.sellers')" :active="request()->routeIs('admin.sellers')">
                    {{ __('Sellers') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.customers')" :active="request()->routeIs('admin.customers')">
                    {{ __('Customers') }}
                </x-responsive-nav-link>
<x-responsive-nav-link :href="route('admin.messages')" :active="request()->routeIs('admin.messages')">
                            {{ __('Messages') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.product-reports')" :active="request()->routeIs('admin.product-reports')">
                            {{ __('Product reports') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.deletion-requests')" :active="request()->routeIs('admin.deletion-requests')">
                            {{ __('Deletion requests') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.orders')" :active="request()->routeIs('admin.orders')">
                    {{ __('Orders') }}
                </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.disputes')" :active="request()->routeIs('admin.disputes')">
                            {{ __('Disputes') }}
                        </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.payments')" :active="request()->routeIs('admin.payments')">
                    {{ __('Payments') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.reports')" :active="request()->routeIs('admin.reports')">
                    {{ __('Reports') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.settings')" :active="request()->routeIs('admin.settings')">
                    {{ __('Settings') }}
                </x-responsive-nav-link>
            @elseif($user && request()->is('seller/*'))
                <x-responsive-nav-link :href="route('seller.reports')" :active="request()->routeIs('seller.reports')">
                    {{ __('Reports') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.messages')" :active="request()->routeIs('seller.messages')">
                    {{ __('Messages') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.payments')" :active="request()->routeIs('seller.payments')">
                    {{ __('Payments') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.orders')" :active="request()->routeIs('seller.orders')">
                    {{ __('Orders') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.products')" :active="request()->routeIs('seller.products')">
                    {{ __('Products') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.store')" :active="request()->routeIs('seller.store')">
                    {{ __('Store Settings') }}
                </x-responsive-nav-link>
            @elseif($user && !request()->is('admin/*') && !request()->is('seller/*'))
                <x-responsive-nav-link :href="route('customer.orders')" :active="request()->routeIs('customer.orders')">
                    {{ __('My Orders') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('customer.wishlist')" :active="request()->routeIs('customer.wishlist')">
                    <span>{{ __('Wishlist') }}</span>
                    <span x-cloak x-show="wishlistCount > 0"
                          class="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-semibold"
                          x-text="wishlistCount"></span>
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('customer.reviews')" :active="request()->routeIs('customer.reviews')">
                    {{ __('Reviews') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('customer.messages')" :active="request()->routeIs('customer.messages')">
                    {{ __('Messages') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('customer.cart')" :active="request()->routeIs('customer.cart')">
                    <span>{{ __('Cart') }}</span>
                    <span x-cloak x-show="cartCount > 0"
                          class="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-indigo-600 text-white text-[10px] font-semibold"
                          x-text="cartCount"></span>
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options (only when logged in) -->
        @if($user)
            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800">{{ $user->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ $user->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    @if($logoutRoute === 'logout')
                        <x-responsive-nav-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-responsive-nav-link>
                    @endif

                    <!-- Authentication (guard-specific logout) -->
                    <form method="POST" action="{{ route($logoutRoute) }}">
                        @csrf

                        <x-responsive-nav-link :href="route($logoutRoute)"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        @else
            @if(!request()->is('admin/*') && !request()->is('seller/*') && !$isSellerContext)
                <div class="pt-4 pb-1 border-t border-gray-200">
                    <div class="mt-3 space-y-1 px-4">
                        <x-responsive-nav-link :href="route('login')">
                            {{ __('Log in') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('register')">
                            {{ __('Register') }}
                        </x-responsive-nav-link>
                    </div>
                </div>
            @endif
        @endif
    </div>
</nav>
