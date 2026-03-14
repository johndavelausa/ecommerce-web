<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $role = $request->input('role', 'customer');
        if (! in_array($role, ['customer', 'seller'], true)) {
            $role = 'customer';
        }

        $commonRules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'contact_number' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:2000'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:customer,seller'],
        ];

        $sellerRules = [
            'store_name' => ['required', 'string', 'max:255', 'unique:sellers,store_name'],
            'store_description' => ['nullable', 'string', 'max:5000'],
            'gcash_number' => ['required', 'string', 'max:50'],
            'reference_number' => ['required', 'string', 'max:100', 'unique:payments,reference_number'],
            'payment_screenshot' => ['required', 'image', 'max:5120'],
        ];

        $request->validate($role === 'seller' ? array_merge($commonRules, $sellerRules) : $commonRules);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'contact_number' => $request->contact_number,
            'address' => $request->address,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($role);

        if ($role === 'seller') {
            $seller = Seller::create([
                'user_id' => $user->id,
                'store_name' => $request->store_name,
                'store_description' => $request->store_description,
                'gcash_number' => $request->gcash_number,
                'status' => 'pending',
            ]);

            $screenshotPath = $request->file('payment_screenshot')->store('payments', 'public');

            Payment::create([
                'seller_id' => $seller->id,
                'type' => 'registration',
                'amount' => 200.00,
                'gcash_number' => $request->gcash_number,
                'reference_number' => $request->reference_number,
                'screenshot_path' => $screenshotPath,
                'status' => 'pending',
                'paid_at' => now(),
            ]);
        }

        event(new Registered($user));

        if ($role === 'seller') {
            Auth::guard('seller')->login($user);
            return redirect(route('seller.status', absolute: false));
        }

        Auth::guard('web')->login($user);
        return redirect('/');
    }
}
