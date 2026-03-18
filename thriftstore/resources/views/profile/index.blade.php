@php
    $section = request()->query('section', 'personal');
    $sections = [
        'personal' => 'Personal Information',
        'security' => 'Security',
        'orders' => 'My Orders',
        'messages' => 'Messages',
        'deletion' => 'Account Deletion',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex gap-8">
            <!-- Sidebar -->
            <aside class="w-full max-w-xs">
                <div class="bg-white rounded-lg shadow p-6 space-y-2">
                    <div>
                        <div class="font-semibold text-gray-700 text-xs mb-2 uppercase tracking-wider">Profile Sections</div>
                        <ul class="space-y-1">
                            <li><a href="?section=personal" class="sidebar-link{{ $section == 'personal' ? ' font-bold text-green-700' : '' }}">Personal Information</a></li>
                            <li><a href="?section=security" class="sidebar-link{{ $section == 'security' ? ' font-bold text-green-700' : '' }}">Security</a></li>
                            <li><a href="?section=orders" class="sidebar-link{{ $section == 'orders' ? ' font-bold text-green-700' : '' }}">My Orders</a></li>
                            <li><a href="?section=messages" class="sidebar-link{{ $section == 'messages' ? ' font-bold text-green-700' : '' }}">Messages</a></li>
                            <li><a href="?section=deletion" class="sidebar-link{{ $section == 'deletion' ? ' font-bold text-green-700' : '' }}">Account Deletion</a></li>
                        </ul>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 space-y-8">
                @if($section === 'personal')
                    <section class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Personal Information</h3>
                        <div>
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </section>
                    <section class="bg-white shadow rounded-lg p-6">
                        @include('profile.partials.addresses')
                    </section>
                @elseif($section === 'security')
                    <section class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Security</h3>
                        @include('profile.partials.update-password-form')
                    </section>
                @elseif($section === 'orders')
                    <section class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">My Orders</h3>
                        <div class="mb-6">
                            @livewire('customer.orders')
                        </div>
                        <div>
                            <h4 class="text-md font-semibold text-gray-800 mb-2">Reviews</h4>
                            @livewire('customer.reviews')
                        </div>
                    </section>
                @elseif($section === 'messages')
                    <section class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Messages</h3>
                        @livewire('customer.messages')
                    </section>
                @elseif($section === 'deletion')
                    <section class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Deletion</h3>
                        @include('profile.partials.delete-user-form')
                    </section>
                @endif
            </main>
        </div>
    </div>
</x-app-layout>
