<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * B1 - v1.3: Allow guests to request a new verification email by entering their email.
 */
class ResendVerificationController extends Controller
{
    public function create(): View
    {
        return view('auth.resend-verification');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // Always show success to avoid email enumeration
        return back()->with('status', 'verification-link-sent');
    }
}
