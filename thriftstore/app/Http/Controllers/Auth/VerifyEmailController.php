<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     * B1 - v1.3: redirect to correct dashboard (customer or seller).
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->redirectIntended($user);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->redirectIntended($user);
    }

    private function redirectIntended($user): RedirectResponse
    {
        $default = $user->seller
            ? route('seller.dashboard', [], false)
            : route('customer.dashboard', [], false);

        return redirect()->intended($default)->with('verified', 1);
    }
}
