<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Browser security headers on every response
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        // SAMEORIGIN, not DENY: PDF previews open in an iframe of this site (layouts/main kpGlobalPdfFrame)
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        // HTTPS can end at the host's CDN, so also go by the configured app URL; browsers ignore HSTS over plain HTTP
        if ($request->isSecure() || str_starts_with((string) config('app.url'), 'https://')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // Do not advertise the PHP version
        $headers->remove('X-Powered-By');
        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
