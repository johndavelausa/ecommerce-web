<x-guest-layout>
    <div class="seller-auth-wrap">
        <div class="seller-auth-head">
            @if(isset($backRoute) && $backRoute)
                <a href="{{ route($backRoute[0]) }}" class="seller-auth-back-link">
                    {{ $backRoute[1] }}
                </a>
            @endif

            <h1 class="seller-auth-title">{{ __('Reset your password') }}</h1>
            <p class="seller-auth-subtitle">
                {{ __('Enter your email and we will send a secure reset link so you can access your seller account again.') }}
            </p>
        </div>

        <div class="seller-auth-content">
            @if (session('status'))
                <div class="seller-auth-status">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="seller-auth-label">{{ __('Email') }}</label>
                    <input id="email" class="seller-auth-input" type="email" name="email" value="{{ old('email') }}" required autofocus />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="pt-1">
                    <button type="submit" class="seller-auth-button">
                        {{ __('Send reset link') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
