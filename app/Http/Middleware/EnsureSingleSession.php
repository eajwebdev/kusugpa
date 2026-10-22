<?php

namespace App\Http\Middleware;

use App\Support\SingleSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSingleSession
{
    public function handle(Request $request, Closure $next)
    {
        $guard = Auth::guard('web');

        if ($guard->check() && !SingleSession::isCurrent($guard->user(), $request)) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Your account was signed in on another device. Please sign in again.';

            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => $message], 401);
            }

            return redirect()->route('getLogin')->with('error', $message);
        }

        return $next($request);
    }
}
