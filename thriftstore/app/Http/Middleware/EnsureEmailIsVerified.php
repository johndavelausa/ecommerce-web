<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect unverified users to the verification notice (B1 - v1.3).
 * Works with both web (customer) and seller guards.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your email address is not verified.'], 403);
            }
            return redirect()->route('login');
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your email address is not verified.'], 403);
            }

            return redirect()->route('verification.notice');
        }

        return $next($request);
    }

    private function resolveUser()
    {
        if (Auth::guard('web')->check()) {
            return Auth::guard('web')->user();
        }
        if (Auth::guard('seller')->check()) {
            return Auth::guard('seller')->user();
        }

        return null;
    }
}
