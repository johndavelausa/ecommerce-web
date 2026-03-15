<x-app-layout>
    <div class="seller-login-body">
        <div class="seller-login-shell">
            <div class="seller-login-card">
                <div class="seller-login-head">
                    <h2 class="seller-login-title">Seller Login</h2>
                    <p class="seller-login-subtitle">Manage your shop and listings in one place.</p>
                </div>

                <div class="seller-login-feedback">
                    <x-auth-session-status class="seller-login-status" :status="session('status')" />
                    @if (session('error'))
                        <div class="seller-login-error">
                            {{ session('error') }}
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('seller.login.store') }}" class="seller-login-form">
                    @csrf

                    <div class="seller-login-field seller-login-field-lg">
                        <x-input-label for="email" :value="__('Username or Email')" />
                        <x-text-input id="email" class="seller-login-input block mt-2 w-full" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div class="seller-login-field seller-login-field-lg">
                        <div class="seller-login-label-row">
                            <x-input-label for="password" :value="__('Password')" />
                            @if (Route::has('password.request'))
                                <a class="seller-login-link seller-login-forgot" href="{{ route('password.request', ['intended' => 'seller']) }}">
                                    {{ __('Forgot Password?') }}
                                </a>
                            @endif
                        </div>
                        <div class="seller-login-password-wrap mt-2">
                            <x-text-input id="password" class="seller-login-input block w-full seller-login-password-input" type="password" name="password" required autocomplete="current-password" />
                            <span class="seller-login-password-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                            </span>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="seller-login-remember">
                        <label for="remember" class="inline-flex items-center">
                            <input id="remember" type="checkbox" class="seller-login-checkbox" name="remember">
                            <span class="ms-2 text-sm text-gray-600">{{ __('Remember me on this device') }}</span>
                        </label>
                    </div>

                    <button type="submit" class="seller-login-button">
                        <span>{{ __('Login to Dashboard') }}</span>
                    </button>

                    <div class="seller-login-divider"></div>

                    <p class="seller-login-register-cta">
                        <span>New to Ukay Hub?</span>
                        <a class="seller-login-link seller-login-register-link" href="{{ route('seller.register') }}">{{ __('Register as a Seller') }}</a>
                    </p>

                    <div class="seller-login-or">OR</div>

                    <p class="seller-login-customer-cta">
                        <a class="seller-login-link" href="{{ route('login') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            <span>{{ __('Continue as Customer') }}</span>
                        </a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
