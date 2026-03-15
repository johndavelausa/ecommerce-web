<footer class="border-t border-gray-200 bg-white mt-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-gray-500">
        <div class="flex items-center gap-1">
            <span>&copy; {{ date('Y') }} ThriftStore.</span>
            <span>All rights reserved.</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="hidden sm:inline-block text-gray-400">&bull;</span>
            <span>Built with Laravel, Livewire & Tailwind CSS.</span>
            <span class="hidden sm:inline-block text-gray-400">&bull;</span>
            <a href="{{ route('faq') }}" class="hover:text-gray-700 underline-offset-2 hover:underline">FAQ</a>
            <span class="text-gray-400">/</span>
            <a href="{{ route('contact') }}" class="hover:text-gray-700 underline-offset-2 hover:underline">Contact</a>
            <span class="text-gray-400">/</span>
            <a href="{{ route('legal.privacy') }}" class="hover:text-gray-700 underline-offset-2 hover:underline">Privacy</a>
            <span class="text-gray-400">/</span>
            <a href="{{ route('legal.terms') }}" class="hover:text-gray-700 underline-offset-2 hover:underline">Terms</a>
            <span class="text-gray-400">/</span>
            <a href="{{ route('legal.cookie-settings') }}" class="hover:text-gray-700 underline-offset-2 hover:underline">Cookies</a>
        </div>
    </div>
</footer>

