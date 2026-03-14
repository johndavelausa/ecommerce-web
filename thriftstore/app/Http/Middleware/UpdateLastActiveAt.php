<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastActiveAt
{
    /**
     * Update last_active_at for the currently active area guard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->guardForRequest($request);
        $user = Auth::guard($guard)->user();

        if ($user) {
            $key = "last_active_at:{$guard}:{$user->id}";
            if (! Cache::has($key)) {
                Cache::put($key, true, now()->addMinutes(5));
                $user->forceFill(['last_active_at' => now()])->saveQuietly();
            }
        }

        return $next($request);
    }

    private function guardForRequest(Request $request): string
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return 'admin';
        }
        if ($request->is('seller') || $request->is('seller/*')) {
            return 'seller';
        }
        return 'web';
    }
}

