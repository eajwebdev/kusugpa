<?php

namespace App\Http\Middleware;

use App\Support\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;

class VerifyTurnstile
{
    public function handle(Request $request, Closure $next)
    {
        $turnstile = app(TurnstileVerifier::class);

        if (!$turnstile->enabled()) {
            return $next($request);
        }

        if ($turnstile->verify($request)) {
            return $next($request);
        }

        $admin = $request->routeIs('admin.postLogin');
        $message = $turnstile->configured()
            ? 'Human verification failed or expired. Please try again.'
            : 'Human verification is unavailable. Please contact an administrator.';

        return redirect()->route($admin ? 'admin.getLogin' : 'getLogin')
            ->with($admin ? 'admin_error' : 'error', $message)
            ->withInput($request->only('username'));
    }
}
