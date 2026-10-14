<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class LoginAuth
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        // For fetch/AJAX calls that expect JSON, never return an HTML redirect
        // (that is what causes "Unexpected token '<', "<!DOCTYPE "... is not valid JSON").
        $wantsJson = $request->expectsJson() || $request->ajax() || $request->wantsJson();

        if (Auth::guard('web')->check()) {
            /** @var User $user */
            $user = Auth::user();

            if (!$user->role) {
                if ($wantsJson) {
                    return response()->json(['message' => 'Invalid user role.'], 403);
                }
                return redirect()->route('getLogin')->with('error', 'Invalid user role.');
            }

            // Administrator and super_admin always have full access
            $isFullAccess = $user->role === 'Administrator' || $user->role === 'super_admin';

            // Check specific permissions if provided
            if (!empty($permissions)) {
                $hasAccess = $isFullAccess;

                if (!$hasAccess) {
                    // Check if user has any of the required permission slugs
                    $hasAccess = $user->permissions()
                        ->whereIn('slug', $permissions)
                        ->exists();
                }

                if (!$hasAccess) {
                    if ($wantsJson) {
                        return response()->json(['message' => 'You do not have permission to access this page.'], 403);
                    }
                    return redirect()->route('dashboard')
                        ->with('error', 'You do not have permission to access this page.');
                }
            }

        } else {
            if ($wantsJson) {
                return response()->json(['message' => 'Unauthenticated. Please sign in again.'], 401);
            }
            return redirect()->route('getLogin')
                ->with('error', 'Please sign in to access this page.');
        }

        $response = $next($request);
        
        // Cache prevention headers
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}