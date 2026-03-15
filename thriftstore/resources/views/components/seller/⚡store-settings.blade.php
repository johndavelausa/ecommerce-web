<?php

use App\Models\Seller;
use App\Notifications\VerifyNewEmailNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $contact_number = '';
    public string $address = '';

    public string $store_name = '';
    public string $store_description = '';
    public string $gcash_number = '';

    public string $delivery_option = 'free';
    public string $delivery_fee = '';

    /** B2 v1.4 — Business hours (displayed on public store profile) */
    public string $business_hours = '';

    public bool $is_open = true;

    public bool $saved = false;
    public bool $emailVerificationSent = false;
    public ?string $pending_email = null;

    public function mount(): void
    {
        $user = Auth::guard('seller')->user();
        $seller = $user?->seller;

        $this->name = (string) ($user?->name ?? '');
        $this->email = (string) ($user?->email ?? '');
        $this->pending_email = $user && $user->pending_email ? (string) $user->pending_email : null;
        $this->contact_number = (string) ($user?->contact_number ?? '');
        $this->address = (string) ($user?->address ?? '');

        $this->store_name = (string) ($seller?->store_name ?? '');
        $this->store_description = (string) ($seller?->store_description ?? '');
        $this->gcash_number = (string) ($seller?->gcash_number ?? '');
        $this->delivery_option = (string) ($seller?->delivery_option ?? 'free');
        $this->delivery_fee = $seller && $seller->delivery_fee !== null ? (string) $seller->delivery_fee : '';
        $this->business_hours = (string) ($seller?->business_hours ?? '');
        $this->is_open = (bool) ($seller?->is_open ?? true);
    }

    public function save(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::guard('seller')->user();
        /** @var Seller|null $seller */
        $seller = $user?->seller;

        if (! $user || ! $seller) {
            abort(403);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'contact_number' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:2000'],
            'store_name' => ['required', 'string', 'max:255', 'unique:sellers,store_name,'.$seller->id],
            'store_description' => ['nullable', 'string', 'max:5000'],
            'business_hours' => ['nullable', 'string', 'max:1000'],
            'gcash_number' => ['nullable', 'string', 'max:50'],
            'delivery_option' => ['required', 'string', 'in:free,flat_rate,per_product'],
        ];
        if ($this->delivery_option === 'flat_rate') {
            $rules['delivery_fee'] = ['required', 'numeric', 'min:0'];
        } else {
            $rules['delivery_fee'] = ['nullable', 'numeric', 'min:0'];
        }
        $this->validate($rules);

        $user->update([
            'name' => $this->name,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
        ]);

        // B1 - v1.3: Seller email change requires verification of new email
        $newEmail = strtolower(trim($this->email));
        if ($newEmail !== strtolower((string) $user->email)) {
            $user->pending_email = $newEmail;
            $user->save();
            Notification::route('mail', $newEmail)->notify(new VerifyNewEmailNotification($user->id, $newEmail));
            $this->emailVerificationSent = true;
            $this->pending_email = $newEmail;
        } else {
            $this->emailVerificationSent = false;
        }

        $seller->update([
            'store_name' => $this->store_name,
            'store_description' => $this->store_description,
            'gcash_number' => $this->gcash_number,
            'delivery_option' => $this->delivery_option,
            'delivery_fee' => $this->delivery_option === 'flat_rate' && $this->delivery_fee !== '' ? $this->delivery_fee : null,
            'business_hours' => $this->business_hours !== '' ? trim($this->business_hours) : null,
            'is_open' => $this->is_open,
        ]);

        $this->saved = true;
    }
};
?>

<div class="max-w-3xl space-y-8">
    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <h3 class="text-lg font-medium text-gray-900">Profile</h3>

        @if($saved)
            <div class="rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">
                Changes saved.
            </div>
        @endif
        @if($emailVerificationSent)
            <div class="rounded-md bg-blue-50 border border-blue-200 px-3 py-2 text-sm text-blue-800">
                A verification link has been sent to <strong>{{ $email }}</strong>. Click the link in that email to update your login address. Until then, continue using your current email to sign in.
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" wire:model.defer="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @error('name') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Email (login)</label>
                <input type="email" wire:model.defer="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                       placeholder="your@email.com">
                <p class="mt-1 text-xs text-gray-500">Changing your email will require verifying the new address. We'll send a link to the new email.</p>
                @error('email') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
            @if($pending_email)
                <p class="text-xs text-amber-700">Pending: <strong>{{ $pending_email }}</strong>. Check that inbox for the verification link.</p>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700">Contact number</label>
                <input type="text" wire:model.defer="contact_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @error('contact_number') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Delivery address</label>
                <textarea wire:model.defer="address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                @error('address') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="text-lg font-medium text-gray-900">Store details</h3>
                @if(Auth::guard('seller')->user()?->seller?->is_verified ?? false)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">✓ Verified seller</span>
                @endif
            </div>
            <button type="button" wire:click="save" wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-indigo-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Save changes
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Store name</label>
                <input type="text" wire:model.defer="store_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @error('store_name') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Store description</label>
                <textarea wire:model.defer="store_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                @error('store_description') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Business hours</label>
                <textarea wire:model.defer="business_hours" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. Mon–Sat 8:00 AM – 5:00 PM, Closed Sunday"></textarea>
                <p class="mt-0.5 text-xs text-gray-500">Shown on your public store page. Optional.</p>
                @error('business_hours') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">GCash number (for customer payments)</label>
                <input type="text" wire:model.defer="gcash_number" class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @error('gcash_number') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="border-t pt-4 mt-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Delivery fee</h4>
                <p class="text-xs text-gray-500 mb-3">Choose how delivery is charged for your store. Customers see this at checkout.</p>
                <div class="space-y-3">
                    @foreach(\App\Models\Seller::deliveryOptionLabels() as $value => $label)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.defer="delivery_option" value="{{ $value }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">{{ $label }}</span>
                        </label>
                    @endforeach
                    @if($delivery_option === 'flat_rate')
                        <div class="pl-6">
                            <label class="block text-sm font-medium text-gray-700">Flat rate (₱) per order</label>
                            <input type="number" step="0.01" min="0" wire:model.defer="delivery_fee"
                                   class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="e.g. 50">
                            @error('delivery_fee') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                        </div>
                    @endif
                    @if($delivery_option === 'per_product')
                        <p class="text-xs text-gray-500 pl-6">Set a delivery fee on each product in Manage products.</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model.defer="is_open"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">
                        Store is open (products visible in catalog)
                    </span>
                </label>
                <span class="text-xs {{ $is_open ? 'text-green-600' : 'text-gray-500' }}">
                    {{ $is_open ? 'Customers can see and order your products.' : 'Store closed — products hidden but not deleted.' }}
                </span>
            </div>
        </div>
    </div>
</div>

