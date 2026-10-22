<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureServiceEnabled
{
    public function handle(Request $request, Closure $next, string $service): Response
    {
        if (!$request->user()?->canAccessService($service)) {
            abort(403, 'This service is not enabled for your account.');
        }

        return $next($request);
    }
}
