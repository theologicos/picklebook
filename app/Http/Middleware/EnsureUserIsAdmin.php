<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps players out of the admin area. Use it after the "auth" middleware: ['auth', 'admin'].
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, __('This area is for the court owner only.'));

        return $next($request);
    }
}
