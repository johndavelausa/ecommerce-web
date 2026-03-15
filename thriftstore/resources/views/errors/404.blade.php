<x-app-layout>
    <div class="min-h-[70vh] flex flex-col items-center justify-center px-4 py-20 text-center">
        <!-- Big Stylized 404 -->
        <h1 class="text-9xl font-extrabold text-blue-600 opacity-20 absolute select-none">404</h1>
        
        <div class="relative z-10">
            <!-- Icon/Illustration -->
            <div class="mb-8">
                <svg class="mx-auto h-24 w-24 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 9.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <h2 class="text-4xl font-bold text-gray-900 mb-4">
                Oops! This page is hiding.
            </h2>
            
            <p class="text-xl text-gray-600 mb-10 max-w-lg mx-auto">
                The link you followed might be broken, or the product has been moved to another rack. 
                Let's get you back to the main collection!
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('catalog') }}" 
                   class="inline-flex items-center justify-center px-8 py-4 text-lg font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-all shadow-lg hover:shadow-blue-500/30 group">
                    <svg class="w-5 h-5 mr-2 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Shopping
                </a>
                
                <button onclick="window.history.back()" 
                        class="inline-flex items-center justify-center px-8 py-4 text-lg font-medium text-blue-600 bg-blue-50 rounded-xl hover:bg-blue-100 transition-all">
                    Go Back
                </button>
            </div>

            <div class="mt-16 text-sm text-gray-400">
                If you think this is a mistake, please <a href="/contact" class="underline hover:text-blue-500">contact us</a>.
            </div>
        </div>
    </div>
</x-app-layout>
