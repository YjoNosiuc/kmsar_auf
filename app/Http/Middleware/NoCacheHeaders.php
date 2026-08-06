<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Never apply no-store to guest auth pages — browsers discard the page
        // (including the CSRF token), which causes 419 "Page Expired" on login.
        if ($request->user() === null || $request->routeIs('login', 'register', 'register.store')) {
            return $response;
        }

        // Binary report downloads must not get no-store — some browsers/PDF readers
        // then save empty or unreadable files.
        if ($request->routeIs('reports.export', 'reports.download')) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (
            str_contains($contentType, 'application/pdf')
            || str_contains($contentType, 'spreadsheetml')
            || str_contains($contentType, 'application/vnd.ms-excel')
            || str_contains($contentType, 'application/octet-stream')
        ) {
            return $response;
        }

        $path = trim($request->path(), '/');
        if (in_array($path, ['login', 'register'], true)) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
