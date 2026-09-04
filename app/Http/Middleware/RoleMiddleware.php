<?php

namespace App\Http\Middleware;

use App\Support\FriendlyExceptionResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Restrict access to users who have at least one of the given Spatie roles.
     *
     * Usage: middleware('role:faculty|viewer')
     * Optional guard: middleware('role:super_admin,web')
     *
     * @param  string  $roles  Pipe-separated role names (e.g. faculty|viewer)
     */
    public function handle(Request $request, Closure $next, string $roles, ?string $guard = null): Response
    {
        $authUser = $guard !== null
            ? auth($guard)->user()
            : $request->user();

        if (! $authUser) {
            return redirect()->guest(route('login'));
        }

        $roleNames = array_values(array_filter(array_map('trim', explode('|', $roles))));

        if ($roleNames === [] || ! $authUser->hasAnyRole($roleNames)) {
            return FriendlyExceptionResponse::forbidden(
                $request,
                __('You do not have access to that page.')
            );
        }

        return $next($request);
    }
}
