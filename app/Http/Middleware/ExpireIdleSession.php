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
        // Global expiry logging is handled by sessions:expire-log (scheduled every
        // minute), rather than making each cashier request sweep every user's sessions.
        if (Auth::check()) {
            $restoreFailed = Auth::viaRemember() && !$sessions->restorePersistent($request);
            $keepSignedIn = (bool) $request->session()->get('keep_signed_in', false);
            $lastActivity = $request->session()->get('last_activity_at');
            $timeout = (int) config('session.lifetime', 120) * 60;

            $inactive = $keepSignedIn && $request->user()->getAttribute('is_active') === false;
            $expired = $inactive || $restoreFailed || (!$keepSignedIn && $lastActivity !== null && now()->timestamp - (int) $lastActivity >= $timeout);
            if (!$expired && !$request->routeIs('logout')) $expired = !$sessions->touch($request);
            if ($expired) {
                if (!$restoreFailed) $sessions->endCurrent($request, $inactive ? 'account_disabled' : (Auth::viaRemember() ? 'remember_expired' : 'idle_timeout'));
                $sessions->forgetPersistentCookie();
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
