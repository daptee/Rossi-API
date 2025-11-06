<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CacheHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only add cache headers to successful GET requests
        if ($request->isMethod('GET') && $response instanceof JsonResponse && $response->getStatusCode() === 200) {
            $this->addCacheHeaders($request, $response);
        }

        return $response;
    }

    /**
     * Add appropriate cache headers based on the route
     *
     * @param Request $request
     * @param JsonResponse $response
     */
    private function addCacheHeaders(Request $request, JsonResponse $response): void
    {
        $path = $request->path();
        $maxAge = $this->getCacheMaxAge($path);

        if ($maxAge > 0) {
            // Add cache headers optimized for CloudFront
            $response->headers->set('Cache-Control', "public, max-age={$maxAge}");
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $maxAge));

            // Only add Vary header for routes that actually vary by these headers
            if ($this->shouldVaryByHeaders($path)) {
                $response->headers->set('Vary', 'Accept, Accept-Language');
            }

            // Add Last-Modified header (more CloudFront-friendly than ETag)
            $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s \G\M\T'));
        } else {
            // Prevent caching for admin/auth routes
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }

    /**
     * Get cache max age based on route pattern
     *
     * @param string $path
     * @return int Cache duration in seconds, 0 means no cache
     */
    private function getCacheMaxAge(string $path): int
    {
        // Admin routes - no cache
        if (str_contains($path, '/admin') || str_contains($path, '/auth')) {
            return 0;
        }

        // Product routes
        if ($path === 'api/product') {
            return 300; // 5 minutes for product listings
        }

        // Web content - 1 hour
        if (str_starts_with($path, 'api/web-content-')) {
            return 3600;
        }

        // Static data - 2 hours
        if ($path === 'api/provinces') {
            return 7200;
        }

        // Catalog - 15 minutes
        if ($path === 'api/catalog') {
            return 900;
        }

        // Default - no cache for unmatched routes
        return 0;
    }

    /**
     * Determine if the route should vary by Accept headers
     *
     * @param string $path
     * @return bool
     */
    private function shouldVaryByHeaders(string $path): bool
    {
        // Only vary by headers for routes that actually return different content based on these headers
        // Most API routes return JSON regardless of Accept header
        return false;
    }
}
