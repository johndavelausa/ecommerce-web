<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * B1 - v1.3: Verify seller's new email; update email and clear pending_email.
 */
class VerifyNewEmailController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'id' => 'required|integer',
            'hash' => 'required|string',
        ]);

        $user = User::findOrFail($request->id);

        if (! $user->pending_email) {
            return redirect()->route('seller.login')->with('error', __('No pending email change.'));
        }

        if (sha1($user->pending_email) !== $request->hash) {
            return redirect()->route('seller.login')->with('error', __('Invalid verification link.'));
        }

        if (! $request->hasValidSignature()) {
            return redirect()->route('seller.login')->with('error', __('Verification link has expired.'));
        }

        $user->email = $user->pending_email;
        $user->pending_email = null;
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        event(new Verified($user));

        return redirect()->route('seller.login')
            ->with('status', __('Your email has been updated. Please log in with your new email address.'));
    }
}
