<?php

namespace App\Http\Middleware;

use App\Support\NavigationPermissions;
use Closure;
use Illuminate\Http\Request;

class NavigationAccess
{
    public function handle(Request $request, Closure $next)
    {
        $required = NavigationPermissions::requiredForRoute(
            $request->route()?->getName(),
            $request->route('type')
        );
        $user = $request->user();
        $allowed = !$required || $user->hasPermission($required);
        if (!$allowed && $required === 'view-loans') {
            $allowed = $user->hasAnyPermission(['create-loans', 'approve-loans', 'process-loan-payments']);
        }
        if (!$allowed && $required === 'view-cash-advances') {
            $allowed = $user->hasAnyPermission(['create-cash-advances', 'approve-cash-advances', 'process-cash-advance-payments']);
        }
        if (!$allowed && $required === 'view-hauling-liquidity') {
            $allowed = $user->hasPermission('manage-hauling-liquidity');
        }
        if (!$allowed && $required === 'view-planter-deductions') {
            $allowed = $user->hasPermission('manage-planter-deductions');
        }
        if (!$allowed && $required === 'create-loans') {
            $allowed = $user->hasPermission('process-loan-payments');
        }
        if (!$allowed && $required === 'create-cash-advances') {
            $allowed = $user->hasPermission('process-cash-advance-payments');
        }
        // Staff may approve / reject / activate below ₱50,000; the controller applies ApprovalPolicy by amount
        if (!$allowed && $required === 'approve-loans') {
            $allowed = $user->hasAnyPermission(['create-loans', 'process-loan-payments']);
        }
        if (!$allowed && $required === 'approve-cash-advances') {
            $allowed = $user->hasAnyPermission(['create-cash-advances', 'process-cash-advance-payments']);
        }
        if (!$allowed) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'You do not have permission to access this page.'], 403);
            }
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
