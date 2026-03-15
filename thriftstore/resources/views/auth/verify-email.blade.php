<x-guest-layout>
    <div class="seller-auth-wrap">
        <div class="seller-auth-head">
            <h1 class="seller-auth-title">{{ __('Verify your email') }}</h1>
            <p class="seller-auth-subtitle">
                {{ __('Before continuing, confirm your email address using the verification link sent to your inbox. We can send a new link anytime.') }}
            </p>
        </div>

        <div class="seller-auth-content">
            @if (session('status') == 'verification-link-sent')
                <div class="seller-auth-status">
                    {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                </div>
            @endif

            <div class="flex flex-col gap-3">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf

                    <button type="submit" class="seller-auth-button">
                        {{ __('Resend verification email') }}
                    </button>
                </form>

                <form method="POST" action="{{ route($logoutRoute ?? 'logout') }}" class="text-center">
                    @csrf

                    <button type="submit" class="seller-auth-secondary-btn">
                        {{ __('Log out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
