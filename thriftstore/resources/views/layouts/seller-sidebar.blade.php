@php
    $seller = Auth::guard('seller')->user()?->seller;
    $storeName = $seller?->store_name ?? 'My Store';
    $initials = collect(explode(' ', $storeName))->take(2)->map(fn($w) => strtoupper($w[0] ?? ''))->implode('');
@endphp

<aside x-data="{ sidebarOpen: false }"
       x-on:toggle-sidebar.window="sidebarOpen = !sidebarOpen"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-50 w-64 flex-col min-h-screen transition-transform duration-300 ease-in-out md:static md:translate-x-0 md:flex"
       style="background: linear-gradient(180deg, #0F3D22 0%, #1a5c35 60%, #0F3D22 100%); box-shadow: 4px 0 24px rgba(0,0,0,0.18);">
    
    {{-- Mobile Overlay --}}
    <div x-show="sidebarOpen" 
         @click="sidebarOpen = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 md:hidden -z-10"></div>


    {{-- ── Brand Header ── --}}
    <div class="px-5 pt-7 pb-5" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
        <a href="{{ route('seller.dashboard') }}" class="flex items-center gap-3 mb-5 group">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 transition-transform group-hover:scale-105"
                 style="background: linear-gradient(135deg, #F9C74F 0%, #F5A623 100%); box-shadow: 0 4px 12px rgba(249,199,79,0.35);">
                <svg class="w-5 h-5" style="color: #0F3D22;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
            </div>
            <div>
                <p class="text-white font-bold text-base leading-none tracking-tight">Ukay Hub</p>
                <p class="text-xs font-medium mt-0.5" style="color: #F9C74F; letter-spacing: 0.06em; text-transform: uppercase;">Seller Portal</p>
            </div>
        </a>

        {{-- Store card --}}
        <div class="flex items-center gap-3 rounded-xl px-3 py-2.5" style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1);">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 font-bold text-sm"
                 style="background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%); color: #fff; letter-spacing: 0.05em;">
                {{ $initials ?: 'S' }}
            </div>
            <div class="min-w-0">
                <p class="text-white font-semibold text-sm truncate leading-tight">{{ $storeName }}</p>
                <p class="text-xs truncate" style="color: rgba(255,255,255,0.45);">Active Seller</p>
            </div>
        </div>
    </div>

    {{-- ── Navigation ── --}}
    <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">

        {{-- Section label --}}
        <p class="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-[0.12em]" style="color: rgba(255,255,255,0.3);">Main</p>

        {{-- Dashboard --}}
        @php $active = request()->routeIs('seller.dashboard'); @endphp
        <a href="{{ route('seller.dashboard') }}"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 relative"
           style="{{ $active
               ? 'background: rgba(249,199,79,0.15); color: #F9C74F; border-left: 3px solid #F9C74F;'
               : 'color: rgba(255,255,255,0.65); border-left: 3px solid transparent;' }}
               @if(!$active) onmouseover=\"this.style.background='rgba(255,255,255,0.06)';this.style.color='#fff';\" onmouseout=\"this.style.background='';this.style.color='rgba(255,255,255,0.65)';\" @endif">
            <svg class="w-4.5 h-4.5 flex-shrink-0" style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            <span>Dashboard</span>
        </a>

        {{-- Orders --}}
        @php $active = request()->routeIs('seller.orders*'); @endphp
        <a href="{{ route('seller.orders') }}"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
           style="{{ $active
               ? 'background: rgba(249,199,79,0.15); color: #F9C74F; border-left: 3px solid #F9C74F;'
               : 'color: rgba(255,255,255,0.65); border-left: 3px solid transparent;' }}
               @if(!$active) onmouseover=\"this.style.background='rgba(255,255,255,0.06)';this.style.color='#fff';\" onmouseout=\"this.style.background='';this.style.color='rgba(255,255,255,0.65)';\" @endif">
            <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <span>Orders</span>
        </a>

        {{-- Products --}}
        @php $active = request()->routeIs('seller.products*'); @endphp
        <a href="{{ route('seller.products') }}"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
           style="{{ $active
               ? 'background: rgba(249,199,79,0.15); color: #F9C74F; border-left: 3px solid #F9C74F;'
               : 'color: rgba(255,255,255,0.65); border-left: 3px solid transparent;' }}
               @if(!$active) onmouseover=\"this.style.background='rgba(255,255,255,0.06)';this.style.color='#fff';\" onmouseout=\"this.style.background='';this.style.color='rgba(255,255,255,0.65)';\" @endif">
            <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <span>Products</span>
        </a>

        {{-- Reports --}}
        @php $active = request()->routeIs('seller.reports*'); @endphp
        <a href="{{ route('seller.reports') }}"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
           style="{{ $active
               ? 'background: rgba(249,199,79,0.15); color: #F9C74F; border-left: 3px solid #F9C74F;'
               : 'color: rgba(255,255,255,0.65); border-left: 3px solid transparent;' }}
               @if(!$active) onmouseover=\"this.style.background='rgba(255,255,255,0.06)';this.style.color='#fff';\" onmouseout=\"this.style.background='';this.style.color='rgba(255,255,255,0.65)';\" @endif">
            <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
            </svg>
            <span>Reports</span>
        </a>

        {{-- Divider --}}
        <div class="my-3" style="border-top: 1px solid rgba(255,255,255,0.07);"></div>
        <p class="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-[0.12em]" style="color: rgba(255,255,255,0.3);">Engagement</p>

        {{-- Reviews --}}
        @php $active = request()->routeIs('seller.reviews*'); @endphp
        <a href="{{ route('seller.reviews') }}"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
           style="{{ $active
               ? 'background: rgba(249,199,79,0.15); color: #F9C74F; border-left: 3px solid #F9C74F;'
               : 'color: rgba(255,255,255,0.65); border-left: 3px solid transparent;' }}
               @if(!$active) onmouseover=\"this.style.background='rgba(255,255,255,0.06)';this.style.color='#fff';\" onmouseout=\"this.style.background='';this.style.color='rgba(255,255,255,0.65)';\" @endif">
            <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
            </svg>
            <span>Reviews</span>
        </a>

        {{-- Messages --}}
        @php $active = request()->routeIs('seller.messages*'); @endphp
        <a href="{{ route('seller.messages') }}"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
           style="{{ $active
               ? 'background: rgba(249,199,79,0.15); color: #F9C74F; border-left: 3px solid #F9C74F;'
               : 'color: rgba(255,255,255,0.65); border-left: 3px solid transparent;' }}
               @if(!$active) onmouseover=\"this.style.background='rgba(255,255,255,0.06)';this.style.color='#fff';\" onmouseout=\"this.style.background='';this.style.color='rgba(255,255,255,0.65)';\" @endif">
            <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            <span>Messages</span>
        </a>

        {{-- Divider --}}
        <div class="my-3" style="border-top: 1px solid rgba(255,255,255,0.07);"></div>
        <p class="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-[0.12em]" style="color: rgba(255,255,255,0.3);">Settings</p>

        {{-- Store Settings --}}
        @php $active = request()->routeIs('seller.store*'); @endphp
        <a href="{{ route('seller.store') }}"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
           style="{{ $active
               ? 'background: rgba(249,199,79,0.15); color: #F9C74F; border-left: 3px solid #F9C74F;'
               : 'color: rgba(255,255,255,0.65); border-left: 3px solid transparent;' }}
               @if(!$active) onmouseover=\"this.style.background='rgba(255,255,255,0.06)';this.style.color='#fff';\" onmouseout=\"this.style.background='';this.style.color='rgba(255,255,255,0.65)';\" @endif">
            <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <span>Store Settings</span>
        </a>

        {{-- Payments --}}
        @php $active = request()->routeIs('seller.payments*'); @endphp
        <a href="{{ route('seller.payments') }}"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
           style="{{ $active
               ? 'background: rgba(249,199,79,0.15); color: #F9C74F; border-left: 3px solid #F9C74F;'
               : 'color: rgba(255,255,255,0.65); border-left: 3px solid transparent;' }}
               @if(!$active) onmouseover=\"this.style.background='rgba(255,255,255,0.06)';this.style.color='#fff';\" onmouseout=\"this.style.background='';this.style.color='rgba(255,255,255,0.65)';\" @endif">
            <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span>Payments</span>
        </a>

    </nav>

    {{-- ── Footer ── --}}
    <div class="px-3 pb-4 pt-2" style="border-top: 1px solid rgba(255,255,255,0.08);">
        <form method="POST" action="{{ route('seller.logout') }}">
            @csrf
            <button type="submit"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
                    style="color: rgba(255,255,255,0.45); border-left: 3px solid transparent;"
                    onmouseover="this.style.background='rgba(239,68,68,0.1)';this.style.color='#fca5a5';"
                    onmouseout="this.style.background='';this.style.color='rgba(255,255,255,0.45)';">
                <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span>Log Out</span>
            </button>
        </form>
        {{-- Bottom brand accent --}}
        <div class="mt-3 h-0.5 rounded-full" style="background: linear-gradient(90deg, #2D9F4E 0%, #F9C74F 50%, #2D9F4E 100%); opacity: 0.4;"></div>
    </div>

</aside>
