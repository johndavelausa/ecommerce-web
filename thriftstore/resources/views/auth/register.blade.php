<x-guest-layout>
    <div class="customer-auth-shell">
        <div class="customer-auth-tabs">
            <a href="{{ route('login') }}" class="customer-auth-tab">{{ __('Login') }}</a>
            <a href="{{ route('register') }}" class="customer-auth-tab is-active">{{ __('Register') }}</a>
        </div>

        <div>
            <h1 class="customer-auth-title">{{ __('Become a Sustainable Shopper') }}</h1>
            <p class="customer-auth-subtitle">{{ __('Start your journey toward ethical fashion.') }}</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="customer-auth-form customer-auth-form-register">
            @csrf

            <div class="customer-auth-field">
                <x-input-label for="name" :value="__('Full Name')" />
                <x-text-input id="name" class="customer-auth-input" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="customer-auth-field">
                <x-input-label for="username" :value="__('Username')" />
                <x-text-input id="username" class="customer-auth-input" type="text" name="username" :value="old('username')" required autocomplete="username" />
                <x-input-error :messages="$errors->get('username')" class="mt-2" />
            </div>

            <div class="customer-auth-field">
                <x-input-label for="email" :value="__('Email Address')" />
                <x-text-input id="email" class="customer-auth-input" type="email" name="email" :value="old('email')" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="customer-auth-field">
                <x-input-label for="contact_number" :value="__('Contact Number')" />
                <x-text-input id="contact_number" class="customer-auth-input" type="text" name="contact_number" :value="old('contact_number')" required autocomplete="tel" />
                <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
            </div>

            <div class="customer-auth-field">
                <x-input-label for="address" :value="__('Delivery Address')" />
                <textarea id="address" name="address" required class="customer-auth-textarea">{{ old('address') }}</textarea>
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>

            <div class="customer-auth-row-2">
                <div class="customer-auth-field">
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" class="customer-auth-input" type="password" name="password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="customer-auth-field">
                    <x-input-label for="password_confirmation" :value="__('Confirm')" />
                    <x-text-input id="password_confirmation" class="customer-auth-input" type="password" name="password_confirmation" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>
            </div>

            <button type="submit" class="customer-auth-submit">{{ __('Create My Eco-Account') }}</button>

            <p class="customer-auth-bottom-link">
                <span>{{ __('Already an eco-member?') }}</span>
                <a href="{{ route('login') }}">{{ __('Sign in here') }}</a>
            </p>
        </form>
    </div>
</x-guest-layout>
