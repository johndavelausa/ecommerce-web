<x-guest-layout>
    <div class="customer-auth-shell">
        <div class="customer-auth-tabs">
            <a href="{{ route('login') }}" class="customer-auth-tab is-active">{{ __('Login') }}</a>
            <a href="{{ route('register') }}" class="customer-auth-tab">{{ __('Register') }}</a>
        </div>

        <div>
            <h1 class="customer-auth-title">{{ __('Welcome Back') }}</h1>
            <p class="customer-auth-subtitle">{{ __('Sign in to continue shopping your thrift finds.') }}</p>
            <x-auth-session-status class="customer-auth-feedback" :status="session('status')" />
        </div>

        <form method="POST" action="{{ route('login') }}" class="customer-auth-form">
            @csrf

            <div class="customer-auth-field">
                <x-input-label for="email" :value="__('Email Address')" />
                <x-text-input id="email" class="customer-auth-input" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="customer-auth-field">
                <div class="flex items-center justify-between gap-2">
                    <x-input-label for="password" :value="__('Password')" />
                    @if (Route::has('password.request'))
                        <a class="customer-auth-top-link" href="{{ route('password.request') }}">{{ __('Forgot Password?') }}</a>
                    @endif
                </div>
                <x-text-input id="password" class="customer-auth-input" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <label for="remember_me" class="customer-auth-remember inline-flex items-center gap-2">
                <input id="remember_me" type="checkbox" name="remember">
                <span>{{ __('Remember me on this device') }}</span>
            </label>

            <button type="submit" class="customer-auth-submit">{{ __('Sign In') }}</button>
        </form>
    </div>
</x-guest-layout>
