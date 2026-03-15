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
        if ($request->is('admin/*') || $request->is('admin') || auth('admin')->check()) {
            return $next($request);
        }

        if ((string) SystemSetting::get('maintenance_mode', '0') === '1') {
            $message = (string) SystemSetting::get('maintenance_message', 'We are currently under maintenance. Please check back soon.');
            return response()->view('maintenance', ['message' => $message], 503);
        }

        return $next($request);
    }
}
