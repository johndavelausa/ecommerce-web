<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow access if user is authenticated via web OR seller guard (B1 - v1.3).
 * Sets the resolved user so $request->user() works in verification controllers.
 */
class AuthenticateForVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            Auth::setUser(Auth::guard('web')->user());

            return $next($request);
        }

        if (Auth::guard('seller')->check()) {
            Auth::setUser(Auth::guard('seller')->user());

            return $next($request);
        }

        return redirect()->route('login');
    }
}
