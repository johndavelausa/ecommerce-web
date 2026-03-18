<aside class="hidden md:block w-64 bg-white border-r min-h-screen">
    <div class="p-6 border-b">
        <div class="font-bold text-lg text-green-900 tracking-wide mb-2">
            <a href="{{ route('seller.dashboard') }}">Seller Panel</a>
        </div>
        <div class="text-xs text-gray-500">{{ Auth::guard('seller')->user()?->seller?->store_name }}</div>
    </div>
    <nav class="seller-sidebar-nav p-6 space-y-2">
        <a href="{{ route('seller.dashboard') }}" class="block px-3 py-2 rounded {{ request()->routeIs('seller.dashboard') ? 'bg-green-100 text-green-900 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
            <span>Dashboard</span>
        </a>
        <a href="{{ route('seller.orders') }}" class="block px-3 py-2 rounded {{ request()->routeIs('seller.orders*') ? 'bg-green-100 text-green-900 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
            <span>Orders</span>
        </a>
        <a href="{{ route('seller.products') }}" class="block px-3 py-2 rounded {{ request()->routeIs('seller.products*') ? 'bg-green-100 text-green-900 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
            <span>Products</span>
        </a>
        <a href="{{ route('seller.reviews') }}" class="block px-3 py-2 rounded {{ request()->routeIs('seller.reviews*') ? 'bg-green-100 text-green-900 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
            <span>Reviews</span>
        </a>
        <a href="{{ route('seller.store') }}" class="block px-3 py-2 rounded {{ request()->routeIs('seller.store*') ? 'bg-green-100 text-green-900 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
            <span>Store Settings</span>
        </a>
        <a href="{{ route('seller.payments') }}" class="block px-3 py-2 rounded {{ request()->routeIs('seller.payments*') ? 'bg-green-100 text-green-900 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
            <span>Payments</span>
        </a>
        <a href="{{ route('seller.message-admin') }}" class="block px-3 py-2 rounded {{ request()->routeIs('seller.message-admin') ? 'bg-green-100 text-green-900 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
            <span>Message Admin</span>
        </a>
    </nav>
</aside>
