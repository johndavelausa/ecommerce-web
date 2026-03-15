<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     * B1 - v1.3: Redirect to correct dashboard; pass logout route for seller/web.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            $default = $request->user()->seller
                ? route('seller.dashboard', [], false)
                : route('customer.dashboard', [], false);
            return redirect()->intended($default);
        }

        $logoutRoute = $request->user()->seller ? 'seller.logout' : 'logout';

        return view('auth.verify-email', ['logoutRoute' => $logoutRoute]);
    }
}
