<aside class="hidden md:block w-72 bg-white border-r border-gray-200 min-h-screen flex flex-col">
    {{-- Header with brand colors --}}
    <div class="p-6 border-b border-gray-100" style="background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%); box-shadow: 0 2px 8px rgba(45,159,78,0.25);">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
            </div>
            <a href="{{ route('seller.dashboard') }}" class="font-bold text-lg tracking-tight" style="color: #212121;">Seller Panel</a>
        </div>
        <div class="text-xs font-medium pl-13" style="color: #757575; padding-left: 52px;">{{ Auth::guard('seller')->user()?->seller?->store_name }}</div>
    </div>
    {{-- Navigation --}}
    <nav class="flex-1 p-4 space-y-1">
        <a href="{{ route('seller.dashboard') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('seller.dashboard') ? 'text-white' : 'hover:bg-gray-50' }}"
           style="{{ request()->routeIs('seller.dashboard') ? 'background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%); box-shadow: 0 2px 8px rgba(45,159,78,0.25);' : 'color: #616161;' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            <span>Dashboard</span>
        </a>

        <a href="{{ route('seller.orders') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('seller.orders*') ? 'text-white' : 'hover:bg-gray-50' }}"
           style="{{ request()->routeIs('seller.orders*') ? 'background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%); box-shadow: 0 2px 8px rgba(45,159,78,0.25);' : 'color: #616161;' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
            </svg>
            <span>Orders</span>
        </a>

        <a href="{{ route('seller.products') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('seller.products*') ? 'text-white' : 'hover:bg-gray-50' }}"
           style="{{ request()->routeIs('seller.products*') ? 'background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%); box-shadow: 0 2px 8px rgba(45,159,78,0.25);' : 'color: #616161;' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <span>Products</span>
        </a>

        <a href="{{ route('seller.reviews') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('seller.reviews*') ? 'text-white' : 'hover:bg-gray-50' }}"
           style="{{ request()->routeIs('seller.reviews*') ? 'background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%); box-shadow: 0 2px 8px rgba(45,159,78,0.25);' : 'color: #616161;' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
            </svg>
            <span>Reviews</span>
        </a>

        <a href="{{ route('seller.messages') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('seller.messages*') ? 'text-white' : 'hover:bg-gray-50' }}"
           style="{{ request()->routeIs('seller.messages*') ? 'background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%); box-shadow: 0 2px 8px rgba(45,159,78,0.25);' : 'color: #616161;' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            <span>Messages</span>
        </a>

        <a href="{{ route('seller.store') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('seller.store*') ? 'text-white' : 'hover:bg-gray-50' }}"
           style="{{ request()->routeIs('seller.store*') ? 'background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%); box-shadow: 0 2px 8px rgba(45,159,78,0.25);' : 'color: #616161;' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <span>Store Settings</span>
        </a>

        <a href="{{ route('seller.payments') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('seller.payments*') ? 'text-white' : 'hover:bg-gray-50' }}"
           style="{{ request()->routeIs('seller.payments*') ? 'background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%); box-shadow: 0 2px 8px rgba(45,159,78,0.25);' : 'color: #616161;' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span>Payments</span>
        </a>
    </nav>

    {{-- Footer accent --}}
    <div class="h-1" style="background: linear-gradient(90deg, #2D9F4E 0%, #F9C74F 100%);"></div>
</aside>
