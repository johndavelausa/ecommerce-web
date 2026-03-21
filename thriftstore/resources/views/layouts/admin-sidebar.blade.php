<aside class="hidden md:block w-64 bg-slate-100 border-r min-h-screen">
    <div class="p-6 border-b bg-white">
        <div class="font-bold text-lg text-indigo-900 tracking-wide mb-2">
            <a href="{{ route('admin.dashboard') }}">Admin Panel</a>
        </div>
        <div class="text-xs text-gray-500">{{ Auth::guard('admin')->user()?->name }} (System Admin)</div>
    </div>
    <nav class="admin-sidebar-nav p-4 space-y-1">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-2 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-200' }}">
            <span class="text-sm">Dashboard</span>
        </a>
        <a href="{{ route('admin.sellers') }}" class="flex items-center space-x-2 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('admin.sellers*') ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-200' }}">
            <span class="text-sm">Seller Management</span>
        </a>
        <a href="{{ route('admin.customers') }}" class="flex items-center space-x-2 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('admin.customers*') ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-200' }}">
            <span class="text-sm">Customer Management</span>
        </a>
        <a href="{{ route('admin.orders') }}" class="flex items-center space-x-2 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('admin.orders*') ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-200' }}">
            <span class="text-sm">All Orders</span>
        </a>
        <a href="{{ route('admin.reports') }}" class="flex items-center space-x-2 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('admin.reports*') ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-200' }}">
            <span class="text-sm">Revenue Reports</span>
        </a>
        <a href="{{ route('admin.payments') }}" class="flex items-center space-x-2 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('admin.payments*') ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-200' }}">
            <span class="text-sm">Platform Payments</span>
        </a>
        <a href="{{ route('admin.messages') }}" class="flex items-center space-x-2 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('admin.messages*') ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-200' }}">
            <span class="text-sm">Seller Support</span>
        </a>
        <a href="{{ route('admin.product-reports') }}" class="flex items-center space-x-2 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('admin.product-reports*') ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-200' }}">
            <span class="text-sm">Reported Listings</span>
        </a>
        <a href="{{ route('admin.deletion-requests') }}" class="flex items-center space-x-2 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('admin.deletion-requests*') ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-200' }}">
            <span class="text-sm">Deletion Requests</span>
        </a>
        <a href="{{ route('admin.settings') }}" class="flex items-center space-x-2 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('admin.settings*') ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-200' }}">
            <span class="text-sm">System Settings</span>
        </a>
    </nav>
</aside>
