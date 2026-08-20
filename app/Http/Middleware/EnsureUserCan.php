<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCan
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if (! $request->user()->can($permission)) {
            abort(403);
        }

        return $next($request);
    }
}
