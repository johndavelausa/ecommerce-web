<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportException;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     * Stores intended login (admin/seller) in session so we can redirect there after reset (A4 - v1.3).
     */
    public function create(Request $request): View
    {
        $intended = $request->query('intended');
        if (in_array($intended, ['admin', 'seller'], true)) {
            session(['password_reset_intended' => $intended]);
        }

        $backRoute = match ($intended) {
            'admin' => ['admin.login', __('Back to Admin login')],
            'seller' => ['seller.login', __('Back to Seller login')],
            default => ['login', __('Back to login')],
        };

        return view('auth.forgot-password', ['intended' => $intended, 'backRoute' => $backRoute]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            return $status == Password::RESET_LINK_SENT
                ? back()->with('status', __($status))
                : back()->withInput($request->only('email'))
                    ->withErrors(['email' => __($status)]);
        } catch (TransportException $e) {
            Log::error('Password reset email failed: '.$e->getMessage());

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('We could not send the password reset link. Please try again later or contact support.')]);
        }
    }
}
