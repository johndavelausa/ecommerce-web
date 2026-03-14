<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
        @csrf

        <!-- Role -->
        <div>
            <x-input-label :value="__('Register As')" />
            <div class="mt-2 flex gap-4">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="role" value="customer" class="role-radio rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('role','customer') === 'customer' ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700">Customer</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="role" value="seller" class="role-radio rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('role') === 'seller' ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700">Seller</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        <!-- Name -->
        <div class="mt-4">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Username -->
        <div class="mt-4">
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Contact Number -->
        <div class="mt-4">
            <x-input-label for="contact_number" :value="__('Contact Number')" />
            <x-text-input id="contact_number" class="block mt-1 w-full" type="text" name="contact_number" :value="old('contact_number')" required autocomplete="tel" />
            <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
        </div>

        <!-- Delivery Address -->
        <div class="mt-4">
            <x-input-label for="address" :value="__('Delivery Address')" />
            <textarea id="address" name="address" required class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('address') }}</textarea>
            <x-input-error :messages="$errors->get('address')" class="mt-2" />
        </div>

        <!-- Seller-only fields -->
        <div id="seller-fields" class="mt-6 p-4 border border-gray-200 rounded-lg bg-white/50">
            <div class="text-sm text-gray-700 font-semibold">Seller registration fee (₱200 via GCash)</div>
            <div class="mt-2 text-sm text-gray-600">
                <div>GCash Number: <span class="font-medium">{{ \App\Models\SystemSetting::get('gcash_number', 'Not set') }}</span></div>
                <div class="mt-2">Scan QR:</div>
                <img class="mt-2 w-48 h-48 object-contain border rounded" src="{{ asset('storage/' . \App\Models\SystemSetting::get('gcash_qr_path', 'defaults/gcash-qr.png')) }}" alt="GCash QR">
            </div>

            <div class="mt-4">
                <x-input-label for="store_name" :value="__('Store Name (unique)')" />
                <x-text-input id="store_name" class="block mt-1 w-full" type="text" name="store_name" :value="old('store_name')" autocomplete="organization" />
                <x-input-error :messages="$errors->get('store_name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="store_description" :value="__('Store Description')" />
                <textarea id="store_description" name="store_description" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('store_description') }}</textarea>
                <x-input-error :messages="$errors->get('store_description')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="gcash_number" :value="__('GCash Number Used for Payment')" />
                <x-text-input id="gcash_number" class="block mt-1 w-full" type="text" name="gcash_number" :value="old('gcash_number')" autocomplete="tel" />
                <x-input-error :messages="$errors->get('gcash_number')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="reference_number" :value="__('GCash Reference Number (unique)')" />
                <x-text-input id="reference_number" class="block mt-1 w-full" type="text" name="reference_number" :value="old('reference_number')" />
                <x-input-error :messages="$errors->get('reference_number')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="payment_screenshot" :value="__('Upload Payment Screenshot')" />
                <input id="payment_screenshot" name="payment_screenshot" type="file" accept="image/*" class="block mt-1 w-full text-sm text-gray-700" />
                <x-input-error :messages="$errors->get('payment_screenshot')" class="mt-2" />
            </div>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>

    <script>
        (function () {
            const sellerFields = document.getElementById('seller-fields');
            const radios = document.querySelectorAll('input.role-radio');
            function sync() {
                const role = document.querySelector('input.role-radio:checked')?.value || 'customer';
                sellerFields.classList.toggle('hidden', role !== 'seller');
            }
            radios.forEach(r => r.addEventListener('change', sync));
            sync();
        })();
    </script>
</x-guest-layout>
