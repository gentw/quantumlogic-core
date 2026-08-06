<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, string $feature)
    {
        // Per-plan gating only means something while plan-tier subscriptions exist.
        // With the module retired the gate allows everything; the middleware stays
        // wired so re-enabling is just the flag.
        if (! config('features.subscription_plans')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->hasFeature($feature)) {
            return response()->json(['error' => 'Upgrade to access this feature'], 403);
        }

        return $next($request);
    }
}
