<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, string $feature)
    {
        $user = $request->user();

        if (!$user || !$user->hasFeature($feature)) {
            return response()->json(['error' => 'Upgrade to access this feature'], 403);
        }

        return $next($request);
    }

}
