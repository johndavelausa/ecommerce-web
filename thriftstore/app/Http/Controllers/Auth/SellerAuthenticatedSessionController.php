<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerAuthenticatedSessionController extends Controller
{
    /**
     * Display the seller login view.
     */
    public function create()
    {
        if (Auth::guard('seller')->check()) {
            $user = Auth::guard('seller')->user();
            $seller = $user->seller;
            if ($seller && $seller->status === 'approved') {
                return redirect()->route('seller.dashboard');
            }

            return redirect()->route('seller.status');
        }

        return view('auth.seller-login');
    }

    /**
     * Handle an incoming seller authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('seller')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::guard('seller')->user();
        if (! $user->hasRole('seller')) {
            Auth::guard('seller')->logout();
            throw ValidationException::withMessages([
                'email' => __('Only seller accounts can sign in here.'),
            ]);
        }

        $seller = $user->seller;
        if ($seller && $seller->status === 'approved') {
            return redirect()->intended(route('seller.dashboard'));
        }

        return redirect()->intended(route('seller.status'));
    }

    /**
     * Destroy the seller authenticated session (only seller guard; other guards unchanged).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('seller')->logout();

        return redirect()->route('seller.login');
    }
}
