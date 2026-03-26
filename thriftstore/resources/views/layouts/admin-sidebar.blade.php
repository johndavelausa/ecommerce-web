<style>
    .admin-sidebar {
        background: linear-gradient(180deg, #0A2B17 0%, #0F3D22 60%, #143d28 100%);
        border-right: none;
        min-height: 100vh;
        width: 240px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
    }
    .admin-sidebar-brand {
        padding: 20px 20px 16px;
        border-bottom: 1px solid rgba(249,199,79,0.2);
    }
    .admin-sidebar-brand-title {
        font-size: 1.125rem;
        font-weight: 800;
        color: #F9C74F;
        letter-spacing: 0.01em;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .admin-sidebar-brand-sub {
        font-size: 0.6875rem;
        color: rgba(255,255,255,0.45);
        margin-top: 4px;
    }
    .admin-sidebar-nav {
        padding: 12px 12px;
        flex: 1;
    }
    .admin-sidebar-section {
        font-size: 0.625rem;
        font-weight: 700;
        color: rgba(249,199,79,0.5);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        padding: 12px 10px 6px;
    }
    .admin-nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 500;
        color: rgba(255,255,255,0.65);
        text-decoration: none;
        transition: all 0.15s ease;
        margin-bottom: 2px;
    }
    .admin-nav-item:hover {
        background: rgba(255,255,255,0.07);
        color: rgba(255,255,255,0.95);
    }
    .admin-nav-item.active {
        background: linear-gradient(135deg, rgba(249,199,79,0.2) 0%, rgba(245,166,35,0.15) 100%);
        color: #F9C74F;
        font-weight: 700;
        border: 1px solid rgba(249,199,79,0.25);
    }
    .admin-nav-item svg {
        flex-shrink: 0;
        opacity: 0.7;
    }
    .admin-nav-item.active svg {
        opacity: 1;
        color: #F9C74F;
    }
    .admin-nav-item:hover svg {
        opacity: 1;
    }
    .admin-sidebar-footer {
        padding: 14px 16px;
        border-top: 1px solid rgba(255,255,255,0.07);
    }
</style>

<aside class="hidden md:flex admin-sidebar">
    <div class="admin-sidebar-brand">
        <a href="{{ route('admin.dashboard') }}" class="admin-sidebar-brand-title">
            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            Admin Panel
        </a>
        <div class="admin-sidebar-brand-sub">{{ Auth::guard('admin')->user()?->name }} &middot; System Admin</div>
    </div>

    <nav class="admin-sidebar-nav">
        <div class="admin-sidebar-section">Overview</div>
        <a href="{{ route('admin.dashboard') }}" class="admin-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('admin.reports') }}" class="admin-nav-item {{ request()->routeIs('admin.reports*') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span>Revenue Reports</span>
        </a>

        <div class="admin-sidebar-section">Management</div>
        <a href="{{ route('admin.sellers') }}" class="admin-nav-item {{ request()->routeIs('admin.sellers*') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <span>Seller Management</span>
        </a>
        <a href="{{ route('admin.customers') }}" class="admin-nav-item {{ request()->routeIs('admin.customers*') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>Customer Management</span>
        </a>
        <a href="{{ route('admin.orders') }}" class="admin-nav-item {{ request()->routeIs('admin.orders*') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <span>All Orders</span>
        </a>
        <a href="{{ route('admin.disputes') }}" class="admin-nav-item {{ request()->routeIs('admin.disputes*') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <span>Returns & Refunds</span>
        </a>
        <a href="{{ route('admin.payments') }}" class="admin-nav-item {{ request()->routeIs('admin.payments*') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span>Platform Payments</span>
        </a>

        <div class="admin-sidebar-section">Support</div>
        <a href="{{ route('admin.messages') }}" class="admin-nav-item {{ request()->routeIs('admin.messages*') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            <span>Seller Support</span>
        </a>
        <a href="{{ route('admin.product-reports') }}" class="admin-nav-item {{ request()->routeIs('admin.product-reports*') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Product Reports</span>
        </a>
        <a href="{{ route('admin.deletion-requests') }}" class="admin-nav-item {{ request()->routeIs('admin.deletion-requests*') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            <span>Deletion Requests</span>
        </a>
        <a href="{{ route('admin.settings') }}" class="admin-nav-item {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>System Settings</span>
        </a>
    </nav>
</aside>
