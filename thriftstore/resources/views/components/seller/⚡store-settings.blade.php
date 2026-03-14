<?php

use App\Models\Seller;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $contact_number = '';
    public string $address = '';

    public string $store_name = '';
    public string $store_description = '';
    public string $gcash_number = '';

    public bool $is_open = true;

    public bool $saved = false;

    public function mount(): void
    {
        $user = Auth::guard('seller')->user();
        $seller = $user?->seller;

        $this->name = (string) ($user?->name ?? '');
        $this->contact_number = (string) ($user?->contact_number ?? '');
        $this->address = (string) ($user?->address ?? '');

        $this->store_name = (string) ($seller?->store_name ?? '');
        $this->store_description = (string) ($seller?->store_description ?? '');
        $this->gcash_number = (string) ($seller?->gcash_number ?? '');
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

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:2000'],
            'store_name' => ['required', 'string', 'max:255', 'unique:sellers,store_name,'.$seller->id],
            'store_description' => ['nullable', 'string', 'max:5000'],
            'gcash_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update([
            'name' => $this->name,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
        ]);

        $seller->update([
            'store_name' => $this->store_name,
            'store_description' => $this->store_description,
            'gcash_number' => $this->gcash_number,
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

        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" wire:model.defer="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @error('name') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

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
            <h3 class="text-lg font-medium text-gray-900">Store details</h3>
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
                <label class="block text-sm font-medium text-gray-700">GCash number (for customer payments)</label>
                <input type="text" wire:model.defer="gcash_number" class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @error('gcash_number') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
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

