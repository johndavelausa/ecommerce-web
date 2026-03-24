<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Always allow admin routes and admin login
        if ($request->is('admin/*') || $request->is('admin') || $request->routeIs('admin.login', 'admin.login.store')) {
            return $next($request);
        }

        // Allow if already authenticated as admin (so admin can browse site in maintenance)
        if (auth('admin')->check()) {
            return $next($request);
        }

        if ((string) SystemSetting::get('maintenance_mode', '0') === '1') {
            $message = (string) SystemSetting::get('maintenance_message', 'We are currently under maintenance. Please check back soon.');
            return response()->view('maintenance', ['message' => $message], 503);
        }

        return $next($request);
    }
}
