<section class="space-y-6">
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <x-input-label for="name" :value="__('Full Name')" class="text-sm font-semibold text-gray-700 mb-1" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-gray-50 focus:!bg-white focus:!border-[#2d6c50] focus:!ring-0 transition-all duration-200" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email Address')" class="text-sm font-semibold text-gray-700 mb-1" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-gray-50 focus:!bg-white focus:!border-[#2d6c50] focus:!ring-0 transition-all duration-200" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-3 p-3 rounded-lg bg-yellow-50 border border-yellow-100">
                        <p class="text-xs text-yellow-700">
                            {{ __('Your email address is unverified.') }}

                            <button form="send-verification" class="font-bold underline text-yellow-800 hover:text-yellow-900 ml-1">
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 font-bold text-xs text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="profile-primary-button">
                {{ __('Update Profile') }}
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-medium text-green-600 flex items-center gap-1.5"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Information Saved') }}
                </p>
            @endif
        </div>
    </form>
</section>
