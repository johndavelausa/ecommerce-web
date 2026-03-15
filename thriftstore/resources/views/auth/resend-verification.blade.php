<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Didn\'t receive the verification email? Enter your email address below and we\'ll send you a new link.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to your email address.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('verification.resend.store') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">
                {{ __('Back to login') }}
            </a>
            <x-primary-button>
                {{ __('Resend Verification Email') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
