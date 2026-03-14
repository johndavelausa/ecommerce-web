@php
    if (request()->is('admin/*')) {
        $user = Auth::guard('admin')->user();
        $dashboardRoute = 'admin.dashboard';
        $logoutRoute = 'admin.logout';
        $notificationsRoute = 'admin.notifications.read-all';
    } elseif (request()->is('seller/*')) {
        $user = Auth::guard('seller')->user();
        $dashboardRoute = ($user?->seller && $user->seller->status === 'approved') ? 'seller.dashboard' : 'seller.status';
        $logoutRoute = 'seller.logout';
        $notificationsRoute = 'seller.notifications.read-all';
    } else {
        $user = Auth::guard('web')->user();
        $dashboardRoute = 'customer.dashboard';
        $logoutRoute = 'logout';
        $notificationsRoute = 'customer.notifications.read-all';
    }
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route($dashboardRoute) }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route($dashboardRoute)" :active="request()->routeIs($dashboardRoute)">
                        {{ __('Dashboard') }}
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
                        <x-nav-link :href="route('customer.orders')" :active="request()->routeIs('customer.orders')">
                            {{ __('My Orders') }}
                        </x-nav-link>
                        <x-nav-link :href="route('customer.wishlist')" :active="request()->routeIs('customer.wishlist')">
                            {{ __('Wishlist') }}
                        </x-nav-link>
                        <x-nav-link :href="route('customer.reviews')" :active="request()->routeIs('customer.reviews')">
                            {{ __('Reviews') }}
                        </x-nav-link>
                        <x-nav-link :href="route('customer.messages')" :active="request()->routeIs('customer.messages')">
                            {{ __('Messages') }}
                        </x-nav-link>
                        <x-nav-link :href="route('customer.cart')" :active="request()->routeIs('customer.cart')">
                            {{ __('Cart') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Notifications + Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-4">
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

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ $user?->name }}</div>

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
                {{ __('Dashboard') }}
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
                <x-responsive-nav-link :href="route('customer.reviews')" :active="request()->routeIs('customer.reviews')">
                    {{ __('Reviews') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('customer.messages')" :active="request()->routeIs('customer.messages')">
                    {{ __('Messages') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('customer.cart')" :active="request()->routeIs('customer.cart')">
                    {{ __('Cart') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ $user?->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ $user?->email }}</div>
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
    </div>
</nav>
