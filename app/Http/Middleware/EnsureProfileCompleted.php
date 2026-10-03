<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileCompleted
{
    /**
     * Keep accounts that haven't completed their profile away from the
     * dashboards — a freshly signed-up intern or an approved staff account
     * lands on the profile completion page instead until their form has
     * been saved at least once. Admins are never gated.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->needsProfileCompletion()) {
            return redirect()->route('profile-completion.edit');
        }

        return $next($request);
    }
}
