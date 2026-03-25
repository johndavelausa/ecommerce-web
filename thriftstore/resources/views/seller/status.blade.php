<x-app-layout>
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-3">
                    @php
                        $user = auth('seller')->user();
                        $seller = $user ? $user->seller : null;
                        $sellerStatus = $seller ? $seller->status : 'pending';
                        $subStatus = $seller ? $seller->subscription_status : 'none';
                        $pendingSubscription = null;
                        if ($seller && $sellerStatus === 'approved' && $subStatus !== 'active') {
                            $pendingSubscription = $seller->payments()->where('type', 'subscription')->where('status', 'pending')->first();
                        }
                    @endphp

                    <div class="text-lg font-semibold">
                        Your seller account is currently:
                        <span class="uppercase">{{ $sellerStatus }}</span>
                    </div>

                    @if($sellerStatus !== 'approved')
                        @if($sellerStatus === 'suspended')
                            <div class="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800 flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    <span class="font-semibold">Your seller account is temporarily suspended.</span>
                                </div>
                                @if($seller && $seller->suspension_reason)
                                    <div class="pl-7 text-xs opacity-90"><span class="font-bold decoration-dotted underline">Reason:</span> {{ $seller->suspension_reason }}</div>
                                @endif
                                <div class="pl-7 text-xs opacity-75 mt-1 text-red-600">Please contact Admin for assistance or message support below.</div>
                            </div>
                        @elseif($sellerStatus === 'rejected')
                            <div class="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Your seller account was rejected. Please review your registration payment details or message Admin.
                            </div>
                        @else
                            <div class="text-sm text-gray-600 bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                                <p class="font-medium text-yellow-800 mb-1">Waiting for Admin Approval</p>
                                You can’t access the seller dashboard until the Admin verifies your registration fee payment (₱200). We'll notify you once approved.
                            </div>
                        @endif
                    @else
                        {{-- Approved --}}
                        @if($subStatus === 'active')
                            <div class="bg-green-50 border border-green-200 p-6 rounded-xl text-center">
                                <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <h3 class="text-lg font-bold text-green-900 mb-1">Account fully active!</h3>
                                <p class="text-green-700 text-sm mb-6">Your subscription is active and you have full access to the seller dashboard.</p>
                                <a href="{{ route('seller.dashboard') }}" class="inline-flex items-center px-6 py-3 bg-green-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Enter Seller Dashboard
                                </a>
                            </div>
                        @elseif($pendingSubscription)
                            <div class="text-sm text-gray-600 bg-blue-50 border border-blue-200 p-4 rounded-lg">
                                <p class="font-medium text-blue-800 mb-1">Subscription Payment Pending</p>
                                Your subscription payment (₱500) has been submitted and is waiting for Admin verification. Once approved, you will have full access to your dashboard.
                                <div class="mt-2 text-xs text-blue-600">Ref: {{ $pendingSubscription->reference_number }} · Submitted {{ $pendingSubscription->created_at->diffForHumans() }}</div>
                            </div>
                        @else
                            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-6">
                                <h3 class="text-xl font-bold text-indigo-900 mb-2">Almost there!</h3>
                                <p class="text-indigo-700 mb-6 text-sm">Your registration is approved. To start selling and access all dashboard features, please pay the monthly subscription fee of ₱500.00.</p>

                                <form method="POST" action="{{ route('seller.subscription.store') }}" enctype="multipart/form-data" class="space-y-4">
                                    @csrf
                                    <div class="bg-white p-4 rounded-lg border border-indigo-200">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="text-sm">
                                                <div class="text-gray-500">Pay to Admin GCash:</div>
                                                <div class="font-bold text-lg text-gray-900">{{ \App\Models\SystemSetting::get('gcash_number', '09XX XXX XXXX') }}</div>
                                            </div>
                                            <img src="{{ \App\Models\SystemSetting::get_url('gcash_qr_path', asset('defaults/gcash-qr.png')) }}" alt="GCash QR" class="w-16 h-16 rounded border">
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="space-y-1">
                                                <x-input-label for="gcash_number" value="Your GCash Number" />
                                                <x-text-input id="gcash_number" name="gcash_number" type="text" class="w-full text-sm" required placeholder="09XX XXX XXXX" />
                                                <x-input-error :messages="$errors->get('gcash_number')" />
                                            </div>
                                            <div class="space-y-1">
                                                <x-input-label for="reference_number" value="Reference Number (13 digits)" />
                                                <x-text-input id="reference_number" name="reference_number" type="text" class="w-full text-sm" required maxlength="13" placeholder="1234 567 890 123" />
                                                <x-input-error :messages="$errors->get('reference_number')" />
                                            </div>
                                        </div>

                                        <div class="mt-4 space-y-1">
                                            <x-input-label for="payment_screenshot" value="Upload Payment Screenshot" />
                                            <input id="payment_screenshot" name="payment_screenshot" type="file" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required />
                                            <x-input-error :messages="$errors->get('payment_screenshot')" />
                                        </div>
                                    </div>

                                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 bg-indigo-600 border border-transparent rounded-lg font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-500 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        Pay Subscription (₱500.00)
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endif

                    <div class="mt-8 pt-6 border-t border-gray-100 flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            Need help? Contact the support team.
                        </div>
                        <a href="{{ route('seller.messages') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                            Messages & Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

