<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idle session timeout for the operator console.
 * Uses SESSION_LIFETIME (minutes) as the max idle window.
 */
class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $lifetimeMinutes = max(1, (int) config('session.lifetime', 120));
        $lastActivity = $request->session()->get('operator.last_activity_at');
        $now = now()->timestamp;

        if (is_int($lastActivity) || (is_string($lastActivity) && ctype_digit($lastActivity))) {
            $idleSeconds = $now - (int) $lastActivity;
            if ($idleSeconds > ($lifetimeMinutes * 60)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->withErrors(['email' => 'Your session expired due to inactivity. Please sign in again.']);
            }
        }

        $request->session()->put('operator.last_activity_at', $now);

        return $next($request);
    }
}
