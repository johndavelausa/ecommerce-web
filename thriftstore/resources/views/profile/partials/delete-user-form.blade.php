<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('You can submit an account deletion request. An admin will review it and you will be notified by email once processed. Before requesting deletion, please download any data you wish to retain.') }}
        </p>
    </header>

    @if(session('status') === 'deletion-request-submitted')
        <p class="text-sm text-green-600">{{ __('Your deletion request has been submitted. You will be notified by email once an admin processes it.') }}</p>
    @endif

    @if(isset($deletionRequestPending) && $deletionRequestPending)
        <p class="text-sm text-amber-600">{{ __('You have a pending deletion request. An admin will process it and notify you by email.') }}</p>
    @else
        <form method="post" action="{{ route('profile.deletion-request') }}">
            @csrf
            <x-danger-button type="submit">{{ __('Request account deletion') }}</x-danger-button>
        </form>
    @endif
</section>
