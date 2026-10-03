<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Restrict a route (or route group) to one or more roles.
     *
     * Usage in routes: ->middleware('role:admin')
     *                  ->middleware('role:admin,intern')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            // Remember where they were headed so that after signing back in they
            // land there again — this is what lets the unattended kiosk station
            // return to the scan page on its own after any rare re-login.
            return redirect()->guest(route('login'));
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'You are not authorized to access this page.');
        }

        if (! $user->is_active) {
            auth()->logout();

            return redirect()->route('login')
                ->withErrors(['login' => 'Your account has been deactivated. Please contact the administrator.']);
        }

        return $next($request);
    }
}
