<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || ! $request->user()->is_active) {
            return response()->json([
                'message' => 'Unauthenticated or inactive user',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}