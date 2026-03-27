<footer class="relative mt-24 overflow-hidden min-h-screen flex flex-col justify-end">
    {{-- Organic blob background --}}
    <div class="absolute inset-0">
        <svg class="absolute w-full h-full" viewBox="0 0 1440 400" preserveAspectRatio="none">
            <defs>
                <linearGradient id="footer-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#34d399;stop-opacity:1" />
                    <stop offset="50%" style="stop-color:#10b981;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#059669;stop-opacity:1" />
                </linearGradient>
            </defs>
            <path fill="url(#footer-gradient)"
                d="M0,100 C320,200 640,50 960,150 C1280,250 1440,100 1440,100 L1440,400 L0,400 Z"></path>
        </svg>

        {{-- Floating elements --}}
        <div class="absolute top-20 left-10 w-24 h-24 bg-white/10 rounded-full blur-xl animate-float"></div>
        <div class="absolute top-40 right-20 w-32 h-32 bg-white/10 rounded-full blur-xl animate-float-delayed"></div>
        <div class="absolute bottom-20 left-1/3 w-20 h-20 bg-white/10 rounded-full blur-xl animate-float"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-10">
        {{-- CTA Section --}}
        <div class="mb-16 text-center">
            <h2 class="text-4xl md:text-5xl font-extrabold mb-4 leading-tight uppercase" style="color: #2d6a4f;">
                Ready to Start<br class="sm:hidden"> Thrifting test?
            </h2>
            <p class="text-lg mb-8 max-w-2xl mx-auto" style="color: #2d6a4f;">
                Join thousands of smart shoppers finding unique treasures every day.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('customer.dashboard') }}"
                    class="px-8 py-4 bg-white font-bold rounded-2xl hover:bg-gray-100 transition-all shadow-2xl hover:shadow-emerald-300/50 hover:scale-105 transform duration-300 text-lg"
                    style="color: #2d6a4f;">
                    Browse Items
                </a>
                <a href="{{ route('seller.login') }}"
                    class="px-8 py-4 bg-white/10 backdrop-blur-sm border-2 border-white/30 font-bold rounded-2xl hover:bg-white/20 transition-all text-lg"
                    style="color: #2d6a4f;">
                    Become a Seller
                </a>
            </div>
        </div>

        {{-- Main footer grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-8 mb-12">
            {{-- Brand --}}
            <div class="col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <div
                        class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-xl transform -rotate-6 hover:rotate-0 transition-transform duration-300">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black text-white">Ukay Hub</h3>
                        <p class="text-indigo-200 text-xs">Sustainable Shopping</p>
                    </div>
                </div>
                <p class="text-indigo-100 text-sm mb-6 max-w-xs leading-relaxed">
                    Giving preloved items a second life, one treasure at a time. Shop smarter, live greener.
                </p>

                {{-- Social with hover effects --}}
                <div class="flex gap-3">
                    <a href="https://facebook.com/joohnndaveee" target="_blank" rel="noopener noreferrer"
                        class="group w-11 h-11 bg-white/10 backdrop-blur-sm rounded-xl flex items-center justify-center hover:bg-white transition-all duration-300 border border-white/20 hover:scale-110 transform">
                        <svg class="w-5 h-5 text-white group-hover:text-indigo-600 transition-colors"
                            fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                    </a>
                    <a href="https://instagram.com/joohnndaveee" target="_blank" rel="noopener noreferrer"
                        class="group w-11 h-11 bg-white/10 backdrop-blur-sm rounded-xl flex items-center justify-center hover:bg-white transition-all duration-300 border border-white/20 hover:scale-110 transform">
                        <svg class="w-5 h-5 text-white group-hover:text-pink-600 transition-colors" fill="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                        </svg>
                    </a>
                </div>
            </div>

            {{-- Quick Links --}}
            <div>
                <h4 class="text-white font-bold mb-4 text-sm uppercase tracking-widest">Shop</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('customer.dashboard') }}"
                            class="text-indigo-100 hover:text-white text-sm transition-colors hover:translate-x-1 inline-block transform duration-200">Browse</a>
                    </li>

                    <li><a href="#"
                            class="text-indigo-100 hover:text-white text-sm transition-colors hover:translate-x-1 inline-block transform duration-200">New
                            Items</a></li>
                    <li><a href="#"
                            class="text-indigo-100 hover:text-white text-sm transition-colors hover:translate-x-1 inline-block transform duration-200">Deals</a>
                    </li>
                </ul>
            </div>

            {{-- Support --}}
            <div>
                <h4 class="text-white font-bold mb-4 text-sm uppercase tracking-widest">Support</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('support.faq') }}"
                            class="text-indigo-100 hover:text-white text-sm transition-colors hover:translate-x-1 inline-block transform duration-200">FAQ</a>
                    </li>
                    <li><a href="{{ route('support.contact') }}"
                            class="text-indigo-100 hover:text-white text-sm transition-colors hover:translate-x-1 inline-block transform duration-200">Contact</a>
                    </li>
                    <li><a href="#"
                            class="text-indigo-100 hover:text-white text-sm transition-colors hover:translate-x-1 inline-block transform duration-200">Shipping</a>
                    </li>
                    <li><a href="#"
                            class="text-indigo-100 hover:text-white text-sm transition-colors hover:translate-x-1 inline-block transform duration-200">Returns</a>
                    </li>
                </ul>
            </div>

            {{-- Legal --}}
            <div>
                <h4 class="text-white font-bold mb-4 text-sm uppercase tracking-widest">Legal</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('support.privacy') }}"
                            class="text-indigo-100 hover:text-white text-sm transition-colors hover:translate-x-1 inline-block transform duration-200">Privacy</a>
                    </li>
                    <li><a href="{{ route('support.terms') }}"
                            class="text-indigo-100 hover:text-white text-sm transition-colors hover:translate-x-1 inline-block transform duration-200">Terms</a>
                    </li>
                    <li><a href="{{ route('support.cookies') }}"
                            class="text-indigo-100 hover:text-white text-sm transition-colors hover:translate-x-1 inline-block transform duration-200">Cookies</a>
                    </li>
                </ul>
            </div>

            {{-- Newsletter --}}
            <div class="col-span-2 md:col-span-4 lg:col-span-2">
                <h4 class="text-white font-bold mb-4 text-sm uppercase tracking-widest">Newsletter</h4>
                <p class="text-indigo-100 text-sm mb-4">Get weekly deals in your inbox.</p>
                <form class="space-y-2">
                    <input type="email" placeholder="ukayhub@email.com"
                        class="w-full px-4 py-3 bg-white/20 backdrop-blur-sm border-2 border-white/30 rounded-xl text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-white/50 text-sm">
                    <button type="submit"
                        class="w-full px-4 py-3 bg-white text-indigo-600 font-bold rounded-xl hover:bg-gray-100 transition-all shadow-lg hover:shadow-xl hover:scale-105 transform duration-200 text-sm">
                        Subscribe →
                    </button>
                </form>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="pt-8 border-t border-white/20">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-sm">
                <div class="text-indigo-100 flex flex-wrap items-center justify-center md:justify-start gap-2">
                    <span class="font-semibold text-white">&copy; {{ date('Y') }} Ukay Hub.</span>
                    <span>All rights reserved.</span>
                    <span class="mx-2 opacity-50">•</span>
                </div>


            </div>
        </div>
    </div>

    <style>
        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        .animate-float-delayed {
            animation: float 6s ease-in-out infinite 3s;
        }
    </style>
</footer>
