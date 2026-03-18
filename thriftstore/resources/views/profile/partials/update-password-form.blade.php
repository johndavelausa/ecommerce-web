<section class="space-y-6">
    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div class="max-w-xl space-y-6">
            <div>
                <x-input-label for="update_password_current_password" :value="__('Current Password')" class="text-sm font-semibold text-gray-700 mb-1" />
                <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-gray-50 focus:!bg-white focus:!border-[#2d6c50] focus:!ring-0 transition-all duration-200" autocomplete="current-password" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password" :value="__('New Password')" class="text-sm font-semibold text-gray-700 mb-1" />
                <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-gray-50 focus:!bg-white focus:!border-[#2d6c50] focus:!ring-0 transition-all duration-200" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" :value="__('Confirm New Password')" class="text-sm font-semibold text-gray-700 mb-1" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full !rounded-xl !border-gray-200 !bg-gray-50 focus:!bg-white focus:!border-[#2d6c50] focus:!ring-0 transition-all duration-200" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="profile-primary-button">
                 {{ __('Update Password') }}
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-medium text-green-600 flex items-center gap-1.5"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Password Changed') }}
                </p>
            @endif
        </div>
    </form>
</section>
