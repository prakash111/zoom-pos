<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $cspDirectives = [
            "default-src 'self' https: data: blob:",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.tailwindcss.com https://checkout.razorpay.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.tailwindcss.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https: wss: ws:",
            "frame-src 'self' https://api.razorpay.com",
            "frame-ancestors 'self' https://license.zoomnearby.com https://*.zoomnearby.com",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        $referer = (string)$request->header('Referer', '');
        if (!str_contains($referer, 'license.zoomnearby.com') && !str_contains($referer, 'zoomnearby.com')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        } else {
            $response->headers->remove('X-Frame-Options');
        }
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $origin = $request->header('Origin');
        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With, X-XSRF-TOKEN');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        return $response;
    }
}
