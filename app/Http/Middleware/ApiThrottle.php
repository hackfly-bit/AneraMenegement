<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApiThrottle
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);
        
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'Too Many Attempts.',
                'retry_after' => $this->availableIn($key),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $this->hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', $this->retriesLeft($key, $maxAttempts));
        $response->headers->set('X-RateLimit-Reset', time() + $this->availableIn($key));

        return $response;
    }

    /**
     * Resolve request signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request)
    {
        if ($user = $request->user()) {
            return 'api:user:' . $user->id;
        }

        if ($route = $request->route()) {
            return 'api:ip:' . $request->ip() . ':' . $route->getName();
        }

        return 'api:ip:' . $request->ip();
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return bool
     */
    protected function tooManyAttempts($key, $maxAttempts)
    {
        return Cache::get($key, 0) >= $maxAttempts;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     *
     * @param  string  $key
     * @param  int  $decayTime
     * @return void
     */
    protected function hit($key, $decayTime)
    {
        $hits = Cache::get($key, 0) + 1;
        Cache::put($key, $hits, $decayTime);
    }

    /**
     * Get the number of retries left for the given key.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return int
     */
    protected function retriesLeft($key, $maxAttempts)
    {
        return max(0, $maxAttempts - Cache::get($key, 0));
    }

    /**
     * Get the number of seconds until the key is available again.
     *
     * @param  string  $key
     * @return int
     */
    protected function availableIn($key)
    {
        return Cache::get($key . ':timer', time() + 60) - time();
    }
}