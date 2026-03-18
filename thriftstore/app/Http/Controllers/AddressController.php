<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    /**
     * Store a newly created address in storage.
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $isDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        $address = $user->addresses()->create($data + ['is_default' => $isDefault]);

        if ($isDefault) {
            $user->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        return back()->with('status', 'address-saved');
    }

    /**
     * Update the specified address in storage.
     */
    public function update(Request $request, Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $isDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        $address->update($data + ['is_default' => $isDefault]);

        if ($isDefault) {
             /** @var \App\Models\User $user */
            $user = $request->user();
            $user->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        return back()->with('status', 'address-updated');
    }

    /**
     * Remove the specified address from storage.
     */
    public function destroy(Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }

        // If deleting the default address, try to set another as default
        if ($address->is_default) {
             /** @var \App\Models\User $user */
            $user = Auth::user();
            $next = $user->addresses()->where('id', '!=', $address->id)->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        $address->delete();

        return back()->with('status', 'address-deleted');
    }

    /**
     * Set the specified address as default.
     */
    public function setDefault(Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }

         /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('status', 'address-set-default');
    }
}
