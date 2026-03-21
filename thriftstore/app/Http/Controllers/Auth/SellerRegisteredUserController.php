<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class SellerRegisteredUserController extends Controller
{
    /**
     * Check whether a seller registration email already exists.
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $email = strtolower((string) $request->query('email', ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['exists' => false]);
        }

        $exists = User::query()->where('email', $email)->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Check whether a seller store name already exists.
     */
    public function checkStoreName(Request $request): JsonResponse
    {
        $storeName = trim((string) $request->query('store_name', ''));

        if ($storeName === '') {
            return response()->json(['exists' => false]);
        }

        $exists = Seller::query()->whereRaw('LOWER(store_name) = ?', [mb_strtolower($storeName)])->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Display the seller registration view (seller/register).
     */
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('seller')->check()) {
            $seller = Auth::guard('seller')->user()->seller;
            if ($seller && $seller->status === 'approved') {
                return redirect()->route('seller.dashboard');
            }
            return redirect()->route('seller.status');
        }
        return view('auth.seller-register');
    }

    /**
     * Handle an incoming seller registration request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'reference_number' => preg_replace('/\D+/', '', (string) $request->input('reference_number', '')),
        ]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'contact_number' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:2000'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'store_name' => ['required', 'string', 'max:255', 'unique:sellers,store_name'],
            'store_description' => ['nullable', 'string', 'max:5000'],
            'gcash_number' => ['required', 'string', 'max:50'],
            'reference_number' => ['required', 'digits:13', 'unique:payments,reference_number'],
            'payment_screenshot' => ['required', 'image', 'max:5120'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'contact_number' => $request->contact_number,
            'address' => $request->address,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('seller');

        $seller = Seller::create([
            'user_id' => $user->id,
            'store_name' => $request->store_name,
            'store_description' => $request->store_description,
            'gcash_number' => $request->gcash_number,
            'status' => 'pending',
            'subscription_status' => 'lapsed',
        ]);

        $screenshotPath = $request->file('payment_screenshot')->store('payments', 'public');

        Payment::create([
            'seller_id' => $seller->id,
            'type' => 'registration',
            'amount' => 700.00,
            'gcash_number' => $request->gcash_number,
            'reference_number' => $request->reference_number,
            'screenshot_path' => $screenshotPath,
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        Auth::guard('seller')->login($user);
        return redirect()->route('seller.status');
    }
}
