<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerIsApproved
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('seller')->user();
        if (! $user) {
            return redirect()->route('seller.login');
        }

        if (! method_exists($user, 'hasRole') || ! $user->hasRole('seller')) {
            return abort(403);
        }

        $seller = $user->seller;
        if (! $seller || $seller->status !== 'approved' || $seller->subscription_status !== 'active') {
            return redirect()->route('seller.status');
        }

        return $next($request);
    }
}
