<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isActiveAdmin(), 403, 'This admin account is disabled.');

        return $next($request);
    }
}
