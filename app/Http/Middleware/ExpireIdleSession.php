<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ExpireIdleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessions = app(\App\Services\AuthenticationSessionService::class);
        $sessions->expireDue();
        if (Auth::check()) {
            $lastActivity = $request->session()->get('last_activity_at');
            $timeout = (int) config('session.lifetime', 120) * 60;

            $expired = Auth::viaRemember() || ($lastActivity !== null && now()->timestamp - (int) $lastActivity >= $timeout);
            if (!$expired && !$request->routeIs('logout')) $expired = !$sessions->touch($request);
            if ($expired) {
                $sessions->endCurrent($request, Auth::viaRemember() ? 'remember_expired' : 'idle_timeout');
                // Expire this browser session without revoking other devices' login tokens.
                Auth::logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Sesi berakhir setelah 2 jam tanpa aktivitas. Silakan masuk kembali.'], 401);
                }

                return redirect()->route('login', ['expired' => 1]);
            }

            if (!$request->routeIs('logout')) $request->session()->put('last_activity_at', now()->timestamp);
        }

        return $next($request);
    }
}
