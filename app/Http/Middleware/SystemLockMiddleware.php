<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SystemSetting;

class SystemLockMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Skip for admin routes
        if ($request->is('system/*') || $request->is('system/auth/*')) {
            return $next($request);
        }

        // Skip for login, logout
        if ($request->is('login') || $request->is('logout') || $request->is('/')) {
            return $next($request);
        }

        $wantsJson = $request->expectsJson() || $request->ajax() || $request->wantsJson();

        $lockedResponse = function (string $message, string $type) use ($request, $wantsJson) {
            // fetch() with Accept: application/json must never receive an HTML
            // error view — that is the "<!DOCTYPE ... is not valid JSON" crash.
            if ($wantsJson) {
                return response()->json(['message' => $message], 503);
            }
            return response()->view('errors.system-locked', [
                'message' => $message,
                'type' => $type
            ], 503);
        };

        // Check if system is locked
        if (SystemSetting::get('system_locked', false)) {
            $lockReason = SystemSetting::get('lock_reason', 'System is currently locked.');
            return $lockedResponse($lockReason, 'locked');
        }

        // Check subscription status
        if (SystemSetting::get('subscription_status') === 'inactive') {
            $dueDate = SystemSetting::get('subscription_due_date', 'N/A');
            return $lockedResponse("System access suspended due to unpaid subscription. Payment was due on {$dueDate}.", 'payment');
        }

        // Check date-range lock
        $lockStart = SystemSetting::get('lock_start_date');
        $lockEnd = SystemSetting::get('lock_end_date');
        
        if ($lockStart && $lockEnd) {
            $now = now();
            $start = \Carbon\Carbon::parse($lockStart);
            $end = \Carbon\Carbon::parse($lockEnd);
            
            if ($now->between($start, $end)) {
                $lockReason = SystemSetting::get('lock_reason', 'System is locked for maintenance.');
                return $lockedResponse($lockReason . " Locked from {$lockStart} to {$lockEnd}.", 'scheduled');
            }
        }

        // Check maintenance mode
        if (SystemSetting::get('maintenance_mode', false)) {
            $maintenanceMessage = SystemSetting::get('maintenance_message', 'System is under maintenance.');
            return $lockedResponse($maintenanceMessage, 'maintenance');
        }

        return $next($request);
    }
}