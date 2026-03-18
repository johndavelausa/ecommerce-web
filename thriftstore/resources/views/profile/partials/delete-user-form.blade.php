<section class="space-y-6">
    <div class="bg-red-50/50 border border-red-100 rounded-2xl p-6">
        <div class="flex items-start gap-4">
            <div class="h-10 w-10 shrink-0 rounded-full bg-red-100 flex items-center justify-center text-red-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-red-900 mb-1">Delete Account</h3>
                <p class="text-sm text-red-700 leading-relaxed">
                    {{ __('You can submit an account deletion request. An admin will review it and you will be notified by email once processed. Before requesting deletion, please download any data you wish to retain.') }}
                </p>
                
                <div class="mt-6">
                    @if(session('status') === 'deletion-request-submitted')
                        <div class="flex items-center gap-2 text-sm font-bold text-green-700 bg-green-50 p-3 rounded-xl border border-green-100">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            {{ __('Your deletion request has been submitted.') }}
                        </div>
                    @elseif(isset($deletionRequestPending) && $deletionRequestPending)
                        <div class="flex items-center gap-2 text-sm font-bold text-amber-700 bg-amber-50 p-3 rounded-xl border border-amber-100">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('You have a pending deletion request.') }}
                        </div>
                    @else
                        <form method="post" action="{{ route('profile.deletion-request') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-red-200 transition-all active:transform active:scale-95">
                                {{ __('Request account deletion') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
