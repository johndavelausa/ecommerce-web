@php
    $initialSection = request()->query('section', 'personal');
    $sections = [
        'personal' => [
            'label' => 'Personal Information',
            'desc' => 'Manage your name, email and basic details',
            'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>'
        ],
        'security' => [
            'label' => 'Security',
            'desc' => 'Keep your account secure with a strong password',
            'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>'
        ],
        'deletion' => [
            'label' => 'Account Settings',
            'desc' => 'Privacy and account deletion requests',
            'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'
        ],
    ];
@endphp

<x-app-layout>
    <div x-data="{ 
            section: '{{ $initialSection }}',
            setSection(newSection) {
                this.section = newSection;
                const url = new URL(window.location);
                url.searchParams.set('section', newSection);
                window.history.pushState({}, '', url);
            }
        }" 
        class="user-profile-bg py-8 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Profile Header --}}
            <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 animate-fade-in-up">
                <div>
                    <h1 class="profile-section-title !text-2xl">Account Settings</h1>
                    <p class="profile-section-subtitle text-sm">Manage your personal details and preferences.</p>
                </div>
                
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-2 text-xs font-medium text-gray-500">
                        <li><a href="/" class="hover:text-green-700">Home</a></li>
                        <li>
                            <div class="flex items-center">
                                <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/></svg>
                                <span class="ml-1 md:ml-2">Profile</span>
                            </div>
                        </li>
                    </ol>
                </nav>
            </div>

            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Sidebar -->
                <aside class="w-full lg:w-64 shrink-0">
                    <div class="profile-glass-card p-3 sticky top-6 animate-fade-in-up" style="animation-delay: 0.1s">
                        <nav class="profile-sidebar-nav">
                            @foreach($sections as $key => $data)
                                <a href="?section={{ $key }}" 
                                   @click.prevent="setSection('{{ $key }}')"
                                   class="profile-sidebar-item"
                                   :class="section === '{{ $key }}' ? 'is-active' : ''">
                                    <span class="shrink-0">{!! $data['icon'] !!}</span>
                                    <span class="text-xs uppercase tracking-wide font-bold">{{ $data['label'] }}</span>
                                </a>
                            @endforeach
                        </nav>
                        
                        <div class="mt-6 pt-4 border-t border-gray-100 px-2">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex items-center gap-3 w-full px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 rounded-lg transition-all uppercase tracking-wider">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
                </aside>

                <!-- Main Content -->
                <main class="flex-1 min-w-0">
                    <div class="animate-fade-in-up" style="animation-delay: 0.2s">
                        {{-- Personal Section --}}
                        <template x-if="section === 'personal'">
                            <div x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 translate-y-4"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="space-y-6">
                                <div class="profile-glass-card p-6 md:p-8">
                                    <div class="mb-6">
                                        <h3 class="text-xl font-bold text-gray-900 leading-none">Personal Information</h3>
                                        <p class="text-sm text-gray-500 mt-2">Update your primary identity settings.</p>
                                    </div>
                                    <div class="profile-content-form">
                                        @include('profile.partials.update-profile-information-form')
                                    </div>
                                </div>
                                <div class="profile-glass-card p-6 md:p-8">
                                    <div class="profile-content-form">
                                        @include('profile.partials.addresses')
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Security Section --}}
                        <template x-if="section === 'security'">
                            <div x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 translate-y-4"
                                 x-transition:enter-end="opacity-100 translate-y-0">
                                <div class="profile-glass-card p-6 md:p-8">
                                    <div class="mb-6">
                                        <h3 class="text-xl font-bold text-gray-900 leading-none">Security Details</h3>
                                        <p class="text-sm text-gray-500 mt-2">Protect your account with a strong password.</p>
                                    </div>
                                    <div class="profile-content-form">
                                        @include('profile.partials.update-password-form')
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Deletion Section --}}
                        <template x-if="section === 'deletion'">
                            <div x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 translate-y-4"
                                 x-transition:enter-end="opacity-100 translate-y-0">
                                <div class="profile-glass-card p-6 md:p-8 border-red-50">
                                    <div class="mb-6">
                                        <h3 class="text-xl font-bold text-red-600 leading-none">Termination</h3>
                                        <p class="text-sm text-gray-500 mt-2">Remove your account data permanently.</p>
                                    </div>
                                    <div class="profile-content-form pt-4">
                                        @include('profile.partials.delete-user-form')
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </main>
            </div>
        </div>
    </div>
</x-app-layout>
