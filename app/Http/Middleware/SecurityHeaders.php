<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua respons:
 *  - Content-Security-Policy : skrip hanya dari domain sendiri atau yang bernonce
 *    (menutup dampak XSS), tanpa framing, form hanya ke domain sendiri.
 *  - X-Frame-Options / frame-ancestors : cegah clickjacking.
 *  - X-Content-Type-Options    : cegah MIME sniffing.
 *  - Referrer-Policy / Permissions-Policy : kurangi kebocoran data & akses perangkat.
 *  - HSTS : hanya bila diakses lewat HTTPS.
 * Nonce dibagikan ke Blade sebagai $cspNonce untuk tag <script> inline.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        $headers = $response->headers;
        $headers->set('Content-Security-Policy', $csp);
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
