<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $user = Auth::user();

        if (! $user || $user->type !== $type) {
            abort(403, 'Unauthorized role.');
        }

        return $next($request);
    }
}

