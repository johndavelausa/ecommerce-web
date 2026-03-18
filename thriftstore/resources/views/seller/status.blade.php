<x-app-layout>
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-3">
                    @php($seller = auth('seller')->user()?->seller)

                    <div class="text-lg font-semibold">
                        Your seller account is currently:
                        <span class="uppercase">{{ $seller?->status ?? 'pending' }}</span>
                    </div>

                    @if(($seller?->status ?? 'pending') !== 'approved')
                        @if(($seller?->status ?? 'pending') === 'suspended')
                            <div class="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800">
                                Your seller account is temporarily suspended. Please contact Admin for assistance.
                            </div>
                        @elseif(($seller?->status ?? 'pending') === 'rejected')
                            <div class="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800">
                                Your seller account was rejected. Please review your registration payment details or message Admin.
                            </div>
                        @else
                            <div class="text-sm text-gray-600">
                                You can’t access the seller dashboard until the Admin verifies your registration fee payment.
                            </div>
                        @endif
                    @else
                        <div class="text-sm text-gray-600">
                            Your account is approved. Go to the seller dashboard.
                        </div>
                        <a href="{{ route('seller.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Open Seller Dashboard
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

