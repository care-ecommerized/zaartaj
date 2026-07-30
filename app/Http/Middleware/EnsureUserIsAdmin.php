<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Restrict a route to staff accounts.
     *
     * Guests are handed to the framework's authentication redirect and signed-in
     * customers get a 404, so the admin area never confirms its own existence to
     * someone probing URLs.
     *
     * The guest branch is handled here rather than left to the `auth` middleware
     * because Laravel's middleware priority sorting does not guarantee `auth` runs
     * first, and a guest reaching this check would otherwise see a 404 where a login
     * prompt belongs.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException;
        }

        abort_unless($user->is_admin === true, 404);

        return $next($request);
    }
}
